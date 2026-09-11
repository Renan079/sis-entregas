<script setup lang="ts">
import type { DashboardData } from '@/types/Dashboard'

type ProximasEntregas = DashboardData['proximas_entregas']

defineProps<{
  entregas: ProximasEntregas
}>()

const emit = defineEmits<{
  abrir: []
}>()

function formatarDataHora(data: string | null) {
  if (!data) return '—'

  const valor = new Date(data)
  if (Number.isNaN(valor.getTime())) return data

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(valor)
}
</script>

<template>
  <article class="painel painel-entregas">
    <header class="painel-cabecalho">
      <div>
        <span class="painel-eyebrow">Fila operacional</span>
        <h2>Ainda sem carrada</h2>
        <p>Pedidos mais antigos que continuam aguardando organização.</p>
      </div>

      <button class="link-acao" type="button" @click="emit('abrir')">
        Ver todas
      </button>
    </header>

    <div v-if="entregas.length === 0" class="estado-vazio">
      Todos os pedidos abertos já estão programados.
    </div>

    <div v-else class="entregas-lista">
      <button
        v-for="entrega in entregas"
        :key="entrega.id"
        class="entrega-item"
        type="button"
        @click="emit('abrir')"
      >
        <span class="pedido">
          <strong>{{ entrega.numero_pedido ? `#${entrega.numero_pedido}` : `#${entrega.id}` }}</strong>
          <small>{{ formatarDataHora(entrega.criada_em) }}</small>
        </span>

        <span class="cliente">
          <strong>{{ entrega.cliente_nome }}</strong>
          <small>{{ entrega.regiao.nome }}</small>
        </span>

        <span class="itens">{{ entrega.total_itens }} item(ns)</span>
      </button>
    </div>
  </article>
</template>
