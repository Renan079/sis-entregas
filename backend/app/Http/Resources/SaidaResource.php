<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaidaResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'data_saida' =>
                $this->data_saida instanceof
                    \DateTimeInterface
                    ? $this->data_saida
                        ->format('Y-m-d')
                    : (string)
                        $this->data_saida,

            'status' =>
                $this->valorEnum(
                    $this->status
                ),

            'observacao' =>
                $this->observacao,

            /*
             * Rota completa e ordenada da carrada.
             */
            'rota' =>
                $this->normalizarRota(
                    $this->rota
                ),

            'regiao_principal' =>
                $this->regiaoPrincipal
                    ? [
                        'id' =>
                            $this
                                ->regiaoPrincipal
                                ->id,

                        'nome' =>
                            $this
                                ->regiaoPrincipal
                                ->nome,

                        'cidade' =>
                            $this
                                ->regiaoPrincipal
                                ->cidade,
                    ]
                    : null,

            'veiculo' =>
                $this->veiculo
                    ? [
                        'id' =>
                            $this
                                ->veiculo
                                ->id,

                        'nome' =>
                            $this
                                ->veiculo
                                ->nome,

                        'placa' =>
                            $this
                                ->veiculo
                                ->placa,

                        'tipo' =>
                            $this
                                ->veiculo
                                ->tipo,

                        'status' =>
                            $this
                                ->veiculo
                                ->status,
                    ]
                    : null,

            'entregas' =>
                $this->whenLoaded(
                    'saidaEntregas',
                    function () {
                        return $this
                            ->saidaEntregas
                            ->map(
                                function (
                                    $saidaEntrega
                                ) {
                                    $entrega =
                                        $saidaEntrega
                                            ->entrega;

                                    if (! $entrega) {
                                        return null;
                                    }

                                    $resultadoRegistrado =
                                        $entrega
                                            ->relationLoaded(
                                                'tentativas'
                                            )
                                            ? $entrega
                                                ->tentativas
                                                ->contains(
                                                    'saida_id',
                                                    $this->id
                                                )
                                            : false;

                                    return [
                                        'id' =>
                                            $entrega->id,

                                        'numero_pedido' =>
                                            $entrega
                                                ->numero_pedido,

                                        'cliente_nome' =>
                                            $entrega
                                                ->cliente_nome,

                                        'endereco' =>
                                            $entrega
                                                ->endereco,

                                        'status' =>
                                            $this
                                                ->valorEnum(
                                                    $entrega
                                                        ->status
                                                ),

                                        'prioridade' =>
                                            $this
                                                ->valorEnum(
                                                    $entrega
                                                        ->prioridade
                                                ),

                                        'ordem' =>
                                            $saidaEntrega
                                                ->ordem,

                                        'resultado_registrado' =>
                                            $resultadoRegistrado,

                                        'itens_programados' =>
                                            $saidaEntrega
                                                ->itensProgramados
                                                ->map(
                                                    function (
                                                        $programacao
                                                    ) {
                                                        $item =
                                                            $programacao
                                                                ->entregaItem;

                                                        return [
                                                            'entrega_item_id' =>
                                                                $programacao
                                                                    ->entrega_item_id,

                                                            'codigo_produto' =>
                                                                $item
                                                                    ?->codigo_produto,

                                                            'descricao_produto' =>
                                                                $item
                                                                    ?->descricao
                                                                ?? $item
                                                                    ?->descricao_produto
                                                                ?? 'Produto sem descrição',

                                                            'unidade' =>
                                                                $item
                                                                    ?->unidade,

                                                            'quantidade_solicitada' =>
                                                                $item
                                                                    ?->quantidade_solicitada,

                                                            'quantidade_programada' =>
                                                                $programacao
                                                                    ->quantidade_programada,
                                                        ];
                                                    }
                                                )
                                                ->values(),
                                    ];
                                }
                            )
                            ->filter()
                            ->values();
                    }
                ),

            'total_entregas' =>
                $this->relationLoaded(
                    'saidaEntregas'
                )
                    ? $this
                        ->saidaEntregas
                        ->count()
                    : $this
                        ->saidaEntregas()
                        ->count(),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
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
                            $nome,
                    ];
                }
            )
            ->filter()
            ->values()
            ->toArray();
    }
}
