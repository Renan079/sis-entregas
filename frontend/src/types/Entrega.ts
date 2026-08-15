export type StatusEntrega =
  | 'PENDENTE'
  | 'PROGRAMADA'
  | 'EM_SAIDA'
  | 'PARCIAL'
  | 'CONCLUIDA'
  | 'NAO_ENTREGUE'
  | 'CANCELADA'

export type PrioridadeEntrega =
  | 'BAIXA'
  | 'NORMAL'
  | 'ALTA'
  | 'URGENTE'

export interface EntregaCliente {
  id: number
  nome: string
  apelido: string | null
}

export interface EntregaRegiao {
  id: number
  nome: string
  cidade: string | null
}

export interface EntregaItem {
  id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  quantidade_solicitada: string | number
  observacao: string | null
}

export interface Entrega {
  id: number
  numero_pedido: string | null

  cliente: EntregaCliente | null
  regiao: EntregaRegiao

  cliente_nome: string
  cliente_apelido: string | null
  cliente_telefone: string | null

  endereco: string
  referencia: string | null

  prioridade: PrioridadeEntrega
  status: StatusEntrega

  observacao: string | null

  itens: EntregaItem[]

  created_at: string
  updated_at: string
}

export interface EntregaItemForm {
  codigo_produto?: string | null
  descricao_produto: string
  unidade?: string | null
  quantidade_solicitada: number
  observacao?: string | null
}

export interface EntregaForm {
  numero_pedido?: string | null

  cliente_id?: number | null
  regiao_id: number

  cliente_nome: string
  cliente_apelido?: string | null
  cliente_telefone?: string | null

  endereco: string
  referencia?: string | null

  prioridade?: PrioridadeEntrega
  observacao?: string | null

  itens: EntregaItemForm[]
}