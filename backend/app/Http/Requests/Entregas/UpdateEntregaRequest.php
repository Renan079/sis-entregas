<?php

namespace App\Http\Requests\Entregas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_pedido' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'cliente_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:clientes,id',
            ],

            'regiao_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:regiaos,id',
            ],

            'cliente_nome' => [
                'sometimes',
                'required',
                'string',
                'max:180',
            ],

            'cliente_apelido' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'cliente_telefone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'endereco' => [
                'sometimes',
                'required',
                'string',
            ],

            'referencia' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'prioridade' => [
                'sometimes',
                Rule::in([
                    'BAIXA',
                    'NORMAL',
                    'ALTA',
                    'URGENTE',
                ]),
            ],

            'observacao' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'itens' => [
                'sometimes',
                'array',
                'min:1',
            ],

            'itens.*.codigo_produto' => [
                'nullable',
                'string',
                'max:80',
            ],

            'itens.*.descricao_produto' => [
                'required_with:itens',
                'string',
                'max:255',
            ],

            'itens.*.unidade' => [
                'nullable',
                'string',
                'max:20',
            ],

            'itens.*.quantidade_solicitada' => [
                'required_with:itens',
                'numeric',
                'gt:0',
            ],

            'itens.*.observacao' => [
                'nullable',
                'string',
            ],
        ];
    }
}