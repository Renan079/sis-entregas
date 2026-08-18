<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class OcrTesseractService
{
    public function extrairTexto(
        string $caminhoAbsoluto
    ): string {
        $this->garantirTesseractInstalado();

        $idioma =
            $this->possuiIdioma('por')
                ? 'por'
                : 'eng';

        $arquivosParaLer = [
            $caminhoAbsoluto,
        ];

        $temporario =
            $this->preprocessarImagem(
                $caminhoAbsoluto
            );

        if ($temporario) {
            $arquivosParaLer[] =
                $temporario;
        }

        $resultados = [];

        try {
            foreach (
                $arquivosParaLer as $arquivo
            ) {
                /*
                 * 6  = bloco uniforme de texto
                 * 4  = colunas/blocos
                 * 11 = texto esparso
                 *
                 * A nota fotografada pode variar bastante,
                 * então testamos mais de uma segmentação.
                 */
                foreach ([6, 4, 11] as $psm) {
                    $resultado =
                        Process::timeout(60)->run(
                            sprintf(
                                'tesseract %s stdout -l %s --psm %d --dpi 300 -c preserve_interword_spaces=1',
                                escapeshellarg(
                                    $arquivo
                                ),
                                escapeshellarg(
                                    $idioma
                                ),
                                $psm
                            )
                        );

                    if (
                        ! $resultado->successful()
                    ) {
                        continue;
                    }

                    $texto =
                        trim(
                            $resultado->output()
                        );

                    if ($texto === '') {
                        continue;
                    }

                    $resultados[] = [
                        'texto' => $texto,
                        'pontuacao' =>
                            $this->pontuar(
                                $texto
                            ),
                    ];
                }
            }
        } finally {
            if (
                $temporario &&
                is_file($temporario)
            ) {
                @unlink($temporario);
            }
        }

        if ($resultados === []) {
            throw new RuntimeException(
                'O Tesseract não conseguiu ler a imagem.'
            );
        }

        usort(
            $resultados,
            fn (array $a, array $b) =>
                $b['pontuacao']
                <=>
                $a['pontuacao']
        );

        return $resultados[0]['texto'];
    }

    private function preprocessarImagem(
        string $origem
    ): ?string {
        $comando =
            $this->comandoImageMagick();

        if (! $comando) {
            /*
             * ImageMagick é opcional.
             * Sem ele, continuamos usando a imagem original.
             */
            return null;
        }

        $destino =
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'ocr_'
            . Str::uuid()
            . '.png';

        $resultado =
            Process::timeout(45)->run(
                sprintf(
                    '%s %s -auto-orient -resize 220%% -colorspace Gray -contrast-stretch 1%%x1%% -sharpen 0x1 %s',
                    $comando,
                    escapeshellarg(
                        $origem
                    ),
                    escapeshellarg(
                        $destino
                    )
                )
            );

        if (
            $resultado->failed() ||
            ! is_file($destino)
        ) {
            @unlink($destino);

            return null;
        }

        return $destino;
    }

    private function comandoImageMagick(): ?string
    {
        /*
         * Ubuntu mais novo costuma usar "magick".
         * Instalações antigas podem expor "convert".
         */
        foreach (
            ['magick', 'convert']
            as $comando
        ) {
            $resultado =
                Process::timeout(5)->run(
                    sprintf(
                        'command -v %s',
                        $comando
                    )
                );

            if (
                $resultado->successful() &&
                trim(
                    $resultado->output()
                ) !== ''
            ) {
                return $comando;
            }
        }

        return null;
    }

    private function garantirTesseractInstalado(): void
    {
        $resultado =
            Process::timeout(10)->run(
                'tesseract --version'
            );

        if ($resultado->failed()) {
            throw new RuntimeException(
                'Tesseract OCR não está instalado no servidor.'
            );
        }
    }

    private function possuiIdioma(
        string $idioma
    ): bool {
        $resultado =
            Process::timeout(10)->run(
                'tesseract --list-langs'
            );

        if ($resultado->failed()) {
            return false;
        }

        return collect(
            preg_split(
                '/\R/',
                $resultado->output()
            ) ?: []
        )
            ->map(
                fn ($linha) =>
                    trim($linha)
            )
            ->contains($idioma);
    }

    private function pontuar(
        string $texto
    ): int {
        $pontuacao =
            min(
                strlen($texto),
                5000
            );

        $normalizado =
            mb_strtolower(
                Str::ascii($texto)
            );

        foreach ([
            'pedido' => 1200,
            'cliente' => 1400,
            'quantidade' => 700,
            'descricao' => 700,
            'data/hr' => 600,
            'pagamento' => 300,
        ] as $marcador => $pontos) {
            if (
                str_contains(
                    $normalizado,
                    $marcador
                )
            ) {
                $pontuacao += $pontos;
            }
        }

        /*
         * Premia bastante a leitura de uma linha de produto.
         */
        if (
            preg_match(
                '/(?:^|\s)[0-9O]{3,4}\s+[0-9O]{6,}\s+.+/mu',
                $texto
            )
        ) {
            $pontuacao += 1800;
        }

        /*
         * Premia a leitura da linha de quantidade.
         * Esta é uma das partes que mais falha em fotos ruins.
         */
        if (
            preg_match(
                '/[0-9]+(?:[.,][0-9]+)?\s*[xX]\s*[0-9]+(?:[.,][0-9]+)?/u',
                $texto
            )
        ) {
            $pontuacao += 2400;
        }

        if (
            preg_match(
                '/[\[\(]\s*[0-9]{1,10}\s*[\]\)]?\s*[-:]/u',
                $texto
            )
        ) {
            $pontuacao += 1000;
        }

        return $pontuacao;
    }
}
