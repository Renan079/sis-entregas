export interface RelatorioFiltros {
  inicio: string
  fim: string
  regiao_id: number | null
  status: string | null
}

export interface RelatorioResumo {
  total_entregas: number
  concluidas: number
  parciais: number
  nao_entregues: number
  canceladas: number
  pendencias_abertas: number
  pendencias_programadas: number
  quantidade_devolvida: number
}

export interface RelatorioStatus {
  status: string
  total: number
}

export interface RelatorioRegiao {
  regiao_id: number | null
  regiao_nome: string
  total: number
  concluidas: number
  parciais: number
  nao_entregues: number
  taxa_conclusao: number
}

export interface RelatorioPendencias {
  total_criadas: number
  abertas: number
  programadas: number
  resolvidas: number
  canceladas: number
}

export interface RelatorioDevolucaoMotivo {
  motivo: string
  registros: number
  quantidade: number
}

export interface RelatorioDevolucoes {
  quantidade_total: number
  registros: number
  por_motivo: RelatorioDevolucaoMotivo[]
}

export interface RelatorioEntregaRecente {
  id: number
  numero_pedido: string
  cliente_nome: string
  regiao_nome: string
  status: string
  data: string | null
}

export interface RelatorioResponse {
  periodo: {
    inicio: string
    fim: string
  }
  filtros: {
    regiao_id: number | null
    status: string | null
  }
  opcoes: {
    regioes: Array<{
      id: number
      nome: string
    }>
  }
  resumo: RelatorioResumo
  entregas_por_status: RelatorioStatus[]
  entregas_por_regiao: RelatorioRegiao[]
  pendencias: RelatorioPendencias
  devolucoes: RelatorioDevolucoes
  entregas_recentes: RelatorioEntregaRecente[]
  gerado_em: string
}
