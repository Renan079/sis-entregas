<?php

namespace App\Http\Controllers;

use App\Models\Entrega;
use App\Services\EntregaHistoricoService;
use Illuminate\Http\JsonResponse;

class EntregaHistoricoController extends Controller
{
    public function __construct(
        private readonly EntregaHistoricoService $service
    ) {
    }

    public function __invoke(
        Entrega $entrega
    ): JsonResponse {
        return response()->json([
            'data' =>
                $this->service->obter(
                    $entrega
                ),
        ]);
    }
}