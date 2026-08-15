export interface Regiao {
  id: number
  nome: string
  cidade: string | null
  observacao: string | null
  ativo: boolean
  created_at: string
  updated_at: string
}

export interface RegiaoForm {
  nome: string
  cidade?: string | null
  observacao?: string | null
  ativo?: boolean
}