<?php

namespace App\Services;

use App\Enums\StatusPendencia;
use App\Enums\StatusSaida;
use App\Models\Entrega;
use App\Models\Pendencia;
use App\Models\TentativaEntrega;
use App\Models\TentativaItem;
use BackedEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EntregaHistoricoService
{
    public function obter(
        Entrega $entrega
    ): array {
        $entrega->loadMissing('itens');

        $itensDaEntrega =
            $entrega->itens->keyBy('id');

        /*
         * RESUMO DOS PRODUTOS
         */
        $itens = $entrega->itens
            ->map(function ($item) {
                $comprado = round(
                    (float)
                    $item->quantidade_solicitada,
                    3
                );

                /*
                 * Tudo que o cliente realmente
                 * já recebeu.
                 */
                $entregue = round(
                    (float)
                    TentativaItem::query()
                        ->where(
                            'entrega_item_id',
                            $item->id
                        )
                        ->sum(
                            'quantidade_entregue'
                        ),
                    3
                );

                /*
                 * Saldo real que ainda falta
                 * o cliente receber.
                 */
                $saldoRestante = max(
                    0,
                    round(
                        $comprado - $entregue,
                        3
                    )
                );

                /*
                 * Parte do saldo restante que
                 * virou pendência.
                 */
                $pendente =
                    (float)
                    Pendencia::query()
                        ->where(
                            'entrega_item_id',
                            $item->id
                        )
                        ->whereIn(
                            'status',
                            [
                                StatusPendencia::ABERTA
                                    ->value,

                                StatusPendencia::PROGRAMADA
                                    ->value,
                            ]
                        )
                        ->selectRaw(
                            '
                            COALESCE(
                                SUM(
                                    quantidade_original
                                    - quantidade_resolvida
                                ),
                                0
                            ) AS saldo
                            '
                        )
                        ->value('saldo');

                $pendente = max(
                    0,
                    round(
                        $pendente,
                        3
                    )
                );

                /*
                 * Evita inconsistência caso exista
                 * algum dado antigo incorreto.
                 */
                $pendente = min(
                    $pendente,
                    $saldoRestante
                );

                /*
                 * Quantidade que está atualmente
                 * reservada em uma carrada e ainda
                 * não teve retorno registrado.
                 */
                $emCarrada =
                    (float)
                    DB::table(
                        'saida_entrega_itens as sei'
                    )
                        ->join(
                            'saida_entregas as se',
                            'se.id',
                            '=',
                            'sei.saida_entrega_id'
                        )
                        ->join(
                            'saidas as s',
                            's.id',
                            '=',
                            'se.saida_id'
                        )
                        ->leftJoin(
                            'tentativa_entregas as te',
                            function ($join) {
                                $join
                                    ->on(
                                        'te.saida_id',
                                        '=',
                                        'se.saida_id'
                                    )
                                    ->on(
                                        'te.entrega_id',
                                        '=',
                                        'se.entrega_id'
                                    );
                            }
                        )
                        ->where(
                            'sei.entrega_item_id',
                            $item->id
                        )
                        ->whereIn(
                            's.status',
                            [
                                StatusSaida::PLANEJADA
                                    ->value,

                                StatusSaida::EM_ANDAMENTO
                                    ->value,
                            ]
                        )
                        ->whereNull('te.id')
                        ->sum(
                            'sei.quantidade_programada'
                        );

                $emCarrada = max(
                    0,
                    round(
                        $emCarrada,
                        3
                    )
                );

                /*
                 * Não deixamos "em carrada"
                 * ultrapassar o saldo livre.
                 */
                $emCarrada = min(
                    $emCarrada,
                    max(
                        0,
                        $saldoRestante
                        - $pendente
                    )
                );

                /*
                 * Aquilo que ainda nem foi colocado
                 * em uma carrada.
                 */
                $naoEnviado = max(
                    0,
                    round(
                        $saldoRestante
                        - $pendente
                        - $emCarrada,
                        3
                    )
                );

                return [
                    'entrega_item_id' =>
                        $item->id,

                    'codigo_produto' =>
                        $item->codigo_produto,

                    'descricao_produto' =>
                        $item->descricao
                        ?? $item->descricao_produto
                        ?? 'Produto sem descrição',

                    'unidade' =>
                        $item->unidade,

                    'comprado' =>
                        $comprado,

                    'entregue' =>
                        $entregue,

                    'saldo_restante' =>
                        $saldoRestante,

                    'pendente' =>
                        $pendente,

                    'em_carrada' =>
                        $emCarrada,

                    'nao_enviado' =>
                        $naoEnviado,
                ];
            })
            ->values();

        /*
         * HISTÓRICO DAS TENTATIVAS
         */
        $tentativas =
            TentativaEntrega::query()
                ->where(
                    'entrega_id',
                    $entrega->id
                )
                ->with([
                    'saida.veiculo',
                    'itens',
                ])
                ->orderBy(
                    'data_hora'
                )
                ->get()
                ->map(
                    function (
                        TentativaEntrega $tentativa
                    ) use (
                        $entrega,
                        $itensDaEntrega
                    ) {
                        $itensTentativa =
                            $tentativa->itens
                                ->map(
                                    function (
                                        $tentativaItem
                                    ) use (
                                        $tentativa,
                                        $entrega,
                                        $itensDaEntrega
                                    ) {
                                        $item =
                                            $itensDaEntrega
                                                ->get(
                                                    $tentativaItem
                                                        ->entrega_item_id
                                                );

                                        /*
                                         * Quanto daquele produto
                                         * estava carregado nesta
                                         * carrada.
                                         */
                                        $programado =
                                            (float)
                                            DB::table(
                                                'saida_entrega_itens as sei'
                                            )
                                                ->join(
                                                    'saida_entregas as se',
                                                    'se.id',
                                                    '=',
                                                    'sei.saida_entrega_id'
                                                )
                                                ->where(
                                                    'se.saida_id',
                                                    $tentativa->saida_id
                                                )
                                                ->where(
                                                    'se.entrega_id',
                                                    $entrega->id
                                                )
                                                ->where(
                                                    'sei.entrega_item_id',
                                                    $tentativaItem
                                                        ->entrega_item_id
                                                )
                                                ->value(
                                                    'sei.quantidade_programada'
                                                );

                                        $programado =
                                            round(
                                                $programado,
                                                3
                                            );

                                        $entregue =
                                            round(
                                                (float)
                                                $tentativaItem
                                                    ->quantidade_entregue,
                                                3
                                            );

                                        return [
                                            'entrega_item_id' =>
                                                $tentativaItem
                                                    ->entrega_item_id,

                                            'codigo_produto' =>
                                                $item
                                                    ?->codigo_produto,

                                            'descricao_produto' =>
                                                $item?->descricao
                                                ?? $item?->descricao_produto
                                                ?? 'Produto sem descrição',

                                            'unidade' =>
                                                $item?->unidade,

                                            'programado' =>
                                                $programado,

                                            'entregue' =>
                                                $entregue,

                                            'nao_entregue' =>
                                                max(
                                                    0,
                                                    round(
                                                        $programado
                                                        - $entregue,
                                                        3
                                                    )
                                                ),

                                            'observacao' =>
                                                $tentativaItem
                                                    ->observacao,
                                        ];
                                    }
                                )
                                ->values();

                        $resultado =
                            $tentativa->resultado;

                        if (
                            $resultado
                            instanceof BackedEnum
                        ) {
                            $resultado =
                                $resultado->value;
                        }

                        return [
                            'tentativa_id' =>
                                $tentativa->id,

                            'saida_id' =>
                                $tentativa->saida_id,

                            'data_hora' =>
                                $tentativa->data_hora
                                    ? Carbon::parse(
                                        $tentativa
                                            ->data_hora
                                    )->toISOString()
                                    : null,

                            'data_saida' =>
                                $tentativa->saida
                                    ?->data_saida,

                            'resultado' =>
                                $resultado,

                            'motivo_nao_entrega' =>
                                $tentativa
                                    ->motivo_nao_entrega,

                            'observacao' =>
                                $tentativa
                                    ->observacao,

                            'veiculo' =>
                                $tentativa->saida
                                    ?->veiculo
                                    ? [
                                        'id' =>
                                            $tentativa
                                                ->saida
                                                ->veiculo
                                                ->id,

                                        'nome' =>
                                            $tentativa
                                                ->saida
                                                ->veiculo
                                                ->nome,

                                        'placa' =>
                                            $tentativa
                                                ->saida
                                                ->veiculo
                                                ->placa,
                                    ]
                                    : null,

                            'itens' =>
                                $itensTentativa,
                        ];
                    }
                )
                ->values();

        /*
         * PENDÊNCIAS DA ENTREGA
         */
        $pendencias =
            Pendencia::query()
                ->where(
                    'entrega_id',
                    $entrega->id
                )
                ->orderBy('id')
                ->get()
                ->map(
                    function ($pendencia) use (
                        $itensDaEntrega
                    ) {
                        $item =
                            $itensDaEntrega->get(
                                $pendencia
                                    ->entrega_item_id
                            );

                        $status =
                            $pendencia->status;

                        if (
                            $status
                            instanceof BackedEnum
                        ) {
                            $status =
                                $status->value;
                        }

                        return [
                            'id' =>
                                $pendencia->id,

                            'entrega_item_id' =>
                                $pendencia
                                    ->entrega_item_id,

                            'descricao_produto' =>
                                $item?->descricao
                                ?? $item?->descricao_produto
                                ?? 'Produto sem descrição',

                            'unidade' =>
                                $item?->unidade,

                            'quantidade_original' =>
                                (float)
                                $pendencia
                                    ->quantidade_original,

                            'quantidade_resolvida' =>
                                (float)
                                $pendencia
                                    ->quantidade_resolvida,

                            'saldo' =>
                                max(
                                    0,
                                    round(
                                        (float)
                                        $pendencia
                                            ->quantidade_original
                                        -
                                        (float)
                                        $pendencia
                                            ->quantidade_resolvida,
                                        3
                                    )
                                ),

                            'status' =>
                                $status,

                            'motivo' =>
                                $pendencia->motivo,

                            'observacao' =>
                                $pendencia
                                    ->observacao,

                            'data_abertura' =>
                                $pendencia
                                    ->data_abertura,

                            'data_resolucao' =>
                                $pendencia
                                    ->data_resolucao,
                        ];
                    }
                )
                ->values();

        return [
            'entrega' => [
                'id' =>
                    $entrega->id,

                'numero_pedido' =>
                    $entrega->numero_pedido,

                'cliente_nome' =>
                    $entrega->cliente_nome,

                'endereco' =>
                    $entrega->endereco,

                'status' =>
                    $entrega->status
                        instanceof BackedEnum
                        ? $entrega
                            ->status
                            ->value
                        : $entrega->status,
            ],

            'itens' =>
                $itens,

            'tentativas' =>
                $tentativas,

            'pendencias' =>
                $pendencias,
        ];
    }
}