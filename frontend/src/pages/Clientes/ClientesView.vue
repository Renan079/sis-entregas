<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { clienteService } from '@/services/clienteService'
import ClienteFormularioModal from '@/components/Clientes/ClienteFormularioModal.vue'

import type { Cliente } from '@/types/Cliente'

const router = useRouter()

const clientes = ref<Cliente[]>([])
const busca = ref('')
const carregando = ref(false)
const erro = ref('')

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const modalFormulario = ref(false)
const clienteSelecionado = ref<Cliente | null>(null)

async function carregarClientes(page = 1) {
  carregando.value = true
  erro.value = ''

  try {
    const response = await clienteService.listar(busca.value, page)

    clientes.value = response.data
    paginaAtual.value = response.meta.current_page
    ultimaPagina.value = response.meta.last_page
    total.value = response.meta.total
  } catch {
    erro.value = 'Não foi possível carregar os clientes.'
  } finally {
    carregando.value = false
  }
}

function pesquisar() {
  carregarClientes(1)
}

function limparBusca() {
  busca.value = ''
  carregarClientes(1)
}

function novoCliente() {
  clienteSelecionado.value = null
  modalFormulario.value = true
}

function editarCliente(cliente: Cliente) {
  clienteSelecionado.value = cliente
  modalFormulario.value = true
}

function fecharFormulario() {
  modalFormulario.value = false
  clienteSelecionado.value = null
}

async function clienteSalvo() {
  fecharFormulario()
  await carregarClientes(paginaAtual.value)
}

async function desativarCliente(cliente: Cliente) {
  const confirmou = window.confirm(
    `Deseja desativar o cliente "${cliente.nome}"?`,
  )

  if (!confirmou) {
    return
  }

  try {
    await clienteService.desativar(cliente.id)
    await carregarClientes(paginaAtual.value)
  } catch {
    erro.value = 'Não foi possível desativar o cliente.'
  }
}

function abrirHistorico(cliente: Cliente) {
  router.push({
    name: 'cliente-historico',
    params: { id: cliente.id },
  })
}

onMounted(() => {
  carregarClientes()
})
</script>

<template>
  <main class="pagina-clientes">
    <header class="cabecalho-clientes">
      <div>
        <span class="eyebrow">Cadastro e relacionamento</span>
        <h1>Clientes</h1>
        <p>
          Consulte cadastros, edite informações e acompanhe o histórico de entregas.
        </p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="novoCliente"
      >
        <span class="botao-icone">+</span>
        Novo cliente
      </button>
    </header>

    <section class="card filtros-clientes">
      <form class="busca-clientes" @submit.prevent="pesquisar">
        <div class="campo-busca">
          <label for="busca-cliente">Buscar cliente</label>
          <div class="input-busca">
            <span aria-hidden="true">⌕</span>
            <input
              id="busca-cliente"
              v-model="busca"
              type="search"
              placeholder="Nome, apelido, código ou telefone..."
            />
          </div>
        </div>

        <div class="acoes-busca">
          <button class="botao primario" type="submit">
            Buscar
          </button>

          <button
            v-if="busca"
            class="botao secundario"
            type="button"
            @click="limparBusca"
          >
            Limpar
          </button>
        </div>
      </form>
    </section>

    <p v-if="erro" class="mensagem erro">
      {{ erro }}
    </p>

    <section class="card tabela-card">
      <div class="topo-tabela">
        <div>
          <strong>{{ total }} cliente(s)</strong>
          <span>Clique em um cliente para consultar o histórico de entregas.</span>
        </div>
      </div>

      <div v-if="carregando" class="estado">
        Carregando clientes...
      </div>

      <div v-else-if="clientes.length === 0" class="estado">
        Nenhum cliente encontrado.
      </div>

      <div v-else class="tabela-container">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>Nome</th>
              <th>Apelido</th>
              <th>Telefone</th>
              <th>Status</th>
              <th class="acoes-coluna">Ações</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="cliente in clientes"
              :key="cliente.id"
              class="linha-cliente"
              title="Clique para ver as entregas deste cliente"
              @click="abrirHistorico(cliente)"
            >
              <td>
                <span class="codigo-cliente">
                  {{ cliente.codigo_externo || '—' }}
                </span>
              </td>

              <td>
                <strong>{{ cliente.nome }}</strong>
              </td>

              <td>{{ cliente.apelido || '—' }}</td>
              <td>{{ cliente.telefone || '—' }}</td>

              <td>
                <span
                  class="status"
                  :class="cliente.ativo ? 'ativo' : 'inativo'"
                >
                  {{ cliente.ativo ? 'Ativo' : 'Inativo' }}
                </span>
              </td>

              <td class="acoes">
                <button
                  class="acao editar"
                  type="button"
                  @click.stop="editarCliente(cliente)"
                >
                  Editar
                </button>

                <button
                  v-if="cliente.ativo"
                  class="acao perigo"
                  type="button"
                  @click.stop="desativarCliente(cliente)"
                >
                  Desativar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="ultimaPagina > 1" class="paginacao">
        <button
          class="botao secundario"
          type="button"
          :disabled="paginaAtual <= 1"
          @click="carregarClientes(paginaAtual - 1)"
        >
          Anterior
        </button>

        <span>Página {{ paginaAtual }} de {{ ultimaPagina }}</span>

        <button
          class="botao secundario"
          type="button"
          :disabled="paginaAtual >= ultimaPagina"
          @click="carregarClientes(paginaAtual + 1)"
        >
          Próxima
        </button>
      </footer>
    </section>

    <ClienteFormularioModal
      :aberto="modalFormulario"
      :cliente="clienteSelecionado"
      @fechar="fecharFormulario"
      @salvo="clienteSalvo"
    />
  </main>
</template>

<style scoped src="../../styles/pages/cliente/clientes.css"></style>
