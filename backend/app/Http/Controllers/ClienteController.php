<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClienteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clientes = Cliente::query()

            ->when($request->filled('busca'), function ($query) use ($request) {
                $busca = $request->string('busca')->toString();

                $query->where(function ($query) use ($busca) {
                    $query
                        ->where('nome', 'ilike', "%{$busca}%")
                        ->orWhere('apelido', 'ilike', "%{$busca}%")
                        ->orWhere('codigo_externo', 'ilike', "%{$busca}%")
                        ->orWhere('telefone', 'ilike', "%{$busca}%");
                });
            })

            ->orderBy('nome')
            ->paginate(20);

        return ClienteResource::collection($clientes);
    }

    public function store(
        StoreClienteRequest $request
    ): ClienteResource {
        $cliente = Cliente::create(
            $request->validated()
        );

        return new ClienteResource($cliente);
    }

    public function show(
        Cliente $cliente
    ): ClienteResource {
        return new ClienteResource($cliente);
    }

    public function update(
        UpdateClienteRequest $request,
        Cliente $cliente
    ): ClienteResource {
        $cliente->update(
            $request->validated()
        );

        return new ClienteResource(
            $cliente->fresh()
        );
    }

    public function destroy(
        Cliente $cliente
    ): Response {
        $cliente->update([
            'ativo' => false,
        ]);

        return response()->noContent();
    }
}