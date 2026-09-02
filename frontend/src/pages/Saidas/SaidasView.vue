<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { saidaService } from '@/services/saidaService.ts'

import type {
  Saida,
  SaidaRotaParada,
  StatusSaida,
} from '@/types/Saida.ts'

const router = useRouter()

const saidas = ref<Saida[]>([])

const carregando = ref(false)
const erro = ref('')

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const filtros = reactive({
  busca: '',
  status: '' as StatusSaida | '',
  data: '',
})

async function carregarSaidas(page = 1) {
  carregando.value = true
  erro.value = ''

  try {
    const response = await saidaService.listar({
      busca: filtros.busca,
      status: filtros.status,
      data: filtros.data,
      page,
    })

    saidas.value = response.data
    paginaAtual.value = response.meta.current_page
    ultimaPagina.value = response.meta.last_page
    total.value = response.meta.total
  } catch {
    erro.value = 'Não foi possível carregar as saídas.'
  } finally {
    carregando.value = false
  }
}

function rotaResumo(rota: SaidaRotaParada[]) {
  if (!rota.length) {
    return 'Sem rota definida'
  }

  const nomes = rota.map((parada) => parada.nome)

  if (nomes.length <= 4) {
    return nomes.join(' → ')
  }

  return `${nomes.slice(0, 4).join(' → ')} → ...`
}

async function novaSaida() {
  await router.push({
    name: 'saida-nova',
  })
}

async function abrirCarrada(saida: Saida) {
  await router.push({
    name: 'saida-detalhe',
    params: { id: saida.id },
  })
}

async function cancelarSaida(saida: Saida) {
  const confirmou = window.confirm(
    `Deseja cancelar a carrada #${saida.id}?`,
  )

  if (!confirmou) {
    return
  }

  try {
    await saidaService.cancelar(saida.id)
    await carregarSaidas(paginaAtual.value)
  } catch {
    erro.value = 'Não foi possível cancelar a saída.'
  }
}

async function abrirHistorico(saida: Saida) {
  await router.push({
    name: 'saida-historico',
    params: { id: saida.id },
  })
}

function filtrar() {
  carregarSaidas(1)
}

function limparFiltros() {
  filtros.busca = ''
  filtros.status = ''
  filtros.data = ''
  carregarSaidas(1)
}

function statusLabel(status: StatusSaida) {
  const labels: Record<StatusSaida, string> = {
    PLANEJADA: 'Planejada',
    EM_ANDAMENTO: 'Em andamento',
    FINALIZADA: 'Finalizada',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function formatarData(data: string) {
  if (!data) {
    return '—'
  }

  const somenteData = data.split('T')[0]

  if (!somenteData) {
    return '—'
  }

  const [ano, mes, dia] = somenteData.split('-')

  if (!ano || !mes || !dia) {
    return data
  }

  return `${dia}/${mes}/${ano}`
}

onMounted(async () => {
  await carregarSaidas()
})
</script>

<template>
  <main class="pagina">
    <header class="cabecalho">
      <div>
        <h1>Saídas / Carradas</h1>
        <p>
          Organize as entregas que irão juntas em cada viagem.
        </p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="novaSaida"
      >
        + Nova carrada
      </button>
    </header>

    <section class="card filtros">
      <form
        class="grid-filtros"
        @submit.prevent="filtrar"
      >
        <input
          v-model="filtros.busca"
          type="text"
          placeholder="Buscar carrada: #12 ou 12"
        />

        <input
          v-model="filtros.data"
          type="date"
        />

        <select v-model="filtros.status">
          <option value="">Todos os status</option>
          <option value="PLANEJADA">Planejada</option>
          <option value="EM_ANDAMENTO">Em andamento</option>
          <option value="FINALIZADA">Finalizada</option>
          <option value="CANCELADA">Cancelada</option>
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
        <strong>{{ total }} carrada(s)</strong>
      </div>

      <div
        v-if="carregando"
        class="estado"
      >
        Carregando...
      </div>

      <div
        v-else-if="saidas.length === 0"
        class="estado"
      >
        Nenhuma carrada encontrada.
      </div>

      <div
        v-else
        class="tabela-container"
      >
        <table>
          <thead>
            <tr>
              <th>Carrada</th>
              <th>Data</th>
              <th>Região</th>
              <th>Rota</th>
              <th>Veículo</th>
              <th>Entregas</th>
              <th>Status</th>
              <th class="acoes-coluna">Ações</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="saida in saidas"
              :key="saida.id"
              class="linha-carrada"
              title="Clique para ver o histórico da carrada"
              @click="abrirHistorico(saida)"
            >
              <td>
                <strong>#{{ saida.id }}</strong>
              </td>

              <td>{{ formatarData(saida.data_saida) }}</td>

              <td>
                {{
                  saida.regiao_principal?.nome ??
                  'Sem região principal'
                }}
              </td>

              <td class="rota-tabela">
                {{ rotaResumo(saida.rota) }}
              </td>

              <td>
                <template v-if="saida.veiculo">
                  <strong>{{ saida.veiculo.nome }}</strong>
                  <small>{{ saida.veiculo.placa || '' }}</small>
                </template>
                <span v-else>—</span>
              </td>

              <td>{{ saida.total_entregas }}</td>

              <td>
                <span
                  class="status"
                  :class="saida.status.toLowerCase()"
                >
                  {{ statusLabel(saida.status) }}
                </span>
              </td>

              <td class="acoes">
                <button
                  class="acao"
                  type="button"
                  @click.stop="abrirCarrada(saida)"
                >
                  Abrir
                </button>

                <button
                  v-if="saida.status === 'PLANEJADA'"
                  class="acao perigo"
                  type="button"
                  @click.stop="cancelarSaida(saida)"
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
          @click="carregarSaidas(paginaAtual - 1)"
        >
          Anterior
        </button>

        <span>
          Página {{ paginaAtual }} de {{ ultimaPagina }}
        </span>

        <button
          class="botao secundario"
          :disabled="paginaAtual >= ultimaPagina"
          @click="carregarSaidas(paginaAtual + 1)"
        >
          Próxima
        </button>
      </footer>
    </section>
  </main>
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
