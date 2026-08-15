<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegiaoRequest;
use App\Http\Requests\UpdateRegiaoRequest;
use App\Http\Resources\RegiaoResource;
use App\Models\Regiao;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RegiaoController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $regioes = Regiao::query()
            ->orderBy('nome')
            ->paginate(20);

        return RegiaoResource::collection($regioes);
    }

    public function store(
        StoreRegiaoRequest $request
    ): RegiaoResource {
        $regiao = Regiao::create(
            $request->validated()
        );

        return new RegiaoResource($regiao);
    }

    public function show(
        Regiao $regiao
    ): RegiaoResource {
        return new RegiaoResource($regiao);
    }

    public function update(
        UpdateRegiaoRequest $request,
        Regiao $regiao
    ): RegiaoResource {
        $regiao->update(
            $request->validated()
        );

        return new RegiaoResource(
            $regiao->fresh()
        );
    }

    public function destroy(
        Regiao $regiao
    ): Response {
        /*
         * Não apagamos fisicamente a região.
         * Apenas desativamos.
         */
        $regiao->update([
            'ativo' => false,
        ]);

        return response()->noContent();
    }
}