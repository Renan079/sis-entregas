<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documentos\ConfirmarImportacaoDocumentoRequest;
use App\Http\Requests\Documentos\ImportarDocumentoPreviewRequest;
use App\Services\DocumentoImportacaoService;
use Illuminate\Http\JsonResponse;

class DocumentoImportacaoController extends Controller
{
    public function __construct(
        private DocumentoImportacaoService $service
    ) {
    }

    public function preview(
        ImportarDocumentoPreviewRequest $request
    ): JsonResponse {
        $arquivos =
            $request->file('arquivos');

        if (! is_array($arquivos)) {
            $arquivoLegado =
                $request->file('arquivo');

            $arquivos =
                $arquivoLegado
                    ? [$arquivoLegado]
                    : [];
        }

        $resultado =
            $this->service->gerarPreview(
                $arquivos,
                $request->user()->id
            );

        return response()->json([
            'data' => $resultado,
        ], 201);
    }

    public function confirmar(
        ConfirmarImportacaoDocumentoRequest $request,
        int $documentoId
    ): JsonResponse {
        $resultado =
            $this->service->confirmar(
                $documentoId,
                $request->validated(),
                $request->user()->id
            );

        return response()->json([
            'data' => $resultado,
        ], 201);
    }
}
