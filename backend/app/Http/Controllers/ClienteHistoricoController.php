<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Entrega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteHistoricoController extends Controller
{
    public function __invoke(
        Request $request,
        Cliente $cliente
    ): JsonResponse {
        $perPage = max(
            5,
            min(
                50,
                (int) $request->input(
                    'per_page',
                    10
                )
            )
        );

        $baseQuery = Entrega::query()
            ->where(
                'cliente_id',
                $cliente->id
            );

        /*
         * Resumo geral do cliente.
         *
         * Não depende da página atual.
         */
        $resumo = [
            'total' =>
                (clone $baseQuery)->count(),

            'pendentes' =>
                (clone $baseQuery)
                    ->where(
                        'status',
                        'PENDENTE'
                    )
                    ->count(),

            'programadas' =>
                (clone $baseQuery)
                    ->where(
                        'status',
                        'PROGRAMADA'
                    )
                    ->count(),

            'em_saida' =>
                (clone $baseQuery)
                    ->where(
                        'status',
                        'EM_SAIDA'
                    )
                    ->count(),

            'parciais' =>
                (clone $baseQuery)
                    ->where(
                        'status',
                        'PARCIAL'
                    )
                    ->count(),

            'concluidas' =>
                (clone $baseQuery)
                    ->where(
                        'status',
                        'CONCLUIDA'
                    )
                    ->count(),

            'nao_entregues' =>
                (clone $baseQuery)
                    ->where(
                        'status',
                        'NAO_ENTREGUE'
                    )
                    ->count(),

            'canceladas' =>
                (clone $baseQuery)
                    ->where(
                        'status',
                        'CANCELADA'
                    )
                    ->count(),
        ];

        $entregas = Entrega::query()
            ->where(
                'cliente_id',
                $cliente->id
            )
            ->with([
                'regiao',
                'itens',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'cliente' => [
                    'id' =>
                        $cliente->id,

                    'codigo_externo' =>
                        $cliente
                            ->codigo_externo,

                    'nome' =>
                        $cliente->nome,

                    'apelido' =>
                        $cliente->apelido,

                    'telefone' =>
                        $cliente->telefone,

                    'ativo' =>
                        (bool) $cliente->ativo,
                ],

                'resumo' => $resumo,

                'entregas' =>
                    collect(
                        $entregas->items()
                    )
                        ->map(
                            function (
                                Entrega $entrega
                            ) {
                                return [
                                    'id' =>
                                        $entrega->id,

                                    'numero_pedido' =>
                                        $entrega
                                            ->numero_pedido,

                                    'status' =>
                                        $entrega
                                            ->status
                                            ->value
                                        ?? $entrega
                                            ->status,

                                    'prioridade' =>
                                        $entrega
                                            ->prioridade
                                            ->value
                                        ?? $entrega
                                            ->prioridade,

                                    'endereco' =>
                                        $entrega
                                            ->endereco,

                                    'referencia' =>
                                        $entrega
                                            ->referencia,

                                    'observacao' =>
                                        $entrega
                                            ->observacao,

                                    'regiao' =>
                                        $entrega
                                            ->regiao
                                            ? [
                                                'id' =>
                                                    $entrega
                                                        ->regiao
                                                        ->id,

                                                'nome' =>
                                                    $entrega
                                                        ->regiao
                                                        ->nome,
                                            ]
                                            : null,

                                    'itens' =>
                                        $entrega
                                            ->itens
                                            ->map(
                                                fn ($item) => [
                                                    'id' =>
                                                        $item->id,

                                                    'codigo_produto' =>
                                                        $item
                                                            ->codigo_produto,

                                                    'descricao_produto' =>
                                                        $item
                                                            ->descricao_produto,

                                                    'unidade' =>
                                                        $item
                                                            ->unidade,

                                                    'quantidade_solicitada' =>
                                                        (float) $item
                                                            ->quantidade_solicitada,
                                                ]
                                            )
                                            ->values(),

                                    'created_at' =>
                                        $entrega
                                            ->created_at
                                            ?->toISOString(),
                                ];
                            }
                        )
                        ->values(),

                'meta' => [
                    'current_page' =>
                        $entregas
                            ->currentPage(),

                    'last_page' =>
                        $entregas
                            ->lastPage(),

                    'per_page' =>
                        $entregas
                            ->perPage(),

                    'total' =>
                        $entregas
                            ->total(),
                ],
            ],
        ]);
    }
}