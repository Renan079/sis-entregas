<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegiaoRequest extends FormRequest
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
                'max:120',
                'unique:regiaos,nome',
            ],

            'cidade' => [
                'nullable',
                'string',
                'max:120',
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