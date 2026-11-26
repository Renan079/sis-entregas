<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegracaoClienteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'codigo_externo' => ['nullable', 'string', 'max:60'],
            'nome' => ['required', 'string', 'max:255'],
            'apelido' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:40'],
        ]);

        $codigoExterno = isset($validado['codigo_externo']) && trim($validado['codigo_externo']) !== ''
            ? trim($validado['codigo_externo'])
            : null;

        if ($codigoExterno !== null) {
            $existente = Cliente::query()
                ->whereRaw('TRIM(codigo_externo) = ?', [$codigoExterno])
                ->first();

            if ($existente) {
                return response()->json([
                    'cliente' => $existente,
                    'criado' => false,
                ], 200);
            }
        }

        $cliente = Cliente::create([
            'codigo_externo' => $codigoExterno,
            'nome' => trim($validado['nome']),
            'apelido' => isset($validado['apelido']) && trim($validado['apelido']) !== ''
                ? trim($validado['apelido'])
                : null,
            'telefone' => isset($validado['telefone']) && trim($validado['telefone']) !== ''
                ? trim($validado['telefone'])
                : null,
            'ativo' => true,
        ]);

        return response()->json([
            'cliente' => $cliente,
            'criado' => true,
        ], 201);
    }
}