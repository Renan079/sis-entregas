<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import HistoricoCarradaPainel from '@/components/Saidas/HistoricoCarradaPainel.vue'
import { saidaService } from '@/services/saidaService.ts'
import type { SaidaHistorico } from '@/services/saidaService.ts'

const route = useRoute()
const router = useRouter()

const historico = ref<SaidaHistorico | null>(null)
const carregando = ref(false)
const erro = ref('')

const saidaId = computed(() => Number(route.params.id))

async function carregar(id: number) {
  carregando.value = true
  erro.value = ''
  historico.value = null

  try {
    historico.value = await saidaService.buscarHistorico(id)
  } catch {
    erro.value = 'Não foi possível carregar o histórico da carrada.'
  } finally {
    carregando.value = false
  }
}

async function voltarParaCarrada() {
  if (!Number.isInteger(saidaId.value) || saidaId.value <= 0) {
    await router.push('/saidas')
    return
  }

  await router.push({
    name: 'saida-detalhe',
    params: { id: saidaId.value },
  })
}

async function voltarParaLista() {
  await router.push('/saidas')
}

watch(
  saidaId,
  async (id) => {
    if (!Number.isInteger(id) || id <= 0) {
      erro.value = 'Carrada inválida.'
      historico.value = null
      return
    }

    await carregar(id)
  },
  { immediate: true },
)
</script>

<template>
  <main class="pagina carrada-historico-page">
    <header class="cabecalho carrada-historico-topo">
      <div>
        <button
          class="voltar-link"
          type="button"
          @click="voltarParaLista"
        >
          ← Voltar para Saidas
        </button>

        <h1>
          {{
            historico
              ? `Histórico da carrada #${historico.saida.id}`
              : `Histórico da carrada #${saidaId}`
          }}
        </h1>

        <p>
          Linha do tempo, entregas, pendências e devoluções da saída.
        </p>
      </div>

      <button
        class="botao secundario"
        type="button"
        @click="voltarParaLista"
      >
        Todas as carradas
      </button>
    </header>

    <p
      v-if="erro"
      class="erro"
    >
      {{ erro }}
    </p>

    <section
      v-if="carregando"
      class="card estado"
    >
      Carregando histórico...
    </section>

    <section
      v-else-if="!historico && !erro"
      class="card estado"
    >
      Histórico não encontrado.
    </section>

    <HistoricoCarradaPainel
      v-else-if="historico"
      :historico="historico"
    />
  </main>
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
<style scoped src="../../styles/pages/saida/saida-historico.css"></style>
