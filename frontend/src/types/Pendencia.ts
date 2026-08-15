export type StatusPendencia =
  | 'ABERTA'
  | 'PROGRAMADA'
  | 'RESOLVIDA'
  | 'CANCELADA'

export type MotivoDevolucao =
  | 'CLIENTE_RECUSOU'
  | 'CLIENTE_DESISTIU'
  | 'PRODUTO_INCORRETO'
  | 'PRODUTO_AVARIADO'
  | 'QUANTIDADE_EXCEDENTE'
  | 'OUTRO'

export interface PendenciaRegiao {
  id: number | null
  nome: string
}

export interface PendenciaProduto {
  codigo: string | null
  descricao: string
  unidade: string | null
}

export interface PendenciaCarradaAtual {
  id: number
  status: 'PLANEJADA' | 'EM_ANDAMENTO'
}

export interface Pendencia {
  id: number
  entrega_id: number
  entrega_item_id: number

  numero_pedido: string | null
  cliente_nome: string
  endereco: string

  regiao: PendenciaRegiao
  produto: PendenciaProduto

  quantidade_original: number
  quantidade_resolvida: number
  saldo_pendente: number

  status: StatusPendencia
  motivo: string | null
  observacao: string | null

  data_abertura: string | null
  data_resolucao: string | null

  carrada_atual: PendenciaCarradaAtual | null

  pode_resolver_manualmente: boolean
}

export interface PendenciaResumo {
  abertas: number
  programadas: number
  resolvidas_30_dias: number
  total_30_dias: number
}

export interface PendenciaMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PendenciaFiltros {
  busca?: string
  status?: StatusPendencia | ''
  regiao_id?: number | null
  data_inicio?: string
  data_fim?: string
  per_page?: number
  page?: number
}

export interface PendenciaListResponse {
  data: Pendencia[]
  resumo: PendenciaResumo
  meta: PendenciaMeta
}

export interface ResolverPendenciaManualPayload {
  motivo_devolucao: MotivoDevolucao
  observacao?: string | null
}

export interface ResolverPendenciaManualResponse {
  id: number
  status: StatusPendencia
  quantidade_original: number
  quantidade_resolvida: number
  saldo_pendente: number
  data_resolucao: string | null
}
