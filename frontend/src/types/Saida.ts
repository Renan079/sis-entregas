export type StatusSaida =
  | 'PLANEJADA'
  | 'EM_ANDAMENTO'
  | 'FINALIZADA'
  | 'CANCELADA'

export interface SaidaRegiao {
  id: number
  nome: string
  cidade: string | null
}

export interface SaidaVeiculo {
  id: number
  nome: string
  placa: string | null
  tipo: string | null
  status: string
}

export interface SaidaRotaParada {
  regiao_id: number | null
  nome: string
}

export interface SaidaItemProgramado {
  entrega_item_id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  quantidade_solicitada: string | number
  quantidade_programada: string | number
}

export interface SaidaEntrega {
  id: number
  numero_pedido: string | null
  cliente_nome: string
  endereco: string
  regiao_nome: string,
  status: string
  prioridade: string
  ordem: number | null
  resultado_registrado: boolean

  itens_programados: SaidaItemProgramado[]
}

export interface Saida {
  id: number
  data_saida: string
  status: StatusSaida
  observacao: string | null
  rota: SaidaRotaParada[]

  regiao_principal: SaidaRegiao | null
  veiculo: SaidaVeiculo | null

  entregas: SaidaEntrega[]
  total_entregas: number

  created_at: string
  updated_at: string
}

export interface SaidaForm {
  data_saida: string
  regiao_principal_id?: number | null
  veiculo_id?: number | null
  rota: SaidaRotaParada[]
  observacao?: string | null
}

export interface ItemDisponivelCarrada {
  entrega_item_id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null

  quantidade_solicitada: number
  quantidade_disponivel: number
}

export interface EntregaDisponivelCarrada {
  entrega_id: number
  numero_pedido: string | null
  cliente_nome: string
  endereco: string

  itens: ItemDisponivelCarrada[]
}

export interface ItemProgramacaoCarrada {
  entrega_item_id: number
  quantidade_programada: number
}

export type DestinoNaoEntregue =
  | 'REENTREGA'
  | 'DEVOLVIDO'

export type MotivoDevolucao =
  | 'CLIENTE_RECUSOU'
  | 'CLIENTE_DESISTIU'
  | 'PRODUTO_INCORRETO'
  | 'PRODUTO_AVARIADO'
  | 'QUANTIDADE_EXCEDENTE'
  | 'OUTRO'

export interface ItemRetornoCarrada {
  entrega_item_id: number
  quantidade_entregue: number
  destino_nao_entregue?: DestinoNaoEntregue | null
  motivo_devolucao?: MotivoDevolucao | null
  observacao?: string | null
}

export interface ResultadoEntregaCarrada {
  id: number
  entrega_id: number
  saida_id: number

  resultado:
    | 'COMPLETA'
    | 'PARCIAL'
    | 'NAO_ENTREGUE'

  motivo_nao_entrega: string | null
  observacao: string | null
  data_hora: string

  itens: {
    id: number
    entrega_item_id: number
    quantidade_entregue: number
    observacao: string | null
  }[]
}