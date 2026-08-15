<?php

namespace App\Http\Requests\Saidas;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarResultadoPendenciaRequest
    extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantidade_entregue' => [
                'required',
                'numeric',
                'min:0',
            ],

            'destino_restante' => [
                'nullable',
                'string',
                'in:REENTREGA,DEVOLVIDO',
            ],

            'motivo_devolucao' => [
                'nullable',
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
}
