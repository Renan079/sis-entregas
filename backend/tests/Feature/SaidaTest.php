<?php

use App\Models\Entrega;
use App\Models\Regiao;
use App\Models\Saida;
use App\Models\User;
use App\Models\Veiculo;
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

    $this->veiculo = Veiculo::create([
        'nome' => 'D20',
        'placa' => 'ABC1D23',
        'tipo' => 'CAMINHONETE',
        'status' => 'DISPONIVEL',
        'ativo' => true,
    ]);
});

function criarEntregaParaSaida(
    User $usuario,
    Regiao $regiao,
    string $pedido
): Entrega {
    $entrega = Entrega::create([
        'numero_pedido' => $pedido,
        'regiao_id' => $regiao->id,

        'cliente_nome' => 'Cliente Teste',
        'cliente_telefone' => '88999999999',

        'endereco' => 'Rua Principal, 100',

        'prioridade' => 'NORMAL',
        'status' => 'PENDENTE',

        'criado_por' => $usuario->id,
    ]);

    $entrega->itens()->create([
        'codigo_produto' => '147',
        'descricao_produto' => 'Cimento 50KG',
        'unidade' => 'UN',
        'quantidade_solicitada' => 10,
    ]);

    return $entrega;
}

it('cria uma saida planejada', function () {
    $response = $this->postJson('/api/saidas', [
        'data_saida' => '2026-08-13',

        'regiao_principal_id' =>
            $this->regiao->id,

        'veiculo_id' =>
            $this->veiculo->id,

        'observacao' =>
            'Primeira carrada do dia',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.status',
            'PLANEJADA'
        )
        ->assertJsonPath(
            'data.regiao_principal.id',
            $this->regiao->id
        )
        ->assertJsonPath(
            'data.veiculo.id',
            $this->veiculo->id
        );

    $this->assertDatabaseHas('saidas', [
        'data_saida' => '2026-08-13',
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);
});

it('adiciona uma entrega em uma saida', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        '1001'
    );

    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' =>
            $this->regiao->id,
        'veiculo_id' =>
            $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' =>
            $this->usuario->id,
    ]);

    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'ordem' => 1,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.entregas.0.id',
            $entrega->id
        )
        ->assertJsonPath(
            'data.entregas.0.ordem',
            1
        );

    $this->assertDatabaseHas('saida_entregas', [
        'saida_id' => $saida->id,
        'entrega_id' => $entrega->id,
        'ordem' => 1,
    ]);

    $this->assertDatabaseHas('entregas', [
        'id' => $entrega->id,
        'status' => 'PROGRAMADA',
    ]);
});

it('inicia uma saida e coloca as entregas em saida', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        '2001'
    );

    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' =>
            $this->regiao->id,
        'veiculo_id' =>
            $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' =>
            $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'ordem' => 1,
        ]
    )->assertOk();

    $response = $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'EM_ANDAMENTO'
        );

    $this->assertDatabaseHas('saidas', [
        'id' => $saida->id,
        'status' => 'EM_ANDAMENTO',
    ]);

    $this->assertDatabaseHas('entregas', [
        'id' => $entrega->id,
        'status' => 'EM_SAIDA',
    ]);
});

it('finaliza uma saida depois de registrar o resultado das entregas', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        '4001'
    );

    $item = $entrega->itens()->first();

    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Adiciona a entrega.
     * Como não informamos itens, o sistema
     * programa todo o saldo disponível.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'ordem' => 1,
        ]
    )->assertOk();

    /*
     * Inicia a carrada.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'EM_ANDAMENTO'
        );

    /*
     * Registra o retorno da entrega.
     *
     * Neste teste consideramos que tudo
     * que foi carregado foi entregue.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' =>
                        (float) $item->quantidade_solicitada,
                ],
            ],
        ]
    )
        ->assertCreated()
        ->assertJsonPath(
            'data.resultado',
            'COMPLETA'
        );

    /*
     * Agora sim a carrada pode ser finalizada.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/finalizar"
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'FINALIZADA'
        );

    $this->assertDatabaseHas('saidas', [
        'id' => $saida->id,
        'status' => 'FINALIZADA',
    ]);

    $this->assertDatabaseHas('entregas', [
        'id' => $entrega->id,
        'status' => 'CONCLUIDA',
    ]);
});

it('cancela uma saida e devolve a entrega para pendente', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        '4001'
    );

    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' =>
            $this->regiao->id,
        'veiculo_id' =>
            $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' =>
            $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,
        ]
    )->assertOk();

    $response = $this->postJson(
        "/api/saidas/{$saida->id}/cancelar"
    );

    $response->assertNoContent();

    $this->assertDatabaseHas('saidas', [
        'id' => $saida->id,
        'status' => 'CANCELADA',
    ]);

    $this->assertDatabaseHas('entregas', [
        'id' => $entrega->id,
        'status' => 'PENDENTE',
    ]);
});

it('remove uma entrega de uma saida planejada', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        '5001'
    );

    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' =>
            $this->regiao->id,
        'veiculo_id' =>
            $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' =>
            $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,
        ]
    )->assertOk();

    $response = $this->deleteJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}"
    );

    $response->assertOk();

    $this->assertDatabaseMissing('saida_entregas', [
        'saida_id' => $saida->id,
        'entrega_id' => $entrega->id,
    ]);

    $this->assertDatabaseHas('entregas', [
        'id' => $entrega->id,
        'status' => 'PENDENTE',
    ]);
});

it('permite dividir os itens de uma entrega entre duas carradas', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        '6001'
    );

    $item = $entrega->itens()->first();

    // Nosso helper cria 10 unidades.
    $saida1 = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $saida2 = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida1->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'ordem' => 1,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 4,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida2->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'ordem' => 1,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 6,
                ],
            ],
        ]
    )->assertOk();

    $this->assertDatabaseHas('saida_entrega_itens', [
        'entrega_item_id' => $item->id,
        'quantidade_programada' => '4.000',
    ]);

    $this->assertDatabaseHas('saida_entrega_itens', [
        'entrega_item_id' => $item->id,
        'quantidade_programada' => '6.000',
    ]);
});

it('nao permite programar quantidade acima do saldo disponivel', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        '7001'
    );

    $item = $entrega->itens()->first();

    $saida1 = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $saida2 = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    // Programa 4 das 10.
    $this->postJson(
        "/api/saidas/{$saida1->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 4,
                ],
            ],
        ]
    )->assertOk();

    // Restam somente 6. Tentar 7 deve falhar.
    $response = $this->postJson(
        "/api/saidas/{$saida2->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 7,
                ],
            ],
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'itens',
        ]);
});

it('cria pendencia somente do que foi carregado e nao entregue', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'RET-001'
    );

    $item = $entrega->itens()->first();

    /*
     * Pedido total: 100 Mizu.
     */
    $item->update([
        'quantidade_solicitada' => 100,
    ]);

    /*
     * Cria a carrada planejada.
     */
    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * O pedido possui 100 unidades,
     * mas esta carrada leva apenas 50.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 50,
                ],
            ],
        ]
    )->assertOk();

    /*
     * Confirma que somente 50 unidades
     * foram programadas nesta carrada.
     */
    $this->assertDatabaseHas(
        'saida_entrega_itens',
        [
            'entrega_item_id' => $item->id,
            'quantidade_programada' => '50.000',
        ]
    );

    /*
     * Veículo sai.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Dos 50 carregados:
     *
     * 45 foram entregues;
     * 5 não foram entregues;
     * os 5 deverão ser REENTREGUES.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 45,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],

            'motivo_nao_entrega' =>
                'Faltaram 5 sacos na entrega',

            'observacao' =>
                'Cliente recebeu parcialmente',
        ]
    );

    /*
     * O resultado desta tentativa foi parcial.
     */
    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.resultado',
            'PARCIAL'
        );

    /*
     * Foi realmente entregue 45.
     */
    $this->assertDatabaseHas(
        'tentativa_items',
        [
            'entrega_item_id' => $item->id,
            'quantidade_entregue' => '45.000',
        ]
    );

    /*
     * A pendência deve ser somente:
     *
     * 50 carregados
     * - 45 entregues
     * = 5 pendentes
     *
     * Não pode criar pendência de 55,
     * pois os outros 50 produtos do pedido
     * nunca saíram da loja.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'entrega_id' => $entrega->id,
            'entrega_item_id' => $item->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '0.000',
            'status' => 'ABERTA',
        ]
    );

    /*
     * Apenas uma pendência foi criada.
     */
    $this->assertDatabaseCount(
        'pendencias',
        1
    );

    /*
     * O pedido inteiro ainda não foi concluído.
     *
     * Pedido: 100
     * Entregue: 45
     * Pendência: 5
     * Ainda não enviado: 50
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'PARCIAL',
        ]
    );
});


it('nao cria pendencia quando tudo que foi carregado foi entregue', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'RET-002'
    );

    $item = $entrega->itens()->first();

    /*
     * Pedido: 100.
     */
    $item->update([
        'quantidade_solicitada' => 100,
    ]);

    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Leva 50.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 50,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Entregou os 50 carregados.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 50,
                ],
            ],
        ]
    );

    /*
     * A tentativa foi completa:
     * tudo que estava no veículo foi entregue.
     */
    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.resultado',
            'COMPLETA'
        );

    /*
     * Não existe pendência.
     */
    $this->assertDatabaseCount(
        'pendencias',
        0
    );

    /*
     * O pedido ainda é parcial porque:
     *
     * pedido = 100
     * entregue = 50
     * ainda existem 50 que nunca foram carregados.
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'PARCIAL',
        ]
    );
});


it('nao permite registrar quantidade entregue maior que a carregada', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'RET-003'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 100,
    ]);

    $saida = Saida::create([
        'data_saida' => '2026-08-13',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Só colocou 50 no veículo.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 50,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Tenta dizer que entregou 60,
     * embora só tenha levado 50.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 60,
                ],
            ],
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'itens',
        ]);

    /*
     * Como a operação foi recusada,
     * nenhuma tentativa deve existir.
     */
    $this->assertDatabaseMissing(
        'tentativa_entregas',
        [
            'entrega_id' => $entrega->id,
            'saida_id' => $saida->id,
        ]
    );

    $this->assertDatabaseCount(
        'pendencias',
        0
    );
});


/*NOVOS TESTES PARA DEVOLUCAO*/


it('registra devolucao sem criar pendencia', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'DEV-001'
    );

    $item = $entrega->itens()->first();

    /*
     * Pedido total: 50 unidades.
     */
    $item->update([
        'quantidade_solicitada' => 50,
    ]);

    $saida = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Carrega as 50 unidades.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 50,
                ],
            ],
        ]
    )->assertOk();

    /*
     * Inicia a carrada.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Das 50:
     *
     * 45 foram entregues;
     * 5 foram recusadas pelo cliente;
     * as 5 devem ser DEVOLVIDAS.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 45,
                    'destino_nao_entregue' => 'DEVOLVIDO',
                    'motivo_devolucao' => 'CLIENTE_RECUSOU',
                    'observacao' =>
                        'Cliente recusou as 5 unidades restantes.',
                ],
            ],
        ]
    );

    /*
     * A viagem foi parcial:
     * nem tudo que saiu foi entregue.
     */
    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.resultado',
            'PARCIAL'
        );

    /*
     * Confirma as 45 efetivamente entregues.
     */
    $this->assertDatabaseHas(
        'tentativa_items',
        [
            'entrega_item_id' => $item->id,
            'quantidade_entregue' => '45.000',
        ]
    );

    /*
     * Confirma a devolução das outras 5.
     */
    $this->assertDatabaseHas(
        'devolucoes',
        [
            'entrega_id' => $entrega->id,
            'entrega_item_id' => $item->id,
            'saida_id' => $saida->id,
            'quantidade' => '5.000',
            'motivo' => 'CLIENTE_RECUSOU',
            'registrado_por' => $this->usuario->id,
        ]
    );

    /*
     * DEVOLVIDO não pode gerar pendência.
     */
    $this->assertDatabaseMissing(
        'pendencias',
        [
            'entrega_id' => $entrega->id,
            'entrega_item_id' => $item->id,
        ]
    );

    /*
     * 45 entregues + 5 devolvidos = 50 tratados.
     *
     * Portanto o pedido está logisticamente concluído.
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'CONCLUIDA',
        ]
    );
});


it('exige destino quando parte do produto nao foi entregue', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'DEV-002'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 50,
    ]);

    $saida = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Carrega 50.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 50,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Entrega somente 45,
     * mas NÃO informa o destino das 5 restantes.
     *
     * Isso deve ser rejeitado.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 45,
                ],
            ],
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'itens',
        ]);

    /*
     * Como o retorno foi inválido,
     * nada deve ser registrado.
     */
    $this->assertDatabaseMissing(
        'tentativa_items',
        [
            'entrega_item_id' => $item->id,
        ]
    );

    $this->assertDatabaseMissing(
        'pendencias',
        [
            'entrega_item_id' => $item->id,
        ]
    );

    $this->assertDatabaseMissing(
        'devolucoes',
        [
            'entrega_item_id' => $item->id,
        ]
    );
});


it('exige motivo quando o produto for devolvido', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'DEV-003'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 50,
    ]);

    $saida = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Carrega as 50 unidades.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 50,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Marca as 5 restantes como DEVOLVIDO,
     * mas não informa o motivo.
     *
     * O sistema precisa impedir.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 45,
                    'destino_nao_entregue' => 'DEVOLVIDO',
                ],
            ],
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'itens',
        ]);

    /*
     * Não pode existir devolução sem motivo.
     */
    $this->assertDatabaseMissing(
        'devolucoes',
        [
            'entrega_item_id' => $item->id,
        ]
    );

    /*
     * Também não pode criar pendência,
     * porque a operação inteira foi rejeitada.
     */
    $this->assertDatabaseMissing(
        'pendencias',
        [
            'entrega_item_id' => $item->id,
        ]
    );
});

it('resolve completamente uma pendencia em uma reentrega', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-001'
    );

    $item = $entrega->itens()->first();

    /*
     * Pedido total: 5 unidades.
     */
    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * =====================================================
     * PRIMEIRA CARRADA
     * =====================================================
     *
     * Leva as 5, mas não consegue entregar nenhuma.
     * As 5 viram pendência.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],

            'motivo_nao_entrega' =>
                'Cliente não recebeu o produto.',
        ]
    )->assertCreated();

    /*
     * Confirma que nasceu uma pendência de 5.
     */
    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->where('entrega_item_id', $item->id)
        ->firstOrFail();

    expect((float) $pendencia->quantidade_original)
        ->toBe(5.0);

    expect($pendencia->status->value)
        ->toBe('ABERTA');

    /*
     * =====================================================
     * SEGUNDA CARRADA
     * =====================================================
     *
     * Agora fazemos a reentrega das 5.
     */
    $saidaReentrega = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Adiciona a pendência à nova carrada.
     */
    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    /*
     * A pendência agora está programada.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'status' => 'PROGRAMADA',
        ]
    );

    /*
     * Inicia a carrada contendo somente a reentrega.
     */
    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/iniciar"
    )->assertOk();

    /*
     * Entrega as 5 unidades da pendência.
     */
    $response = $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 5,
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.quantidade_programada',
            5
        )
        ->assertJsonPath(
            'data.quantidade_entregue',
            5
        )
        ->assertJsonPath(
            'data.quantidade_devolvida',
            0
        )
        ->assertJsonPath(
            'data.quantidade_reentrega',
            0
        );

    /*
     * O retorno específico da reentrega deve existir.
     */
    $this->assertDatabaseHas(
        'saida_pendencia_retornos',
        [
            'saida_id' => $saidaReentrega->id,
            'pendencia_id' => $pendencia->id,
            'quantidade_programada' => '5.000',
            'quantidade_entregue' => '5.000',
            'quantidade_devolvida' => '0.000',
            'quantidade_reentrega' => '0.000',
        ]
    );

    /*
     * Toda a pendência foi resolvida.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '5.000',
            'status' => 'RESOLVIDA',
        ]
    );

    /*
     * Como o pedido tinha 5 e as 5 foram entregues
     * na reentrega, não existe obrigação logística restante.
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'CONCLUIDA',
        ]
    );
});


it('mantem saldo pendente quando a reentrega for parcial', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-002'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * =====================================================
     * PRIMEIRA CARRADA
     * Cria pendência de 5.
     * =====================================================
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->where('entrega_item_id', $item->id)
        ->firstOrFail();

    /*
     * =====================================================
     * SEGUNDA CARRADA
     * Tenta reentregar as 5.
     * =====================================================
     */
    $saidaReentrega = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/iniciar"
    )->assertOk();

    /*
     * Das 5:
     *
     * 3 foram entregues;
     * 2 ainda deverão ser REENTREGUES novamente.
     */
    $response = $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 3,
            'destino_restante' => 'REENTREGA',
            'observacao' =>
                'Ainda ficaram duas unidades para o cliente.',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.quantidade_programada',
            5
        )
        ->assertJsonPath(
            'data.quantidade_entregue',
            3
        )
        ->assertJsonPath(
            'data.quantidade_devolvida',
            0
        )
        ->assertJsonPath(
            'data.quantidade_reentrega',
            2
        )
        ->assertJsonPath(
            'data.destino_restante',
            'REENTREGA'
        );

    /*
     * O snapshot da reentrega precisa mostrar
     * exatamente o que ocorreu nesta carrada.
     */
    $this->assertDatabaseHas(
        'saida_pendencia_retornos',
        [
            'saida_id' => $saidaReentrega->id,
            'pendencia_id' => $pendencia->id,
            'quantidade_programada' => '5.000',
            'quantidade_entregue' => '3.000',
            'quantidade_devolvida' => '0.000',
            'quantidade_reentrega' => '2.000',
            'destino_restante' => 'REENTREGA',
        ]
    );

    /*
     * 3 das 5 foram resolvidas.
     *
     * Portanto:
     *
     * original = 5
     * resolvido = 3
     * saldo = 2
     *
     * Como ainda existe saldo, volta para ABERTA.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '3.000',
            'status' => 'ABERTA',
        ]
    );

    /*
     * Não pode existir devolução neste cenário.
     */
    $this->assertDatabaseMissing(
        'devolucoes',
        [
            'pendencia_id' => $pendencia->id,
        ]
    );

    /*
     * Ainda existem 2 unidades para entregar.
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'PARCIAL',
        ]
    );
});


it('resolve o restante da reentrega como devolucao', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-003'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * =====================================================
     * PRIMEIRA CARRADA
     * Cria a pendência original de 5.
     * =====================================================
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->where('entrega_item_id', $item->id)
        ->firstOrFail();

    /*
     * =====================================================
     * SEGUNDA CARRADA
     * =====================================================
     */
    $saidaReentrega = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/iniciar"
    )->assertOk();

    /*
     * Das 5:
     *
     * 3 foram entregues;
     * 2 o cliente não quer mais.
     *
     * Portanto as 2 são DEVOLVIDAS.
     */
    $response = $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 3,
            'destino_restante' => 'DEVOLVIDO',
            'motivo_devolucao' => 'CLIENTE_DESISTIU',
            'observacao' =>
                'Cliente desistiu das duas unidades restantes.',
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.quantidade_programada',
            5
        )
        ->assertJsonPath(
            'data.quantidade_entregue',
            3
        )
        ->assertJsonPath(
            'data.quantidade_devolvida',
            2
        )
        ->assertJsonPath(
            'data.quantidade_reentrega',
            0
        )
        ->assertJsonPath(
            'data.destino_restante',
            'DEVOLVIDO'
        );

    /*
     * Registra exatamente o resultado da reentrega.
     */
    $this->assertDatabaseHas(
        'saida_pendencia_retornos',
        [
            'saida_id' => $saidaReentrega->id,
            'pendencia_id' => $pendencia->id,
            'quantidade_programada' => '5.000',
            'quantidade_entregue' => '3.000',
            'quantidade_devolvida' => '2.000',
            'quantidade_reentrega' => '0.000',
            'destino_restante' => 'DEVOLVIDO',
            'motivo_devolucao' => 'CLIENTE_DESISTIU',
        ]
    );

    /*
     * Confirma a devolução das 2 restantes.
     */
    $this->assertDatabaseHas(
        'devolucoes',
        [
            'entrega_id' => $entrega->id,
            'entrega_item_id' => $item->id,
            'saida_id' => $saidaReentrega->id,
            'pendencia_id' => $pendencia->id,
            'quantidade' => '2.000',
            'motivo' => 'CLIENTE_DESISTIU',
            'registrado_por' => $this->usuario->id,
        ]
    );

    /*
     * As 5 unidades da obrigação original foram tratadas:
     *
     * 3 entregues
     * +
     * 2 devolvidas
     *
     * Portanto a pendência é RESOLVIDA.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '5.000',
            'status' => 'RESOLVIDA',
        ]
    );

    /*
     * Não existe mais obrigação logística.
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'CONCLUIDA',
        ]
    );
});

it('nao permite programar a mesma pendencia em duas carradas', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-004'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Cria a pendência original de 5.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->where('entrega_item_id', $item->id)
        ->firstOrFail();

    /*
     * Cria duas novas carradas.
     */
    $saidaA = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $saidaB = Saida::create([
        'data_saida' => '2026-08-16',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Primeira carrada consegue programar.
     */
    $this->postJson(
        "/api/saidas/{$saidaA->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    /*
     * A mesma pendência NÃO pode ser colocada
     * simultaneamente na segunda carrada.
     */
    $response = $this->postJson(
        "/api/saidas/{$saidaB->id}/pendencias/{$pendencia->id}"
    );

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'pendencia',
        ]);

    /*
     * Continua vinculada apenas à primeira carrada.
     */
    $this->assertDatabaseHas(
        'saida_pendencias',
        [
            'saida_id' => $saidaA->id,
            'pendencia_id' => $pendencia->id,
        ]
    );

    $this->assertDatabaseMissing(
        'saida_pendencias',
        [
            'saida_id' => $saidaB->id,
            'pendencia_id' => $pendencia->id,
        ]
    );

    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'status' => 'PROGRAMADA',
        ]
    );
});


it('nao permite entregar quantidade maior que a pendencia programada', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-005'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Gera pendência de 5.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Nova carrada leva a pendência de 5.
     */
    $saida = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Tenta dizer que entregou 6,
     * mas só existiam 5 programadas.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 6,
        ]
    );

    $response->assertStatus(422);

    /*
     * Nenhum retorno inválido pode ser persistido.
     */
    $this->assertDatabaseMissing(
        'saida_pendencia_retornos',
        [
            'saida_id' => $saida->id,
            'pendencia_id' => $pendencia->id,
        ]
    );

    /*
     * A pendência continua intacta.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '0.000',
            'status' => 'PROGRAMADA',
        ]
    );
});


it('finaliza uma carrada contendo somente reentrega depois do retorno', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-006'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Primeira carrada gera pendência de 5.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Nova carrada SEM entrega normal.
     * Ela terá somente a reentrega.
     */
    $saidaReentrega = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    /*
     * Deve conseguir iniciar mesmo sem entregas normais.
     */
    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/iniciar"
    )
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'EM_ANDAMENTO'
        );

    /*
     * Registra o retorno da única reentrega.
     */
    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 5,
        ]
    )->assertCreated();

    /*
     * Agora a carrada deve poder ser finalizada.
     */
    $response = $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/finalizar"
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'FINALIZADA'
        );

    $this->assertDatabaseHas(
        'saidas',
        [
            'id' => $saidaReentrega->id,
            'status' => 'FINALIZADA',
        ]
    );

    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'status' => 'RESOLVIDA',
        ]
    );
});

it('remove uma pendencia de uma carrada planejada e volta para aberta', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-007'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Cria a pendência original.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Coloca a pendência em outra carrada.
     */
    $saida = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'status' => 'PROGRAMADA',
        ]
    );

    /*
     * Operador muda de ideia antes da viagem.
     */
    $this->deleteJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    /*
     * Não pertence mais à carrada.
     */
    $this->assertDatabaseMissing(
        'saida_pendencias',
        [
            'saida_id' => $saida->id,
            'pendencia_id' => $pendencia->id,
        ]
    );

    /*
     * Volta a ficar disponível para outra carrada.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '0.000',
            'status' => 'ABERTA',
        ]
    );
});

it('nao finaliza carrada enquanto uma reentrega estiver sem retorno', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-008'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Gera pendência de 5.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Carrada contendo somente a reentrega.
     */
    $saida = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * NÃO registramos o retorno da reentrega.
     *
     * Tentamos finalizar direto.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/finalizar"
    );

    /*
     * A carrada não pode terminar.
     *
     * Aqui não vou prender o teste a uma mensagem específica.
     */
    $response->assertStatus(500);

    /*
     * Principal regra que queremos proteger:
     * a carrada continua EM_ANDAMENTO.
     */
    $this->assertDatabaseHas(
        'saidas',
        [
            'id' => $saida->id,
            'status' => 'EM_ANDAMENTO',
        ]
    );

    /*
     * Também não existe retorno inventado.
     */
    $this->assertDatabaseMissing(
        'saida_pendencia_retornos',
        [
            'saida_id' => $saida->id,
            'pendencia_id' => $pendencia->id,
        ]
    );
});

it('cancela carrada planejada e libera novamente a pendencia', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'REENT-009'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Gera pendência.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Programa em nova carrada.
     */
    $saida = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'status' => 'PROGRAMADA',
        ]
    );

    /*
     * Cancela a carrada antes da operação acontecer.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/cancelar"
    )->assertNoContent();

    /*
     * Carrada cancelada.
     */
    $this->assertDatabaseHas(
        'saidas',
        [
            'id' => $saida->id,
            'status' => 'CANCELADA',
        ]
    );

    /*
     * A obrigação continua existindo.
     *
     * Portanto precisa voltar para ABERTA.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '0.000',
            'status' => 'ABERTA',
        ]
    );
});

it('nao remove pendencia depois que a carrada foi iniciada', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'SEG-001'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    $saida = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Depois de iniciada, não pode remover.
     */
    $response = $this->deleteJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    );

    $response->assertStatus(500);

    /*
     * O vínculo continua existindo.
     */
    $this->assertDatabaseHas(
        'saida_pendencias',
        [
            'saida_id' => $saida->id,
            'pendencia_id' => $pendencia->id,
        ]
    );

    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'status' => 'PROGRAMADA',
        ]
    );
});

it('nao registra duas vezes o retorno da mesma reentrega', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'SEG-002'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Gera pendência de 5.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    $saida = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Primeiro retorno: válido.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 5,
        ]
    )->assertCreated();

    /*
     * Segundo retorno da MESMA pendência
     * na MESMA carrada.
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 5,
        ]
    );

    $response->assertStatus(500);

    /*
     * Continua existindo somente um retorno.
     */
    expect(
        \App\Models\SaidaPendenciaRetorno::query()
            ->where('saida_id', $saida->id)
            ->where('pendencia_id', $pendencia->id)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_resolvida' => '5.000',
            'status' => 'RESOLVIDA',
        ]
    );
});

it('nao permite programar novamente uma pendencia resolvida', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'SEG-003'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Resolve a pendência.
     */
    $saidaA = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaA->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saidaA->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaA->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 5,
        ]
    )->assertCreated();

    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'status' => 'RESOLVIDA',
        ]
    );

    /*
     * Tenta colocar a mesma pendência resolvida
     * em uma terceira carrada.
     */
    $saidaB = Saida::create([
        'data_saida' => '2026-08-16',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $response = $this->postJson(
        "/api/saidas/{$saidaB->id}/pendencias/{$pendencia->id}"
    );

    $response->assertStatus(500);

    $this->assertDatabaseMissing(
        'saida_pendencias',
        [
            'saida_id' => $saidaB->id,
            'pendencia_id' => $pendencia->id,
        ]
    );
});

it('permite destinos diferentes para produtos da mesma entrega', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'SEG-004'
    );

    $itemReentrega = $entrega->itens()->first();

    $itemReentrega->update([
        'quantidade_solicitada' => 10,
    ]);

    /*
     * Segundo produto do mesmo pedido.
     */
    $itemDevolucao = $entrega->itens()->create([
        'codigo_produto' => 'ARG-002',
        'descricao_produto' => 'Argamassa AC3',
        'unidade' => 'SC',
        'quantidade_solicitada' => 20,
    ]);

    $saida = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    /*
     * Leva os dois produtos completos.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $itemReentrega->id,
                    'quantidade_programada' => 10,
                ],
                [
                    'entrega_item_id' => $itemDevolucao->id,
                    'quantidade_programada' => 20,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Produto 1:
     * 10 -> entrega 8 -> reentrega 2
     *
     * Produto 2:
     * 20 -> entrega 15 -> devolução 5
     */
    $response = $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $itemReentrega->id,
                    'quantidade_entregue' => 8,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
                [
                    'entrega_item_id' => $itemDevolucao->id,
                    'quantidade_entregue' => 15,
                    'destino_nao_entregue' => 'DEVOLVIDO',
                    'motivo_devolucao' => 'CLIENTE_RECUSOU',
                ],
            ],
        ]
    );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.resultado',
            'PARCIAL'
        );

    /*
     * Produto 1 gera pendência de 2.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'entrega_id' => $entrega->id,
            'entrega_item_id' => $itemReentrega->id,
            'quantidade_original' => '2.000',
            'status' => 'ABERTA',
        ]
    );

    /*
     * Produto 1 NÃO gera devolução.
     */
    $this->assertDatabaseMissing(
        'devolucoes',
        [
            'entrega_item_id' => $itemReentrega->id,
        ]
    );

    /*
     * Produto 2 gera devolução de 5.
     */
    $this->assertDatabaseHas(
        'devolucoes',
        [
            'entrega_id' => $entrega->id,
            'entrega_item_id' => $itemDevolucao->id,
            'quantidade' => '5.000',
            'motivo' => 'CLIENTE_RECUSOU',
        ]
    );

    /*
     * Produto 2 NÃO gera pendência.
     */
    $this->assertDatabaseMissing(
        'pendencias',
        [
            'entrega_item_id' => $itemDevolucao->id,
        ]
    );

    /*
     * Ainda existe obrigação das 2 unidades
     * do primeiro produto.
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'PARCIAL',
        ]
    );
});

it('nao conta pendencia novamente como saldo normal', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'SEG-005'
    );

    $item = $entrega->itens()->first();

    /*
     * Pedido de 100.
     */
    $item->update([
        'quantidade_solicitada' => 100,
    ]);

    /*
     * Primeira carrada leva 55.
     */
    $saida = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 55,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Entrega 50.
     *
     * 5 viram pendência.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 50,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    /*
     * Pedido: 100
     * Entregue: 50
     * Pendência: 5
     *
     * Saldo restante = 50
     *
     * Mas saldo NORMAL disponível:
     *
     * 100 - 50 - 5 = 45.
     */
    $response = $this->getJson(
        "/api/planejamento/entregas/{$entrega->id}/itens-disponiveis"
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.itens.0.quantidade_disponivel',
            45
        );
});

it('mantem saldo normal correto depois de reentrega parcial com devolucao', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'SEG-006'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 100,
    ]);

    /*
     * Primeira carrada:
     *
     * 55 carregados
     * 50 entregues
     * 5 reentrega
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 55,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 50,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Antes da reentrega:
     *
     * 100 solicitado
     * 50 entregue
     * 5 pendência
     *
     * Normal disponível = 45.
     */
    $this->getJson(
        "/api/planejamento/entregas/{$entrega->id}/itens-disponiveis"
    )
        ->assertOk()
        ->assertJsonPath(
            'data.itens.0.quantidade_disponivel',
            45
        );

    /*
     * Reentrega dos 5.
     */
    $saidaReentrega = Saida::create([
        'data_saida' => '2026-08-15',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/iniciar"
    )->assertOk();

    /*
     * Dos 5:
     *
     * 3 entregues
     * 2 devolvidos
     */
    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}/resultado",
        [
            'quantidade_entregue' => 3,
            'destino_restante' => 'DEVOLVIDO',
            'motivo_devolucao' => 'CLIENTE_DESISTIU',
        ]
    )->assertCreated();

    /*
     * Agora:
     *
     * Pedido = 100
     * Entregue total = 53
     * Devolvido = 2
     *
     * Tratado = 55
     *
     * Restam normalmente = 45.
     *
     * NÃO pode virar:
     * 42, 47, 50 etc.
     */
    $response = $this->getJson(
        "/api/planejamento/entregas/{$entrega->id}/itens-disponiveis"
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.itens.0.quantidade_disponivel',
            45
        );

    /*
     * Pendência acabou.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_resolvida' => '5.000',
            'status' => 'RESOLVIDA',
        ]
    );

    /*
     * Devolução das 2 registrada.
     */
    $this->assertDatabaseHas(
        'devolucoes',
        [
            'entrega_item_id' => $item->id,
            'pendencia_id' => $pendencia->id,
            'quantidade' => '2.000',
        ]
    );
});

it('resolve manualmente uma pendencia aberta por desistência do cliente', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'MANUAL-001'
    );

    $item = $entrega->itens()->first();

    /*
     * Pedido total: 5 unidades.
     */
    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Primeira carrada leva as 5.
     */
    $saida = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saida->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saida->id}/iniciar"
    )->assertOk();

    /*
     * Nenhuma unidade foi entregue.
     *
     * As 5 ficam para reentrega.
     */
    $this->postJson(
        "/api/saidas/{$saida->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],

            'motivo_nao_entrega' =>
                'Cliente não recebeu o produto.',
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->where('entrega_item_id', $item->id)
        ->firstOrFail();

    /*
     * Antes da resolução manual:
     *
     * original = 5
     * resolvido = 0
     * saldo = 5
     * status = ABERTA
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '0.000',
            'status' => 'ABERTA',
        ]
    );

    /*
     * Dois dias depois o cliente comparece à loja
     * e informa que não deseja mais as 5 unidades.
     */
    $response = $this->postJson(
        "/api/pendencias/{$pendencia->id}/resolver-manualmente",
        [
            'motivo_devolucao' => 'CLIENTE_DESISTIU',
            'observacao' =>
                'Cliente compareceu à loja e desistiu do restante.',
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'RESOLVIDA'
        )
        ->assertJsonPath(
            'data.quantidade_original',
            5
        )
        ->assertJsonPath(
            'data.quantidade_resolvida',
            5
        )
        ->assertJsonPath(
            'data.saldo_pendente',
            0
        );

    /*
     * A pendência precisa estar encerrada.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '5.000',
            'status' => 'RESOLVIDA',
        ]
    );

    /*
     * A quantidade deixa de ser obrigação logística
     * e vira devolução.
     *
     * Não existe carrada nesta operação.
     */
    $this->assertDatabaseHas(
        'devolucoes',
        [
            'entrega_id' => $entrega->id,
            'entrega_item_id' => $item->id,
            'pendencia_id' => $pendencia->id,
            'quantidade' => '5.000',
            'motivo' => 'CLIENTE_DESISTIU',
            'registrado_por' => $this->usuario->id,
        ]
    );

    /*
     * Precisamos preservar no histórico que essa
     * resolução aconteceu manualmente.
     */
    $this->assertDatabaseHas(
        'pendencia_movimentacoes',
        [
            'pendencia_id' => $pendencia->id,
            'tipo' => 'AJUSTE',
            'quantidade' => '5.000',
            'usuario_id' => $this->usuario->id,
        ]
    );

    /*
     * 0 entregue + 5 devolvido = 5 tratados.
     *
     * Não existe mais nada para entregar.
     */
    $this->assertDatabaseHas(
        'entregas',
        [
            'id' => $entrega->id,
            'status' => 'CONCLUIDA',
        ]
    );
});

it('nao resolve manualmente uma pendencia que esta programada em carrada', function () {
    $entrega = criarEntregaParaSaida(
        $this->usuario,
        $this->regiao,
        'MANUAL-002'
    );

    $item = $entrega->itens()->first();

    $item->update([
        'quantidade_solicitada' => 5,
    ]);

    /*
     * Carrada inicial que gera a pendência.
     */
    $saidaOrigem = Saida::create([
        'data_saida' => '2026-08-14',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas",
        [
            'entrega_id' => $entrega->id,

            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_programada' => 5,
                ],
            ],
        ]
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/iniciar"
    )->assertOk();

    $this->postJson(
        "/api/saidas/{$saidaOrigem->id}/entregas/{$entrega->id}/resultado",
        [
            'itens' => [
                [
                    'entrega_item_id' => $item->id,
                    'quantidade_entregue' => 0,
                    'destino_nao_entregue' => 'REENTREGA',
                ],
            ],
        ]
    )->assertCreated();

    $pendencia = \App\Models\Pendencia::query()
        ->where('entrega_id', $entrega->id)
        ->firstOrFail();

    /*
     * Agora coloca a pendência em uma nova carrada.
     */
    $saidaReentrega = Saida::create([
        'data_saida' => '2026-08-16',
        'regiao_principal_id' => $this->regiao->id,
        'veiculo_id' => $this->veiculo->id,
        'status' => 'PLANEJADA',
        'criado_por' => $this->usuario->id,
    ]);

    $this->postJson(
        "/api/saidas/{$saidaReentrega->id}/pendencias/{$pendencia->id}"
    )->assertNoContent();

    /*
     * Agora está PROGRAMADA.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_resolvida' => '0.000',
            'status' => 'PROGRAMADA',
        ]
    );

    /*
     * Tenta resolver manualmente.
     *
     * Deve ser bloqueado.
     */
    $response = $this->postJson(
        "/api/pendencias/{$pendencia->id}/resolver-manualmente",
        [
            'motivo_devolucao' => 'CLIENTE_DESISTIU',
            'observacao' =>
                'Cliente desistiu do restante.',
        ]
    );

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'pendencia',
        ]);

    /*
     * A pendência permanece intacta.
     */
    $this->assertDatabaseHas(
        'pendencias',
        [
            'id' => $pendencia->id,
            'quantidade_original' => '5.000',
            'quantidade_resolvida' => '0.000',
            'status' => 'PROGRAMADA',
        ]
    );

    /*
     * Nenhuma devolução manual pode ter sido criada.
     */
    $this->assertDatabaseMissing(
        'devolucoes',
        [
            'pendencia_id' => $pendencia->id,
            'motivo' => 'CLIENTE_DESISTIU',
        ]
    );
});