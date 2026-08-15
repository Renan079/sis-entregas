<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVeiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'required',
                'string',
                'max:100',
            ],

            'placa' => [
                'nullable',
                'string',
                'max:20',
                'unique:veiculos,placa',
            ],

            'tipo' => [
                'nullable',
                'string',
                'max:50',
            ],

            'status' => [
                'sometimes',
                Rule::in([
                    'DISPONIVEL',
                    'INDISPONIVEL',
                    'MANUTENCAO',
                ]),
            ],

            'observacao' => [
                'nullable',
                'string',
            ],

            'ativo' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}