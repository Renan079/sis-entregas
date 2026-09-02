<script setup lang="ts">
import { reactive, watch } from 'vue'

import type {
  MotivoDevolucao,
  Pendencia,
} from '@/types/Pendencia'

const props = defineProps<{
  aberto: boolean
  pendencia: Pendencia | null
  salvando: boolean
}>()

const emit = defineEmits<{
  fechar: []
  confirmar: [
    dados: {
      motivo_devolucao: MotivoDevolucao
      observacao: string | null
    },
  ]
}>()

const motivos: {
  valor: MotivoDevolucao
  label: string
}[] = [
  {
    valor: 'CLIENTE_DESISTIU',
    label: 'Cliente desistiu do produto',
  },
  {
    valor: 'CLIENTE_RECUSOU',
    label: 'Cliente recusou o produto',
  },
  {
    valor: 'PRODUTO_INCORRETO',
    label: 'Produto incorreto',
  },
  {
    valor: 'PRODUTO_AVARIADO',
    label: 'Produto avariado',
  },
  {
    valor: 'QUANTIDADE_EXCEDENTE',
    label: 'Quantidade excedente',
  },
  {
    valor: 'OUTRO',
    label: 'Outro',
  },
]

const formulario = reactive({
  motivo_devolucao:
    'CLIENTE_DESISTIU' as MotivoDevolucao,
  observacao: '',
})

watch(
  () => props.aberto,
  (aberto) => {
    if (!aberto) {
      return
    }

    formulario.motivo_devolucao =
      'CLIENTE_DESISTIU'
    formulario.observacao = ''
  },
)

function pedirFechamento() {
  if (props.salvando) {
    return
  }

  emit('fechar')
}

function confirmar() {
  if (
    !props.pendencia ||
    !formulario.motivo_devolucao
  ) {
    return
  }

  emit('confirmar', {
    motivo_devolucao:
      formulario.motivo_devolucao,
    observacao:
      formulario.observacao.trim() ||
      null,
  })
}

function formatarQuantidade(
  valor: number,
) {
  return new Intl.NumberFormat(
    'pt-BR',
    {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3,
    },
  ).format(Number(valor))
}
</script>

<template>
  <div
    v-if="aberto && pendencia"
    class="modal-fundo"
    @click.self="pedirFechamento"
  >
    <section class="modal">
      <header class="modal-cabecalho">
        <div>
          <span class="eyebrow">
            Encerramento de reentrega
          </span>

          <h2>
            Resolver pendência
            manualmente
          </h2>
        </div>

        <button
          class="fechar"
          type="button"
          :disabled="salvando"
          @click="pedirFechamento"
        >
          ×
        </button>
      </header>

      <div class="aviso-resolucao">
        <strong>
          Esta ação encerra a pendência
          sem nova reentrega.
        </strong>

        <span>
          O saldo pendente será
          registrado como devolução e
          deixará de aparecer como
          obrigação logística.
        </span>
      </div>

      <div class="produto-resolucao">
        <small>Pedido / Cliente</small>

        <strong>
          {{
            pendencia.numero_pedido
              ? `#${pendencia.numero_pedido}`
              : `Pedido #${pendencia.entrega_id}`
          }}
          ·
          {{ pendencia.cliente_nome }}
        </strong>
      </div>

      <div class="campo">
        <label>Produto</label>

        <div class="campo-leitura">
          {{ pendencia.produto.descricao }}

          <span
            v-if="pendencia.produto.codigo"
          >
            Ref:
            {{ pendencia.produto.codigo }}
          </span>
        </div>
      </div>

      <div class="campo">
        <label>
          Quantidade pendente
        </label>

        <div class="campo-leitura forte">
          {{
            formatarQuantidade(
              pendencia.saldo_pendente,
            )
          }}
          {{ pendencia.produto.unidade ?? '' }}
        </div>
      </div>

      <div class="campo">
        <label>
          Motivo da devolução *
        </label>

        <select
          v-model="formulario.motivo_devolucao"
          required
        >
          <option
            v-for="motivo in motivos"
            :key="motivo.valor"
            :value="motivo.valor"
          >
            {{ motivo.label }}
          </option>
        </select>
      </div>

      <div class="campo">
        <label>
          Observação
        </label>

        <textarea
          v-model="formulario.observacao"
          rows="4"
          maxlength="500"
          placeholder="Ex.: Cliente compareceu à loja e desistiu do restante."
        ></textarea>

        <small class="contador">
          {{ formulario.observacao.length }}/500
        </small>
      </div>

      <footer class="modal-acoes">
        <button
          class="botao secundario"
          type="button"
          :disabled="salvando"
          @click="pedirFechamento"
        >
          Cancelar
        </button>

        <button
          class="botao perigo"
          type="button"
          :disabled="salvando"
          @click="confirmar"
        >
          {{
            salvando
              ? 'Resolvendo...'
              : 'Confirmar resolução'
          }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style
  scoped
  src="../../styles/pages/pendencia/pendencia-resolucao-modal.css"
></style>
