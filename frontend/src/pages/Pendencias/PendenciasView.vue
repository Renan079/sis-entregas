<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

import { pendenciaService } from '@/services/pendenciaService.ts'
import { regiaoService } from '@/services/regiaoService.ts'
import { saidaService } from '@/services/saidaService.ts'

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

const formularioResolucao = reactive({
  motivo_devolucao: '' as MotivoDevolucao | '',
  observacao: '',
})

const modalDetalhes = ref(false)
const pendenciaDetalhes = ref<Pendencia | null>(null)

const motivos: {
  valor: MotivoDevolucao
  label: string
}[] = [
  {
    valor: 'CLIENTE_DESISTIU',
    label: 'Cliente desistiu do produto',
  },
  {
    valor: 'CLIENTE_RECUSOU',
    label: 'Cliente recusou o produto',
  },
  {
    valor: 'PRODUTO_INCORRETO',
    label: 'Produto incorreto',
  },
  {
    valor: 'PRODUTO_AVARIADO',
    label: 'Produto avariado',
  },
  {
    valor: 'QUANTIDADE_EXCEDENTE',
    label: 'Quantidade excedente',
  },
  {
    valor: 'OUTRO',
    label: 'Outro',
  },
]

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

  formularioResolucao.motivo_devolucao =
    'CLIENTE_DESISTIU'

  formularioResolucao.observacao = ''

  modalResolucao.value = true
}

function fecharResolucao() {
  if (salvando.value) {
    return
  }

  modalResolucao.value = false
  pendenciaResolucao.value = null
  formularioResolucao.motivo_devolucao = ''
  formularioResolucao.observacao = ''
}

async function confirmarResolucao() {
  if (
    !pendenciaResolucao.value ||
    !formularioResolucao
      .motivo_devolucao
  ) {
    erro.value =
      'Informe o motivo da resolução manual.'

    return
  }

  salvando.value = true
  erro.value = ''
  sucesso.value = ''

  try {
    await pendenciaService
      .resolverManualmente(
        pendenciaResolucao.value.id,
        {
          motivo_devolucao:
            formularioResolucao
              .motivo_devolucao,

          observacao:
            formularioResolucao
              .observacao.trim() ||
            null,
        },
      )

    modalResolucao.value = false
    pendenciaResolucao.value = null
    formularioResolucao.motivo_devolucao = ''
    formularioResolucao.observacao = ''

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

function motivoLabel(
  motivo: string | null,
) {
  if (!motivo) {
    return '—'
  }

  const encontrado =
    motivos.find(
      (item) =>
        item.valor === motivo,
    )

  return encontrado?.label ?? motivo
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
                      : `Pedido #${pendencia.entrega_id}`
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
                    pendencia.status ===
                      'RESOLVIDA'
                      ? 'Resolvida'
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

    <!-- RESOLUÇÃO MANUAL -->
    <div
      v-if="
        modalResolucao &&
        pendenciaResolucao
      "
      class="modal-fundo"
      @click.self="
        fecharResolucao
      "
    >
      <section class="modal">
        <header class="modal-cabecalho">
          <div>
            <span class="eyebrow">
              Encerramento de reentrega
            </span>

            <h2>
              Resolver pendência
              manualmente
            </h2>
          </div>

          <button
            class="fechar"
            type="button"
            :disabled="salvando"
            @click="
              fecharResolucao
            "
          >
            ×
          </button>
        </header>

        <div class="aviso-resolucao">
          <strong>
            Esta ação encerra a pendência
            sem nova reentrega.
          </strong>

          <span>
            O saldo pendente será
            registrado como devolução e
            deixará de aparecer como
            obrigação logística.
          </span>
        </div>

        <div class="produto-resolucao">
          <small>Pedido / Cliente</small>

          <strong>
            {{
              pendenciaResolucao
                .numero_pedido
                ? `#${pendenciaResolucao.numero_pedido}`
                : `Pedido #${pendenciaResolucao.entrega_id}`
            }}
            ·
            {{
              pendenciaResolucao
                .cliente_nome
            }}
          </strong>
        </div>

        <div class="campo">
          <label>Produto</label>

          <div class="campo-leitura">
            {{
              pendenciaResolucao
                .produto.descricao
            }}

            <span
              v-if="
                pendenciaResolucao
                  .produto.codigo
              "
            >
              Ref:
              {{
                pendenciaResolucao
                  .produto.codigo
              }}
            </span>
          </div>
        </div>

        <div class="campo">
          <label>
            Quantidade pendente
          </label>

          <div class="campo-leitura forte">
            {{
              formatarQuantidade(
                pendenciaResolucao
                  .saldo_pendente,
              )
            }}
            {{
              pendenciaResolucao
                .produto.unidade ??
              ''
            }}
          </div>
        </div>

        <div class="campo">
          <label>
            Motivo da devolução *
          </label>

          <select
            v-model="
              formularioResolucao
                .motivo_devolucao
            "
            required
          >
            <option
              v-for="
                motivo in motivos
              "
              :key="motivo.valor"
              :value="motivo.valor"
            >
              {{ motivo.label }}
            </option>
          </select>
        </div>

        <div class="campo">
          <label>
            Observação
          </label>

          <textarea
            v-model="
              formularioResolucao
                .observacao
            "
            rows="4"
            maxlength="500"
            placeholder="Ex.: Cliente compareceu à loja e desistiu do restante."
          ></textarea>

          <small class="contador">
            {{
              formularioResolucao
                .observacao.length
            }}/500
          </small>
        </div>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            :disabled="salvando"
            @click="
              fecharResolucao
            "
          >
            Cancelar
          </button>

          <button
            class="botao perigo"
            type="button"
            :disabled="salvando"
            @click="
              confirmarResolucao
            "
          >
            {{
              salvando
                ? 'Resolvendo...'
                : 'Confirmar resolução'
            }}
          </button>
        </footer>
      </section>
    </div>

    <!-- DETALHES -->
    <div
      v-if="
        modalDetalhes &&
        pendenciaDetalhes
      "
      class="modal-fundo"
      @click.self="
        fecharDetalhes
      "
    >
      <section class="modal detalhes-modal">
        <header class="modal-cabecalho">
          <div>
            <span class="eyebrow">
              Pendência
              #{{ pendenciaDetalhes.id }}
            </span>

            <h2>
              {{
                pendenciaDetalhes
                  .produto.descricao
              }}
            </h2>

            <p>
              {{
                pendenciaDetalhes
                  .numero_pedido
                  ? `Pedido #${pendenciaDetalhes.numero_pedido}`
                  : `Pedido #${pendenciaDetalhes.entrega_id}`
              }}
              ·
              {{
                pendenciaDetalhes
                  .cliente_nome
              }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="
              fecharDetalhes
            "
          >
            ×
          </button>
        </header>

        <div class="detalhes-grid">
          <div>
            <small>Status</small>

            <strong>
              {{
                statusLabel(
                  pendenciaDetalhes.status,
                )
              }}
            </strong>
          </div>

          <div>
            <small>Região</small>

            <strong>
              {{
                pendenciaDetalhes
                  .regiao.nome
              }}
            </strong>
          </div>

          <div>
            <small>
              Quantidade original
            </small>

            <strong>
              {{
                formatarQuantidade(
                  pendenciaDetalhes
                    .quantidade_original,
                )
              }}
            </strong>
          </div>

          <div>
            <small>
              Quantidade resolvida
            </small>

            <strong>
              {{
                formatarQuantidade(
                  pendenciaDetalhes
                    .quantidade_resolvida,
                )
              }}
            </strong>
          </div>

          <div>
            <small>Saldo pendente</small>

            <strong>
              {{
                formatarQuantidade(
                  pendenciaDetalhes
                    .saldo_pendente,
                )
              }}
            </strong>
          </div>

          <div>
            <small>Aberta em</small>

            <strong>
              {{
                formatarDataHora(
                  pendenciaDetalhes
                    .data_abertura,
                )
              }}
            </strong>
          </div>
        </div>

        <div
          v-if="
            pendenciaDetalhes
              .carrada_atual
          "
          class="carrada-detalhe"
        >
          <small>
            Programação atual
          </small>

          <strong>
            Carrada
            #{{
              pendenciaDetalhes
                .carrada_atual.id
            }}
          </strong>

          <span>
            {{
              pendenciaDetalhes
                .carrada_atual.status ===
                'PLANEJADA'
                ? 'Planejada'
                : 'Em andamento'
            }}
          </span>
        </div>

        <div class="detalhe-texto">
          <small>Motivo</small>

          <p>
            {{
              motivoLabel(
                pendenciaDetalhes
                  .motivo,
              )
            }}
          </p>
        </div>

        <div class="detalhe-texto">
          <small>Observação</small>

          <p>
            {{
              pendenciaDetalhes
                .observacao ||
              'Nenhuma observação registrada.'
            }}
          </p>
        </div>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            @click="
              fecharDetalhes
            "
          >
            Fechar
          </button>
        </footer>
      </section>
    </div>
  </main>
</template>

<style scoped>
.pagina-pendencias {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  color: #172033;
}

.cabecalho {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
}

.cabecalho h1 {
  margin: 4px 0 6px;
  font-size: clamp(1.6rem, 3vw, 2.1rem);
  line-height: 1.1;
}

.cabecalho p,
.modal-cabecalho p {
  margin: 0;
  color: #667085;
}

.eyebrow {
  color: #2563eb;
  font-size: 0.76rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.resumo-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.resumo-card {
  display: flex;
  gap: 14px;
  align-items: center;
  min-height: 112px;
  padding: 20px;
  border: 1px solid #e4e7ec;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 2px 10px rgb(16 24 40 / 4%);
}

.resumo-card .icone {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  flex: 0 0 46px;
  border-radius: 50%;
  font-weight: 900;
  font-size: 1.3rem;
}

.resumo-card strong {
  display: block;
  font-size: 1.55rem;
  line-height: 1;
}

.resumo-card span {
  display: block;
  margin-top: 6px;
  font-weight: 700;
}

.resumo-card small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.resumo-abertas .icone {
  color: #b42318;
  background: #fee4e2;
}

.resumo-programadas .icone {
  color: #b54708;
  background: #fef0c7;
}

.resumo-resolvidas .icone {
  color: #027a48;
  background: #d1fadf;
}

.resumo-total .icone {
  color: #175cd3;
  background: #dbeafe;
}

.card {
  border: 1px solid #e4e7ec;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 2px 10px rgb(16 24 40 / 4%);
}

.filtros {
  padding: 18px;
}

.grid-filtros {
  display: grid;
  grid-template-columns:
    minmax(320px, 2.1fr)
    minmax(150px, 0.75fr)
    minmax(170px, 0.8fr)
    minmax(155px, 0.72fr)
    minmax(155px, 0.72fr)
    max-content;
  gap: 14px;
  align-items: end;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
}

.campo label {
  font-size: 0.82rem;
  font-weight: 700;
  color: #344054;
}

.campo input,
.campo select,
.campo textarea,
.por-pagina select {
  width: 100%;
  height: 44px;
  min-height: 44px;
  box-sizing: border-box;
  border: 1px solid #d0d5dd;
  border-radius: 9px;
  padding: 0 12px;
  background: #fff;
  color: #101828;
  font: inherit;
  outline: none;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.campo input[type='date'] {
  padding-right: 10px;
}

.campo textarea {
  height: auto;
  min-height: 100px;
  padding: 10px 12px;
  resize: vertical;
}

.campo input:focus,
.campo select:focus,
.campo textarea:focus,
.por-pagina select:focus {
  border-color: #84adff;
  box-shadow: 0 0 0 3px rgb(37 99 235 / 10%);
}

.acoes-filtro {
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  gap: 10px;
  height: 44px;
}

.acoes-filtro .botao {
  height: 44px;
  min-height: 44px;
  padding: 0 18px;
  white-space: nowrap;
  flex-shrink: 0;
}

.acoes-filtro .primario {
  min-width: 94px;
}

.acoes-filtro .secundario {
  min-width: 142px;
}

.botao,
.acao {
  border: 1px solid transparent;
  border-radius: 9px;
  padding: 9px 13px;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  transition:
    transform 0.15s ease,
    background 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.botao:hover:not(:disabled),
.acao:hover:not(:disabled) {
  transform: translateY(-1px);
}

.botao:disabled,
.acao:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.botao.primario {
  color: #fff;
  background: #175cd3;
}

.botao.primario:hover:not(:disabled) {
  background: #1849a9;
  box-shadow: 0 4px 10px rgb(23 92 211 / 18%);
}

.botao.secundario {
  color: #344054;
  border-color: #d0d5dd;
  background: #fff;
}

.botao.secundario:hover:not(:disabled) {
  background: #f9fafb;
}

.botao.perigo {
  color: #fff;
  background: #d92d20;
}

.mensagem {
  margin: 0;
  border-radius: 10px;
  padding: 11px 14px;
  font-weight: 600;
}

.mensagem.erro {
  color: #b42318;
  border: 1px solid #fecdca;
  background: #fef3f2;
}

.mensagem.sucesso {
  color: #027a48;
  border: 1px solid #abefc6;
  background: #ecfdf3;
}

.tabela-card {
  overflow: hidden;
}

.topo-tabela {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
  padding: 16px 18px;
  border-bottom: 1px solid #eaecf0;
}

.topo-tabela > div:first-child {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.topo-tabela span {
  color: #667085;
  font-size: 0.82rem;
}

.por-pagina {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #667085;
  font-size: 0.82rem;
}

.por-pagina select {
  width: auto;
  min-height: 36px;
  height: 36px;
  padding: 0 28px 0 9px;
}

.tabela-container {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1120px;
  border-collapse: collapse;
}

th,
td {
  padding: 15px 16px;
  text-align: left;
  vertical-align: middle;
  border-bottom: 1px solid #eaecf0;
}

th {
  color: #475467;
  background: #f9fafb;
  font-size: 0.78rem;
  font-weight: 800;
}

td {
  color: #344054;
  font-size: 0.9rem;
}

td strong {
  color: #101828;
}

td small,
.linha-secundaria {
  display: block;
  margin-top: 4px;
  color: #667085;
}

.quantidades {
  display: flex;
  flex-direction: column;
}

.status {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  border-radius: 999px;
  padding: 4px 9px;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
}

.status.aberta {
  color: #b42318;
  background: #fee4e2;
}

.status.programada {
  color: #b54708;
  background: #fef0c7;
}

.status.resolvida {
  color: #027a48;
  background: #d1fadf;
}

.status.cancelada {
  color: #475467;
  background: #eaecf0;
}

.carrada-atual {
  margin-top: 7px;
}

.tempo {
  font-weight: 700;
}

.tempo.atencao {
  color: #b54708;
}

.tempo.atrasada {
  color: #b42318;
}

.acoes-coluna {
  width: 260px;
}

.acoes {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}

.acao {
  padding: 7px 10px;
  color: #344054;
  border-color: #d0d5dd;
  background: #fff;
  font-size: 0.78rem;
}

.acao.resolver {
  color: #175cd3;
  border-color: #84adff;
  background: #eff8ff;
}

.acao.remover {
  color: #b42318;
  border-color: #fda29b;
  background: #fff5f4;
}

.acao-bloqueada {
  display: inline-flex;
  align-items: center;
  border-radius: 8px;
  padding: 7px 10px;
  color: #667085;
  background: #f2f4f7;
  font-size: 0.76rem;
  font-weight: 700;
}

.estado {
  padding: 46px 20px;
  text-align: center;
  color: #667085;
}

.paginacao {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  align-items: center;
  padding: 14px 18px;
}

.modal-fundo {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(16 24 40 / 56%);
  backdrop-filter: blur(3px);
}

.modal {
  width: min(560px, 100%);
  max-height: calc(100vh - 40px);
  overflow-y: auto;
  border-radius: 15px;
  background: #fff;
  box-shadow: 0 24px 80px rgb(16 24 40 / 28%);
}

.detalhes-modal {
  width: min(700px, 100%);
}

.modal-cabecalho {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: flex-start;
  padding: 20px 22px 16px;
  border-bottom: 1px solid #eaecf0;
}

.modal-cabecalho h2 {
  margin: 4px 0 4px;
  font-size: 1.25rem;
}

.fechar {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #667085;
  font-size: 1.5rem;
  cursor: pointer;
}

.fechar:hover {
  background: #f2f4f7;
}

.aviso-resolucao {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin: 18px 22px 0;
  padding: 13px 14px;
  border: 1px solid #fedf89;
  border-radius: 10px;
  color: #7a2e0e;
  background: #fffaeb;
}

.aviso-resolucao span {
  color: #93370d;
  font-size: 0.84rem;
}

.produto-resolucao,
.modal > .campo,
.detalhe-texto,
.carrada-detalhe {
  margin-left: 22px;
  margin-right: 22px;
}

.produto-resolucao {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 18px;
  margin-bottom: 16px;
}

.produto-resolucao small,
.detalhe-texto small,
.carrada-detalhe small,
.detalhes-grid small {
  color: #667085;
  font-weight: 700;
}

.modal > .campo {
  margin-bottom: 15px;
}

.campo-leitura {
  min-height: 42px;
  border: 1px solid #e4e7ec;
  border-radius: 9px;
  padding: 10px 11px;
  background: #f9fafb;
}

.campo-leitura span {
  display: block;
  margin-top: 4px;
  color: #667085;
  font-size: 0.8rem;
}

.campo-leitura.forte {
  font-weight: 800;
}

.contador {
  align-self: flex-end;
  color: #98a2b3;
}

.modal-acoes {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 16px 22px 20px;
  border-top: 1px solid #eaecf0;
}

.detalhes-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  padding: 20px 22px;
}

.detalhes-grid > div {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-height: 76px;
  padding: 13px;
  border: 1px solid #eaecf0;
  border-radius: 10px;
  background: #f9fafb;
}

.carrada-detalhe {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 15px;
  padding: 13px;
  border: 1px solid #b2ddff;
  border-radius: 10px;
  background: #eff8ff;
}

.carrada-detalhe span {
  color: #175cd3;
  font-size: 0.84rem;
}

.detalhe-texto {
  margin-bottom: 15px;
}

.detalhe-texto p {
  margin: 5px 0 0;
  line-height: 1.5;
}

@media (max-width: 1400px) {
  .grid-filtros {
    grid-template-columns:
      minmax(280px, 1.7fr)
      minmax(140px, 0.75fr)
      minmax(160px, 0.8fr)
      minmax(145px, 0.72fr)
      minmax(145px, 0.72fr)
      max-content;
    gap: 10px;
  }

  .acoes-filtro .botao {
    padding: 0 14px;
  }

  .acoes-filtro .primario {
    min-width: 88px;
  }

  .acoes-filtro .secundario {
    min-width: 128px;
  }
}

@media (max-width: 1180px) {
  .resumo-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .grid-filtros {
    grid-template-columns:
      repeat(3, minmax(0, 1fr));
  }

  .acoes-filtro {
    height: auto;
    justify-content: flex-start;
  }

  .acoes-filtro .botao {
    flex: 1;
  }
}

@media (max-width: 720px) {
  .pagina-pendencias {
    padding: 16px;
  }

  .resumo-grid {
    grid-template-columns: 1fr;
  }

  .grid-filtros {
    grid-template-columns: 1fr;
  }

  .acoes-filtro {
    flex-direction: column;
    align-items: stretch;
  }

  .acoes-filtro .botao {
    width: 100%;
  }

  .topo-tabela {
    align-items: flex-start;
    flex-direction: column;
  }

  .paginacao {
    justify-content: space-between;
  }

  .modal-fundo {
    padding: 10px;
  }

  .detalhes-grid {
    grid-template-columns: 1fr;
  }

  .modal-acoes {
    flex-direction: column-reverse;
  }

  .modal-acoes .botao {
    width: 100%;
  }
}
</style>
