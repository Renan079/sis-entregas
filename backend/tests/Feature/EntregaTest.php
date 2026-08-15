<?php

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Regiao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->usuario = User::factory()->create([
        'perfil' => 'ADMIN',
        'ativo' => true,
    ]);

    Sanctum::actingAs($this->usuario);

    $this->regiao = Regiao::create([
        'nome' => 'Gameleira dos Machado',
        'cidade' => 'Canindé',
        'ativo' => true,
    ]);

    $this->cliente = Cliente::create([
        'codigo_externo' => 'CLI-001',
        'nome' => 'João da Silva',
        'apelido' => 'João',
        'telefone' => '88999999999',
        'ativo' => true,
    ]);
});

it('cria uma entrega com seus itens', function () {
    $response = $this->postJson('/api/entregas', [
        'numero_pedido' => '23249',

        'cliente_id' => $this->cliente->id,
        'regiao_id' => $this->regiao->id,

        // Atualmente o request exige este campo.
        'cliente_nome' => $this->cliente->nome,

        'endereco' => 'Rua Principal, 100',
        'referencia' => 'Em frente ao mercantil',

        'prioridade' => 'NORMAL',

        'itens' => [
            [
                'codigo_produto' => '147',
                'descricao_produto' => 'Cimento Mizu CP2 50KG',
                'unidade' => 'UN',
                'quantidade_solicitada' => 10,
            ],
            [
                'codigo_produto' => '200',
                'descricao_produto' => 'Bloco Cerâmico',
                'unidade' => 'UN',
                'quantidade_solicitada' => 100,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.numero_pedido', '23249')
        ->assertJsonPath('data.cliente_nome', 'João da Silva')
        ->assertJsonPath('data.status', 'PENDENTE')
        ->assertJsonCount(2, 'data.itens');

    $this->assertDatabaseHas('entregas', [
        'numero_pedido' => '23249',
        'cliente_id' => $this->cliente->id,
        'regiao_id' => $this->regiao->id,
        'status' => 'PENDENTE',
    ]);

    $this->assertDatabaseHas('entrega_items', [
        'codigo_produto' => '147',
        'descricao_produto' => 'Cimento Mizu CP2 50KG',
    ]);

    $this->assertDatabaseHas('entrega_eventos', [
        'tipo_evento' => 'ENTREGA_CRIADA',
    ]);
});

it('lista entregas', function () {
    $entrega = Entrega::create([
        'numero_pedido' => '1000',
        'cliente_id' => $this->cliente->id,
        'regiao_id' => $this->regiao->id,

        'cliente_nome' => $this->cliente->nome,
        'cliente_apelido' => $this->cliente->apelido,
        'cliente_telefone' => $this->cliente->telefone,

        'endereco' => 'Rua A',
        'prioridade' => 'NORMAL',
        'status' => 'PENDENTE',

        'criado_por' => $this->usuario->id,
    ]);

    $entrega->itens()->create([
        'descricao_produto' => 'Cimento',
        'quantidade_solicitada' => 10,
    ]);

    $response = $this->getJson('/api/entregas');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.numero_pedido', '1000');
});

it('busca entrega pelo cliente', function () {
    $entrega = Entrega::create([
        'numero_pedido' => '2000',
        'regiao_id' => $this->regiao->id,

        'cliente_nome' => 'Maria Oliveira',

        'endereco' => 'Rua B',
        'prioridade' => 'NORMAL',
        'status' => 'PENDENTE',

        'criado_por' => $this->usuario->id,
    ]);

    $entrega->itens()->create([
        'descricao_produto' => 'Areia',
        'quantidade_solicitada' => 5,
    ]);

    $response = $this->getJson(
        '/api/entregas?busca=Maria'
    );

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.cliente_nome',
            'Maria Oliveira'
        );
});

it('atualiza uma entrega antes de possuir tentativa', function () {
    $entrega = Entrega::create([
        'numero_pedido' => '3000',

        'cliente_id' => $this->cliente->id,
        'regiao_id' => $this->regiao->id,

        'cliente_nome' => $this->cliente->nome,

        'endereco' => 'Endereço antigo',
        'prioridade' => 'NORMAL',
        'status' => 'PENDENTE',

        'criado_por' => $this->usuario->id,
    ]);

    $entrega->itens()->create([
        'descricao_produto' => 'Cimento',
        'quantidade_solicitada' => 10,
    ]);

    $response = $this->patchJson(
        "/api/entregas/{$entrega->id}",
        [
            'endereco' => 'Endereço atualizado',
            'prioridade' => 'ALTA',

            'itens' => [
                [
                    'codigo_produto' => '500',
                    'descricao_produto' => 'Telha',
                    'unidade' => 'UN',
                    'quantidade_solicitada' => 50,
                ],
            ],
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.endereco',
            'Endereço atualizado'
        )
        ->assertJsonPath('data.prioridade', 'ALTA')
        ->assertJsonCount(1, 'data.itens');

    $this->assertDatabaseHas('entregas', [
        'id' => $entrega->id,
        'endereco' => 'Endereço atualizado',
        'prioridade' => 'ALTA',
    ]);

    $this->assertDatabaseHas('entrega_items', [
        'entrega_id' => $entrega->id,
        'descricao_produto' => 'Telha',
    ]);
});

it('cancela uma entrega sem apagar seu historico', function () {
    $entrega = Entrega::create([
        'numero_pedido' => '4000',

        'cliente_id' => $this->cliente->id,
        'regiao_id' => $this->regiao->id,

        'cliente_nome' => $this->cliente->nome,

        'endereco' => 'Rua C',
        'prioridade' => 'NORMAL',
        'status' => 'PENDENTE',

        'criado_por' => $this->usuario->id,
    ]);

    $response = $this->deleteJson(
        "/api/entregas/{$entrega->id}"
    );

    $response->assertNoContent();

    $this->assertDatabaseHas('entregas', [
        'id' => $entrega->id,
        'status' => 'CANCELADA',
    ]);

    $this->assertDatabaseHas('entrega_eventos', [
        'entrega_id' => $entrega->id,
        'tipo_evento' => 'ENTREGA_CANCELADA',
    ]);
});

it('nao cria entrega sem produtos', function () {
    $response = $this->postJson('/api/entregas', [
        'cliente_id' => $this->cliente->id,
        'regiao_id' => $this->regiao->id,

        'cliente_nome' => $this->cliente->nome,

        'endereco' => 'Rua Teste',

        'itens' => [],
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'itens',
        ]);
});