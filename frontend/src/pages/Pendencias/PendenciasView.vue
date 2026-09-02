<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import { pendenciaService } from '@/services/pendenciaService.ts'
import { regiaoService } from '@/services/regiaoService.ts'
import { saidaService } from '@/services/saidaService.ts'

import PendenciaResolucaoModal from '@/components/Pendencias/PendenciaResolucaoModal.vue'
import PendenciaDetalhesModal from '@/components/Pendencias/PendenciaDetalhesModal.vue'

import type { Regiao } from '@/types/Regiao'

import type {
  MotivoDevolucao,
  Pendencia,
  PendenciaResumo,
  StatusPendencia,
} from '@/types/Pendencia'

const pendencias = ref<Pendencia[]>([])
const regioes = ref<Regiao[]>([])

const carregando = ref(false)
const salvando = ref(false)
const removendo = ref<number | null>(null)
const erro = ref('')
const sucesso = ref('')

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const resumo = reactive<PendenciaResumo>({
  abertas: 0,
  programadas: 0,
  resolvidas_30_dias: 0,
  total_30_dias: 0,
})

const filtros = reactive({
  busca: '',
  status: '' as StatusPendencia | '',
  regiao_id: null as number | null,
  data_inicio: '',
  data_fim: '',
  per_page: 10,
})

const modalResolucao = ref(false)
const pendenciaResolucao = ref<Pendencia | null>(null)

const modalDetalhes = ref(false)
const pendenciaDetalhes = ref<Pendencia | null>(null)



async function carregarPendencias(
  page = 1,
) {
  carregando.value = true
  erro.value = ''

  try {
    const response =
      await pendenciaService.listar({
        busca: filtros.busca,
        status: filtros.status,
        regiao_id: filtros.regiao_id,
        data_inicio: filtros.data_inicio,
        data_fim: filtros.data_fim,
        per_page: filtros.per_page,
        page,
      })

    pendencias.value = response.data

    resumo.abertas =
      response.resumo.abertas

    resumo.programadas =
      response.resumo.programadas

    resumo.resolvidas_30_dias =
      response.resumo.resolvidas_30_dias

    resumo.total_30_dias =
      response.resumo.total_30_dias

    paginaAtual.value =
      response.meta.current_page

    ultimaPagina.value =
      response.meta.last_page

    total.value =
      response.meta.total
  } catch (error) {
    erro.value =
      mensagemErro(
        error,
        'Não foi possível carregar as pendências.',
      )
  } finally {
    carregando.value = false
  }
}

async function carregarRegioes() {
  try {
    const response =
      await regiaoService.listar(1)

    regioes.value =
      response.data.filter(
        (regiao) => regiao.ativo,
      )
  } catch {
    erro.value =
      'As pendências foram carregadas, mas não foi possível carregar as regiões.'
  }
}

function filtrar() {
  carregarPendencias(1)
}

function limparFiltros() {
  filtros.busca = ''
  filtros.status = ''
  filtros.regiao_id = null
  filtros.data_inicio = ''
  filtros.data_fim = ''

  carregarPendencias(1)
}

function alterarQuantidadePorPagina() {
  carregarPendencias(1)
}

function abrirResolucao(
  pendencia: Pendencia,
) {
  if (
    !pendencia.pode_resolver_manualmente
  ) {
    return
  }

  erro.value = ''
  sucesso.value = ''

  pendenciaResolucao.value =
    pendencia

  modalResolucao.value = true
}

function fecharResolucao() {
  if (salvando.value) {
    return
  }

  modalResolucao.value = false
  pendenciaResolucao.value = null
}

async function confirmarResolucao(
  dados: {
    motivo_devolucao: MotivoDevolucao
    observacao: string | null
  },
) {
  if (!pendenciaResolucao.value) {
    return
  }

  salvando.value = true
  erro.value = ''
  sucesso.value = ''

  try {
    await pendenciaService
      .resolverManualmente(
        pendenciaResolucao.value.id,
        dados,
      )

    modalResolucao.value = false
    pendenciaResolucao.value = null

    sucesso.value =
      'Pendência resolvida manualmente e registrada como devolução.'

    await carregarPendencias(
      paginaAtual.value,
    )
  } catch (error) {
    erro.value =
      mensagemErro(
        error,
        'Não foi possível resolver a pendência.',
      )
  } finally {
    salvando.value = false
  }
}

async function removerDaCarrada(
  pendencia: Pendencia,
) {
  if (
    pendencia.status !== 'PROGRAMADA' ||
    !pendencia.carrada_atual ||
    pendencia.carrada_atual.status !==
      'PLANEJADA'
  ) {
    return
  }

  const confirmar = window.confirm(
    `Remover a pendência #${pendencia.id} da Carrada #${pendencia.carrada_atual.id}?`,
  )

  if (!confirmar) {
    return
  }

  removendo.value = pendencia.id
  erro.value = ''
  sucesso.value = ''

  try {
    await saidaService.removerPendencia(
      pendencia.carrada_atual.id,
      pendencia.id,
    )

    sucesso.value =
      'Pendência removida da carrada e liberada novamente.'

    await carregarPendencias(
      paginaAtual.value,
    )
  } catch (error) {
    erro.value =
      mensagemErro(
        error,
        'Não foi possível remover a pendência da carrada.',
      )
  } finally {
    removendo.value = null
  }
}

function abrirDetalhes(
  pendencia: Pendencia,
) {
  pendenciaDetalhes.value =
    pendencia

  modalDetalhes.value = true
}

function fecharDetalhes() {
  modalDetalhes.value = false
  pendenciaDetalhes.value = null
}

function statusLabel(
  status: StatusPendencia,
) {
  const labels: Record<
    StatusPendencia,
    string
  > = {
    ABERTA: 'Aberta',
    PROGRAMADA: 'Programada',
    RESOLVIDA: 'Resolvida',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}


function formatarQuantidade(
  valor: number,
) {
  return new Intl.NumberFormat(
    'pt-BR',
    {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3,
    },
  ).format(Number(valor))
}


function diasEmAberto(
  data: string | null,
) {
  if (!data) {
    return '—'
  }

  const abertura = new Date(data)

  if (
    Number.isNaN(
      abertura.getTime(),
    )
  ) {
    return '—'
  }

  const hoje = new Date()

  const diferenca =
    hoje.getTime() -
    abertura.getTime()

  const dias = Math.max(
    0,
    Math.floor(
      diferenca /
        (1000 * 60 * 60 * 24),
    ),
  )

  if (dias === 0) {
    return 'Hoje'
  }

  if (dias === 1) {
    return '1 dia'
  }

  return `${dias} dias`
}

function classeTempo(
  pendencia: Pendencia,
) {
  if (
    pendencia.status === 'RESOLVIDA' ||
    pendencia.status === 'CANCELADA'
  ) {
    return ''
  }

  if (!pendencia.data_abertura) {
    return ''
  }

  const abertura =
    new Date(
      pendencia.data_abertura,
    )

  if (
    Number.isNaN(
      abertura.getTime(),
    )
  ) {
    return ''
  }

  const dias =
    Math.max(
      0,
      Math.floor(
        (
          Date.now() -
          abertura.getTime()
        ) /
          (1000 * 60 * 60 * 24),
      ),
    )

  if (dias >= 4) {
    return 'atrasada'
  }

  if (dias >= 2) {
    return 'atencao'
  }

  return ''
}

function mensagemErro(
  error: unknown,
  fallback: string,
) {
  const resposta =
    error as {
      response?: {
        data?: {
          message?: string
          errors?: Record<
            string,
            string[]
          >
        }
      }
    }

  const errors =
    resposta.response?.data
      ?.errors

  if (errors) {
    const primeira =
      Object.values(errors)
        .flat()
        .find(Boolean)

    if (primeira) {
      return primeira
    }
  }

  return (
    resposta.response?.data
      ?.message ||
    fallback
  )
}

onMounted(async () => {
  await Promise.all([
    carregarPendencias(),
    carregarRegioes(),
  ])
})
</script>


<template>
  <main class="pagina-pendencias">
    <header class="cabecalho">
      <div>
        <span class="eyebrow">
          Operação logística
        </span>

        <h1>
          Pendências / Reentregas
        </h1>

        <p>
          Gerencie produtos que precisam
          ser reentregues ou encerrados
          por devolução.
        </p>
      </div>
    </header>

    <section class="resumo-grid">
      <article
        class="resumo-card resumo-abertas"
      >
        <div class="icone">!</div>

        <div>
          <strong>
            {{ resumo.abertas }}
          </strong>

          <span>
            Pendências abertas
          </span>

          <small>
            Aguardando decisão
          </small>
        </div>
      </article>

      <article
        class="resumo-card resumo-programadas"
      >
        <div class="icone">↗</div>

        <div>
          <strong>
            {{ resumo.programadas }}
          </strong>

          <span>
            Programadas
          </span>

          <small>
            Em carradas futuras
          </small>
        </div>
      </article>

      <article
        class="resumo-card resumo-resolvidas"
      >
        <div class="icone">✓</div>

        <div>
          <strong>
            {{ resumo.resolvidas_30_dias }}
          </strong>

          <span>
            Resolvidas
          </span>

          <small>
            Últimos 30 dias
          </small>
        </div>
      </article>

      <article
        class="resumo-card resumo-total"
      >
        <div class="icone">□</div>

        <div>
          <strong>
            {{ resumo.total_30_dias }}
          </strong>

          <span>
            Total de pendências
          </span>

          <small>
            Abertas nos últimos 30 dias
          </small>
        </div>
      </article>
    </section>

    <section class="card filtros">
      <form
        class="grid-filtros"
        @submit.prevent="filtrar"
      >
        <div class="campo busca">
          <label>
            Buscar
          </label>

          <input
            v-model="filtros.busca"
            type="search"
            placeholder="Pedido, cliente, produto ou código..."
          />
        </div>

        <div class="campo">
          <label>Status</label>

          <select
            v-model="filtros.status"
          >
            <option value="">
              Todos
            </option>

            <option value="ABERTA">
              Aberta
            </option>

            <option value="PROGRAMADA">
              Programada
            </option>

            <option value="RESOLVIDA">
              Resolvida
            </option>

            <option value="CANCELADA">
              Cancelada
            </option>
          </select>
        </div>

        <div class="campo">
          <label>Região</label>

          <select
            v-model="
              filtros.regiao_id
            "
          >
            <option :value="null">
              Todas
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

        <div class="campo">
          <label>Data início</label>

          <input
            v-model="
              filtros.data_inicio
            "
            type="date"
          />
        </div>

        <div class="campo">
          <label>Data fim</label>

          <input
            v-model="
              filtros.data_fim
            "
            type="date"
          />
        </div>

        <div class="acoes-filtro">
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
            Limpar filtros
          </button>
        </div>
      </form>
    </section>

    <p
      v-if="erro"
      class="mensagem erro"
    >
      {{ erro }}
    </p>

    <p
      v-if="sucesso"
      class="mensagem sucesso"
    >
      {{ sucesso }}
    </p>

    <section class="card tabela-card">
      <div class="topo-tabela">
        <div>
          <strong>
            {{ total }}
            pendência(s)
          </strong>

          <span>
            Abertas primeiro e mais
            antigas no topo.
          </span>
        </div>

        <div class="por-pagina">
          <label>
            Por página
          </label>

          <select
            v-model.number="
              filtros.per_page
            "
            @change="
              alterarQuantidadePorPagina
            "
          >
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>

      <div
        v-if="carregando"
        class="estado"
      >
        Carregando pendências...
      </div>

      <div
        v-else-if="
          pendencias.length === 0
        "
        class="estado"
      >
        Nenhuma pendência encontrada.
      </div>

      <div
        v-else
        class="tabela-container"
      >
        <table>
          <thead>
            <tr>
              <th>Pedido / Cliente</th>
              <th>Produto</th>
              <th>Região</th>
              <th>Quantidade</th>
              <th>Status</th>
              <th>Aberta há</th>
              <th class="acoes-coluna">
                Ações
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                pendencia in
                pendencias
              "
              :key="pendencia.id"
            >
              <td>
                <strong>
                  {{
                    pendencia.numero_pedido
                      ? `#${pendencia.numero_pedido}`
                      : `#${pendencia.entrega_id}`
                  }}
                </strong>

                <span class="linha-secundaria">
                  {{ pendencia.cliente_nome }}
                </span>

                <small>
                  {{ pendencia.endereco }}
                </small>
              </td>

              <td>
                <strong>
                  {{
                    pendencia.produto.descricao
                  }}
                </strong>

                <small
                  v-if="
                    pendencia.produto.codigo
                  "
                >
                  Ref:
                  {{
                    pendencia.produto.codigo
                  }}
                </small>
              </td>

              <td>
                {{
                  pendencia.regiao.nome
                }}
              </td>

              <td>
                <div class="quantidades">
                  <strong>
                    {{
                      formatarQuantidade(
                        pendencia.saldo_pendente,
                      )
                    }}
                    {{
                      pendencia.produto.unidade ??
                      ''
                    }}
                  </strong>

                  <small>
                    Original:
                    {{
                      formatarQuantidade(
                        pendencia.quantidade_original,
                      )
                    }}
                    · Resolvido:
                    {{
                      formatarQuantidade(
                        pendencia.quantidade_resolvida,
                      )
                    }}
                  </small>
                </div>
              </td>

              <td>
                <span
                  class="status"
                  :class="
                    pendencia.status.toLowerCase()
                  "
                >
                  {{
                    statusLabel(
                      pendencia.status,
                    )
                  }}
                </span>

                <small
                  v-if="
                    pendencia.carrada_atual
                  "
                  class="carrada-atual"
                >
                  Carrada
                  #{{
                    pendencia.carrada_atual.id
                  }}
                  ·
                  {{
                    pendencia.carrada_atual
                      .status === 'PLANEJADA'
                      ? 'Planejada'
                      : 'Em andamento'
                  }}
                </small>
              </td>

              <td>
                <span
                  class="tempo"
                  :class="
                    classeTempo(
                      pendencia,
                    )
                  "
                >
                  {{
                    pendencia.status === 'RESOLVIDA' ||
                    pendencia.status === 'CANCELADA'
                      ? '—'
                      : diasEmAberto(
                          pendencia.data_abertura,
                        )
                  }}
                </span>
              </td>

              <td class="acoes">
                <button
                  class="acao"
                  type="button"
                  @click="
                    abrirDetalhes(
                      pendencia,
                    )
                  "
                >
                  Ver
                </button>

                <button
                  v-if="
                    pendencia
                      .pode_resolver_manualmente
                  "
                  class="acao resolver"
                  type="button"
                  @click="
                    abrirResolucao(
                      pendencia,
                    )
                  "
                >
                  Resolver manualmente
                </button>

                <button
                  v-else-if="
                    pendencia.status ===
                      'PROGRAMADA' &&
                    pendencia.carrada_atual
                      ?.status ===
                      'PLANEJADA'
                  "
                  class="acao remover"
                  type="button"
                  :disabled="
                    removendo ===
                    pendencia.id
                  "
                  @click="
                    removerDaCarrada(
                      pendencia,
                    )
                  "
                >
                  {{
                    removendo ===
                    pendencia.id
                      ? 'Removendo...'
                      : 'Remover da carrada'
                  }}
                </button>

                <span
                  v-else-if="
                    pendencia.status ===
                      'PROGRAMADA' &&
                    pendencia.carrada_atual
                      ?.status ===
                      'EM_ANDAMENTO'
                  "
                  class="acao-bloqueada"
                >
                  Carrada em andamento
                </span>
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
          type="button"
          :disabled="
            paginaAtual <= 1
          "
          @click="
            carregarPendencias(
              paginaAtual - 1,
            )
          "
        >
          Anterior
        </button>

        <span>
          Página
          {{ paginaAtual }}
          de
          {{ ultimaPagina }}
        </span>

        <button
          class="botao secundario"
          type="button"
          :disabled="
            paginaAtual >=
            ultimaPagina
          "
          @click="
            carregarPendencias(
              paginaAtual + 1,
            )
          "
        >
          Próxima
        </button>
      </footer>
    </section>

    <PendenciaResolucaoModal
      :aberto="modalResolucao"
      :pendencia="pendenciaResolucao"
      :salvando="salvando"
      @fechar="fecharResolucao"
      @confirmar="confirmarResolucao"
    />

    <PendenciaDetalhesModal
      :aberto="modalDetalhes"
      :pendencia="pendenciaDetalhes"
      @fechar="fecharDetalhes"
    />
  </main>
</template>

<style
  scoped
  src="../../styles/pages/pendencia/pendencias.css"
></style>
