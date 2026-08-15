<?php

namespace App\Http\Requests;

use App\Models\Regiao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegiaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $regiao = $this->route('regiao');

        $regiaoId = $regiao instanceof Regiao
            ? $regiao->id
            : $regiao;

        return [
            'nome' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('regiaos', 'nome')
                    ->ignore($regiaoId),
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