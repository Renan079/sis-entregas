<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import DashboardMetricas from '@/components/Dashboard/DashboardMetricas.vue'
import DashboardRegioesPainel from '@/components/Dashboard/DashboardRegioesPainel.vue'
import DashboardCarradasPainel from '@/components/Dashboard/DashboardCarradasPainel.vue'
import DashboardPendenciasPainel from '@/components/Dashboard/DashboardPendenciasPainel.vue'
import DashboardEntregasPainel from '@/components/Dashboard/DashboardEntregasPainel.vue'
import { dashboardService } from '@/services/dashboardService'
import { useAuthStore } from '@/stores/auth'

import type { DashboardData } from '@/types/Dashboard'

const auth = useAuthStore()
const router = useRouter()

const dashboard = ref<DashboardData | null>(null)
const carregando = ref(false)
const erro = ref('')

const primeiroNome = computed(() => {
  const nome = auth.user?.name?.trim()
  return nome ? (nome.split(/\s+/)[0] ?? nome) : ''
})

const hoje = computed(() => {
  const texto = new Intl.DateTimeFormat('pt-BR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
  }).format(new Date())

  return texto.charAt(0).toUpperCase() + texto.slice(1)
})

const mesAtual = computed(() => {
  const texto = new Intl.DateTimeFormat('pt-BR', {
    month: 'long',
    year: 'numeric',
  }).format(new Date())

  return texto.charAt(0).toUpperCase() + texto.slice(1)
})

async function carregarDashboard() {
  carregando.value = true
  erro.value = ''

  try {
    dashboard.value = await dashboardService.buscar()
  } catch {
    erro.value = 'Não foi possível carregar a dashboard.'
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

function formatarDataHora(data: string | null) {
  if (!data) return '—'

  const valor = new Date(data)
  if (Number.isNaN(valor.getTime())) return data

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(valor)
}

onMounted(carregarDashboard)
</script>

<template>
  <main class="dashboard">
    <header class="dashboard-cabecalho">
      <div class="dashboard-titulo">
        <span class="dashboard-eyebrow">{{ hoje }}</span>

        <h1>
          {{ primeiroNome ? `Olá, ${primeiroNome}` : 'Dashboard' }}
        </h1>

        <p>
          Acompanhe a operação, identifique prioridades e acesse rapidamente
          o que precisa da sua atenção.
        </p>
      </div>

      <div class="cabecalho-acoes">
        <button
          class="botao secundario"
          type="button"
          :disabled="carregando"
          @click="carregarDashboard"
        >
          {{ carregando ? 'Atualizando...' : 'Atualizar' }}
        </button>

        <button class="botao sair" type="button" @click="sair">
          Sair
        </button>
      </div>
    </header>

    <p v-if="erro" class="mensagem erro">
      {{ erro }}
    </p>

    <div v-if="carregando && !dashboard" class="carregando-principal">
      <span class="carregando-spinner" aria-hidden="true"></span>
      <strong>Carregando visão operacional...</strong>
    </div>

    <template v-else-if="dashboard">
      <DashboardMetricas
        :resumo="dashboard.resumo"
        :mes-label="mesAtual"
        @entregas="irParaEntregas"
        @saidas="irParaSaidas"
        @pendencias="irParaPendencias"
      />

      <section class="dashboard-grid">
        <DashboardRegioesPainel
          :regioes="dashboard.entregas_por_regiao"
          @abrir="irParaEntregas"
        />

        <DashboardCarradasPainel
          :carradas="dashboard.carradas_hoje"
          @abrir="irParaSaidas"
        />

        <DashboardPendenciasPainel
          :pendencias="dashboard.pendencias_atencao"
          @abrir="irParaPendencias"
        />

        <DashboardEntregasPainel
          :entregas="dashboard.proximas_entregas"
          @abrir="irParaEntregas"
        />
      </section>

      <footer class="dashboard-rodape">
        <span class="status-atualizacao"></span>
        Atualizado em
        {{ formatarDataHora(dashboard.periodo.atualizado_em) }}
      </footer>
    </template>
  </main>
</template>

<style src="@/styles/pages/dashboard/dashboard.css"></style>
