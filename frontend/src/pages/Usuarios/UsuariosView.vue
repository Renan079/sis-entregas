<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usuarioService } from '@/services/usuarioService'
import UsuarioFormularioModal from '@/components/Usuarios/UsuarioFormularioModal.vue'
import UsuarioSenhaModal from '@/components/Usuarios/UsuarioSenhaModal.vue'
import type {
  Usuario,
  UsuarioAtualizacao,
  UsuarioResumo,
  UsuarioSenha,
} from '@/types/Usuario'

const router = useRouter()
const auth = useAuthStore()

const usuarios = ref<Usuario[]>([])
const busca = ref('')
const carregando = ref(false)
const salvando = ref(false)
const erro = ref('')
const sucesso = ref('')

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const totalFiltrado = ref(0)
const perPage = ref(10)

const resumo = reactive<UsuarioResumo>({
  total: 0,
  ativos: 0,
  admins: 0,
  operadores: 0,
})

const usuarioEditando = ref<Usuario | null>(null)
const usuarioSenha = ref<Usuario | null>(null)
const modalErro = ref('')

function mensagemErro(error: unknown, fallback: string) {
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

  return primeiraValidacao || resposta.response?.data?.message || fallback
}

async function carregarUsuarios(page = 1) {
  carregando.value = true
  erro.value = ''

  try {
    const response = await usuarioService.listar(
      busca.value.trim(),
      page,
      perPage.value,
    )

    usuarios.value = response.data
    Object.assign(resumo, response.resumo)

    paginaAtual.value = response.meta.current_page
    ultimaPagina.value = response.meta.last_page
    totalFiltrado.value = response.meta.total
  } catch (error) {
    erro.value = mensagemErro(
      error,
      'Não foi possível carregar os usuários.',
    )
  } finally {
    carregando.value = false
  }
}

function pesquisar() {
  carregarUsuarios(1)
}

function limparBusca() {
  busca.value = ''
  carregarUsuarios(1)
}

function abrirEdicao(usuario: Usuario) {
  modalErro.value = ''
  usuarioEditando.value = usuario
}

function fecharEdicao() {
  if (salvando.value) return

  usuarioEditando.value = null
  modalErro.value = ''
}

async function salvarUsuario(dados: UsuarioAtualizacao) {
  if (!usuarioEditando.value) return

  salvando.value = true
  modalErro.value = ''
  sucesso.value = ''

  try {
    const atualizado = await usuarioService.atualizar(
      usuarioEditando.value.id,
      dados,
    )

    sucesso.value = `Usuário ${atualizado.name} atualizado com sucesso.`
    fecharEdicao()

    if (auth.user?.id === atualizado.id) {
      await auth.fetchUser()
    }

    await carregarUsuarios(paginaAtual.value)
  } catch (error) {
    modalErro.value = mensagemErro(
      error,
      'Não foi possível atualizar o usuário.',
    )
  } finally {
    salvando.value = false
  }
}

async function alternarStatus(usuario: Usuario) {
  if (auth.user?.id === usuario.id) return

  const novoStatus = !usuario.ativo
  const acao = novoStatus ? 'ativar' : 'desativar'

  const confirmou = window.confirm(
    `Deseja ${acao} o usuário "${usuario.name}"?`,
  )

  if (!confirmou) return

  erro.value = ''
  sucesso.value = ''

  try {
    await usuarioService.atualizar(usuario.id, {
      name: usuario.name,
      email: usuario.email,
      perfil: usuario.perfil,
      ativo: novoStatus,
    })

    sucesso.value = `Usuário ${novoStatus ? 'ativado' : 'desativado'} com sucesso.`
    await carregarUsuarios(paginaAtual.value)
  } catch (error) {
    erro.value = mensagemErro(
      error,
      `Não foi possível ${acao} o usuário.`,
    )
  }
}

function abrirSenha(usuario: Usuario) {
  modalErro.value = ''
  usuarioSenha.value = usuario
}

function fecharSenha() {
  if (salvando.value) return

  usuarioSenha.value = null
  modalErro.value = ''
}

async function redefinirSenha(dados: UsuarioSenha) {
  if (!usuarioSenha.value) return

  salvando.value = true
  modalErro.value = ''
  sucesso.value = ''

  try {
    const mensagem = await usuarioService.redefinirSenha(
      usuarioSenha.value.id,
      dados,
    )

    sucesso.value = mensagem
    fecharSenha()
  } catch (error) {
    modalErro.value = mensagemErro(
      error,
      'Não foi possível redefinir a senha.',
    )
  } finally {
    salvando.value = false
  }
}

function formatarData(data: string) {
  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) return '—'

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
  }).format(valor)
}

onMounted(() => {
  carregarUsuarios()
})
</script>

<template>
  <main class="usuarios-page">
    <header class="pagina-cabecalho">
      <div>
        <span class="eyebrow">Administração</span>
        <h1>Usuários</h1>
        <p>Gerencie acessos, perfis e segurança da equipe.</p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="router.push('/usuarios/novo')"
      >
        + Novo usuário
      </button>
    </header>

    <section class="resumo-grid">
      <article class="resumo-card total">
        <span class="icone">♙</span>
        <div>
          <strong>{{ resumo.total }}</strong>
          <span>Usuários</span>
          <small>Total cadastrado</small>
        </div>
      </article>

      <article class="resumo-card ativos">
        <span class="icone">✓</span>
        <div>
          <strong>{{ resumo.ativos }}</strong>
          <span>Ativos</span>
          <small>Com acesso liberado</small>
        </div>
      </article>

      <article class="resumo-card admins">
        <span class="icone">★</span>
        <div>
          <strong>{{ resumo.admins }}</strong>
          <span>Administradores</span>
          <small>Acesso administrativo</small>
        </div>
      </article>

      <article class="resumo-card operadores">
        <span class="icone">⚙</span>
        <div>
          <strong>{{ resumo.operadores }}</strong>
          <span>Operadores</span>
          <small>Equipe operacional</small>
        </div>
      </article>
    </section>

    <section class="card filtros">
      <form class="busca" @submit.prevent="pesquisar">
        <input
          v-model="busca"
          type="search"
          placeholder="Buscar por nome ou e-mail..."
        />

        <button class="botao primario" type="submit">
          Buscar
        </button>

        <button
          v-if="busca"
          class="botao secundario"
          type="button"
          @click="limparBusca"
        >
          Limpar
        </button>
      </form>
    </section>

    <p v-if="erro" class="mensagem erro" role="alert">
      {{ erro }}
    </p>

    <p v-if="sucesso" class="mensagem sucesso" role="status">
      {{ sucesso }}
    </p>

    <section class="card tabela-card">
      <div class="topo-tabela">
        <div>
          <strong>{{ totalFiltrado }} usuário(s)</strong>
          <span>Administradores aparecem primeiro.</span>
        </div>

        <div class="por-pagina">
          <label for="usuarios-per-page">Por página</label>
          <select
            id="usuarios-per-page"
            v-model.number="perPage"
            @change="carregarUsuarios(1)"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>

      <div v-if="carregando" class="estado">
        Carregando usuários...
      </div>

      <div v-else-if="usuarios.length === 0" class="estado">
        Nenhum usuário encontrado.
      </div>

      <div v-else class="tabela-container">
        <table>
          <thead>
            <tr>
              <th>Usuário</th>
              <th>Perfil</th>
              <th>Status</th>
              <th>Cadastrado em</th>
              <th class="acoes-coluna">Ações</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="usuario in usuarios" :key="usuario.id">
              <td>
                <div class="usuario-identidade">
                  <span class="avatar">{{ usuario.name.charAt(0).toUpperCase() }}</span>
                  <div>
                    <strong>
                      {{ usuario.name }}
                      <span v-if="auth.user?.id === usuario.id" class="voce">Você</span>
                    </strong>
                    <small>{{ usuario.email }}</small>
                  </div>
                </div>
              </td>

              <td>
                <span class="perfil" :class="usuario.perfil.toLowerCase()">
                  {{ usuario.perfil === 'ADMIN' ? 'Administrador' : 'Operador' }}
                </span>
              </td>

              <td>
                <span class="status" :class="usuario.ativo ? 'ativo' : 'inativo'">
                  {{ usuario.ativo ? 'Ativo' : 'Inativo' }}
                </span>
              </td>

              <td>{{ formatarData(usuario.created_at) }}</td>

              <td class="acoes">
                <button
                  class="acao"
                  type="button"
                  @click="abrirEdicao(usuario)"
                >
                  Editar
                </button>

                <button
                  class="acao senha"
                  type="button"
                  @click="abrirSenha(usuario)"
                >
                  Redefinir senha
                </button>

                <button
                  v-if="auth.user?.id !== usuario.id"
                  class="acao"
                  :class="usuario.ativo ? 'perigo' : 'ativar'"
                  type="button"
                  @click="alternarStatus(usuario)"
                >
                  {{ usuario.ativo ? 'Desativar' : 'Ativar' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="ultimaPagina > 1" class="paginacao">
        <button
          class="botao secundario"
          type="button"
          :disabled="paginaAtual <= 1"
          @click="carregarUsuarios(paginaAtual - 1)"
        >
          Anterior
        </button>

        <span>Página {{ paginaAtual }} de {{ ultimaPagina }}</span>

        <button
          class="botao secundario"
          type="button"
          :disabled="paginaAtual >= ultimaPagina"
          @click="carregarUsuarios(paginaAtual + 1)"
        >
          Próxima
        </button>
      </footer>
    </section>

    <UsuarioFormularioModal
      v-if="usuarioEditando"
      :usuario="usuarioEditando"
      :usuario-atual-id="auth.user?.id ?? null"
      :salvando="salvando"
      :erro="modalErro"
      @fechar="fecharEdicao"
      @salvar="salvarUsuario"
    />

    <UsuarioSenhaModal
      v-if="usuarioSenha"
      :usuario="usuarioSenha"
      :salvando="salvando"
      :erro="modalErro"
      @fechar="fecharSenha"
      @salvar="redefinirSenha"
    />
  </main>
</template>

<style scoped src="../../styles/pages/usuario/usuarios.css"></style>
