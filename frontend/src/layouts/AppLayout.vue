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
        <RouterLink
          to="/"
          active-class=""
          exact-active-class="router-link-active"
        >
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

        <RouterLink
          v-if="auth.user?.perfil === 'ADMIN'"
          to="/usuarios"
        >
          <span>+</span>
          Usuários
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
  background: var(--se-bg);
}

.sidebar {
  position: fixed;
  z-index: 50;
  inset: 0 auto 0 0;
  display: flex;
  width: 264px;
  flex-direction: column;
  padding: 22px 14px 18px;
  background:
    radial-gradient(circle at 20% 0%, rgb(21 94 239 / 26%), transparent 34%),
    linear-gradient(180deg, var(--se-navy-900) 0%, var(--se-navy-950) 100%);
  color: white;
  box-shadow: 14px 0 36px rgb(7 22 47 / 12%);
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 66px;
  padding: 4px 8px 20px;
  border-bottom: 1px solid rgb(255 255 255 / 10%);
}

.logo-icon {
  position: relative;
  display: flex;
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  align-items: center;
  justify-content: center;
  border: 1px solid rgb(255 255 255 / 16%);
  border-radius: 13px;
  background: linear-gradient(145deg, var(--se-blue-600), #3b82f6);
  color: white;
  box-shadow: 0 8px 22px rgb(21 94 239 / 30%);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.logo-icon::after {
  position: absolute;
  right: -3px;
  bottom: -3px;
  width: 11px;
  height: 11px;
  border: 2px solid var(--se-navy-900);
  border-radius: 50%;
  background: var(--se-orange-500);
  content: '';
}

.logo strong {
  display: block;
  color: #fff;
  font-size: 14px;
  line-height: 1.25;
}

.logo small {
  display: block;
  margin-top: 4px;
  color: #9fb0c8;
  font-size: 11px;
}

.menu {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 5px;
  padding-top: 22px;
}

.menu::before {
  padding: 0 12px 8px;
  color: #7186a5;
  content: 'NAVEGAÇÃO';
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.14em;
}

.menu a {
  position: relative;
  display: flex;
  min-height: 46px;
  align-items: center;
  gap: 11px;
  padding: 7px 10px;
  border: 1px solid transparent;
  border-radius: 11px;
  color: #c7d2e2;
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  transition:
    background var(--se-transition),
    color var(--se-transition),
    border-color var(--se-transition),
    transform var(--se-transition);
}

.menu a:hover {
  border-color: rgb(255 255 255 / 7%);
  background: rgb(255 255 255 / 6%);
  color: white;
  transform: translateX(2px);
}

.menu a.router-link-active {
  border-color: rgb(96 165 250 / 18%);
  background: linear-gradient(90deg, rgb(21 94 239 / 28%), rgb(21 94 239 / 10%));
  color: white;
  box-shadow: inset 3px 0 0 var(--se-orange-500);
}

.menu span {
  display: inline-flex;
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: rgb(255 255 255 / 5%);
  color: #9fb0c8;
  font-size: 16px;
  text-align: center;
  transition: inherit;
}

.menu a:hover span,
.menu a.router-link-active span {
  background: rgb(21 94 239 / 35%);
  color: #fff;
}

.usuario {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 14px;
  padding: 17px 8px 0;
  border-top: 1px solid rgb(255 255 255 / 10%);
}

.avatar {
  display: flex;
  width: 39px;
  height: 39px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border: 2px solid rgb(255 255 255 / 15%);
  border-radius: 12px;
  background: linear-gradient(145deg, var(--se-orange-500), var(--se-orange-600));
  color: white;
  box-shadow: 0 7px 18px rgb(249 115 22 / 18%);
  font-size: 14px;
  font-weight: 800;
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
  color: white;
  font-size: 13px;
}

.usuario-info small {
  margin-top: 3px;
  color: #8397b3;
  font-size: 11px;
  text-transform: capitalize;
}

.logout {
  display: inline-flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  border: 1px solid transparent;
  border-radius: 9px;
  background: transparent;
  color: #8397b3;
  font-size: 18px;
  cursor: pointer;
  transition: var(--se-transition);
}

.logout:hover {
  border-color: rgb(255 255 255 / 8%);
  background: rgb(255 255 255 / 6%);
  color: white;
}

.conteudo {
  width: calc(100% - 264px);
  min-height: 100vh;
  margin-left: 264px;
  background:
    radial-gradient(circle at 100% 0%, rgb(21 94 239 / 4%), transparent 28%),
    var(--se-bg);
}

@media (max-width: 800px) {
  .sidebar {
    width: 82px;
    padding-right: 10px;
    padding-left: 10px;
  }

  .logo {
    justify-content: center;
    padding-right: 0;
    padding-left: 0;
  }

  .logo > div:last-child,
  .menu::before,
  .usuario-info {
    display: none;
  }

  .menu a {
    justify-content: center;
    padding: 7px;
    font-size: 0;
  }

  .menu a:hover {
    transform: none;
  }

  .menu span {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
    font-size: 18px;
  }

  .usuario {
    justify-content: center;
    padding-right: 0;
    padding-left: 0;
  }

  .logout {
    display: none;
  }

  .conteudo {
    width: calc(100% - 82px);
    margin-left: 82px;
  }
}
</style>