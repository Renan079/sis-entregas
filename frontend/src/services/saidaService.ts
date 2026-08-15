import { api } from '@/services/api'

import type {
  DestinoNaoEntregue,
  EntregaDisponivelCarrada,
  ItemProgramacaoCarrada,
  ItemRetornoCarrada,
  MotivoDevolucao,
  ResultadoEntregaCarrada,
  Saida,
  SaidaForm,
  SaidaRotaParada,
  StatusSaida,
} from '@/types/Saida'

interface SaidasResponse {
  data: Saida[]

  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

interface SaidaResponse {
  data: Saida
}

interface EntregaDisponivelResponse {
  data: EntregaDisponivelCarrada
}

export interface EntregaDisponivelRota {
  id: number
  numero_pedido: string | null
  cliente_nome: string
  endereco: string
  regiao_id: number
  regiao_nome: string
  status: string
  prioridade: string
}

interface EntregasDisponiveisRotaResponse {
  data: EntregaDisponivelRota[]
}

export type StatusPendenciaRota =
  | 'ABERTA'
  | 'PROGRAMADA'
  | 'RESOLVIDA'
  | 'CANCELADA'

export interface PendenciaRota {
  id: number
  entrega_id: number
  entrega_item_id: number
  numero_pedido: string | null
  cliente_nome: string
  endereco: string
  regiao_id: number
  regiao_nome: string
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  quantidade_original: number
  quantidade_resolvida: number
  saldo_pendente: number
  status: StatusPendenciaRota
  motivo: string | null
  observacao: string | null
  data_abertura: string | null

  resultado_registrado: boolean
  resultado_id: number | null
  quantidade_programada_resultado: number | null
  quantidade_entregue_resultado: number
  quantidade_devolvida_resultado: number
  quantidade_reentrega_resultado: number
  destino_restante: DestinoNaoEntregue | null
  motivo_devolucao: MotivoDevolucao | null
  observacao_resultado: string | null
  data_hora_resultado: string | null
}

interface PendenciasRotaResponse {
  data: PendenciaRota[]
}

interface ResultadoEntregaResponse {
  data: ResultadoEntregaCarrada
}

export interface ResultadoReentregaCarrada {
  id: number
  saida_id: number
  pendencia_id: number
  quantidade_programada: number
  quantidade_entregue: number
  quantidade_devolvida: number
  quantidade_reentrega: number
  destino_restante: DestinoNaoEntregue | null
  motivo_devolucao: MotivoDevolucao | null
  observacao: string | null
  data_hora: string
}

interface ResultadoReentregaResponse {
  data: ResultadoReentregaCarrada
}

export type ResultadoHistoricoCarrada =
  | 'COMPLETA'
  | 'PARCIAL'
  | 'NAO_ENTREGUE'

export type StatusPendenciaHistoricoCarrada =
  | 'ABERTA'
  | 'PROGRAMADA'
  | 'RESOLVIDA'
  | 'CANCELADA'

export interface SaidaHistoricoItem {
  entrega_item_id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  carregado: number
  entregue: number
  devolvido: number
  reentrega: number
  nao_entregue: number
  observacao: string | null
}

export interface SaidaHistoricoPendencia {
  id: number
  entrega_item_id: number
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  quantidade_original: number
  quantidade_resolvida: number
  saldo_atual: number
  status_atual: StatusPendenciaHistoricoCarrada
  motivo: string | null
  observacao: string | null
  data_abertura: string | null
  data_resolucao: string | null
}

export interface SaidaHistoricoEntrega {
  id: number
  saida_entrega_id: number
  ordem: number | null
  numero_pedido: string | null
  cliente_nome: string
  endereco: string
  resultado: ResultadoHistoricoCarrada | null
  resultado_registrado: boolean
  data_hora_resultado: string | null
  motivo_nao_entrega: string | null
  observacao_resultado: string | null
  itens: SaidaHistoricoItem[]
  pendencias_geradas: SaidaHistoricoPendencia[]
}

export interface SaidaHistoricoReentrega {
  pendencia_id: number
  entrega_id: number
  entrega_item_id: number
  numero_pedido: string | null
  cliente_nome: string
  endereco: string
  regiao_id: number | null
  regiao_nome: string
  codigo_produto: string | null
  descricao_produto: string
  unidade: string | null
  quantidade_programada: number
  quantidade_entregue: number
  quantidade_devolvida: number
  quantidade_reentrega: number
  resultado_registrado: boolean
  destino_restante: DestinoNaoEntregue | null
  motivo_devolucao: MotivoDevolucao | null
  observacao_resultado: string | null
  data_hora_resultado: string | null
  status_atual: StatusPendenciaHistoricoCarrada
  saldo_atual: number
  motivo: string | null
  observacao: string | null
  data_abertura: string | null
  data_resolucao: string | null
  adicionada_em: string | null
}

export interface SaidaHistoricoEvento {
  id: number
  tipo_evento: string
  descricao: string
  dados: Record<string, unknown> | null
  data_hora: string
  usuario_id: number | null
  origem:
    | 'TEMPO_REAL'
    | 'BACKFILL'
    | 'LEGADO_TENTATIVA'
    | string
}

export interface SaidaHistorico {
  saida: {
    id: number
    data_saida: string
    status: StatusSaida
    observacao: string | null
    rota: SaidaRotaParada[]

    regiao: {
      id: number
      nome: string
    } | null

    veiculo: {
      id: number
      nome: string
      placa: string | null
    } | null

    criada_em: string | null
  }

  resumo: {
    total_entregas: number
    total_entregas_normais: number
    total_reentregas: number
    total_atendimentos: number
    completas: number
    parciais: number
    nao_entregues: number
    sem_resultado: number
    sem_resultado_entregas: number
    sem_resultado_reentregas: number
    quantidade_pendencias_geradas: number
    quantidade_reentrega_programada: number
    quantidade_entregue_reentrega: number
    quantidade_devolvida: number
  }

  entregas: SaidaHistoricoEntrega[]
  reentregas: SaidaHistoricoReentrega[]
  eventos: SaidaHistoricoEvento[]
}

interface SaidaHistoricoResponse {
  data: SaidaHistorico
}

export interface SaidaFiltros {
  busca?: string
  status?: StatusSaida | ''
  data?: string
  page?: number
}

export const saidaService = {
  async listar(
    filtros: SaidaFiltros = {},
  ) {
    const response =
      await api.get<SaidasResponse>(
        '/api/saidas',
        {
          params: {
            busca:
              filtros.busca || undefined,

            status:
              filtros.status || undefined,

            data:
              filtros.data || undefined,

            page:
              filtros.page ?? 1,
          },
        },
      )

    return response.data
  },

  async buscar(
    id: number,
  ): Promise<Saida> {
    const response =
      await api.get<SaidaResponse>(
        `/api/saidas/${id}`,
      )

    return response.data.data
  },

  async buscarHistorico(
    id: number,
  ): Promise<SaidaHistorico> {
    const response =
      await api.get<SaidaHistoricoResponse>(
        `/api/saidas/${id}/historico`,
      )

    return response.data.data
  },

  async criar(
    dados: SaidaForm,
  ): Promise<Saida> {
    const response =
      await api.post<SaidaResponse>(
        '/api/saidas',
        dados,
      )

    return response.data.data
  },

  async buscarEntregasDisponiveis(
    saidaId: number,
  ): Promise<EntregaDisponivelRota[]> {
    const response =
      await api.get<EntregasDisponiveisRotaResponse>(
        `/api/saidas/${saidaId}/entregas-disponiveis`,
      )

    return response.data.data
  },

  async buscarPendenciasDisponiveis(
    saidaId: number,
  ): Promise<PendenciaRota[]> {
    const response =
      await api.get<PendenciasRotaResponse>(
        `/api/saidas/${saidaId}/pendencias-disponiveis`,
      )

    return response.data.data
  },

  async buscarPendenciasDaCarrada(
    saidaId: number,
  ): Promise<PendenciaRota[]> {
    const response =
      await api.get<PendenciasRotaResponse>(
        `/api/saidas/${saidaId}/pendencias`,
      )

    return response.data.data
  },

  async adicionarPendencia(
    saidaId: number,
    pendenciaId: number,
  ): Promise<void> {
    await api.post(
      `/api/saidas/${saidaId}/pendencias/${pendenciaId}`,
    )
  },

  async removerPendencia(
    saidaId: number,
    pendenciaId: number,
  ): Promise<void> {
    await api.delete(
      `/api/saidas/${saidaId}/pendencias/${pendenciaId}`,
    )
  },

  async registrarResultadoPendencia(
    saidaId: number,
    pendenciaId: number,
    dados: {
      quantidade_entregue: number
      destino_restante?: DestinoNaoEntregue | null
      motivo_devolucao?: MotivoDevolucao | null
      observacao?: string | null
    },
  ): Promise<ResultadoReentregaCarrada> {
    const response =
      await api.post<ResultadoReentregaResponse>(
        `/api/saidas/${saidaId}/pendencias/${pendenciaId}/resultado`,
        dados,
      )

    return response.data.data
  },

  async buscarItensDisponiveis(
    entregaId: number,
  ): Promise<EntregaDisponivelCarrada> {
    const response =
      await api.get<EntregaDisponivelResponse>(
        `/api/planejamento/entregas/${entregaId}/itens-disponiveis`,
      )

    return response.data.data
  },

  async adicionarEntrega(
    saidaId: number,
    entregaId: number,
    ordem?: number,
    itens: ItemProgramacaoCarrada[] = [],
  ): Promise<Saida> {
    const response =
      await api.post<SaidaResponse>(
        `/api/saidas/${saidaId}/entregas`,
        {
          entrega_id: entregaId,
          ordem,
          itens,
        },
      )

    return response.data.data
  },

  async removerEntrega(
    saidaId: number,
    entregaId: number,
  ): Promise<Saida> {
    const response =
      await api.delete<SaidaResponse>(
        `/api/saidas/${saidaId}/entregas/${entregaId}`,
      )

    return response.data.data
  },

  async iniciar(
    id: number,
  ): Promise<Saida> {
    const response =
      await api.post<SaidaResponse>(
        `/api/saidas/${id}/iniciar`,
      )

    return response.data.data
  },

  async finalizar(
    id: number,
  ): Promise<Saida> {
    const response =
      await api.post<SaidaResponse>(
        `/api/saidas/${id}/finalizar`,
      )

    return response.data.data
  },

  async cancelar(
    id: number,
  ): Promise<void> {
    await api.post(
      `/api/saidas/${id}/cancelar`,
    )
  },

  async registrarResultadoEntrega(
    saidaId: number,
    entregaId: number,
    dados: {
      itens: ItemRetornoCarrada[]
      motivo_nao_entrega?: string | null
      observacao?: string | null
    },
  ): Promise<ResultadoEntregaCarrada> {
    const response =
      await api.post<ResultadoEntregaResponse>(
        `/api/saidas/${saidaId}/entregas/${entregaId}/resultado`,
        dados,
      )

    return response.data.data
  },
}
