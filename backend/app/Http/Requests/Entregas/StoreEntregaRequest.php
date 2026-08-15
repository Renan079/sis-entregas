<?php

namespace App\Http\Requests\Entregas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_pedido' => [
                'nullable',
                'string',
                'max:100',
            ],

            'documento_importado_id' => [
                'nullable',
                'integer',
                'exists:documento_importados,id',
            ],

            'cliente_id' => [
                'nullable',
                'integer',
                'exists:clientes,id',
            ],

            'regiao_id' => [
                'required',
                'integer',
                'exists:regiaos,id',
            ],

            'cliente_nome' => [
                'required',
                'string',
                'max:180',
            ],

            'cliente_apelido' => [
                'nullable',
                'string',
                'max:150',
            ],

            'cliente_telefone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'endereco' => [
                'required',
                'string',
            ],

            'referencia' => [
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
                'nullable',
                'string',
            ],

            'itens' => [
                'required',
                'array',
                'min:1',
            ],

            'itens.*.codigo_produto' => [
                'nullable',
                'string',
                'max:80',
            ],

            'itens.*.descricao_produto' => [
                'required',
                'string',
                'max:255',
            ],

            'itens.*.unidade' => [
                'nullable',
                'string',
                'max:20',
            ],

            'itens.*.quantidade_solicitada' => [
                'required',
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