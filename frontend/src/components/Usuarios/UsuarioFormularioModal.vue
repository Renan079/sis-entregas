<script setup lang="ts">
import { reactive, watch } from 'vue'
import type {
  PerfilUsuario,
  Usuario,
  UsuarioAtualizacao,
} from '@/types/Usuario'

const props = defineProps<{
  usuario: Usuario
  salvando: boolean
  erro: string
  usuarioAtualId: number | null
}>()

const emit = defineEmits<{
  fechar: []
  salvar: [dados: UsuarioAtualizacao]
}>()

const formulario = reactive<UsuarioAtualizacao>({
  name: '',
  email: '',
  perfil: 'OPERADOR',
  ativo: true,
})

watch(
  () => props.usuario,
  (usuario) => {
    formulario.name = usuario.name
    formulario.email = usuario.email
    formulario.perfil = usuario.perfil
    formulario.ativo = usuario.ativo
  },
  { immediate: true },
)

function salvar() {
  emit('salvar', {
    name: formulario.name.trim(),
    email: formulario.email.trim().toLowerCase(),
    perfil: formulario.perfil as PerfilUsuario,
    ativo: formulario.ativo,
  })
}
</script>

<template>
  <div class="modal-fundo" @click.self="emit('fechar')">
    <section class="modal-usuario">
      <header class="modal-cabecalho">
        <div>
          <span class="eyebrow">Administração</span>
          <h2>Editar usuário</h2>
          <p>Atualize os dados e as permissões de acesso.</p>
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

      <form @submit.prevent="salvar">
        <div class="campo">
          <label for="usuario-nome">Nome *</label>
          <input
            id="usuario-nome"
            v-model="formulario.name"
            type="text"
            maxlength="255"
            :disabled="salvando"
            required
          />
        </div>

        <div class="campo">
          <label for="usuario-email">E-mail *</label>
          <input
            id="usuario-email"
            v-model="formulario.email"
            type="email"
            maxlength="255"
            :disabled="salvando"
            required
          />
        </div>

        <div class="campos-duplos">
          <div class="campo">
            <label for="usuario-perfil">Perfil *</label>
            <select
              id="usuario-perfil"
              v-model="formulario.perfil"
              :disabled="salvando || usuario.id === usuarioAtualId"
            >
              <option value="OPERADOR">Operador</option>
              <option value="ADMIN">Administrador</option>
            </select>
            <small v-if="usuario.id === usuarioAtualId" class="ajuda">
              Seu próprio perfil de administrador não pode ser removido.
            </small>
          </div>

          <div class="campo status-campo">
            <label>Status</label>
            <label class="switch-linha">
              <input
                v-model="formulario.ativo"
                type="checkbox"
                :disabled="salvando || usuario.id === usuarioAtualId"
              />
              <span>{{ formulario.ativo ? 'Usuário ativo' : 'Usuário inativo' }}</span>
            </label>
            <small v-if="usuario.id === usuarioAtualId" class="ajuda">
              Você não pode desativar o próprio usuário.
            </small>
          </div>
        </div>

        <p v-if="erro" class="mensagem erro" role="alert">
          {{ erro }}
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
            {{ salvando ? 'Salvando...' : 'Salvar alterações' }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped src="../../styles/pages/usuario/usuario-formulario-modal.css"></style>
