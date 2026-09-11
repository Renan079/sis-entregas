<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->garantirAdministrador($request);

        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);

        $busca = trim((string) ($filtros['busca'] ?? ''));
        $perPage = (int) ($filtros['per_page'] ?? 10);

        $query = User::query()
            ->select([
                'id',
                'name',
                'email',
                'perfil',
                'ativo',
                'created_at',
                'updated_at',
            ]);

        if ($busca !== '') {
            $query->where(function ($query) use ($busca) {
                $query
                    ->where('name', 'ilike', "%{$busca}%")
                    ->orWhere('email', 'ilike', "%{$busca}%");
            });
        }

        $paginacao = $query
            ->orderByRaw("CASE WHEN perfil = 'ADMIN' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginacao->items(),
            'resumo' => [
                'total' => User::query()->count(),
                'ativos' => User::query()->where('ativo', true)->count(),
                'admins' => User::query()->where('perfil', 'ADMIN')->count(),
                'operadores' => User::query()->where('perfil', 'OPERADOR')->count(),
            ],
            'meta' => [
                'current_page' => $paginacao->currentPage(),
                'last_page' => $paginacao->lastPage(),
                'per_page' => $paginacao->perPage(),
                'total' => $paginacao->total(),
            ],
        ]);
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $administrador = $this->garantirAdministrador($request);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
            'perfil' => ['required', Rule::in(['ADMIN', 'OPERADOR'])],
            'ativo' => ['required', 'boolean'],
        ]);

        if ($administrador->id === $usuario->id) {
            if ($dados['perfil'] !== 'ADMIN') {
                throw ValidationException::withMessages([
                    'perfil' => ['Você não pode remover seu próprio perfil de administrador.'],
                ]);
            }

            if (! $dados['ativo']) {
                throw ValidationException::withMessages([
                    'ativo' => ['Você não pode desativar o próprio usuário.'],
                ]);
            }
        }

        $usuario->update([
            'name' => trim($dados['name']),
            'email' => $dados['email'],
            'perfil' => $dados['perfil'],
            'ativo' => $dados['ativo'],
        ]);

        return response()->json([
            'message' => 'Usuário atualizado com sucesso.',
            'user' => $usuario->fresh(),
        ]);
    }

    public function updatePassword(Request $request, User $usuario): JsonResponse
    {
        $this->garantirAdministrador($request);

        $dados = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $usuario->update([
            'password' => Hash::make($dados['password']),
        ]);

        return response()->json([
            'message' => "Senha de {$usuario->name} redefinida com sucesso.",
        ]);
    }

    private function garantirAdministrador(Request $request): User
    {
        $usuario = $request->user();

        abort_unless(
            $usuario && $usuario->ativo && $usuario->perfil === 'ADMIN',
            403,
            'Apenas administradores podem gerenciar usuários.',
        );

        return $usuario;
    }
}
