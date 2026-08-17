<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { dashboardService } from '@/services/dashboardService'
import { useAuthStore } from '@/stores/auth'

import type {
  DashboardCarrada,
  DashboardData,
} from '@/types/Dashboard'

const auth = useAuthStore()
const router = useRouter()

const dashboard =
  ref<DashboardData | null>(null)

const carregando = ref(false)
const erro = ref('')

const primeiroNome = computed(() => {
  const nome =
    auth.user?.name?.trim()

  if (!nome) {
    return ''
  }

  return nome.split(/\s+/)[0] ?? nome
})

const maiorTotalRegiao = computed(() => {
  if (
    !dashboard.value ||
    dashboard.value
      .entregas_por_regiao.length === 0
  ) {
    return 1
  }

  return Math.max(
    ...dashboard.value
      .entregas_por_regiao
      .map((regiao) => regiao.total),
    1,
  )
})

async function carregarDashboard() {
  carregando.value = true
  erro.value = ''

  try {
    dashboard.value =
      await dashboardService.buscar()
  } catch {
    erro.value =
      'Não foi possível carregar a dashboard.'
  } finally {
    carregando.value = false
  }
}

async function sair() {
  await auth.logout()
  await router.push('/login')
}

function irParaEntregas() {
  router.push('/entregas')
}

function irParaSaidas() {
  router.push('/saidas')
}

function irParaPendencias() {
  router.push('/pendencias')
}

function percentualRegiao(
  total: number,
) {
  return Math.max(
    7,
    Math.round(
      (
        total /
        maiorTotalRegiao.value
      ) * 100,
    ),
  )
}

function statusCarradaLabel(
  status: DashboardCarrada['status'],
) {
  const labels: Record<
    DashboardCarrada['status'],
    string
  > = {
    PLANEJADA: 'Planejada',
    EM_ANDAMENTO: 'Em andamento',
    FINALIZADA: 'Finalizada',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function rotaCarrada(
  carrada: DashboardCarrada,
) {
  if (carrada.rota.length > 0) {
    return carrada.rota.join(' → ')
  }

  return (
    carrada.regiao_principal ??
    'Sem rota definida'
  )
}

function formatarQuantidade(
  valor: number,
) {
  return new Intl.NumberFormat(
    'pt-BR',
    {
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

  if (
    Number.isNaN(
      valor.getTime(),
    )
  ) {
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

function mesAtualLabel() {
  const texto =
    new Intl.DateTimeFormat(
      'pt-BR',
      {
        month: 'long',
        year: 'numeric',
      },
    ).format(new Date())

  return (
    texto.charAt(0).toUpperCase() +
    texto.slice(1)
  )
}

function hojeLabel() {
  const texto =
    new Intl.DateTimeFormat(
      'pt-BR',
      {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
      },
    ).format(new Date())

  return (
    texto.charAt(0).toUpperCase() +
    texto.slice(1)
  )
}

onMounted(() => {
  carregarDashboard()
})
</script>

<template>
  <main class="dashboard">
    <header class="dashboard-cabecalho">
      <div>
        <span class="data-hoje">
          {{ hojeLabel() }}
        </span>

        <h1>
          <template v-if="primeiroNome">
            Olá, {{ primeiroNome }}
          </template>

          <template v-else>
            Dashboard
          </template>
        </h1>

        <p>
          Visão operacional das entregas
          e carradas que precisam da sua
          atenção.
        </p>
      </div>

      <div class="cabecalho-acoes">
        <button
          class="botao secundario"
          type="button"
          :disabled="carregando"
          @click="carregarDashboard"
        >
          {{
            carregando
              ? 'Atualizando...'
              : 'Atualizar'
          }}
        </button>

        <button
          class="botao sair"
          type="button"
          @click="sair"
        >
          Sair
        </button>
      </div>
    </header>

    <p
      v-if="erro"
      class="mensagem erro"
    >
      {{ erro }}
    </p>

    <div
      v-if="
        carregando &&
        !dashboard
      "
      class="carregando-principal"
    >
      Carregando dashboard...
    </div>

    <template v-else-if="dashboard">
      <section class="metricas-grid">
        <article
          class="metrica-card principal"
          role="button"
          tabindex="0"
          @click="irParaEntregas"
          @keydown.enter="irParaEntregas"
        >
          <div class="metrica-icone">
            ✓
          </div>

          <div class="metrica-conteudo">
            <span>
              Entregas realizadas no mês
            </span>

            <strong>
              {{
                dashboard.resumo
                  .entregas_realizadas_mes
              }}
            </strong>

            <small>
              {{ mesAtualLabel() }}
            </small>
          </div>
        </article>

        <article
          class="metrica-card"
          role="button"
          tabindex="0"
          @click="irParaEntregas"
          @keydown.enter="irParaEntregas"
        >
          <div class="metrica-icone">
            →
          </div>

          <div class="metrica-conteudo">
            <span>
              Aguardando carrada
            </span>

            <strong>
              {{
                dashboard.resumo
                  .aguardando_programacao
              }}
            </strong>

            <small>
              Pedidos ainda sem programação
            </small>
          </div>
        </article>

        <article
          class="metrica-card"
          role="button"
          tabindex="0"
          @click="irParaSaidas"
          @keydown.enter="irParaSaidas"
        >
          <div class="metrica-icone">
            ▦
          </div>

          <div class="metrica-conteudo">
            <span>
              Carradas de hoje
            </span>

            <strong>
              {{
                dashboard.resumo
                  .carradas_hoje
              }}
            </strong>

            <small>
              {{
                dashboard.resumo
                  .carradas_planejadas_hoje
              }}
              planejada(s) ·
              {{
                dashboard.resumo
                  .carradas_em_andamento_hoje
              }}
              em andamento
            </small>
          </div>
        </article>

        <article
          class="metrica-card"
          role="button"
          tabindex="0"
          @click="irParaPendencias"
          @keydown.enter="irParaPendencias"
        >
          <div class="metrica-icone">
            ↻
          </div>

          <div class="metrica-conteudo">
            <span>
              Reentregas abertas
            </span>

            <strong>
              {{
                dashboard.resumo
                  .reentregas_abertas
              }}
            </strong>

            <small>
              Aguardando nova programação
            </small>
          </div>
        </article>

        <article
          class="metrica-card problema"
          role="button"
          tabindex="0"
          @click="irParaEntregas"
          @keydown.enter="irParaEntregas"
        >
          <div class="metrica-icone">
            !
          </div>

          <div class="metrica-conteudo">
            <span>
              Pedidos com problema
            </span>

            <strong>
              {{
                dashboard.resumo
                  .pedidos_com_problema
              }}
            </strong>

            <small>
              Parciais ou não entregues
            </small>
          </div>
        </article>
      </section>

      <section class="dashboard-grid">
        <article class="painel">
          <header class="painel-cabecalho">
            <div>
              <h2>
                Entregas para organizar
                por região
              </h2>

              <p>
                Onde existe maior volume
                aguardando carrada.
              </p>
            </div>

            <button
              class="link-acao"
              type="button"
              @click="irParaEntregas"
            >
              Ver entregas
            </button>
          </header>

          <div
            v-if="
              dashboard
                .entregas_por_regiao
                .length === 0
            "
            class="estado-vazio"
          >
            Nenhuma entrega aguardando
            programação.
          </div>

          <div
            v-else
            class="regioes-lista"
          >
            <button
              v-for="
                regiao in
                dashboard
                  .entregas_por_regiao
              "
              :key="
                regiao.regiao_id ??
                regiao.nome
              "
              class="regiao-item"
              type="button"
              @click="irParaEntregas"
            >
              <div class="regiao-topo">
                <strong>
                  {{ regiao.nome }}
                </strong>

                <span>
                  {{ regiao.total }}
                  entrega(s)
                </span>
              </div>

              <div class="barra-fundo">
                <div
                  class="barra-preenchimento"
                  :style="{
                    width:
                      `${percentualRegiao(
                        regiao.total,
                      )}%`,
                  }"
                ></div>
              </div>
            </button>
          </div>
        </article>

        <article class="painel">
          <header class="painel-cabecalho">
            <div>
              <h2>Carradas de hoje</h2>

              <p>
                Acompanhe o que está
                planejado e o que já saiu.
              </p>
            </div>

            <button
              class="link-acao"
              type="button"
              @click="irParaSaidas"
            >
              Ver carradas
            </button>
          </header>

          <div
            v-if="
              dashboard.carradas_hoje
                .length === 0
            "
            class="estado-vazio"
          >
            Nenhuma carrada registrada
            para hoje.
          </div>

          <div
            v-else
            class="carradas-lista"
          >
            <button
              v-for="
                carrada in
                dashboard.carradas_hoje
              "
              :key="carrada.id"
              class="carrada-item"
              type="button"
              @click="irParaSaidas"
            >
              <div class="carrada-numero">
                #{{ carrada.id }}
              </div>

              <div class="carrada-dados">
                <strong>
                  {{
                    rotaCarrada(
                      carrada,
                    )
                  }}
                </strong>

                <small>
                  {{
                    carrada
                      .total_entregas
                  }}
                  entrega(s)

                  <template
                    v-if="
                      carrada
                        .total_reentregas
                    "
                  >
                    ·
                    {{
                      carrada
                        .total_reentregas
                    }}
                    reentrega(s)
                  </template>
                </small>
              </div>

              <span
                class="status-saida"
                :class="
                  carrada.status
                    .toLowerCase()
                "
              >
                {{
                  statusCarradaLabel(
                    carrada.status,
                  )
                }}
              </span>
            </button>
          </div>
        </article>

        <article class="painel">
          <header class="painel-cabecalho">
            <div>
              <h2>
                Pendências mais antigas
              </h2>

              <p>
                Reentregas abertas que
                merecem atenção primeiro.
              </p>
            </div>

            <button
              class="link-acao"
              type="button"
              @click="irParaPendencias"
            >
              Ver pendências
            </button>
          </header>

          <div
            v-if="
              dashboard
                .pendencias_atencao
                .length === 0
            "
            class="estado-vazio"
          >
            Nenhuma pendência aberta.
          </div>

          <div
            v-else
            class="pendencias-lista"
          >
            <button
              v-for="
                pendencia in
                dashboard
                  .pendencias_atencao
              "
              :key="pendencia.id"
              class="pendencia-item"
              type="button"
              @click="irParaPendencias"
            >
              <div class="pendencia-principal">
                <div>
                  <strong>
                    {{
                      pendencia
                        .numero_pedido
                        ? `#${pendencia.numero_pedido}`
                        : `Pedido #${pendencia.entrega_id}`
                    }}
                  </strong>

                  <span>
                    {{
                      pendencia
                        .cliente_nome
                    }}
                  </span>
                </div>

                <span
                  class="dias"
                  :class="{
                    urgente:
                      pendencia
                        .dias_aberta >= 4,
                  }"
                >
                  {{
                    pendencia
                      .dias_aberta === 0
                      ? 'Hoje'
                      : `${pendencia.dias_aberta} dia(s)`
                  }}
                </span>
              </div>

              <div class="pendencia-produto">
                <span>
                  {{
                    pendencia
                      .produto.descricao
                  }}
                </span>

                <strong>
                  {{
                    formatarQuantidade(
                      pendencia
                        .saldo_pendente,
                    )
                  }}
                  {{
                    pendencia
                      .produto.unidade ??
                    ''
                  }}
                </strong>
              </div>

              <small>
                {{
                  pendencia.regiao.nome
                }}
              </small>
            </button>
          </div>
        </article>

        <article class="painel">
          <header class="painel-cabecalho">
            <div>
              <h2>
                Ainda sem carrada
              </h2>

              <p>
                Pedidos mais antigos que
                continuam aguardando
                organização.
              </p>
            </div>

            <button
              class="link-acao"
              type="button"
              @click="irParaEntregas"
            >
              Ver todas
            </button>
          </header>

          <div
            v-if="
              dashboard
                .proximas_entregas
                .length === 0
            "
            class="estado-vazio"
          >
            Todos os pedidos abertos já
            estão programados.
          </div>

          <div
            v-else
            class="entregas-lista"
          >
            <button
              v-for="
                entrega in
                dashboard
                  .proximas_entregas
              "
              :key="entrega.id"
              class="entrega-item"
              type="button"
              @click="irParaEntregas"
            >
              <div class="pedido">
                <strong>
                  {{
                    entrega.numero_pedido
                      ? `#${entrega.numero_pedido}`
                      : `#${entrega.id}`
                  }}
                </strong>

                <small>
                  {{
                    formatarDataHora(
                      entrega.criada_em,
                    )
                  }}
                </small>
              </div>

              <div class="cliente">
                <strong>
                  {{
                    entrega.cliente_nome
                  }}
                </strong>

                <small>
                  {{
                    entrega.regiao.nome
                  }}
                </small>
              </div>

              <span class="itens">
                {{
                  entrega.total_itens
                }}
                item(ns)
              </span>
            </button>
          </div>
        </article>
      </section>

      <footer class="dashboard-rodape">
        Atualizado em
        {{
          formatarDataHora(
            dashboard.periodo
              .atualizado_em,
          )
        }}
      </footer>
    </template>
  </main>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.dashboard {
  min-height: 100vh;
  padding: 30px;
  background: #f5f7fa;
  color: #172033;
  font-family:
    Inter,
    system-ui,
    sans-serif;
}

.dashboard-cabecalho {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.data-hoje {
  display: block;
  margin-bottom: 6px;
  color: #175cd3;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.dashboard-cabecalho h1 {
  margin: 0;
  font-size: clamp(27px, 3vw, 34px);
  letter-spacing: -0.03em;
}

.dashboard-cabecalho p {
  max-width: 670px;
  margin: 7px 0 0;
  color: #667085;
  line-height: 1.5;
}

.cabecalho-acoes {
  display: flex;
  gap: 10px;
}

.botao {
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 9px;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.botao:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.botao.secundario {
  border-color: #d0d5dd;
  background: #fff;
  color: #344054;
}

.botao.sair {
  background: #172033;
  color: #fff;
}

.mensagem {
  margin: 0 0 20px;
  padding: 12px 16px;
  border-radius: 9px;
}

.mensagem.erro {
  border: 1px solid #fecdca;
  background: #fef3f2;
  color: #b42318;
}

.carregando-principal {
  display: grid;
  min-height: 300px;
  place-items: center;
  color: #667085;
}

.metricas-grid {
  display: grid;
  grid-template-columns:
    repeat(5, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.metrica-card {
  display: flex;
  min-width: 0;
  min-height: 132px;
  gap: 14px;
  align-items: flex-start;
  padding: 18px;
  border: 1px solid #e4e7ec;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 1px 3px rgb(16 24 40 / 5%);
  cursor: pointer;
  transition:
    transform 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.metrica-card:hover,
.metrica-card:focus-visible {
  transform: translateY(-2px);
  border-color: #b2ccff;
  box-shadow: 0 8px 22px rgb(16 24 40 / 8%);
  outline: none;
}

.metrica-card.principal {
  border-color: #b2ccff;
  background:
    linear-gradient(
      145deg,
      #eff8ff,
      #fff 70%
    );
}

.metrica-card.problema {
  background:
    linear-gradient(
      145deg,
      #fff6ed,
      #fff 70%
    );
}

.metrica-icone {
  display: grid;
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  place-items: center;
  border-radius: 11px;
  background: #f2f4f7;
  color: #344054;
  font-size: 20px;
  font-weight: 900;
}

.principal .metrica-icone {
  background: #dbeafe;
  color: #175cd3;
}

.problema .metrica-icone {
  background: #ffead5;
  color: #c4320a;
}

.metrica-conteudo {
  min-width: 0;
}

.metrica-conteudo > span {
  display: block;
  min-height: 36px;
  color: #475467;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.35;
}

.metrica-conteudo strong {
  display: block;
  margin: 5px 0 4px;
  color: #101828;
  font-size: 29px;
  line-height: 1;
}

.metrica-conteudo small {
  display: block;
  color: #98a2b3;
  font-size: 11.5px;
  line-height: 1.35;
}

.dashboard-grid {
  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    minmax(0, 1fr);
  gap: 20px;
}

.painel {
  min-width: 0;
  overflow: hidden;
  border: 1px solid #e4e7ec;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 1px 3px rgb(16 24 40 / 5%);
}

.painel-cabecalho {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 19px 20px;
  border-bottom: 1px solid #eaecf0;
}

.painel-cabecalho h2 {
  margin: 0;
  color: #101828;
  font-size: 17px;
}

.painel-cabecalho p {
  margin: 5px 0 0;
  color: #667085;
  font-size: 12.5px;
}

.link-acao {
  flex: 0 0 auto;
  padding: 0;
  border: 0;
  background: transparent;
  color: #175cd3;
  font: inherit;
  font-size: 12.5px;
  font-weight: 800;
  cursor: pointer;
}

.estado-vazio {
  padding: 42px 20px;
  color: #98a2b3;
  text-align: center;
}

.regioes-lista {
  padding: 10px 20px 18px;
}

.regiao-item {
  display: block;
  width: 100%;
  padding: 10px 0;
  border: 0;
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.regiao-topo {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: center;
  margin-bottom: 7px;
}

.regiao-topo strong {
  color: #344054;
  font-size: 13.5px;
}

.regiao-topo span {
  color: #667085;
  font-size: 12px;
}

.barra-fundo {
  height: 7px;
  overflow: hidden;
  border-radius: 999px;
  background: #f2f4f7;
}

.barra-preenchimento {
  height: 100%;
  border-radius: inherit;
  background: #84adff;
  transition: width 0.3s ease;
}

.carradas-lista,
.pendencias-lista,
.entregas-lista {
  display: flex;
  flex-direction: column;
}

.carrada-item,
.pendencia-item,
.entrega-item {
  width: 100%;
  border: 0;
  border-bottom: 1px solid #eaecf0;
  background: #fff;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.carrada-item:last-child,
.pendencia-item:last-child,
.entrega-item:last-child {
  border-bottom: 0;
}

.carrada-item:hover,
.pendencia-item:hover,
.entrega-item:hover {
  background: #f9fafb;
}

.carrada-item {
  display: grid;
  grid-template-columns:
    54px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 14px 18px;
}

.carrada-numero {
  color: #175cd3;
  font-size: 13px;
  font-weight: 800;
}

.carrada-dados {
  min-width: 0;
}

.carrada-dados strong {
  display: block;
  overflow: hidden;
  color: #344054;
  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.carrada-dados small {
  display: block;
  margin-top: 4px;
  color: #98a2b3;
  font-size: 11.5px;
}

.status-saida {
  display: inline-flex;
  padding: 5px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 800;
  white-space: nowrap;
}

.status-saida.planejada {
  background: #fef0c7;
  color: #b54708;
}

.status-saida.em_andamento {
  background: #dbeafe;
  color: #175cd3;
}

.status-saida.finalizada {
  background: #d1fadf;
  color: #027a48;
}

.status-saida.cancelada {
  background: #f2f4f7;
  color: #667085;
}

.pendencia-item {
  padding: 14px 18px;
}

.pendencia-principal,
.pendencia-produto {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}

.pendencia-principal > div {
  min-width: 0;
}

.pendencia-principal strong {
  margin-right: 7px;
  color: #101828;
  font-size: 13px;
}

.pendencia-principal span:not(.dias) {
  color: #667085;
  font-size: 12px;
}

.dias {
  flex: 0 0 auto;
  padding: 4px 7px;
  border-radius: 999px;
  background: #f2f4f7;
  color: #667085;
  font-size: 10.5px;
  font-weight: 800;
}

.dias.urgente {
  background: #fee4e2;
  color: #b42318;
}

.pendencia-produto {
  margin-top: 8px;
}

.pendencia-produto span {
  overflow: hidden;
  color: #344054;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pendencia-produto strong {
  flex: 0 0 auto;
  color: #101828;
  font-size: 12px;
}

.pendencia-item > small {
  display: block;
  margin-top: 6px;
  color: #98a2b3;
  font-size: 11px;
}

.entrega-item {
  display: grid;
  grid-template-columns:
    minmax(80px, 0.8fr)
    minmax(0, 1.7fr)
    auto;
  gap: 14px;
  align-items: center;
  padding: 14px 18px;
}

.pedido strong,
.cliente strong {
  display: block;
  color: #101828;
  font-size: 13px;
}

.pedido small,
.cliente small {
  display: block;
  margin-top: 3px;
  color: #98a2b3;
  font-size: 11px;
}

.itens {
  padding: 5px 8px;
  border-radius: 999px;
  background: #f2f4f7;
  color: #475467;
  font-size: 10.5px;
  font-weight: 800;
  white-space: nowrap;
}

.dashboard-rodape {
  padding: 18px 2px 0;
  color: #98a2b3;
  font-size: 11px;
  text-align: right;
}

@media (max-width: 1350px) {
  .metricas-grid {
    grid-template-columns:
      repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 980px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .metricas-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .dashboard {
    padding: 18px;
  }

  .dashboard-cabecalho {
    flex-direction: column;
  }

  .cabecalho-acoes {
    width: 100%;
  }

  .cabecalho-acoes .botao {
    flex: 1;
  }

  .metricas-grid {
    grid-template-columns: 1fr;
  }

  .metrica-card {
    min-height: 112px;
  }

  .painel-cabecalho {
    align-items: flex-start;
    flex-direction: column;
  }

  .carrada-item {
    grid-template-columns:
      48px minmax(0, 1fr);
  }

  .status-saida {
    grid-column: 2;
    width: fit-content;
  }

  .entrega-item {
    grid-template-columns:
      minmax(72px, 0.8fr)
      minmax(0, 1.4fr);
  }

  .itens {
    grid-column: 2;
    width: fit-content;
  }
}
</style>
