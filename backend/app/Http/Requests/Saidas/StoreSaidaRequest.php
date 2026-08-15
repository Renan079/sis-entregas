<?php

namespace App\Http\Requests\Saidas;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_saida' => [
                'required',
                'date',
            ],

            'regiao_principal_id' => [
                'nullable',
                'integer',
                'exists:regiaos,id',
            ],

            'veiculo_id' => [
                'nullable',
                'integer',
                'exists:veiculos,id',
            ],

            /*
             * Rota ordenada da carrada.
             *
             * Exemplo:
             * Gameleira
             * Serra de São João
             * Malhada Redonda
             * Banco de Areia
             */
            'rota' => [
                'nullable',
                'array',
                'max:30',
            ],

            'rota.*.regiao_id' => [
                'nullable',
                'integer',
                'exists:regiaos,id',
            ],

            'rota.*.nome' => [
                'required_with:rota',
                'string',
                'max:150',
            ],

            'observacao' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
