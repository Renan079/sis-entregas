<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const erro = ref('')

const auth = useAuthStore()
const router = useRouter()

async function entrar() {
  erro.value = ''

  try {
    await auth.login(email.value, password.value)

    await router.push('/')
  } catch {
    erro.value = 'E-mail ou senha inválidos.'
  }
}
</script>

<template>
  <main>
    <h1>Entrar</h1>

    <form @submit.prevent="entrar">
      <div>
        <label>E-mail</label>
        <input
          v-model="email"
          type="email"
          required
        />
      </div>

      <div>
        <label>Senha</label>
        <input
          v-model="password"
          type="password"
          required
        />
      </div>

      <p v-if="erro">
        {{ erro }}
      </p>

      <button
        type="submit"
        :disabled="auth.loading"
      >
        {{ auth.loading ? 'Entrando...' : 'Entrar' }}
      </button>
    </form>
  </main>
</template>