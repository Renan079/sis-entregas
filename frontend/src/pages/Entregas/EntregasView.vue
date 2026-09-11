<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { entregaService } from '@/services/entregaService'
import { regiaoService } from '@/services/regiaoService'

import ImportarEntregaModal from '@/components/Entregas/ImportarEntregaModal.vue'

import type { Regiao } from '@/types/Regiao'
import type {
  Entrega,
  PrioridadeEntrega,
  StatusEntrega,
} from '@/types/Entrega'

const router = useRouter()

const entregas = ref<Entrega[]>([])
const regioes = ref<Regiao[]>([])

const carregando = ref(false)
const erro = ref('')

const modalImportacao = ref(false)

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const filtros = reactive({
  busca: '',
  regiao_id: null as number | null,
  status: '' as StatusEntrega | '',
  prioridade: '' as PrioridadeEntrega | '',
  ordem: 'antigas' as 'antigas' | 'recentes',
})

async function carregarEntregas(page = 1) {
  carregando.value = true
  erro.value = ''

  try {
    const response = await entregaService.listar({
      busca: filtros.busca,
      regiao_id: filtros.regiao_id,
      status: filtros.status,
      prioridade: filtros.prioridade,
      ordem: filtros.ordem,
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

function abrirImportacao() {
  modalImportacao.value = true
}

function fecharImportacao() {
  modalImportacao.value = false
}

async function importacaoConcluida() {
  modalImportacao.value = false
  await carregarEntregas(1)
}

async function novaEntrega() {
  await router.push({
    name: 'entrega-nova',
  })
}

async function editarEntrega(entrega: Entrega) {
  await router.push({
    name: 'entrega-editar',
    params: {
      id: entrega.id,
    },
  })
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

  await router.push({
    name: 'entrega-historico',
    params: {
      id: entrega.id,
    },
  })
}

function pesquisar() {
  carregarEntregas(1)
}

function limparFiltros() {
  filtros.busca = ''
  filtros.regiao_id = null
  filtros.status = ''
  filtros.prioridade = ''
  filtros.ordem = 'antigas'

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

function formatarDataLancamento(data: string) {
  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
  }).format(valor)
}

function formatarHoraLancamento(data: string) {
  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return ''
  }

  return new Intl.DateTimeFormat('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(valor)
}

function tempoDesdeLancamento(data: string) {
  const lancamento = new Date(data)

  if (Number.isNaN(lancamento.getTime())) {
    return ''
  }

  const hoje = new Date()

  const inicioHoje = new Date(
    hoje.getFullYear(),
    hoje.getMonth(),
    hoje.getDate(),
  )

  const inicioLancamento = new Date(
    lancamento.getFullYear(),
    lancamento.getMonth(),
    lancamento.getDate(),
  )

  const diferenca = Math.max(
    0,
    Math.round(
      (inicioHoje.getTime() - inicioLancamento.getTime()) /
        86_400_000,
    ),
  )

  if (diferenca === 0) {
    return 'Hoje'
  }

  if (diferenca === 1) {
    return 'Há 1 dia'
  }

  return `Há ${diferenca} dias`
}

function entregaEmAberto(entrega: Entrega) {
  return !['CONCLUIDA', 'CANCELADA'].includes(entrega.status)
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

      <div class="cabecalho-acoes">
        <button
          class="botao secundario"
          type="button"
          @click="abrirImportacao"
        >
          Importar foto
        </button>

        <button
          class="botao primario"
          type="button"
          @click="novaEntrega"
        >
          + Nova entrega
        </button>
      </div>
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
          <option value="">Todos os status</option>
          <option value="PENDENTE">Pendente</option>
          <option value="PROGRAMADA">Programada</option>
          <option value="EM_SAIDA">Em saída</option>
          <option value="PARCIAL">Parcial</option>
          <option value="CONCLUIDA">Concluída</option>
          <option value="NAO_ENTREGUE">Não entregue</option>
          <option value="CANCELADA">Cancelada</option>
        </select>

        <select v-model="filtros.prioridade">
          <option value="">Todas as prioridades</option>
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

        <label class="ordenacao-entregas">
          <span>Ordenar:</span>

          <select
            v-model="filtros.ordem"
            @change="carregarEntregas(1)"
          >
            <option value="antigas">
              Mais antigas primeiro
            </option>

            <option value="recentes">
              Mais recentes primeiro
            </option>
          </select>
        </label>
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
              <th>Lançada em</th>
              <th>Cliente</th>
              <th>Região</th>
              <th>Endereço</th>
              <th>Produtos</th>
              <th>Prioridade</th>
              <th>Status</th>
              <th class="acoes-coluna">Ações</th>
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

              <td class="data-lancamento">
                <strong>
                  {{ formatarDataLancamento(entrega.created_at) }}
                </strong>

                <small>
                  {{ formatarHoraLancamento(entrega.created_at) }}
                </small>

                <span
                  v-if="entregaEmAberto(entrega)"
                  class="tempo-lancamento"
                >
                  {{ tempoDesdeLancamento(entrega.created_at) }}
                </span>
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

    <ImportarEntregaModal
      :aberto="modalImportacao"
      :regioes="regioes"
      @fechar="fecharImportacao"
      @importado="importacaoConcluida"
    />
  </main>
</template>

<style scoped src="../../styles/pages/entrega/entregas.css"></style>
