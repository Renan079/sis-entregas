<?php

namespace App\Http\Requests\Relatorios;

use App\Enums\StatusEntrega;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RelatorioIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inicio' => [
                'required',
                'date_format:Y-m-d',
            ],

            'fim' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:inicio',
            ],

            'regiao_id' => [
                'nullable',
                'integer',
                'exists:regiaos,id',
            ],

            'status' => [
                'nullable',
                Rule::in(
                    array_map(
                        fn ($status) => $status->value,
                        StatusEntrega::cases()
                    )
                ),
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ) {
            if (
                ! $this->filled('inicio') ||
                ! $this->filled('fim')
            ) {
                return;
            }

            try {
                $inicio = now()
                    ->createFromFormat(
                        'Y-m-d',
                        $this->string('inicio')
                    )
                    ->startOfDay();

                $fim = now()
                    ->createFromFormat(
                        'Y-m-d',
                        $this->string('fim')
                    )
                    ->endOfDay();
            } catch (\Throwable) {
                return;
            }

            if (
                $inicio->diffInDays($fim) > 366
            ) {
                $validator->errors()->add(
                    'fim',
                    'O período máximo por consulta é de 366 dias.'
                );
            }
        });
    }
}
