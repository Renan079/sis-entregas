<script setup lang="ts">
import { computed } from 'vue'
import type { PendenciaRota } from '@/services/saidaService.ts'
import type { DestinoNaoEntregue, MotivoDevolucao } from '@/types/Saida.ts'

const props = defineProps<{
  modalRetornoPendencia: boolean
  pendenciaRetorno: PendenciaRota | null
  quantidadeEntreguePendencia: number
  destinoRestantePendencia: DestinoNaoEntregue | null
  motivoDevolucaoPendencia: MotivoDevolucao | null
  observacaoRetornoPendencia: string
  registrandoRetornoPendencia: boolean
}>()

const emit = defineEmits<{
  fechar: []
  confirmar: []
  'update:quantidadeEntreguePendencia': [valor: number]
  'update:destinoRestantePendencia': [valor: DestinoNaoEntregue | null]
  'update:motivoDevolucaoPendencia': [valor: MotivoDevolucao | null]
  'update:observacaoRetornoPendencia': [valor: string]
}>()

const quantidadeEntreguePendencia = computed({
  get: () => props.quantidadeEntreguePendencia,
  set: (valor: number) => emit('update:quantidadeEntreguePendencia', Number(valor)),
})

const destinoRestantePendencia = computed({
  get: () => props.destinoRestantePendencia,
  set: (valor: DestinoNaoEntregue | null) => emit('update:destinoRestantePendencia', valor),
})

const motivoDevolucaoPendencia = computed({
  get: () => props.motivoDevolucaoPendencia,
  set: (valor: MotivoDevolucao | null) => emit('update:motivoDevolucaoPendencia', valor),
})

const observacaoRetornoPendencia = computed({
  get: () => props.observacaoRetornoPendencia,
  set: (valor: string) => emit('update:observacaoRetornoPendencia', valor),
})

function fecharRetornoPendencia() { emit('fechar') }
function confirmarRetornoPendencia() { emit('confirmar') }

function quantidadeFaltantePendencia() {
  if (!props.pendenciaRetorno) return 0
  return Math.max(
    0,
    Number(props.pendenciaRetorno.saldo_pendente) - Number(quantidadeEntreguePendencia.value),
  )
}

function alterarDestinoPendencia() {
  if (destinoRestantePendencia.value !== 'DEVOLVIDO') {
    motivoDevolucaoPendencia.value = null
  }
}
</script>

<template>
    <div
      v-if="
        modalRetornoPendencia &&
        pendenciaRetorno
      "
      class="modal-fundo retorno-modal-fundo"
      @click.self="fecharRetornoPendencia"
    >
      <section class="modal medio">
        <header class="modal-cabecalho">
          <div>
            <h2>Registrar retorno da reentrega</h2>

            <p>
              Pedido
              {{
                pendenciaRetorno.numero_pedido ??
                `#${pendenciaRetorno.entrega_id}`
              }}
              ·
              {{ pendenciaRetorno.cliente_nome }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharRetornoPendencia"
          >
            ×
          </button>
        </header>

        <div class="reentrega-retorno-produto">
          <div>
            <small>Produto</small>
            <strong>
              {{ pendenciaRetorno.descricao_produto }}
            </strong>
            <span v-if="pendenciaRetorno.codigo_produto">
              Cód. {{ pendenciaRetorno.codigo_produto }}
            </span>
          </div>

          <div>
            <small>Programado para reentrega</small>
            <strong>
              {{ pendenciaRetorno.saldo_pendente }}
              {{ pendenciaRetorno.unidade || '' }}
            </strong>
          </div>
        </div>

        <div class="grid-retorno-reentrega">
          <div class="campo">
            <label>Entregue ao cliente</label>
            <input
              v-model.number="quantidadeEntreguePendencia"
              type="number"
              min="0"
              :max="pendenciaRetorno.saldo_pendente"
              step="0.001"
            />
          </div>

          <div class="campo">
            <label>Ainda faltou</label>
            <strong
              class="saldo-faltante-reentrega"
              :class="{
                'tem-falta':
                  quantidadeFaltantePendencia() > 0,
              }"
            >
              {{ quantidadeFaltantePendencia() }}
              {{ pendenciaRetorno.unidade || '' }}
            </strong>
          </div>
        </div>

        <div
          v-if="quantidadeFaltantePendencia() > 0"
          class="campo"
        >
          <label>
            Destino da quantidade restante
          </label>

          <select
            v-model="destinoRestantePendencia"
            @change="alterarDestinoPendencia"
          >
            <option :value="null">
              Selecione...
            </option>
            <option value="REENTREGA">
              Reentrega novamente
            </option>
            <option value="DEVOLVIDO">
              Devolvido
            </option>
          </select>
        </div>

        <div
          v-if="
            quantidadeFaltantePendencia() > 0 &&
            destinoRestantePendencia ===
              'DEVOLVIDO'
          "
          class="campo"
        >
          <label>Motivo da devolução</label>

          <select v-model="motivoDevolucaoPendencia">
            <option :value="null">
              Selecione...
            </option>
            <option value="CLIENTE_RECUSOU">
              Cliente recusou
            </option>
            <option value="CLIENTE_DESISTIU">
              Cliente desistiu
            </option>
            <option value="PRODUTO_INCORRETO">
              Produto incorreto
            </option>
            <option value="PRODUTO_AVARIADO">
              Produto avariado
            </option>
            <option value="QUANTIDADE_EXCEDENTE">
              Quantidade excedente
            </option>
            <option value="OUTRO">
              Outro
            </option>
          </select>
        </div>

        <div class="campo">
          <label>Observação</label>
          <textarea
            v-model="observacaoRetornoPendencia"
            rows="3"
            placeholder="Observações sobre esta reentrega..."
          ></textarea>
        </div>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            @click="fecharRetornoPendencia"
          >
            Cancelar
          </button>

          <button
            class="botao finalizar"
            type="button"
            :disabled="registrandoRetornoPendencia"
            @click="confirmarRetornoPendencia"
          >
            {{
              registrandoRetornoPendencia
                ? 'Registrando...'
                : 'Confirmar retorno'
            }}
          </button>
        </footer>
      </section>
    </div>
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
