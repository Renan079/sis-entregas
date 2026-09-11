import { api } from '@/services/api'
import type {
  Usuario,
  UsuarioAtualizacao,
  UsuarioListaResponse,
  UsuarioSenha,
} from '@/types/Usuario'

export const usuarioService = {
  async listar(
    busca = '',
    page = 1,
    perPage = 10,
  ): Promise<UsuarioListaResponse> {
    const response = await api.get('/api/usuarios', {
      params: {
        busca: busca || undefined,
        page,
        per_page: perPage,
      },
    })

    return response.data
  },

  async atualizar(
    id: number,
    dados: UsuarioAtualizacao,
  ): Promise<Usuario> {
    const response = await api.put(`/api/usuarios/${id}`, dados)

    return response.data.user
  },

  async redefinirSenha(
    id: number,
    dados: UsuarioSenha,
  ): Promise<string> {
    const response = await api.put(`/api/usuarios/${id}/senha`, dados)

    return response.data.message
  },
}
