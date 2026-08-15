<?php

namespace App\Services;

use App\Enums\StatusEntrega;
use App\Enums\StatusPendencia;
use App\Enums\StatusSaida;
use App\Models\Devolucao;
use App\Models\Entrega;
use App\Models\Pendencia;
use App\Models\Saida;
use App\Models\SaidaEntrega;
use App\Models\SaidaEntregaItem;
use App\Models\SaidaEvento;
use App\Models\TentativaItem;
use App\Models\User;
use App\Models\Veiculo;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaidaService
{
    /**
     * Cria uma nova saída/carrada.
     */
    public function criar(
        array $dados,
        User $usuario
    ): Saida {
        return DB::transaction(function () use (
            $dados,
            $usuario
        ) {
            /*
             * A rota é salva como snapshot da carrada.
             *
             * Cada parada guarda o nome daquele momento
             * e, quando existir, o id da região cadastrada.
             */
            $rota =
                collect(
                    $dados['rota'] ?? []
                )
                    ->map(
                        function ($parada) {
                            return [
                                'regiao_id' =>
                                    isset(
                                        $parada[
                                            'regiao_id'
                                        ]
                                    )
                                        ? (int)
                                            $parada[
                                                'regiao_id'
                                            ]
                                        : null,

                                'nome' =>
                                    trim(
                                        (string)
                                        $parada['nome']
                                    ),
                            ];
                        }
                    )
                    ->filter(
                        fn ($parada) =>
                            $parada['nome'] !== ''
                    )
                    ->values()
                    ->toArray();

            $saida = Saida::create([
                'regiao_principal_id' =>
                    $dados['regiao_principal_id'] ?? null,

                'veiculo_id' =>
                    $dados['veiculo_id'] ?? null,

                'data_saida' =>
                    $dados['data_saida'],

                'rota' =>
                    $rota,

                'status' =>
                    StatusSaida::PLANEJADA,

                'observacao' =>
                    $dados['observacao'] ?? null,

                'criado_por' =>
                    $usuario->id,
            ]);

            $this->registrarEvento(
                $saida,
                'SAIDA_CRIADA',
                "Carrada #{$saida->id} criada no sistema.",
                [
                    'data_saida' =>
                        $saida->data_saida,

                    'regiao_principal_id' =>
                        $saida->regiao_principal_id,

                    'veiculo_id' =>
                        $saida->veiculo_id,

                    'rota' =>
                        $rota,

                    'observacao' =>
                        $saida->observacao,
                ],
                $usuario
            );

            return $saida;
        });
    }

    /**
     * Adiciona uma entrega à carrada.
     *
     * Permite selecionar somente alguns produtos
     * e quantidades parciais.
     */
    public function adicionarEntrega(
        Saida $saida,
        Entrega $entrega,
        ?int $ordem = null,
        array $itens = []
    ): void {
        DB::transaction(function () use (
            $saida,
            $entrega,
            $ordem,
            $itens
        ) {
            if (
                $saida->status !==
                StatusSaida::PLANEJADA
            ) {
                throw ValidationException::withMessages([
                    'saida' => [
                        'Só é possível adicionar entregas em uma saída planejada.',
                    ],
                ]);
            }

            if (
                in_array(
                    $entrega->status,
                    [
                        StatusEntrega::CONCLUIDA,
                        StatusEntrega::CANCELADA,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'entrega' => [
                        'Esta entrega não pode ser adicionada a uma saída.',
                    ],
                ]);
            }

            $entrega->loadMissing('itens');

            $saidaEntrega =
                SaidaEntrega::firstOrCreate(
                    [
                        'saida_id' =>
                            $saida->id,

                        'entrega_id' =>
                            $entrega->id,
                    ],
                    [
                        'ordem' =>
                            $ordem,
                    ]
                );

            $novaAssociacao =
                $saidaEntrega->wasRecentlyCreated;

            if ($ordem !== null) {
                $saidaEntrega->update([
                    'ordem' => $ordem,
                ]);
            }

            /*
             * O frontend informou produtos
             * específicos e suas quantidades.
             */
            if (! empty($itens)) {
                $idsSelecionados = [];

                foreach ($itens as $dadosItem) {
                    $item = $entrega
                        ->itens
                        ->firstWhere(
                            'id',
                            (int) $dadosItem[
                                'entrega_item_id'
                            ]
                        );

                    if (! $item) {
                        throw ValidationException::withMessages([
                            'itens' => [
                                'Um dos produtos informados não pertence a esta entrega.',
                            ],
                        ]);
                    }

                    $quantidade =
                        (float) $dadosItem[
                            'quantidade_programada'
                        ];

                    if ($quantidade <= 0) {
                        throw ValidationException::withMessages([
                            'itens' => [
                                'A quantidade programada deve ser maior que zero.',
                            ],
                        ]);
                    }

                    /*
                     * Ignoramos a programação atual
                     * desta própria carrada para permitir
                     * edição da quantidade.
                     */
                    $saldoDisponivel =
                        $this
                            ->saldoDisponivelParaProgramacao(
                                $item->id,
                                (float) $item
                                    ->quantidade_solicitada,
                                $saidaEntrega->id
                            );

                    if (
                        $quantidade >
                        $saldoDisponivel
                    ) {
                        throw ValidationException::withMessages([
                            'itens' => [
                                "A quantidade programada é maior que o saldo disponível ({$saldoDisponivel}).",
                            ],
                        ]);
                    }

                    SaidaEntregaItem::updateOrCreate(
                        [
                            'saida_entrega_id' =>
                                $saidaEntrega->id,

                            'entrega_item_id' =>
                                $item->id,
                        ],
                        [
                            'quantidade_programada' =>
                                $quantidade,
                        ]
                    );

                    $idsSelecionados[] =
                        $item->id;
                }

                /*
                 * Remove produtos que estavam nesta
                 * carrada mas deixaram de ser escolhidos.
                 */
                $saidaEntrega
                    ->itensProgramados()
                    ->whereNotIn(
                        'entrega_item_id',
                        $idsSelecionados
                    )
                    ->delete();
            } else {
                /*
                 * Compatibilidade com o fluxo antigo.
                 *
                 * Sem itens informados, programa
                 * todo o saldo disponível.
                 */
                foreach ($entrega->itens as $item) {
                    $saldoDisponivel =
                        $this
                            ->saldoDisponivelParaProgramacao(
                                $item->id,
                                (float) $item
                                    ->quantidade_solicitada,
                                $saidaEntrega->id
                            );

                    if ($saldoDisponivel <= 0) {
                        continue;
                    }

                    SaidaEntregaItem::updateOrCreate(
                        [
                            'saida_entrega_id' =>
                                $saidaEntrega->id,

                            'entrega_item_id' =>
                                $item->id,
                        ],
                        [
                            'quantidade_programada' =>
                                $saldoDisponivel,
                        ]
                    );
                }
            }

            if (
                ! $saidaEntrega
                    ->itensProgramados()
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'itens' => [
                        'Esta entrega não possui quantidade disponível para programar.',
                    ],
                ]);
            }

            /*
             * IMPORTANTE:
             *
             * Não marcamos mais automaticamente
             * o pedido inteiro como PROGRAMADO.
             *
             * O status agora depende do saldo real.
             */
            $this->sincronizarStatusProgramacao(
                $entrega
            );

            /*
             * Guarda um snapshot dos produtos e
             * quantidades que ficaram nesta carrada.
             */
            $itensEvento =
                $this->snapshotItensProgramados(
                    $saidaEntrega
                );

            $identificadorPedido =
                $entrega->numero_pedido
                    ? "#{$entrega->numero_pedido}"
                    : "#{$entrega->id}";

            $this->registrarEvento(
                $saida,
                $novaAssociacao
                    ? 'ENTREGA_ADICIONADA'
                    : 'ENTREGA_ATUALIZADA',
                $novaAssociacao
                    ? "Pedido {$identificadorPedido} de {$entrega->cliente_nome} adicionado à carrada."
                    : "Programação do pedido {$identificadorPedido} de {$entrega->cliente_nome} atualizada na carrada.",
                [
                    'entrega_id' =>
                        $entrega->id,

                    'saida_entrega_id' =>
                        $saidaEntrega->id,

                    'numero_pedido' =>
                        $entrega->numero_pedido,

                    'cliente_nome' =>
                        $entrega->cliente_nome,

                    'ordem' =>
                        $saidaEntrega->ordem,

                    'itens' =>
                        $itensEvento,
                ]
            );
        });
    }

    /**
     * Remove uma entrega da carrada.
     */
    public function removerEntrega(
        Saida $saida,
        Entrega $entrega
    ): void {
        DB::transaction(function () use (
            $saida,
            $entrega
        ) {
            if (
                $saida->status !==
                StatusSaida::PLANEJADA
            ) {
                throw new DomainException(
                    'Só é possível remover entregas de uma saída planejada.'
                );
            }

            $saidaEntrega =
                SaidaEntrega::query()
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->where(
                        'entrega_id',
                        $entrega->id
                    )
                    ->first();

            $itensEvento =
                $saidaEntrega
                    ? $this->snapshotItensProgramados(
                        $saidaEntrega
                    )
                    : [];

            $saida
                ->entregas()
                ->detach($entrega->id);

            /*
             * Após remover, recalcula o saldo
             * e o status do pedido.
             */
            $this->sincronizarStatusProgramacao(
                $entrega
            );

            $identificadorPedido =
                $entrega->numero_pedido
                    ? "#{$entrega->numero_pedido}"
                    : "#{$entrega->id}";

            $this->registrarEvento(
                $saida,
                'ENTREGA_REMOVIDA',
                "Pedido {$identificadorPedido} de {$entrega->cliente_nome} removido da carrada.",
                [
                    'entrega_id' =>
                        $entrega->id,

                    'numero_pedido' =>
                        $entrega->numero_pedido,

                    'cliente_nome' =>
                        $entrega->cliente_nome,

                    'itens' =>
                        $itensEvento,
                ]
            );
        });
    }

    /**
     * Adiciona uma pendência antiga à carrada.
     */
    public function adicionarPendencia(
        Saida $saida,
        Pendencia $pendencia
    ): void {
        DB::transaction(function () use (
            $saida,
            $pendencia
        ) {
            if (
                $saida->status !==
                StatusSaida::PLANEJADA
            ) {
                throw new DomainException(
                    'Só é possível adicionar pendências em uma saída planejada.'
                );
            }

            if (
                in_array(
                    $pendencia->status,
                    [
                        StatusPendencia::RESOLVIDA,
                        StatusPendencia::CANCELADA,
                    ],
                    true
                )
            ) {
                throw new DomainException(
                    'Esta pendência não está disponível para reentrega.'
                );
            }

            $saldoPendente =
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
                );

            if ($saldoPendente <= 0) {
                throw ValidationException::withMessages([
                    'pendencia' => [
                        'Esta pendência não possui saldo para reentrega.',
                    ],
                ]);
            }

            $jaNestaCarrada =
                DB::table('saida_pendencias')
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->where(
                        'pendencia_id',
                        $pendencia->id
                    )
                    ->exists();

            if ($jaNestaCarrada) {
                return;
            }

            /*
             * Uma pendência PROGRAMADA não pode ser colocada
             * simultaneamente em duas carradas.
             */
            if (
                $pendencia->status ===
                StatusPendencia::PROGRAMADA
            ) {
                throw ValidationException::withMessages([
                    'pendencia' => [
                        'Esta pendência já está programada em outra carrada.',
                    ],
                ]);
            }

            $saida
                ->pendencias()
                ->syncWithoutDetaching([
                    $pendencia->id,
                ]);

            $pendencia->update([
                'status' =>
                    StatusPendencia::PROGRAMADA,
            ]);

            $this->registrarEvento(
                $saida,
                'PENDENCIA_ADICIONADA',
                "Pendência #{$pendencia->id} adicionada à carrada.",
                [
                    'pendencia_id' =>
                        $pendencia->id,

                    'entrega_id' =>
                        $pendencia->entrega_id,

                    'entrega_item_id' =>
                        $pendencia->entrega_item_id,

                    'quantidade_original' =>
                        (float) $pendencia
                            ->quantidade_original,

                    'quantidade_resolvida' =>
                        (float) $pendencia
                            ->quantidade_resolvida,

                    'saldo_pendente' =>
                        $saldoPendente,
                ]
            );
        });
    }

    /**
     * Remove uma pendência da carrada.
     */
    public function removerPendencia(
        Saida $saida,
        Pendencia $pendencia
    ): void {
        DB::transaction(function () use (
            $saida,
            $pendencia
        ) {
            if (
                $saida->status !==
                StatusSaida::PLANEJADA
            ) {
                throw new DomainException(
                    'Só é possível remover pendências de uma saída planejada.'
                );
            }

            $estaNestaCarrada =
                DB::table('saida_pendencias')
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->where(
                        'pendencia_id',
                        $pendencia->id
                    )
                    ->exists();

            if (! $estaNestaCarrada) {
                throw ValidationException::withMessages([
                    'pendencia' => [
                        'Esta pendência não pertence a esta carrada.',
                    ],
                ]);
            }

            $saida
                ->pendencias()
                ->detach($pendencia->id);

            $pendencia->update([
                'status' =>
                    StatusPendencia::ABERTA,
            ]);

            $this->registrarEvento(
                $saida,
                'PENDENCIA_REMOVIDA',
                "Pendência #{$pendencia->id} removida da carrada.",
                [
                    'pendencia_id' =>
                        $pendencia->id,

                    'entrega_id' =>
                        $pendencia->entrega_id,

                    'entrega_item_id' =>
                        $pendencia->entrega_item_id,
                ]
            );
        });
    }

    /**
     * Inicia a carrada.
     */
    public function iniciar(
        Saida $saida
    ): void {
        DB::transaction(function () use ($saida) {
            if (
                $saida->status !==
                StatusSaida::PLANEJADA
            ) {
                throw new DomainException(
                    'A saída não está planejada.'
                );
            }

            $possuiEntregas =
                $saida->entregas()->exists();

            $possuiPendencias =
                $saida->pendencias()->exists();

            if (
                ! $possuiEntregas &&
                ! $possuiPendencias
            ) {
                throw new DomainException(
                    'A saída não possui entregas ou pendências.'
                );
            }

            /*
             * Primeiro mudamos a carrada para
             * EM_ANDAMENTO.
             */
            $saida->update([
                'status' =>
                    StatusSaida::EM_ANDAMENTO,
            ]);

            /*
             * Depois sincronizamos cada pedido.
             *
             * Se todo o pedido estiver alocado,
             * ele fica EM_SAIDA.
             *
             * Se ainda houver saldo livre,
             * ele continua PENDENTE para permitir
             * formar outra carrada.
             */
            foreach (
                $saida->entregas as $entrega
            ) {
                $this->sincronizarStatusProgramacao(
                    $entrega
                );
            }

            /*
             * Carradas formadas somente por reentregas também
             * colocam o pedido em trânsito. Sem este passo, uma
             * carrada de pendência poderia estar EM_ANDAMENTO e
             * o pedido continuaria aparecendo apenas como PARCIAL.
             */
            foreach (
                $saida
                    ->pendencias()
                    ->with('entrega')
                    ->get() as $pendencia
            ) {
                if (! $pendencia->entrega) {
                    continue;
                }

                $this->sincronizarStatusProgramacao(
                    $pendencia->entrega
                );
            }

            $saida->loadMissing([
                'veiculo',
                'regiaoPrincipal',
            ]);

            $this->registrarEvento(
                $saida,
                'SAIDA_INICIADA',
                "Carrada #{$saida->id} iniciada.",
                [
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

                    'total_entregas' =>
                        $saida->entregas()->count(),

                    'total_pendencias' =>
                        $saida->pendencias()->count(),
                ]
            );
        });
    }

    /**
     * Finaliza uma carrada.
     */
    public function finalizar(
        Saida $saida
    ): void {
        DB::transaction(function () use ($saida) {
            if (
                $saida->status !==
                StatusSaida::EM_ANDAMENTO
            ) {
                throw new DomainException(
                    'Somente uma saída em andamento pode ser finalizada.'
                );
            }

            $entregasSemResultado =
                SaidaEntrega::query()
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->whereDoesntHave(
                        'entrega.tentativas',
                        function ($query) use ($saida) {
                            $query->where(
                                'saida_id',
                                $saida->id
                            );
                        }
                    )
                    ->count();

            /*
             * Reentregas entram na carrada por
             * saida_pendencias e possuem seu próprio
             * registro de retorno.
             */
            $pendenciasSemResultado =
                DB::table('saida_pendencias as sp')
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
                    ->whereNull('spr.id')
                    ->count();

            if (
                $entregasSemResultado > 0 ||
                $pendenciasSemResultado > 0
            ) {
                throw new DomainException(
                    'Registre o resultado de todas as entregas e reentregas antes de finalizar a carrada.'
                );
            }

            $saida->update([
                'status' =>
                    StatusSaida::FINALIZADA,
            ]);

            $this->registrarEvento(
                $saida,
                'SAIDA_FINALIZADA',
                "Carrada #{$saida->id} finalizada.",
                [
                    'total_entregas' =>
                        SaidaEntrega::query()
                            ->where(
                                'saida_id',
                                $saida->id
                            )
                            ->count(),

                    'total_reentregas' =>
                        DB::table('saida_pendencias')
                            ->where(
                                'saida_id',
                                $saida->id
                            )
                            ->count(),
                ]
            );
        });
    }

    /**
     * Troca o veículo usado na carrada.
     */
    public function trocarVeiculo(
        Saida $saida,
        ?int $veiculoId
    ): void {
        DB::transaction(function () use (
            $saida,
            $veiculoId
        ) {
            if (
                $saida->status ===
                StatusSaida::FINALIZADA
            ) {
                throw new DomainException(
                    'Não é possível alterar uma saída finalizada.'
                );
            }

            if (
                $saida->status ===
                StatusSaida::CANCELADA
            ) {
                throw new DomainException(
                    'Não é possível alterar uma saída cancelada.'
                );
            }

            $veiculoAnterior =
                $saida->veiculo_id
                    ? Veiculo::find(
                        $saida->veiculo_id
                    )
                    : null;

            $novoVeiculo =
                $veiculoId
                    ? Veiculo::find(
                        $veiculoId
                    )
                    : null;

            $saida->update([
                'veiculo_id' =>
                    $veiculoId,
            ]);

            $this->registrarEvento(
                $saida,
                'VEICULO_TROCADO',
                "Veículo da carrada #{$saida->id} alterado.",
                [
                    'veiculo_anterior' =>
                        $veiculoAnterior
                            ? [
                                'id' =>
                                    $veiculoAnterior->id,

                                'nome' =>
                                    $veiculoAnterior->nome,

                                'placa' =>
                                    $veiculoAnterior->placa,
                            ]
                            : null,

                    'veiculo_novo' =>
                        $novoVeiculo
                            ? [
                                'id' =>
                                    $novoVeiculo->id,

                                'nome' =>
                                    $novoVeiculo->nome,

                                'placa' =>
                                    $novoVeiculo->placa,
                            ]
                            : null,
                ]
            );
        });
    }

    /**
     * Retorna os produtos e o saldo disponível
     * de cada item para novas carradas.
     */
    public function itensDisponiveis(
            Entrega $entrega
        ): array {
            $entrega->loadMissing('itens');

            return $entrega
                ->itens
                ->map(function ($item) {
                    return [
                        'entrega_item_id' =>
                            $item->id,

                        'codigo_produto' =>
                            $item->codigo_produto,

                        'descricao_produto' =>
                            $item->descricao_produto
                            ?? 'Produto sem descrição',

                        'unidade' =>
                            $item->unidade,

                        'quantidade_solicitada' =>
                            (float) $item
                                ->quantidade_solicitada,

                        'quantidade_disponivel' =>
                            $this
                                ->saldoDisponivelParaProgramacao(
                                    $item->id,
                                    (float) $item
                                        ->quantidade_solicitada
                                ),
                    ];
                })
                ->values()
                ->toArray();
        }

    /**
     * Calcula o saldo ainda disponível.
     *
     * solicitado
     * - entregue
     * - pendências abertas
     * - programado em carradas ativas ainda sem retorno
     */
    private function saldoDisponivelParaProgramacao(
        int $entregaItemId,
        float $quantidadeSolicitada,
        ?int $ignorarSaidaEntregaId = null
    ): float {
        /*
         * 1. Quantidade entregue em entregas normais.
         */
        $quantidadeEntregueNormal =
            (float) TentativaItem::query()
                ->where(
                    'entrega_item_id',
                    $entregaItemId
                )
                ->sum('quantidade_entregue');

        /*
         * 2. Quantidade entregue ao cliente durante
         * reentregas de pendências.
         */
        $quantidadeEntregueReentrega =
            (float) DB::table(
                'saida_pendencia_retornos as spr'
            )
                ->join(
                    'pendencias as p',
                    'p.id',
                    '=',
                    'spr.pendencia_id'
                )
                ->where(
                    'p.entrega_item_id',
                    $entregaItemId
                )
                ->sum(
                    'spr.quantidade_entregue'
                );

        /*
         * 3. Quantidade encerrada como DEVOLVIDA.
         *
         * Ela não foi entregue ao cliente, mas não deve
         * voltar para o planejamento de uma nova carrada.
         */
        $quantidadeDevolvida =
            (float) Devolucao::query()
                ->where(
                    'entrega_item_id',
                    $entregaItemId
                )
                ->sum('quantidade');

        /*
         * 4. Pendências ainda abertas/programadas.
         *
         * Elas fazem parte do saldo do pedido, porém devem
         * aparecer no fluxo de REENTREGA e não como saldo
         * normal disponível.
         */
        $quantidadePendente =
            (float) Pendencia::query()
                ->where(
                    'entrega_item_id',
                    $entregaItemId
                )
                ->whereIn(
                    'status',
                    [
                        StatusPendencia::ABERTA->value,
                        StatusPendencia::PROGRAMADA->value,
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

        /*
         * 5. Quantidade normal reservada em carradas
         * planejadas/em andamento que ainda não tiveram
         * retorno registrado.
         */
        $query =
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
                    $entregaItemId
                )
                ->whereIn(
                    's.status',
                    [
                        StatusSaida::PLANEJADA->value,
                        StatusSaida::EM_ANDAMENTO->value,
                    ]
                )
                ->whereNull('te.id');

        if ($ignorarSaidaEntregaId !== null) {
            $query->where(
                'sei.saida_entrega_id',
                '!=',
                $ignorarSaidaEntregaId
            );
        }

        $quantidadeReservada =
            (float) $query->sum(
                'sei.quantidade_programada'
            );

        return max(
            0,
            round(
                $quantidadeSolicitada
                - $quantidadeEntregueNormal
                - $quantidadeEntregueReentrega
                - $quantidadeDevolvida
                - $quantidadePendente
                - $quantidadeReservada,
                3
            )
        );
    }

    /**
     * Sincroniza o status da entrega de acordo
     * com o saldo realmente programado.
     *
     * Exemplo:
     *
     * Pedido: 50
     *
     * Carrada 1: 20
     * Saldo: 30
     * Status: PENDENTE
     *
     * Carrada 2: 30
     * Saldo: 0
     * Status: PROGRAMADA
     *
     * Se uma das carradas com todo o saldo
     * alocado estiver em andamento:
     * Status: EM_SAIDA
     */
    private function sincronizarStatusProgramacao(
        Entrega $entrega
    ): void {
        /*
         * Não alteramos estados finais.
         */
        if (
            in_array(
                $entrega->status,
                [
                    StatusEntrega::CONCLUIDA,
                    StatusEntrega::CANCELADA,
                ],
                true
            )
        ) {
            return;
        }

        $entrega->loadMissing('itens');

        $possuiSaldoDisponivel = false;

        foreach ($entrega->itens as $item) {
            $saldo =
                $this
                    ->saldoDisponivelParaProgramacao(
                        $item->id,
                        (float) $item
                            ->quantidade_solicitada
                    );

            if ($saldo > 0) {
                $possuiSaldoDisponivel = true;
                break;
            }
        }

        /*
         * Ainda há produto livre.
         *
         * O pedido precisa continuar aparecendo
         * na lista para outra carrada.
         */
        if ($possuiSaldoDisponivel) {
            $entrega->update([
                'status' =>
                    StatusEntrega::PENDENTE,
            ]);

            return;
        }

        /*
         * Não existe saldo livre.
         *
         * Verificamos se alguma parte do pedido
         * está neste momento em uma carrada
         * EM_ANDAMENTO.
         */
        $possuiSaidaNormalEmAndamento =
            SaidaEntrega::query()
                ->where(
                    'entrega_id',
                    $entrega->id
                )
                ->whereHas(
                    'saida',
                    function ($query) {
                        $query->where(
                            'status',
                            StatusSaida::EM_ANDAMENTO
                                ->value
                        );
                    }
                )
                ->exists();

        /*
         * Uma reentrega também representa produto em trânsito.
         * Consideramos somente carradas ainda sem retorno para
         * essa pendência.
         */
        $possuiReentregaEmAndamento =
            DB::table('saida_pendencias as sp')
                ->join(
                    'pendencias as p',
                    'p.id',
                    '=',
                    'sp.pendencia_id'
                )
                ->join(
                    'saidas as s',
                    's.id',
                    '=',
                    'sp.saida_id'
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
                    'p.entrega_id',
                    $entrega->id
                )
                ->where(
                    's.status',
                    StatusSaida::EM_ANDAMENTO->value
                )
                ->whereNull('spr.id')
                ->exists();

        $possuiSaidaEmAndamento =
            $possuiSaidaNormalEmAndamento ||
            $possuiReentregaEmAndamento;

        if ($possuiSaidaEmAndamento) {
            $entrega->update([
                'status' =>
                    StatusEntrega::EM_SAIDA,
            ]);

            return;
        }

        /*
         * Todo o saldo está reservado,
         * mas ainda não saiu da loja.
         */
        $possuiSaidaNormalPlanejada =
            SaidaEntrega::query()
                ->where(
                    'entrega_id',
                    $entrega->id
                )
                ->whereHas(
                    'saida',
                    function ($query) {
                        $query->where(
                            'status',
                            StatusSaida::PLANEJADA
                                ->value
                        );
                    }
                )
                ->exists();

        $possuiReentregaPlanejada =
            DB::table('saida_pendencias as sp')
                ->join(
                    'pendencias as p',
                    'p.id',
                    '=',
                    'sp.pendencia_id'
                )
                ->join(
                    'saidas as s',
                    's.id',
                    '=',
                    'sp.saida_id'
                )
                ->where(
                    'p.entrega_id',
                    $entrega->id
                )
                ->where(
                    's.status',
                    StatusSaida::PLANEJADA->value
                )
                ->exists();

        $possuiSaidaPlanejada =
            $possuiSaidaNormalPlanejada ||
            $possuiReentregaPlanejada;

        if ($possuiSaidaPlanejada) {
            $entrega->update([
                'status' =>
                    StatusEntrega::PROGRAMADA,
            ]);

            return;
        }

        /*
         * Sem programação ativa.
         */
        $entrega->update([
            'status' =>
                StatusEntrega::PENDENTE,
        ]);
    }

    /**
     * Cancela uma saída.
     */
    public function cancelar(
        Saida $saida
    ): void {
        DB::transaction(function () use ($saida) {
            if (
                $saida->status ===
                StatusSaida::FINALIZADA
            ) {
                throw new DomainException(
                    'Uma saída finalizada não pode ser cancelada.'
                );
            }

            if (
                $saida->status ===
                StatusSaida::CANCELADA
            ) {
                throw new DomainException(
                    'Esta saída já está cancelada.'
                );
            }

            /*
             * Depois de registrar qualquer retorno, cancelar
             * a carrada apagaria o sentido histórico da operação.
             */
            $possuiRetornoNormal =
                DB::table('tentativa_entregas')
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->exists();

            $possuiRetornoReentrega =
                DB::table(
                    'saida_pendencia_retornos'
                )
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->exists();

            if (
                $possuiRetornoNormal ||
                $possuiRetornoReentrega
            ) {
                throw new DomainException(
                    'Não é possível cancelar uma carrada que já possui retornos registrados.'
                );
            }

            $statusAnterior =
                $saida->status instanceof \BackedEnum
                    ? $saida->status->value
                    : $saida->status;

            $entregas =
                $saida->entregas()->get();

            $pendencias =
                $saida->pendencias()->get();

            /*
             * Cancelamos primeiro para que esta
             * carrada deixe de contar no cálculo
             * de quantidade programada.
             */
            $saida->update([
                'status' =>
                    StatusSaida::CANCELADA,
            ]);

            foreach ($entregas as $entrega) {
                $this->sincronizarStatusProgramacao(
                    $entrega
                );
            }

            foreach (
                $pendencias as $pendencia
            ) {
                if (
                    $pendencia->status ===
                    StatusPendencia::PROGRAMADA
                ) {
                    $pendencia->update([
                        'status' =>
                            StatusPendencia::ABERTA,
                    ]);
                }
            }

            $this->registrarEvento(
                $saida,
                'SAIDA_CANCELADA',
                "Carrada #{$saida->id} cancelada.",
                [
                    'status_anterior' =>
                        $statusAnterior,

                    'total_entregas' =>
                        $entregas->count(),

                    'total_pendencias' =>
                        $pendencias->count(),
                ]
            );
        });
    }

    /**
     * Cria um snapshot dos itens programados
     * naquele momento.
     *
     * Isso é importante para o histórico não
     * depender de mudanças futuras nos produtos.
     */
    private function snapshotItensProgramados(
        SaidaEntrega $saidaEntrega
    ): array {
        $saidaEntrega->load(
            'itensProgramados.entregaItem'
        );

        return $saidaEntrega
            ->itensProgramados
            ->map(function ($programacao) {
                $item =
                    $programacao->entregaItem;

                return [
                    'entrega_item_id' =>
                        $programacao
                            ->entrega_item_id,

                    'codigo_produto' =>
                        $item?->codigo_produto,

                    'descricao_produto' =>
                        $item?->descricao_produto
                        ?? 'Produto sem descrição',

                    'unidade' =>
                        $item?->unidade,

                    'quantidade_programada' =>
                        (float)
                        $programacao
                            ->quantidade_programada,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Registra um evento real da carrada.
     *
     * Se o service estiver sendo executado por
     * uma requisição autenticada, auth()->id()
     * identifica automaticamente o usuário.
     *
     * No método criar(), o usuário é informado
     * explicitamente.
     */
    private function registrarEvento(
        Saida $saida,
        string $tipoEvento,
        string $descricao,
        array $dados = [],
        ?User $usuario = null
    ): void {
        SaidaEvento::create([
            'saida_id' =>
                $saida->id,

            'usuario_id' =>
                $usuario?->id
                ?? auth()->id(),

            'tipo_evento' =>
                $tipoEvento,

            'descricao' =>
                $descricao,

            'dados' =>
                ! empty($dados)
                    ? $dados
                    : null,

            'created_at' =>
                now(),
        ]);
    }
}
