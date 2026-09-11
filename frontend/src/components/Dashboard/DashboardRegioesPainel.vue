<script setup lang="ts">
import { computed } from 'vue'
import type { DashboardData } from '@/types/Dashboard'

type DashboardRegiao = DashboardData['entregas_por_regiao'][number]

const props = defineProps<{
  regioes: DashboardRegiao[]
}>()

const emit = defineEmits<{
  abrir: []
}>()

const maiorTotal = computed(() => {
  if (props.regioes.length === 0) return 1
  return Math.max(...props.regioes.map((regiao) => regiao.total), 1)
})

function percentual(total: number) {
  return Math.max(7, Math.round((total / maiorTotal.value) * 100))
}
</script>

<template>
  <article class="painel painel-regioes">
    <header class="painel-cabecalho">
      <div>
        <span class="painel-eyebrow">Planejamento</span>
        <h2>Entregas para organizar por região</h2>
        <p>Onde existe maior volume aguardando carrada.</p>
      </div>

      <button class="link-acao" type="button" @click="emit('abrir')">
        Ver entregas
      </button>
    </header>

    <div v-if="regioes.length === 0" class="estado-vazio">
      Nenhuma entrega aguardando programação.
    </div>

    <div v-else class="regioes-lista">
      <button
        v-for="regiao in regioes"
        :key="regiao.regiao_id ?? regiao.nome"
        class="regiao-item"
        type="button"
        @click="emit('abrir')"
      >
        <div class="regiao-topo">
          <strong>{{ regiao.nome }}</strong>
          <span>{{ regiao.total }} entrega(s)</span>
        </div>

        <div class="barra-fundo">
          <div
            class="barra-preenchimento"
            :style="{ width: `${percentual(regiao.total)}%` }"
          ></div>
        </div>
      </button>
    </div>
  </article>
</template>
