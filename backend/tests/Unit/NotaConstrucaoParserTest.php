<?php

use App\Services\NotaConstrucaoParser;

it('nao mistura totais e pagamento na descricao do produto', function () {
    $texto = <<<'TXT'
A JH CONSTRUCAO
Es) Pedid 18536 - RENAN MACHADO
Data/Hr 1/02/202€ 07:37 X J01
/ Item Coí Descri 3 Produ
Quantidade Jnitario Total Item
“á 0001 1789780130157 LUVA SOLDAVEL LISA 3:
y MM
04 SUBTOTAL = 3,00
/ OUTROS = 2,50
(/ TOTAL = 5,50
v / PAGAMENTO= 5,50
, Forma de pagamento :
Y 19/03/2026 5,50 - PROMISSORIA
Cliente: (DR WILTON)
[76] - WILTON BARROS QUENTAL
SITIO RIACHAO
SERRA - PORTEIRAO
TXT;

    $resultado = app(
        NotaConstrucaoParser::class
    )->interpretar($texto);

    expect($resultado['itens'])
        ->toHaveCount(1)
        ->and(
            $resultado['itens'][0]['descricao_produto']
        )
        ->toBe('LUVA SOLDAVEL LISA 3: MM')
        ->and(
            $resultado['itens'][0]['descricao_produto']
        )
        ->not->toContain('SUBTOTAL')
        ->and(
            $resultado['itens'][0]['descricao_produto']
        )
        ->not->toContain('PAGAMENTO');
});
