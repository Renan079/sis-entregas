<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentoImportacaoService
{
    public function __construct(
        private OcrTesseractService $ocr,
        private NotaConstrucaoParser $parser
    ) {
    }

    public function gerarPreview(
        array $arquivos,
        int $usuarioId
    ): array {
        if ($arquivos === []) {
            throw ValidationException::withMessages([
                'arquivos' => [
                    'Selecione pelo menos uma foto ou um PDF.',
                ],
            ]);
        }

        $tabela = $this->tabelaDocumentos();

        $metadadosArquivos = [];
        $hashes = [];

        foreach (
            array_values($arquivos)
            as $indice => $arquivo
        ) {
            if (! $arquivo instanceof UploadedFile) {
                continue;
            }

            $hash = hash_file(
                'sha256',
                $arquivo->getRealPath()
            );

            $hashes[] = $hash;

            $metadadosArquivos[] = [
                'ordem' => $indice + 1,
                'nome_original' =>
                    $arquivo->getClientOriginalName(),
                'extensao' => strtolower(
                    $arquivo->getClientOriginalExtension()
                ),
                'hash_sha256' => $hash,
            ];
        }

        if ($metadadosArquivos === []) {
            throw ValidationException::withMessages([
                'arquivos' => [
                    'Nenhum arquivo válido foi recebido.',
                ],
            ]);
        }

        /*
         * Para várias fotos, a ordem faz parte do documento.
         * Assim, o conjunto inteiro possui um único hash.
         */
        $hashDocumento = hash(
            'sha256',
            implode('|', $hashes)
        );

        $existente = DB::table($tabela)
            ->where('hash_sha256', $hashDocumento)
            ->first();

        if ($existente) {
            throw ValidationException::withMessages([
                'arquivos' => [
                    'Este mesmo documento já foi enviado anteriormente.',
                ],
            ]);
        }

        $somenteUmPdf =
            count($metadadosArquivos) === 1 &&
            $metadadosArquivos[0]['extensao'] === 'pdf';

        $tipoArquivo =
            $somenteUmPdf
                ? 'PDF'
                : 'FOTO';

        $diretorio = sprintf(
            'documentos-importados/%s/%s/%s',
            now()->format('Y'),
            now()->format('m'),
            Str::uuid()
        );

        $pathsSalvos = [];

        try {
            foreach (
                array_values($arquivos)
                as $indice => $arquivo
            ) {
                $extensao = strtolower(
                    $arquivo->getClientOriginalExtension()
                );

                $nome = sprintf(
                    '%02d-%s.%s',
                    $indice + 1,
                    Str::uuid(),
                    $extensao
                );

                $path = $arquivo->storeAs(
                    $diretorio,
                    $nome,
                    'local'
                );

                $pathsSalvos[] = $path;
                $metadadosArquivos[$indice]['path'] = $path;
            }

            $nomeDocumento =
                count($metadadosArquivos) === 1
                    ? $metadadosArquivos[0]['nome_original']
                    : sprintf(
                        'Nota com %d fotos',
                        count($metadadosArquivos)
                    );

            $agora = now();

            $documentoId = DB::table($tabela)
                ->insertGetId([
                    'usuario_id' => $usuarioId,
                    'tipo_arquivo' => $tipoArquivo,
                    'nome_arquivo' => $nomeDocumento,
                    /*
                     * Mantemos compatibilidade com a tabela atual.
                     * Em múltiplas fotos, arquivo_path aponta para a
                     * primeira página; todos os paths ficam também
                     * gravados no JSON de origem.
                     */
                    'arquivo_path' => $pathsSalvos[0],
                    'hash_sha256' => $hashDocumento,
                    'status' => 'PROCESSANDO',
                    'dados_extraidos' => json_encode(
                        [
                            'origem' => [
                                'tipo' => $tipoArquivo,
                                'quantidade_arquivos' =>
                                    count($metadadosArquivos),
                                'arquivos' =>
                                    $metadadosArquivos,
                            ],
                        ],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
        } catch (Throwable $e) {
            foreach ($pathsSalvos as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }

        try {
            $caminhosAbsolutos = collect(
                $pathsSalvos
            )
                ->map(
                    fn (string $path) =>
                        Storage::disk('local')->path($path)
                )
                ->all();

            $ocr = $this->ocr
                ->extrairTextoDeArquivos(
                    $caminhosAbsolutos
                );

            $texto = $ocr['texto'];
            $dados = $this->parser
                ->interpretar($texto);

            $this->adicionarAvisoSobrePossivelSobreposicao(
                $dados
            );

            $sugestoes = [
                'cliente' =>
                    $this->buscarClienteSugerido(
                        $dados['cliente']
                    ),

                'regiao' =>
                    $this->buscarRegiaoSugerida(
                        $dados['endereco']
                    ),

                'pedido_ja_existe' =>
                    $dados['numero_pedido']
                        ? DB::table('entregas')
                            ->where(
                                'numero_pedido',
                                $dados['numero_pedido']
                            )
                            ->exists()
                        : false,
            ];

            if ($sugestoes['pedido_ja_existe']) {
                $dados['avisos'][] =
                    'Já existe uma entrega com este número de pedido.';
            }

            DB::table($tabela)
                ->where('id', $documentoId)
                ->update([
                    'status' => 'AGUARDANDO_CONFIRMACAO',
                    'texto_extraido' => $texto,
                    'dados_extraidos' => json_encode(
                        [
                            'origem' => [
                                'tipo' => $tipoArquivo,
                                'quantidade_arquivos' =>
                                    count($metadadosArquivos),
                                'arquivos' =>
                                    $metadadosArquivos,
                                'paginas_ocr' =>
                                    $ocr['paginas'],
                            ],
                            'dados' => $dados,
                            'sugestoes' => $sugestoes,
                        ],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'erro_processamento' => null,
                    'updated_at' => now(),
                ]);

            return [
                'documento' => [
                    'id' => $documentoId,
                    'nome_arquivo' => $nomeDocumento,
                    'tipo_arquivo' => $tipoArquivo,
                    'quantidade_arquivos' =>
                        count($metadadosArquivos),
                    'status' => 'AGUARDANDO_CONFIRMACAO',
                ],
                'dados' => $dados,
                'sugestoes' => $sugestoes,
            ];
        } catch (Throwable $e) {
            DB::table($tabela)
                ->where('id', $documentoId)
                ->update([
                    'status' => 'ERRO',
                    'erro_processamento' =>
                        $e->getMessage(),
                    'updated_at' => now(),
                ]);

            throw ValidationException::withMessages([
                'arquivos' => [
                    'Não foi possível ler este documento. Confira as fotos/PDF e tente novamente. Detalhe: '
                    . $e->getMessage(),
                ],
            ]);
        }
    }

    public function confirmar(
        int $documentoId,
        array $dados,
        int $usuarioId
    ): array {
        $tabela = $this->tabelaDocumentos();

        return DB::transaction(function () use (
            $tabela,
            $documentoId,
            $dados,
            $usuarioId
        ) {
            $documento = DB::table($tabela)
                ->where('id', $documentoId)
                ->lockForUpdate()
                ->first();

            if (! $documento) {
                throw ValidationException::withMessages([
                    'documento' => [
                        'Documento de importação não encontrado.',
                    ],
                ]);
            }

            if ($documento->status === 'CONFIRMADO') {
                throw ValidationException::withMessages([
                    'documento' => [
                        'Este documento já foi importado.',
                    ],
                ]);
            }

            if ($documento->status !== 'AGUARDANDO_CONFIRMACAO') {
                throw ValidationException::withMessages([
                    'documento' => [
                        'O documento ainda não está pronto para importação.',
                    ],
                ]);
            }

            if (
                DB::table('entregas')
                    ->where(
                        'numero_pedido',
                        $dados['numero_pedido']
                    )
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'numero_pedido' => [
                        'Já existe uma entrega com este número de pedido.',
                    ],
                ]);
            }

            $clienteId =
                $this->resolverCliente($dados);

            $agora = now();

            $entregaId = DB::table('entregas')
                ->insertGetId([
                    'numero_pedido' =>
                        $dados['numero_pedido'],
                    'documento_importado_id' =>
                        $documentoId,
                    'cliente_id' => $clienteId,
                    'regiao_id' => $dados['regiao_id'],
                    'cliente_nome' =>
                        $dados['cliente']['nome'],
                    'cliente_apelido' =>
                        $dados['cliente']['apelido'] ?? null,
                    'cliente_telefone' =>
                        $dados['cliente']['telefone'] ?? null,
                    'endereco' => $dados['endereco'],
                    'referencia' =>
                        $dados['referencia'] ?? null,
                    'prioridade' =>
                        $dados['prioridade'],
                    'status' => 'PENDENTE',
                    'observacao' =>
                        $dados['observacao'] ?? null,
                    'criado_por' => $usuarioId,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);

            foreach ($dados['itens'] as $item) {
                DB::table('entrega_items')->insert([
                    'entrega_id' => $entregaId,
                    'codigo_produto' =>
                        $item['codigo_produto'] ?? null,
                    'descricao_produto' =>
                        $item['descricao_produto'],
                    'unidade' =>
                        $item['unidade'] ?? null,
                    'quantidade_solicitada' =>
                        $item['quantidade_solicitada'],
                    'observacao' =>
                        $item['observacao'] ?? null,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            $dadosAnteriores = json_decode(
                (string) ($documento->dados_extraidos ?? '{}'),
                true
            );

            DB::table($tabela)
                ->where('id', $documentoId)
                ->update([
                    'status' => 'CONFIRMADO',
                    'dados_extraidos' => json_encode(
                        [
                            'origem' =>
                                $dadosAnteriores['origem'] ?? null,
                            'entrega_id' => $entregaId,
                            'dados_confirmados' => $dados,
                        ],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'updated_at' => now(),
                ]);

            return [
                'entrega_id' => $entregaId,
                'numero_pedido' =>
                    $dados['numero_pedido'],
                'status' => 'PENDENTE',
            ];
        });
    }

    private function resolverCliente(array $dados): int
    {
        if (! empty($dados['cliente_id'])) {
            return (int) $dados['cliente_id'];
        }

        $cliente = $dados['cliente'];

        if (! empty($cliente['codigo_externo'])) {
            $existente = DB::table('clientes')
                ->where(
                    'codigo_externo',
                    $cliente['codigo_externo']
                )
                ->first();

            if ($existente) {
                return (int) $existente->id;
            }
        }

        $agora = now();

        return DB::table('clientes')->insertGetId([
            'codigo_externo' =>
                $cliente['codigo_externo'] ?? null,
            'nome' => $cliente['nome'],
            'apelido' => $cliente['apelido'] ?? null,
            'telefone' => $cliente['telefone'] ?? null,
            'ativo' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }

    private function buscarClienteSugerido(
        array $cliente
    ): ?array {
        $query = DB::table('clientes')
            ->where('ativo', true);

        $encontrado = null;

        if (! empty($cliente['codigo_externo'])) {
            $encontrado = (clone $query)
                ->where(
                    'codigo_externo',
                    $cliente['codigo_externo']
                )
                ->first();
        }

        if (
            ! $encontrado &&
            ! empty($cliente['telefone'])
        ) {
            $telefone = preg_replace(
                '/\\D+/',
                '',
                $cliente['telefone']
            );

            if ($telefone) {
                $encontrado = (clone $query)
                    ->whereRaw(
                        "REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '', 'g') = ?",
                        [$telefone]
                    )
                    ->first();
            }
        }

        if (! $encontrado) {
            return null;
        }

        return [
            'id' => (int) $encontrado->id,
            'codigo_externo' =>
                $encontrado->codigo_externo,
            'nome' => $encontrado->nome,
            'apelido' => $encontrado->apelido,
            'telefone' => $encontrado->telefone,
        ];
    }

    private function buscarRegiaoSugerida(
        ?string $endereco
    ): ?array {
        if (! $endereco) {
            return null;
        }

        $alvo = $this->normalizar($endereco);

        $melhor = null;
        $melhorPontuacao = 0;

        foreach (
            DB::table('regiaos')
                ->where('ativo', true)
                ->get()
            as $regiao
        ) {
            $pontuacao = 0;
            $nome = $this->normalizar(
                (string) $regiao->nome
            );
            $cidade = $this->normalizar(
                (string) ($regiao->cidade ?? '')
            );

            if (
                strlen($nome) >= 3 &&
                str_contains($alvo, $nome)
            ) {
                $pontuacao += 3;
            }

            if (
                strlen($cidade) >= 3 &&
                str_contains($alvo, $cidade)
            ) {
                $pontuacao += 1;
            }

            if ($pontuacao > $melhorPontuacao) {
                $melhorPontuacao = $pontuacao;
                $melhor = $regiao;
            }
        }

        if (! $melhor) {
            return null;
        }

        return [
            'id' => (int) $melhor->id,
            'nome' => $melhor->nome,
            'cidade' => $melhor->cidade,
        ];
    }

    private function adicionarAvisoSobrePossivelSobreposicao(
        array &$dados
    ): void {
        $assinaturas = [];
        $duplicados = false;

        foreach (
            $dados['itens'] ?? []
            as $item
        ) {
            $assinatura = implode('|', [
                $this->normalizar(
                    (string) ($item['codigo_produto'] ?? '')
                ),
                $this->normalizar(
                    (string) ($item['descricao_produto'] ?? '')
                ),
                number_format(
                    (float) ($item['quantidade_solicitada'] ?? 0),
                    3,
                    '.',
                    ''
                ),
            ]);

            if (isset($assinaturas[$assinatura])) {
                $duplicados = true;
                break;
            }

            $assinaturas[$assinatura] = true;
        }

        if ($duplicados) {
            $dados['avisos'][] =
                'Foram encontrados produtos idênticos mais de uma vez. Se as fotos tiveram sobreposição, confira se algum item foi lido duplicado.';
        }
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(
            Str::ascii($texto)
        );
    }

    private function tabelaDocumentos(): string
    {
        if (Schema::hasTable('documento_importados')) {
            return 'documento_importados';
        }

        if (Schema::hasTable('documentos_importados')) {
            return 'documentos_importados';
        }

        throw ValidationException::withMessages([
            'documento' => [
                'A tabela de documentos importados ainda não existe. Rode as migrations.',
            ],
        ]);
    }
}
