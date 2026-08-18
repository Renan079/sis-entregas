<?php

use App\Http\Controllers\RegiaoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\SaidaController;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\EntregaHistoricoController;
use App\Http\Controllers\SaidaHistoricoController;
use App\Http\Controllers\PendenciaController;
use App\Http\Controllers\ClienteHistoricoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoImportacaoController;

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('regioes', RegiaoController::class)
        ->parameters([
            'regioes' => 'regiao',
        ]);

    Route::apiResource('clientes', ClienteController::class)
    ->parameters([
        'clientes' => 'cliente',
    ]);

    Route::apiResource('entregas', EntregaController::class)
        ->parameters([
            'entregas' => 'entrega',
        ]);


    Route::get('/pendencias', [
        PendenciaController::class,
        'index',
    ]);

    Route::post(
        '/pendencias/{pendencia}/resolver-manualmente',
        [
            PendenciaController::class,
            'resolverManualmente',
        ]
    );

    Route::get('/saidas', [
        SaidaController::class,
        'index',
        ]);

    Route::post('/saidas', [
        SaidaController::class,
        'store',
    ]);

    Route::get('/saidas/{saida}', [
        SaidaController::class,
        'show',
    ]);

    Route::get('/saidas/{saida}/entregas-disponiveis', [
        SaidaController::class,
        'entregasDisponiveis',
    ]);

    Route::get('/saidas/{saida}/pendencias-disponiveis', [
        SaidaController::class,
        'pendenciasDisponiveis',
    ]);

    Route::get('/saidas/{saida}/pendencias', [
        SaidaController::class,
        'pendenciasDaCarrada',
    ]);

    Route::post('/saidas/{saida}/pendencias/{pendencia}', [
        SaidaController::class,
        'adicionarPendencia',
    ]);

    Route::delete('/saidas/{saida}/pendencias/{pendencia}', [
        SaidaController::class,
        'removerPendencia',
    ]);

    Route::post(
        '/saidas/{saida}/pendencias/{pendencia}/resultado',
        [
            SaidaController::class,
            'registrarResultadoPendencia',
        ]
    );

    Route::post('/saidas/{saida}/entregas', [
        SaidaController::class,
        'adicionarEntrega',
    ]);

    Route::delete(
        '/saidas/{saida}/entregas/{entrega}',
        [
            SaidaController::class,
            'removerEntrega',
        ]
    );

    Route::post('/saidas/{saida}/iniciar', [
        SaidaController::class,
        'iniciar',
    ]);

    Route::post('/saidas/{saida}/finalizar', [
        SaidaController::class,
        'finalizar',
    ]);

    Route::post('/saidas/{saida}/cancelar', [
        SaidaController::class,
        'cancelar',
    ]);
    Route::apiResource(
        'veiculos',
        VeiculoController::class
    )
        ->parameters([
            'veiculos' => 'veiculo',
    ]);

    Route::get(
        '/planejamento/entregas/{entrega}/itens-disponiveis',
        [
            SaidaController::class,
            'itensDisponiveis',
        ]
    );

    Route::post(
        '/saidas/{saida}/entregas/{entrega}/resultado',
        [
            SaidaController::class,
            'registrarResultadoEntrega',
        ]
    );

    Route::get(
        '/entregas/{entrega}/historico',
        EntregaHistoricoController::class
    );

    Route::get(
        '/saidas/{saida}/historico',
        SaidaHistoricoController::class
    );

    Route::get(
        '/clientes/{cliente}/entregas',
        ClienteHistoricoController::class
    );

    Route::get(
        '/dashboard',
        DashboardController::class
    );

    Route::post(
        '/documentos/importar-preview',
        [DocumentoImportacaoController::class, 'preview']
    );

    Route::post(
        '/documentos/{documentoId}/confirmar',
        [DocumentoImportacaoController::class, 'confirmar']
    );

});