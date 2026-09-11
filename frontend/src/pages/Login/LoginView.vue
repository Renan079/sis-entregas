<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const erro = ref('')
const mostrarSenha = ref(false)

const auth = useAuthStore()
const router = useRouter()

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
    'Não foi possível entrar no sistema.'
  )
}

async function entrar() {
  erro.value = ''

  try {
    await auth.login(email.value, password.value)
    await router.push('/')
  } catch (error) {
    erro.value = mensagemErro(error)
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-brand" aria-label="Apresentação do sistema">
      <div class="brand-topo">
        <div class="brand-logo" aria-hidden="true">
          <span>SE</span>
        </div>

        <div>
          <strong>Sistema de Entregas</strong>
          <small>Gestão operacional</small>
        </div>
      </div>

      <div class="brand-conteudo">
        <span class="brand-eyebrow">Operação organizada</span>

        <h1>
          Controle suas entregas
          <span>do planejamento à conclusão.</span>
        </h1>

        <p>
          Acompanhe carradas, pendências, clientes e rotas em um único ambiente.
        </p>

        <div class="brand-recursos">
          <article>
            <span class="recurso-icone azul">✓</span>
            <div>
              <strong>Entregas centralizadas</strong>
              <small>Visualize o que está pendente, programado ou concluído.</small>
            </div>
          </article>

          <article>
            <span class="recurso-icone laranja">↗</span>
            <div>
              <strong>Operação mais rápida</strong>
              <small>Planeje carradas e acompanhe reentregas sem papelada.</small>
            </div>
          </article>
        </div>
      </div>

      <p class="brand-rodape">
        Gestão simples para uma operação que não pode parar.
      </p>
    </section>

    <section class="login-area">
      <div class="login-card">
        <header class="login-cabecalho">
          <span class="login-eyebrow">Acesso ao sistema</span>
          <h2>Bem-vindo de volta</h2>
          <p>Informe seus dados para continuar.</p>
        </header>

        <form class="login-form" @submit.prevent="entrar">
          <div class="campo">
            <label for="email">E-mail</label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              placeholder="seuemail@empresa.com"
              :disabled="auth.loading"
              required
            />
          </div>

          <div class="campo">
            <label for="password">Senha</label>

            <div class="senha-campo">
              <input
                id="password"
                v-model="password"
                :type="mostrarSenha ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="Digite sua senha"
                :disabled="auth.loading"
                required
              />

              <button
                class="mostrar-senha"
                type="button"
                :disabled="auth.loading"
                :aria-label="mostrarSenha ? 'Ocultar senha' : 'Mostrar senha'"
                @click="mostrarSenha = !mostrarSenha"
              >
                {{ mostrarSenha ? 'Ocultar' : 'Mostrar' }}
              </button>
            </div>
          </div>

          <div
            v-if="erro"
            class="mensagem-erro"
            role="alert"
          >
            <span aria-hidden="true">!</span>
            {{ erro }}
          </div>

          <button
            class="botao-entrar"
            type="submit"
            :disabled="auth.loading"
          >
            <span v-if="auth.loading" class="spinner" aria-hidden="true"></span>
            {{ auth.loading ? 'Entrando...' : 'Entrar no sistema' }}
          </button>
        </form>

        <footer class="login-rodape">
          <span>Ambiente seguro</span>
          <span class="separador">•</span>
          <span>Novos usuários são cadastrados por um administrador</span>
        </footer>
      </div>
    </section>
  </main>
</template>

<style scoped src="../../styles/pages/auth/login.css"></style>
