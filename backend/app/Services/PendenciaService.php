<?php

namespace App\Services;

use App\Enums\StatusPendencia;
use App\Enums\StatusSaida;
use App\Models\Devolucao;
use App\Models\Entrega;
use App\Models\EntregaEvento;
use App\Models\Pendencia;
use App\Models\PendenciaMovimentacao;
use App\Models\Saida;
use App\Models\SaidaEntrega;
use App\Models\SaidaEvento;
use App\Models\SaidaPendenciaRetorno;
use App\Models\TentativaEntrega;
use App\Models\TentativaItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PendenciaService
{
    public function __construct(
        private EntregaStatusService
            $entregaStatusService
    ) {
    }

    /**
     * Trata somente a falta gerada por uma
     * ENTREGA NORMAL.
     *
     * A partir de agora o destino é informado
     * por produto:
     *
     * REENTREGA -> cria pendência.
     * DEVOLVIDO -> não cria pendência, pois a
     *              devolução é registrada pelo
     *              EntregaService.
     */
    public function sincronizar(
        Entrega $entrega,
        TentativaEntrega $tentativa,
        User $usuario,
        array $itensRetorno = []
    ): void {
        $saidaEntrega =
            SaidaEntrega::query()
                ->where(
                    'saida_id',
                    $tentativa->saida_id
                )
                ->where(
                    'entrega_id',
                    $entrega->id
                )
                ->with([
                    'itensProgramados',
                ])
                ->first();

        if (! $saidaEntrega) {
            throw new DomainException(
                'Não foi encontrada a programação da entrega nesta carrada.'
            );
        }

        $itensProgramados =
            $saidaEntrega
                ->itensProgramados
                ->keyBy('entrega_item_id');

        $itensEntregues =
            TentativaItem::query()
                ->where(
                    'tentativa_id',
                    $tentativa->id
                )
                ->get()
                ->keyBy('entrega_item_id');

        $retornoPorItem =
            collect($itensRetorno)
                ->keyBy('entrega_item_id');

        foreach (
            $itensProgramados as
            $entregaItemId => $programacao
        ) {
            $quantidadeProgramada =
                round(
                    (float)
                    $programacao
                        ->quantidade_programada,
                    3
                );

            $tentativaItem =
                $itensEntregues->get(
                    $entregaItemId
                );

            $quantidadeEntregue =
                round(
                    (float) (
                        $tentativaItem
                            ?->quantidade_entregue
                        ?? 0
                    ),
                    3
                );

            $quantidadeNaoEntregue =
                max(
                    0,
                    round(
                        $quantidadeProgramada
                        - $quantidadeEntregue,
                        3
                    )
                );

            if ($quantidadeNaoEntregue <= 0) {
                continue;
            }

            $dadosRetorno =
                $retornoPorItem->get(
                    $entregaItemId,
                    []
                );

            $destino =
                $dadosRetorno[
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

            /*
             * Produto recusado/devolvido não vira
             * pendência e não aparecerá em nova rota.
             */
            if ($destino === 'DEVOLVIDO') {
                continue;
            }

            $this->criarPendencia(
                $entrega,
                (int) $entregaItemId,
                $tentativa,
                $usuario,
                $quantidadeNaoEntregue
            );
        }
    }

    /**
     * Registra o retorno de uma REENTREGA que
     * entrou na carrada via saida_pendencias.
     */
    public function registrarRetorno(
        Saida $saida,
        Pendencia $pendencia,
        array $dados,
        User $usuario
    ): SaidaPendenciaRetorno {
        return DB::transaction(function () use (
            $saida,
            $pendencia,
            $dados,
            $usuario
        ) {
            if (
                $saida->status !==
                StatusSaida::EM_ANDAMENTO
            ) {
                throw new DomainException(
                    'Só é possível registrar o retorno de uma reentrega enquanto a carrada estiver em andamento.'
                );
            }

            $pertenceACarrada =
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

            if (! $pertenceACarrada) {
                throw new DomainException(
                    'Esta pendência não pertence à carrada informada.'
                );
            }

            $resultadoJaRegistrado =
                SaidaPendenciaRetorno::query()
                    ->where(
                        'saida_id',
                        $saida->id
                    )
                    ->where(
                        'pendencia_id',
                        $pendencia->id
                    )
                    ->exists();

            if ($resultadoJaRegistrado) {
                throw new DomainException(
                    'O retorno desta reentrega já foi registrado nesta carrada.'
                );
            }

            if (
                $pendencia->status !==
                StatusPendencia::PROGRAMADA
            ) {
                throw new DomainException(
                    'Esta pendência não está programada para reentrega nesta carrada.'
                );
            }

            $saldoAnterior =
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

            if ($saldoAnterior <= 0) {
                throw new DomainException(
                    'Esta pendência não possui saldo para retorno.'
                );
            }

            $quantidadeEntregue =
                round(
                    (float) (
                        $dados[
                            'quantidade_entregue'
                        ] ?? 0
                    ),
                    3
                );

            if (
                $quantidadeEntregue < 0 ||
                $quantidadeEntregue >
                    $saldoAnterior
            ) {
                throw ValidationException::withMessages([
                    'quantidade_entregue' => [
                        "A quantidade entregue deve ficar entre 0 e {$saldoAnterior}.",
                    ],
                ]);
            }

            $restante =
                max(
                    0,
                    round(
                        $saldoAnterior
                        - $quantidadeEntregue,
                        3
                    )
                );

            $destinoRestante =
                $restante > 0
                    ? (
                        $dados[
                            'destino_restante'
                        ] ?? null
                    )
                    : null;

            if (
                $restante > 0 &&
                ! in_array(
                    $destinoRestante,
                    [
                        'REENTREGA',
                        'DEVOLVIDO',
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'destino_restante' => [
                        'Informe se o saldo restante será reentregue novamente ou devolvido.',
                    ],
                ]);
            }

            $motivoDevolucao =
                trim(
                    (string) (
                        $dados[
                            'motivo_devolucao'
                        ] ?? ''
                    )
                );

            if (
                $destinoRestante ===
                    'DEVOLVIDO' &&
                $motivoDevolucao === ''
            ) {
                throw ValidationException::withMessages([
                    'motivo_devolucao' => [
                        'Informe o motivo da devolução.',
                    ],
                ]);
            }

            $quantidadeDevolvida =
                $destinoRestante ===
                    'DEVOLVIDO'
                    ? $restante
                    : 0.0;

            $quantidadeReentrega =
                $destinoRestante ===
                    'REENTREGA'
                    ? $restante
                    : 0.0;

            $resultado =
                SaidaPendenciaRetorno::create([
                    'saida_id' =>
                        $saida->id,

                    'pendencia_id' =>
                        $pendencia->id,

                    'quantidade_programada' =>
                        $saldoAnterior,

                    'quantidade_entregue' =>
                        $quantidadeEntregue,

                    'quantidade_devolvida' =>
                        $quantidadeDevolvida,

                    'quantidade_reentrega' =>
                        $quantidadeReentrega,

                    'destino_restante' =>
                        $destinoRestante,

                    'motivo_devolucao' =>
                        $motivoDevolucao !== ''
                            ? $motivoDevolucao
                            : null,

                    'observacao' =>
                        $dados['observacao']
                        ?? null,

                    'registrado_por' =>
                        $usuario->id,

                    'data_hora' =>
                        now(),
                ]);

            /*
             * Quantidade entregue resolve parte
             * da pendência.
             */
            if ($quantidadeEntregue > 0) {
                PendenciaMovimentacao::create([
                    'pendencia_id' =>
                        $pendencia->id,

                    'tentativa_id' =>
                        null,

                    'tipo' =>
                        $restante <= 0
                            ? 'RESOLUCAO'
                            : 'ENTREGA_PARCIAL',

                    'quantidade' =>
                        $quantidadeEntregue,

                    'observacao' =>
                        'Quantidade entregue em reentrega.',

                    'usuario_id' =>
                        $usuario->id,
                ]);
            }

            /*
             * Devolução também encerra aquela
             * quantidade logisticamente, mas não
             * conta como produto entregue ao cliente.
             */
            if ($quantidadeDevolvida > 0) {
                Devolucao::create([
                    'entrega_id' =>
                        $pendencia->entrega_id,

                    'entrega_item_id' =>
                        $pendencia
                            ->entrega_item_id,

                    'saida_id' =>
                        $saida->id,

                    'tentativa_origem_id' =>
                        null,

                    'pendencia_id' =>
                        $pendencia->id,

                    'quantidade' =>
                        $quantidadeDevolvida,

                    'motivo' =>
                        $motivoDevolucao,

                    'observacao' =>
                        $dados['observacao']
                        ?? null,

                    'registrado_por' =>
                        $usuario->id,

                    'data_hora' =>
                        now(),
                ]);

                PendenciaMovimentacao::create([
                    'pendencia_id' =>
                        $pendencia->id,

                    'tentativa_id' =>
                        null,

                    /*
                     * Mantemos AJUSTE porque o enum
                     * atual da tabela não possui
                     * DEVOLUCAO.
                     */
                    'tipo' =>
                        'AJUSTE',

                    'quantidade' =>
                        $quantidadeDevolvida,

                    'observacao' =>
                        "Pendência encerrada por devolução ({$motivoDevolucao}).",

                    'usuario_id' =>
                        $usuario->id,
                ]);
            }

            $novoTotalResolvido =
                min(
                    (float)
                    $pendencia
                        ->quantidade_original,

                    round(
                        (float)
                        $pendencia
                            ->quantidade_resolvida
                        +
                        $quantidadeEntregue
                        +
                        $quantidadeDevolvida,
                        3
                    )
                );

            $novoSaldo =
                max(
                    0,
                    round(
                        (float)
                        $pendencia
                            ->quantidade_original
                        -
                        $novoTotalResolvido,
                        3
                    )
                );

            if ($novoSaldo <= 0) {
                $pendencia->update([
                    'quantidade_resolvida' =>
                        $novoTotalResolvido,

                    'status' =>
                        StatusPendencia::RESOLVIDA,

                    'data_resolucao' =>
                        now(),
                ]);
            } else {
                /*
                 * Ainda precisa voltar para outra
                 * carrada.
                 */
                $pendencia->update([
                    'quantidade_resolvida' =>
                        $novoTotalResolvido,

                    'status' =>
                        StatusPendencia::ABERTA,

                    'data_resolucao' =>
                        null,
                ]);
            }

            $entrega =
                $pendencia->entrega()
                    ->first();

            if ($entrega) {
                $this->entregaStatusService
                    ->sincronizar(
                        $entrega
                    );
            }

            $identificadorPedido =
                $entrega?->numero_pedido
                    ? "#{$entrega->numero_pedido}"
                    : "#{$pendencia->entrega_id}";

            SaidaEvento::create([
                'saida_id' =>
                    $saida->id,

                'usuario_id' =>
                    $usuario->id,

                'tipo_evento' =>
                    'RESULTADO_REENTREGA_REGISTRADO',

                'descricao' =>
                    "Retorno da reentrega do pedido {$identificadorPedido} registrado.",

                'dados' => [
                    'pendencia_id' =>
                        $pendencia->id,

                    'entrega_id' =>
                        $pendencia->entrega_id,

                    'entrega_item_id' =>
                        $pendencia
                            ->entrega_item_id,

                    'quantidade_programada' =>
                        $saldoAnterior,

                    'quantidade_entregue' =>
                        $quantidadeEntregue,

                    'quantidade_devolvida' =>
                        $quantidadeDevolvida,

                    'quantidade_reentrega' =>
                        $quantidadeReentrega,

                    'destino_restante' =>
                        $destinoRestante,

                    'motivo_devolucao' =>
                        $motivoDevolucao !== ''
                            ? $motivoDevolucao
                            : null,
                ],

                'created_at' =>
                    $resultado->data_hora
                    ?? now(),
            ]);

            if ($entrega) {
                EntregaEvento::create([
                    'entrega_id' =>
                        $entrega->id,

                    'usuario_id' =>
                        $usuario->id,

                    'tipo_evento' =>
                        'RESULTADO_REENTREGA_REGISTRADO',

                    'descricao' =>
                        'Retorno de reentrega registrado.',

                    'dados' => [
                        'saida_id' =>
                            $saida->id,

                        'pendencia_id' =>
                            $pendencia->id,

                        'quantidade_programada' =>
                            $saldoAnterior,

                        'quantidade_entregue' =>
                            $quantidadeEntregue,

                        'quantidade_devolvida' =>
                            $quantidadeDevolvida,

                        'quantidade_reentrega' =>
                            $quantidadeReentrega,
                    ],
                ]);
            }

            return $resultado->refresh();
        });
    }

    /**
     * Cria uma nova pendência causada por uma
     * tentativa de entrega normal.
     */
    private function criarPendencia(
        Entrega $entrega,
        int $entregaItemId,
        TentativaEntrega $tentativa,
        User $usuario,
        float $quantidade
    ): Pendencia {
        $pendencia = Pendencia::create([
            'entrega_id' =>
                $entrega->id,

            'entrega_item_id' =>
                $entregaItemId,

            'tentativa_origem_id' =>
                $tentativa->id,

            'quantidade_original' =>
                $quantidade,

            'quantidade_resolvida' =>
                0,

            'status' =>
                StatusPendencia::ABERTA,

            'motivo' =>
                $tentativa
                    ->motivo_nao_entrega,

            'observacao' =>
                $tentativa->observacao,

            'data_abertura' =>
                now(),
        ]);

        PendenciaMovimentacao::create([
            'pendencia_id' =>
                $pendencia->id,

            'tentativa_id' =>
                $tentativa->id,

            'tipo' =>
                'CRIACAO',

            'quantidade' =>
                $quantidade,

            'usuario_id' =>
                $usuario->id,

            'observacao' =>
                'Pendência gerada pelo retorno da carrada.',
        ]);

        return $pendencia;
    }
}
