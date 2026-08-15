<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { clienteService } from '@/services/clienteService'
import type { Cliente, ClienteForm } from '@/types/Cliente'

const clientes = ref<Cliente[]>([])
const busca = ref('')
const carregando = ref(false)
const salvando = ref(false)
const erro = ref('')

const modalAberto = ref(false)
const clienteEditandoId = ref<number | null>(null)

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const formulario = reactive<ClienteForm>({
  codigo_externo: '',
  nome: '',
  apelido: '',
  telefone: '',
  ativo: true,
})

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

function limparFormulario() {
  formulario.codigo_externo = ''
  formulario.nome = ''
  formulario.apelido = ''
  formulario.telefone = ''
  formulario.ativo = true

  clienteEditandoId.value = null
}

function novoCliente() {
  limparFormulario()
  modalAberto.value = true
}

function editarCliente(cliente: Cliente) {
  clienteEditandoId.value = cliente.id

  formulario.codigo_externo = cliente.codigo_externo ?? ''
  formulario.nome = cliente.nome
  formulario.apelido = cliente.apelido ?? ''
  formulario.telefone = cliente.telefone ?? ''
  formulario.ativo = cliente.ativo

  modalAberto.value = true
}

function fecharModal() {
  modalAberto.value = false
  limparFormulario()
}

async function salvarCliente() {
  erro.value = ''

  if (!formulario.nome.trim()) {
    erro.value = 'Informe o nome do cliente.'
    return
  }

  salvando.value = true

  const dados: ClienteForm = {
    codigo_externo: formulario.codigo_externo?.trim() || null,
    nome: formulario.nome.trim(),
    apelido: formulario.apelido?.trim() || null,
    telefone: formulario.telefone?.trim() || null,
    ativo: formulario.ativo,
  }

  try {
    if (clienteEditandoId.value) {
      await clienteService.atualizar(
        clienteEditandoId.value,
        dados,
      )
    } else {
      await clienteService.criar(dados)
    }

    fecharModal()
    await carregarClientes(paginaAtual.value)
  } catch {
    erro.value = 'Não foi possível salvar o cliente.'
  } finally {
    salvando.value = false
  }
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

function pesquisar() {
  carregarClientes(1)
}

function limparBusca() {
  busca.value = ''
  carregarClientes(1)
}

onMounted(() => {
  carregarClientes()
})
</script>

<template>
  <main class="pagina">
    <header class="cabecalho">
      <div>
        <h1>Clientes</h1>
        <p>Gerencie os clientes cadastrados no sistema.</p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="novoCliente"
      >
        + Novo cliente
      </button>
    </header>

    <section class="card filtros">
      <form class="busca" @submit.prevent="pesquisar">
        <input
          v-model="busca"
          type="text"
          placeholder="Buscar por nome, apelido, código ou telefone..."
        />

        <button
          class="botao primario"
          type="submit"
        >
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
      </form>
    </section>

    <p v-if="erro" class="erro">
      {{ erro }}
    </p>

    <section class="card">
      <div class="topo-tabela">
        <strong>
          {{ total }} cliente(s)
        </strong>
      </div>

      <div v-if="carregando" class="estado">
        Carregando clientes...
      </div>

      <div
        v-else-if="clientes.length === 0"
        class="estado"
      >
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
            >
              <td>
                {{ cliente.codigo_externo || '—' }}
              </td>

              <td>
                <strong>{{ cliente.nome }}</strong>
              </td>

              <td>
                {{ cliente.apelido || '—' }}
              </td>

              <td>
                {{ cliente.telefone || '—' }}
              </td>

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
                  class="acao"
                  type="button"
                  @click="editarCliente(cliente)"
                >
                  Editar
                </button>

                <button
                  v-if="cliente.ativo"
                  class="acao perigo"
                  type="button"
                  @click="desativarCliente(cliente)"
                >
                  Desativar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="ultimaPagina > 1"
        class="paginacao"
      >
        <button
          class="botao secundario"
          type="button"
          :disabled="paginaAtual <= 1"
          @click="carregarClientes(paginaAtual - 1)"
        >
          Anterior
        </button>

        <span>
          Página {{ paginaAtual }} de {{ ultimaPagina }}
        </span>

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

    <div
      v-if="modalAberto"
      class="modal-fundo"
      @click.self="fecharModal"
    >
      <section class="modal">
        <header class="modal-cabecalho">
          <div>
            <h2>
              {{
                clienteEditandoId
                  ? 'Editar cliente'
                  : 'Novo cliente'
              }}
            </h2>

            <p>
              Preencha os dados do cliente.
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharModal"
          >
            ×
          </button>
        </header>

        <form @submit.prevent="salvarCliente">
          <div class="grid-form">
            <div class="campo">
              <label for="nome">
                Nome *
              </label>

              <input
                id="nome"
                v-model="formulario.nome"
                type="text"
                maxlength="180"
                required
              />
            </div>

            <div class="campo">
              <label for="codigo">
                Código externo
              </label>

              <input
                id="codigo"
                v-model="formulario.codigo_externo"
                type="text"
                maxlength="50"
              />
            </div>

            <div class="campo">
              <label for="apelido">
                Apelido
              </label>

              <input
                id="apelido"
                v-model="formulario.apelido"
                type="text"
                maxlength="150"
              />
            </div>

            <div class="campo">
              <label for="telefone">
                Telefone
              </label>

              <input
                id="telefone"
                v-model="formulario.telefone"
                type="text"
                maxlength="30"
              />
            </div>
          </div>

          <label
            v-if="clienteEditandoId"
            class="checkbox"
          >
            <input
              v-model="formulario.ativo"
              type="checkbox"
            />

            Cliente ativo
          </label>

          <footer class="modal-acoes">
            <button
              class="botao secundario"
              type="button"
              @click="fecharModal"
            >
              Cancelar
            </button>

            <button
              class="botao primario"
              type="submit"
              :disabled="salvando"
            >
              {{
                salvando
                  ? 'Salvando...'
                  : 'Salvar cliente'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </main>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.pagina {
  min-height: 100vh;
  padding: 32px;
  background: #f5f7fa;
  color: #172033;
  font-family:
    Inter,
    system-ui,
    sans-serif;
}

.cabecalho {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.cabecalho h1 {
  margin: 0;
  font-size: 28px;
}

.cabecalho p,
.modal-cabecalho p {
  margin: 6px 0 0;
  color: #667085;
}

.card {
  background: white;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgb(16 24 40 / 5%);
}

.filtros {
  margin-bottom: 20px;
  padding: 18px;
}

.busca {
  display: flex;
  gap: 10px;
}

.busca input {
  flex: 1;
}

input {
  width: 100%;
  min-height: 42px;
  padding: 10px 12px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: white;
  font-size: 14px;
  outline: none;
}

input:focus {
  border-color: #667085;
  box-shadow: 0 0 0 3px rgb(102 112 133 / 10%);
}

.botao {
  min-height: 42px;
  padding: 0 16px;
  border: 0;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.botao:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.primario {
  background: #172033;
  color: white;
}

.secundario {
  border: 1px solid #d0d5dd;
  background: white;
  color: #344054;
}

.topo-tabela {
  padding: 18px 20px;
  border-bottom: 1px solid #eaecf0;
}

.tabela-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  padding: 12px 20px;
  background: #f9fafb;
  color: #667085;
  font-size: 12px;
  text-align: left;
  text-transform: uppercase;
}

td {
  padding: 16px 20px;
  border-top: 1px solid #eaecf0;
  font-size: 14px;
}

tbody tr:hover {
  background: #fafafa;
}

.status {
  display: inline-flex;
  padding: 4px 9px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.status.ativo {
  background: #ecfdf3;
  color: #027a48;
}

.status.inativo {
  background: #f2f4f7;
  color: #667085;
}

.acoes-coluna {
  text-align: right;
}

.acoes {
  text-align: right;
  white-space: nowrap;
}

.acao {
  margin-left: 12px;
  border: 0;
  background: transparent;
  color: #344054;
  font-weight: 600;
  cursor: pointer;
}

.acao.perigo {
  color: #b42318;
}

.estado {
  padding: 50px 20px;
  color: #667085;
  text-align: center;
}

.erro {
  padding: 12px 16px;
  border: 1px solid #fecdca;
  border-radius: 8px;
  background: #fef3f2;
  color: #b42318;
}

.paginacao {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-top: 1px solid #eaecf0;
  color: #667085;
  font-size: 14px;
}

.modal-fundo {
  position: fixed;
  z-index: 100;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgb(16 24 40 / 55%);
}

.modal {
  width: 100%;
  max-width: 650px;
  padding: 24px;
  border-radius: 14px;
  background: white;
  box-shadow: 0 24px 48px rgb(16 24 40 / 20%);
}

.modal-cabecalho {
  display: flex;
  justify-content: space-between;
  margin-bottom: 24px;
}

.modal-cabecalho h2 {
  margin: 0;
}

.fechar {
  border: 0;
  background: transparent;
  color: #667085;
  font-size: 28px;
  cursor: pointer;
}

.grid-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}

.campo label {
  display: block;
  margin-bottom: 7px;
  font-size: 14px;
  font-weight: 600;
}

.checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 20px;
  font-size: 14px;
}

.checkbox input {
  width: auto;
  min-height: auto;
}

.modal-acoes {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 28px;
}

@media (max-width: 700px) {
  .pagina {
    padding: 18px;
  }

  .cabecalho,
  .busca {
    align-items: stretch;
    flex-direction: column;
  }

  .grid-form {
    grid-template-columns: 1fr;
  }
}
</style>