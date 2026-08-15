import { api } from '@/services/api'

import type {
  PendenciaFiltros,
  PendenciaListResponse,
  ResolverPendenciaManualPayload,
  ResolverPendenciaManualResponse,
} from '@/types/Pendencia'

interface ResolverPendenciaManualApiResponse {
  data: ResolverPendenciaManualResponse
}

export const pendenciaService = {
  async listar(
    filtros: PendenciaFiltros = {},
  ): Promise<PendenciaListResponse> {
    const response =
      await api.get<PendenciaListResponse>(
        '/api/pendencias',
        {
          params: {
            busca:
              filtros.busca || undefined,

            status:
              filtros.status || undefined,

            regiao_id:
              filtros.regiao_id || undefined,

            data_inicio:
              filtros.data_inicio || undefined,

            data_fim:
              filtros.data_fim || undefined,

            per_page:
              filtros.per_page ?? 10,

            page:
              filtros.page ?? 1,
          },
        },
      )

    return response.data
  },

  async resolverManualmente(
    pendenciaId: number,
    dados: ResolverPendenciaManualPayload,
  ): Promise<ResolverPendenciaManualResponse> {
    const response =
      await api.post<ResolverPendenciaManualApiResponse>(
        `/api/pendencias/${pendenciaId}/resolver-manualmente`,
        dados,
      )

    return response.data.data
  },
}
