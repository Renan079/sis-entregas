<script setup lang="ts">
import { reactive, ref } from 'vue'
import type { Usuario, UsuarioSenha } from '@/types/Usuario'

const props = defineProps<{
  usuario: Usuario
  salvando: boolean
  erro: string
}>()

const emit = defineEmits<{
  fechar: []
  salvar: [dados: UsuarioSenha]
}>()

const mostrarSenha = ref(false)
const mostrarConfirmacao = ref(false)
const validacaoLocal = ref('')

const formulario = reactive<UsuarioSenha>({
  password: '',
  password_confirmation: '',
})

function salvar() {
  validacaoLocal.value = ''

  if (formulario.password !== formulario.password_confirmation) {
    validacaoLocal.value = 'A confirmação da senha não corresponde à senha informada.'
    return
  }

  emit('salvar', {
    password: formulario.password,
    password_confirmation: formulario.password_confirmation,
  })
}
</script>

<template>
  <div class="modal-fundo" @click.self="emit('fechar')">
    <section class="modal-senha">
      <header class="modal-cabecalho">
        <div>
          <span class="eyebrow">Segurança</span>
          <h2>Redefinir senha</h2>
          <p>{{ props.usuario.name }}</p>
        </div>

        <button
          class="fechar"
          type="button"
          :disabled="salvando"
          @click="emit('fechar')"
        >
          ×
        </button>
      </header>

      <div class="aviso">
        A nova senha substituirá imediatamente a senha atual deste usuário.
      </div>

      <form @submit.prevent="salvar">
        <div class="campo">
          <label for="nova-senha">Nova senha *</label>
          <div class="senha-campo">
            <input
              id="nova-senha"
              v-model="formulario.password"
              :type="mostrarSenha ? 'text' : 'password'"
              autocomplete="new-password"
              :disabled="salvando"
              required
            />
            <button
              class="mostrar-senha"
              type="button"
              :disabled="salvando"
              @click="mostrarSenha = !mostrarSenha"
            >
              {{ mostrarSenha ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>
        </div>

        <div class="campo">
          <label for="confirmar-nova-senha">Confirmar nova senha *</label>
          <div class="senha-campo">
            <input
              id="confirmar-nova-senha"
              v-model="formulario.password_confirmation"
              :type="mostrarConfirmacao ? 'text' : 'password'"
              autocomplete="new-password"
              :disabled="salvando"
              required
            />
            <button
              class="mostrar-senha"
              type="button"
              :disabled="salvando"
              @click="mostrarConfirmacao = !mostrarConfirmacao"
            >
              {{ mostrarConfirmacao ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>
        </div>

        <p v-if="validacaoLocal || erro" class="mensagem erro" role="alert">
          {{ validacaoLocal || erro }}
        </p>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            :disabled="salvando"
            @click="emit('fechar')"
          >
            Cancelar
          </button>

          <button
            class="botao primario"
            type="submit"
            :disabled="salvando"
          >
            {{ salvando ? 'Redefinindo...' : 'Redefinir senha' }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped src="../../styles/pages/usuario/usuario-senha-modal.css"></style>
