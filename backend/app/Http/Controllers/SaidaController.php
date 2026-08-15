<?php

namespace App\Http\Controllers;

use App\Enums\StatusEntrega;
use App\Enums\StatusPendencia;

use App\Http\Requests\Saidas\RegistrarResultadoEntregaRequest;
use App\Http\Requests\Saidas\RegistrarResultadoPendenciaRequest;
use App\Services\EntregaService;
use App\Services\PendenciaService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Saidas\AdicionarEntregaSaidaRequest;
use App\Http\Requests\Saidas\StoreSaidaRequest;
use App\Http\Resources\SaidaResource;
use App\Models\Entrega;
use App\Models\Pendencia;
use App\Models\Saida;
use App\Services\SaidaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SaidaController extends Controller
{
    public function __construct(
        private readonly SaidaService $saidaService,
        private readonly EntregaService $entregaService,
        private readonly PendenciaService $pendenciaService
    ) 
    {
        // Constructor left intentionally empty; property promotion handles assignments
    }
    
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $saidas = Saida::query()
            ->with([
                'regiaoPrincipal',
                'veiculo',

                'saidaEntregas.entrega.tentativas',

                'saidaEntregas.itensProgramados.entregaItem',
            ])

            ->when(
                $request->filled('busca'),
                function ($query) use ($request) {
                    $busca =
                        trim(
                            $request
                                ->string('busca')
                                ->toString()
                        );

                    /*
                     * Aceita:
                     *
                     * 12
                     * #12
                     * Carrada 12
                     */
                    $numeroCarrada =
                        preg_replace(
                            '/\D+/',
                            '',
                            $busca
                        );

                    if (
                        $numeroCarrada === ''
                    ) {
                        /*
                         * Se não existir número na busca,
                         * não retornamos uma carrada errada.
                         */
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->where(
                        'id',
                        (int) $numeroCarrada
                    );
                }
            )

            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )

            ->when(
                $request->filled('data'),
                fn ($query) => $query->whereDate(
                    'data_saida',
                    $request->string('data')->toString()
                )
            )

            ->orderByDesc('data_saida')
            ->orderByDesc('id')
            ->paginate(20);

        return SaidaResource::collection($saidas);
    }

    public function store(
        StoreSaidaRequest $request
    ): SaidaResource {
        $saida = $this->saidaService->criar(
            $request->validated(),
            $request->user(),
        );

        $saida->load([
            'saidaEntregas.entrega.tentativas',
            'regiaoPrincipal',
            'veiculo',

            'saidaEntregas.entrega',

            'saidaEntregas.itensProgramados.entregaItem',
        ]);

        return new SaidaResource($saida);
    }

    public function show(
        Saida $saida
    ): SaidaResource {
        $saida->load([
            'saidaEntregas.entrega.tentativas',
            'regiaoPrincipal',
            'veiculo',

            'saidaEntregas.entrega.itens',

            'saidaEntregas.itensProgramados.entregaItem',

            'equipe.funcionario',

            'pendencias',
        ]);

        return new SaidaResource($saida);
    }


    /**
     * Lista as entregas que podem ser adicionadas à carrada,
     * considerando todas as regiões cadastradas na rota.
     *
     * A região principal também entra no filtro caso não tenha
     * sido adicionada explicitamente à rota.
     */
    public function entregasDisponiveis(
        Saida $saida
    ): JsonResponse {
        $regiaoIds =
            $this->regiaoIdsDaRota($saida);

        /*
         * Sem regiões cadastradas na rota não existe
         * associação segura para localizar entregas.
         * Paradas livres continuam apenas descritivas.
         */
        if ($regiaoIds->isEmpty()) {
            return response()->json([
                'data' => [],
            ]);
        }

        $entregaIdsJaNaCarrada =
            $saida
                ->entregas()
                ->pluck('entregas.id');

        $entregas =
            Entrega::query()
                ->with([
                    'regiao',
                    'itens',
                ])
                ->whereIn(
                    'status',
                    [
                        StatusEntrega::PENDENTE->value,
                        StatusEntrega::PROGRAMADA->value,
                        StatusEntrega::PARCIAL->value,
                        StatusEntrega::NAO_ENTREGUE->value,
                    ]
                )
                ->whereIn(
                    'regiao_id',
                    $regiaoIds->all()
                )
                ->when(
                    $entregaIdsJaNaCarrada->isNotEmpty(),
                    fn ($query) =>
                        $query->whereNotIn(
                            'id',
                            $entregaIdsJaNaCarrada->all()
                        )
                )
                ->get();

        /*
         * Uma entrega PARCIAL pode existir somente porque
         * possui pendência. Nesse caso o saldo normal é zero
         * e ela não deve aparecer na lista de entregas normais.
         * A reentrega aparecerá na seção de pendências.
         */
        $entregas =
            $entregas
                ->filter(
                    function (Entrega $entrega) {
                        $itens =
                            $this->saidaService
                                ->itensDisponiveis(
                                    $entrega
                                );

                        return collect($itens)
                            ->contains(
                                fn ($item) =>
                                    (float) (
                                        $item[
                                            'quantidade_disponivel'
                                        ] ?? 0
                                    ) > 0
                            );
                    }
                );

        $ordemRegioes =
            $regiaoIds->flip();

        $entregas =
            $entregas
                ->sortBy(
                    function ($entrega) use (
                        $ordemRegioes
                    ) {
                        $ordemRegiao =
                            $ordemRegioes->get(
                                (int) $entrega->regiao_id,
                                PHP_INT_MAX
                            );

                        return sprintf(
                            '%010d-%010d',
                            $ordemRegiao,
                            $entrega->id
                        );
                    }
                )
                ->values();

        return response()->json([
            'data' =>
                $entregas->map(
                    function ($entrega) {
                        $status =
                            $entrega->status instanceof
                                \BackedEnum
                                ? $entrega->status->value
                                : $entrega->status;

                        $prioridade =
                            $entrega->prioridade instanceof
                                \BackedEnum
                                ? $entrega->prioridade->value
                                : $entrega->prioridade;

                        return [
                            'id' =>
                                $entrega->id,

                            'numero_pedido' =>
                                $entrega->numero_pedido,

                            'cliente_nome' =>
                                $entrega->cliente_nome,

                            'endereco' =>
                                $entrega->endereco,

                            'regiao_id' =>
                                $entrega->regiao_id,

                            'regiao_nome' =>
                                $entrega->regiao?->nome
                                ?? 'Sem região',

                            'status' =>
                                $status,

                            'prioridade' =>
                                $prioridade,
                        ];
                    }
                )
                ->values(),
        ]);
    }

    /**
     * Pendências ABERTAS das regiões cadastradas na rota.
     *
     * Uma pendência é uma reentrega e fica separada do
     * saldo normal para evitar programar a mesma quantidade
     * duas vezes.
     */
    public function pendenciasDisponiveis(
        Saida $saida
    ): JsonResponse {
        $regiaoIds =
            $this->regiaoIdsDaRota($saida);

        if ($regiaoIds->isEmpty()) {
            return response()->json([
                'data' => [],
            ]);
        }

        $pendencias =
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
                ->where(
                    'p.status',
                    StatusPendencia::ABERTA->value
                )
                ->whereIn(
                    'e.regiao_id',
                    $regiaoIds->all()
                )
                ->whereRaw(
                    '(p.quantidade_original - p.quantidade_resolvida) > 0'
                )
                ->whereNotExists(
                    function ($query) use ($saida) {
                        $query
                            ->selectRaw('1')
                            ->from(
                                'saida_pendencias as sp'
                            )
                            ->whereColumn(
                                'sp.pendencia_id',
                                'p.id'
                            )
                            ->where(
                                'sp.saida_id',
                                $saida->id
                            );
                    }
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
                    'e.numero_pedido',
                    'e.cliente_nome',
                    'e.endereco',
                    'e.regiao_id',
                    'r.nome as regiao_nome',
                    'ei.codigo_produto',
                    'ei.descricao_produto',
                    'ei.unidade',
                ])
                ->get();

        return response()->json([
            'data' =>
                $this->ordenarEFormatarPendencias(
                    $pendencias,
                    $regiaoIds
                ),
        ]);
    }

    /**
     * Pendências que já foram colocadas nesta carrada.
     */
    public function pendenciasDaCarrada(
        Saida $saida
    ): JsonResponse {
        $regiaoIds =
            $this->regiaoIdsDaRota($saida);

        $pendencias =
            DB::table('saida_pendencias as sp')
                ->join(
                    'pendencias as p',
                    'p.id',
                    '=',
                    'sp.pendencia_id'
                )
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
                ->leftJoin(
                    'saida_pendencia_retornos as spr',
                    function ($join) {
                        $join
                            ->on(
                                'spr.saida_id',
                                '=',
                                'sp.saida_id'
                            )
                            ->on(
                                'spr.pendencia_id',
                                '=',
                                'sp.pendencia_id'
                            );
                    }
                )
                ->where(
                    'sp.saida_id',
                    $saida->id
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
                    'e.numero_pedido',
                    'e.cliente_nome',
                    'e.endereco',
                    'e.regiao_id',
                    'r.nome as regiao_nome',
                    'ei.codigo_produto',
                    'ei.descricao_produto',
                    'ei.unidade',
                    'spr.id as resultado_id',
                    'spr.quantidade_programada as resultado_quantidade_programada',
                    'spr.quantidade_entregue as resultado_quantidade_entregue',
                    'spr.quantidade_devolvida as resultado_quantidade_devolvida',
                    'spr.quantidade_reentrega as resultado_quantidade_reentrega',
                    'spr.destino_restante as resultado_destino_restante',
                    'spr.motivo_devolucao as resultado_motivo_devolucao',
                    'spr.observacao as resultado_observacao',
                    'spr.data_hora as resultado_data_hora',
                ])
                ->get();

        return response()->json([
            'data' =>
                $this->ordenarEFormatarPendencias(
                    $pendencias,
                    $regiaoIds
                ),
        ]);
    }

    public function adicionarPendencia(
        Saida $saida,
        Pendencia $pendencia
    ): Response {
        $this->saidaService
            ->adicionarPendencia(
                $saida,
                $pendencia
            );

        return response()->noContent();
    }

    public function removerPendencia(
        Saida $saida,
        Pendencia $pendencia
    ): Response {
        $this->saidaService
            ->removerPendencia(
                $saida,
                $pendencia
            );

        return response()->noContent();
    }

    public function registrarResultadoPendencia(
        RegistrarResultadoPendenciaRequest $request,
        Saida $saida,
        Pendencia $pendencia
    ): JsonResponse {
        $resultado =
            $this->pendenciaService
                ->registrarRetorno(
                    $saida,
                    $pendencia,
                    $request->validated(),
                    $request->user()
                );

        return response()->json([
            'data' => [
                'id' =>
                    $resultado->id,

                'saida_id' =>
                    $resultado->saida_id,

                'pendencia_id' =>
                    $resultado->pendencia_id,

                'quantidade_programada' =>
                    (float)
                    $resultado
                        ->quantidade_programada,

                'quantidade_entregue' =>
                    (float)
                    $resultado
                        ->quantidade_entregue,

                'quantidade_devolvida' =>
                    (float)
                    $resultado
                        ->quantidade_devolvida,

                'quantidade_reentrega' =>
                    (float)
                    $resultado
                        ->quantidade_reentrega,

                'destino_restante' =>
                    $resultado
                        ->destino_restante,

                'motivo_devolucao' =>
                    $resultado
                        ->motivo_devolucao,

                'observacao' =>
                    $resultado
                        ->observacao,

                'data_hora' =>
                    $resultado
                        ->data_hora,
            ],
        ], 201);
    }

    public function registrarResultadoEntrega(
        RegistrarResultadoEntregaRequest $request,
        Saida $saida,
        Entrega $entrega
    ): JsonResponse {
        $dados = $request->validated();

        $tentativa =
            $this->entregaService->registrarResultado(
                $entrega,
                $saida,
                $dados['itens'],
                $request->user(),
                $dados['motivo_nao_entrega'] ?? null,
                $dados['observacao'] ?? null
            );

        return response()->json([
            'data' => [
                'id' => $tentativa->id,

                'entrega_id' =>
                    $tentativa->entrega_id,

                'saida_id' =>
                    $tentativa->saida_id,

                'resultado' =>
                    $tentativa->resultado->value,

                'motivo_nao_entrega' =>
                    $tentativa->motivo_nao_entrega,

                'observacao' =>
                    $tentativa->observacao,

                'data_hora' =>
                    $tentativa->data_hora,

                'itens' =>
                    $tentativa->itens
                        ->map(function ($item) {
                            return [
                                'id' =>
                                    $item->id,

                                'entrega_item_id' =>
                                    $item->entrega_item_id,

                                'quantidade_entregue' =>
                                    (float)
                                    $item->quantidade_entregue,

                                'observacao' =>
                                    $item->observacao,
                            ];
                        })
                        ->values(),
            ],
        ], 201);
    }

    public function itensDisponiveis(
        Entrega $entrega
    ): JsonResponse {
        return response()->json([
            'data' => [
                'entrega_id' => $entrega->id,

                'numero_pedido' =>
                    $entrega->numero_pedido,

                'cliente_nome' =>
                    $entrega->cliente_nome,

                'endereco' =>
                    $entrega->endereco,

                'itens' =>
                    $this->saidaService
                        ->itensDisponiveis($entrega),
            ],
        ]);
    }

    public function adicionarEntrega(
        AdicionarEntregaSaidaRequest $request,
        Saida $saida
    ): SaidaResource {
        $dados = $request->validated();

        $entrega = Entrega::findOrFail(
            $dados['entrega_id']
        );

        $this->saidaService->adicionarEntrega(
            $saida,
            $entrega,
            $dados['ordem'] ?? null,
            $dados['itens'] ?? []
        );

        $saida->refresh()->load([
            'saidaEntregas.entrega.tentativas',
            'regiaoPrincipal',
            'veiculo',

            'saidaEntregas.entrega',

            'saidaEntregas.itensProgramados.entregaItem',
        ]);

        return new SaidaResource($saida);
    }

    public function removerEntrega(
        Saida $saida,
        Entrega $entrega
    ): SaidaResource {
        $this->saidaService->removerEntrega(
            $saida,
            $entrega
        );

        $saida->refresh()->load([
            'saidaEntregas.entrega.tentativas',
            'regiaoPrincipal',
            'veiculo',

            'saidaEntregas.entrega',

            'saidaEntregas.itensProgramados.entregaItem',
        ]);

        return new SaidaResource($saida);
    }

    public function iniciar(
        Saida $saida
    ): SaidaResource {
        $this->saidaService->iniciar($saida);

        $saida->refresh()->load([
            'saidaEntregas.entrega.tentativas',
            'regiaoPrincipal',
            'veiculo',

            'saidaEntregas.entrega',

            'saidaEntregas.itensProgramados.entregaItem',
        ]);

        return new SaidaResource($saida);
    }

    public function finalizar(
        Saida $saida
    ): SaidaResource {
        $this->saidaService->finalizar($saida);

        $saida->refresh()->load([
            'saidaEntregas.entrega.tentativas',
            'regiaoPrincipal',
            'veiculo',

            'saidaEntregas.entrega',

            'saidaEntregas.itensProgramados.entregaItem',
        ]);

        return new SaidaResource($saida);
    }

    public function cancelar(
        Saida $saida
    ): Response {
        $this->saidaService->cancelar($saida);

        return response()->noContent();
    }

    /**
     * IDs das regiões cadastradas na rota, preservando
     * a ordem do percurso. A região principal entra como
     * fallback quando não estiver explicitamente na rota.
     */
    private function regiaoIdsDaRota(
        Saida $saida
    ) {
        $rota =
            is_array($saida->rota)
                ? $saida->rota
                : [];

        $regiaoIds =
            collect($rota)
                ->map(
                    fn ($parada) =>
                        is_array($parada)
                            ? ($parada['regiao_id'] ?? null)
                            : null
                )
                ->filter(
                    fn ($regiaoId) =>
                        $regiaoId !== null
                )
                ->map(
                    fn ($regiaoId) =>
                        (int) $regiaoId
                )
                ->unique()
                ->values();

        if (
            $saida->regiao_principal_id !== null &&
            ! $regiaoIds->contains(
                (int) $saida->regiao_principal_id
            )
        ) {
            $regiaoIds->push(
                (int) $saida->regiao_principal_id
            );
        }

        return $regiaoIds;
    }

    /**
     * Normaliza o formato usado pelo frontend e ordena
     * as reentregas de acordo com a rota da carrada.
     */
    private function ordenarEFormatarPendencias(
        $pendencias,
        $regiaoIds
    ): array {
        $ordemRegioes =
            $regiaoIds->flip();

        return $pendencias
            ->sortBy(
                function ($pendencia) use (
                    $ordemRegioes
                ) {
                    $ordemRegiao =
                        $ordemRegioes->get(
                            (int) $pendencia->regiao_id,
                            PHP_INT_MAX
                        );

                    return sprintf(
                        '%010d-%010d',
                        $ordemRegiao,
                        $pendencia->id
                    );
                }
            )
            ->map(
                function ($pendencia) {
                    $resultadoId =
                        $pendencia->resultado_id
                        ?? null;

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

                        'regiao_id' =>
                            (int) $pendencia->regiao_id,

                        'regiao_nome' =>
                            $pendencia->regiao_nome
                            ?? 'Sem região',

                        'codigo_produto' =>
                            $pendencia->codigo_produto,

                        'descricao_produto' =>
                            $pendencia->descricao_produto,

                        'unidade' =>
                            $pendencia->unidade,

                        'quantidade_original' =>
                            (float) $pendencia
                                ->quantidade_original,

                        'quantidade_resolvida' =>
                            (float) $pendencia
                                ->quantidade_resolvida,

                        'saldo_pendente' =>
                            max(
                                0,
                                round(
                                    (float) $pendencia
                                        ->quantidade_original
                                    -
                                    (float) $pendencia
                                        ->quantidade_resolvida,
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

                        'resultado_registrado' =>
                            $resultadoId !== null,

                        'resultado_id' =>
                            $resultadoId !== null
                                ? (int) $resultadoId
                                : null,

                        'quantidade_programada_resultado' =>
                            isset(
                                $pendencia
                                    ->resultado_quantidade_programada
                            )
                                ? (float)
                                    $pendencia
                                        ->resultado_quantidade_programada
                                : null,

                        'quantidade_entregue_resultado' =>
                            (float) (
                                $pendencia
                                    ->resultado_quantidade_entregue
                                ?? 0
                            ),

                        'quantidade_devolvida_resultado' =>
                            (float) (
                                $pendencia
                                    ->resultado_quantidade_devolvida
                                ?? 0
                            ),

                        'quantidade_reentrega_resultado' =>
                            (float) (
                                $pendencia
                                    ->resultado_quantidade_reentrega
                                ?? 0
                            ),

                        'destino_restante' =>
                            $pendencia
                                ->resultado_destino_restante
                            ?? null,

                        'motivo_devolucao' =>
                            $pendencia
                                ->resultado_motivo_devolucao
                            ?? null,

                        'observacao_resultado' =>
                            $pendencia
                                ->resultado_observacao
                            ?? null,

                        'data_hora_resultado' =>
                            $pendencia
                                ->resultado_data_hora
                            ?? null,
                    ];
                }
            )
            ->values()
            ->toArray();
    }

}