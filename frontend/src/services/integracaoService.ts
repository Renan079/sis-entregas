import { api } from './api';

export interface PedidoExternoItem {
  codigo_produto: string | null;
  descricao_produto: string;
  unidade: string;
  quantidade_solicitada: number;
  observacao: string | null;
}

export interface PedidoExternoResponse {
  numero_pedido: string;
  data_pedido: string | null;
  cliente_id: number | null;
  cliente: {
    codigo_externo: string | null;
    nome: string;
    apelido: string | null;
    telefone: string | null;
  };
  regiao_id: number | null;
  endereco: string;
  referencia: string | null;
  observacao: string | null;
  itens: PedidoExternoItem[];
  alertas: {
    pedido_ja_existe: boolean;
    cliente_cadastrado: boolean;
    regiao_encontrada: boolean;
  };
}

export interface CadastrarClienteExternoPayload {
  codigo_externo: string | null;
  nome: string;
  apelido?: string | null;
  telefone?: string | null;
}

export interface CadastrarClienteExternoResponse {
  cliente: {
    id: number;
    codigo_externo: string | null;
    nome: string;
    apelido: string | null;
    telefone: string | null;
    ativo: boolean;
  };
  criado: boolean;
}

export async function buscarPedidoExterno(numero: string): Promise<PedidoExternoResponse> {
  const { data } = await api.get<PedidoExternoResponse | { data: PedidoExternoResponse }>(
    `/api/integracao/pedidos/${encodeURIComponent(numero.trim())}`
  );

  if (data && typeof data === 'object' && 'data' in data && data.data) {
    return data.data;
  }

  return data as PedidoExternoResponse;
}

export async function cadastrarClienteExterno(
  payload: CadastrarClienteExternoPayload
): Promise<CadastrarClienteExternoResponse> {
  const { data } = await api.post<CadastrarClienteExternoResponse>(
    '/api/integracao/clientes',
    payload
  );

  return data;
}