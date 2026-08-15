<?php

namespace App\Services;

use App\Models\Devolucao;
use App\Models\Pendencia;
use App\Models\Saida;
use App\Models\SaidaEvento;
use App\Models\SaidaPendenciaRetorno;
use App\Models\TentativaEntrega;
use BackedEnum;

class SaidaHistoricoService
{
    public function obter(
        Saida $saida
    ): array {
        $saida->loadMissing([
            'regiaoPrincipal',
            'veiculo',
            'saidaEntregas.entrega',
            'saidaEntregas.itensProgramados.entregaItem',
            'pendencias.entrega.regiao',
            'pendencias.entregaItem',
        ]);

        /*
         * Eventos reais da carrada. Além da timeline,
         * eles ajudam a recuperar o snapshot de uma
         * reentrega antiga que ainda não possuía a tabela
         * saida_pendencia_retornos.
         */
        $eventosModel =
            SaidaEvento::query()
                ->where(
                    'saida_id',
                    $saida->id
                )
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

        $devolucoesDaSaida =
            Devolucao::query()
                ->where(
                    'saida_id',
                    $saida->id
                )
                ->get();

        $retornosReentrega =
            SaidaPendenciaRetorno::query()
                ->where(
                    'saida_id',
                    $saida->id
                )
                ->get()
                ->keyBy('pendencia_id');

        /*
         * =====================================================
         * ENTREGAS NORMAIS DA CARRADA
         * =====================================================
         */
        $entregas =
            $saida
                ->saidaEntregas
                ->map(
                    function (
                        $saidaEntrega
                    ) use (
                        $saida,
                        $devolucoesDaSaida
                    ) {
                        $entrega =
                            $saidaEntrega->entrega;

                        if (! $entrega) {
                            return null;
                        }

                        $tentativa =
                            TentativaEntrega::query()
                                ->where(
                                    'saida_id',
                                    $saida->id
                                )
                                ->where(
                                    'entrega_id',
                                    $entrega->id
                                )
                                ->with('itens')
                                ->first();

                        $itensTentativa =
                            $tentativa
                                ? $tentativa
                                    ->itens
                                    ->keyBy(
                                        'entrega_item_id'
                                    )
                                : collect();

                        $pendenciasDaTentativa =
                            $tentativa
                                ? Pendencia::query()
                                    ->where(
                                        'tentativa_origem_id',
                                        $tentativa->id
                                    )
                                    ->get()
                                : collect();

                        $itens =
                            $saidaEntrega
                                ->itensProgramados
                                ->map(
                                    function (
                                        $programacao
                                    ) use (
                                        $itensTentativa,
                                        $tentativa,
                                        $devolucoesDaSaida,
                                        $pendenciasDaTentativa
                                    ) {
                                        $item =
                                            $programacao
                                                ->entregaItem;

                                        $tentativaItem =
                                            $itensTentativa
                                                ->get(
                                                    $programacao
                                                        ->entrega_item_id
                                                );

                                        $carregado =
                                            round(
                                                (float)
                                                $programacao
                                                    ->quantidade_programada,
                                                3
                                            );

                                        $entregue =
                                            round(
                                                (float) (
                                                    $tentativaItem
                                                        ?->quantidade_entregue
                                                    ?? 0
                                                ),
                                                3
                                            );

                                        $devolvido =
                                            $tentativa
                                                ? round(
                                                    (float)
                                                    $devolucoesDaSaida
                                                        ->where(
                                                            'tentativa_origem_id',
                                                            $tentativa->id
                                                        )
                                                        ->where(
                                                            'entrega_item_id',
                                                            $programacao
                                                                ->entrega_item_id
                                                        )
                                                        ->sum(
                                                            'quantidade'
                                                        ),
                                                    3
                                                )
                                                : 0.0;

                                        /*
                                         * Snapshot da quantidade que
                                         * nasceu como reentrega nesta
                                         * tentativa.
                                         */
                                        $reentrega =
                                            round(
                                                (float)
                                                $pendenciasDaTentativa
                                                    ->where(
                                                        'entrega_item_id',
                                                        $programacao
                                                            ->entrega_item_id
                                                    )
                                                    ->sum(
                                                        'quantidade_original'
                                                    ),
                                                3
                                            );

                                        return [
                                            'entrega_item_id' =>
                                                $programacao
                                                    ->entrega_item_id,

                                            'codigo_produto' =>
                                                $item
                                                    ?->codigo_produto,

                                            'descricao_produto' =>
                                                $item
                                                    ?->descricao_produto
                                                ?? 'Produto sem descrição',

                                            'unidade' =>
                                                $item?->unidade,

                                            'carregado' =>
                                                $carregado,

                                            'entregue' =>
                                                $entregue,

                                            'devolvido' =>
                                                $devolvido,

                                            'reentrega' =>
                                                $reentrega,

                                            'nao_entregue' =>
                                                max(
                                                    0,
                                                    round(
                                                        $carregado
                                                        - $entregue,
                                                        3
                                                    )
                                                ),

                                            'observacao' =>
                                                $tentativaItem
                                                    ?->observacao,
                                        ];
                                    }
                                )
                                ->values();

                        $pendenciasGeradas =
                            $pendenciasDaTentativa
                                ->map(
                                    function (
                                        Pendencia $pendencia
                                    ) use ($itens) {
                                        $produto =
                                            $itens
                                                ->firstWhere(
                                                    'entrega_item_id',
                                                    $pendencia
                                                        ->entrega_item_id
                                                );

                                        $status =
                                            $this->valorEnum(
                                                $pendencia->status
                                            );

                                        return [
                                            'id' =>
                                                $pendencia->id,

                                            'entrega_item_id' =>
                                                $pendencia
                                                    ->entrega_item_id,

                                            'codigo_produto' =>
                                                $produto[
                                                    'codigo_produto'
                                                ] ?? null,

                                            'descricao_produto' =>
                                                $produto[
                                                    'descricao_produto'
                                                ] ?? 'Produto sem descrição',

                                            'unidade' =>
                                                $produto[
                                                    'unidade'
                                                ] ?? null,

                                            'quantidade_original' =>
                                                (float)
                                                $pendencia
                                                    ->quantidade_original,

                                            'quantidade_resolvida' =>
                                                (float)
                                                $pendencia
                                                    ->quantidade_resolvida,

                                            'saldo_atual' =>
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

                                            'status_atual' =>
                                                $status,

                                            'motivo' =>
                                                $pendencia->motivo,

                                            'observacao' =>
                                                $pendencia->observacao,

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
                            'id' =>
                                $entrega->id,

                            'saida_entrega_id' =>
                                $saidaEntrega->id,

                            'ordem' =>
                                $saidaEntrega->ordem,

                            'numero_pedido' =>
                                $entrega->numero_pedido,

                            'cliente_nome' =>
                                $entrega->cliente_nome,

                            'endereco' =>
                                $entrega->endereco,

                            'resultado' =>
                                $this->valorEnum(
                                    $tentativa?->resultado
                                ),

                            'resultado_registrado' =>
                                $tentativa !== null,

                            'data_hora_resultado' =>
                                $tentativa?->data_hora,

                            'motivo_nao_entrega' =>
                                $tentativa
                                    ?->motivo_nao_entrega,

                            'observacao_resultado' =>
                                $tentativa?->observacao,

                            'itens' =>
                                $itens,

                            'pendencias_geradas' =>
                                $pendenciasGeradas,
                        ];
                    }
                )
                ->filter()
                ->sortBy(
                    fn ($entrega) =>
                        $entrega['ordem']
                        ?? PHP_INT_MAX
                )
                ->values();

        /*
         * =====================================================
         * REENTREGAS / PENDÊNCIAS LEVADAS NESTA CARRADA
         * =====================================================
         */
        $reentregas =
            $saida
                ->pendencias
                ->map(
                    function (
                        Pendencia $pendencia
                    ) use (
                        $retornosReentrega,
                        $eventosModel
                    ) {
                        $entrega =
                            $pendencia->entrega;

                        $item =
                            $pendencia->entregaItem;

                        $resultado =
                            $retornosReentrega
                                ->get(
                                    $pendencia->id
                                );

                        $saldoAtual =
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
                            );

                        $quantidadeProgramada =
                            $resultado
                                ? (float)
                                    $resultado
                                        ->quantidade_programada
                                : null;

                        /*
                         * Carradas antigas podem não possuir
                         * saida_pendencia_retornos. Nesse caso
                         * buscamos o saldo salvo no evento de
                         * inclusão da reentrega.
                         */
                        if ($quantidadeProgramada === null) {
                            $eventoAdicao =
                                $eventosModel
                                    ->where(
                                        'tipo_evento',
                                        'PENDENCIA_ADICIONADA'
                                    )
                                    ->first(
                                        function (
                                            SaidaEvento $evento
                                        ) use ($pendencia) {
                                            return (int) (
                                                $evento->dados[
                                                    'pendencia_id'
                                                ] ?? 0
                                            ) ===
                                            $pendencia->id;
                                        }
                                    );

                            $quantidadeProgramada =
                                isset(
                                    $eventoAdicao?->dados[
                                        'saldo_pendente'
                                    ]
                                )
                                    ? (float)
                                        $eventoAdicao
                                            ->dados[
                                                'saldo_pendente'
                                            ]
                                    : $saldoAtual;
                        }

                        return [
                            'pendencia_id' =>
                                $pendencia->id,

                            'entrega_id' =>
                                $pendencia->entrega_id,

                            'entrega_item_id' =>
                                $pendencia
                                    ->entrega_item_id,

                            'numero_pedido' =>
                                $entrega?->numero_pedido,

                            'cliente_nome' =>
                                $entrega?->cliente_nome
                                ?? 'Cliente não localizado',

                            'endereco' =>
                                $entrega?->endereco
                                ?? '',

                            'regiao_id' =>
                                $entrega?->regiao_id,

                            'regiao_nome' =>
                                $entrega?->regiao?->nome
                                ?? 'Sem região',

                            'codigo_produto' =>
                                $item?->codigo_produto,

                            'descricao_produto' =>
                                $item?->descricao_produto
                                ?? 'Produto sem descrição',

                            'unidade' =>
                                $item?->unidade,

                            'quantidade_programada' =>
                                round(
                                    (float)
                                    $quantidadeProgramada,
                                    3
                                ),

                            'quantidade_entregue' =>
                                (float) (
                                    $resultado
                                        ?->quantidade_entregue
                                    ?? 0
                                ),

                            'quantidade_devolvida' =>
                                (float) (
                                    $resultado
                                        ?->quantidade_devolvida
                                    ?? 0
                                ),

                            'quantidade_reentrega' =>
                                (float) (
                                    $resultado
                                        ?->quantidade_reentrega
                                    ?? 0
                                ),

                            'resultado_registrado' =>
                                $resultado !== null,

                            'destino_restante' =>
                                $resultado
                                    ?->destino_restante,

                            'motivo_devolucao' =>
                                $resultado
                                    ?->motivo_devolucao,

                            'observacao_resultado' =>
                                $resultado?->observacao,

                            'data_hora_resultado' =>
                                $resultado?->data_hora,

                            'status_atual' =>
                                $this->valorEnum(
                                    $pendencia->status
                                ),

                            'saldo_atual' =>
                                $saldoAtual,

                            'motivo' =>
                                $pendencia->motivo,

                            'observacao' =>
                                $pendencia->observacao,

                            'data_abertura' =>
                                $pendencia
                                    ->data_abertura,

                            'data_resolucao' =>
                                $pendencia
                                    ->data_resolucao,

                            'adicionada_em' =>
                                $pendencia->pivot
                                    ?->created_at,
                        ];
                    }
                )
                ->values();

        /*
         * =====================================================
         * RESUMO
         * =====================================================
         */
        $completas =
            $entregas
                ->where(
                    'resultado',
                    'COMPLETA'
                )
                ->count();

        $parciais =
            $entregas
                ->where(
                    'resultado',
                    'PARCIAL'
                )
                ->count();

        $naoEntregues =
            $entregas
                ->where(
                    'resultado',
                    'NAO_ENTREGUE'
                )
                ->count();

        $semResultadoEntregas =
            $entregas
                ->where(
                    'resultado_registrado',
                    false
                )
                ->count();

        $semResultadoReentregas =
            $reentregas
                ->where(
                    'resultado_registrado',
                    false
                )
                ->count();

        $quantidadePendenciasGeradas =
            $entregas
                ->flatMap(
                    fn ($entrega) =>
                        $entrega[
                            'pendencias_geradas'
                        ]
                )
                ->sum(
                    'quantidade_original'
                );

        $quantidadeReentregaProgramada =
            $reentregas
                ->sum(
                    'quantidade_programada'
                );

        $quantidadeEntregueReentrega =
            $reentregas
                ->sum(
                    'quantidade_entregue'
                );

        $quantidadeDevolvida =
            $devolucoesDaSaida
                ->sum('quantidade');

        /*
         * =====================================================
         * TIMELINE
         * =====================================================
         */
        $eventos =
            $eventosModel
                ->map(
                    function (
                        SaidaEvento $evento
                    ) {
                        $dados =
                            $evento->dados ?? [];

                        return [
                            'id' =>
                                $evento->id,

                            'tipo_evento' =>
                                $evento
                                    ->tipo_evento,

                            'descricao' =>
                                $evento
                                    ->descricao,

                            'dados' =>
                                $dados,

                            'data_hora' =>
                                $evento
                                    ->created_at
                                    ?->toISOString(),

                            'usuario_id' =>
                                $evento
                                    ->usuario_id,

                            'origem' =>
                                $dados['origem']
                                ?? 'TEMPO_REAL',
                        ];
                    }
                )
                ->values();

        $rota =
            $this->normalizarRota(
                $saida->rota
            );

        return [
            'saida' => [
                'id' =>
                    $saida->id,

                'data_saida' =>
                    $saida->data_saida,

                'status' =>
                    $this->valorEnum(
                        $saida->status
                    ),

                'observacao' =>
                    $saida->observacao,

                'rota' =>
                    $rota,

                'regiao' =>
                    $saida->regiaoPrincipal
                        ? [
                            'id' =>
                                $saida
                                    ->regiaoPrincipal
                                    ->id,

                            'nome' =>
                                $saida
                                    ->regiaoPrincipal
                                    ->nome,
                        ]
                        : null,

                'veiculo' =>
                    $saida->veiculo
                        ? [
                            'id' =>
                                $saida->veiculo->id,

                            'nome' =>
                                $saida->veiculo->nome,

                            'placa' =>
                                $saida->veiculo->placa,
                        ]
                        : null,

                'criada_em' =>
                    $saida->created_at,
            ],

            'resumo' => [
                /* Compatibilidade com o frontend antigo. */
                'total_entregas' =>
                    $entregas->count(),

                'total_entregas_normais' =>
                    $entregas->count(),

                'total_reentregas' =>
                    $reentregas->count(),

                'total_atendimentos' =>
                    $entregas->count()
                    + $reentregas->count(),

                'completas' =>
                    $completas,

                'parciais' =>
                    $parciais,

                'nao_entregues' =>
                    $naoEntregues,

                'sem_resultado' =>
                    $semResultadoEntregas
                    + $semResultadoReentregas,

                'sem_resultado_entregas' =>
                    $semResultadoEntregas,

                'sem_resultado_reentregas' =>
                    $semResultadoReentregas,

                'quantidade_pendencias_geradas' =>
                    round(
                        (float)
                        $quantidadePendenciasGeradas,
                        3
                    ),

                'quantidade_reentrega_programada' =>
                    round(
                        (float)
                        $quantidadeReentregaProgramada,
                        3
                    ),

                'quantidade_entregue_reentrega' =>
                    round(
                        (float)
                        $quantidadeEntregueReentrega,
                        3
                    ),

                'quantidade_devolvida' =>
                    round(
                        (float)
                        $quantidadeDevolvida,
                        3
                    ),
            ],

            'entregas' =>
                $entregas,

            'reentregas' =>
                $reentregas,

            'eventos' =>
                $eventos,
        ];
    }

    private function valorEnum(
        mixed $valor
    ): mixed {
        return $valor instanceof BackedEnum
            ? $valor->value
            : $valor;
    }

    private function normalizarRota(
        mixed $rota
    ): array {
        if (is_string($rota)) {
            $decodificada =
                json_decode(
                    $rota,
                    true
                );

            $rota =
                is_array($decodificada)
                    ? $decodificada
                    : [];
        }

        if (! is_array($rota)) {
            return [];
        }

        return collect($rota)
            ->map(
                function ($parada) {
                    if (! is_array($parada)) {
                        return null;
                    }

                    $nome =
                        trim(
                            (string) (
                                $parada['nome']
                                ?? ''
                            )
                        );

                    if ($nome === '') {
                        return null;
                    }

                    return [
                        'regiao_id' =>
                            isset(
                                $parada['regiao_id']
                            )
                                ? (int)
                                    $parada['regiao_id']
                                : null,

                        'nome' =>
                            $nome,
                    ];
                }
            )
            ->filter()
            ->values()
            ->toArray();
    }
}
