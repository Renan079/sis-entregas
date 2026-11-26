<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { clienteService } from '@/services/clienteService'
import { entregaService } from '@/services/entregaService'
import { buscarPedidoExterno, cadastrarClienteExterno } from '@/services/integracaoService'
import { regiaoService } from '@/services/regiaoService'

import type { Cliente } from '@/types/Cliente'
import type { Regiao } from '@/types/Regiao'
import type {
  Entrega,
  EntregaForm,
  EntregaItemForm,
} from '@/types/Entrega'

interface ClienteExternoPendente {
  codigo_externo: string | null
  nome: string
  apelido: string | null
  telefone: string | null
}

const route = useRoute()
const router = useRouter()

const regioes = ref<Regiao[]>([])
const clientes = ref<Cliente[]>([])

const carregando = ref(false)
const salvando = ref(false)
const buscandoPedido = ref(false)
const erro = ref('')
const avisoIntegracao = ref('')
const buscaCliente = ref('')
const entregaCarregada = ref<Entrega | null>(null)

const modalCadastroClienteAberto = ref(false)
const salvandoClienteExterno = ref(false)
const clienteExternoPendente = ref<ClienteExternoPendente | null>(null)

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
  avisoIntegracao.value = ''

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

function fecharModalCadastroCliente() {
  modalCadastroClienteAberto.value = false
  clienteExternoPendente.value = null
}

async function confirmarCadastroClienteExterno() {
  if (!clienteExternoPendente.value) {
    return
  }

  salvandoClienteExterno.value = true
  erro.value = ''

  try {
    const resposta = await cadastrarClienteExterno({
      codigo_externo: clienteExternoPendente.value.codigo_externo,
      nome: clienteExternoPendente.value.nome,
      apelido: clienteExternoPendente.value.apelido,
      telefone: clienteExternoPendente.value.telefone,
    })

    formulario.cliente_id = resposta.cliente.id
    formulario.cliente_nome = resposta.cliente.nome
    formulario.cliente_apelido = resposta.cliente.apelido ?? ''
    buscaCliente.value = resposta.cliente.nome

    if (resposta.cliente.telefone) {
      formulario.cliente_telefone = resposta.cliente.telefone
    }

    fecharModalCadastroCliente()
    avisoIntegracao.value = `Cliente "${resposta.cliente.nome}" cadastrado e vinculado à entrega.`
  } catch (e: unknown) {
    const mensagem = (
      e as { response?: { data?: { message?: string } } }
    )?.response?.data?.message

    erro.value =
      mensagem || 'Não foi possível cadastrar o cliente.'
  } finally {
    salvandoClienteExterno.value = false
  }
}

async function buscarPedidoNoSistema() {
  if (editando.value || bloqueada.value) {
    return
  }

  const numero = formulario.numero_pedido?.trim()

  if (!numero) {
    erro.value = 'Informe o número do pedido para buscar no sistema.'
    return
  }

  buscandoPedido.value = true
  erro.value = ''
  avisoIntegracao.value = ''
  clientes.value = []
  fecharModalCadastroCliente()

  try {
    const pedido = await buscarPedidoExterno(numero)

    formulario.numero_pedido = pedido.numero_pedido
    formulario.cliente_id = pedido.cliente_id
    formulario.cliente_nome = pedido.cliente.nome
    formulario.cliente_apelido = pedido.cliente.apelido ?? ''
    formulario.cliente_telefone = pedido.cliente.telefone ?? ''
    buscaCliente.value = pedido.cliente.nome

    if (pedido.regiao_id) {
      formulario.regiao_id = pedido.regiao_id
    }

    formulario.endereco = pedido.endereco
    formulario.referencia = pedido.referencia ?? ''
    formulario.observacao = pedido.observacao ?? ''

    if (pedido.itens.length > 0) {
      formulario.itens = pedido.itens.map((item) => ({
        codigo_produto: item.codigo_produto ?? '',
        descricao_produto: item.descricao_produto,
        unidade: item.unidade || 'UN',
        quantidade_solicitada: Number(item.quantidade_solicitada),
        observacao: item.observacao ?? '',
      }))
    } else {
      formulario.itens = [novoItem()]
    }

    if (pedido.alertas.pedido_ja_existe) {
      avisoIntegracao.value = `Atenção: já existe uma entrega ativa cadastrada para o pedido #${pedido.numero_pedido}.`
    } else if (!pedido.alertas.regiao_encontrada) {
      avisoIntegracao.value =
        'Pedido carregado do sistema comercial. Selecione uma região antes de salvar.'
    } else {
      avisoIntegracao.value = `Pedido #${pedido.numero_pedido} carregado do sistema comercial.`
    }

    if (!pedido.alertas.cliente_cadastrado && pedido.cliente?.nome) {
      clienteExternoPendente.value = pedido.cliente
      modalCadastroClienteAberto.value = true
    }
  } catch (e: unknown) {
    const mensagem = (
      e as { response?: { data?: { message?: string } } }
    )?.response?.data?.message

    erro.value =
      mensagem || 'Não foi possível buscar o pedido no sistema comercial.'
  } finally {
    buscandoPedido.value = false
  }
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

      <p
        v-if="avisoIntegracao"
        class="estado-formulario"
      >
        {{ avisoIntegracao }}
      </p>

      <section class="card-formulario">
        <form @submit.prevent="salvarEntrega">
          <fieldset :disabled="bloqueada || salvando">
            <div class="grid-form">
              <div class="campo">
                <label>Número do pedido</label>

                <div class="cliente-busca">
                  <input
                    v-model="formulario.numero_pedido"
                    type="text"
                    placeholder="Ex: 9945"
                  />

                  <button
                    v-if="!editando"
                    class="botao secundario"
                    type="button"
                    :disabled="buscandoPedido || bloqueada || salvando"
                    @click="buscarPedidoNoSistema"
                  >
                    {{ buscandoPedido ? 'Buscando...' : 'Buscar' }}
                  </button>
                </div>
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

    <div
      v-if="modalCadastroClienteAberto && clienteExternoPendente"
      class="modal-backdrop"
    >
      <div class="modal-card">
        <h3>Cliente não cadastrado</h3>
        <p>
          Este cliente ainda não possui cadastro no sistema. Deseja cadastrá-lo com os dados da loja?
        </p>

        <div class="modal-dados">
          <div>
            <strong>Código externo:</strong>
            {{ clienteExternoPendente.codigo_externo || '—' }}
          </div>
          <div>
            <strong>Nome:</strong>
            {{ clienteExternoPendente.nome }}
          </div>
          <div v-if="clienteExternoPendente.apelido">
            <strong>Apelido:</strong>
            {{ clienteExternoPendente.apelido }}
          </div>
          <div>
            <strong>Telefone:</strong>
            {{ clienteExternoPendente.telefone || '—' }}
          </div>
        </div>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            :disabled="salvandoClienteExterno"
            @click="fecharModalCadastroCliente"
          >
            Não, apenas usar aqui
          </button>

          <button
            class="botao primario"
            type="button"
            :disabled="salvandoClienteExterno"
            @click="confirmarCadastroClienteExterno"
          >
            {{ salvandoClienteExterno ? 'Cadastrando...' : 'Sim, cadastrar cliente' }}
          </button>
        </footer>
      </div>
    </div>
  </main>
</template>

<style scoped src="../../styles/pages/entrega/entrega-formulario.css"></style>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.5);
}

.modal-card {
  width: 100%;
  max-width: 460px;
  padding: 1.5rem;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
}

.modal-dados {
  display: grid;
  gap: 0.4rem;
  margin: 1rem 0 1.25rem;
  padding: 0.85rem;
  border-radius: 8px;
  background: #f8fafc;
}

.modal-acoes {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>