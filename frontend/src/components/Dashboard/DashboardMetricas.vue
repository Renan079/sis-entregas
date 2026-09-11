<script setup lang="ts">
import type { DashboardData } from '@/types/Dashboard'

type DashboardResumo = DashboardData['resumo']

defineProps<{
  resumo: DashboardResumo
  mesLabel: string
}>()

const emit = defineEmits<{
  entregas: []
  saidas: []
  pendencias: []
}>()
</script>

<template>
  <section class="metricas-grid">
    <button class="metrica-card destaque-azul" type="button" @click="emit('entregas')">
      <span class="metrica-icone">✓</span>
      <span class="metrica-conteudo">
        <span>Entregas realizadas no mês</span>
        <strong>{{ resumo.entregas_realizadas_mes }}</strong>
        <small>{{ mesLabel }}</small>
      </span>
    </button>

    <button class="metrica-card" type="button" @click="emit('entregas')">
      <span class="metrica-icone">→</span>
      <span class="metrica-conteudo">
        <span>Aguardando carrada</span>
        <strong>{{ resumo.aguardando_programacao }}</strong>
        <small>Pedidos ainda sem programação</small>
      </span>
    </button>

    <button class="metrica-card" type="button" @click="emit('saidas')">
      <span class="metrica-icone">▦</span>
      <span class="metrica-conteudo">
        <span>Carradas de hoje</span>
        <strong>{{ resumo.carradas_hoje }}</strong>
        <small>
          {{ resumo.carradas_planejadas_hoje }} planejada(s) ·
          {{ resumo.carradas_em_andamento_hoje }} em andamento
        </small>
      </span>
    </button>

    <button class="metrica-card destaque-laranja" type="button" @click="emit('pendencias')">
      <span class="metrica-icone">↻</span>
      <span class="metrica-conteudo">
        <span>Reentregas abertas</span>
        <strong>{{ resumo.reentregas_abertas }}</strong>
        <small>Aguardando nova programação</small>
      </span>
    </button>

    <button class="metrica-card destaque-vermelho" type="button" @click="emit('entregas')">
      <span class="metrica-icone">!</span>
      <span class="metrica-conteudo">
        <span>Pedidos com problema</span>
        <strong>{{ resumo.pedidos_com_problema }}</strong>
        <small>Parciais ou não entregues</small>
      </span>
    </button>
  </section>
</template>
