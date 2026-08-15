import { api } from '@/services/api'
import type { Regiao, RegiaoForm } from '@/types/Regiao'

interface RegioesResponse {
  data: Regiao[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

interface RegiaoResponse {
  data: Regiao
}

export const regiaoService = {
  async listar(page = 1) {
    const response = await api.get<RegioesResponse>('/api/regioes', {
      params: {
        page,
      },
    })

    return response.data
  },

  async buscar(id: number) {
    const response = await api.get<RegiaoResponse>(
      `/api/regioes/${id}`,
    )

    return response.data.data
  },

  async criar(dados: RegiaoForm) {
    const response = await api.post<RegiaoResponse>(
      '/api/regioes',
      dados,
    )

    return response.data.data
  },

  async atualizar(id: number, dados: Partial<RegiaoForm>) {
    const response = await api.patch<RegiaoResponse>(
      `/api/regioes/${id}`,
      dados,
    )

    return response.data.data
  },

  async desativar(id: number) {
    await api.delete(`/api/regioes/${id}`)
  },
}