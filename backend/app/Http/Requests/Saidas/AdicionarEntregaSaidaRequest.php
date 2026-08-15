<?php

namespace App\Http\Requests\Saidas;

use Illuminate\Foundation\Http\FormRequest;

class AdicionarEntregaSaidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entrega_id' => [
                'required',
                'integer',
                'exists:entregas,id',
            ],

            'ordem' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'itens' => [
                'nullable',
                'array',
                'min:1',
            ],

            'itens.*.entrega_item_id' => [
                'required_with:itens',
                'integer',
                'distinct',
                'exists:entrega_items,id',
            ],

            'itens.*.quantidade_programada' => [
                'required_with:itens',
                'numeric',
                'gt:0',
            ],
        ];
    }
}