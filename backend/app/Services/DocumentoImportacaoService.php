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
        UploadedFile $arquivo,
        int $usuarioId
    ): array {
        $tabela = $this->tabelaDocumentos();
        $hash = hash_file(
            'sha256',
            $arquivo->getRealPath()
        );

        $existente = DB::table($tabela)
            ->where('hash_sha256', $hash)
            ->first();

        if ($existente) {
            throw ValidationException::withMessages([
                'arquivo' => [
                    'Esta mesma imagem já foi enviada anteriormente.',
                ],
            ]);
        }

        $nome = sprintf(
            '%s.%s',
            Str::uuid(),
            strtolower(
                $arquivo->getClientOriginalExtension()
            )
        );

        $diretorio = sprintf(
            'documentos-importados/%s/%s',
            now()->format('Y'),
            now()->format('m')
        );

        $path = $arquivo->storeAs(
            $diretorio,
            $nome,
            'local'
        );

        $agora = now();

        $documentoId = DB::table($tabela)
            ->insertGetId([
                'usuario_id' => $usuarioId,
                /*
                 * A tabela atual usa enum/check com valores de domínio
                 * (FOTO, PDF, CSV, XLS, XLSX, XML), não MIME type.
                 */
                'tipo_arquivo' => 'FOTO',
                'nome_arquivo' =>
                    $arquivo->getClientOriginalName(),
                'arquivo_path' => $path,
                'hash_sha256' => $hash,
                'status' => 'PROCESSANDO',
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

        try {
            $texto = $this->ocr->extrairTexto(
                Storage::disk('local')->path($path)
            );

            $dados = $this->parser->interpretar($texto);

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
                    'nome_arquivo' =>
                        $arquivo->getClientOriginalName(),
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
                'arquivo' => [
                    'Não foi possível ler esta foto. Tente uma imagem mais reta, iluminada e nítida. Detalhe: '
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

            if ($documento->status === 'IMPORTADO') {
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

            DB::table($tabela)
                ->where('id', $documentoId)
                ->update([
                    'status' => 'CONFIRMADO',
                    'dados_extraidos' => json_encode(
                        [
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
