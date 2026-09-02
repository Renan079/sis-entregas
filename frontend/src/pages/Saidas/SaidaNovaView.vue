<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { regiaoService } from '@/services/regiaoService.ts'
import { saidaService } from '@/services/saidaService.ts'
import { veiculoService } from '@/services/veiculoService.ts'

import type { Regiao } from '@/types/Regiao.ts'
import type { SaidaForm } from '@/types/Saida.ts'
import type { Veiculo } from '@/types/Veiculo.ts'

const router = useRouter()

const regioes = ref<Regiao[]>([])
const veiculos = ref<Veiculo[]>([])

const carregando = ref(true)
const salvando = ref(false)
const erro = ref('')

const paradaRegiaoSelecionada = ref<number | null>(null)
const paradaLivre = ref('')

function hojeLocal() {
  const agora = new Date()
  const ano = agora.getFullYear()
  const mes = String(agora.getMonth() + 1).padStart(2, '0')
  const dia = String(agora.getDate()).padStart(2, '0')

  return `${ano}-${mes}-${dia}`
}

const formulario = reactive<SaidaForm>({
  data_saida: hojeLocal(),
  regiao_principal_id: null,
  veiculo_id: null,
  rota: [],
  observacao: '',
})

const veiculosDisponiveis = computed(() =>
  veiculos.value.filter(
    (veiculo) =>
      veiculo.ativo &&
      veiculo.status === 'DISPONIVEL',
  ),
)

async function carregarDadosAuxiliares() {
  carregando.value = true
  erro.value = ''

  try {
    const [regioesResponse, veiculosResponse] = await Promise.all([
      regiaoService.listar(1),
      veiculoService.listar(),
    ])

    regioes.value = regioesResponse.data.filter(
      (regiao) => regiao.ativo,
    )

    veiculos.value = veiculosResponse.data
  } catch {
    erro.value =
      'Não foi possível carregar regiões ou veículos.'
  } finally {
    carregando.value = false
  }
}

function adicionarRegiaoNaRota() {
  if (paradaRegiaoSelecionada.value === null) {
    return
  }

  const regiao = regioes.value.find(
    (item) => item.id === paradaRegiaoSelecionada.value,
  )

  if (!regiao) {
    return
  }

  formulario.rota.push({
    regiao_id: regiao.id,
    nome: regiao.nome,
  })

  paradaRegiaoSelecionada.value = null
}

function adicionarParadaLivre() {
  const nome = paradaLivre.value.trim()

  if (!nome) {
    return
  }

  formulario.rota.push({
    regiao_id: null,
    nome,
  })

  paradaLivre.value = ''
}

function removerParadaRota(index: number) {
  formulario.rota.splice(index, 1)
}

function moverParadaRota(
  index: number,
  direcao: -1 | 1,
) {
  const destino = index + direcao

  if (
    destino < 0 ||
    destino >= formulario.rota.length
  ) {
    return
  }

  const [parada] = formulario.rota.splice(index, 1)

  if (!parada) {
    return
  }

  formulario.rota.splice(destino, 0, parada)
}

async function criarSaida() {
  erro.value = ''
  salvando.value = true

  try {
    const saida = await saidaService.criar({
      data_saida: formulario.data_saida,
      regiao_principal_id:
        formulario.regiao_principal_id || null,
      veiculo_id: formulario.veiculo_id || null,
      rota: formulario.rota.map((parada) => ({
        regiao_id: parada.regiao_id,
        nome: parada.nome,
      })),
      observacao: formulario.observacao?.trim() || null,
    })

    await router.push({
      name: 'saida-detalhe',
      params: { id: saida.id },
    })
  } catch {
    erro.value = 'Não foi possível criar a carrada.'
  } finally {
    salvando.value = false
  }
}

async function voltar() {
  await router.push({
    name: 'saidas',
  })
}

onMounted(async () => {
  await carregarDadosAuxiliares()
})
</script>

<template>
  <main class="pagina-nova-saida">
    <header class="cabecalho-nova-saida">
      <div>
        <button
          class="voltar-link"
          type="button"
          @click="voltar"
        >
          ← Voltar para saídas
        </button>

        <h1>Nova carrada</h1>
        <p>
          Defina os dados básicos e monte a rota da saída.
        </p>
      </div>
    </header>

    <p
      v-if="erro"
      class="erro"
    >
      {{ erro }}
    </p>

    <div
      v-if="carregando"
      class="estado-carregamento"
    >
      Carregando dados da nova carrada...
    </div>

    <form
      v-else
      class="formulario-nova-saida"
      @submit.prevent="criarSaida"
    >
      <section class="card-formulario">
        <header class="secao-cabecalho">
          <div>
            <h2>Dados da saída</h2>
            <p>
              Informe a data, região principal e veículo.
            </p>
          </div>
        </header>

        <div class="grid-dados">
          <div class="campo">
            <label for="data_saida">Data *</label>
            <input
              id="data_saida"
              v-model="formulario.data_saida"
              type="date"
              required
            />
          </div>

          <div class="campo">
            <label for="regiao_principal">
              Região principal
            </label>

            <select
              id="regiao_principal"
              v-model="formulario.regiao_principal_id"
            >
              <option :value="null">
                Selecione...
              </option>

              <option
                v-for="regiao in regioes"
                :key="regiao.id"
                :value="regiao.id"
              >
                {{ regiao.nome }}
              </option>
            </select>
          </div>

          <div class="campo">
            <label for="veiculo">Veículo</label>

            <select
              id="veiculo"
              v-model="formulario.veiculo_id"
            >
              <option :value="null">
                Selecione...
              </option>

              <option
                v-for="veiculo in veiculosDisponiveis"
                :key="veiculo.id"
                :value="veiculo.id"
              >
                {{ veiculo.nome }}
                {{
                  veiculo.placa
                    ? `- ${veiculo.placa}`
                    : ''
                }}
              </option>
            </select>

            <small
              v-if="veiculosDisponiveis.length === 0"
              class="ajuda"
            >
              Nenhum veículo disponível cadastrado.
            </small>
          </div>
        </div>
      </section>

      <section class="card-formulario">
        <header class="secao-cabecalho">
          <div>
            <h2>Rota da carrada</h2>
            <p>
              Monte as paradas na ordem em que o veículo deverá passar.
            </p>
          </div>
        </header>

        <div class="rota-adicionar">
          <select v-model="paradaRegiaoSelecionada">
            <option :value="null">
              Selecione uma região cadastrada...
            </option>

            <option
              v-for="regiao in regioes"
              :key="regiao.id"
              :value="regiao.id"
            >
              {{ regiao.nome }}
            </option>
          </select>

          <button
            class="botao secundario"
            type="button"
            @click="adicionarRegiaoNaRota"
          >
            + Região
          </button>
        </div>

        <div class="rota-adicionar">
          <input
            v-model="paradaLivre"
            type="text"
            placeholder="Outra parada, ex.: Banco de Areia"
            @keydown.enter.prevent="adicionarParadaLivre"
          />

          <button
            class="botao secundario"
            type="button"
            @click="adicionarParadaLivre"
          >
            + Parada
          </button>
        </div>

        <div
          v-if="formulario.rota.length"
          class="rota-lista"
        >
          <div
            v-for="(parada, index) in formulario.rota"
            :key="`${parada.nome}-${index}`"
            class="rota-parada"
          >
            <span class="rota-numero">
              {{ index + 1 }}
            </span>

            <strong>{{ parada.nome }}</strong>

            <div class="rota-acoes">
              <button
                type="button"
                title="Mover para cima"
                :disabled="index === 0"
                @click="moverParadaRota(index, -1)"
              >
                ↑
              </button>

              <button
                type="button"
                title="Mover para baixo"
                :disabled="index === formulario.rota.length - 1"
                @click="moverParadaRota(index, 1)"
              >
                ↓
              </button>

              <button
                class="remover-rota"
                type="button"
                @click="removerParadaRota(index)"
              >
                Remover
              </button>
            </div>
          </div>
        </div>

        <div
          v-else
          class="rota-vazia"
        >
          Nenhuma parada adicionada.
        </div>
      </section>

      <section class="card-formulario">
        <header class="secao-cabecalho">
          <div>
            <h2>Observação</h2>
            <p>
              Inclua alguma informação operacional, se necessário.
            </p>
          </div>
        </header>

        <div class="campo campo-sem-margem">
          <textarea
            v-model="formulario.observacao"
            rows="4"
            placeholder="Observações sobre esta carrada..."
          ></textarea>
        </div>
      </section>

      <footer class="acoes-formulario">
        <button
          class="botao secundario"
          type="button"
          :disabled="salvando"
          @click="voltar"
        >
          Cancelar
        </button>

        <button
          class="botao primario"
          type="submit"
          :disabled="salvando"
        >
          {{ salvando ? 'Criando...' : 'Criar carrada' }}
        </button>
      </footer>
    </form>
  </main>
</template>

<style scoped src="../../styles/pages/saida/saida-nova.css"></style>
