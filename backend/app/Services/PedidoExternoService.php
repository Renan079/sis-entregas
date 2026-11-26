<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Regiao;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class PedidoExternoService
{
    public function buscarPorNumero(string $numeroPedido): ?array
    {
        $numeroLimpo = trim($numeroPedido);

        if ($numeroLimpo === '' || ! ctype_digit($numeroLimpo)) {
            throw new RuntimeException('Informe um número de pedido numérico válido.');
        }

        $pdo = $this->conectar();
        $numeroInt = (int) $numeroLimpo;

        $stmtPedido = $pdo->prepare(<<<'SQL'
            SELECT
                p.PEDIDO AS NUMERO_PEDIDO,
                p.DATA AS DATA_PEDIDO,
                COALESCE(p.OBSERVACAO, p.OBS) AS OBSERVACAO,
                c.CB AS CLIENTE_CODIGO,
                c.NOME AS CLIENTE_NOME,
                c.APELIDO AS CLIENTE_APELIDO,
                COALESCE(NULLIF(TRIM(e.TELEFONE), ''), NULLIF(TRIM(c.TEL1), ''), TRIM(c.TEL2)) AS CLIENTE_TELEFONE,
                COALESCE(NULLIF(TRIM(e.ENDERECO), ''), TRIM(c.ENDERECO)) AS ENDERECO,
                COALESCE(NULLIF(TRIM(e.BAIRRO), ''), TRIM(b.DESCRICAO)) AS BAIRRO,
                COALESCE(NULLIF(TRIM(ce.DESCRICAO), ''), TRIM(cc.DESCRICAO)) AS CIDADE,
                COALESCE(NULLIF(TRIM(e.PREF), ''), TRIM(c.PREF)) AS REFERENCIA
            FROM PEDIDO p
            LEFT JOIN CADASTRO c ON c.CB = p.CB
            LEFT JOIN ENDENTREGA e ON e.CODIGOEND = p.CODIGOEND
            LEFT JOIN BAIRRO b ON b.CODIGOBAI = c.CODIGOBAI
            LEFT JOIN CIDADE cc ON cc.CODIGOCID = c.CODIGOCID
            LEFT JOIN CIDADE ce ON ce.CODIGOCID = e.CODIGOCID
            WHERE p.PEDIDO = :numero
        SQL);

        $stmtPedido->execute(['numero' => $numeroInt]);
        $cabecalho = $stmtPedido->fetch(PDO::FETCH_ASSOC);

        if (! $cabecalho) {
            return null;
        }

        $stmtItens = $pdo->prepare(<<<'SQL'
            SELECT
                i.CODIGOPRO AS CODIGO_PRODUTO,
                COALESCE(NULLIF(TRIM(i.DESCRITIVO), ''), TRIM(pr.DESCRICAO)) AS DESCRICAO_PRODUTO,
                TRIM(pr.UNIDADE) AS UNIDADE,
                i.QUANTIDADE AS QUANTIDADE_SOLICITADA
            FROM IT_PEDIDO i
            LEFT JOIN PRODUTO pr ON pr.CODIGOPRO = i.CODIGOPRO
            WHERE i.PEDIDO = :numero
            ORDER BY i.CHAVE ASC
        SQL);

        $stmtItens->execute(['numero' => $numeroInt]);
        $linhasItens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        $codigoExterno = $this->texto($cabecalho['CLIENTE_CODIGO'] ?? null);
        $nomeCliente = $this->texto($cabecalho['CLIENTE_NOME'] ?? null) ?? 'Cliente não identificado';
        $apelidoCliente = $this->texto($cabecalho['CLIENTE_APELIDO'] ?? null);
        $telefoneCliente = $this->texto($cabecalho['CLIENTE_TELEFONE'] ?? null);

        $logradouro = $this->texto($cabecalho['ENDERECO'] ?? null);
        $bairro = $this->texto($cabecalho['BAIRRO'] ?? null);
        $cidade = $this->texto($cabecalho['CIDADE'] ?? null);

        $enderecoCompleto = collect([$logradouro, $bairro, $cidade])
            ->filter()
            ->implode(' - ');

        $clienteExistente = $this->buscarClienteLocalPorCodigoExterno($codigoExterno);

        $regiaoSugerida = $this->sugerirRegiao($bairro, $cidade);

        $pedidoJaExiste = Entrega::where('numero_pedido', (string) $numeroInt)
            ->where('status', '!=', 'CANCELADA')
            ->exists();

        $itens = collect($linhasItens)
            ->map(function (array $item) {
                return [
                    'codigo_produto' => $this->texto($item['CODIGO_PRODUTO'] ?? null),
                    'descricao_produto' => $this->texto($item['DESCRICAO_PRODUTO'] ?? null) ?? 'Produto sem descrição',
                    'unidade' => $this->texto($item['UNIDADE'] ?? null) ?? 'UN',
                    'quantidade_solicitada' => (float) ($item['QUANTIDADE_SOLICITADA'] ?? 0),
                    'observacao' => null,
                ];
            })
            ->filter(fn (array $item) => $item['quantidade_solicitada'] > 0)
            ->values()
            ->all();

        return [
            'numero_pedido' => (string) $numeroInt,
            'data_pedido' => $this->texto($cabecalho['DATA_PEDIDO'] ?? null),
            'cliente_id' => $clienteExistente?->id,
            'cliente' => [
                'codigo_externo' => $codigoExterno,
                'nome' => $nomeCliente,
                'apelido' => $apelidoCliente,
                'telefone' => $telefoneCliente,
            ],
            'regiao_id' => $regiaoSugerida?->id,
            'endereco' => $enderecoCompleto,
            'referencia' => $this->texto($cabecalho['REFERENCIA'] ?? null),
            'observacao' => $this->texto($cabecalho['OBSERVACAO'] ?? null),
            'itens' => $itens,
            'alertas' => [
                'pedido_ja_existe' => $pedidoJaExiste,
                'cliente_cadastrado' => $clienteExistente !== null,
                'regiao_encontrada' => $regiaoSugerida !== null,
            ],
        ];
    }

    private function buscarClienteLocalPorCodigoExterno(?string $codigoExterno): ?Cliente
    {
        if ($codigoExterno === null || $codigoExterno === '') {
            return null;
        }

        return Cliente::query()
            ->whereRaw('TRIM(codigo_externo) = ?', [$codigoExterno])
            ->first();
    }

    private function conectar(): PDO
    {
        if (! config('database.connections.firebird.database')) {
            throw new RuntimeException('Caminho do banco Firebird não configurado.');
        }

        $host = config('database.connections.firebird.host');
        $port = config('database.connections.firebird.port');
        $database = config('database.connections.firebird.database');
        $charset = config('database.connections.firebird.charset', 'WIN1252');

        $dsn = "firebird:dbname={$host}/{$port}:{$database};charset={$charset}";

        return new PDO(
            $dsn,
            config('database.connections.firebird.username'),
            config('database.connections.firebird.password'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    private function sugerirRegiao(?string $bairro, ?string $cidade): ?Regiao
    {
        $termos = collect([$bairro, $cidade])->filter();

        foreach ($termos as $termo) {
            $normalizado = Str::lower(Str::ascii($termo));

            $regiao = Regiao::where('ativo', true)
                ->get()
                ->first(function (Regiao $r) use ($normalizado) {
                    return Str::lower(Str::ascii($r->nome)) === $normalizado;
                });

            if ($regiao) {
                return $regiao;
            }
        }

        return null;
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $str = trim((string) $valor);

        if ($str === '') {
            return null;
        }

        if (! mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
        }

        return Str::squish($str);
    }
}