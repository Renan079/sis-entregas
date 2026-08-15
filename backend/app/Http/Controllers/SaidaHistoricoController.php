<?php

namespace App\Http\Controllers;

use App\Models\Saida;
use App\Services\SaidaHistoricoService;
use Illuminate\Http\JsonResponse;

class SaidaHistoricoController extends Controller
{
    public function __construct(
        private readonly SaidaHistoricoService $service
    ) {
    }

    public function __invoke(
        Saida $saida
    ): JsonResponse {
        return response()->json([
            'data' =>
                $this->service->obter(
                    $saida
                ),
        ]);
    }
}