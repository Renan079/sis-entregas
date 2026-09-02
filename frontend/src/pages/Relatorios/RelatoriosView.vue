<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { relatorioService } from '@/services/relatorioService.ts'

import type {
  RelatorioFiltros,
  RelatorioResponse,
} from '@/types/Relatorio.ts'

const carregando = ref(false)
const erro = ref('')
const dados = ref<RelatorioResponse | null>(null)
const aba = ref<
  'visao-geral' |
  'entregas' |
  'pendencias' |
  'devolucoes'
>('visao-geral')

const hoje = new Date()
const primeiroDiaMes = new Date(
  hoje.getFullYear(),
  hoje.getMonth(),
  1,
)

const filtros = ref<RelatorioFiltros>({
  inicio: paraInputDate(primeiroDiaMes),
  fim: paraInputDate(hoje),
  regiao_id: null,
  status: null,
})

const maiorStatus = computed(() => {
  if (!dados.value) return 1

  return Math.max(
    1,
    ...dados.value.entregas_por_status.map(
      (item) => item.total,
    ),
  )
})

const maiorRegiao = computed(() => {
  if (!dados.value) return 1

  return Math.max(
    1,
    ...dados.value.entregas_por_regiao.map(
      (item) => item.total,
    ),
  )
})

onMounted(() => {
  carregar()
})

async function carregar() {
  carregando.value = true
  erro.value = ''

  try {
    dados.value =
      await relatorioService.buscar(
        filtros.value,
      )
  } catch (e: any) {
    erro.value =
      e?.response?.data?.message ??
      'Não foi possível carregar os relatórios.'
  } finally {
    carregando.value = false
  }
}

function limparFiltros() {
  filtros.value = {
    inicio: paraInputDate(primeiroDiaMes),
    fim: paraInputDate(hoje),
    regiao_id: null,
    status: null,
  }

  carregar()
}

function paraInputDate(
  data: Date,
): string {
  const ano = data.getFullYear()
  const mes = String(
    data.getMonth() + 1,
  ).padStart(2, '0')
  const dia = String(
    data.getDate(),
  ).padStart(2, '0')

  return `${ano}-${mes}-${dia}`
}

function percentual(
  valor: number,
  total: number,
): number {
  if (!total) return 0

  return Math.min(
    100,
    Math.round(
      (valor / total) * 100,
    ),
  )
}

function labelStatus(
  status: string,
): string {
  const labels: Record<string, string> = {
    PENDENTE: 'Pendente',
    PROGRAMADA: 'Programada',
    EM_SAIDA: 'Em saída',
    PARCIAL: 'Parcial',
    CONCLUIDA: 'Concluída',
    NAO_ENTREGUE: 'Não entregue',
    CANCELADA: 'Cancelada',
  }

  return labels[status] ?? status
}

function labelMotivo(
  motivo: string,
): string {
  const labels: Record<string, string> = {
    CLIENTE_RECUSOU:
      'Cliente recusou',
    CLIENTE_DESISTIU:
      'Cliente desistiu',
    PRODUTO_INCORRETO:
      'Produto incorreto',
    PRODUTO_AVARIADO:
      'Produto avariado',
    QUANTIDADE_EXCEDENTE:
      'Quantidade excedente',
    OUTRO:
      'Outro',
  }

  return labels[motivo] ?? motivo
}

function formatarNumero(
  valor: number,
): string {
  return new Intl.NumberFormat(
    'pt-BR',
    {
      maximumFractionDigits: 3,
    },
  ).format(valor)
}

function formatarDataHora(
  valor: string | null,
): string {
  if (!valor) return '—'

  return new Intl.DateTimeFormat(
    'pt-BR',
    {
      dateStyle: 'short',
      timeStyle: 'short',
    },
  ).format(new Date(valor))
}
</script>

<template>
  <main class="relatorios-page">
    <section class="relatorios-topo">
      <div>
        <span class="eyebrow">
          Gestão operacional
        </span>

        <h1>Relatórios</h1>

        <p>
          Acompanhe o histórico das entregas,
          pendências e devoluções.
        </p>
      </div>

      <button
        class="botao-atualizar"
        type="button"
        :disabled="carregando"
        @click="carregar"
      >
        ↻ Atualizar
      </button>
    </section>

    <section class="filtros-card">
      <div class="campo">
        <label>De</label>
        <input
          v-model="filtros.inicio"
          type="date"
        />
      </div>

      <div class="campo">
        <label>Até</label>
        <input
          v-model="filtros.fim"
          type="date"
        />
      </div>

      <div class="campo">
        <label>Região</label>
        <select v-model="filtros.regiao_id">
          <option :value="null">
            Todas
          </option>

          <option
            v-for="regiao in dados?.opcoes.regioes ?? []"
            :key="regiao.id"
            :value="regiao.id"
          >
            {{ regiao.nome }}
          </option>
        </select>
      </div>

      <div class="campo">
        <label>Status</label>
        <select v-model="filtros.status">
          <option :value="null">
            Todos
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
      </div>

      <div class="filtros-acoes">
        <button
          class="botao-secundario"
          type="button"
          @click="limparFiltros"
        >
          Limpar
        </button>

        <button
          class="botao-aplicar"
          type="button"
          :disabled="carregando"
          @click="carregar"
        >
          Aplicar filtros
        </button>
      </div>
    </section>

    <p
      v-if="erro"
      class="erro"
    >
      {{ erro }}
    </p>

    <div
      v-if="carregando && !dados"
      class="estado"
    >
      Carregando relatórios...
    </div>

    <template v-if="dados">
      <section class="resumo-grid">
        <article class="resumo-card azul">
          <div class="resumo-icone">
            ▣
          </div>

          <div>
            <span>Entregas no período</span>
            <strong>
              {{ dados.resumo.total_entregas }}
            </strong>
            <small>
              {{ dados.periodo.inicio }}
              até
              {{ dados.periodo.fim }}
            </small>
          </div>
        </article>

        <article class="resumo-card verde">
          <div class="resumo-icone">
            ✓
          </div>

          <div>
            <span>Concluídas</span>
            <strong>
              {{ dados.resumo.concluidas }}
            </strong>
            <small>
              {{
                percentual(
                  dados.resumo.concluidas,
                  dados.resumo.total_entregas,
                )
              }}% do período
            </small>
          </div>
        </article>

        <article class="resumo-card laranja">
          <div class="resumo-icone">
            !
          </div>

          <div>
            <span>Pendências abertas</span>
            <strong>
              {{
                dados.resumo
                  .pendencias_abertas
              }}
            </strong>
            <small>
              {{
                dados.resumo
                  .pendencias_programadas
              }}
              programada(s)
            </small>
          </div>
        </article>

        <article class="resumo-card vermelho">
          <div class="resumo-icone">
            ↩
          </div>

          <div>
            <span>Quantidade devolvida</span>
            <strong>
              {{
                formatarNumero(
                  dados.resumo
                    .quantidade_devolvida,
                )
              }}
            </strong>
            <small>
              {{
                dados.devolucoes.registros
              }}
              registro(s)
            </small>
          </div>
        </article>
      </section>

      <nav class="abas">
        <button
          :class="{
            ativo: aba === 'visao-geral',
          }"
          @click="aba = 'visao-geral'"
        >
          Visão geral
        </button>

        <button
          :class="{
            ativo: aba === 'entregas',
          }"
          @click="aba = 'entregas'"
        >
          Entregas
        </button>

        <button
          :class="{
            ativo: aba === 'pendencias',
          }"
          @click="aba = 'pendencias'"
        >
          Pendências / Reentregas
        </button>

        <button
          :class="{
            ativo: aba === 'devolucoes',
          }"
          @click="aba = 'devolucoes'"
        >
          Devoluções
        </button>
      </nav>

      <section
        v-if="aba === 'visao-geral'"
        class="painel-grid"
      >
        <article class="painel">
          <header>
            <div>
              <h2>Entregas por status</h2>
              <p>
                Distribuição das entregas
                cadastradas no período.
              </p>
            </div>
          </header>

          <div class="barras">
            <div
              v-for="item in dados.entregas_por_status"
              :key="item.status"
              class="barra-linha"
            >
              <div class="barra-label">
                <span>
                  {{ labelStatus(item.status) }}
                </span>
                <strong>
                  {{ item.total }}
                </strong>
              </div>

              <div class="barra-trilho">
                <div
                  class="barra-preenchimento"
                  :class="`status-${item.status.toLowerCase()}`"
                  :style="{
                    width:
                      `${percentual(
                        item.total,
                        maiorStatus,
                      )}%`,
                  }"
                ></div>
              </div>
            </div>
          </div>
        </article>

        <article class="painel">
          <header>
            <div>
              <h2>Regiões com maior volume</h2>
              <p>
                Quantidade de entregas
                registradas por região.
              </p>
            </div>
          </header>

          <div
            v-if="
              dados.entregas_por_regiao.length
            "
            class="regioes-lista"
          >
            <div
              v-for="regiao in dados.entregas_por_regiao.slice(0, 7)"
              :key="
                regiao.regiao_id ??
                regiao.regiao_nome
              "
              class="regiao-linha"
            >
              <div class="regiao-topo">
                <strong>
                  {{ regiao.regiao_nome }}
                </strong>

                <span>
                  {{ regiao.total }}
                  entrega(s)
                </span>
              </div>

              <div class="barra-trilho">
                <div
                  class="barra-preenchimento regiao"
                  :style="{
                    width:
                      `${percentual(
                        regiao.total,
                        maiorRegiao,
                      )}%`,
                  }"
                ></div>
              </div>

              <small>
                {{ regiao.taxa_conclusao }}%
                concluídas
              </small>
            </div>
          </div>

          <div
            v-else
            class="estado-menor"
          >
            Nenhuma entrega encontrada.
          </div>
        </article>

        <article class="painel largura-total">
          <header>
            <div>
              <h2>Últimas entregas do período</h2>
              <p>
                Visão rápida dos registros
                mais recentes.
              </p>
            </div>
          </header>

          <div class="tabela-scroll">
            <table>
              <thead>
                <tr>
                  <th>Pedido</th>
                  <th>Cliente</th>
                  <th>Região</th>
                  <th>Status</th>
                  <th>Cadastro</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="entrega in dados.entregas_recentes"
                  :key="entrega.id"
                >
                  <td>
                    #{{ entrega.numero_pedido }}
                  </td>
                  <td>
                    {{ entrega.cliente_nome }}
                  </td>
                  <td>
                    {{ entrega.regiao_nome }}
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="
                        `badge-${entrega.status.toLowerCase()}`
                      "
                    >
                      {{
                        labelStatus(
                          entrega.status,
                        )
                      }}
                    </span>
                  </td>
                  <td>
                    {{
                      formatarDataHora(
                        entrega.data,
                      )
                    }}
                  </td>
                </tr>

                <tr
                  v-if="
                    !dados.entregas_recentes.length
                  "
                >
                  <td
                    colspan="5"
                    class="vazio-tabela"
                  >
                    Nenhuma entrega encontrada
                    para os filtros escolhidos.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </section>

      <section
        v-else-if="aba === 'entregas'"
        class="painel"
      >
        <header>
          <div>
            <h2>Desempenho por região</h2>
            <p>
              Conclusões, parciais e não entregues.
            </p>
          </div>
        </header>

        <div class="tabela-scroll">
          <table>
            <thead>
              <tr>
                <th>Região</th>
                <th>Total</th>
                <th>Concluídas</th>
                <th>Parciais</th>
                <th>Não entregues</th>
                <th>Conclusão</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="regiao in dados.entregas_por_regiao"
                :key="
                  regiao.regiao_id ??
                  regiao.regiao_nome
                "
              >
                <td>
                  <strong>
                    {{ regiao.regiao_nome }}
                  </strong>
                </td>
                <td>{{ regiao.total }}</td>
                <td>{{ regiao.concluidas }}</td>
                <td>{{ regiao.parciais }}</td>
                <td>
                  {{ regiao.nao_entregues }}
                </td>
                <td>
                  <span class="taxa">
                    {{ regiao.taxa_conclusao }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section
        v-else-if="aba === 'pendencias'"
        class="pendencia-grid"
      >
        <article class="mini-card">
          <span>Criadas no período</span>
          <strong>
            {{ dados.pendencias.total_criadas }}
          </strong>
        </article>

        <article class="mini-card laranja">
          <span>Abertas</span>
          <strong>
            {{ dados.pendencias.abertas }}
          </strong>
        </article>

        <article class="mini-card azul">
          <span>Programadas</span>
          <strong>
            {{ dados.pendencias.programadas }}
          </strong>
        </article>

        <article class="mini-card verde">
          <span>Resolvidas</span>
          <strong>
            {{ dados.pendencias.resolvidas }}
          </strong>
        </article>

        <article class="mini-card vermelho">
          <span>Canceladas</span>
          <strong>
            {{ dados.pendencias.canceladas }}
          </strong>
        </article>

        <article class="painel largura-total">
          <header>
            <div>
              <h2>Leitura operacional</h2>
              <p>
                Resumo das pendências e
                reentregas no período.
              </p>
            </div>
          </header>

          <div class="insight">
            <div class="insight-icone">↻</div>

            <div>
              <strong>
                {{
                  dados.pendencias.abertas +
                  dados.pendencias.programadas
                }}
                pendência(s) ainda exigem atenção
              </strong>

              <p>
                Abertas aguardam nova programação.
                Programadas já possuem reentrega
                encaminhada.
              </p>
            </div>
          </div>
        </article>
      </section>

      <section
        v-else
        class="painel-grid"
      >
        <article class="painel">
          <header>
            <div>
              <h2>Devoluções por motivo</h2>
              <p>
                Quantidade devolvida agrupada
                pelo motivo informado.
              </p>
            </div>
          </header>

          <div
            v-if="
              dados.devolucoes.por_motivo.length
            "
            class="devolucoes-lista"
          >
            <div
              v-for="item in dados.devolucoes.por_motivo"
              :key="item.motivo"
              class="devolucao-linha"
            >
              <div>
                <strong>
                  {{ labelMotivo(item.motivo) }}
                </strong>
                <span>
                  {{ item.registros }}
                  registro(s)
                </span>
              </div>

              <b>
                {{
                  formatarNumero(
                    item.quantidade,
                  )
                }}
              </b>
            </div>
          </div>

          <div
            v-else
            class="estado-menor"
          >
            Nenhuma devolução no período.
          </div>
        </article>

        <article class="painel">
          <header>
            <div>
              <h2>Resumo de devoluções</h2>
              <p>
                Volume devolvido nas entregas
                filtradas.
              </p>
            </div>
          </header>

          <div class="devolucao-destaque">
            <span>Quantidade total</span>
            <strong>
              {{
                formatarNumero(
                  dados.devolucoes
                    .quantidade_total,
                )
              }}
            </strong>
            <small>
              {{
                dados.devolucoes.registros
              }}
              ocorrência(s) registradas
            </small>
          </div>
        </article>
      </section>

      <footer class="relatorios-rodape">
        Atualizado em
        {{ formatarDataHora(dados.gerado_em) }}
      </footer>
    </template>
  </main>
</template>

<style scoped>
.relatorios-page,
.relatorios-page * {
  box-sizing: border-box;
}

.relatorios-page {
  --navy: #0b1f44;
  --blue: #155eef;
  --blue-soft: #eff6ff;
  --orange: #f97316;
  --orange-soft: #fff4e8;
  --green: #039855;
  --red: #d92d20;
  --ink: #132238;
  --muted: #667085;
  --border: #e4e7ec;
  --surface: #ffffff;
  --background: #f5f7fb;

  min-height: 100%;
  padding: 28px;
  background:
    radial-gradient(
      circle at 88% 0%,
      rgba(21, 94, 239, .08),
      transparent 30%
    ),
    var(--background);
  color: var(--ink);
}

.relatorios-topo {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 20px;
}

.eyebrow {
  display: block;
  margin-bottom: 6px;
  color: var(--blue);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
}

h1,
h2,
p {
  margin: 0;
}

h1 {
  font-size: 30px;
  line-height: 1.15;
}

.relatorios-topo p,
.painel header p {
  margin-top: 6px;
  color: var(--muted);
  font-size: 13px;
}

.botao-atualizar,
.botao-aplicar,
.botao-secundario {
  min-height: 42px;
  padding: 0 16px;
  border-radius: 10px;
  font-weight: 800;
  cursor: pointer;
}

.botao-atualizar {
  border: 1px solid #bfd3ff;
  background: #fff;
  color: var(--blue);
}

.botao-aplicar {
  border: 1px solid var(--blue);
  background: var(--blue);
  color: #fff;
}

.botao-secundario {
  border: 1px solid var(--border);
  background: #fff;
  color: #344054;
}

button:disabled {
  opacity: .55;
  cursor: default;
}

.filtros-card {
  display: grid;
  grid-template-columns:
    160px
    160px
    minmax(180px, 1fr)
    minmax(180px, 1fr)
    auto;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--surface);
  box-shadow: 0 8px 24px rgba(16, 24, 40, .05);
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.campo label {
  color: #344054;
  font-size: 12px;
  font-weight: 800;
}

.campo input,
.campo select {
  width: 100%;
  height: 42px;
  padding: 0 11px;
  border: 1px solid #d0d5dd;
  border-radius: 9px;
  outline: none;
  background: #fff;
  color: var(--ink);
}

.campo input:focus,
.campo select:focus {
  border-color: #84adff;
  box-shadow: 0 0 0 3px rgba(21, 94, 239, .1);
}

.filtros-acoes {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.resumo-grid {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-top: 18px;
}

.resumo-card {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 130px;
  padding: 18px;
  overflow: hidden;
  border: 1px solid #dbe7ff;
  border-radius: 16px;
  background:
    linear-gradient(
      135deg,
      #fff 45%,
      #f1f6ff
    );
  box-shadow: 0 8px 24px rgba(16, 24, 40, .05);
}

.resumo-card.laranja {
  border-color: #ffd6ae;
  background:
    linear-gradient(
      135deg,
      #fff 45%,
      #fff4e8
    );
}

.resumo-card.verde {
  border-color: #b7ebcd;
  background:
    linear-gradient(
      135deg,
      #fff 45%,
      #ecfdf3
    );
}

.resumo-card.vermelho {
  border-color: #fecdca;
  background:
    linear-gradient(
      135deg,
      #fff 45%,
      #fff1f0
    );
}

.resumo-icone {
  display: grid;
  flex: 0 0 48px;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: #dbe8ff;
  color: var(--blue);
  font-size: 20px;
  font-weight: 900;
}

.resumo-card.laranja .resumo-icone {
  background: #ffead5;
  color: var(--orange);
}

.resumo-card.verde .resumo-icone {
  background: #d1fadf;
  color: var(--green);
}

.resumo-card.vermelho .resumo-icone {
  background: #fee4e2;
  color: var(--red);
}

.resumo-card > div:last-child {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.resumo-card span {
  color: #344054;
  font-size: 12px;
  font-weight: 800;
}

.resumo-card strong {
  margin: 4px 0 2px;
  font-size: 29px;
  line-height: 1;
}

.resumo-card small {
  color: #98a2b3;
  font-size: 11px;
}

.abas {
  display: flex;
  gap: 6px;
  margin: 20px 0 14px;
  padding: 5px;
  width: fit-content;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #fff;
}

.abas button {
  padding: 9px 14px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #667085;
  font-weight: 800;
  cursor: pointer;
}

.abas button.ativo {
  background: var(--navy);
  color: #fff;
  box-shadow: 0 4px 12px rgba(11, 31, 68, .18);
}

.painel-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.painel,
.mini-card {
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--surface);
  box-shadow: 0 8px 24px rgba(16, 24, 40, .045);
}

.painel {
  padding: 18px;
}

.painel.largura-total {
  grid-column: 1 / -1;
}

.painel header {
  padding-bottom: 14px;
  border-bottom: 1px solid #f0f1f3;
}

.painel h2 {
  font-size: 17px;
}

.barras,
.regioes-lista,
.devolucoes-lista {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 16px;
}

.barra-label,
.regiao-topo,
.devolucao-linha {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.barra-label {
  margin-bottom: 6px;
  font-size: 12px;
}

.barra-trilho {
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: #f2f4f7;
}

.barra-preenchimento {
  height: 100%;
  min-width: 3px;
  border-radius: inherit;
  background: var(--blue);
}

.status-concluida {
  background: var(--green);
}

.status-parcial {
  background: var(--orange);
}

.status-nao_entregue,
.status-cancelada {
  background: var(--red);
}

.status-programada,
.status-em_saida {
  background: var(--blue);
}

.status-pendente {
  background: #7a5af8;
}

.barra-preenchimento.regiao {
  background:
    linear-gradient(
      90deg,
      var(--blue),
      #69a1ff
    );
}

.regiao-linha small {
  display: block;
  margin-top: 5px;
  color: #98a2b3;
  font-size: 11px;
}

.tabela-scroll {
  overflow-x: auto;
  margin-top: 10px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 12px 10px;
  border-bottom: 1px solid #f0f1f3;
  text-align: left;
  white-space: nowrap;
}

th {
  color: #667085;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

td {
  color: #344054;
  font-size: 12px;
}

.badge,
.taxa {
  display: inline-flex;
  align-items: center;
  padding: 5px 8px;
  border-radius: 999px;
  background: #eef4ff;
  color: var(--blue);
  font-size: 10px;
  font-weight: 900;
}

.badge-concluida {
  background: #dcfae6;
  color: #067647;
}

.badge-parcial {
  background: #ffead5;
  color: #b54708;
}

.badge-nao_entregue,
.badge-cancelada {
  background: #fee4e2;
  color: #b42318;
}

.pendencia-grid {
  display: grid;
  grid-template-columns:
    repeat(5, minmax(0, 1fr));
  gap: 14px;
}

.mini-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 18px;
  border-top: 3px solid #98a2b3;
}

.mini-card span {
  color: #667085;
  font-size: 12px;
  font-weight: 800;
}

.mini-card strong {
  font-size: 28px;
}

.mini-card.laranja {
  border-top-color: var(--orange);
}

.mini-card.azul {
  border-top-color: var(--blue);
}

.mini-card.verde {
  border-top-color: var(--green);
}

.mini-card.vermelho {
  border-top-color: var(--red);
}

.insight {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  padding: 16px;
  border-radius: 12px;
  background: var(--orange-soft);
}

.insight-icone {
  display: grid;
  flex: 0 0 44px;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: #fff;
  color: var(--orange);
  font-size: 22px;
}

.insight p {
  margin-top: 5px;
  color: #7a2e0e;
  font-size: 12px;
}

.devolucao-linha {
  padding: 11px 0;
  border-bottom: 1px solid #f0f1f3;
}

.devolucao-linha > div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.devolucao-linha span {
  color: #98a2b3;
  font-size: 11px;
}

.devolucao-linha b {
  color: var(--orange);
  font-size: 16px;
}

.devolucao-destaque {
  display: flex;
  min-height: 210px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top: 14px;
  border-radius: 14px;
  background:
    radial-gradient(
      circle,
      #fff3e8,
      #fff
    );
}

.devolucao-destaque span,
.devolucao-destaque small {
  color: #667085;
}

.devolucao-destaque strong {
  margin: 8px 0;
  color: var(--orange);
  font-size: 46px;
}

.estado,
.estado-menor,
.vazio-tabela {
  color: #98a2b3;
  text-align: center;
}

.estado {
  padding: 70px 20px;
}

.estado-menor {
  padding: 55px 20px;
}

.erro {
  margin-top: 16px;
  padding: 12px 14px;
  border: 1px solid #fecdca;
  border-radius: 10px;
  background: #fef3f2;
  color: #b42318;
  font-size: 12px;
}

.relatorios-rodape {
  padding: 20px 0 2px;
  color: #98a2b3;
  font-size: 11px;
  text-align: right;
}

@media (max-width: 1180px) {
  .filtros-card {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .filtros-acoes {
    grid-column: 1 / -1;
  }

  .resumo-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .pendencia-grid {
    grid-template-columns:
      repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .relatorios-page {
    padding: 18px;
  }

  .relatorios-topo {
    flex-direction: column;
  }

  .filtros-card,
  .resumo-grid,
  .painel-grid,
  .pendencia-grid {
    grid-template-columns: 1fr;
  }

  .filtros-acoes {
    grid-column: auto;
  }

  .abas {
    width: 100%;
    overflow-x: auto;
  }

  .abas button {
    white-space: nowrap;
  }

  .painel.largura-total {
    grid-column: auto;
  }
}
</style>
