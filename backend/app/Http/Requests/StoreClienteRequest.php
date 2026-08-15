<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_externo' => [
                'nullable',
                'string',
                'max:50',
                'unique:clientes,codigo_externo',
            ],

            'nome' => [
                'required',
                'string',
                'max:180',
            ],

            'apelido' => [
                'nullable',
                'string',
                'max:150',
            ],

            'telefone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'ativo' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}