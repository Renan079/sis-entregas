<script setup lang="ts">
import { computed } from 'vue'
import type { ItemRetornoCarrada, SaidaEntrega } from '@/types/Saida.ts'

const props = defineProps<{
  modalRetorno: boolean
  entregaRetorno: SaidaEntrega | null
  itensRetorno: ItemRetornoCarrada[]
  motivoRetorno: string
  observacaoRetorno: string
  registrandoRetorno: boolean
}>()

const emit = defineEmits<{
  fechar: []
  confirmar: []
  'update:motivoRetorno': [valor: string]
  'update:observacaoRetorno': [valor: string]
}>()

const motivoRetorno = computed({
  get: () => props.motivoRetorno,
  set: (valor: string) => emit('update:motivoRetorno', valor),
})

const observacaoRetorno = computed({
  get: () => props.observacaoRetorno,
  set: (valor: string) => emit('update:observacaoRetorno', valor),
})

function fecharRetorno() { emit('fechar') }
function confirmarRetorno() { emit('confirmar') }

function itemRetorno(entregaItemId: number) {
  return props.itensRetorno.find(
    (item) => item.entrega_item_id === entregaItemId,
  )
}

function quantidadeFaltanteRetorno(
  carregado: string | number,
  entregaItemId: number,
) {
  return Math.max(
    0,
    Number(carregado) - Number(itemRetorno(entregaItemId)?.quantidade_entregue ?? 0),
  )
}

function limparDestinoSemFalta(
  carregado: string | number,
  entregaItemId: number,
) {
  if (quantidadeFaltanteRetorno(carregado, entregaItemId) > 0) return
  const retorno = itemRetorno(entregaItemId)
  if (!retorno) return
  retorno.destino_nao_entregue = null
  retorno.motivo_devolucao = null
}

function alterarDestinoItemRetorno(entregaItemId: number) {
  const retorno = itemRetorno(entregaItemId)
  if (!retorno) return
  if (retorno.destino_nao_entregue !== 'DEVOLVIDO') {
    retorno.motivo_devolucao = null
  }
}
</script>

<template>
    <div
      v-if="
        modalRetorno &&
        entregaRetorno
      "
      class="modal-fundo retorno-modal-fundo"
      @click.self="fecharRetorno"
    >
      <section class="modal grande retorno-modal">
        <header class="modal-cabecalho">
          <div>
            <h2>Registrar retorno</h2>

            <p>
              Pedido
              {{
                entregaRetorno.numero_pedido ??
                `#${entregaRetorno.id}`
              }}
              ·
              {{ entregaRetorno.cliente_nome }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharRetorno"
          >
            ×
          </button>
        </header>

        <div class="aviso-retorno">
          Informe quanto foi realmente entregue. Quando
          houver falta, escolha o destino de cada produto:
          <strong>Reentrega</strong> ou
          <strong>Devolvido</strong>.
        </div>

        <div class="tabela-itens">
          <div class="retorno-cabecalho">
            <span>Produto</span>
            <span>Carregado</span>
            <span>Entregue</span>
            <span>Faltou</span>
            <span>Destino</span>
          </div>

          <div
            v-for="item in entregaRetorno.itens_programados"
            :key="item.entrega_item_id"
            class="retorno-item"
          >
            <div>
              <strong>{{ item.descricao_produto }}</strong>

              <small v-if="item.codigo_produto">
                Cód. {{ item.codigo_produto }}
              </small>
            </div>

            <div>
              <strong>{{ item.quantidade_programada }}</strong>
              {{ item.unidade || '' }}
            </div>

            <div>
              <input
                v-if="itemRetorno(item.entrega_item_id)"
                v-model.number="
                  itemRetorno(
                    item.entrega_item_id,
                  )!.quantidade_entregue
                "
                type="number"
                min="0"
                :max="Number(item.quantidade_programada)"
                step="0.001"
                @input="
                  limparDestinoSemFalta(
                    item.quantidade_programada,
                    item.entrega_item_id,
                  )
                "
              />
            </div>

            <div>
              <strong
                :class="{
                  'tem-falta':
                    quantidadeFaltanteRetorno(
                      item.quantidade_programada,
                      item.entrega_item_id,
                    ) > 0,
                }"
              >
                {{
                  quantidadeFaltanteRetorno(
                    item.quantidade_programada,
                    item.entrega_item_id,
                  )
                }}
                {{ item.unidade || '' }}
              </strong>
            </div>

            <div class="destino-retorno">
              <template
                v-if="
                  quantidadeFaltanteRetorno(
                    item.quantidade_programada,
                    item.entrega_item_id,
                  ) > 0 &&
                  itemRetorno(item.entrega_item_id)
                "
              >
                <select
                  v-model="
                    itemRetorno(
                      item.entrega_item_id,
                    )!.destino_nao_entregue
                  "
                  @change="
                    alterarDestinoItemRetorno(
                      item.entrega_item_id,
                    )
                  "
                >
                  <option :value="null">
                    Selecione...
                  </option>
                  <option value="REENTREGA">
                    Reentrega
                  </option>
                  <option value="DEVOLVIDO">
                    Devolvido
                  </option>
                </select>

                <select
                  v-if="
                    itemRetorno(
                      item.entrega_item_id,
                    )?.destino_nao_entregue ===
                    'DEVOLVIDO'
                  "
                  v-model="
                    itemRetorno(
                      item.entrega_item_id,
                    )!.motivo_devolucao
                  "
                  class="motivo-devolucao-select"
                >
                  <option :value="null">
                    Motivo...
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
              </template>

              <span v-else>—</span>
            </div>
          </div>
        </div>

        <div class="campo retorno-campo">
          <label>Motivo geral da ocorrência</label>

          <input
            v-model="motivoRetorno"
            type="text"
            placeholder="Ex.: cliente ausente, acesso bloqueado..."
          />

          <small>
            Opcional. O motivo de devolução é escolhido
            individualmente no produto.
          </small>
        </div>

        <div class="campo">
          <label>Observação</label>

          <textarea
            v-model="observacaoRetorno"
            rows="3"
            placeholder="Observações sobre esta entrega..."
          ></textarea>
        </div>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            @click="fecharRetorno"
          >
            Cancelar
          </button>

          <button
            class="botao finalizar"
            type="button"
            :disabled="registrandoRetorno"
            @click="confirmarRetorno"
          >
            {{
              registrandoRetorno
                ? 'Registrando...'
                : 'Confirmar retorno'
            }}
          </button>
        </footer>
      </section>
    </div>
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
