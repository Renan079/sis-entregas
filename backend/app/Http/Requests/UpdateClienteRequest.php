<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');

        $clienteId = $cliente instanceof Cliente
            ? $cliente->id
            : $cliente;

        return [
            'codigo_externo' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('clientes', 'codigo_externo')
                    ->ignore($clienteId),
            ],

            'nome' => [
                'sometimes',
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