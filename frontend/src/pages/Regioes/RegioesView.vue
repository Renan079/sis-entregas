<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { regiaoService } from '@/services/regiaoService'
import RegiaoFormularioModal from '@/components/Regioes/RegiaoFormularioModal.vue'

import type { Regiao } from '@/types/Regiao'

const regioes = ref<Regiao[]>([])
const carregando = ref(false)
const erro = ref('')

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

const modalFormulario = ref(false)
const regiaoSelecionada = ref<Regiao | null>(null)

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

function novaRegiao() {
  regiaoSelecionada.value = null
  modalFormulario.value = true
}

function editarRegiao(regiao: Regiao) {
  regiaoSelecionada.value = regiao
  modalFormulario.value = true
}

function fecharFormulario() {
  modalFormulario.value = false
  regiaoSelecionada.value = null
}

async function regiaoSalva() {
  fecharFormulario()
  await carregarRegioes(paginaAtual.value)
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
  <main class="pagina-regioes">
    <header class="cabecalho-regioes">
      <div>
        <span class="eyebrow">Organização logística</span>
        <h1>Regiões</h1>
        <p>
          Organize as áreas usadas no planejamento e agrupamento das entregas.
        </p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="novaRegiao"
      >
        <span class="botao-icone">+</span>
        Nova região
      </button>
    </header>

    <p v-if="erro" class="mensagem erro">
      {{ erro }}
    </p>

    <section class="card tabela-card">
      <div class="topo-tabela">
        <div>
          <strong>{{ total }} região(ões)</strong>
          <span>
            Cadastros usados para organizar rotas e entregas por área.
          </span>
        </div>
      </div>

      <div v-if="carregando" class="estado">
        Carregando regiões...
      </div>

      <div v-else-if="regioes.length === 0" class="estado estado-vazio">
        <span class="estado-icone">⌖</span>
        <strong>Nenhuma região cadastrada.</strong>
        <small>Cadastre a primeira região para começar a organizar as entregas.</small>
      </div>

      <div v-else class="tabela-container">
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
                <strong class="nome-regiao">{{ regiao.nome }}</strong>
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
                  class="acao editar"
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

        <span>Página {{ paginaAtual }} de {{ ultimaPagina }}</span>

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

    <RegiaoFormularioModal
      :aberto="modalFormulario"
      :regiao="regiaoSelecionada"
      @fechar="fecharFormulario"
      @salvo="regiaoSalva"
    />
  </main>
</template>

<style scoped src="../../styles/pages/regiao/regioes.css"></style>
