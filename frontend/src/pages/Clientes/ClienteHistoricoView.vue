<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { clienteHistoricoService } from '@/services/clienteHistoricoService'
import ClienteHistoricoPainel from '@/components/Clientes/ClienteHistoricoPainel.vue'

import type { ClienteHistorico } from '@/types/ClienteHistorico'

const route = useRoute()
const router = useRouter()

const historico = ref<ClienteHistorico | null>(null)
const carregando = ref(false)
const erro = ref('')

const clienteId = Number(route.params.id)

async function carregarHistorico(page = 1) {
  if (!Number.isFinite(clienteId) || clienteId <= 0) {
    erro.value = 'Cliente inválido.'
    return
  }

  carregando.value = true
  erro.value = ''

  try {
    historico.value = await clienteHistoricoService.buscarEntregas(
      clienteId,
      page,
    )
  } catch {
    erro.value = 'Não foi possível carregar as entregas do cliente.'
  } finally {
    carregando.value = false
  }
}

function voltar() {
  router.push('/clientes')
}

onMounted(() => {
  carregarHistorico()
})
</script>

<template>
  <main class="pagina-historico-cliente">
    <header class="historico-pagina-cabecalho">
      <div>
        <button class="voltar" type="button" @click="voltar">
          ← Voltar para clientes
        </button>

        <span class="eyebrow">Histórico do cliente</span>
        <h1>{{ historico?.cliente.nome ?? 'Cliente' }}</h1>
        <p v-if="historico">
          <template v-if="historico.cliente.codigo_externo">
            Código: {{ historico.cliente.codigo_externo }} ·
          </template>
          {{ historico.cliente.telefone ?? 'Sem telefone' }}
        </p>
        <p v-else>
          Consulte as entregas e o histórico operacional deste cliente.
        </p>
      </div>
    </header>

    <p v-if="erro" class="mensagem erro">
      {{ erro }}
    </p>

    <div v-if="carregando" class="card estado">
      Carregando histórico do cliente...
    </div>

    <ClienteHistoricoPainel
      v-else-if="historico"
      :historico="historico"
      @pagina="carregarHistorico"
    />
  </main>
</template>

<style scoped src="../../styles/pages/cliente/cliente-historico.css"></style>
