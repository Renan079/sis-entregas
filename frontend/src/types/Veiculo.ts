export type StatusVeiculo =
  | 'DISPONIVEL'
  | 'INDISPONIVEL'
  | 'MANUTENCAO'

export interface Veiculo {
  id: number
  nome: string
  placa: string | null
  tipo: string | null
  status: StatusVeiculo
  observacao: string | null
  ativo: boolean
  created_at: string
  updated_at: string
}