<?php

namespace App\Http\Controllers;

use App\Http\Requests\Relatorios\RelatorioIndexRequest;
use App\Services\RelatorioService;
use Illuminate\Http\JsonResponse;

class RelatorioController extends Controller
{
    public function __construct(
        private RelatorioService $service
    ) {
    }

    public function index(
        RelatorioIndexRequest $request
    ): JsonResponse {
        return response()->json([
            'data' => $this->service->gerar(
                $request->validated()
            ),
        ]);
    }
}
