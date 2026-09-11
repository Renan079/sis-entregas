<script setup lang="ts">
import type { DashboardData } from '@/types/Dashboard'

type PendenciasAtencao = DashboardData['pendencias_atencao']

defineProps<{
  pendencias: PendenciasAtencao
}>()

const emit = defineEmits<{
  abrir: []
}>()

function formatarQuantidade(valor: number) {
  return new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 3,
  }).format(Number(valor))
}
</script>

<template>
  <article class="painel painel-pendencias">
    <header class="painel-cabecalho">
      <div>
        <span class="painel-eyebrow">Atenção</span>
        <h2>Pendências mais antigas</h2>
        <p>Reentregas abertas que merecem atenção primeiro.</p>
      </div>

      <button class="link-acao" type="button" @click="emit('abrir')">
        Ver pendências
      </button>
    </header>

    <div v-if="pendencias.length === 0" class="estado-vazio">
      Nenhuma pendência aberta.
    </div>

    <div v-else class="pendencias-lista">
      <button
        v-for="pendencia in pendencias"
        :key="pendencia.id"
        class="pendencia-item"
        type="button"
        @click="emit('abrir')"
      >
        <div class="pendencia-principal">
          <div>
            <strong>
              {{ pendencia.numero_pedido ? `#${pendencia.numero_pedido}` : `#${pendencia.entrega_id}` }}
            </strong>
            <span>{{ pendencia.cliente_nome }}</span>
          </div>

          <span class="dias" :class="{ urgente: pendencia.dias_aberta >= 4 }">
            {{ pendencia.dias_aberta === 0 ? 'Hoje' : `${pendencia.dias_aberta} dia(s)` }}
          </span>
        </div>

        <div class="pendencia-produto">
          <span>{{ pendencia.produto.descricao }}</span>
          <strong>
            {{ formatarQuantidade(pendencia.saldo_pendente) }}
            {{ pendencia.produto.unidade ?? '' }}
          </strong>
        </div>

        <small>{{ pendencia.regiao.nome }}</small>
      </button>
    </div>
  </article>
</template>
