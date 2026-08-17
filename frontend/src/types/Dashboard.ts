export interface DashboardResumo {
  entregas_realizadas_mes: number
  aguardando_programacao: number

  carradas_hoje: number
  carradas_planejadas_hoje: number
  carradas_em_andamento_hoje: number
  carradas_finalizadas_hoje: number

  reentregas_abertas: number
  pedidos_com_problema: number
}

export interface DashboardRegiao {
  regiao_id: number | null
  nome: string
  total: number
}

export interface DashboardEntrega {
  id: number
  numero_pedido: string | null
  cliente_nome: string
  endereco: string
  status: string
  prioridade: string

  regiao: {
    id: number | null
    nome: string
  }

  total_itens: number
  criada_em: string | null
}

export interface DashboardPendencia {
  id: number
  entrega_id: number
  numero_pedido: string | null
  cliente_nome: string

  produto: {
    codigo: string | null
    descricao: string
    unidade: string | null
  }

  saldo_pendente: number

  regiao: {
    id: number | null
    nome: string
  }

  data_abertura: string | null
  dias_aberta: number
}

export type DashboardStatusSaida =
  | 'PLANEJADA'
  | 'EM_ANDAMENTO'
  | 'FINALIZADA'
  | 'CANCELADA'

export interface DashboardCarrada {
  id: number
  data_saida: string
  status: DashboardStatusSaida
  regiao_principal: string | null
  rota: string[]

  veiculo: {
    nome: string
    placa: string | null
  } | null

  total_entregas: number
  total_reentregas: number
}

export interface DashboardData {
  resumo: DashboardResumo

  entregas_por_regiao: DashboardRegiao[]
  proximas_entregas: DashboardEntrega[]
  pendencias_atencao: DashboardPendencia[]
  carradas_hoje: DashboardCarrada[]

  periodo: {
    inicio_mes: string
    fim_mes: string
    atualizado_em: string
  }
}
