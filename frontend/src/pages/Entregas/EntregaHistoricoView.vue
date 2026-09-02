<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import EntregaHistoricoPainel from '@/components/Entregas/EntregaHistoricoPainel.vue'
import { entregaService } from '@/services/entregaService'
import type { EntregaHistorico } from '@/services/entregaService'

const route = useRoute()
const router = useRouter()

const carregando = ref(false)
const erro = ref('')
const historico = ref<EntregaHistorico | null>(null)

const entregaId = computed(() => Number(route.params.id))

async function carregarHistorico() {
  erro.value = ''
  historico.value = null

  if (!Number.isInteger(entregaId.value) || entregaId.value <= 0) {
    erro.value = 'Entrega inválida.'
    return
  }

  carregando.value = true

  try {
    historico.value =
      await entregaService.buscarHistorico(entregaId.value)
  } catch {
    erro.value =
      'Não foi possível carregar o histórico da entrega.'
  } finally {
    carregando.value = false
  }
}

async function voltarParaEntregas() {
  await router.push({
    name: 'entregas',
  })
}

onMounted(carregarHistorico)
</script>

<template>
  <main class="pagina-historico-entrega">
    <header class="historico-pagina-cabecalho">
      <div>
        <button
          class="botao-voltar"
          type="button"
          @click="voltarParaEntregas"
        >
          ← Voltar para entregas
        </button>

        <h1>Histórico da entrega</h1>

        <p v-if="historico">
          Pedido
          {{
            historico.entrega.numero_pedido
              ? `#${historico.entrega.numero_pedido}`
              : `#${historico.entrega.id}`
          }}
          ·
          {{ historico.entrega.cliente_nome }}
        </p>

        <p v-else>
          Acompanhe tentativas, produtos e pendências da entrega.
        </p>
      </div>
    </header>

    <p
      v-if="erro"
      class="erro-historico"
    >
      {{ erro }}
    </p>

    <section
      v-if="carregando"
      class="historico-card estado-historico"
    >
      Carregando histórico...
    </section>

    <EntregaHistoricoPainel
      v-else-if="historico"
      :historico="historico"
    />
  </main>
</template>

<style scoped src="../../styles/pages/entrega/entrega-historico.css"></style>
