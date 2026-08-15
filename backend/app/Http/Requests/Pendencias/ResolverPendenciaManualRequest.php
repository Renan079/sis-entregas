<?php

namespace App\Http\Requests\Pendencias;

use Illuminate\Foundation\Http\FormRequest;

class ResolverPendenciaManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_devolucao' => [
                'required',
                'string',
                'in:CLIENTE_RECUSOU,CLIENTE_DESISTIU,PRODUTO_INCORRETO,PRODUTO_AVARIADO,QUANTIDADE_EXCEDENTE,OUTRO',
            ],

            'observacao' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_devolucao.required' =>
                'Informe o motivo da resolução manual.',

            'motivo_devolucao.in' =>
                'O motivo informado não é válido.',
        ];
    }
}
