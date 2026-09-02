<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import CarradaDetalhesPainel from '@/components/Saidas/CarradaDetalhesPainel.vue'
import RetornoEntregaModal from '@/components/Saidas/RetornoEntregaModal.vue'
import RetornoPendenciaModal from '@/components/Saidas/RetornoPendenciaModal.vue'
import SelecionarItensModal from '@/components/Saidas/SelecionarItensModal.vue'
import { useSaidaDetalhe } from '@/composables/saidas/useSaidaDetalhe'

const route = useRoute()
const router = useRouter()

const {
  saidaSelecionada,
  entregasDisponiveis,
  pendenciasDisponiveis,
  pendenciasNaCarrada,
  carregando,
  carregandoItens,
  salvando,
  erro,
  processandoPendenciaId,
  modalSelecionarItens,
  entregaParaAdicionar,
  quantidadesProgramadas,
  modalRetorno,
  entregaRetorno,
  itensRetorno,
  motivoRetorno,
  observacaoRetorno,
  registrandoRetorno,
  modalRetornoPendencia,
  pendenciaRetorno,
  quantidadeEntreguePendencia,
  destinoRestantePendencia,
  motivoDevolucaoPendencia,
  observacaoRetornoPendencia,
  registrandoRetornoPendencia,
  carregar,
  adicionarPendenciaCarrada,
  removerPendenciaCarrada,
  abrirItensEntrega,
  fecharSelecaoItens,
  levarTudo,
  limparQuantidades,
  confirmarAdicionarEntrega,
  removerEntrega,
  iniciarSaida,
  finalizarSaida,
  abrirRetorno,
  fecharRetorno,
  confirmarRetorno,
  abrirRetornoPendencia,
  fecharRetornoPendencia,
  confirmarRetornoPendencia,
} = useSaidaDetalhe()

const saidaId = computed(() => Number(route.params.id))

async function voltar() {
  await router.push('/saidas')
}

watch(
  saidaId,
  async (id) => {
    if (!Number.isInteger(id) || id <= 0) {
      return
    }

    await carregar(id)
  },
  { immediate: true },
)
</script>

<template>
  <main class="pagina carrada-detalhe-page">
    <header class="cabecalho carrada-detalhe-topo">
      <div>
        <button
          class="voltar-link"
          type="button"
          @click="voltar"
        >
          ← Voltar para carradas
        </button>

        <h1>
          {{
            saidaSelecionada
              ? `Carrada #${saidaSelecionada.id}`
              : 'Carrada'
          }}
        </h1>

        <p>
          Planejamento, operação e retorno desta saída.
        </p>
      </div>
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
      Carregando carrada...
    </section>

    <section
      v-else-if="!saidaSelecionada"
      class="card estado"
    >
      Carrada não encontrada.
    </section>

    <CarradaDetalhesPainel
      v-else
      :saida-selecionada="saidaSelecionada"
      :pendencias-na-carrada="pendenciasNaCarrada"
      :pendencias-disponiveis="pendenciasDisponiveis"
      :entregas-disponiveis="entregasDisponiveis"
      :processando-pendencia-id="processandoPendenciaId"
      :carregando-itens="carregandoItens"
      @retorno-entrega="abrirRetorno"
      @remover-entrega="removerEntrega"
      @retorno-pendencia="abrirRetornoPendencia"
      @remover-pendencia="removerPendenciaCarrada"
      @adicionar-pendencia="adicionarPendenciaCarrada"
      @ver-itens="abrirItensEntrega"
      @iniciar="iniciarSaida"
      @finalizar="finalizarSaida"
    />

    <SelecionarItensModal
      :modal-selecionar-itens="modalSelecionarItens"
      :entrega-para-adicionar="entregaParaAdicionar"
      :quantidades-programadas="quantidadesProgramadas"
      :salvando="salvando"
      @fechar="fecharSelecaoItens"
      @levar-tudo="levarTudo"
      @limpar="limparQuantidades"
      @confirmar="confirmarAdicionarEntrega"
    />

    <RetornoEntregaModal
      :modal-retorno="modalRetorno"
      :entrega-retorno="entregaRetorno"
      :itens-retorno="itensRetorno"
      :motivo-retorno="motivoRetorno"
      :observacao-retorno="observacaoRetorno"
      :registrando-retorno="registrandoRetorno"
      @fechar="fecharRetorno"
      @confirmar="confirmarRetorno"
      @update:motivo-retorno="motivoRetorno = $event"
      @update:observacao-retorno="observacaoRetorno = $event"
    />

    <RetornoPendenciaModal
      :modal-retorno-pendencia="modalRetornoPendencia"
      :pendencia-retorno="pendenciaRetorno"
      :quantidade-entregue-pendencia="quantidadeEntreguePendencia"
      :destino-restante-pendencia="destinoRestantePendencia"
      :motivo-devolucao-pendencia="motivoDevolucaoPendencia"
      :observacao-retorno-pendencia="observacaoRetornoPendencia"
      :registrando-retorno-pendencia="registrandoRetornoPendencia"
      @fechar="fecharRetornoPendencia"
      @confirmar="confirmarRetornoPendencia"
      @update:quantidade-entregue-pendencia="
        quantidadeEntreguePendencia = $event
      "
      @update:destino-restante-pendencia="
        destinoRestantePendencia = $event
      "
      @update:motivo-devolucao-pendencia="
        motivoDevolucaoPendencia = $event
      "
      @update:observacao-retorno-pendencia="
        observacaoRetornoPendencia = $event
      "
    />
  </main>
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
<style scoped src="../../styles/pages/saida/saida-detalhe.css"></style>
