import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import AppLayout from '@/layouts/AppLayout.vue'

import LoginView from '@/pages/Login/LoginView.vue'
import DashboardView from '@/pages/Dashboard/DashboardView.vue'
import ClientesView from '@/pages/Clientes/ClientesView.vue'
import RegioesView from '@/pages/Regioes/RegioesView.vue'
import EntregasView from '@/pages/Entregas/EntregasView.vue'
import SaidasView from '@/pages/Saidas/SaidasView.vue'
import PendenciasView from '@/pages/Pendencias/PendenciasView.vue'
import RelatoriosView from '@/pages/Relatorios/RelatoriosView.vue'
import SaidaDetalheView from '@/pages/Saidas/SaidaDetalheView.vue'
import SaidaNovaView from '@/pages/Saidas/SaidaNovaView.vue'
import SaidaHistoricoView from '@/pages/Saidas/SaidaHistoricoView.vue'
import EntregaHistoricoView from '@/pages/Entregas/EntregaHistoricoView.vue'
import EntregaFormularioView from '@/pages/Entregas/EntregaFormularioView.vue'
import ClienteHistoricoView from '@/pages/Clientes/ClienteHistoricoView.vue'
import UsuariosView from '@/pages/Usuarios/UsuariosView.vue'
import UsuarioNovoView from '@/pages/Usuarios/UsuarioNovoView.vue'

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
          path: 'clientes/:id/historico',
          name: 'cliente-historico',
          component: ClienteHistoricoView,
        },

        {
          path: 'regioes',
          name: 'regioes',
          component: RegioesView,
        },

        {
          path: 'usuarios',
          name: 'usuarios',
          component: UsuariosView,
          meta: {
            requiresAdmin: true,
          },
        },

        {
          path: 'usuarios/novo',
          name: 'usuario-novo',
          component: UsuarioNovoView,
          meta: {
            requiresAdmin: true,
          },
        },

        {
          path: 'entregas',
          name: 'entregas',
          component: EntregasView,
        },
        {
          path: 'entregas/nova',
          name: 'entrega-nova',
          component: EntregaFormularioView,
        },
        {
          path: 'entregas/:id/editar',
          name: 'entrega-editar',
          component: EntregaFormularioView,
        },
        {
          path: 'entregas/:id/historico',
          name: 'entrega-historico',
          component: EntregaHistoricoView,
        },

        {
          path: 'saidas',
          name: 'saidas',
          component: SaidasView,
        },
        {
          path: 'saidas/nova',
          name: 'saida-nova',
          component: SaidaNovaView,
        },
        {
          path: 'saidas/:id',
          name: 'saida-detalhe',
          component: SaidaDetalheView,
        },
        {
          path: 'saidas/:id/historico',
          name: 'saida-historico',
          component: SaidaHistoricoView,
        },

        {
          path: 'pendencias',
          name: 'pendencias',
          component: PendenciasView,
        },

        {
          path: 'relatorios',
          name: 'relatorios',
          component: RelatoriosView,
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

  if (to.meta.requiresAdmin && auth.user?.perfil !== 'ADMIN') {
    return {
      name: 'dashboard',
    }
  }

  if (to.meta.guest && auth.user) {
    return {
      name: 'dashboard',
    }
  }
})

export default router
