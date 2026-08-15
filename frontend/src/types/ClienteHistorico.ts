export type StatusEntregaCliente =
  | 'PENDENTE'
  | 'PROGRAMADA'
  | 'EM_SAIDA'
  | 'PARCIAL'
  | 'CONCLUIDA'
  | 'NAO_ENTREGUE'
  | 'CANCELADA'

export interface ClienteHistoricoResumo {
  total: number
  pendentes: number
  programadas: number
  em_saida: number
  parciais: number
  concluidas: number
  nao_entregues: number
  canceladas: number
}

export interface ClienteHistoricoItem {
  id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  quantidade_solicitada: number
}

export interface ClienteHistoricoEntrega {
  id: number
  numero_pedido: string | null
  status: StatusEntregaCliente
  prioridade: string
  endereco: string
  referencia: string | null
  observacao: string | null

  regiao: {
    id: number
    nome: string
  } | null

  itens: ClienteHistoricoItem[]
  created_at: string
}

export interface ClienteHistorico {
  cliente: {
    id: number
    codigo_externo: string | null
    nome: string
    apelido: string | null
    telefone: string | null
    ativo: boolean
  }

  resumo: ClienteHistoricoResumo

  entregas: ClienteHistoricoEntrega[]

  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
