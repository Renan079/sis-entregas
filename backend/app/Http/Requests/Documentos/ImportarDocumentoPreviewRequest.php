<?php

namespace App\Http\Requests\Documentos;

use Illuminate\Foundation\Http\FormRequest;

class ImportarDocumentoPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'arquivo' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:12288',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' =>
                'Selecione uma foto da nota.',

            'arquivo.mimes' =>
                'Use uma imagem JPG, JPEG, PNG ou WEBP.',

            'arquivo.max' =>
                'A imagem pode ter no máximo 12 MB.',
        ];
    }
}
