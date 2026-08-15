<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function sair() {
  await auth.logout()

  await router.push('/login')
}
</script>

<template>
  <main>
    <h1>Dashboard</h1>

    <p v-if="auth.user">
      Bem-vindo, {{ auth.user.name }}.
    </p>

    <button @click="sair">
      Sair
    </button>
  </main>
</template>