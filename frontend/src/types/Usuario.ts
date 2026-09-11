export type PerfilUsuario = 'ADMIN' | 'OPERADOR'

export interface Usuario {
  id: number
  name: string
  email: string
  perfil: PerfilUsuario
  ativo: boolean
  created_at: string
  updated_at: string
}

export interface UsuarioResumo {
  total: number
  ativos: number
  admins: number
  operadores: number
}

export interface UsuarioListaResponse {
  data: Usuario[]
  resumo: UsuarioResumo
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface UsuarioAtualizacao {
  name: string
  email: string
  perfil: PerfilUsuario
  ativo: boolean
}

export interface UsuarioSenha {
  password: string
  password_confirmation: string
}
