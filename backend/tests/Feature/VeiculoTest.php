<?php

use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $usuario = User::factory()->create([
        'perfil' => 'ADMIN',
        'ativo' => true,
    ]);

    Sanctum::actingAs($usuario);
});

it('lista veiculos', function () {
    Veiculo::create([
        'nome' => 'D20',
        'placa' => 'ABC1D23',
        'tipo' => 'CAMINHONETE',
        'status' => 'DISPONIVEL',
        'ativo' => true,
    ]);

    $response = $this->getJson('/api/veiculos');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nome', 'D20');
});

it('cria um veiculo', function () {
    $response = $this->postJson('/api/veiculos', [
        'nome' => 'Caminhão',
        'placa' => 'DEF4G56',
        'tipo' => 'CAMINHAO',
        'status' => 'DISPONIVEL',
        'observacao' => 'Veículo principal',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Caminhão')
        ->assertJsonPath('data.status', 'DISPONIVEL');

    $this->assertDatabaseHas('veiculos', [
        'nome' => 'Caminhão',
        'placa' => 'DEF4G56',
        'status' => 'DISPONIVEL',
    ]);
});

it('atualiza um veiculo', function () {
    $veiculo = Veiculo::create([
        'nome' => 'D20',
        'placa' => 'ABC1D23',
        'tipo' => 'CAMINHONETE',
        'status' => 'DISPONIVEL',
        'ativo' => true,
    ]);

    $response = $this->patchJson(
        "/api/veiculos/{$veiculo->id}",
        [
            'status' => 'MANUTENCAO',
            'observacao' => 'Problema mecânico',
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'MANUTENCAO');

    $this->assertDatabaseHas('veiculos', [
        'id' => $veiculo->id,
        'status' => 'MANUTENCAO',
        'observacao' => 'Problema mecânico',
    ]);
});

it('desativa um veiculo sem apagar o registro', function () {
    $veiculo = Veiculo::create([
        'nome' => 'D20',
        'placa' => 'ABC1D23',
        'tipo' => 'CAMINHONETE',
        'status' => 'DISPONIVEL',
        'ativo' => true,
    ]);

    $response = $this->deleteJson(
        "/api/veiculos/{$veiculo->id}"
    );

    $response->assertNoContent();

    $this->assertDatabaseHas('veiculos', [
        'id' => $veiculo->id,
        'ativo' => false,
    ]);
});

it('nao permite placa duplicada', function () {
    Veiculo::create([
        'nome' => 'D20',
        'placa' => 'ABC1D23',
        'tipo' => 'CAMINHONETE',
        'status' => 'DISPONIVEL',
        'ativo' => true,
    ]);

    $response = $this->postJson('/api/veiculos', [
        'nome' => 'Outro veículo',
        'placa' => 'ABC1D23',
        'tipo' => 'CAMINHAO',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'placa',
        ]);
});