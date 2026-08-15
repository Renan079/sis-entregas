<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { regiaoService } from '@/services/regiaoService'
import type { Regiao, RegiaoForm } from '@/types/Regiao'

const regioes = ref<Regiao[]>([])

const carregando = ref(false)
const salvando = ref(false)
const erro = ref('')

const modalAberto = ref(false)
const regiaoEditandoId = ref<number | null>(null)

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const formulario = reactive<RegiaoForm>({
  nome: '',
  cidade: '',
  observacao: '',
  ativo: true,
})

async function carregarRegioes(page = 1) {
  carregando.value = true
  erro.value = ''

  try {
    const response = await regiaoService.listar(page)

    regioes.value = response.data
    paginaAtual.value = response.meta.current_page
    ultimaPagina.value = response.meta.last_page
    total.value = response.meta.total
  } catch {
    erro.value = 'Não foi possível carregar as regiões.'
  } finally {
    carregando.value = false
  }
}

function limparFormulario() {
  formulario.nome = ''
  formulario.cidade = ''
  formulario.observacao = ''
  formulario.ativo = true

  regiaoEditandoId.value = null
}

function novaRegiao() {
  limparFormulario()
  modalAberto.value = true
}

function editarRegiao(regiao: Regiao) {
  regiaoEditandoId.value = regiao.id

  formulario.nome = regiao.nome
  formulario.cidade = regiao.cidade ?? ''
  formulario.observacao = regiao.observacao ?? ''
  formulario.ativo = regiao.ativo

  modalAberto.value = true
}

function fecharModal() {
  modalAberto.value = false
  limparFormulario()
}

async function salvarRegiao() {
  erro.value = ''

  if (!formulario.nome.trim()) {
    erro.value = 'Informe o nome da região.'
    return
  }

  salvando.value = true

  const dados: RegiaoForm = {
    nome: formulario.nome.trim(),
    cidade: formulario.cidade?.trim() || null,
    observacao: formulario.observacao?.trim() || null,
    ativo: formulario.ativo,
  }

  try {
    if (regiaoEditandoId.value) {
      await regiaoService.atualizar(
        regiaoEditandoId.value,
        dados,
      )
    } else {
      await regiaoService.criar(dados)
    }

    fecharModal()
    await carregarRegioes(paginaAtual.value)
  } catch {
    erro.value = 'Não foi possível salvar a região.'
  } finally {
    salvando.value = false
  }
}

async function desativarRegiao(regiao: Regiao) {
  const confirmou = window.confirm(
    `Deseja desativar a região "${regiao.nome}"?`,
  )

  if (!confirmou) {
    return
  }

  try {
    await regiaoService.desativar(regiao.id)
    await carregarRegioes(paginaAtual.value)
  } catch {
    erro.value = 'Não foi possível desativar a região.'
  }
}

onMounted(() => {
  carregarRegioes()
})
</script>

<template>
  <main class="pagina">
    <header class="cabecalho">
      <div>
        <h1>Regiões</h1>

        <p>
          Organize as áreas utilizadas no planejamento das entregas.
        </p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="novaRegiao"
      >
        + Nova região
      </button>
    </header>

    <p v-if="erro" class="erro">
      {{ erro }}
    </p>

    <section class="card">
      <div class="topo-tabela">
        <strong>
          {{ total }} região(ões)
        </strong>
      </div>

      <div
        v-if="carregando"
        class="estado"
      >
        Carregando regiões...
      </div>

      <div
        v-else-if="regioes.length === 0"
        class="estado"
      >
        Nenhuma região cadastrada.
      </div>

      <div
        v-else
        class="tabela-container"
      >
        <table>
          <thead>
            <tr>
              <th>Região</th>
              <th>Cidade</th>
              <th>Observação</th>
              <th>Status</th>
              <th class="acoes-coluna">Ações</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="regiao in regioes"
              :key="regiao.id"
            >
              <td>
                <strong>
                  {{ regiao.nome }}
                </strong>
              </td>

              <td>
                {{ regiao.cidade || '—' }}
              </td>

              <td class="observacao">
                {{ regiao.observacao || '—' }}
              </td>

              <td>
                <span
                  class="status"
                  :class="regiao.ativo ? 'ativo' : 'inativo'"
                >
                  {{ regiao.ativo ? 'Ativa' : 'Inativa' }}
                </span>
              </td>

              <td class="acoes">
                <button
                  class="acao"
                  type="button"
                  @click="editarRegiao(regiao)"
                >
                  Editar
                </button>

                <button
                  v-if="regiao.ativo"
                  class="acao perigo"
                  type="button"
                  @click="desativarRegiao(regiao)"
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
          @click="carregarRegioes(paginaAtual - 1)"
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
          @click="carregarRegioes(paginaAtual + 1)"
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
                regiaoEditandoId
                  ? 'Editar região'
                  : 'Nova região'
              }}
            </h2>

            <p>
              Informe os dados da região.
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

        <form @submit.prevent="salvarRegiao">
          <div class="campo">
            <label for="nome">
              Nome da região *
            </label>

            <input
              id="nome"
              v-model="formulario.nome"
              type="text"
              maxlength="120"
              required
              placeholder="Ex: Gameleira dos Machado"
            />
          </div>

          <div class="campo">
            <label for="cidade">
              Cidade
            </label>

            <input
              id="cidade"
              v-model="formulario.cidade"
              type="text"
              maxlength="120"
              placeholder="Ex: Canindé"
            />
          </div>

          <div class="campo">
            <label for="observacao">
              Observação
            </label>

            <textarea
              id="observacao"
              v-model="formulario.observacao"
              rows="4"
              placeholder="Informações adicionais sobre a região..."
            />
          </div>

          <label
            v-if="regiaoEditandoId"
            class="checkbox"
          >
            <input
              v-model="formulario.ativo"
              type="checkbox"
            />

            Região ativa
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
                  : 'Salvar região'
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
  font-family: Inter, system-ui, sans-serif;
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
  overflow: hidden;
  background: white;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgb(16 24 40 / 5%);
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

.erro {
  padding: 12px 16px;
  border: 1px solid #fecdca;
  border-radius: 8px;
  background: #fef3f2;
  color: #b42318;
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

.observacao {
  max-width: 350px;
  color: #667085;
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

.acoes-coluna,
.acoes {
  text-align: right;
}

.acoes {
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
  max-width: 600px;
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

.campo {
  margin-bottom: 18px;
}

.campo label {
  display: block;
  margin-bottom: 7px;
  font-size: 14px;
  font-weight: 600;
}

input,
textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: white;
  font: inherit;
  outline: none;
}

input {
  min-height: 42px;
}

textarea {
  resize: vertical;
}

input:focus,
textarea:focus {
  border-color: #667085;
  box-shadow: 0 0 0 3px rgb(102 112 133 / 10%);
}

.checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
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

  .cabecalho {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>