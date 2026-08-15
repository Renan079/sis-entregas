<?php

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create([
        'perfil' => 'ADMIN',
        'ativo' => true,
    ]);

    Sanctum::actingAs($user);
});

it('lista clientes', function () {
    Cliente::create([
        'nome' => 'Roberto',
        'telefone' => '88999999999',
        'ativo' => true,
    ]);

    $response = $this->getJson('/api/clientes');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.nome', 'Roberto');
});

it('cria um cliente', function () {
    $response = $this->postJson('/api/clientes', [
        'codigo_externo' => '123',
        'nome' => 'Roberto',
        'apelido' => 'Beto',
        'telefone' => '88999999999',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Roberto');

    $this->assertDatabaseHas('clientes', [
        'codigo_externo' => '123',
        'nome' => 'Roberto',
    ]);
});

it('busca um cliente', function () {
    $cliente = Cliente::create([
        'nome' => 'Maria',
        'ativo' => true,
    ]);

    $response = $this->getJson(
        "/api/clientes/{$cliente->id}"
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.nome', 'Maria');
});

it('atualiza um cliente', function () {
    $cliente = Cliente::create([
        'nome' => 'Roberto',
        'ativo' => true,
    ]);

    $response = $this->patchJson(
        "/api/clientes/{$cliente->id}",
        [
            'nome' => 'Roberto Silva',
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.nome',
            'Roberto Silva'
        );

    $this->assertDatabaseHas('clientes', [
        'id' => $cliente->id,
        'nome' => 'Roberto Silva',
    ]);
});

it('desativa um cliente', function () {
    $cliente = Cliente::create([
        'nome' => 'Roberto',
        'ativo' => true,
    ]);

    $response = $this->deleteJson(
        "/api/clientes/{$cliente->id}"
    );

    $response->assertNoContent();

    $this->assertDatabaseHas('clientes', [
        'id' => $cliente->id,
        'ativo' => false,
    ]);
});

it('pesquisa cliente pelo nome', function () {
    Cliente::create([
        'nome' => 'Roberto Silva',
        'ativo' => true,
    ]);

    Cliente::create([
        'nome' => 'Maria Santos',
        'ativo' => true,
    ]);

    $response = $this->getJson(
        '/api/clientes?busca=Roberto'
    );

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.nome',
            'Roberto Silva'
        );
});

it('nao permite codigo externo duplicado', function () {
    Cliente::create([
        'codigo_externo' => '123',
        'nome' => 'Cliente 1',
        'ativo' => true,
    ]);

    $response = $this->postJson('/api/clientes', [
        'codigo_externo' => '123',
        'nome' => 'Cliente 2',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'codigo_externo',
        ]);
});