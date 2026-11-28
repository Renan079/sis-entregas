import type { Saida, SaidaEntrega, SaidaItemProgramado } from '@/types/Saida'

export interface PendenciaCarradaRomaneio {
  id: number | string
  numero_pedido?: string | null
  cliente_nome: string
  endereco: string
  ponto_referencia?: string | null
  referencia?: string | null
  regiao_nome?: string | null
  prioridade?: string | null
  observacao?: string | null
  observacoes?: string | null
  itens_programados?: SaidaItemProgramado[]
  itens?: SaidaItemProgramado[]
}

interface ResumoProduto {
  chave: string
  codigo: string
  descricao: string
  unidade: string
  quantidadeTotal: number
}

function escaparHtml(valor: unknown): string {
  return String(valor ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

function primeiroTexto(...valores: Array<unknown>): string {
  for (const valor of valores) {
    if (typeof valor === 'string' && valor.trim().length > 0) {
      return valor.trim()
    }
  }
  return ''
}

function extrairPontoReferencia(entrega: SaidaEntrega | PendenciaCarradaRomaneio): string {
  const reg = entrega as unknown as Record<string, unknown>
  const cliente =
    typeof reg.cliente === 'object' && reg.cliente !== null
      ? (reg.cliente as Record<string, unknown>)
      : null

  return primeiroTexto(
    reg.ponto_referencia,
    reg.referencia,
    reg.cliente_ponto_referencia,
    reg.cliente_referencia,
    cliente?.ponto_referencia,
    cliente?.referencia,
  )
}

function extrairObservacaoEntrega(entrega: SaidaEntrega | PendenciaCarradaRomaneio): string {
  const reg = entrega as unknown as Record<string, unknown>
  const pedido =
    typeof reg.pedido === 'object' && reg.pedido !== null
      ? (reg.pedido as Record<string, unknown>)
      : null

  return primeiroTexto(
    reg.observacao,
    reg.observacoes,
    reg.descricao,
    reg.descricao_entrega,
    pedido?.observacao,
  )
}

function formatarQuantidade(qtd: string | number): string {
  const num = Number(qtd)
  if (Number.isNaN(num)) {
    return escaparHtml(qtd)
  }
  return Number.isInteger(num) ? String(num) : num.toLocaleString('pt-BR')
}

function normalizarItensEntrega(
  entrega: SaidaEntrega | PendenciaCarradaRomaneio,
): SaidaItemProgramado[] {
  if (Array.isArray(entrega.itens_programados) && entrega.itens_programados.length > 0) {
    return entrega.itens_programados
  }
  if ('itens' in entrega && Array.isArray(entrega.itens)) {
    return entrega.itens
  }
  return []
}

function consolidarProdutos(
  entregas: SaidaEntrega[],
  pendencias: PendenciaCarradaRomaneio[],
): ResumoProduto[] {
  const mapa = new Map<string, ResumoProduto>()

  const acumularItens = (itens: SaidaItemProgramado[]) => {
    for (const item of itens) {
      const qtd = Number(item.quantidade_programada) || 0
      if (qtd <= 0) continue

      const codigo = (item.codigo_produto ?? '').trim()
      const descricao = item.descricao_produto.trim()
      const unidade = (item.unidade ?? 'UN').trim()
      const chave = `${codigo.toLowerCase()}||${descricao.toLowerCase()}||${unidade.toLowerCase()}`

      const atual = mapa.get(chave)
      if (atual) {
        atual.quantidadeTotal += qtd
      } else {
        mapa.set(chave, {
          chave,
          codigo: codigo || '-',
          descricao,
          unidade,
          quantidadeTotal: qtd,
        })
      }
    }
  }

  for (const entrega of entregas) {
    acumularItens(normalizarItensEntrega(entrega))
  }

  for (const pendencia of pendencias) {
    acumularItens(normalizarItensEntrega(pendencia))
  }

  return Array.from(mapa.values()).sort((a, b) => a.descricao.localeCompare(b.descricao, 'pt-BR'))
}

function renderBlocoEntregaHtml(
  tituloTipo: string,
  codigoRef: string,
  cliente: string,
  endereco: string,
  pontoReferencia: string,
  regiao: string,
  prioridade: string,
  itens: SaidaItemProgramado[],
  observacaoEntrega?: string | null,
): string {
  const linhasItens =
    itens.length > 0
      ? itens
          .map(
            (item) => `
              <tr>
                <td class="col-check"><span class="box"></span></td>
                <td class="col-cod">${escaparHtml(item.codigo_produto || '-')}</td>
                <td class="col-desc">${escaparHtml(item.descricao_produto)}</td>
                <td class="col-qtd"><strong>${formatarQuantidade(item.quantidade_programada)}</strong> ${escaparHtml(item.unidade || '')}</td>
                <td class="col-obs"></td>
              </tr>
            `,
          )
          .join('')
      : `
          <tr>
            <td colspan="5" class="sem-itens">Sem itens programados listados neste registro.</td>
          </tr>
        `

  const obsLimpa = primeiroTexto(observacaoEntrega)
  const refLimpa = primeiroTexto(pontoReferencia)

  return `
    <section class="bloco-entrega">
      <div class="entrega-topo">
        <div>
          <span class="badge-tipo">${escaparHtml(tituloTipo)}</span>
          <strong class="cliente-nome">${escaparHtml(cliente)}</strong>
          ${codigoRef ? `<span class="meta-inline">• Pedido: <strong>#${escaparHtml(codigoRef)}</strong></span>` : ''}
        </div>
        <div class="entrega-meta-dir">
          ${regiao ? `<span><strong>Região:</strong> ${escaparHtml(regiao)}</span>` : ''}
          ${prioridade ? `<span><strong>Prioridade:</strong> ${escaparHtml(prioridade)}</span>` : ''}
        </div>
      </div>

      <div class="entrega-endereco">
        <div><strong>Endereço:</strong> ${escaparHtml(endereco || 'Não informado')}</div>
        ${refLimpa ? `<div class="ref-extra"><strong>Ponto de referência:</strong> ${escaparHtml(refLimpa)}</div>` : ''}
        ${obsLimpa ? `<div class="obs-extra"><strong>Observação da entrega:</strong> ${escaparHtml(obsLimpa)}</div>` : ''}
      </div>

      <table class="tabela-itens">
        <thead>
          <tr>
            <th class="col-check">OK</th>
            <th class="col-cod">Cód.</th>
            <th class="col-desc">Produto</th>
            <th class="col-qtd">Qtd.</th>
            <th class="col-obs">Posição no carro / Obs.</th>
          </tr>
        </thead>
        <tbody>
          ${linhasItens}
        </tbody>
      </table>
    </section>
  `
}

export function imprimirRomaneioCarrada(
  saida: Saida,
  pendencias: PendenciaCarradaRomaneio[] = [],
): void {
  const entregasOrdenadas = [...(saida.entregas ?? [])].sort((a, b) => {
    const ordemA = a.ordem ?? Number.MAX_SAFE_INTEGER
    const ordemB = b.ordem ?? Number.MAX_SAFE_INTEGER
    if (ordemA !== ordemB) return ordemA - ordemB
    return a.id - b.id
  })

  const resumoProdutos = consolidarProdutos(entregasOrdenadas, pendencias)
  const emitidoEm = new Date().toLocaleString('pt-BR')

  const veiculoTexto = saida.veiculo
    ? `${saida.veiculo.nome}${saida.veiculo.placa ? ` (${saida.veiculo.placa})` : ''}`
    : 'Não informado'

  const regiaoTexto = saida.regiao_principal
    ? `${saida.regiao_principal.nome}${saida.regiao_principal.cidade ? ` - ${saida.regiao_principal.cidade}` : ''}`
    : 'Não informada'

  const rotaTexto =
    Array.isArray(saida.rota) && saida.rota.length > 0
      ? saida.rota.map((parada) => parada.nome).join(' → ')
      : null

  const linhasResumo =
    resumoProdutos.length > 0
      ? resumoProdutos
          .map(
            (prod) => `
              <tr>
                <td class="col-check"><span class="box"></span></td>
                <td class="col-cod">${escaparHtml(prod.codigo)}</td>
                <td class="col-desc">${escaparHtml(prod.descricao)}</td>
                <td class="col-qtd"><strong>${formatarQuantidade(prod.quantidadeTotal)}</strong> ${escaparHtml(prod.unidade)}</td>
              </tr>
            `,
          )
          .join('')
      : `
          <tr>
            <td colspan="4" class="sem-itens">Nenhum item programado nesta carrada.</td>
          </tr>
        `

  const blocosEntregas = entregasOrdenadas
    .map((entrega, idx) => {
      const rotuloOrdem = entrega.ordem != null ? `Entrega #${entrega.ordem}` : `Entrega ${idx + 1}`
      return renderBlocoEntregaHtml(
        rotuloOrdem,
        entrega.numero_pedido ?? String(entrega.id),
        entrega.cliente_nome,
        entrega.endereco,
        extrairPontoReferencia(entrega),
        entrega.regiao_nome,
        entrega.prioridade,
        normalizarItensEntrega(entrega),
        extrairObservacaoEntrega(entrega),
      )
    })
    .join('')

  const blocosPendencias = pendencias
    .map((pend, idx) =>
      renderBlocoEntregaHtml(
        `Pendência ${idx + 1}`,
        pend.numero_pedido ?? String(pend.id),
        pend.cliente_nome,
        pend.endereco,
        extrairPontoReferencia(pend),
        pend.regiao_nome ?? '',
        pend.prioridade ?? '',
        normalizarItensEntrega(pend),
        extrairObservacaoEntrega(pend),
      ),
    )
    .join('')

  const html = `
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8" />
      <title>Romaneio - Carrada #${escaparHtml(saida.id)}</title>
      <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
          font-family: Arial, Helvetica, sans-serif;
          color: #111827;
          margin: 0;
          padding: 16px;
          font-size: 12px;
          line-height: 1.35;
        }
        .barra-acao {
          display: flex;
          justify-content: space-between;
          align-items: center;
          background: #f3f4f6;
          border: 1px solid #d1d5db;
          padding: 10px 14px;
          border-radius: 6px;
          margin-bottom: 16px;
        }
        .barra-acao button {
          background: #111827;
          color: #fff;
          border: none;
          padding: 8px 14px;
          border-radius: 5px;
          cursor: pointer;
          font-weight: bold;
        }
        .cabecalho {
          border: 2px solid #111827;
          padding: 10px 12px;
          margin-bottom: 14px;
        }
        .cabecalho-topo {
          display: flex;
          justify-content: space-between;
          align-items: baseline;
          border-bottom: 1px solid #d1d5db;
          padding-bottom: 6px;
          margin-bottom: 8px;
        }
        .cabecalho-topo h1 {
          margin: 0;
          font-size: 18px;
        }
        .grid-info {
          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 4px 16px;
        }
        .assinaturas {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 12px;
          margin-top: 10px;
          padding-top: 8px;
          border-top: 1px dashed #9ca3af;
          font-size: 11px;
        }
        .linha-ass {
          border-bottom: 1px solid #374151;
          height: 18px;
          margin-bottom: 3px;
        }
        h2 {
          font-size: 13px;
          text-transform: uppercase;
          margin: 14px 0 6px;
          padding-bottom: 3px;
          border-bottom: 1.5px solid #111827;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: 8px;
        }
        th, td {
          border: 1px solid #9ca3af;
          padding: 5px 7px;
          text-align: left;
          vertical-align: middle;
        }
        th {
          background: #f3f4f6;
          font-size: 11px;
        }
        .col-check { width: 34px; text-align: center; }
        .col-cod { width: 75px; }
        .col-qtd { width: 95px; text-align: right; white-space: nowrap; }
        .col-obs { width: 170px; }
        .box {
          display: inline-block;
          width: 13px;
          height: 13px;
          border: 1.5px solid #111827;
        }
        .bloco-entrega {
          border: 1px solid #6b7280;
          margin-bottom: 10px;
          padding: 7px 9px;
          break-inside: avoid;
          page-break-inside: avoid;
        }
        .entrega-topo {
          display: flex;
          justify-content: space-between;
          gap: 8px;
          flex-wrap: wrap;
          margin-bottom: 4px;
        }
        .badge-tipo {
          background: #111827;
          color: #fff;
          font-size: 10px;
          font-weight: bold;
          padding: 2px 6px;
          border-radius: 3px;
          margin-right: 6px;
        }
        .cliente-nome { font-size: 13px; }
        .meta-inline { color: #374151; margin-left: 4px; }
        .entrega-meta-dir {
          display: flex;
          gap: 12px;
          font-size: 11px;
        }
        .entrega-endereco {
          font-size: 11.5px;
          margin-bottom: 6px;
          background: #f9fafb;
          padding: 5px 7px;
          border-left: 3px solid #374151;
        }
        .ref-extra {
          margin-top: 3px;
        }
        .obs-extra {
          margin-top: 4px;
          padding: 4px 6px;
          background: #fefce8;
          border-left: 2px solid #ca8a04;
        }
        .sem-itens {
          text-align: center;
          color: #6b7280;
          font-style: italic;
        }
        @media print {
          .barra-acao { display: none; }
          body { padding: 0; }
        }
      </style>
    </head>
    <body>
      <div class="barra-acao">
        <span><strong>Romaneio de Carregamento — Carrada #${escaparHtml(saida.id)}</strong> (use "Salvar como PDF" ou escolha a impressora)</span>
        <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
      </div>

      <header class="cabecalho">
        <div class="cabecalho-topo">
          <h1>ROMANEIO DE CARREGAMENTO — CARRADAS #${escaparHtml(saida.id)}</h1>
          <span>Emitido em: ${escaparHtml(emitidoEm)}</span>
        </div>
        <div class="grid-info">
          <div><strong>Data da saída:</strong> ${escaparHtml(saida.data_saida)}</div>
          <div><strong>Status:</strong> ${escaparHtml(saida.status)}</div>
          <div><strong>Veículo:</strong> ${escaparHtml(veiculoTexto)}</div>
          <div><strong>Região principal:</strong> ${escaparHtml(regiaoTexto)}</div>
          <div><strong>Total de entregas:</strong> ${escaparHtml(saida.total_entregas ?? entregasOrdenadas.length)}</div>
          ${rotaTexto ? `<div><strong>Rota:</strong> ${escaparHtml(rotaTexto)}</div>` : '<div></div>'}
        </div>
        ${saida.observacao ? `<div style="margin-top:6px;"><strong>Observação geral:</strong> ${escaparHtml(saida.observacao)}</div>` : ''}

        <div class="assinaturas">
          <div><div class="linha-ass"></div>Separou no depósito</div>
          <div><div class="linha-ass"></div>Conferiu / Carregou</div>
          <div><div class="linha-ass"></div>Motorista</div>
        </div>
      </header>

      <h2>1. Resumo para separar no estoque</h2>
      <table>
        <thead>
          <tr>
            <th class="col-check">OK</th>
            <th class="col-cod">Cód.</th>
            <th class="col-desc">Produto</th>
            <th class="col-qtd">Total</th>
          </tr>
        </thead>
        <tbody>
          ${linhasResumo}
        </tbody>
      </table>

      <h2>2. Itens por entrega e posição no carro</h2>
      ${blocosEntregas || '<p class="sem-itens">Nenhuma entrega vinculada a esta carrada.</p>'}

      ${
        blocosPendencias
          ? `<h2>3. Pendências vinculadas</h2>${blocosPendencias}`
          : ''
      }
    </body>
    </html>
  `

  const janela = window.open('', '_blank', 'width=960,height=720')
  if (!janela) {
    alert('Permita pop-ups no navegador para abrir o romaneio de carregamento.')
    return
  }

  janela.document.open()
  janela.document.write(html)
  janela.document.close()
  janela.focus()
  janela.onload = () => {
    janela.print()
  }
}