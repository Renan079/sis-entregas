<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVeiculoRequest;
use App\Http\Requests\UpdateVeiculoRequest;
use App\Http\Resources\VeiculoResource;
use App\Models\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VeiculoController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $veiculos = Veiculo::query()

            ->when($request->filled('busca'), function ($query) use ($request) {
                $busca = $request->string('busca')->toString();

                $query->where(function ($query) use ($busca) {
                    $query
                        ->where('nome', 'ilike', "%{$busca}%")
                        ->orWhere('placa', 'ilike', "%{$busca}%")
                        ->orWhere('tipo', 'ilike', "%{$busca}%");
                });
            })

            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )

            ->orderBy('nome')
            ->paginate(20);

        return VeiculoResource::collection($veiculos);
    }

    public function store(
        StoreVeiculoRequest $request
    ): VeiculoResource {
        $veiculo = Veiculo::create(
            $request->validated()
        );

        return new VeiculoResource($veiculo);
    }

    public function show(
        Veiculo $veiculo
    ): VeiculoResource {
        return new VeiculoResource($veiculo);
    }

    public function update(
        UpdateVeiculoRequest $request,
        Veiculo $veiculo
    ): VeiculoResource {
        $veiculo->update(
            $request->validated()
        );

        return new VeiculoResource(
            $veiculo->fresh()
        );
    }

    public function destroy(
        Veiculo $veiculo
    ): Response {
        $veiculo->update([
            'ativo' => false,
        ]);

        return response()->noContent();
    }
}