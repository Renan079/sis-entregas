<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class OcrTesseractService
{
    private const MAX_PAGINAS_PDF = 12;

    /**
     * Lê uma imagem ou um PDF.
     */
    public function extrairTexto(
        string $caminhoAbsoluto
    ): string {
        $extensao =
            strtolower(
                pathinfo(
                    $caminhoAbsoluto,
                    PATHINFO_EXTENSION
                )
            );

        if ($extensao === 'pdf') {
            return $this->extrairTextoPdf(
                $caminhoAbsoluto
            );
        }

        return $this->extrairTextoImagem(
            $caminhoAbsoluto
        );
    }

    /**
     * Lê várias fotos na ordem recebida e devolve um único texto.
     * Cada foto representa uma parte/página da mesma nota.
     */
    public function extrairTextoDeArquivos(
        array $caminhos
    ): array {
        if ($caminhos === []) {
            throw new RuntimeException(
                'Nenhum arquivo foi enviado para OCR.'
            );
        }

        $paginas = [];

        foreach (
            array_values($caminhos)
            as $indice => $caminho
        ) {
            $texto =
                $this->extrairTexto(
                    $caminho
                );

            $paginas[] = [
                'ordem' => $indice + 1,
                'texto' => trim($texto),
            ];
        }

        return [
            'texto' => trim(
                implode(
                    "\n\n",
                    array_column(
                        $paginas,
                        'texto'
                    )
                )
            ),
            'paginas' => $paginas,
        ];
    }

    private function extrairTextoImagem(
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
                $arquivosParaLer
                as $arquivo
            ) {
                foreach ([6, 4, 11] as $psm) {
                    $resultado =
                        Process::timeout(90)->run(
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
                'O Tesseract não conseguiu ler uma das imagens.'
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

    private function extrairTextoPdf(
        string $pdf
    ): string {
        $this->garantirPopplerInstalado();

        $quantidadePaginas =
            $this->quantidadePaginasPdf(
                $pdf
            );

        if (
            $quantidadePaginas >
            self::MAX_PAGINAS_PDF
        ) {
            throw new RuntimeException(
                sprintf(
                    'O PDF possui %d páginas. O limite atual é %d páginas por nota.',
                    $quantidadePaginas,
                    self::MAX_PAGINAS_PDF
                )
            );
        }

        $diretorio =
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'ocr_pdf_'
            . Str::uuid();

        if (
            ! mkdir(
                $diretorio,
                0700,
                true
            ) &&
            ! is_dir($diretorio)
        ) {
            throw new RuntimeException(
                'Não foi possível preparar o PDF para OCR.'
            );
        }

        $prefixo =
            $diretorio
            . DIRECTORY_SEPARATOR
            . 'pagina';

        try {
            $resultado =
                Process::timeout(120)->run(
                    sprintf(
                        'pdftoppm -png -r 250 -f 1 -l %d %s %s',
                        self::MAX_PAGINAS_PDF,
                        escapeshellarg($pdf),
                        escapeshellarg($prefixo)
                    )
                );

            if ($resultado->failed()) {
                throw new RuntimeException(
                    'Não foi possível converter o PDF em páginas para leitura.'
                );
            }

            $imagens =
                glob(
                    $prefixo . '-*.png'
                ) ?: [];

            natsort($imagens);
            $imagens =
                array_values($imagens);

            if ($imagens === []) {
                throw new RuntimeException(
                    'O PDF não gerou nenhuma página legível.'
                );
            }

            $textos = [];

            foreach ($imagens as $imagem) {
                $textos[] =
                    $this->extrairTextoImagem(
                        $imagem
                    );
            }

            return trim(
                implode(
                    "\n\n",
                    $textos
                )
            );
        } finally {
            foreach (
                glob(
                    $diretorio
                    . DIRECTORY_SEPARATOR
                    . '*'
                ) ?: []
                as $arquivo
            ) {
                @unlink($arquivo);
            }

            @rmdir($diretorio);
        }
    }

    private function quantidadePaginasPdf(
        string $pdf
    ): int {
        $resultado =
            Process::timeout(15)->run(
                sprintf(
                    'pdfinfo %s',
                    escapeshellarg($pdf)
                )
            );

        if (
            $resultado->successful() &&
            preg_match(
                '/^Pages:\s*(\d+)/mi',
                $resultado->output(),
                $match
            )
        ) {
            return max(
                1,
                (int) $match[1]
            );
        }

        return 1;
    }

    private function preprocessarImagem(
        string $origem
    ): ?string {
        $comando =
            $this->comandoImageMagick();

        if (! $comando) {
            return null;
        }

        $destino =
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'ocr_'
            . Str::uuid()
            . '.png';

        $resultado =
            Process::timeout(60)->run(
                sprintf(
                    '%s %s -auto-orient -resize 220%% -colorspace Gray -contrast-stretch 1%%x1%% -sharpen 0x1 %s',
                    $comando,
                    escapeshellarg($origem),
                    escapeshellarg($destino)
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

    private function garantirPopplerInstalado(): void
    {
        foreach (
            ['pdftoppm', 'pdfinfo']
            as $comando
        ) {
            $resultado =
                Process::timeout(10)->run(
                    sprintf(
                        'command -v %s',
                        $comando
                    )
                );

            if ($resultado->failed()) {
                throw new RuntimeException(
                    'Para importar PDF, instale o pacote poppler-utils no servidor.'
                );
            }
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

        if (
            preg_match(
                '/(?:^|\s)[0-9O]{3,4}\s+[0-9O]{6,}\s+.+/mu',
                $texto
            )
        ) {
            $pontuacao += 1800;
        }

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
