<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if ($user && ! $user->ativo) {
            return response()->json([
                'message' => 'Este usuário está desativado.',
            ], 403);
        }

        return $next($request);
    }
}
