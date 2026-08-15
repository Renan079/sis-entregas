<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import { entregaService } from '@/services/entregaService'
import type { EntregaHistorico } from '@/services/entregaService'
import { clienteService } from '@/services/clienteService'
import { regiaoService } from '@/services/regiaoService'

import type { Cliente } from '@/types/Cliente'
import type { Regiao } from '@/types/Regiao'

import type {
  Entrega,
  EntregaForm,
  EntregaItemForm,
  PrioridadeEntrega,
  StatusEntrega,
} from '@/types/Entrega'

const entregas = ref<Entrega[]>([])
const clientes = ref<Cliente[]>([])
const regioes = ref<Regiao[]>([])

const carregando = ref(false)
const salvando = ref(false)
const erro = ref('')

const modalAberto = ref(false)
const entregaEditandoId = ref<number | null>(null)

const modalHistorico = ref(false)
const carregandoHistorico = ref(false)
const historicoEntrega = ref<EntregaHistorico | null>(null)

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const buscaCliente = ref('')

const filtros = reactive({
  busca: '',
  regiao_id: null as number | null,
  status: '' as StatusEntrega | '',
  prioridade: '' as PrioridadeEntrega | '',
})

const formulario = reactive<EntregaForm>({
  numero_pedido: '',
  cliente_id: null,
  regiao_id: 0,

  cliente_nome: '',
  cliente_apelido: '',
  cliente_telefone: '',

  endereco: '',
  referencia: '',

  prioridade: 'NORMAL',
  observacao: '',

  itens: [],
})

function novoItem(): EntregaItemForm {
  return {
    codigo_produto: '',
    descricao_produto: '',
    unidade: 'UN',
    quantidade_solicitada: 1,
    observacao: '',
  }
}

function limparFormulario() {
  formulario.numero_pedido = ''
  formulario.cliente_id = null
  formulario.regiao_id = 0

  formulario.cliente_nome = ''
  formulario.cliente_apelido = ''
  formulario.cliente_telefone = ''

  formulario.endereco = ''
  formulario.referencia = ''
  formulario.prioridade = 'NORMAL'
  formulario.observacao = ''

  formulario.itens = [novoItem()]

  buscaCliente.value = ''
  entregaEditandoId.value = null
}

async function carregarEntregas(page = 1) {
  carregando.value = true
  erro.value = ''

  try {
    const response = await entregaService.listar({
      busca: filtros.busca,
      regiao_id: filtros.regiao_id,
      status: filtros.status,
      prioridade: filtros.prioridade,
      page,
    })

    entregas.value = response.data

    paginaAtual.value = response.meta.current_page
    ultimaPagina.value = response.meta.last_page
    total.value = response.meta.total
  } catch {
    erro.value = 'Não foi possível carregar as entregas.'
  } finally {
    carregando.value = false
  }
}

async function carregarRegioes() {
  try {
    const response = await regiaoService.listar(1)

    regioes.value = response.data.filter(
      (regiao) => regiao.ativo,
    )
  } catch {
    erro.value = 'Não foi possível carregar as regiões.'
  }
}

async function pesquisarClientes() {
  try {
    const response = await clienteService.listar(
      buscaCliente.value,
      1,
    )

    clientes.value = response.data.filter(
      (cliente) => cliente.ativo,
    )
  } catch {
    erro.value = 'Não foi possível pesquisar clientes.'
  }
}

function selecionarCliente(cliente: Cliente) {
  formulario.cliente_id = cliente.id
  formulario.cliente_nome = cliente.nome
  formulario.cliente_apelido = cliente.apelido ?? ''
  formulario.cliente_telefone = cliente.telefone ?? ''

  buscaCliente.value = cliente.nome
  clientes.value = []
}

function limparClienteSelecionado() {
  formulario.cliente_id = null
  formulario.cliente_nome = ''
  formulario.cliente_apelido = ''
  formulario.cliente_telefone = ''

  buscaCliente.value = ''
  clientes.value = []
}

function novaEntrega() {
  limparFormulario()
  modalAberto.value = true
}

function editarEntrega(entrega: Entrega) {
  entregaEditandoId.value = entrega.id

  formulario.numero_pedido = entrega.numero_pedido ?? ''

  formulario.cliente_id =
    entrega.cliente?.id ?? null

  formulario.regiao_id = entrega.regiao.id

  formulario.cliente_nome = entrega.cliente_nome
  formulario.cliente_apelido =
    entrega.cliente_apelido ?? ''
  formulario.cliente_telefone =
    entrega.cliente_telefone ?? ''

  formulario.endereco = entrega.endereco
  formulario.referencia = entrega.referencia ?? ''

  formulario.prioridade = entrega.prioridade
  formulario.observacao = entrega.observacao ?? ''

  formulario.itens = entrega.itens.map((item) => ({
    codigo_produto: item.codigo_produto ?? '',
    descricao_produto: item.descricao_produto,
    unidade: item.unidade ?? '',
    quantidade_solicitada:
      Number(item.quantidade_solicitada),
    observacao: item.observacao ?? '',
  }))

  buscaCliente.value = entrega.cliente_nome

  modalAberto.value = true
}

function fecharModal() {
  modalAberto.value = false
  clientes.value = []
  limparFormulario()
}

function adicionarItem() {
  formulario.itens.push(novoItem())
}

function removerItem(index: number) {
  if (formulario.itens.length === 1) {
    return
  }

  formulario.itens.splice(index, 1)
}

async function salvarEntrega() {
  erro.value = ''

  if (!formulario.cliente_nome.trim()) {
    erro.value = 'Informe o cliente.'
    return
  }

  if (!formulario.regiao_id) {
    erro.value = 'Selecione uma região.'
    return
  }

  if (!formulario.endereco.trim()) {
    erro.value = 'Informe o endereço da entrega.'
    return
  }

  const itemInvalido = formulario.itens.some(
    (item) =>
      !item.descricao_produto.trim() ||
      Number(item.quantidade_solicitada) <= 0,
  )

  if (itemInvalido) {
    erro.value =
      'Preencha corretamente os produtos da entrega.'
    return
  }

  salvando.value = true

  const dados: EntregaForm = {
    numero_pedido:
      formulario.numero_pedido?.trim() || null,

    cliente_id: formulario.cliente_id,

    regiao_id: formulario.regiao_id,

    cliente_nome: formulario.cliente_nome.trim(),

    cliente_apelido:
      formulario.cliente_apelido?.trim() || null,

    cliente_telefone:
      formulario.cliente_telefone?.trim() || null,

    endereco: formulario.endereco.trim(),

    referencia:
      formulario.referencia?.trim() || null,

    prioridade: formulario.prioridade,

    observacao:
      formulario.observacao?.trim() || null,

    itens: formulario.itens.map((item) => ({
      codigo_produto:
        item.codigo_produto?.trim() || null,

      descricao_produto:
        item.descricao_produto.trim(),

      unidade:
        item.unidade?.trim() || null,

      quantidade_solicitada:
        Number(item.quantidade_solicitada),

      observacao:
        item.observacao?.trim() || null,
    })),
  }

  try {
    if (entregaEditandoId.value) {
      await entregaService.atualizar(
        entregaEditandoId.value,
        dados,
      )
    } else {
      await entregaService.criar(dados)
    }

    fecharModal()

    await carregarEntregas(paginaAtual.value)
  } catch {
    erro.value = 'Não foi possível salvar a entrega.'
  } finally {
    salvando.value = false
  }
}

async function cancelarEntrega(entrega: Entrega) {
  const confirmou = window.confirm(
    `Deseja cancelar a entrega do pedido ${
      entrega.numero_pedido ?? entrega.id
    }?`,
  )

  if (!confirmou) {
    return
  }

  try {
    await entregaService.cancelar(entrega.id)

    await carregarEntregas(paginaAtual.value)
  } catch {
    erro.value = 'Não foi possível cancelar a entrega.'
  }
}

async function abrirHistorico(entrega: Entrega) {
  erro.value = ''
  carregandoHistorico.value = true
  historicoEntrega.value = null
  modalHistorico.value = true

  try {
    historicoEntrega.value =
      await entregaService.buscarHistorico(entrega.id)
  } catch {
    modalHistorico.value = false
    erro.value =
      'Não foi possível carregar o histórico da entrega.'
  } finally {
    carregandoHistorico.value = false
  }
}

function fecharHistorico() {
  modalHistorico.value = false
  historicoEntrega.value = null
}

function formatarQuantidade(valor: number) {
  return new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 3,
  }).format(Number(valor))
}

function formatarDataHora(
  data: string | null,
) {
  if (!data) {
    return '—'
  }

  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat(
    'pt-BR',
    {
      dateStyle: 'short',
      timeStyle: 'short',
    },
  ).format(valor)
}

function formatarDataHistorico(
  data: string | null,
) {
  if (!data) {
    return '—'
  }

  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat(
    'pt-BR',
    {
      dateStyle: 'short',
    },
  ).format(valor)
}

function resultadoEntregaLabel(
  resultado: string,
) {
  const labels: Record<string, string> = {
    COMPLETA: 'Completa',
    PARCIAL: 'Parcial',
    NAO_ENTREGUE: 'Não entregue',
  }

  return labels[resultado] ?? resultado
}

function statusPendenciaLabel(
  status: string,
) {
  const labels: Record<string, string> = {
    ABERTA: 'Aberta',
    PROGRAMADA: 'Programada',
    RESOLVIDA: 'Resolvida',
    CANCELADA: 'Cancelada',
  }

  return labels[status] ?? status
}

function pesquisar() {
  carregarEntregas(1)
}

function limparFiltros() {
  filtros.busca = ''
  filtros.regiao_id = null
  filtros.status = ''
  filtros.prioridade = ''

  carregarEntregas(1)
}

function statusLabel(status: StatusEntrega) {
  const labels: Record<StatusEntrega, string> = {
    PENDENTE: 'Pendente',
    PROGRAMADA: 'Programada',
    EM_SAIDA: 'Em saída',
    PARCIAL: 'Parcial',
    CONCLUIDA: 'Concluída',
    NAO_ENTREGUE: 'Não entregue',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function prioridadeLabel(prioridade: PrioridadeEntrega) {
  const labels: Record<PrioridadeEntrega, string> = {
    BAIXA: 'Baixa',
    NORMAL: 'Normal',
    ALTA: 'Alta',
    URGENTE: 'Urgente',
  }

  return labels[prioridade]
}

onMounted(async () => {
  await carregarRegioes()
  await carregarEntregas()
})
</script>

<template>
  <main class="pagina">
    <header class="cabecalho">
      <div>
        <h1>Entregas</h1>

        <p>
          Controle as entregas e os produtos enviados aos clientes.
        </p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="novaEntrega"
      >
        + Nova entrega
      </button>
    </header>

    <section class="card filtros">
      <form
        class="grid-filtros"
        @submit.prevent="pesquisar"
      >
        <input
          v-model="filtros.busca"
          type="text"
          placeholder="Pedido, cliente, telefone ou endereço..."
        />

        <select v-model="filtros.regiao_id">
          <option :value="null">
            Todas as regiões
          </option>

          <option
            v-for="regiao in regioes"
            :key="regiao.id"
            :value="regiao.id"
          >
            {{ regiao.nome }}
          </option>
        </select>

        <select v-model="filtros.status">
          <option value="">
            Todos os status
          </option>

          <option value="PENDENTE">
            Pendente
          </option>

          <option value="PROGRAMADA">
            Programada
          </option>

          <option value="EM_SAIDA">
            Em saída
          </option>

          <option value="PARCIAL">
            Parcial
          </option>

          <option value="CONCLUIDA">
            Concluída
          </option>

          <option value="NAO_ENTREGUE">
            Não entregue
          </option>

          <option value="CANCELADA">
            Cancelada
          </option>
        </select>

        <select v-model="filtros.prioridade">
          <option value="">
            Todas as prioridades
          </option>

          <option value="BAIXA">Baixa</option>
          <option value="NORMAL">Normal</option>
          <option value="ALTA">Alta</option>
          <option value="URGENTE">Urgente</option>
        </select>

        <button
          class="botao primario"
          type="submit"
        >
          Filtrar
        </button>

        <button
          class="botao secundario"
          type="button"
          @click="limparFiltros"
        >
          Limpar
        </button>
      </form>
    </section>

    <p
      v-if="erro"
      class="erro"
    >
      {{ erro }}
    </p>

    <section class="card">
      <div class="topo-tabela">
        <strong>
          {{ total }} entrega(s)
        </strong>
      </div>

      <div
        v-if="carregando"
        class="estado"
      >
        Carregando entregas...
      </div>

      <div
        v-else-if="entregas.length === 0"
        class="estado"
      >
        Nenhuma entrega encontrada.
      </div>

      <div
        v-else
        class="tabela-container"
      >
        <table>
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Cliente</th>
              <th>Região</th>
              <th>Endereço</th>
              <th>Produtos</th>
              <th>Prioridade</th>
              <th>Status</th>
              <th class="acoes-coluna">
                Ações
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="entrega in entregas"
              :key="entrega.id"
              class="linha-entrega"
              title="Clique para ver o histórico da entrega"
              @click="abrirHistorico(entrega)"
            >
              <td>
                <strong>
                  {{ entrega.numero_pedido || `#${entrega.id}` }}
                </strong>
              </td>

              <td>
                <strong>{{ entrega.cliente_nome }}</strong>

                <small
                  v-if="entrega.cliente_apelido"
                  class="secundario-texto"
                >
                  {{ entrega.cliente_apelido }}
                </small>
              </td>

              <td>
                {{ entrega.regiao.nome }}
              </td>

              <td class="endereco">
                {{ entrega.endereco }}
              </td>

              <td>
                {{ entrega.itens.length }}
              </td>

              <td>
                <span
                  class="prioridade"
                  :class="entrega.prioridade.toLowerCase()"
                >
                  {{ prioridadeLabel(entrega.prioridade) }}
                </span>
              </td>

              <td>
                <span
                  class="status"
                  :class="entrega.status.toLowerCase()"
                >
                  {{ statusLabel(entrega.status) }}
                </span>
              </td>

              <td class="acoes">
                <button
                  v-if="
                    entrega.status !== 'CONCLUIDA' &&
                    entrega.status !== 'CANCELADA'
                  "
                  class="acao"
                  type="button"
                  @click.stop="editarEntrega(entrega)"
                >
                  Editar
                </button>

                <button
                  v-if="
                    entrega.status !== 'CONCLUIDA' &&
                    entrega.status !== 'CANCELADA'
                  "
                  class="acao perigo"
                  type="button"
                  @click.stop="cancelarEntrega(entrega)"
                >
                  Cancelar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="ultimaPagina > 1"
        class="paginacao"
      >
        <button
          class="botao secundario"
          :disabled="paginaAtual <= 1"
          @click="carregarEntregas(paginaAtual - 1)"
        >
          Anterior
        </button>

        <span>
          Página {{ paginaAtual }} de {{ ultimaPagina }}
        </span>

        <button
          class="botao secundario"
          :disabled="paginaAtual >= ultimaPagina"
          @click="carregarEntregas(paginaAtual + 1)"
        >
          Próxima
        </button>
      </footer>
    </section>


    <!-- HISTÓRICO DA ENTREGA -->

    <div
      v-if="modalHistorico"
      class="modal-fundo historico-modal-fundo"
      @click.self="fecharHistorico"
    >
      <section class="modal historico-modal">
        <header class="modal-cabecalho">
          <div>
            <h2>
              Histórico da entrega
            </h2>

            <p v-if="historicoEntrega">
              Pedido
              {{
                historicoEntrega.entrega.numero_pedido
                  ? `#${historicoEntrega.entrega.numero_pedido}`
                  : `#${historicoEntrega.entrega.id}`
              }}
              ·
              {{ historicoEntrega.entrega.cliente_nome }}
            </p>

            <p v-else>
              Carregando informações...
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharHistorico"
          >
            ×
          </button>
        </header>

        <div
          v-if="carregandoHistorico"
          class="estado historico-carregando"
        >
          Carregando histórico...
        </div>

        <template v-else-if="historicoEntrega">
          <div class="historico-identificacao">
            <div>
              <small>Cliente</small>
              <strong>
                {{ historicoEntrega.entrega.cliente_nome }}
              </strong>
            </div>

            <div>
              <small>Endereço</small>
              <strong>
                {{ historicoEntrega.entrega.endereco }}
              </strong>
            </div>

            <div>
              <small>Status atual</small>
              <strong>
                {{ statusLabel(historicoEntrega.entrega.status as StatusEntrega) }}
              </strong>
            </div>
          </div>

          <section class="historico-secao">
            <div class="historico-titulo">
              <div>
                <h3>Resumo dos produtos</h3>
                <p>
                  O saldo restante inclui as pendências.
                </p>
              </div>
            </div>

            <div
              v-for="item in historicoEntrega.itens"
              :key="item.entrega_item_id"
              class="historico-produto"
            >
              <div class="historico-produto-topo">
                <div>
                  <strong>
                    {{ item.descricao_produto }}
                  </strong>

                  <small v-if="item.codigo_produto">
                    Cód. {{ item.codigo_produto }}
                  </small>
                </div>

                <span>
                  {{ item.unidade || '' }}
                </span>
              </div>

              <div class="historico-resumo-grid">
                <div class="historico-indicador">
                  <small>Comprado</small>
                  <strong>
                    {{ formatarQuantidade(item.comprado) }}
                  </strong>
                </div>

                <div class="historico-indicador entregue">
                  <small>Entregue</small>
                  <strong>
                    {{ formatarQuantidade(item.entregue) }}
                  </strong>
                </div>

                <div class="historico-indicador saldo">
                  <small>Saldo restante</small>
                  <strong>
                    {{ formatarQuantidade(item.saldo_restante) }}
                  </strong>
                </div>

                <div class="historico-indicador pendente">
                  <small>Pendência</small>
                  <strong>
                    {{ formatarQuantidade(item.pendente) }}
                  </strong>
                </div>

                <div class="historico-indicador">
                  <small>Em carrada</small>
                  <strong>
                    {{ formatarQuantidade(item.em_carrada) }}
                  </strong>
                </div>

                <div class="historico-indicador">
                  <small>Ainda não enviado</small>
                  <strong>
                    {{ formatarQuantidade(item.nao_enviado) }}
                  </strong>
                </div>
              </div>

              <div class="historico-explicacao">
                <span>
                  Saldo restante:
                  <strong>
                    {{ formatarQuantidade(item.saldo_restante) }}
                  </strong>
                </span>

                <span>
                  =
                </span>

                <span>
                  Pendência:
                  <strong>
                    {{ formatarQuantidade(item.pendente) }}
                  </strong>
                </span>

                <span>
                  +
                </span>

                <span>
                  Em carrada:
                  <strong>
                    {{ formatarQuantidade(item.em_carrada) }}
                  </strong>
                </span>

                <span>
                  +
                </span>

                <span>
                  Ainda não enviado:
                  <strong>
                    {{ formatarQuantidade(item.nao_enviado) }}
                  </strong>
                </span>
              </div>
            </div>
          </section>

          <section class="historico-secao">
            <div class="historico-titulo">
              <div>
                <h3>Tentativas de entrega</h3>
                <p>
                  Carradas, quantidades carregadas e o que
                  o cliente realmente recebeu.
                </p>
              </div>
            </div>

            <div
              v-if="historicoEntrega.tentativas.length === 0"
              class="historico-vazio"
            >
              Ainda não houve tentativa de entrega.
            </div>

            <div
              v-for="tentativa in historicoEntrega.tentativas"
              :key="tentativa.tentativa_id"
              class="historico-evento"
            >
              <div class="historico-evento-topo">
                <div>
                  <strong>
                    {{ formatarDataHora(tentativa.data_hora) }}
                  </strong>

                  <span>
                    Carrada #{{ tentativa.saida_id }}
                  </span>
                </div>

                <span
                  class="resultado-historico"
                  :class="tentativa.resultado.toLowerCase()"
                >
                  {{ resultadoEntregaLabel(tentativa.resultado) }}
                </span>
              </div>

              <div
                v-if="tentativa.veiculo"
                class="historico-veiculo"
              >
                Veículo:
                <strong>
                  {{ tentativa.veiculo.nome }}
                </strong>

                <span v-if="tentativa.veiculo.placa">
                  · {{ tentativa.veiculo.placa }}
                </span>
              </div>

              <div class="historico-itens-tentativa">
                <div class="historico-item-cabecalho">
                  <span>Produto</span>
                  <span>Carregado</span>
                  <span>Entregue</span>
                  <span>Não entregue</span>
                </div>

                <div
                  v-for="item in tentativa.itens"
                  :key="item.entrega_item_id"
                  class="historico-item-linha"
                >
                  <div>
                    <strong>
                      {{ item.descricao_produto }}
                    </strong>

                    <small v-if="item.codigo_produto">
                      Cód. {{ item.codigo_produto }}
                    </small>
                  </div>

                  <span>
                    {{ formatarQuantidade(item.programado) }}
                    {{ item.unidade || '' }}
                  </span>

                  <strong class="quantidade-entregue">
                    {{ formatarQuantidade(item.entregue) }}
                    {{ item.unidade || '' }}
                  </strong>

                  <strong
                    :class="{
                      'quantidade-faltante':
                        item.nao_entregue > 0,
                    }"
                  >
                    {{ formatarQuantidade(item.nao_entregue) }}
                    {{ item.unidade || '' }}
                  </strong>
                </div>
              </div>

              <div
                v-if="
                  tentativa.motivo_nao_entrega ||
                  tentativa.observacao
                "
                class="historico-observacoes"
              >
                <p v-if="tentativa.motivo_nao_entrega">
                  <strong>Motivo:</strong>
                  {{ tentativa.motivo_nao_entrega }}
                </p>

                <p v-if="tentativa.observacao">
                  <strong>Observação:</strong>
                  {{ tentativa.observacao }}
                </p>
              </div>
            </div>
          </section>

          <section class="historico-secao">
            <div class="historico-titulo">
              <div>
                <h3>Pendências</h3>
                <p>
                  Produtos que saíram para entrega e
                  não foram totalmente entregues.
                </p>
              </div>
            </div>

            <div
              v-if="historicoEntrega.pendencias.length === 0"
              class="historico-vazio"
            >
              Esta entrega não possui pendências.
            </div>

            <div
              v-for="pendencia in historicoEntrega.pendencias"
              :key="pendencia.id"
              class="historico-pendencia"
            >
              <div>
                <strong>
                  {{ pendencia.descricao_produto }}
                </strong>

                <small>
                  Aberta em
                  {{ formatarDataHistorico(pendencia.data_abertura) }}
                </small>

                <small v-if="pendencia.data_resolucao">
                  Resolvida em
                  {{ formatarDataHistorico(pendencia.data_resolucao) }}
                </small>
              </div>

              <div class="historico-pendencia-quantidades">
                <span>
                  Original:
                  <strong>
                    {{ formatarQuantidade(pendencia.quantidade_original) }}
                  </strong>
                </span>

                <span>
                  Resolvida:
                  <strong>
                    {{ formatarQuantidade(pendencia.quantidade_resolvida) }}
                  </strong>
                </span>

                <span>
                  Saldo:
                  <strong>
                    {{ formatarQuantidade(pendencia.saldo) }}
                  </strong>
                </span>
              </div>

              <span
                class="pendencia-status"
                :class="pendencia.status.toLowerCase()"
              >
                {{ statusPendenciaLabel(pendencia.status) }}
              </span>

              <p
                v-if="pendencia.motivo || pendencia.observacao"
                class="historico-pendencia-motivo"
              >
                {{ pendencia.motivo || pendencia.observacao }}
              </p>
            </div>
          </section>

          <footer class="modal-acoes">
            <button
              class="botao secundario"
              type="button"
              @click="fecharHistorico"
            >
              Fechar
            </button>
          </footer>
        </template>
      </section>
    </div>

    <!-- MODAL -->
    <div
      v-if="modalAberto"
      class="modal-fundo"
      @click.self="fecharModal"
    >
      <section class="modal">
        <header class="modal-cabecalho">
          <div>
            <h2>
              {{
                entregaEditandoId
                  ? 'Editar entrega'
                  : 'Nova entrega'
              }}
            </h2>

            <p>
              Informe os dados da entrega.
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharModal"
          >
            ×
          </button>
        </header>

        <form @submit.prevent="salvarEntrega">
          <div class="grid-form">
            <div class="campo">
              <label>Número do pedido</label>

              <input
                v-model="formulario.numero_pedido"
                type="text"
                placeholder="Ex: 23249"
              />
            </div>

            <div class="campo">
              <label>Região *</label>

              <select
                v-model="formulario.regiao_id"
                required
              >
                <option :value="0">
                  Selecione...
                </option>

                <option
                  v-for="regiao in regioes"
                  :key="regiao.id"
                  :value="regiao.id"
                >
                  {{ regiao.nome }}
                </option>
              </select>
            </div>
          </div>

          <div class="campo cliente-campo">
            <label>Cliente *</label>

            <div class="cliente-busca">
              <input
                v-model="buscaCliente"
                type="text"
                placeholder="Digite o nome do cliente..."
                @input="pesquisarClientes"
              />

              <button
                v-if="formulario.cliente_id"
                class="botao secundario"
                type="button"
                @click="limparClienteSelecionado"
              >
                Limpar
              </button>
            </div>

            <div
              v-if="clientes.length"
              class="resultado-clientes"
            >
              <button
                v-for="cliente in clientes"
                :key="cliente.id"
                type="button"
                @click="selecionarCliente(cliente)"
              >
                <strong>{{ cliente.nome }}</strong>

                <span v-if="cliente.apelido">
                  {{ cliente.apelido }}
                </span>

                <small v-if="cliente.telefone">
                  {{ cliente.telefone }}
                </small>
              </button>
            </div>
          </div>

          <div class="grid-form">
            <div class="campo">
              <label>Telefone</label>

              <input
                v-model="formulario.cliente_telefone"
                type="text"
              />
            </div>

            <div class="campo">
              <label>Prioridade</label>

              <select v-model="formulario.prioridade">
                <option value="BAIXA">Baixa</option>
                <option value="NORMAL">Normal</option>
                <option value="ALTA">Alta</option>
                <option value="URGENTE">Urgente</option>
              </select>
            </div>
          </div>

          <div class="campo">
            <label>Endereço *</label>

            <input
              v-model="formulario.endereco"
              type="text"
              required
            />
          </div>

          <div class="campo">
            <label>Referência</label>

            <input
              v-model="formulario.referencia"
              type="text"
              placeholder="Ex: Em frente ao mercantil"
            />
          </div>

          <div class="produtos-cabecalho">
            <div>
              <h3>Produtos</h3>
              <p>Itens que deverão ser entregues.</p>
            </div>

            <button
              class="botao secundario"
              type="button"
              @click="adicionarItem"
            >
              + Adicionar produto
            </button>
          </div>

          <div
            v-for="(item, index) in formulario.itens"
            :key="index"
            class="produto"
          >
            <div class="produto-numero">
              Produto {{ index + 1 }}
            </div>

            <div class="produto-grid">
              <div class="campo">
                <label>Código</label>

                <input
                  v-model="item.codigo_produto"
                  type="text"
                />
              </div>

              <div class="campo descricao">
                <label>Descrição *</label>

                <input
                  v-model="item.descricao_produto"
                  type="text"
                  required
                />
              </div>

              <div class="campo">
                <label>Unidade</label>

                <input
                  v-model="item.unidade"
                  type="text"
                  placeholder="UN"
                />
              </div>

              <div class="campo">
                <label>Quantidade *</label>

                <input
                  v-model.number="item.quantidade_solicitada"
                  type="number"
                  min="0.001"
                  step="0.001"
                  required
                />
              </div>
            </div>

            <button
              v-if="formulario.itens.length > 1"
              class="remover-produto"
              type="button"
              @click="removerItem(index)"
            >
              Remover produto
            </button>
          </div>

          <div class="campo">
            <label>Observação da entrega</label>

            <textarea
              v-model="formulario.observacao"
              rows="3"
            ></textarea>
          </div>

          <footer class="modal-acoes">
            <button
              class="botao secundario"
              type="button"
              @click="fecharModal"
            >
              Cancelar
            </button>

            <button
              class="botao primario"
              type="submit"
              :disabled="salvando"
            >
              {{
                salvando
                  ? 'Salvando...'
                  : 'Salvar entrega'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </main>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.pagina {
  min-height: 100vh;
  padding: 32px;
  color: #172033;
}

.cabecalho {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.cabecalho h1,
.produtos-cabecalho h3 {
  margin: 0;
}

.cabecalho p,
.produtos-cabecalho p,
.modal-cabecalho p {
  margin: 5px 0 0;
  color: #667085;
}

.card {
  overflow: hidden;
  background: white;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
}

.filtros {
  margin-bottom: 20px;
  padding: 16px;
}

.grid-filtros {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
  gap: 10px;
}

input,
select,
textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: white;
  font: inherit;
  outline: none;
}

input,
select {
  min-height: 42px;
}

textarea {
  resize: vertical;
}

.botao {
  min-height: 42px;
  padding: 0 16px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.primario {
  border: 0;
  background: #172033;
  color: white;
}

.secundario {
  border: 1px solid #d0d5dd;
  background: white;
  color: #344054;
}

.botao:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.erro {
  padding: 12px 16px;
  border: 1px solid #fecdca;
  border-radius: 8px;
  background: #fef3f2;
  color: #b42318;
}

.topo-tabela {
  padding: 18px 20px;
  border-bottom: 1px solid #eaecf0;
}

.tabela-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  padding: 12px 16px;
  background: #f9fafb;
  color: #667085;
  font-size: 12px;
  text-align: left;
}

td {
  padding: 15px 16px;
  border-top: 1px solid #eaecf0;
  font-size: 14px;
}

.endereco {
  max-width: 250px;
}

.secundario-texto {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.status,
.prioridade {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.status.pendente,
.status.programada {
  background: #fff7ed;
  color: #c2410c;
}

.status.em_saida {
  background: #eff6ff;
  color: #1d4ed8;
}

.status.parcial,
.status.nao_entregue {
  background: #fef2f2;
  color: #b42318;
}

.status.concluida {
  background: #ecfdf3;
  color: #027a48;
}

.status.cancelada {
  background: #f2f4f7;
  color: #667085;
}

.prioridade.baixa {
  background: #f2f4f7;
}

.prioridade.normal {
  background: #eff6ff;
}

.prioridade.alta {
  background: #fff7ed;
}

.prioridade.urgente {
  background: #fef2f2;
  color: #b42318;
}

.acoes-coluna,
.acoes {
  text-align: right;
}

.acao {
  margin-left: 10px;
  border: 0;
  background: transparent;
  font-weight: 600;
  cursor: pointer;
}

.acao.perigo,
.remover-produto {
  color: #b42318;
}

.estado {
  padding: 50px;
  color: #667085;
  text-align: center;
}

.paginacao {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-top: 1px solid #eaecf0;
}

.modal-fundo {
  position: fixed;
  z-index: 100;
  inset: 0;
  overflow-y: auto;
  padding: 40px 20px;
  background: rgb(16 24 40 / 60%);
}

.modal {
  width: 100%;
  max-width: 900px;
  margin: auto;
  padding: 26px;
  border-radius: 14px;
  background: white;
}

.modal-cabecalho {
  display: flex;
  justify-content: space-between;
  margin-bottom: 25px;
}

.modal-cabecalho h2 {
  margin: 0;
}

.fechar {
  border: 0;
  background: transparent;
  font-size: 28px;
  cursor: pointer;
}

.grid-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.campo {
  margin-bottom: 18px;
}

.campo label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
}

.cliente-campo {
  position: relative;
}

.cliente-busca {
  display: flex;
  gap: 8px;
}

.resultado-clientes {
  position: absolute;
  z-index: 10;
  top: 70px;
  right: 0;
  left: 0;
  overflow: hidden;
  max-height: 260px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: white;
  box-shadow: 0 10px 25px rgb(16 24 40 / 12%);
}

.resultado-clientes button {
  display: flex;
  width: 100%;
  flex-direction: column;
  padding: 12px;
  border: 0;
  border-bottom: 1px solid #eaecf0;
  background: white;
  text-align: left;
  cursor: pointer;
}

.resultado-clientes button:hover {
  background: #f9fafb;
}

.resultado-clientes span,
.resultado-clientes small {
  margin-top: 3px;
  color: #667085;
}

.produtos-cabecalho {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 28px 0 14px;
}

.produto {
  margin-bottom: 14px;
  padding: 16px;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
  background: #f9fafb;
}

.produto-numero {
  margin-bottom: 12px;
  font-size: 13px;
  font-weight: 700;
}

.produto-grid {
  display: grid;
  grid-template-columns: 1fr 3fr 1fr 1fr;
  gap: 12px;
}

.produto-grid .campo {
  margin-bottom: 0;
}

.remover-produto {
  margin-top: 12px;
  border: 0;
  background: transparent;
  font-weight: 600;
  cursor: pointer;
}

.modal-acoes {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 25px;
}


.linha-entrega {
  cursor: pointer;
}

.linha-entrega:hover {
  background: #f9fafb;
}

.historico-modal-fundo {
  z-index: 200;
}

.historico-modal {
  max-width: 1100px;
}

.historico-carregando {
  padding: 70px 20px;
}

.historico-identificacao {
  display: grid;
  grid-template-columns: 1fr 2fr 1fr;
  gap: 12px;
  margin-bottom: 26px;
}

.historico-identificacao > div {
  padding: 14px;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
  background: #f9fafb;
}

.historico-identificacao small,
.historico-identificacao strong {
  display: block;
}

.historico-identificacao small {
  margin-bottom: 5px;
  color: #667085;
}

.historico-secao {
  margin-top: 28px;
}

.historico-titulo {
  margin-bottom: 14px;
}

.historico-titulo h3 {
  margin: 0;
}

.historico-titulo p {
  margin: 4px 0 0;
  color: #667085;
  font-size: 13px;
}

.historico-produto {
  margin-bottom: 14px;
  padding: 16px;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
}

.historico-produto-topo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}

.historico-produto-topo small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.historico-resumo-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px;
}

.historico-indicador {
  padding: 12px;
  border-radius: 8px;
  background: #f9fafb;
}

.historico-indicador small,
.historico-indicador strong {
  display: block;
}

.historico-indicador small {
  margin-bottom: 4px;
  color: #667085;
  font-size: 11px;
}

.historico-indicador.entregue {
  background: #ecfdf3;
}

.historico-indicador.entregue strong {
  color: #027a48;
}

.historico-indicador.saldo {
  background: #eff6ff;
}

.historico-indicador.saldo strong {
  color: #1d4ed8;
}

.historico-indicador.pendente {
  background: #fff7ed;
}

.historico-indicador.pendente strong {
  color: #c2410c;
}

.historico-explicacao {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #eaecf0;
  color: #667085;
  font-size: 12px;
}

.historico-evento {
  position: relative;
  margin-bottom: 14px;
  padding: 16px;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
}

.historico-evento-topo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 10px;
}

.historico-evento-topo div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.historico-evento-topo span {
  color: #667085;
  font-size: 12px;
}

.resultado-historico {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.resultado-historico.completa {
  background: #ecfdf3;
  color: #027a48;
}

.resultado-historico.parcial {
  background: #fff7ed;
  color: #c2410c;
}

.resultado-historico.nao_entregue {
  background: #fef2f2;
  color: #b42318;
}

.historico-veiculo {
  margin-bottom: 12px;
  color: #667085;
  font-size: 13px;
}

.historico-itens-tentativa {
  overflow: hidden;
  border: 1px solid #eaecf0;
  border-radius: 9px;
}

.historico-item-cabecalho,
.historico-item-linha {
  display: grid;
  grid-template-columns: 3fr 1fr 1fr 1fr;
  gap: 12px;
  align-items: center;
  padding: 10px 12px;
}

.historico-item-cabecalho {
  background: #f9fafb;
  color: #667085;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.historico-item-linha {
  border-top: 1px solid #eaecf0;
  font-size: 13px;
}

.historico-item-linha small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.quantidade-entregue {
  color: #027a48;
}

.quantidade-faltante {
  color: #b42318;
}

.historico-observacoes {
  margin-top: 12px;
  padding: 12px;
  border-radius: 8px;
  background: #f9fafb;
  font-size: 13px;
}

.historico-observacoes p {
  margin: 0;
}

.historico-observacoes p + p {
  margin-top: 6px;
}

.historico-pendencia {
  position: relative;
  margin-bottom: 10px;
  padding: 14px;
  border: 1px solid #fedf89;
  border-radius: 10px;
  background: #fffaeb;
}

.historico-pendencia > div:first-child {
  padding-right: 110px;
}

.historico-pendencia small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.historico-pendencia-quantidades {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 10px;
  font-size: 13px;
}

.pendencia-status {
  position: absolute;
  top: 14px;
  right: 14px;
  padding: 4px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.pendencia-status.aberta,
.pendencia-status.programada {
  background: #fff7ed;
  color: #c2410c;
}

.pendencia-status.resolvida {
  background: #ecfdf3;
  color: #027a48;
}

.pendencia-status.cancelada {
  background: #f2f4f7;
  color: #667085;
}

.historico-pendencia-motivo {
  margin: 10px 0 0;
  color: #667085;
  font-size: 13px;
}

.historico-vazio {
  padding: 22px;
  border: 1px dashed #d0d5dd;
  border-radius: 10px;
  color: #667085;
  text-align: center;
}

@media (max-width: 1000px) {
  .grid-filtros {
    grid-template-columns: 1fr 1fr;
  }

  .produto-grid {
    grid-template-columns: 1fr 1fr;
  }

  .historico-resumo-grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .historico-identificacao {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 700px) {
  .pagina {
    padding: 18px;
  }

  .cabecalho,
  .produtos-cabecalho {
    align-items: stretch;
    flex-direction: column;
  }

  .grid-form,
  .grid-filtros,
  .produto-grid {
    grid-template-columns: 1fr;
  }

  .historico-resumo-grid {
    grid-template-columns: 1fr 1fr;
  }

  .historico-item-cabecalho {
    display: none;
  }

  .historico-item-linha {
    grid-template-columns: 1fr;
  }

  .historico-evento-topo {
    align-items: flex-start;
    flex-direction: column;
  }

  .historico-pendencia > div:first-child {
    padding-right: 0;
  }

  .pendencia-status {
    position: static;
    margin-top: 10px;
  }
}
</style>