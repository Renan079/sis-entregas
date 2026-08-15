import { api } from '@/services/api'
import type { Veiculo } from '@/types/Veiculo'

interface VeiculosResponse {
  data: Veiculo[]

  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const veiculoService = {
  async listar() {
    const response = await api.get<VeiculosResponse>(
      '/api/veiculos',
    )

    return response.data
  },
}