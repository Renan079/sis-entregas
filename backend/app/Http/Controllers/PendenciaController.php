<?php

namespace App\Http\Controllers;

use App\Enums\StatusPendencia;
use App\Http\Requests\Pendencias\ResolverPendenciaManualRequest;
use App\Models\Pendencia;
use App\Services\PendenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PendenciaController extends Controller
{
    public function __construct(
        private readonly PendenciaService $pendenciaService
    ) {
    }

    /**
     * Lista geral de pendências/reentregas.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer(
            'per_page',
            10
        );

        if (! in_array(
            $perPage,
            [5, 10, 20, 50],
            true
        )) {
            $perPage = 10;
        }

        $query = DB::table('pendencias as p')
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
            ->select([
                'p.id',
                'p.entrega_id',
                'p.entrega_item_id',
                'p.quantidade_original',
                'p.quantidade_resolvida',
                'p.status',
                'p.motivo',
                'p.observacao',
                'p.data_abertura',
                'p.data_resolucao',

                'e.numero_pedido',
                'e.cliente_nome',
                'e.endereco',
                'e.regiao_id',

                'r.nome as regiao_nome',

                'ei.codigo_produto',
                'ei.descricao_produto',
                'ei.unidade',
            ])
            ->selectSub(
                DB::table('saida_pendencias as sp')
                    ->join(
                        'saidas as s',
                        's.id',
                        '=',
                        'sp.saida_id'
                    )
                    ->select('s.id')
                    ->whereColumn(
                        'sp.pendencia_id',
                        'p.id'
                    )
                    ->whereIn(
                        's.status',
                        [
                            'PLANEJADA',
                            'EM_ANDAMENTO',
                        ]
                    )
                    ->orderByDesc('s.id')
                    ->limit(1),
                'carrada_atual_id'
            )
            ->selectSub(
                DB::table('saida_pendencias as sp')
                    ->join(
                        'saidas as s',
                        's.id',
                        '=',
                        'sp.saida_id'
                    )
                    ->select('s.status')
                    ->whereColumn(
                        'sp.pendencia_id',
                        'p.id'
                    )
                    ->whereIn(
                        's.status',
                        [
                            'PLANEJADA',
                            'EM_ANDAMENTO',
                        ]
                    )
                    ->orderByDesc('s.id')
                    ->limit(1),
                'carrada_atual_status'
            );

        if ($request->filled('busca')) {
            $busca = trim(
                $request->string('busca')
                    ->toString()
            );

            $query->where(function ($q) use ($busca) {
                $termo = "%{$busca}%";

                $q->where(
                    'e.numero_pedido',
                    'ilike',
                    $termo
                )
                    ->orWhere(
                        'e.cliente_nome',
                        'ilike',
                        $termo
                    )
                    ->orWhere(
                        'ei.descricao_produto',
                        'ilike',
                        $termo
                    )
                    ->orWhere(
                        'ei.codigo_produto',
                        'ilike',
                        $termo
                    );
            });
        }

        if ($request->filled('status')) {
            $status = strtoupper(
                $request->string('status')
                    ->toString()
            );

            if (in_array(
                $status,
                [
                    StatusPendencia::ABERTA->value,
                    StatusPendencia::PROGRAMADA->value,
                    StatusPendencia::RESOLVIDA->value,
                    StatusPendencia::CANCELADA->value,
                ],
                true
            )) {
                $query->where(
                    'p.status',
                    $status
                );
            }
        }

        if ($request->filled('regiao_id')) {
            $query->where(
                'e.regiao_id',
                (int) $request->integer('regiao_id')
            );
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate(
                'p.data_abertura',
                '>=',
                $request->string('data_inicio')
                    ->toString()
            );
        }

        if ($request->filled('data_fim')) {
            $query->whereDate(
                'p.data_abertura',
                '<=',
                $request->string('data_fim')
                    ->toString()
            );
        }

        $paginacao = $query
            ->orderByRaw(
                "CASE p.status
                    WHEN 'ABERTA' THEN 1
                    WHEN 'PROGRAMADA' THEN 2
                    WHEN 'RESOLVIDA' THEN 3
                    ELSE 4
                END"
            )
            ->orderBy('p.data_abertura')
            ->orderBy('p.id')
            ->paginate($perPage);

        $dados = collect(
            $paginacao->items()
        )->map(function ($pendencia) {
            $original = (float)
                $pendencia->quantidade_original;

            $resolvida = (float)
                $pendencia->quantidade_resolvida;

            return [
                'id' =>
                    (int) $pendencia->id,

                'entrega_id' =>
                    (int) $pendencia->entrega_id,

                'entrega_item_id' =>
                    (int) $pendencia->entrega_item_id,

                'numero_pedido' =>
                    $pendencia->numero_pedido,

                'cliente_nome' =>
                    $pendencia->cliente_nome,

                'endereco' =>
                    $pendencia->endereco,

                'regiao' => [
                    'id' =>
                        $pendencia->regiao_id
                            ? (int) $pendencia->regiao_id
                            : null,

                    'nome' =>
                        $pendencia->regiao_nome
                        ?? 'Sem região',
                ],

                'produto' => [
                    'codigo' =>
                        $pendencia->codigo_produto,

                    'descricao' =>
                        $pendencia->descricao_produto,

                    'unidade' =>
                        $pendencia->unidade,
                ],

                'quantidade_original' =>
                    $original,

                'quantidade_resolvida' =>
                    $resolvida,

                'saldo_pendente' =>
                    max(
                        0,
                        round(
                            $original - $resolvida,
                            3
                        )
                    ),

                'status' =>
                    $pendencia->status,

                'motivo' =>
                    $pendencia->motivo,

                'observacao' =>
                    $pendencia->observacao,

                'data_abertura' =>
                    $pendencia->data_abertura,

                'data_resolucao' =>
                    $pendencia->data_resolucao,

                'carrada_atual' =>
                    $pendencia->carrada_atual_id
                        ? [
                            'id' =>
                                (int) $pendencia->carrada_atual_id,

                            'status' =>
                                $pendencia->carrada_atual_status,
                        ]
                        : null,

                'pode_resolver_manualmente' =>
                    $pendencia->status ===
                    StatusPendencia::ABERTA->value,
            ];
        })->values();

        $resumo = [
            'abertas' =>
                Pendencia::query()
                    ->where(
                        'status',
                        StatusPendencia::ABERTA
                    )
                    ->count(),

            'programadas' =>
                Pendencia::query()
                    ->where(
                        'status',
                        StatusPendencia::PROGRAMADA
                    )
                    ->count(),

            'resolvidas_30_dias' =>
                Pendencia::query()
                    ->where(
                        'status',
                        StatusPendencia::RESOLVIDA
                    )
                    ->where(
                        'data_resolucao',
                        '>=',
                        now()->subDays(30)
                    )
                    ->count(),

            'total_30_dias' =>
                Pendencia::query()
                    ->where(
                        'data_abertura',
                        '>=',
                        now()->subDays(30)
                    )
                    ->count(),
        ];

        return response()->json([
            'data' =>
                $dados,

            'resumo' =>
                $resumo,

            'meta' => [
                'current_page' =>
                    $paginacao->currentPage(),

                'last_page' =>
                    $paginacao->lastPage(),

                'per_page' =>
                    $paginacao->perPage(),

                'total' =>
                    $paginacao->total(),
            ],
        ]);
    }

    /**
     * Resolve uma pendência sem criar carrada falsa.
     */
    public function resolverManualmente(
        ResolverPendenciaManualRequest $request,
        Pendencia $pendencia
    ): JsonResponse {
        $pendencia = $this
            ->pendenciaService
            ->resolverManualmente(
                $pendencia,
                $request->validated(),
                $request->user()
            );

        return response()->json([
            'data' => [
                'id' =>
                    $pendencia->id,

                'status' =>
                    $pendencia->status->value,

                'quantidade_original' =>
                    (float)
                    $pendencia->quantidade_original,

                'quantidade_resolvida' =>
                    (float)
                    $pendencia->quantidade_resolvida,

                'saldo_pendente' =>
                    max(
                        0,
                        round(
                            (float)
                            $pendencia->quantidade_original
                            -
                            (float)
                            $pendencia->quantidade_resolvida,
                            3
                        )
                    ),

                'data_resolucao' =>
                    $pendencia->data_resolucao,
            ],
        ]);
    }
}
