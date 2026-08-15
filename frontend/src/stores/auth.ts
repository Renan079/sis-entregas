import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/services/api'

interface User {
  id: number
  name: string
  email: string
  perfil: string
  ativo: boolean
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  async function login(email: string, password: string) {
    loading.value = true

    try {
      // 1. Obtém o cookie CSRF
      await api.get('/sanctum/csrf-cookie')

      // 2. Faz login
      await api.post('/login', {
        email,
        password,
      })

      // 3. Busca usuário autenticado
      await fetchUser()
    } finally {
      loading.value = false
    }
  }

  async function fetchUser() {
    try {
      const response = await api.get('/api/user')

      user.value = response.data

      return true
    } catch {
      user.value = null

      return false
    }
  }

  async function logout() {
    await api.post('/logout')

    user.value = null
  }

  return {
    user,
    loading,
    login,
    logout,
    fetchUser,
  }
})