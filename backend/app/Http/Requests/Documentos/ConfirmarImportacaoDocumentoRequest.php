<?php

namespace App\Http\Requests\Documentos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarImportacaoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_pedido' => [
                'required',
                'string',
                'max:80',
            ],

            'cliente_id' => [
                'nullable',
                'integer',
                'exists:clientes,id',
            ],

            'cliente' => [
                'required',
                'array',
            ],

            'cliente.codigo_externo' => [
                'nullable',
                'string',
                'max:50',
            ],

            'cliente.nome' => [
                'required',
                'string',
                'max:180',
            ],

            'cliente.apelido' => [
                'nullable',
                'string',
                'max:150',
            ],

            'cliente.telefone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'regiao_id' => [
                'required',
                'integer',
                'exists:regiaos,id',
            ],

            'endereco' => [
                'required',
                'string',
                'max:500',
            ],

            'referencia' => [
                'nullable',
                'string',
                'max:500',
            ],

            'prioridade' => [
                'required',
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
                'max:2000',
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
                'max:500',
            ],
        ];
    }
}
