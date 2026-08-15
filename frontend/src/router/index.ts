import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import AppLayout from '@/layouts/AppLayout.vue'

import LoginView from '@/pages/Login/LoginView.vue'
import DashboardView from '@/pages/Dashboard/DashboardView.vue'
import ClientesView from '@/pages/Clientes/ClientesView.vue'
import RegioesView from '@/pages/Regioes/RegioesView.vue'
import EntregasView from '@/pages/Entregas/EntregasView.vue'
import SaidasView from '@/pages/Saidas/SaidasView.vue'

const router = createRouter({
  history: createWebHistory(),

  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: {
        guest: true,
      },
    },

    {
      path: '/',
      component: AppLayout,
      meta: {
        requiresAuth: true,
      },

      children: [
        {
          path: '',
          name: 'dashboard',
          component: DashboardView,
        },

        {
          path: 'clientes',
          name: 'clientes',
          component: ClientesView,
        },
        {
          path: 'regioes',
          name: 'regioes',
          component: RegioesView,
        },
        {
          path: 'entregas',
          name: 'entregas',
          component: EntregasView,
        },
        {
          path: 'saidas',
          name: 'saidas',
          component: SaidasView,
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.user) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.user) {
    return {
      name: 'login',
    }
  }

  if (to.meta.guest && auth.user) {
    return {
      name: 'dashboard',
    }
  }
})

export default router