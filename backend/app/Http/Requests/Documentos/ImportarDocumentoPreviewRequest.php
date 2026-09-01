<?php

namespace App\Http\Requests\Documentos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportarDocumentoPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * V4 usa arquivos[] para permitir várias fotos.
             * "arquivo" continua aceito por compatibilidade com
             * versões antigas do frontend.
             */
            'arquivos' => [
                'nullable',
                'array',
                'min:1',
                'max:12',
                'required_without:arquivo',
            ],

            'arquivos.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:12288',
            ],

            'arquivo' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:12288',
                'required_without:arquivos',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ) {
            $arquivos =
                $this->file('arquivos');

            if (! is_array($arquivos)) {
                $legado =
                    $this->file('arquivo');

                $arquivos =
                    $legado
                        ? [$legado]
                        : [];
            }

            if ($arquivos === []) {
                return;
            }

            $pdfs = collect($arquivos)
                ->filter(
                    fn ($arquivo) =>
                        strtolower(
                            $arquivo
                                ->getClientOriginalExtension()
                        ) === 'pdf'
                );

            if (
                $pdfs->isNotEmpty() &&
                count($arquivos) > 1
            ) {
                $validator->errors()->add(
                    'arquivos',
                    'Envie um PDF sozinho ou várias fotos. Não misture PDF e imagens na mesma importação.'
                );
            }

            $totalBytes = collect($arquivos)
                ->sum(
                    fn ($arquivo) =>
                        (int) $arquivo->getSize()
                );

            if (
                $totalBytes >
                40 * 1024 * 1024
            ) {
                $validator->errors()->add(
                    'arquivos',
                    'O conjunto de arquivos pode ter no máximo 40 MB.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'arquivos.required_without' =>
                'Selecione pelo menos uma foto ou um PDF.',

            'arquivos.max' =>
                'Uma nota pode ter no máximo 12 fotos.',

            'arquivos.*.mimes' =>
                'Use imagens JPG, JPEG, PNG, WEBP ou um PDF.',

            'arquivos.*.max' =>
                'Cada arquivo pode ter no máximo 12 MB.',

            'arquivo.required_without' =>
                'Selecione uma foto ou um PDF.',

            'arquivo.mimes' =>
                'Use uma imagem JPG, JPEG, PNG, WEBP ou PDF.',

            'arquivo.max' =>
                'O arquivo pode ter no máximo 12 MB.',
        ];
    }
}
