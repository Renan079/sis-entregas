<?php

namespace App\Http\Requests;

use App\Models\Veiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVeiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $veiculo = $this->route('veiculo');

        $veiculoId = $veiculo instanceof Veiculo
            ? $veiculo->id
            : $veiculo;

        return [
            'nome' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'placa' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('veiculos', 'placa')
                    ->ignore($veiculoId),
            ],

            'tipo' => [
                'sometimes',
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
                'sometimes',
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