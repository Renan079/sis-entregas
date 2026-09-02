import { api } from '@/services/api'

import type {
  RelatorioFiltros,
  RelatorioResponse,
} from '@/types/Relatorio.ts'

interface ApiResponse {
  data: RelatorioResponse
}

export const relatorioService = {
  async buscar(
    filtros: RelatorioFiltros,
  ): Promise<RelatorioResponse> {
    const params = {
      inicio: filtros.inicio,
      fim: filtros.fim,
      regiao_id:
        filtros.regiao_id || undefined,
      status:
        filtros.status || undefined,
    }

    const response =
      await api.get<ApiResponse>(
        '/api/relatorios',
        { params },
      )

    return response.data.data
  },
}
