import { api } from '@/services/api'
import type { Cliente, ClienteForm } from '@/types/Cliente'

interface ClientesResponse {
  data: Cliente[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

interface ClienteResponse {
  data: Cliente
}

export const clienteService = {
  async listar(busca = '', page = 1) {
    const response = await api.get<ClientesResponse>('/api/clientes', {
      params: {
        busca,
        page,
      },
    })

    return response.data
  },

  async buscar(id: number) {
    const response = await api.get<ClienteResponse>(
      `/api/clientes/${id}`,
    )

    return response.data.data
  },

  async criar(dados: ClienteForm) {
    const response = await api.post<ClienteResponse>(
      '/api/clientes',
      dados,
    )

    return response.data.data
  },

  async atualizar(id: number, dados: Partial<ClienteForm>) {
    const response = await api.patch<ClienteResponse>(
      `/api/clientes/${id}`,
      dados,
    )

    return response.data.data
  },

  async desativar(id: number) {
    await api.delete(`/api/clientes/${id}`)
  },
}