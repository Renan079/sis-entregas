export interface Cliente {
  id: number
  codigo_externo: string | null
  nome: string
  apelido: string | null
  telefone: string | null
  ativo: boolean
  created_at: string
  updated_at: string
}

export interface ClienteForm {
  codigo_externo?: string | null
  nome: string
  apelido?: string | null
  telefone?: string | null
  ativo?: boolean
}