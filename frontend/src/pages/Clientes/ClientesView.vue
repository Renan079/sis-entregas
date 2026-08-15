<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { clienteService } from '@/services/clienteService'
import { clienteHistoricoService } from '@/services/clienteHistoricoService'

import type { Cliente, ClienteForm } from '@/types/Cliente'
import type {
  ClienteHistorico,
  ClienteHistoricoEntrega,
  StatusEntregaCliente,
} from '@/types/ClienteHistorico'

const clientes = ref<Cliente[]>([])
const busca = ref('')
const carregando = ref(false)
const salvando = ref(false)
const erro = ref('')

const modalAberto = ref(false)
const clienteEditandoId = ref<number | null>(null)

const modalHistorico = ref(false)
const carregandoHistorico = ref(false)
const historicoCliente = ref<ClienteHistorico | null>(null)
const clienteHistoricoSelecionado = ref<Cliente | null>(null)

const paginaHistorico = ref(1)
const ultimaPaginaHistorico = ref(1)

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

async function abrirHistoricoCliente(
  cliente: Cliente,
  page = 1,
) {
  clienteHistoricoSelecionado.value = cliente
  modalHistorico.value = true
  carregandoHistorico.value = true
  erro.value = ''

  try {
    const response =
      await clienteHistoricoService.buscarEntregas(
        cliente.id,
        page,
      )

    historicoCliente.value = response

    paginaHistorico.value =
      response.meta.current_page

    ultimaPaginaHistorico.value =
      response.meta.last_page
  } catch {
    erro.value =
      'Não foi possível carregar as entregas do cliente.'
  } finally {
    carregandoHistorico.value = false
  }
}

function fecharHistoricoCliente() {
  modalHistorico.value = false
  historicoCliente.value = null
  clienteHistoricoSelecionado.value = null
  paginaHistorico.value = 1
  ultimaPaginaHistorico.value = 1
}

function statusEntregaLabel(
  status: StatusEntregaCliente,
) {
  const labels: Record<
    StatusEntregaCliente,
    string
  > = {
    PENDENTE: 'Pendente',
    PROGRAMADA: 'Programada',
    EM_SAIDA: 'Em saída',
    PARCIAL: 'Parcial',
    CONCLUIDA: 'Concluída',
    NAO_ENTREGUE: 'Não entregue',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function formatarData(data: string) {
  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat(
    'pt-BR',
    {
      dateStyle: 'short',
      timeStyle: 'short',
    },
  ).format(valor)
}

function formatarQuantidade(
  quantidade: number,
) {
  return new Intl.NumberFormat(
    'pt-BR',
    {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3,
    },
  ).format(Number(quantidade))
}

function tituloPedido(
  entrega: ClienteHistoricoEntrega,
) {
  return entrega.numero_pedido
    ? `Pedido #${entrega.numero_pedido}`
    : `Entrega #${entrega.id}`
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
              class="linha-cliente"
              title="Clique para ver as entregas deste cliente"
              @click="abrirHistoricoCliente(cliente)"
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

    <!-- HISTÓRICO / ENTREGAS DO CLIENTE -->
    <div
      v-if="modalHistorico"
      class="modal-fundo"
      @click.self="fecharHistoricoCliente"
    >
      <section class="modal modal-historico-cliente">
        <header class="modal-cabecalho historico-cabecalho">
          <div>
            <span class="historico-eyebrow">
              Histórico do cliente
            </span>

            <h2>
              {{
                historicoCliente?.cliente.nome ??
                clienteHistoricoSelecionado?.nome ??
                'Cliente'
              }}
            </h2>

            <p>
              <template
                v-if="
                  historicoCliente?.cliente.codigo_externo
                "
              >
                Código:
                {{
                  historicoCliente.cliente.codigo_externo
                }}
                ·
              </template>

              {{
                historicoCliente?.cliente.telefone ??
                clienteHistoricoSelecionado?.telefone ??
                'Sem telefone'
              }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharHistoricoCliente"
          >
            ×
          </button>
        </header>

        <div
          v-if="carregandoHistorico"
          class="estado historico-carregando"
        >
          Carregando entregas do cliente...
        </div>

        <template v-else-if="historicoCliente">
          <section class="historico-resumo">
            <article class="resumo-item">
              <small>Total de entregas</small>
              <strong>
                {{ historicoCliente.resumo.total }}
              </strong>
            </article>

            <article class="resumo-item pendente">
              <small>Pendentes</small>
              <strong>
                {{ historicoCliente.resumo.pendentes }}
              </strong>
            </article>

            <article class="resumo-item parcial">
              <small>Parciais</small>
              <strong>
                {{ historicoCliente.resumo.parciais }}
              </strong>
            </article>

            <article class="resumo-item concluida">
              <small>Concluídas</small>
              <strong>
                {{ historicoCliente.resumo.concluidas }}
              </strong>
            </article>
          </section>

          <div class="historico-subresumo">
            <span>
              Programadas:
              <strong>
                {{ historicoCliente.resumo.programadas }}
              </strong>
            </span>

            <span>
              Em saída:
              <strong>
                {{ historicoCliente.resumo.em_saida }}
              </strong>
            </span>

            <span>
              Não entregues:
              <strong>
                {{ historicoCliente.resumo.nao_entregues }}
              </strong>
            </span>

            <span>
              Canceladas:
              <strong>
                {{ historicoCliente.resumo.canceladas }}
              </strong>
            </span>
          </div>

          <section class="historico-lista">
            <header class="historico-lista-cabecalho">
              <div>
                <h3>Entregas</h3>
                <p>
                  Da mais recente para a mais antiga.
                </p>
              </div>

              <strong>
                {{ historicoCliente.meta.total }}
                registro(s)
              </strong>
            </header>

            <div
              v-if="
                historicoCliente.entregas.length === 0
              "
              class="historico-vazio"
            >
              Este cliente ainda não possui entregas.
            </div>

            <article
              v-for="
                entrega in historicoCliente.entregas
              "
              v-else
              :key="entrega.id"
              class="entrega-cliente-card"
            >
              <header class="entrega-cliente-topo">
                <div>
                  <strong class="pedido-numero">
                    {{ tituloPedido(entrega) }}
                  </strong>

                  <span class="entrega-data">
                    {{ formatarData(entrega.created_at) }}
                  </span>
                </div>

                <span
                  class="status status-entrega"
                  :class="
                    entrega.status.toLowerCase()
                  "
                >
                  {{ statusEntregaLabel(entrega.status) }}
                </span>
              </header>

              <div class="entrega-local">
                <div>
                  <small>Região</small>
                  <strong>
                    {{
                      entrega.regiao?.nome ??
                      'Sem região'
                    }}
                  </strong>
                </div>

                <div>
                  <small>Endereço</small>
                  <strong>
                    {{ entrega.endereco }}
                  </strong>

                  <span v-if="entrega.referencia">
                    Ref.: {{ entrega.referencia }}
                  </span>
                </div>
              </div>

              <div class="entrega-produtos">
                <div class="produtos-titulo">
                  <strong>Produtos</strong>
                  <span>
                    {{ entrega.itens.length }}
                    item(ns)
                  </span>
                </div>

                <div
                  v-for="item in entrega.itens"
                  :key="item.id"
                  class="produto-linha"
                >
                  <div>
                    <strong>
                      {{ item.descricao_produto }}
                    </strong>

                    <small v-if="item.codigo_produto">
                      Cód. {{ item.codigo_produto }}
                    </small>
                  </div>

                  <strong class="produto-quantidade">
                    {{
                      formatarQuantidade(
                        item.quantidade_solicitada,
                      )
                    }}
                    {{ item.unidade ?? '' }}
                  </strong>
                </div>
              </div>

              <div
                v-if="entrega.observacao"
                class="entrega-observacao"
              >
                <small>Observação</small>
                <p>{{ entrega.observacao }}</p>
              </div>
            </article>
          </section>

          <footer
            v-if="ultimaPaginaHistorico > 1"
            class="paginacao historico-paginacao"
          >
            <button
              class="botao secundario"
              type="button"
              :disabled="paginaHistorico <= 1"
              @click="
                abrirHistoricoCliente(
                  clienteHistoricoSelecionado!,
                  paginaHistorico - 1,
                )
              "
            >
              Anterior
            </button>

            <span>
              Página
              {{ paginaHistorico }}
              de
              {{ ultimaPaginaHistorico }}
            </span>

            <button
              class="botao secundario"
              type="button"
              :disabled="
                paginaHistorico >=
                ultimaPaginaHistorico
              "
              @click="
                abrirHistoricoCliente(
                  clienteHistoricoSelecionado!,
                  paginaHistorico + 1,
                )
              "
            >
              Próxima
            </button>
          </footer>
        </template>
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

.linha-cliente {
  cursor: pointer;
  transition: background 0.15s ease;
}

.linha-cliente:hover {
  background: #f8fafc;
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


.modal-historico-cliente {
  max-width: 980px;
  max-height: calc(100vh - 40px);
  padding: 0;
  overflow: hidden;
}

.historico-cabecalho {
  margin-bottom: 0;
  padding: 24px 26px 20px;
  border-bottom: 1px solid #eaecf0;
}

.historico-eyebrow {
  display: inline-block;
  margin-bottom: 6px;
  color: #175cd3;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.historico-carregando {
  min-height: 300px;
  display: grid;
  place-items: center;
}

.historico-resumo {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  padding: 20px 26px 12px;
  background: #f8fafc;
}

.resumo-item {
  display: flex;
  min-height: 82px;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  padding: 14px 16px;
  border: 1px solid #e4e7ec;
  border-radius: 11px;
  background: white;
}

.resumo-item small {
  color: #667085;
  font-size: 12px;
  font-weight: 600;
}

.resumo-item strong {
  color: #101828;
  font-size: 24px;
  line-height: 1;
}

.resumo-item.pendente {
  border-left: 4px solid #f04438;
}

.resumo-item.parcial {
  border-left: 4px solid #f79009;
}

.resumo-item.concluida {
  border-left: 4px solid #12b76a;
}

.historico-subresumo {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 18px;
  padding: 0 26px 18px;
  border-bottom: 1px solid #eaecf0;
  background: #f8fafc;
  color: #667085;
  font-size: 12px;
}

.historico-subresumo strong {
  color: #344054;
}

.historico-lista {
  max-height: calc(100vh - 365px);
  overflow-y: auto;
  padding: 20px 26px 24px;
}

.historico-lista-cabecalho {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 14px;
}

.historico-lista-cabecalho h3 {
  margin: 0;
  font-size: 17px;
}

.historico-lista-cabecalho p {
  margin: 4px 0 0;
  color: #667085;
  font-size: 13px;
}

.historico-lista-cabecalho > strong {
  color: #667085;
  font-size: 13px;
}

.historico-vazio {
  padding: 50px 20px;
  border: 1px dashed #d0d5dd;
  border-radius: 12px;
  color: #667085;
  text-align: center;
}

.entrega-cliente-card {
  overflow: hidden;
  margin-bottom: 14px;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
  background: white;
  box-shadow: 0 1px 2px rgb(16 24 40 / 4%);
}

.entrega-cliente-topo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 15px 17px;
  border-bottom: 1px solid #eaecf0;
  background: #fcfcfd;
}

.entrega-cliente-topo > div {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pedido-numero {
  color: #101828;
  font-size: 15px;
}

.entrega-data {
  color: #667085;
  font-size: 12px;
}

.status-entrega {
  font-weight: 700;
}

.status-entrega.pendente {
  color: #b42318;
  background: #fee4e2;
}

.status-entrega.programada {
  color: #b54708;
  background: #fef0c7;
}

.status-entrega.em_saida {
  color: #175cd3;
  background: #dbeafe;
}

.status-entrega.parcial {
  color: #b54708;
  background: #fffaeb;
}

.status-entrega.concluida {
  color: #027a48;
  background: #d1fadf;
}

.status-entrega.nao_entregue {
  color: #b42318;
  background: #fef3f2;
}

.status-entrega.cancelada {
  color: #475467;
  background: #f2f4f7;
}

.entrega-local {
  display: grid;
  grid-template-columns: minmax(150px, 0.65fr) minmax(260px, 1.35fr);
  gap: 18px;
  padding: 14px 17px;
  border-bottom: 1px solid #eaecf0;
}

.entrega-local > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.entrega-local small,
.entrega-observacao small {
  color: #667085;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.entrega-local strong {
  color: #344054;
  font-size: 13px;
}

.entrega-local span {
  color: #667085;
  font-size: 12px;
}

.entrega-produtos {
  padding: 14px 17px;
}

.produtos-titulo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.produtos-titulo > strong {
  color: #344054;
  font-size: 12px;
  text-transform: uppercase;
}

.produtos-titulo > span {
  color: #667085;
  font-size: 11px;
}

.produto-linha {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 9px 0;
  border-top: 1px solid #f2f4f7;
}

.produto-linha:first-of-type {
  border-top: 0;
}

.produto-linha > div {
  min-width: 0;
}

.produto-linha > div > strong {
  display: block;
  color: #344054;
  font-size: 13px;
}

.produto-linha small {
  display: block;
  margin-top: 3px;
  color: #98a2b3;
  font-size: 11px;
}

.produto-quantidade {
  flex-shrink: 0;
  color: #101828;
  font-size: 13px;
}

.entrega-observacao {
  padding: 12px 17px 14px;
  border-top: 1px solid #eaecf0;
  background: #fffcf5;
}

.entrega-observacao p {
  margin: 5px 0 0;
  color: #475467;
  font-size: 12px;
  line-height: 1.45;
}

.historico-paginacao {
  background: white;
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

  .modal-historico-cliente {
    max-height: calc(100vh - 20px);
  }

  .historico-resumo {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 16px;
  }

  .historico-subresumo {
    padding: 0 16px 16px;
  }

  .historico-lista {
    max-height: calc(100vh - 410px);
    padding: 16px;
  }

  .entrega-local {
    grid-template-columns: 1fr;
  }

  .entrega-cliente-topo,
  .produto-linha {
    align-items: flex-start;
  }

  .entrega-cliente-topo > div {
    align-items: flex-start;
    flex-direction: column;
    gap: 4px;
  }
}
</style>