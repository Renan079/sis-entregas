<script setup lang="ts">
import type { DashboardCarrada, DashboardData } from '@/types/Dashboard'

type CarradasHoje = DashboardData['carradas_hoje']

defineProps<{
  carradas: CarradasHoje
}>()

const emit = defineEmits<{
  abrir: []
}>()

function statusLabel(status: DashboardCarrada['status']) {
  const labels: Record<DashboardCarrada['status'], string> = {
    PLANEJADA: 'Planejada',
    EM_ANDAMENTO: 'Em andamento',
    FINALIZADA: 'Finalizada',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function rotaCarrada(carrada: DashboardCarrada) {
  return carrada.rota.length > 0
    ? carrada.rota.join(' → ')
    : (carrada.regiao_principal ?? 'Sem rota definida')
}
</script>

<template>
  <article class="painel painel-carradas">
    <header class="painel-cabecalho">
      <div>
        <span class="painel-eyebrow">Operação de hoje</span>
        <h2>Carradas de hoje</h2>
        <p>Acompanhe o que está planejado e o que já saiu.</p>
      </div>

      <button class="link-acao" type="button" @click="emit('abrir')">
        Ver carradas
      </button>
    </header>

    <div v-if="carradas.length === 0" class="estado-vazio">
      Nenhuma carrada registrada para hoje.
    </div>

    <div v-else class="carradas-lista">
      <button
        v-for="carrada in carradas"
        :key="carrada.id"
        class="carrada-item"
        type="button"
        @click="emit('abrir')"
      >
        <span class="carrada-numero">#{{ carrada.id }}</span>

        <span class="carrada-dados">
          <strong>{{ rotaCarrada(carrada) }}</strong>
          <small>
            {{ carrada.total_entregas }} entrega(s)
            <template v-if="carrada.total_reentregas">
              · {{ carrada.total_reentregas }} reentrega(s)
            </template>
          </small>
        </span>

        <span class="status-saida" :class="carrada.status.toLowerCase()">
          {{ statusLabel(carrada.status) }}
        </span>
      </button>
    </div>
  </article>
</template>
