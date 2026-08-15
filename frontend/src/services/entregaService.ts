import { api } from '@/services/api'

import type {
  Entrega,
  EntregaForm,
  PrioridadeEntrega,
  StatusEntrega,
} from '@/types/Entrega'

interface EntregasResponse {
  data: Entrega[]

  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

interface EntregaResponse {
  data: Entrega
}

export interface EntregaHistoricoItem {
  entrega_item_id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  comprado: number
  entregue: number
  saldo_restante: number
  pendente: number
  em_carrada: number
  nao_enviado: number
}

export interface EntregaHistoricoTentativaItem {
  entrega_item_id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  programado: number
  entregue: number
  nao_entregue: number
  observacao: string | null
}

export interface EntregaHistoricoTentativa {
  tentativa_id: number
  saida_id: number
  data_hora: string | null
  data_saida: string | null
  resultado: 'COMPLETA' | 'PARCIAL' | 'NAO_ENTREGUE'
  motivo_nao_entrega: string | null
  observacao: string | null

  veiculo: {
    id: number
    nome: string
    placa: string | null
  } | null

  itens: EntregaHistoricoTentativaItem[]
}

export interface EntregaHistoricoPendencia {
  id: number
  entrega_item_id: number
  descricao_produto: string
  unidade: string | null
  quantidade_original: number
  quantidade_resolvida: number
  saldo: number
  status:
    | 'ABERTA'
    | 'PROGRAMADA'
    | 'RESOLVIDA'
    | 'CANCELADA'
  motivo: string | null
  observacao: string | null
  data_abertura: string | null
  data_resolucao: string | null
}

export interface EntregaHistorico {
  entrega: {
    id: number
    numero_pedido: string | null
    cliente_nome: string
    endereco: string
    status: StatusEntrega
  }

  itens: EntregaHistoricoItem[]
  tentativas: EntregaHistoricoTentativa[]
  pendencias: EntregaHistoricoPendencia[]
}

interface EntregaHistoricoResponse {
  data: EntregaHistorico
}

export interface EntregaFiltros {
  busca?: string
  regiao_id?: number | null
  status?: StatusEntrega | ''
  prioridade?: PrioridadeEntrega | ''
  page?: number
}

export const entregaService = {
  async listar(filtros: EntregaFiltros = {}) {
    const response = await api.get<EntregasResponse>(
      '/api/entregas',
      {
        params: {
          busca: filtros.busca || undefined,
          regiao_id: filtros.regiao_id || undefined,
          status: filtros.status || undefined,
          prioridade: filtros.prioridade || undefined,
          page: filtros.page ?? 1,
        },
      },
    )

    return response.data
  },

  async buscar(id: number) {
    const response = await api.get<EntregaResponse>(
      `/api/entregas/${id}`,
    )

    return response.data.data
  },

  async buscarHistorico(id: number) {
    const response =
      await api.get<EntregaHistoricoResponse>(
        `/api/entregas/${id}/historico`,
      )

    return response.data.data
  },

  async criar(dados: EntregaForm) {
    const response = await api.post<EntregaResponse>(
      '/api/entregas',
      dados,
    )

    return response.data.data
  },

  async atualizar(
    id: number,
    dados: Partial<EntregaForm>,
  ) {
    const response = await api.patch<EntregaResponse>(
      `/api/entregas/${id}`,
      dados,
    )

    return response.data.data
  },

  async cancelar(id: number) {
    await api.delete(`/api/entregas/${id}`)
  },
}
