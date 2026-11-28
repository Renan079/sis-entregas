import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, fetchCsrfCookie } from '@/services/api'

interface User {
  id: number
  name: string
  email: string
  perfil: string
  ativo: boolean
}

interface NovoUsuario {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const creatingUser = ref(false)

  async function login(email: string, password: string) {
    loading.value = true

    try {
      await fetchCsrfCookie()

      await api.post('/login', {
        email: email.trim().toLowerCase(),
        password,
      })

      await fetchUser()
    } finally {
      loading.value = false
    }
  }

  async function createUser(data: NovoUsuario) {
    creatingUser.value = true

    try {
      await fetchCsrfCookie()

      const response = await api.post('/register', {
        ...data,
        name: data.name.trim(),
        email: data.email.trim().toLowerCase(),
      })

      return response.data.user as User
    } finally {
      creatingUser.value = false
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
    creatingUser,
    login,
    createUser,
    logout,
    fetchUser,
  }
})