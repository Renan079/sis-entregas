<?php

namespace App\Http\Requests\Saidas;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarResultadoEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'itens' => [
                'required',
                'array',
                'min:1',
            ],

            'itens.*.entrega_item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:entrega_items,id',
            ],

            'itens.*.quantidade_entregue' => [
                'required',
                'numeric',
                'min:0',
            ],

            'itens.*.destino_nao_entregue' => [
                'nullable',
                'string',
                'in:REENTREGA,DEVOLVIDO',
            ],

            'itens.*.motivo_devolucao' => [
                'nullable',
                'string',
                'in:CLIENTE_RECUSOU,CLIENTE_DESISTIU,PRODUTO_INCORRETO,PRODUTO_AVARIADO,QUANTIDADE_EXCEDENTE,OUTRO',
            ],

            'itens.*.observacao' => [
                'nullable',
                'string',
                'max:500',
            ],

            'motivo_nao_entrega' => [
                'nullable',
                'string',
                'max:255',
            ],

            'observacao' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}