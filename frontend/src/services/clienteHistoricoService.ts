import { api } from '@/services/api'

import type {
  ClienteHistorico,
} from '@/types/ClienteHistorico.ts'

interface ClienteHistoricoResponse {
  data: ClienteHistorico
}

export const clienteHistoricoService = {
  async buscarEntregas(
    clienteId: number,
    page = 1,
    perPage = 10,
  ): Promise<ClienteHistorico> {
    const response =
      await api.get<ClienteHistoricoResponse>(
        `/api/clientes/${clienteId}/entregas`,
        {
          params: {
            page,
            per_page: perPage,
          },
        },
      )

    return response.data.data
  },
}
