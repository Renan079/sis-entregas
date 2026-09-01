<?php

use App\Services\NotaConstrucaoParser;

it('interpreta uma nota dividida em duas fotos', function () {
    $texto = <<<'TXT'
JH CONSTRUCAO
Pedido : 19001 - RENAN MACHADO
Data/Hr: 01/09/2026 - 08:30
Item Codigo Descricao do Produto
0001 1789780130157 LUVA SOLDAVEL LISA 32 MM
1 x 3,00 = 3,00
0002 0000000000476 MANGUEIRA PRA IRRIGACAO 1P PRETA
50 x 2,60 = 130,00

0003 0000000000999 JOELHO SOLDAVEL 25 MM
4 x 1,50 = 6,00
TOTAL = 139,00
Cliente: (DR WILTON)
[76] - WILTON BARROS QUENTAL
SITIO RIACHAO
SERRA - PORTEIRAO
(88)98104-2462
TXT;

    $resultado = app(
        NotaConstrucaoParser::class
    )->interpretar($texto);

    expect($resultado['numero_pedido'])
        ->toBe('19001')
        ->and($resultado['cliente']['codigo_externo'])
        ->toBe('76')
        ->and($resultado['itens'])
        ->toHaveCount(3)
        ->and($resultado['itens'][0]['codigo_produto'])
        ->toBe('1789780130157')
        ->and($resultado['itens'][2]['quantidade_solicitada'])
        ->toBe(4.0);
});
