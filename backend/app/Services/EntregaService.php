<?php

namespace App\Services;

use App\Enums\ResultadoEntrega;
use App\Enums\StatusEntrega;
use App\Enums\StatusSaida;
use App\Models\Devolucao;
use App\Models\Entrega;
use App\Models\EntregaEvento;
use App\Models\Pendencia;
use App\Models\Saida;
use App\Models\SaidaEntrega;
use App\Models\SaidaEvento;
use App\Models\TentativaEntrega;
use App\Models\TentativaItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntregaService
{
    public function __construct(
        private PendenciaService $pendenciaService,
        private EntregaStatusService
            $entregaStatusService
    ) {
    }

    public function registrarResultado(
        Entrega $entrega,
        Saida $saida,
        array $itens,
        User $usuario,
        ?string $motivoNaoEntrega = null,
        ?string $observacao = null
    ): TentativaEntrega {
        return DB::transaction(function () use (
            $entrega,
            $saida,
            $itens,
            $usuario,
            $motivoNaoEntrega,
            $observacao
        ) {
            /*
             * O retorno só pode ser registrado
             * enquanto a carrada estiver em andamento.
             */
            if (
                $saida->status !==
                StatusSaida::EM_ANDAMENTO
            ) {
                throw new DomainException(
                    'Só é possível registrar o resultado de uma saída em andamento.'
                );
            }

            /*
             * Confirma que esta entrega realmente
             * pertence a esta carrada.
             */
            $saidaEntrega = SaidaEntrega::query()
                ->where(
                    'saida_id',
                    $saida->id
                )
                ->where(
                    'entrega_id',
                    $entrega->id
                )
                ->with([
                    'itensProgramados.entregaItem',
                ])
                ->first();

            if (! $saidaEntrega) {
                throw new DomainException(
                    'Esta entrega não pertence à carrada informada.'
                );
            }

            /*
             * Impede registrar o retorno duas vezes
             * para a mesma entrega na mesma carrada.
             */
            $resultadoJaRegistrado =
                TentativaEntrega::query()
                    ->where(
                        'entrega_id',
                        $entrega->id
                    )
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->exists();

            if ($resultadoJaRegistrado) {
                throw new DomainException(
                    'O resultado desta entrega já foi registrado nesta carrada.'
                );
            }

            $entrega->loadMissing('itens');

            /*
             * Produtos que realmente foram
             * programados/carregados nesta carrada.
             */
            $itensProgramados =
                $saidaEntrega
                    ->itensProgramados
                    ->keyBy('entrega_item_id');

            if ($itensProgramados->isEmpty()) {
                throw new DomainException(
                    'Esta entrega não possui produtos programados nesta carrada.'
                );
            }

            /*
             * Produtos informados no retorno.
             */
            $itensInformados =
                collect($itens)
                    ->keyBy('entrega_item_id');

            /*
             * Impede informar no retorno um produto
             * que não estava nesta carrada.
             */
            foreach (
                $itensInformados as
                $entregaItemId => $dados
            ) {
                if (
                    ! $itensProgramados->has(
                        (int) $entregaItemId
                    )
                ) {
                    throw new DomainException(
                        'Foi informado um produto que não estava programado nesta carrada.'
                    );
                }
            }

            $quantidadeTotalProgramada = 0.0;
            $quantidadeTotalEntregue = 0.0;
            $quantidadeTotalDevolvida = 0.0;
            $possuiItemNaoEntregueTotalmente = false;

            /*
             * Valida as quantidades contra aquilo
             * que realmente saiu na carrada.
             */
            foreach (
                $itensProgramados as
                $entregaItemId => $programacao
            ) {
                $item =
                    $programacao->entregaItem;

                if (! $item) {
                    throw new DomainException(
                        'Não foi possível localizar um dos produtos programados.'
                    );
                }

                $quantidadeProgramada =
                    (float)
                    $programacao
                        ->quantidade_programada;

                $dados =
                    $itensInformados->get(
                        $entregaItemId
                    );

                /*
                 * Se o item não for informado,
                 * consideramos que entregou zero.
                 */
                $quantidadeEntregue =
                    $dados
                        ? (float) (
                            $dados[
                                'quantidade_entregue'
                            ] ?? 0
                        )
                        : 0;

                if ($quantidadeEntregue < 0) {
                    throw new DomainException(
                        'A quantidade entregue não pode ser negativa.'
                    );
                }

                /*
                 * REGRA PRINCIPAL:
                 *
                 * Não pode entregar mais do que
                 * foi carregado nesta carrada.
                 */
                if (
                    $quantidadeEntregue >
                    $quantidadeProgramada
                ) {
                    $descricao =
                        $item->descricao_produto
                        ?? 'produto';

                    throw ValidationException::withMessages([
                        'itens' => [
                            "Foram programadas {$quantidadeProgramada} unidades de {$descricao}, "
                            . "mas foram informadas {$quantidadeEntregue} como entregues.",
                        ],
                    ]);
                }

                $quantidadeNaoEntregue =
                    max(
                        0,
                        round(
                            $quantidadeProgramada
                            - $quantidadeEntregue,
                            3
                        )
                    );

                if ($quantidadeNaoEntregue > 0) {
                    $destino =
                        $dados[
                            'destino_nao_entregue'
                        ] ?? null;

                    if (
                        ! in_array(
                            $destino,
                            [
                                'REENTREGA',
                                'DEVOLVIDO',
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'itens' => [
                                'Informe o destino de todos os produtos que não foram entregues.',
                            ],
                        ]);
                    }

                    if ($destino === 'DEVOLVIDO') {
                        $motivoDevolucao =
                            trim(
                                (string) (
                                    $dados[
                                        'motivo_devolucao'
                                    ] ?? ''
                                )
                            );

                        if ($motivoDevolucao === '') {
                            throw ValidationException::withMessages([
                                'itens' => [
                                    'Informe o motivo dos produtos marcados como devolvidos.',
                                ],
                            ]);
                        }
                    }
                }

                $quantidadeTotalProgramada +=
                    $quantidadeProgramada;

                $quantidadeTotalEntregue +=
                    $quantidadeEntregue;

                if (
                    $quantidadeEntregue <
                    $quantidadeProgramada
                ) {
                    $possuiItemNaoEntregueTotalmente =
                        true;
                }
            }

            /*
             * Resultado DESTA CARRADA.
             *
             * Não significa necessariamente que
             * o pedido inteiro foi concluído.
             */
            if (
                ! $possuiItemNaoEntregueTotalmente
            ) {
                $resultado =
                    ResultadoEntrega::COMPLETA;
            } elseif (
                $quantidadeTotalEntregue > 0
            ) {
                $resultado =
                    ResultadoEntrega::PARCIAL;
            } else {
                $resultado =
                    ResultadoEntrega::NAO_ENTREGUE;
            }

            /*
             * Registra a tentativa de entrega.
             */
            $tentativa =
                TentativaEntrega::create([
                    'entrega_id' =>
                        $entrega->id,

                    'saida_id' =>
                        $saida->id,

                    'resultado' =>
                        $resultado,

                    'motivo_nao_entrega' =>
                        $motivoNaoEntrega,

                    'observacao' =>
                        $observacao,

                    'registrado_por' =>
                        $usuario->id,

                    'data_hora' =>
                        now(),
                ]);

            /*
             * Registra somente os produtos que
             * estavam nesta carrada.
             */
            foreach (
                $itensProgramados as
                $entregaItemId => $programacao
            ) {
                $dados =
                    $itensInformados->get(
                        $entregaItemId
                    );

                $quantidadeEntregue =
                    $dados
                        ? (float) (
                            $dados[
                                'quantidade_entregue'
                            ] ?? 0
                        )
                        : 0;

                TentativaItem::create([
                    'tentativa_id' =>
                        $tentativa->id,

                    'entrega_item_id' =>
                        (int) $entregaItemId,

                    'quantidade_entregue' =>
                        $quantidadeEntregue,

                    'observacao' =>
                        $dados[
                            'observacao'
                        ] ?? null,
                ]);

                $quantidadeProgramada =
                    (float)
                    $programacao
                        ->quantidade_programada;

                $quantidadeNaoEntregue =
                    max(
                        0,
                        round(
                            $quantidadeProgramada
                            - $quantidadeEntregue,
                            3
                        )
                    );

                $destino =
                    $dados[
                        'destino_nao_entregue'
                    ] ?? null;

                /*
                 * Quando o cliente não quer mais o
                 * produto, registramos DEVOLUÇÃO.
                 *
                 * Essa quantidade não deve virar
                 * pendência e não pode voltar para
                 * uma nova carrada.
                 */
                if (
                    $quantidadeNaoEntregue > 0 &&
                    $destino === 'DEVOLVIDO'
                ) {
                    $motivoDevolucao =
                        trim(
                            (string) (
                                $dados[
                                    'motivo_devolucao'
                                ] ?? ''
                            )
                        );

                    Devolucao::create([
                        'entrega_id' =>
                            $entrega->id,

                        'entrega_item_id' =>
                            (int) $entregaItemId,

                        'saida_id' =>
                            $saida->id,

                        'tentativa_origem_id' =>
                            $tentativa->id,

                        'pendencia_id' =>
                            null,

                        'quantidade' =>
                            $quantidadeNaoEntregue,

                        'motivo' =>
                            $motivoDevolucao,

                        'observacao' =>
                            $dados[
                                'observacao'
                            ] ?? $observacao,

                        'registrado_por' =>
                            $usuario->id,

                        'data_hora' =>
                            $tentativa->data_hora
                            ?? now(),
                    ]);

                    $quantidadeTotalDevolvida +=
                        $quantidadeNaoEntregue;
                }
            }

            /*
             * O destino de cada quantidade não
             * entregue foi escolhido no frontend:
             *
             * REENTREGA -> gera pendência.
             * DEVOLVIDO -> já foi registrado em
             *              devolucoes acima.
             */
            $this->pendenciaService
                ->sincronizar(
                    $entrega,
                    $tentativa,
                    $usuario,
                    $itens
                );

            /*
             * O status logístico considera:
             *
             * entregue + devolvido.
             *
             * Pendência ainda aberta continua
             * sendo saldo a tratar.
             */
            $this->entregaStatusService
                ->sincronizar(
                    $entrega
                );

            /*
             * -------------------------------------------------
             * HISTÓRICO REAL DA CARRADA
             * -------------------------------------------------
             *
             * Registramos um snapshot do resultado desta
             * entrega exatamente como ele aconteceu nesta
             * carrada.
             */
            $tentativa->load('itens');

            $itensTentativa =
                $tentativa
                    ->itens
                    ->keyBy('entrega_item_id');

            $itensEvento =
                $itensProgramados
                    ->map(
                        function (
                            $programacao
                        ) use (
                            $itensTentativa,
                            $tentativa
                        ) {
                            $produto =
                                $programacao
                                    ->entregaItem;

                            $tentativaItem =
                                $itensTentativa
                                    ->get(
                                        $programacao
                                            ->entrega_item_id
                                    );

                            $programado =
                                (float)
                                $programacao
                                    ->quantidade_programada;

                            $entregue =
                                (float) (
                                    $tentativaItem
                                        ?->quantidade_entregue
                                    ?? 0
                                );

                            $devolvido =
                                (float)
                                Devolucao::query()
                                    ->where(
                                        'tentativa_origem_id',
                                        $tentativa->id
                                    )
                                    ->where(
                                        'entrega_item_id',
                                        $programacao
                                            ->entrega_item_id
                                    )
                                    ->sum('quantidade');

                            $pendenciaGerada =
                                (float)
                                Pendencia::query()
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
                                        'quantidade_original'
                                    );

                            return [
                                'entrega_item_id' =>
                                    $programacao
                                        ->entrega_item_id,

                                'codigo_produto' =>
                                    $produto
                                        ?->codigo_produto,

                                'descricao_produto' =>
                                    $produto?->descricao_produto
                                    ?? 'Produto sem descrição',

                                'unidade' =>
                                    $produto?->unidade,

                                'quantidade_programada' =>
                                    $programado,

                                'quantidade_entregue' =>
                                    $entregue,

                                'quantidade_devolvida' =>
                                    $devolvido,

                                'quantidade_reentrega' =>
                                    $pendenciaGerada,

                                'quantidade_nao_entregue' =>
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
                                        ?->observacao,
                            ];
                        }
                    )
                    ->values()
                    ->toArray();

            /*
             * Pendências que nasceram especificamente
             * desta tentativa/carrada.
             */
            $pendenciasGeradas =
                Pendencia::query()
                    ->where(
                        'tentativa_origem_id',
                        $tentativa->id
                    )
                    ->get()
                    ->map(
                        function (
                            Pendencia $pendencia
                        ) {
                            return [
                                'pendencia_id' =>
                                    $pendencia->id,

                                'entrega_item_id' =>
                                    $pendencia
                                        ->entrega_item_id,

                                'quantidade_original' =>
                                    (float)
                                    $pendencia
                                        ->quantidade_original,

                                'quantidade_resolvida' =>
                                    (float)
                                    $pendencia
                                        ->quantidade_resolvida,

                                'status' =>
                                    $pendencia->status
                                        ->value,
                            ];
                        }
                    )
                    ->values()
                    ->toArray();

            $identificadorPedido =
                $entrega->numero_pedido
                    ? "#{$entrega->numero_pedido}"
                    : "#{$entrega->id}";

            $resultadoLabel =
                match ($resultado) {
                    ResultadoEntrega::COMPLETA =>
                        'completa',

                    ResultadoEntrega::PARCIAL =>
                        'parcial',

                    ResultadoEntrega::NAO_ENTREGUE =>
                        'não entregue',
                };

            SaidaEvento::create([
                'saida_id' =>
                    $saida->id,

                'usuario_id' =>
                    $usuario->id,

                'tipo_evento' =>
                    'RESULTADO_ENTREGA_REGISTRADO',

                'descricao' =>
                    "Resultado do pedido {$identificadorPedido} de {$entrega->cliente_nome}: {$resultadoLabel}.",

                'dados' => [
                    'entrega_id' =>
                        $entrega->id,

                    'numero_pedido' =>
                        $entrega->numero_pedido,

                    'cliente_nome' =>
                        $entrega->cliente_nome,

                    'tentativa_id' =>
                        $tentativa->id,

                    'resultado' =>
                        $resultado->value,

                    'motivo_nao_entrega' =>
                        $motivoNaoEntrega,

                    'observacao' =>
                        $observacao,

                    'quantidade_programada' =>
                        $quantidadeTotalProgramada,

                    'quantidade_entregue' =>
                        $quantidadeTotalEntregue,

                    'quantidade_devolvida' =>
                        $quantidadeTotalDevolvida,

                    'quantidade_nao_entregue' =>
                        max(
                            0,
                            round(
                                $quantidadeTotalProgramada
                                - $quantidadeTotalEntregue,
                                3
                            )
                        ),

                    'itens' =>
                        $itensEvento,

                    'pendencias_geradas' =>
                        $pendenciasGeradas,
                ],

                /*
                 * O horário do evento é o mesmo horário
                 * real do registro da tentativa.
                 */
                'created_at' =>
                    $tentativa->data_hora
                    ?? now(),
            ]);

            /*
             * Histórico da própria entrega.
             */
            EntregaEvento::create([
                'entrega_id' =>
                    $entrega->id,

                'usuario_id' =>
                    $usuario->id,

                'tipo_evento' =>
                    'RESULTADO_ENTREGA_REGISTRADO',

                'descricao' =>
                    "Resultado registrado: {$resultado->value}",

                'dados' => [
                    'tentativa_id' =>
                        $tentativa->id,

                    'saida_id' =>
                        $saida->id,

                    'resultado' =>
                        $resultado->value,

                    'quantidade_programada' =>
                        $quantidadeTotalProgramada,

                    'quantidade_entregue' =>
                        $quantidadeTotalEntregue,

                    'quantidade_devolvida' =>
                        $quantidadeTotalDevolvida,

                    'quantidade_nao_entregue' =>
                        max(
                            0,
                            $quantidadeTotalProgramada
                                -
                            $quantidadeTotalEntregue
                        ),
                ],
            ]);

            return $tentativa->load([
                'itens',
                'entrega',
            ]);
        });
    }
}
