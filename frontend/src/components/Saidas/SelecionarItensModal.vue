<script setup lang="ts">
import type { EntregaDisponivelCarrada } from '@/types/Saida.ts'

const props = defineProps<{
  modalSelecionarItens: boolean
  entregaParaAdicionar: EntregaDisponivelCarrada | null
  quantidadesProgramadas: Record<number, number>
  salvando: boolean
}>()

const quantidadesProgramadas = props.quantidadesProgramadas

const emit = defineEmits<{
  fechar: []
  levarTudo: []
  limpar: []
  confirmar: []
}>()

function fecharSelecaoItens() { emit('fechar') }
function levarTudo() { emit('levarTudo') }
function limparQuantidades() { emit('limpar') }
function confirmarAdicionarEntrega() { emit('confirmar') }
</script>

<template>
    <div
      v-if="
        modalSelecionarItens &&
        entregaParaAdicionar
      "
      class="modal-fundo itens-modal-fundo"
      @click.self="
        fecharSelecaoItens
      "
    >
      <section class="modal medio">
        <header
          class="modal-cabecalho"
        >
          <div>
            <h2>
              Produtos do pedido
              {{
                entregaParaAdicionar
                  .numero_pedido
                  ? `#${entregaParaAdicionar.numero_pedido}`
                  : ''
              }}
            </h2>

            <p>
              {{
                entregaParaAdicionar
                  .cliente_nome
              }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="
              fecharSelecaoItens
            "
          >
            ×
          </button>
        </header>

        <div
          class="endereco-entrega"
        >
          <small>
            Endereço
          </small>

          <strong>
            {{
              entregaParaAdicionar
                .endereco
            }}
          </strong>
        </div>

        <div
          class="acoes-quantidade"
        >
          <button
            class="botao secundario"
            type="button"
            @click="levarTudo"
          >
            Levar todo o saldo
          </button>

          <button
            class="botao secundario"
            type="button"
            @click="
              limparQuantidades
            "
          >
            Zerar quantidades
          </button>
        </div>

        <div class="tabela-itens">
          <div
            class="item-cabecalho"
          >
            <span>
              Produto
            </span>

            <span>
              Pedido
            </span>

            <span>
              Disponível
            </span>

            <span>
              Levar agora
            </span>
          </div>

          <div
            v-for="
              item in
              entregaParaAdicionar.itens
            "
            :key="
              item.entrega_item_id
            "
            class="item-selecao"
          >
            <div>
              <strong>
                {{
                  item.descricao_produto
                }}
              </strong>

              <small
                v-if="
                  item.codigo_produto
                "
              >
                Cód.
                {{
                  item.codigo_produto
                }}
              </small>
            </div>

            <div>
              {{
                item.quantidade_solicitada
              }}
              {{ item.unidade || '' }}
            </div>

            <div>
              <strong>
                {{
                  item.quantidade_disponivel
                }}
              </strong>

              {{ item.unidade || '' }}
            </div>

            <div>
              <input
                v-model.number="
                  quantidadesProgramadas[
                    item.entrega_item_id
                  ]
                "
                type="number"
                min="0"
                :max="
                  item.quantidade_disponivel
                "
                step="0.001"
              />
            </div>
          </div>
        </div>

        <p
          class="aviso-capacidade"
        >
          Confira os produtos e as
          quantidades para decidir se
          o veículo escolhido suporta
          esta carrada.
        </p>

        <footer
          class="modal-acoes"
        >
          <button
            class="botao secundario"
            type="button"
            @click="
              fecharSelecaoItens
            "
          >
            Cancelar
          </button>

          <button
            class="botao primario"
            type="button"
            :disabled="salvando"
            @click="
              confirmarAdicionarEntrega
            "
          >
            {{
              salvando
                ? 'Adicionando...'
                : 'Adicionar à carrada'
            }}
          </button>
        </footer>
      </section>
    </div>
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
