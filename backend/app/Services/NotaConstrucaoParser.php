<?php

namespace App\Services;

use Illuminate\Support\Str;

class NotaConstrucaoParser
{
    public function interpretar(
        string $texto
    ): array {
        $linhas =
            collect(
                preg_split(
                    '/\R/',
                    $texto
                ) ?: []
            )
                ->map(
                    fn ($linha) =>
                        Str::squish(
                            (string) $linha
                        )
                )
                ->filter(
                    fn ($linha) =>
                        $linha !== ''
                )
                ->values()
                ->all();

        $numeroPedido =
            $this->extrairPedido(
                $linhas
            );

        $dataDocumento =
            $this->extrairData(
                $linhas
            );

        $cliente =
            $this->extrairCliente(
                $linhas
            );

        $itens =
            $this->extrairItens(
                $linhas
            );

        $observacao =
            $this->extrairObservacao(
                $linhas
            );

        $avisos = [];

        if (! $numeroPedido) {
            $avisos[] =
                'Não foi possível identificar o número do pedido.';
        }

        if (! $cliente['nome']) {
            $avisos[] =
                'Confira o nome do cliente.';
        }

        if (! $cliente['endereco']) {
            $avisos[] =
                'Confira o endereço da entrega.';
        }

        if ($itens === []) {
            $avisos[] =
                'Nenhum produto foi identificado automaticamente.';
        }

        foreach (
            $itens as $item
        ) {
            if (
                (float)
                $item[
                    'quantidade_solicitada'
                ] <= 0
            ) {
                $identificacao =
                    $item[
                        'codigo_produto'
                    ]
                    ?: $item[
                        'descricao_produto'
                    ];

                $avisos[] =
                    sprintf(
                        'Confira a quantidade do produto %s. O OCR identificou o produto, mas não conseguiu ler a quantidade.',
                        $identificacao
                    );
            }
        }

        return [
            'numero_pedido' =>
                $numeroPedido,

            'data_documento' =>
                $dataDocumento,

            'cliente' => [
                'codigo_externo' =>
                    $cliente[
                        'codigo_externo'
                    ],

                'nome' =>
                    $cliente['nome'],

                'apelido' =>
                    $cliente[
                        'apelido'
                    ],

                'telefone' =>
                    $cliente[
                        'telefone'
                    ],
            ],

            'endereco' =>
                $cliente[
                    'endereco'
                ],

            'referencia' =>
                null,

            'prioridade' =>
                'NORMAL',

            'observacao' =>
                $observacao,

            'itens' =>
                $itens,

            'avisos' =>
                $avisos,
        ];
    }

    private function extrairPedido(
        array $linhas
    ): ?string {
        foreach (
            $linhas as $linha
        ) {
            /*
             * Aceita:
             * Pedido : 18536
             * Pedid 18536
             * Pedido 18536
             */
            if (
                preg_match(
                    '/pedid(?:o)?\s*[:;]?\s*([0-9]{3,})/iu',
                    $linha,
                    $match
                )
            ) {
                return $match[1];
            }
        }

        return null;
    }

    private function extrairData(
        array $linhas
    ): ?string {
        foreach (
            $linhas as $linha
        ) {
            /*
             * Só aceitamos uma data completa e plausível.
             * Não tentamos adivinhar "202€" como 2026.
             */
            if (
                preg_match(
                    '/data\s*\/?\s*hr\s*[:;]?\s*([0-9]{1,2})\/([0-9]{2})\/([0-9]{4})/iu',
                    $linha,
                    $match
                )
            ) {
                $dia =
                    str_pad(
                        $match[1],
                        2,
                        '0',
                        STR_PAD_LEFT
                    );

                return sprintf(
                    '%s-%s-%s',
                    $match[3],
                    $match[2],
                    $dia
                );
            }
        }

        return null;
    }

    private function extrairCliente(
        array $linhas
    ): array {
        $inicio = null;

        foreach (
            $linhas as $indice => $linha
        ) {
            if (
                preg_match(
                    '/cliente\s*[:;]/iu',
                    $linha
                )
            ) {
                $inicio = $indice;
                break;
            }
        }

        if ($inicio === null) {
            return $this
                ->clienteVazio();
        }

        $bloco = [];

        for (
            $i = $inicio;
            $i < min(
                count($linhas),
                $inicio + 12
            );
            $i++
        ) {
            $linha =
                $linhas[$i];

            $ascii =
                mb_strtolower(
                    Str::ascii(
                        $linha
                    )
                );

            if (
                $i > $inicio &&
                (
                    str_contains(
                        $ascii,
                        'observacao:'
                    ) ||
                    str_contains(
                        $ascii,
                        'ass conferente'
                    ) ||
                    str_contains(
                        $ascii,
                        'assinatura do cliente'
                    )
                )
            ) {
                break;
            }

            $bloco[] =
                $linha;
        }

        $apelido =
            $this
                ->extrairApelidoCliente(
                    $bloco[0] ?? ''
                );

        $codigo = null;
        $nome = null;
        $telefone = null;
        $indiceNome = null;
        $indiceTelefone = null;

        foreach (
            $bloco as
            $indice => $linha
        ) {
            if (
                preg_match(
                    '/(?:[\[\(]\s*|^1)([0-9]{1,10})\s*[\]\)]?\s*[-:]\s*(.+)$/u',
                    $linha,
                    $match
                )
            ) {
                $codigo =
                    trim(
                        $match[1]
                    );

                $nome =
                    trim(
                        $match[2]
                    );

                $indiceNome =
                    $indice;

                continue;
            }

            if (
                preg_match(
                    '/[\[\(]\s*([0-9]{1,10})\s*[\]\)]?\s*(?:[-:]?\s*)([A-ZÀ-Ü][A-ZÀ-Ü\s]{5,})$/u',
                    $linha,
                    $match
                )
            ) {
                $codigo =
                    trim(
                        $match[1]
                    );

                $nome =
                    trim(
                        $match[2]
                    );

                $indiceNome =
                    $indice;

                continue;
            }

            if (
                preg_match(
                    '/(?:\(?[0-9]{2}\)?\s*)?[0-9]{4,5}[-\s]?[0-9]{4}/u',
                    $linha,
                    $match
                )
            ) {
                $telefone =
                    trim(
                        $match[0]
                    );

                $indiceTelefone =
                    $indice;
            }
        }

        $enderecoLinhas = [];

        if (
            $indiceNome !== null
        ) {
            $fim =
                $indiceTelefone
                ??
                count($bloco);

            for (
                $i =
                    $indiceNome + 1;
                $i < $fim;
                $i++
            ) {
                $linha =
                    trim(
                        $bloco[$i]
                        ?? ''
                    );

                if (
                    $linha === '' ||
                    preg_match(
                        '/^-+$/',
                        $linha
                    )
                ) {
                    continue;
                }

                $enderecoLinhas[] =
                    $linha;
            }
        }

        return [
            'codigo_externo' =>
                $codigo,

            'nome' =>
                $nome,

            'apelido' =>
                $apelido,

            'telefone' =>
                $telefone,

            'endereco' =>
                $enderecoLinhas
                !== []
                    ? implode(
                        ' - ',
                        $enderecoLinhas
                    )
                    : null,
        ];
    }

    private function extrairApelidoCliente(
        string $linha
    ): ?string {
        if (
            preg_match(
                '/cliente\s*[:;]\s*\(([^)]+)\)/iu',
                $linha,
                $match
            )
        ) {
            return trim(
                $match[1]
            );
        }

        return null;
    }

    private function extrairItens(
        array $linhas
    ): array {
        $itens = [];
        $totalLinhas =
            count($linhas);

        for (
            $i = 0;
            $i < $totalLinhas;
            $i++
        ) {
            $linha =
                $linhas[$i];

            /*
             * Procura item + código + descrição.
             * O cabeçalho pode estar completamente quebrado.
             */
            if (
                ! preg_match(
                    '/(?:^|\s)([0-9O]{3,4})\s+([0-9O]{6,})\s+(.+)$/u',
                    $linha,
                    $match
                )
            ) {
                continue;
            }

            $codigo =
                str_replace(
                    ['O', 'o'],
                    '0',
                    $match[2]
                );

            $descricaoPartes = [
                trim(
                    $match[3]
                ),
            ];

            $quantidade = null;

            for (
                $j = $i + 1;
                $j < min(
                    $totalLinhas,
                    $i + 7
                );
                $j++
            ) {
                $proxima =
                    $linhas[$j];

                if (
                    preg_match(
                        '/([0-9]+(?:[.,][0-9]+)?)\s*[xX]\s*[0-9]+(?:[.,][0-9]+)?(?:\s*=\s*|\s+)[0-9]+(?:[.,][0-9]+)?/u',
                        $proxima,
                        $qtdMatch
                    )
                ) {
                    $quantidade =
                        $this->numero(
                            $qtdMatch[1]
                        );

                    $i = $j;
                    break;
                }

                $ascii =
                    mb_strtolower(
                        Str::ascii(
                            $proxima
                        )
                    );

                /*
                 * O OCR costuma colocar caracteres antes dos totais:
                 *
                 * 04 SUBTOTAL = 3,00
                 * / OUTROS = 2,50
                 * (/ TOTAL = 5,50
                 * v / PAGAMENTO = 5,50
                 *
                 * Por isso não podemos exigir que SUBTOTAL/TOTAL
                 * estejam no início da linha.
                 */
                if (
                    $this->linhaEncerraDescricao(
                        $ascii
                    )
                ) {
                    break;
                }

                if (
                    preg_match(
                        '/(?:^|\s)[0-9O]{3,4}\s+[0-9O]{6,}\s+.+$/u',
                        $proxima
                    )
                ) {
                    break;
                }

                /*
                 * Linhas curtas como "MM" são continuação
                 * válida de descrição.
                 */
                if (
                    preg_match(
                        '/[A-Za-zÀ-ÿ]{2,}/u',
                        $proxima
                    )
                ) {
                    $continuacao =
                        $this->limparContinuacaoDescricao(
                            $proxima
                        );

                    if (
                        $continuacao !== ''
                    ) {
                        $descricaoPartes[] =
                            $continuacao;
                    }
                }
            }

            $descricao =
                Str::squish(
                    implode(
                        ' ',
                        $descricaoPartes
                    )
                );

            /*
             * V3: não descartamos mais o produto se a
             * quantidade sumiu no OCR.
             *
             * Retornamos 0 para obrigar a conferência
             * manual antes da confirmação.
             */
            $itens[] = [
                'codigo_produto' =>
                    $codigo,

                'descricao_produto' =>
                    $descricao,

                'unidade' =>
                    null,

                'quantidade_solicitada' =>
                    $quantidade
                    ?? 0,

                'observacao' =>
                    null,
            ];
        }

        return $itens;
    }

    private function extrairObservacao(
        array $linhas
    ): ?string {
        $inicio = null;

        foreach (
            $linhas as
            $indice => $linha
        ) {
            $ascii =
                mb_strtolower(
                    Str::ascii(
                        $linha
                    )
                );

            if (
                str_contains(
                    $ascii,
                    'observacao:'
                )
            ) {
                $inicio =
                    $indice;

                break;
            }
        }

        if (
            $inicio === null
        ) {
            return null;
        }

        $partes = [];

        $primeira =
            preg_replace(
                '/^.*?observa(?:c|ç)(?:a|ã)o\s*:\s*/iu',
                '',
                $linhas[$inicio]
            );

        if (
            trim(
                (string)
                $primeira
            ) !== ''
        ) {
            $partes[] =
                trim(
                    (string)
                    $primeira
                );
        }

        for (
            $i = $inicio + 1;
            $i < count($linhas);
            $i++
        ) {
            $ascii =
                mb_strtolower(
                    Str::ascii(
                        $linhas[$i]
                    )
                );

            if (
                str_contains(
                    $ascii,
                    'ass conferente'
                ) ||
                str_contains(
                    $ascii,
                    'assinatura do cliente'
                )
            ) {
                break;
            }

            $partes[] =
                $linhas[$i];
        }

        $texto =
            Str::squish(
                implode(
                    ' ',
                    $partes
                )
            );

        return
            $texto !== ''
                ? $texto
                : null;
    }

    private function linhaEncerraDescricao(
        string $linhaAscii
    ): bool {
        foreach ([
            'subtotal',
            'outros',
            'pagamento',
            'forma de pagamento',
            'cliente:',
        ] as $marcador) {
            if (
                str_contains(
                    $linhaAscii,
                    $marcador
                )
            ) {
                return true;
            }
        }

        /*
         * TOTAL deve ser tratado separadamente para evitar
         * colisões desnecessárias com textos maiores.
         */
        if (
            preg_match(
                '/(?:^|[^a-z])total\s*(?:=|$)/i',
                $linhaAscii
            )
        ) {
            return true;
        }

        return false;
    }

    private function limparContinuacaoDescricao(
        string $linha
    ): string {
        $linha =
            Str::squish(
                trim($linha)
            );

        /*
         * Remove sujeira comum criada na margem da foto.
         * Exemplo real: "y MM" -> "MM".
         *
         * Só removemos um token isolado de uma letra quando
         * existe outro texto depois dele, evitando apagar uma
         * descrição válida inteira.
         */
        $linha =
            preg_replace(
                '/^[^A-Za-z0-9À-ÿ]*[A-Za-zÀ-ÿ]\s+(?=[A-Za-z0-9À-ÿ]{2,})/u',
                '',
                $linha
            )
            ?? $linha;

        /* Remove apenas pontuação solta no começo. */
        $linha =
            preg_replace(
                '/^[^A-Za-z0-9À-ÿ]+/u',
                '',
                $linha
            )
            ?? $linha;

        return
            Str::squish(
                trim($linha)
            );
    }

    private function clienteVazio(): array
    {
        return [
            'codigo_externo' =>
                null,
            'nome' =>
                null,
            'apelido' =>
                null,
            'telefone' =>
                null,
            'endereco' =>
                null,
        ];
    }

    private function numero(
        string $valor
    ): float {
        return (float)
            str_replace(
                ',',
                '.',
                trim($valor)
            );
    }
}
