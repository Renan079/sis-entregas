<script setup lang="ts">
import { reactive, ref, watch } from 'vue'

import { clienteService } from '@/services/clienteService'
import type { Cliente, ClienteForm } from '@/types/Cliente'

const props = defineProps<{
  aberto: boolean
  cliente: Cliente | null
}>()

const emit = defineEmits<{
  fechar: []
  salvo: []
}>()

const salvando = ref(false)
const erro = ref('')

const formulario = reactive<ClienteForm>({
  codigo_externo: '',
  nome: '',
  apelido: '',
  telefone: '',
  ativo: true,
})

function preencherFormulario() {
  formulario.codigo_externo = props.cliente?.codigo_externo ?? ''
  formulario.nome = props.cliente?.nome ?? ''
  formulario.apelido = props.cliente?.apelido ?? ''
  formulario.telefone = props.cliente?.telefone ?? ''
  formulario.ativo = props.cliente?.ativo ?? true
  erro.value = ''
}

watch(
  () => [props.aberto, props.cliente] as const,
  ([aberto]) => {
    if (aberto) {
      preencherFormulario()
    }
  },
  { immediate: true },
)

function fechar() {
  if (salvando.value) {
    return
  }

  emit('fechar')
}

async function salvarCliente() {
  erro.value = ''

  if (!formulario.nome.trim()) {
    erro.value = 'Informe o nome do cliente.'
    return
  }

  const dados: ClienteForm = {
    codigo_externo: formulario.codigo_externo?.trim() || null,
    nome: formulario.nome.trim(),
    apelido: formulario.apelido?.trim() || null,
    telefone: formulario.telefone?.trim() || null,
    ativo: formulario.ativo,
  }

  salvando.value = true

  try {
    if (props.cliente) {
      await clienteService.atualizar(props.cliente.id, dados)
    } else {
      await clienteService.criar(dados)
    }

    emit('salvo')
  } catch {
    erro.value = 'Não foi possível salvar o cliente.'
  } finally {
    salvando.value = false
  }
}
</script>

<template>
  <div
    v-if="aberto"
    class="modal-fundo"
    @click.self="fechar"
  >
    <section class="modal cliente-formulario-modal">
      <header class="modal-cabecalho">
        <div>
          <span class="eyebrow">
            {{ cliente ? 'Atualização cadastral' : 'Novo cadastro' }}
          </span>
          <h2>{{ cliente ? 'Editar cliente' : 'Novo cliente' }}</h2>
          <p>Preencha os dados principais do cliente.</p>
        </div>

        <button
          class="fechar"
          type="button"
          :disabled="salvando"
          @click="fechar"
        >
          ×
        </button>
      </header>

      <form @submit.prevent="salvarCliente">
        <p v-if="erro" class="mensagem erro">
          {{ erro }}
        </p>

        <div class="grid-form">
          <div class="campo campo-destaque">
            <label for="nome">Nome *</label>
            <input
              id="nome"
              v-model="formulario.nome"
              type="text"
              maxlength="180"
              required
            />
          </div>

          <div class="campo">
            <label for="codigo">Código externo</label>
            <input
              id="codigo"
              v-model="formulario.codigo_externo"
              type="text"
              maxlength="50"
            />
          </div>

          <div class="campo">
            <label for="apelido">Apelido</label>
            <input
              id="apelido"
              v-model="formulario.apelido"
              type="text"
              maxlength="150"
            />
          </div>

          <div class="campo">
            <label for="telefone">Telefone</label>
            <input
              id="telefone"
              v-model="formulario.telefone"
              type="text"
              maxlength="30"
            />
          </div>
        </div>

        <label v-if="cliente" class="checkbox">
          <input v-model="formulario.ativo" type="checkbox" />
          <span>
            <strong>Cliente ativo</strong>
            <small>Permite usar este cadastro em novas operações.</small>
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
            {{ salvando ? 'Salvando...' : 'Salvar cliente' }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped src="../../styles/pages/cliente/cliente-formulario-modal.css"></style>
