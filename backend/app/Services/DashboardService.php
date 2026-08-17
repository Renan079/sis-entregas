<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function obter(): array
    {
        $agora = now();
        $inicioMes = $agora->copy()->startOfMonth();
        $fimMes = $agora->copy()->endOfMonth();
        $hoje = $agora->copy()->toDateString();

        $aguardandoQuery =
            $this->queryEntregasAguardandoProgramacao();

        $aguardandoProgramacao =
            (clone $aguardandoQuery)->count();

        /*
         * Entregas realizadas no mês:
         * conta tentativas normais do mês que tiveram
         * alguma quantidade efetivamente entregue.
         */
        $entregasRealizadasMes =
            DB::table('tentativa_entregas as te')
                ->whereBetween(
                    'te.data_hora',
                    [$inicioMes, $fimMes]
                )
                ->whereExists(function ($query) {
                    $query
                        ->selectRaw('1')
                        ->from('tentativa_items as ti')
                        ->whereColumn(
                            'ti.tentativa_id',
                            'te.id'
                        )
                        ->where(
                            'ti.quantidade_entregue',
                            '>',
                            0
                        );
                })
                ->count();

        $carradasResumo =
            DB::table('saidas')
                ->whereDate('data_saida', $hoje)
                ->where('status', '<>', 'CANCELADA')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw(
                    "COUNT(*) FILTER (WHERE status = 'PLANEJADA') AS planejadas"
                )
                ->selectRaw(
                    "COUNT(*) FILTER (WHERE status = 'EM_ANDAMENTO') AS em_andamento"
                )
                ->selectRaw(
                    "COUNT(*) FILTER (WHERE status = 'FINALIZADA') AS finalizadas"
                )
                ->first();

        $reentregasAbertas =
            DB::table('pendencias')
                ->where('status', 'ABERTA')
                ->count();

        $pedidosComProblema =
            DB::table('entregas')
                ->whereIn(
                    'status',
                    ['PARCIAL', 'NAO_ENTREGUE']
                )
                ->count();

        $regioes =
            (clone $aguardandoQuery)
                ->leftJoin(
                    'regiaos as r',
                    'r.id',
                    '=',
                    'e.regiao_id'
                )
                ->select([
                    'e.regiao_id',
                    'r.nome as regiao_nome',
                ])
                ->selectRaw('COUNT(*) AS total')
                ->groupBy(
                    'e.regiao_id',
                    'r.nome'
                )
                ->orderByDesc('total')
                ->orderBy('r.nome')
                ->limit(8)
                ->get()
                ->map(fn ($item) => [
                    'regiao_id' =>
                        $item->regiao_id
                            ? (int) $item->regiao_id
                            : null,

                    'nome' =>
                        $item->regiao_nome
                        ?: 'Sem região',

                    'total' =>
                        (int) $item->total,
                ])
                ->values()
                ->toArray();

        $proximasEntregas =
            (clone $aguardandoQuery)
                ->leftJoin(
                    'regiaos as r',
                    'r.id',
                    '=',
                    'e.regiao_id'
                )
                ->select([
                    'e.id',
                    'e.numero_pedido',
                    'e.cliente_nome',
                    'e.endereco',
                    'e.status',
                    'e.prioridade',
                    'e.created_at',
                    'e.regiao_id',
                    'r.nome as regiao_nome',
                ])
                ->selectSub(
                    DB::table('entrega_items as ei')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn(
                            'ei.entrega_id',
                            'e.id'
                        ),
                    'total_itens'
                )
                ->orderBy('e.created_at')
                ->orderBy('e.id')
                ->limit(8)
                ->get()
                ->map(fn ($entrega) => [
                    'id' =>
                        (int) $entrega->id,

                    'numero_pedido' =>
                        $entrega->numero_pedido,

                    'cliente_nome' =>
                        $entrega->cliente_nome,

                    'endereco' =>
                        $entrega->endereco,

                    'status' =>
                        $entrega->status,

                    'prioridade' =>
                        $entrega->prioridade,

                    'regiao' => [
                        'id' =>
                            $entrega->regiao_id
                                ? (int) $entrega->regiao_id
                                : null,

                        'nome' =>
                            $entrega->regiao_nome
                            ?: 'Sem região',
                    ],

                    'total_itens' =>
                        (int) $entrega->total_itens,

                    'criada_em' =>
                        $entrega->created_at,
                ])
                ->values()
                ->toArray();

        $pendenciasAtencao =
            DB::table('pendencias as p')
                ->join(
                    'entregas as e',
                    'e.id',
                    '=',
                    'p.entrega_id'
                )
                ->join(
                    'entrega_items as ei',
                    'ei.id',
                    '=',
                    'p.entrega_item_id'
                )
                ->leftJoin(
                    'regiaos as r',
                    'r.id',
                    '=',
                    'e.regiao_id'
                )
                ->where('p.status', 'ABERTA')
                ->select([
                    'p.id',
                    'p.entrega_id',
                    'e.numero_pedido',
                    'e.cliente_nome',
                    'ei.codigo_produto',
                    'ei.descricao_produto',
                    'ei.unidade',
                    'p.data_abertura',
                    'e.regiao_id',
                    'r.nome as regiao_nome',
                ])
                ->selectRaw(
                    '(p.quantidade_original - p.quantidade_resolvida) AS saldo_pendente'
                )
                ->orderBy('p.data_abertura')
                ->orderBy('p.id')
                ->limit(6)
                ->get()
                ->map(function ($pendencia) use ($agora) {
                    $dataAbertura =
                        $pendencia->data_abertura
                            ? Carbon::parse(
                                $pendencia->data_abertura
                            )
                            : null;

                    return [
                        'id' =>
                            (int) $pendencia->id,

                        'entrega_id' =>
                            (int) $pendencia->entrega_id,

                        'numero_pedido' =>
                            $pendencia->numero_pedido,

                        'cliente_nome' =>
                            $pendencia->cliente_nome,

                        'produto' => [
                            'codigo' =>
                                $pendencia->codigo_produto,

                            'descricao' =>
                                $pendencia->descricao_produto,

                            'unidade' =>
                                $pendencia->unidade,
                        ],

                        'saldo_pendente' =>
                            (float) $pendencia->saldo_pendente,

                        'regiao' => [
                            'id' =>
                                $pendencia->regiao_id
                                    ? (int) $pendencia->regiao_id
                                    : null,

                            'nome' =>
                                $pendencia->regiao_nome
                                ?: 'Sem região',
                        ],

                        'data_abertura' =>
                            $dataAbertura?->toISOString(),

                        'dias_aberta' =>
                            $dataAbertura
                                ? (int) $dataAbertura
                                    ->copy()
                                    ->startOfDay()
                                    ->diffInDays(
                                        $agora
                                            ->copy()
                                            ->startOfDay()
                                    )
                                : 0,
                    ];
                })
                ->values()
                ->toArray();

        $carradasHoje =
            DB::table('saidas as s')
                ->leftJoin(
                    'regiaos as r',
                    'r.id',
                    '=',
                    's.regiao_principal_id'
                )
                ->leftJoin(
                    'veiculos as v',
                    'v.id',
                    '=',
                    's.veiculo_id'
                )
                ->whereDate(
                    's.data_saida',
                    $hoje
                )
                ->where(
                    's.status',
                    '<>',
                    'CANCELADA'
                )
                ->select([
                    's.id',
                    's.data_saida',
                    's.status',
                    's.rota',
                    'r.nome as regiao_nome',
                    'v.nome as veiculo_nome',
                    'v.placa as veiculo_placa',
                ])
                ->selectSub(
                    DB::table('saida_entregas as se')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn(
                            'se.saida_id',
                            's.id'
                        ),
                    'total_entregas'
                )
                ->selectSub(
                    DB::table('saida_pendencias as sp')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn(
                            'sp.saida_id',
                            's.id'
                        ),
                    'total_reentregas'
                )
                ->orderByRaw(
                    "CASE s.status
                        WHEN 'EM_ANDAMENTO' THEN 1
                        WHEN 'PLANEJADA' THEN 2
                        WHEN 'FINALIZADA' THEN 3
                        ELSE 4
                    END"
                )
                ->orderBy('s.id')
                ->get()
                ->map(function ($saida) {
                    $rota = $saida->rota;

                    if (is_string($rota)) {
                        $rota = json_decode(
                            $rota,
                            true
                        );
                    }

                    if (! is_array($rota)) {
                        $rota = [];
                    }

                    $rota =
                        collect($rota)
                            ->map(
                                fn ($parada) =>
                                    is_array($parada)
                                        ? ($parada['nome'] ?? null)
                                        : null
                            )
                            ->filter()
                            ->values()
                            ->toArray();

                    return [
                        'id' =>
                            (int) $saida->id,

                        'data_saida' =>
                            $saida->data_saida,

                        'status' =>
                            $saida->status,

                        'regiao_principal' =>
                            $saida->regiao_nome,

                        'rota' =>
                            $rota,

                        'veiculo' =>
                            $saida->veiculo_nome
                                ? [
                                    'nome' =>
                                        $saida->veiculo_nome,

                                    'placa' =>
                                        $saida->veiculo_placa,
                                ]
                                : null,

                        'total_entregas' =>
                            (int) $saida->total_entregas,

                        'total_reentregas' =>
                            (int) $saida->total_reentregas,
                    ];
                })
                ->values()
                ->toArray();

        return [
            'resumo' => [
                'entregas_realizadas_mes' =>
                    (int) $entregasRealizadasMes,

                'aguardando_programacao' =>
                    (int) $aguardandoProgramacao,

                'carradas_hoje' =>
                    (int) ($carradasResumo->total ?? 0),

                'carradas_planejadas_hoje' =>
                    (int) ($carradasResumo->planejadas ?? 0),

                'carradas_em_andamento_hoje' =>
                    (int) ($carradasResumo->em_andamento ?? 0),

                'carradas_finalizadas_hoje' =>
                    (int) ($carradasResumo->finalizadas ?? 0),

                'reentregas_abertas' =>
                    (int) $reentregasAbertas,

                'pedidos_com_problema' =>
                    (int) $pedidosComProblema,
            ],

            'entregas_por_regiao' =>
                $regioes,

            'proximas_entregas' =>
                $proximasEntregas,

            'pendencias_atencao' =>
                $pendenciasAtencao,

            'carradas_hoje' =>
                $carradasHoje,

            'periodo' => [
                'inicio_mes' =>
                    $inicioMes->toDateString(),

                'fim_mes' =>
                    $fimMes->toDateString(),

                'atualizado_em' =>
                    $agora->toISOString(),
            ],
        ];
    }

    private function queryEntregasAguardandoProgramacao(): Builder
    {
        return DB::table('entregas as e')
            ->whereIn(
                'e.status',
                [
                    'PENDENTE',
                    'PARCIAL',
                    'NAO_ENTREGUE',
                ]
            )
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('saida_entregas as se')
                    ->join(
                        'saidas as s',
                        's.id',
                        '=',
                        'se.saida_id'
                    )
                    ->whereColumn(
                        'se.entrega_id',
                        'e.id'
                    )
                    ->whereIn(
                        's.status',
                        [
                            'PLANEJADA',
                            'EM_ANDAMENTO',
                        ]
                    );
            });
    }
}
