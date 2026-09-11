<script setup lang="ts">
import { reactive, ref, watch } from 'vue'

import { regiaoService } from '@/services/regiaoService'
import type { Regiao, RegiaoForm } from '@/types/Regiao'

const props = defineProps<{
  aberto: boolean
  regiao: Regiao | null
}>()

const emit = defineEmits<{
  fechar: []
  salvo: []
}>()

const salvando = ref(false)
const erro = ref('')

const formulario = reactive<RegiaoForm>({
  nome: '',
  cidade: '',
  observacao: '',
  ativo: true,
})

function preencherFormulario() {
  formulario.nome = props.regiao?.nome ?? ''
  formulario.cidade = props.regiao?.cidade ?? ''
  formulario.observacao = props.regiao?.observacao ?? ''
  formulario.ativo = props.regiao?.ativo ?? true
  erro.value = ''
}

function fechar() {
  if (salvando.value) {
    return
  }

  emit('fechar')
}

async function salvar() {
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
    if (props.regiao) {
      await regiaoService.atualizar(props.regiao.id, dados)
    } else {
      await regiaoService.criar(dados)
    }

    emit('salvo')
  } catch {
    erro.value = 'Não foi possível salvar a região.'
  } finally {
    salvando.value = false
  }
}

watch(
  () => props.aberto,
  (aberto) => {
    if (aberto) {
      preencherFormulario()
    }
  },
)
</script>

<template>
  <div
    v-if="aberto"
    class="modal-fundo"
    @click.self="fechar"
  >
    <section class="modal-regiao" role="dialog" aria-modal="true">
      <header class="modal-cabecalho">
        <div>
          <span class="eyebrow">
            {{ regiao ? 'Editar cadastro' : 'Novo cadastro' }}
          </span>

          <h2>
            {{ regiao ? 'Editar região' : 'Nova região' }}
          </h2>

          <p>
            Informe os dados usados na organização das entregas.
          </p>
        </div>

        <button
          class="fechar"
          type="button"
          :disabled="salvando"
          aria-label="Fechar"
          @click="fechar"
        >
          ×
        </button>
      </header>

      <form class="formulario-regiao" @submit.prevent="salvar">
        <p v-if="erro" class="mensagem erro">
          {{ erro }}
        </p>

        <div class="campo">
          <label for="regiao-nome">Nome da região *</label>
          <input
            id="regiao-nome"
            v-model="formulario.nome"
            type="text"
            maxlength="120"
            required
            autocomplete="off"
            placeholder="Ex.: Gameleira dos Machado"
          />
        </div>

        <div class="campo">
          <label for="regiao-cidade">Cidade</label>
          <input
            id="regiao-cidade"
            v-model="formulario.cidade"
            type="text"
            maxlength="120"
            autocomplete="off"
            placeholder="Ex.: Canindé"
          />
        </div>

        <div class="campo">
          <label for="regiao-observacao">Observação</label>
          <textarea
            id="regiao-observacao"
            v-model="formulario.observacao"
            rows="4"
            placeholder="Informações adicionais sobre a região..."
          ></textarea>
        </div>

        <label
          v-if="regiao"
          class="checkbox"
        >
          <input
            v-model="formulario.ativo"
            type="checkbox"
          />

          <span>
            <strong>Região ativa</strong>
            <small>Regiões inativas deixam de ficar disponíveis para novos planejamentos.</small>
          </span>
        </label>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            :disabled="salvando"
            @click="fechar"
          >
            Cancelar
          </button>

          <button
            class="botao primario"
            type="submit"
            :disabled="salvando"
          >
            {{ salvando ? 'Salvando...' : 'Salvar região' }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped src="../../styles/pages/regiao/regiao-formulario-modal.css"></style>
