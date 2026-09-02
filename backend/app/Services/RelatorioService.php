<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RelatorioService
{
    public function gerar(
        array $filtros
    ): array {
        $timezoneOperacao = config(
            'app.operation_timezone',
            'America/Fortaleza'
        );

        $inicioLocal = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $filtros['inicio'],
            $timezoneOperacao
        )->startOfDay();

        $fimLocal = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $filtros['fim'],
            $timezoneOperacao
        )->endOfDay();

        $inicio = $inicioLocal->utc();
        $fim = $fimLocal->utc();

        $regiaoId =
            $filtros['regiao_id']
            ?? null;

        $status =
            $filtros['status']
            ?? null;

        $entregas = $this->queryEntregas(
            $inicio,
            $fim,
            $regiaoId,
            $status
        );

        $totalEntregas =
            (clone $entregas)->count();

        $porStatus =
            (clone $entregas)
                ->select(
                    'entregas.status',
                    DB::raw('COUNT(*) AS total')
                )
                ->groupBy(
                    'entregas.status'
                )
                ->pluck(
                    'total',
                    'status'
                )
                ->map(
                    fn ($total) =>
                        (int) $total
                )
                ->all();

        $porRegiao =
            (clone $entregas)
                ->leftJoin(
                    'regiaos',
                    'regiaos.id',
                    '=',
                    'entregas.regiao_id'
                )
                ->select(
                    'entregas.regiao_id',
                    DB::raw(
                        "COALESCE(regiaos.nome, 'Sem região') AS regiao_nome"
                    ),
                    DB::raw(
                        'COUNT(*) AS total'
                    ),
                    DB::raw(
                        "SUM(CASE WHEN entregas.status = 'CONCLUIDA' THEN 1 ELSE 0 END) AS concluidas"
                    ),
                    DB::raw(
                        "SUM(CASE WHEN entregas.status = 'PARCIAL' THEN 1 ELSE 0 END) AS parciais"
                    ),
                    DB::raw(
                        "SUM(CASE WHEN entregas.status = 'NAO_ENTREGUE' THEN 1 ELSE 0 END) AS nao_entregues"
                    )
                )
                ->groupBy(
                    'entregas.regiao_id',
                    'regiaos.nome'
                )
                ->orderByDesc('total')
                ->get()
                ->map(
                    fn ($linha) => [
                        'regiao_id' =>
                            $linha->regiao_id
                                ? (int) $linha->regiao_id
                                : null,

                        'regiao_nome' =>
                            $linha->regiao_nome,

                        'total' =>
                            (int) $linha->total,

                        'concluidas' =>
                            (int) $linha->concluidas,

                        'parciais' =>
                            (int) $linha->parciais,

                        'nao_entregues' =>
                            (int) $linha->nao_entregues,

                        'taxa_conclusao' =>
                            (int) $linha->total > 0
                                ? round(
                                    (
                                        (int) $linha->concluidas
                                        /
                                        (int) $linha->total
                                    ) * 100,
                                    1
                                )
                                : 0,
                    ]
                )
                ->values();

        $pendencias =
            $this->resumoPendencias(
                $inicio,
                $fim,
                $regiaoId
            );

        $devolucoes =
            $this->resumoDevolucoes(
                $inicio,
                $fim,
                $regiaoId
            );

        $recentes =
            (clone $entregas)
                ->leftJoin(
                    'regiaos',
                    'regiaos.id',
                    '=',
                    'entregas.regiao_id'
                )
                ->select([
                    'entregas.id',
                    'entregas.numero_pedido',
                    'entregas.cliente_nome',
                    'entregas.status',
                    'entregas.created_at',
                    DB::raw(
                        "COALESCE(regiaos.nome, 'Sem região') AS regiao_nome"
                    ),
                ])
                ->orderByDesc(
                    'entregas.created_at'
                )
                ->limit(12)
                ->get()
                ->map(
                    fn ($linha) => [
                        'id' =>
                            (int) $linha->id,

                        'numero_pedido' =>
                            $linha->numero_pedido,

                        'cliente_nome' =>
                            $linha->cliente_nome,

                        'regiao_nome' =>
                            $linha->regiao_nome,

                        'status' =>
                            $linha->status,

                        'data' =>
                            optional(
                                $linha->created_at
                            )
                                ? CarbonImmutable::parse(
                                    $linha->created_at
                                )->format('Y-m-d H:i:s')
                                : null,
                    ]
                )
                ->values();

        return [
            'periodo' => [
                'inicio' =>
                    $inicioLocal->format('Y-m-d'),

                'fim' =>
                    $fimLocal->format('Y-m-d'),
            ],

            'filtros' => [
                'regiao_id' =>
                    $regiaoId
                        ? (int) $regiaoId
                        : null,

                'status' =>
                    $status,
            ],

            'opcoes' => [
                'regioes' =>
                    DB::table('regiaos')
                        ->where('ativo', true)
                        ->orderBy('nome')
                        ->get([
                            'id',
                            'nome',
                        ])
                        ->map(
                            fn ($regiao) => [
                                'id' =>
                                    (int) $regiao->id,
                                'nome' =>
                                    $regiao->nome,
                            ]
                        )
                        ->values(),
            ],

            'resumo' => [
                'total_entregas' =>
                    $totalEntregas,

                'concluidas' =>
                    $porStatus['CONCLUIDA']
                    ?? 0,

                'parciais' =>
                    $porStatus['PARCIAL']
                    ?? 0,

                'nao_entregues' =>
                    $porStatus['NAO_ENTREGUE']
                    ?? 0,

                'canceladas' =>
                    $porStatus['CANCELADA']
                    ?? 0,

                'pendencias_abertas' =>
                    $pendencias['abertas'],

                'pendencias_programadas' =>
                    $pendencias['programadas'],

                'quantidade_devolvida' =>
                    $devolucoes['quantidade_total'],
            ],

            'entregas_por_status' =>
                collect([
                    'PENDENTE',
                    'PROGRAMADA',
                    'EM_SAIDA',
                    'PARCIAL',
                    'CONCLUIDA',
                    'NAO_ENTREGUE',
                    'CANCELADA',
                ])
                    ->map(
                        fn (string $statusEntrega) => [
                            'status' =>
                                $statusEntrega,

                            'total' =>
                                $porStatus[
                                    $statusEntrega
                                ]
                                ?? 0,
                        ]
                    )
                    ->values(),

            'entregas_por_regiao' =>
                $porRegiao,

            'pendencias' =>
                $pendencias,

            'devolucoes' =>
                $devolucoes,

            'entregas_recentes' =>
                $recentes,

            'gerado_em' =>
                now()->toIso8601String(),
        ];
    }

    private function queryEntregas(
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        ?int $regiaoId,
        ?string $status
    ): Builder {
        return DB::table('entregas')
            ->whereBetween(
                'entregas.created_at',
                [$inicio, $fim]
            )
            ->when(
                $regiaoId,
                fn (Builder $query) =>
                    $query->where(
                        'entregas.regiao_id',
                        $regiaoId
                    )
            )
            ->when(
                $status,
                fn (Builder $query) =>
                    $query->where(
                        'entregas.status',
                        $status
                    )
            );
    }

    private function resumoPendencias(
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        ?int $regiaoId
    ): array {
        $query = DB::table('pendencias')
            ->join(
                'entregas',
                'entregas.id',
                '=',
                'pendencias.entrega_id'
            )
            ->whereBetween(
                'pendencias.created_at',
                [$inicio, $fim]
            )
            ->when(
                $regiaoId,
                fn (Builder $query) =>
                    $query->where(
                        'entregas.regiao_id',
                        $regiaoId
                    )
            );

        $porStatus =
            (clone $query)
                ->select(
                    'pendencias.status',
                    DB::raw('COUNT(*) AS total')
                )
                ->groupBy(
                    'pendencias.status'
                )
                ->pluck(
                    'total',
                    'status'
                )
                ->map(
                    fn ($total) =>
                        (int) $total
                )
                ->all();

        return [
            'total_criadas' =>
                array_sum(
                    $porStatus
                ),

            'abertas' =>
                $porStatus['ABERTA']
                ?? 0,

            'programadas' =>
                $porStatus['PROGRAMADA']
                ?? 0,

            'resolvidas' =>
                $porStatus['RESOLVIDA']
                ?? 0,

            'canceladas' =>
                $porStatus['CANCELADA']
                ?? 0,
        ];
    }

    private function resumoDevolucoes(
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        ?int $regiaoId
    ): array {
        $query = DB::table('devolucoes')
            ->join(
                'entregas',
                'entregas.id',
                '=',
                'devolucoes.entrega_id'
            )
            ->whereBetween(
                'devolucoes.data_hora',
                [$inicio, $fim]
            )
            ->when(
                $regiaoId,
                fn (Builder $query) =>
                    $query->where(
                        'entregas.regiao_id',
                        $regiaoId
                    )
            );

        $quantidadeTotal =
            (float)
            (clone $query)->sum(
                'devolucoes.quantidade'
            );

        $porMotivo =
            (clone $query)
                ->select(
                    DB::raw(
                        "COALESCE(devolucoes.motivo, 'OUTRO') AS motivo"
                    ),
                    DB::raw(
                        'COUNT(*) AS registros'
                    ),
                    DB::raw(
                        'SUM(devolucoes.quantidade) AS quantidade'
                    )
                )
                ->groupBy(
                    'devolucoes.motivo'
                )
                ->orderByDesc(
                    'quantidade'
                )
                ->get()
                ->map(
                    fn ($linha) => [
                        'motivo' =>
                            $linha->motivo,

                        'registros' =>
                            (int)
                            $linha->registros,

                        'quantidade' =>
                            (float)
                            $linha->quantidade,
                    ]
                )
                ->values();

        return [
            'quantidade_total' =>
                $quantidadeTotal,

            'registros' =>
                (clone $query)->count(),

            'por_motivo' =>
                $porMotivo,
        ];
    }
}
