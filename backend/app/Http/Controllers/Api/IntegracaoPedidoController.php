<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PedidoExternoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class IntegracaoPedidoController extends Controller
{
    public function __construct(
        private readonly PedidoExternoService $pedidoExternoService,
    ) {}

    public function show(Request $request, string $numero): JsonResponse
    {
        $numeroLimpo = trim($numero);

        if ($numeroLimpo === '') {
            return response()->json([
                'message' => 'Informe o número do pedido.',
            ], 422);
        }

        try {
            $dados = $this->pedidoExternoService->buscarPorNumero($numeroLimpo);

            if (! $dados) {
                return response()->json([
                    'message' => "Pedido #{$numeroLimpo} não encontrado no sistema comercial.",
                ], 404);
            }

            return response()->json($dados);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Erro ao consultar o sistema comercial (Firebird): ' . $e->getMessage(),
            ], 500);
        }
    }
}