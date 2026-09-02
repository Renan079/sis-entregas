<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { clienteService } from '@/services/clienteService'
import { entregaService } from '@/services/entregaService'
import { regiaoService } from '@/services/regiaoService'

import type { Cliente } from '@/types/Cliente'
import type { Regiao } from '@/types/Regiao'
import type {
  Entrega,
  EntregaForm,
  EntregaItemForm,
} from '@/types/Entrega'

const route = useRoute()
const router = useRouter()

const regioes = ref<Regiao[]>([])
const clientes = ref<Cliente[]>([])

const carregando = ref(false)
const salvando = ref(false)
const erro = ref('')
const buscaCliente = ref('')
const entregaCarregada = ref<Entrega | null>(null)

const entregaId = computed(() => {
  const valor = Number(route.params.id)
  return Number.isFinite(valor) && valor > 0 ? valor : null
})

const editando = computed(() => entregaId.value !== null)

const titulo = computed(() =>
  editando.value ? 'Editar entrega' : 'Nova entrega',
)

const bloqueada = computed(() => {
  const status = entregaCarregada.value?.status

  return status === 'CONCLUIDA' || status === 'CANCELADA'
})

function novoItem(): EntregaItemForm {
  return {
    codigo_produto: '',
    descricao_produto: '',
    unidade: 'UN',
    quantidade_solicitada: 1,
    observacao: '',
  }
}

function criarFormularioVazio(): EntregaForm {
  return {
    numero_pedido: '',
    cliente_id: null,
    regiao_id: 0,
    cliente_nome: '',
    cliente_apelido: '',
    cliente_telefone: '',
    endereco: '',
    referencia: '',
    prioridade: 'NORMAL',
    observacao: '',
    itens: [novoItem()],
  }
}

const formulario = reactive<EntregaForm>(
  criarFormularioVazio(),
)

async function carregarRegioes() {
  const response = await regiaoService.listar(1)

  regioes.value = response.data.filter(
    (regiao) => regiao.ativo,
  )
}

function preencherFormulario(entrega: Entrega) {
  formulario.numero_pedido = entrega.numero_pedido ?? ''
  formulario.cliente_id = entrega.cliente?.id ?? null
  formulario.regiao_id = entrega.regiao.id
  formulario.cliente_nome = entrega.cliente_nome
  formulario.cliente_apelido = entrega.cliente_apelido ?? ''
  formulario.cliente_telefone = entrega.cliente_telefone ?? ''
  formulario.endereco = entrega.endereco
  formulario.referencia = entrega.referencia ?? ''
  formulario.prioridade = entrega.prioridade
  formulario.observacao = entrega.observacao ?? ''

  formulario.itens = entrega.itens.map((item) => ({
    codigo_produto: item.codigo_produto ?? '',
    descricao_produto: item.descricao_produto,
    unidade: item.unidade ?? '',
    quantidade_solicitada: Number(item.quantidade_solicitada),
    observacao: item.observacao ?? '',
  }))

  buscaCliente.value = entrega.cliente_nome
}

async function carregarPagina() {
  carregando.value = true
  erro.value = ''

  try {
    await carregarRegioes()

    if (!entregaId.value) {
      return
    }

    const entrega = await entregaService.buscar(entregaId.value)
    entregaCarregada.value = entrega
    preencherFormulario(entrega)

    if (bloqueada.value) {
      erro.value =
        'Esta entrega está concluída ou cancelada e não pode mais ser editada.'
    }
  } catch {
    erro.value = 'Não foi possível carregar os dados da entrega.'
  } finally {
    carregando.value = false
  }
}

async function pesquisarClientes() {
  try {
    const response = await clienteService.listar(
      buscaCliente.value,
      1,
    )

    clientes.value = response.data.filter(
      (cliente) => cliente.ativo,
    )
  } catch {
    erro.value = 'Não foi possível pesquisar clientes.'
  }
}

function selecionarCliente(cliente: Cliente) {
  formulario.cliente_id = cliente.id
  formulario.cliente_nome = cliente.nome
  formulario.cliente_apelido = cliente.apelido ?? ''
  formulario.cliente_telefone = cliente.telefone ?? ''

  buscaCliente.value = cliente.nome
  clientes.value = []
}

function limparClienteSelecionado() {
  formulario.cliente_id = null
  formulario.cliente_nome = ''
  formulario.cliente_apelido = ''
  formulario.cliente_telefone = ''
  buscaCliente.value = ''
  clientes.value = []
}

function adicionarItem() {
  formulario.itens.push(novoItem())
}

function removerItem(index: number) {
  if (formulario.itens.length === 1) {
    return
  }

  formulario.itens.splice(index, 1)
}

async function voltar() {
  await router.push({ name: 'entregas' })
}

function montarDados(): EntregaForm | null {
  erro.value = ''

  if (!formulario.cliente_nome.trim()) {
    erro.value = 'Informe o cliente.'
    return null
  }

  if (!formulario.regiao_id) {
    erro.value = 'Selecione uma região.'
    return null
  }

  if (!formulario.endereco.trim()) {
    erro.value = 'Informe o endereço da entrega.'
    return null
  }

  const itemInvalido = formulario.itens.some(
    (item) =>
      !item.descricao_produto.trim() ||
      Number(item.quantidade_solicitada) <= 0,
  )

  if (itemInvalido) {
    erro.value =
      'Preencha corretamente os produtos da entrega.'
    return null
  }

  return {
    numero_pedido:
      formulario.numero_pedido?.trim() || null,
    cliente_id: formulario.cliente_id,
    regiao_id: formulario.regiao_id,
    cliente_nome: formulario.cliente_nome.trim(),
    cliente_apelido:
      formulario.cliente_apelido?.trim() || null,
    cliente_telefone:
      formulario.cliente_telefone?.trim() || null,
    endereco: formulario.endereco.trim(),
    referencia:
      formulario.referencia?.trim() || null,
    prioridade: formulario.prioridade,
    observacao:
      formulario.observacao?.trim() || null,
    itens: formulario.itens.map((item) => ({
      codigo_produto:
        item.codigo_produto?.trim() || null,
      descricao_produto:
        item.descricao_produto.trim(),
      unidade:
        item.unidade?.trim() || null,
      quantidade_solicitada:
        Number(item.quantidade_solicitada),
      observacao:
        item.observacao?.trim() || null,
    })),
  }
}

async function salvarEntrega() {
  if (bloqueada.value) {
    return
  }

  const dados = montarDados()

  if (!dados) {
    return
  }

  salvando.value = true

  try {
    if (entregaId.value) {
      await entregaService.atualizar(
        entregaId.value,
        dados,
      )
    } else {
      await entregaService.criar(dados)
    }

    await router.push({ name: 'entregas' })
  } catch {
    erro.value = 'Não foi possível salvar a entrega.'
  } finally {
    salvando.value = false
  }
}

onMounted(carregarPagina)
</script>

<template>
  <main class="pagina-entrega-formulario">
    <header class="cabecalho-entrega-formulario">
      <div>
        <button
          class="voltar-link"
          type="button"
          @click="voltar"
        >
          ← Voltar para entregas
        </button>

        <h1>{{ titulo }}</h1>

        <p>
          {{
            editando
              ? 'Atualize os dados e produtos desta entrega.'
              : 'Informe os dados da nova entrega.'
          }}
        </p>
      </div>
    </header>

    <div
      v-if="carregando"
      class="estado-formulario"
    >
      Carregando entrega...
    </div>

    <template v-else>
      <p
        v-if="erro"
        class="erro"
      >
        {{ erro }}
      </p>

      <section class="card-formulario">
        <form @submit.prevent="salvarEntrega">
          <fieldset :disabled="bloqueada || salvando">
            <div class="grid-form">
              <div class="campo">
                <label>Número do pedido</label>

                <input
                  v-model="formulario.numero_pedido"
                  type="text"
                  placeholder="Ex: 23249"
                />
              </div>

              <div class="campo">
                <label>Região *</label>

                <select
                  v-model="formulario.regiao_id"
                  required
                >
                  <option :value="0">
                    Selecione...
                  </option>

                  <option
                    v-for="regiao in regioes"
                    :key="regiao.id"
                    :value="regiao.id"
                  >
                    {{ regiao.nome }}
                  </option>
                </select>
              </div>
            </div>

            <div class="campo cliente-campo">
              <label>Cliente *</label>

              <div class="cliente-busca">
                <input
                  v-model="buscaCliente"
                  type="text"
                  placeholder="Digite o nome do cliente..."
                  @input="pesquisarClientes"
                />

                <button
                  v-if="formulario.cliente_id"
                  class="botao secundario"
                  type="button"
                  @click="limparClienteSelecionado"
                >
                  Limpar
                </button>
              </div>

              <div
                v-if="clientes.length"
                class="resultado-clientes"
              >
                <button
                  v-for="cliente in clientes"
                  :key="cliente.id"
                  type="button"
                  @click="selecionarCliente(cliente)"
                >
                  <strong>{{ cliente.nome }}</strong>

                  <span v-if="cliente.apelido">
                    {{ cliente.apelido }}
                  </span>

                  <small v-if="cliente.telefone">
                    {{ cliente.telefone }}
                  </small>
                </button>
              </div>
            </div>

            <div class="grid-form">
              <div class="campo">
                <label>Telefone</label>

                <input
                  v-model="formulario.cliente_telefone"
                  type="text"
                />
              </div>

              <div class="campo">
                <label>Prioridade</label>

                <select v-model="formulario.prioridade">
                  <option value="BAIXA">Baixa</option>
                  <option value="NORMAL">Normal</option>
                  <option value="ALTA">Alta</option>
                  <option value="URGENTE">Urgente</option>
                </select>
              </div>
            </div>

            <div class="campo">
              <label>Endereço *</label>

              <input
                v-model="formulario.endereco"
                type="text"
                required
              />
            </div>

            <div class="campo">
              <label>Referência</label>

              <input
                v-model="formulario.referencia"
                type="text"
                placeholder="Ex: Em frente ao mercantil"
              />
            </div>

            <div class="produtos-cabecalho">
              <div>
                <h2>Produtos</h2>
                <p>Itens que deverão ser entregues.</p>
              </div>

              <button
                class="botao secundario"
                type="button"
                @click="adicionarItem"
              >
                + Adicionar produto
              </button>
            </div>

            <div
              v-for="(item, index) in formulario.itens"
              :key="index"
              class="produto"
            >
              <div class="produto-numero">
                Produto {{ index + 1 }}
              </div>

              <div class="produto-grid">
                <div class="campo">
                  <label>Código</label>

                  <input
                    v-model="item.codigo_produto"
                    type="text"
                  />
                </div>

                <div class="campo descricao">
                  <label>Descrição *</label>

                  <input
                    v-model="item.descricao_produto"
                    type="text"
                    required
                  />
                </div>

                <div class="campo">
                  <label>Unidade</label>

                  <input
                    v-model="item.unidade"
                    type="text"
                    placeholder="UN"
                  />
                </div>

                <div class="campo">
                  <label>Quantidade *</label>

                  <input
                    v-model.number="item.quantidade_solicitada"
                    type="number"
                    min="0.001"
                    step="0.001"
                    required
                  />
                </div>
              </div>

              <button
                v-if="formulario.itens.length > 1"
                class="remover-produto"
                type="button"
                @click="removerItem(index)"
              >
                Remover produto
              </button>
            </div>

            <div class="campo">
              <label>Observação da entrega</label>

              <textarea
                v-model="formulario.observacao"
                rows="3"
              ></textarea>
            </div>
          </fieldset>

          <footer class="acoes-formulario">
            <button
              class="botao secundario"
              type="button"
              :disabled="salvando"
              @click="voltar"
            >
              Cancelar
            </button>

            <button
              v-if="!bloqueada"
              class="botao primario"
              type="submit"
              :disabled="salvando"
            >
              {{ salvando ? 'Salvando...' : 'Salvar entrega' }}
            </button>
          </footer>
        </form>
      </section>
    </template>
  </main>
</template>

<style scoped src="../../styles/pages/entrega/entrega-formulario.css"></style>
