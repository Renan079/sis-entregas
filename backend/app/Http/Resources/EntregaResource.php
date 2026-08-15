<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntregaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'numero_pedido' => $this->numero_pedido,

            'cliente' => $this->whenLoaded('cliente', function () {
                return [
                    'id' => $this->cliente?->id,
                    'nome' => $this->cliente?->nome,
                    'apelido' => $this->cliente?->apelido,
                ];
            }),

            'regiao' => $this->whenLoaded('regiao', function () {
                return [
                    'id' => $this->regiao->id,
                    'nome' => $this->regiao->nome,
                    'cidade' => $this->regiao->cidade,
                ];
            }),

            'cliente_nome' => $this->cliente_nome,
            'cliente_apelido' => $this->cliente_apelido,
            'cliente_telefone' => $this->cliente_telefone,

            'endereco' => $this->endereco,
            'referencia' => $this->referencia,

            'prioridade' => $this->prioridade,

            'status' => $this->status instanceof \BackedEnum
                ? $this->status->value
                : $this->status,

            'observacao' => $this->observacao,

            'itens' => $this->whenLoaded('itens', function () {
                return $this->itens->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'codigo_produto' => $item->codigo_produto,
                        'descricao_produto' => $item->descricao_produto,
                        'unidade' => $item->unidade,
                        'quantidade_solicitada' =>
                            $item->quantidade_solicitada,
                        'observacao' => $item->observacao,
                    ];
                });
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}