<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const erro = ref('')
const sucesso = ref('')
const mostrarSenha = ref(false)
const mostrarConfirmacao = ref(false)

const formulario = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

function limparFormulario() {
  formulario.name = ''
  formulario.email = ''
  formulario.password = ''
  formulario.password_confirmation = ''
}

function mensagemErro(error: unknown) {
  const resposta = error as {
    response?: {
      data?: {
        message?: string
        errors?: Record<string, string[]>
      }
    }
  }

  const primeiraValidacao = Object.values(
    resposta.response?.data?.errors ?? {},
  )
    .flat()
    .find(Boolean)

  return (
    primeiraValidacao ||
    resposta.response?.data?.message ||
    'Não foi possível cadastrar o usuário.'
  )
}

async function cadastrar() {
  erro.value = ''
  sucesso.value = ''

  if (formulario.password !== formulario.password_confirmation) {
    erro.value = 'A confirmação da senha não corresponde à senha informada.'
    return
  }

  try {
    const usuario = await auth.createUser(formulario)

    sucesso.value = `Usuário ${usuario.name} cadastrado com sucesso como OPERADOR.`
    limparFormulario()
  } catch (error) {
    erro.value = mensagemErro(error)
  }
}
</script>

<template>
  <main class="usuario-novo-page">
    <header class="pagina-cabecalho">
      <div>
        <span class="eyebrow">Administração</span>
        <h1>Cadastrar usuário</h1>
        <p>Crie um novo acesso para a equipe operacional.</p>
      </div>

      <button
        class="botao secundario"
        type="button"
        @click="router.push('/usuarios')"
      >
        Voltar aos usuários
      </button>
    </header>

    <section class="cadastro-grid">
      <aside class="informacoes-card">
        <span class="info-icone">+</span>
        <h2>Novo acesso</h2>
        <p>
          O usuário será criado como <strong>OPERADOR</strong> e ficará ativo imediatamente.
        </p>

        <div class="info-item">
          <span>✓</span>
          <div>
            <strong>Perfil protegido</strong>
            <small>O formulário não permite criar administradores.</small>
          </div>
        </div>

        <div class="info-item">
          <span>✓</span>
          <div>
            <strong>Acesso controlado</strong>
            <small>Somente um administrador autenticado pode cadastrar usuários.</small>
          </div>
        </div>
      </aside>

      <section class="form-card">
        <div class="form-destaque"></div>

        <header>
          <h2>Dados do usuário</h2>
          <p>Preencha os dados usados para entrar no sistema.</p>
        </header>

        <form @submit.prevent="cadastrar">
          <div class="campo campo-completo">
            <label for="name">Nome *</label>
            <input
              id="name"
              v-model="formulario.name"
              type="text"
              autocomplete="name"
              maxlength="255"
              placeholder="Nome completo"
              :disabled="auth.creatingUser"
              required
            />
          </div>

          <div class="campo campo-completo">
            <label for="register-email">E-mail *</label>
            <input
              id="register-email"
              v-model="formulario.email"
              type="email"
              autocomplete="email"
              maxlength="255"
              placeholder="usuario@empresa.com"
              :disabled="auth.creatingUser"
              required
            />
          </div>

          <div class="campos-senha">
            <div class="campo">
              <label for="register-password">Senha *</label>
              <div class="senha-campo">
                <input
                  id="register-password"
                  v-model="formulario.password"
                  :type="mostrarSenha ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Mínimo exigido pelo sistema"
                  :disabled="auth.creatingUser"
                  required
                />
                <button
                  type="button"
                  class="mostrar-senha"
                  :disabled="auth.creatingUser"
                  @click="mostrarSenha = !mostrarSenha"
                >
                  {{ mostrarSenha ? 'Ocultar' : 'Mostrar' }}
                </button>
              </div>
            </div>

            <div class="campo">
              <label for="password-confirmation">Confirmar senha *</label>
              <div class="senha-campo">
                <input
                  id="password-confirmation"
                  v-model="formulario.password_confirmation"
                  :type="mostrarConfirmacao ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Digite a senha novamente"
                  :disabled="auth.creatingUser"
                  required
                />
                <button
                  type="button"
                  class="mostrar-senha"
                  :disabled="auth.creatingUser"
                  @click="mostrarConfirmacao = !mostrarConfirmacao"
                >
                  {{ mostrarConfirmacao ? 'Ocultar' : 'Mostrar' }}
                </button>
              </div>
            </div>
          </div>

          <p v-if="erro" class="mensagem erro" role="alert">
            {{ erro }}
          </p>

          <p v-if="sucesso" class="mensagem sucesso" role="status">
            {{ sucesso }}
          </p>

          <footer class="form-acoes">
            <button
              class="botao secundario"
              type="button"
              :disabled="auth.creatingUser"
              @click="limparFormulario"
            >
              Limpar
            </button>

            <button
              class="botao primario"
              type="submit"
              :disabled="auth.creatingUser"
            >
              {{ auth.creatingUser ? 'Cadastrando...' : 'Cadastrar usuário' }}
            </button>
          </footer>
        </form>
      </section>
    </section>
  </main>
</template>

<style scoped src="../../styles/pages/usuario/usuario-novo.css"></style>
