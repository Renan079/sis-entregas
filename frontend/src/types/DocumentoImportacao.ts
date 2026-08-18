export interface DocumentoImportacaoItem {
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  quantidade_solicitada: number
  observacao: string | null
}

export interface DocumentoImportacaoCliente {
  codigo_externo: string | null
  nome: string | null
  apelido: string | null
  telefone: string | null
}

export interface DocumentoImportacaoPreview {
  documento: {
    id: number
    nome_arquivo: string
    status: 'AGUARDANDO_CONFIRMACAO'
  }

  dados: {
    numero_pedido: string | null
    data_documento: string | null
    cliente: DocumentoImportacaoCliente
    endereco: string | null
    referencia: string | null
    prioridade: 'BAIXA' | 'NORMAL' | 'ALTA' | 'URGENTE'
    observacao: string | null
    itens: DocumentoImportacaoItem[]
    avisos: string[]
  }

  sugestoes: {
    cliente: {
      id: number
      codigo_externo: string | null
      nome: string
      apelido: string | null
      telefone: string | null
    } | null

    regiao: {
      id: number
      nome: string
      cidade: string | null
    } | null

    pedido_ja_existe: boolean
  }
}

export interface ConfirmarImportacaoPayload {
  numero_pedido: string
  cliente_id: number | null

  cliente: {
    codigo_externo: string | null
    nome: string
    apelido: string | null
    telefone: string | null
  }

  regiao_id: number
  endereco: string
  referencia: string | null
  prioridade: 'BAIXA' | 'NORMAL' | 'ALTA' | 'URGENTE'
  observacao: string | null
  itens: DocumentoImportacaoItem[]
}

export interface ImportacaoConfirmada {
  entrega_id: number
  numero_pedido: string
  status: 'PENDENTE'
}
