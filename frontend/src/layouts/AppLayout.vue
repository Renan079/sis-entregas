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
  <div class="app-layout">
    <aside class="sidebar">
      <div class="logo">
        <div class="logo-icon">SE</div>

        <div>
          <strong>Sistema de Entregas</strong>
          <small>Gestão operacional</small>
        </div>
      </div>

      <nav class="menu">
        <RouterLink to="/">
          <span>▦</span>
          Dashboard
        </RouterLink>

        <RouterLink to="/clientes">
          <span>♙</span>
          Clientes
        </RouterLink>

        <RouterLink to="/regioes">
          <span>⌖</span>
          Regiões
        </RouterLink>

        <RouterLink to="/entregas">
          <span>▤</span>
          Entregas
        </RouterLink>

        <RouterLink to="/saidas">
          <span>⇢</span>
          Saídas
        </RouterLink>

        <RouterLink to="/pendencias">
          <span>!</span>
          Pendências
        </RouterLink>

        <RouterLink to="/relatorios">
          <span>▦</span>
          Relatórios
        </RouterLink>
      </nav>

      <div class="usuario">
        <div class="avatar">
          {{ auth.user?.name?.charAt(0).toUpperCase() }}
        </div>

        <div class="usuario-info">
          <strong>{{ auth.user?.name }}</strong>
          <small>{{ auth.user?.perfil }}</small>
        </div>

        <button
          type="button"
          class="logout"
          title="Sair"
          @click="sair"
        >
          ↪
        </button>
      </div>
    </aside>

    <section class="conteudo">
      <RouterView />
    </section>
  </div>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.app-layout {
  display: flex;
  min-height: 100vh;
  background: #f5f7fa;
}

.sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  display: flex;
  width: 250px;
  flex-direction: column;
  padding: 24px 16px;
  background: #172033;
  color: white;
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 8px 24px;
  border-bottom: 1px solid rgb(255 255 255 / 10%);
}

.logo-icon {
  display: flex;
  width: 42px;
  height: 42px;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: white;
  color: #172033;
  font-weight: 800;
}

.logo strong {
  display: block;
  font-size: 14px;
}

.logo small {
  display: block;
  margin-top: 3px;
  color: #98a2b3;
  font-size: 11px;
}

.menu {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 6px;
  padding-top: 24px;
}

.menu a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  border-radius: 8px;
  color: #d0d5dd;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
}

.menu a:hover {
  background: rgb(255 255 255 / 6%);
  color: white;
}

.menu a.router-link-exact-active {
  background: white;
  color: #172033;
  font-weight: 600;
}

.menu span {
  width: 20px;
  text-align: center;
}

.usuario {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-top: 18px;
  border-top: 1px solid rgb(255 255 255 / 10%);
}

.avatar {
  display: flex;
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #344054;
  font-weight: 700;
}

.usuario-info {
  flex: 1;
  overflow: hidden;
}

.usuario-info strong,
.usuario-info small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.usuario-info strong {
  font-size: 13px;
}

.usuario-info small {
  margin-top: 3px;
  color: #98a2b3;
  font-size: 11px;
}

.logout {
  border: 0;
  background: transparent;
  color: #98a2b3;
  font-size: 18px;
  cursor: pointer;
}

.logout:hover {
  color: white;
}

.conteudo {
  width: calc(100% - 250px);
  min-height: 100vh;
  margin-left: 250px;
}

@media (max-width: 800px) {
  .sidebar {
    width: 78px;
  }

  .logo > div:last-child,
  .menu a {
    font-size: 0;
  }

  .menu a span {
    font-size: 20px;
  }

  .usuario-info {
    display: none;
  }

  .conteudo {
    width: calc(100% - 78px);
    margin-left: 78px;
  }
}
</style>