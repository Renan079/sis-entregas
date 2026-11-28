import axios from 'axios'

const apiBaseUrl = (import.meta.env.VITE_API_URL || '').replace(/\/+$/, '')

// Instância para endpoints da API (ex.: /api/login, /api/saidas)
export const api = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

// Instância na raiz do domínio para o Sanctum (/sanctum/csrf-cookie)
const csrfClient = axios.create({
  baseURL: '',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

export async function fetchCsrfCookie(): Promise<void> {
  await csrfClient.get('/sanctum/csrf-cookie')
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    return Promise.reject(error)
  },
)