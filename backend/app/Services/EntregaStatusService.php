<?php

namespace App\Services;

use App\Enums\StatusEntrega;
use App\Models\Devolucao;
use App\Models\Entrega;
use App\Models\TentativaItem;
use Illuminate\Support\Facades\DB;

class EntregaStatusService
{
    /**
     * Quantidade que o cliente efetivamente recebeu.
     *
     * Soma:
     * - entregas normais;
     * - entregas realizadas em reentregas.
     */
    public function totalEntregueItem(
        int $entregaItemId
    ): float {
        $entregueNormal =
            (float) TentativaItem::query()
                ->where(
                    'entrega_item_id',
                    $entregaItemId
                )
                ->sum('quantidade_entregue');

        $entregueReentrega =
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

        return round(
            $entregueNormal
            + $entregueReentrega,
            3
        );
    }

    /**
     * Quantidade encerrada como devolução.
     *
     * Produto devolvido não deve voltar para
     * o planejamento de novas carradas.
     */
    public function totalDevolvidoItem(
        int $entregaItemId
    ): float {
        return round(
            (float) Devolucao::query()
                ->where(
                    'entrega_item_id',
                    $entregaItemId
                )
                ->sum('quantidade'),
            3
        );
    }

    /**
     * Sincroniza o status logístico do pedido.
     *
     * Uma quantidade é considerada tratada quando:
     *
     * entregue + devolvido.
     *
     * Pendência/reentrega ainda aberta NÃO encerra
     * a quantidade do pedido.
     */
    public function sincronizar(
        Entrega $entrega
    ): void {
        if (
            $entrega->status ===
            StatusEntrega::CANCELADA
        ) {
            return;
        }

        $entrega->loadMissing('itens');

        $pedidoConcluido = true;
        $houveTratamento = false;

        foreach ($entrega->itens as $item) {
            $solicitado =
                round(
                    (float)
                    $item->quantidade_solicitada,
                    3
                );

            $entregue =
                $this->totalEntregueItem(
                    $item->id
                );

            $devolvido =
                $this->totalDevolvidoItem(
                    $item->id
                );

            $tratado =
                round(
                    $entregue
                    + $devolvido,
                    3
                );

            if ($tratado > 0) {
                $houveTratamento = true;
            }

            if ($tratado < $solicitado) {
                $pedidoConcluido = false;
            }
        }

        if ($pedidoConcluido) {
            $status =
                StatusEntrega::CONCLUIDA;
        } elseif ($houveTratamento) {
            $status =
                StatusEntrega::PARCIAL;
        } else {
            $status =
                StatusEntrega::NAO_ENTREGUE;
        }

        $entrega->update([
            'status' => $status,
        ]);
    }
}
