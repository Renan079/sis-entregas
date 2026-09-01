<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { documentoImportacaoService } from '@/services/documentoImportacaoService'

import type { Regiao } from '@/types/Regiao'
import type {
  ConfirmarImportacaoPayload,
  DocumentoImportacaoPreview,
} from '@/types/DocumentoImportacao'

interface ArquivoSelecionado {
  id: string
  arquivo: File
  url: string | null
}

const props = defineProps<{
  aberto: boolean
  regioes: Regiao[]
}>()

const emit = defineEmits<{
  fechar: []
  importado: [entregaId: number]
}>()

const inputCamera = ref<HTMLInputElement | null>(null)
const inputFotos = ref<HTMLInputElement | null>(null)
const inputPdf = ref<HTMLInputElement | null>(null)

const arquivos = ref<ArquivoSelecionado[]>([])
const preview = ref<DocumentoImportacaoPreview | null>(null)
const processando = ref(false)
const salvando = ref(false)
const erro = ref('')

const formulario = ref<ConfirmarImportacaoPayload | null>(null)

const temPdf = computed(() =>
  arquivos.value.some(
    (item) => item.arquivo.type === 'application/pdf',
  ),
)

const podeProcessar = computed(() =>
  arquivos.value.length > 0 &&
  !processando.value,
)

const podeConfirmar = computed(() => {
  const dados = formulario.value

  if (!dados) {
    return false
  }

  return Boolean(
    dados.numero_pedido.trim() &&
    dados.cliente.nome.trim() &&
    dados.regiao_id &&
    dados.endereco.trim() &&
    dados.itens.length > 0 &&
    dados.itens.every(
      (item) =>
        item.descricao_produto.trim() &&
        Number(item.quantidade_solicitada) > 0,
    ),
  )
})

watch(
  () => props.aberto,
  (aberto) => {
    if (!aberto) {
      limpar()
    }
  },
)

function abrirCamera() {
  inputCamera.value?.click()
}

function selecionarFotos() {
  inputFotos.value?.click()
}

function selecionarPdf() {
  inputPdf.value?.click()
}

function cameraSelecionada(event: Event) {
  const input = event.target as HTMLInputElement
  const arquivo = input.files?.[0]
  input.value = ''

  if (!arquivo) return

  if (temPdf.value) {
    limparArquivos()
  }

  adicionarArquivos([arquivo])
}

function fotosSelecionadas(event: Event) {
  const input = event.target as HTMLInputElement
  const novos = Array.from(input.files ?? [])
  input.value = ''

  if (!novos.length) return

  if (temPdf.value) {
    limparArquivos()
  }

  adicionarArquivos(novos)
}

function pdfSelecionado(event: Event) {
  const input = event.target as HTMLInputElement
  const arquivo = input.files?.[0]
  input.value = ''

  if (!arquivo) return

  limparArquivos()
  adicionarArquivos([arquivo])
}

function adicionarArquivos(novos: File[]) {
  erro.value = ''
  preview.value = null
  formulario.value = null

  const vagas = Math.max(0, 12 - arquivos.value.length)

  if (novos.length > vagas) {
    erro.value = 'Uma nota pode ter no máximo 12 fotos.'
  }

  novos.slice(0, vagas).forEach((arquivo) => {
    arquivos.value.push({
      id: `${Date.now()}-${Math.random()}`,
      arquivo,
      url: arquivo.type.startsWith('image/')
        ? URL.createObjectURL(arquivo)
        : null,
    })
  })
}

function removerArquivo(index: number) {
  const item = arquivos.value[index]

  if (item?.url) {
    URL.revokeObjectURL(item.url)
  }

  arquivos.value.splice(index, 1)
}

function moverArquivo(index: number, direcao: -1 | 1) {
  const destino = index + direcao

  if (
    destino < 0 ||
    destino >= arquivos.value.length
  ) {
    return
  }

  const atual = arquivos.value[index]
  const destinoArquivo = arquivos.value[destino]

  if (!atual || !destinoArquivo) {
    return
  }

  arquivos.value[index] = destinoArquivo
  arquivos.value[destino] = atual
}

async function processarDocumento() {
  if (!podeProcessar.value) return

  erro.value = ''
  preview.value = null
  formulario.value = null
  processando.value = true

  try {
    const resultado =
      await documentoImportacaoService.gerarPreview(
        arquivos.value.map((item) => item.arquivo),
      )

    preview.value = resultado
    montarFormulario(resultado)
  } catch (e: any) {
    erro.value = extrairErro(e)
  } finally {
    processando.value = false
  }
}

function montarFormulario(
  resultado: DocumentoImportacaoPreview,
) {
  const clienteSugerido =
    resultado.sugestoes.cliente

  formulario.value = {
    numero_pedido:
      resultado.dados.numero_pedido ?? '',

    cliente_id:
      clienteSugerido?.id ?? null,

    cliente: {
      codigo_externo:
        resultado.dados.cliente.codigo_externo,
      nome:
        clienteSugerido?.nome ??
        resultado.dados.cliente.nome ??
        '',
      apelido:
        clienteSugerido?.apelido ??
        resultado.dados.cliente.apelido,
      telefone:
        clienteSugerido?.telefone ??
        resultado.dados.cliente.telefone,
    },

    regiao_id:
      resultado.sugestoes.regiao?.id ?? 0,

    endereco:
      resultado.dados.endereco ?? '',

    referencia:
      resultado.dados.referencia,

    prioridade:
      resultado.dados.prioridade,

    observacao:
      resultado.dados.observacao,

    itens:
      resultado.dados.itens.map((item) => ({
        ...item,
      })),
  }
}

function editarPaginas() {
  preview.value = null
  formulario.value = null
  erro.value = ''
}

function usarNovoCliente() {
  if (!formulario.value) return

  formulario.value.cliente_id = null

  if (preview.value) {
    formulario.value.cliente = {
      codigo_externo:
        preview.value.dados.cliente.codigo_externo,
      nome:
        preview.value.dados.cliente.nome ?? '',
      apelido:
        preview.value.dados.cliente.apelido,
      telefone:
        preview.value.dados.cliente.telefone,
    }
  }
}

function removerItem(index: number) {
  formulario.value?.itens.splice(index, 1)
}

function adicionarItem() {
  formulario.value?.itens.push({
    codigo_produto: null,
    descricao_produto: '',
    unidade: null,
    quantidade_solicitada: 1,
    observacao: null,
  })
}

async function confirmar() {
  if (
    !preview.value ||
    !formulario.value ||
    !podeConfirmar.value
  ) {
    return
  }

  erro.value = ''
  salvando.value = true

  try {
    const resultado =
      await documentoImportacaoService.confirmar(
        preview.value.documento.id,
        normalizarPayload(formulario.value),
      )

    emit('importado', resultado.entrega_id)
    emit('fechar')
  } catch (e: any) {
    erro.value = extrairErro(e)
  } finally {
    salvando.value = false
  }
}

function normalizarPayload(
  dados: ConfirmarImportacaoPayload,
): ConfirmarImportacaoPayload {
  return {
    ...dados,
    numero_pedido: dados.numero_pedido.trim(),
    cliente: {
      codigo_externo:
        dados.cliente.codigo_externo?.trim() || null,
      nome: dados.cliente.nome.trim(),
      apelido:
        dados.cliente.apelido?.trim() || null,
      telefone:
        dados.cliente.telefone?.trim() || null,
    },
    endereco: dados.endereco.trim(),
    referencia:
      dados.referencia?.trim() || null,
    observacao:
      dados.observacao?.trim() || null,
    itens: dados.itens.map((item) => ({
      codigo_produto:
        item.codigo_produto?.trim() || null,
      descricao_produto:
        item.descricao_produto.trim(),
      unidade:
        item.unidade?.trim() || null,
      quantidade_solicitada:
        Number(item.quantidade_solicitada),
      observacao:
        item.observacao?.trim() || null,
    })),
  }
}

function extrairErro(e: any): string {
  const errors = e?.response?.data?.errors

  if (errors && typeof errors === 'object') {
    const primeira = Object.values(errors)
      .flat()
      .find(Boolean)

    if (typeof primeira === 'string') {
      return primeira
    }
  }

  return (
    e?.response?.data?.message ??
    'Não foi possível processar o documento.'
  )
}

function limparArquivos() {
  arquivos.value.forEach((item) => {
    if (item.url) {
      URL.revokeObjectURL(item.url)
    }
  })

  arquivos.value = []
}

function limpar() {
  limparArquivos()
  preview.value = null
  formulario.value = null
  erro.value = ''
  processando.value = false
  salvando.value = false
}

function fechar() {
  if (processando.value || salvando.value) return
  emit('fechar')
}
</script>

<template>
  <div
    v-if="aberto"
    class="ocr-fundo"
    @click.self="fechar"
  >
    <section class="ocr-modal">
      <header class="ocr-cabecalho">
        <div>
          <span class="eyebrow">
            Importação de documento
          </span>

          <h2>Importar entrega</h2>

          <p>
            Use uma foto, várias fotos da mesma nota
            ou um PDF digitalizado.
          </p>
        </div>

        <button
          class="fechar"
          type="button"
          :disabled="processando || salvando"
          @click="fechar"
        >
          ×
        </button>
      </header>

      <input
        ref="inputCamera"
        class="arquivo-oculto"
        type="file"
        accept="image/*"
        capture="environment"
        @change="cameraSelecionada"
      />

      <input
        ref="inputFotos"
        class="arquivo-oculto"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        @change="fotosSelecionadas"
      />

      <input
        ref="inputPdf"
        class="arquivo-oculto"
        type="file"
        accept="application/pdf"
        @change="pdfSelecionado"
      />

      <div
        v-if="!preview && !processando"
        class="selecao-foto selecao-documento"
      >
        <div class="icone-camera">▣</div>

        <h3>Adicione a nota</h3>

        <p>
          Notas compridas podem ser fotografadas em partes.
          Mantenha as fotos na ordem: topo, meio e final.
        </p>

        <div class="selecao-acoes">
          <button
            class="botao primario"
            type="button"
            :disabled="temPdf"
            @click="abrirCamera"
          >
            Tirar foto
          </button>

          <button
            class="botao secundario"
            type="button"
            :disabled="temPdf"
            @click="selecionarFotos"
          >
            Selecionar fotos
          </button>

          <button
            class="botao secundario"
            type="button"
            @click="selecionarPdf"
          >
            Selecionar PDF
          </button>
        </div>

        <div
          v-if="arquivos.length"
          class="paginas-selecionadas"
        >
          <div class="paginas-topo">
            <div>
              <strong>
                {{ temPdf ? 'PDF selecionado' : 'Páginas da nota' }}
              </strong>
              <span v-if="!temPdf">
                {{ arquivos.length }} de 12 fotos
              </span>
            </div>

            <button
              class="limpar-paginas"
              type="button"
              @click="limparArquivos"
            >
              Limpar
            </button>
          </div>

          <div class="paginas-grid">
            <article
              v-for="(item, index) in arquivos"
              :key="item.id"
              class="pagina-card"
            >
              <div class="pagina-preview">
                <img
                  v-if="item.url"
                  :src="item.url"
                  :alt="`Página ${index + 1}`"
                />

                <div
                  v-else
                  class="pdf-preview"
                >
                  PDF
                </div>

                <span class="pagina-numero">
                  {{ index + 1 }}
                </span>
              </div>

              <div class="pagina-nome">
                {{ item.arquivo.name }}
              </div>

              <div
                v-if="!temPdf"
                class="pagina-acoes"
              >
                <button
                  type="button"
                  :disabled="index === 0"
                  title="Mover para cima"
                  @click="moverArquivo(index, -1)"
                >
                  ←
                </button>

                <button
                  type="button"
                  :disabled="index === arquivos.length - 1"
                  title="Mover para baixo"
                  @click="moverArquivo(index, 1)"
                >
                  →
                </button>

                <button
                  type="button"
                  title="Remover"
                  @click="removerArquivo(index)"
                >
                  ×
                </button>
              </div>
            </article>
          </div>

          <div class="dica-sobreposicao">
            <strong>Dica:</strong>
            em notas grandes, deixe uma pequena sobreposição entre
            as fotos para não cortar linhas, mas confira os produtos
            caso o OCR leia algum item duas vezes.
          </div>

          <button
            class="botao primario processar-nota"
            type="button"
            :disabled="!podeProcessar"
            @click="processarDocumento"
          >
            Processar nota
          </button>
        </div>
      </div>

      <div
        v-if="processando"
        class="processando"
      >
        <div class="spinner"></div>
        <strong>Lendo a nota...</strong>
        <span>
          Estamos juntando as páginas e identificando
          pedido, cliente, endereço e produtos.
        </span>
      </div>

      <p
        v-if="erro"
        class="erro"
      >
        {{ erro }}
      </p>

      <template
        v-if="preview && formulario && !processando"
      >
        <div class="conferencia-layout">
          <aside class="foto-coluna">
            <div class="foto-sticky">
              <strong>
                {{
                  preview.documento.tipo_arquivo === 'PDF'
                    ? 'PDF enviado'
                    : `${preview.documento.quantidade_arquivos} foto(s) enviada(s)`
                }}
              </strong>

              <div class="mini-paginas">
                <template
                  v-for="(item, index) in arquivos"
                  :key="item.id"
                >
                  <img
                    v-if="item.url"
                    :src="item.url"
                    :alt="`Página ${index + 1}`"
                  />

                  <div
                    v-else
                    class="pdf-preview lateral"
                  >
                    PDF
                  </div>
                </template>
              </div>

              <button
                class="botao secundario trocar-foto"
                type="button"
                @click="editarPaginas"
              >
                Editar páginas
              </button>
            </div>
          </aside>
          <div class="dados-coluna">
            <div
              v-if="preview.dados.avisos.length"
              class="avisos"
            >
              <strong>Confira estes pontos</strong>

              <ul>
                <li
                  v-for="aviso in preview.dados.avisos"
                  :key="aviso"
                >
                  {{ aviso }}
                </li>
              </ul>
            </div>

            <div
              v-if="preview.sugestoes.cliente"
              class="sugestao"
            >
              <div>
                <strong>Cliente encontrado</strong>
                <span>
                  {{ preview.sugestoes.cliente.nome }}
                  <template
                    v-if="preview.sugestoes.cliente.telefone"
                  >
                    · {{ preview.sugestoes.cliente.telefone }}
                  </template>
                </span>
              </div>

              <button
                v-if="formulario.cliente_id"
                type="button"
                @click="usarNovoCliente"
              >
                Não é este cliente
              </button>
            </div>

            <section class="secao">
              <h3>Dados da entrega</h3>

              <div class="grid-form">
                <label class="campo">
                  <span>Pedido *</span>
                  <input
                    v-model="formulario.numero_pedido"
                    type="text"
                  />
                </label>

                <label class="campo">
                  <span>Região *</span>
                  <select v-model="formulario.regiao_id">
                    <option :value="0">
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
                </label>

                <label class="campo">
                  <span>Prioridade</span>
                  <select v-model="formulario.prioridade">
                    <option value="BAIXA">Baixa</option>
                    <option value="NORMAL">Normal</option>
                    <option value="ALTA">Alta</option>
                    <option value="URGENTE">Urgente</option>
                  </select>
                </label>

                <div class="campo leitura">
                  <span>Data lida na nota</span>
                  <strong>
                    {{ preview.dados.data_documento || 'Não identificada' }}
                  </strong>
                </div>
              </div>
            </section>

            <section class="secao">
              <h3>Cliente</h3>

              <div class="grid-form">
                <label class="campo">
                  <span>Código externo</span>
                  <input
                    v-model="formulario.cliente.codigo_externo"
                    type="text"
                  />
                </label>

                <label class="campo campo-duplo">
                  <span>Nome *</span>
                  <input
                    v-model="formulario.cliente.nome"
                    type="text"
                  />
                </label>

                <label class="campo">
                  <span>Apelido</span>
                  <input
                    v-model="formulario.cliente.apelido"
                    type="text"
                  />
                </label>

                <label class="campo">
                  <span>Telefone</span>
                  <input
                    v-model="formulario.cliente.telefone"
                    type="text"
                  />
                </label>
              </div>
            </section>

            <section class="secao">
              <h3>Local da entrega</h3>

              <label class="campo">
                <span>Endereço *</span>
                <input
                  v-model="formulario.endereco"
                  type="text"
                />
              </label>

              <label class="campo">
                <span>Referência</span>
                <input
                  v-model="formulario.referencia"
                  type="text"
                />
              </label>
            </section>

            <section class="secao">
              <div class="secao-topo">
                <div>
                  <h3>Produtos</h3>
                  <p>
                    Confira principalmente código, descrição
                    e quantidade.
                  </p>
                </div>

                <button
                  class="botao secundario"
                  type="button"
                  @click="adicionarItem"
                >
                  + Produto
                </button>
              </div>

              <div class="produtos">
                <div
                  v-for="(item, index) in formulario.itens"
                  :key="index"
                  class="produto"
                >
                  <div class="produto-numero">
                    {{ index + 1 }}
                  </div>

                  <div class="produto-grid">
                    <label class="campo">
                      <span>Código</span>
                      <input
                        v-model="item.codigo_produto"
                        type="text"
                      />
                    </label>

                    <label class="campo descricao">
                      <span>Descrição *</span>
                      <input
                        v-model="item.descricao_produto"
                        type="text"
                      />
                    </label>

                    <label class="campo unidade">
                      <span>Unidade</span>
                      <input
                        v-model="item.unidade"
                        type="text"
                        placeholder="UN"
                      />
                    </label>

                    <label class="campo quantidade">
                      <span>Quantidade *</span>
                      <input
                        v-model.number="item.quantidade_solicitada"
                        type="number"
                        min="0.001"
                        step="0.001"
                      />
                    </label>
                  </div>

                  <button
                    class="remover-produto"
                    type="button"
                    :disabled="formulario.itens.length === 1"
                    title="Remover produto"
                    @click="removerItem(index)"
                  >
                    ×
                  </button>
                </div>
              </div>
            </section>

            <section class="secao">
              <label class="campo">
                <span>Observação da nota</span>
                <textarea
                  v-model="formulario.observacao"
                  rows="3"
                ></textarea>
              </label>
            </section>
          </div>
        </div>

        <footer class="ocr-acoes">
          <div class="seguranca">
            A entrega só será criada depois da sua confirmação.
          </div>

          <div>
            <button
              class="botao secundario"
              type="button"
              :disabled="salvando"
              @click="fechar"
            >
              Cancelar
            </button>

            <button
              class="botao primario"
              type="button"
              :disabled="!podeConfirmar || salvando"
              @click="confirmar"
            >
              {{
                salvando
                  ? 'Importando...'
                  : 'Confirmar e criar entrega'
              }}
            </button>
          </div>
        </footer>
      </template>
    </section>
  </div>
</template>


<style scoped>
* {
  box-sizing: border-box;
}

.ocr-fundo {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(16 24 40 / 60%);
  backdrop-filter: blur(3px);
}

.ocr-modal {
  width: min(1180px, 100%);
  max-height: calc(100vh - 40px);
  overflow: auto;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 24px 80px rgb(16 24 40 / 30%);
}

.ocr-cabecalho {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding: 20px 24px;
  border-bottom: 1px solid #eaecf0;
  background: #fff;
}

.ocr-cabecalho h2 {
  margin: 4px 0 4px;
  color: #101828;
  font-size: 22px;
}

.ocr-cabecalho p {
  margin: 0;
  color: #667085;
  font-size: 13px;
}

.eyebrow {
  color: #175cd3;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.fechar {
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: #667085;
  font-size: 26px;
  cursor: pointer;
}

.fechar:hover:not(:disabled) {
  background: #f2f4f7;
}

.arquivo-oculto {
  display: none;
}

.selecao-foto,
.processando {
  display: flex;
  min-height: 420px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 42px 20px;
  text-align: center;
}

.icone-camera {
  display: grid;
  width: 64px;
  height: 64px;
  place-items: center;
  border-radius: 18px;
  background: #eff8ff;
  color: #175cd3;
  font-size: 30px;
}

.selecao-foto h3 {
  margin: 18px 0 7px;
}

.selecao-foto p {
  max-width: 490px;
  margin: 0;
  color: #667085;
  line-height: 1.55;
}

.selecao-acoes {
  display: flex;
  gap: 10px;
  margin-top: 22px;
}

.botao {
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 9px;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.botao:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.botao.primario {
  background: #175cd3;
  color: #fff;
}

.botao.secundario {
  border-color: #d0d5dd;
  background: #fff;
  color: #344054;
}

.spinner {
  width: 42px;
  height: 42px;
  margin-bottom: 16px;
  border: 4px solid #e4e7ec;
  border-top-color: #175cd3;
  border-radius: 50%;
  animation: girar 0.8s linear infinite;
}

.processando span {
  margin-top: 6px;
  color: #667085;
}

@keyframes girar {
  to {
    transform: rotate(360deg);
  }
}

.erro {
  margin: 16px 24px 0;
  padding: 12px 14px;
  border: 1px solid #fecdca;
  border-radius: 9px;
  background: #fef3f2;
  color: #b42318;
}

.conferencia-layout {
  display: grid;
  grid-template-columns: 330px minmax(0, 1fr);
}

.foto-coluna {
  padding: 22px;
  border-right: 1px solid #eaecf0;
  background: #f9fafb;
}

.foto-sticky {
  position: sticky;
  top: 100px;
}

.foto-sticky > strong {
  display: block;
  margin-bottom: 10px;
}

.foto-sticky img {
  display: block;
  width: 100%;
  max-height: 590px;
  object-fit: contain;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
  background: #fff;
}

.trocar-foto {
  width: 100%;
  margin-top: 10px;
}

.dados-coluna {
  min-width: 0;
  padding: 22px 24px 8px;
}

.avisos {
  margin-bottom: 18px;
  padding: 13px 15px;
  border: 1px solid #fedf89;
  border-radius: 10px;
  background: #fffaeb;
  color: #7a2e0e;
}

.avisos ul {
  margin: 7px 0 0;
  padding-left: 20px;
  font-size: 13px;
}

.sugestao {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 18px;
  padding: 12px 14px;
  border: 1px solid #abefc6;
  border-radius: 10px;
  background: #ecfdf3;
}

.sugestao strong,
.sugestao span {
  display: block;
}

.sugestao span {
  margin-top: 3px;
  color: #027a48;
  font-size: 12px;
}

.sugestao button {
  border: 0;
  background: transparent;
  color: #175cd3;
  font-weight: 700;
  cursor: pointer;
}

.secao {
  padding: 18px 0;
  border-bottom: 1px solid #eaecf0;
}

.secao:last-child {
  border-bottom: 0;
}

.secao h3 {
  margin: 0 0 14px;
  color: #101828;
  font-size: 15px;
}

.secao-topo {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.secao-topo h3 {
  margin-bottom: 4px;
}

.secao-topo p {
  margin: 0;
  color: #667085;
  font-size: 12px;
}

.grid-form {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 13px;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 13px;
}

.campo > span {
  color: #344054;
  font-size: 12px;
  font-weight: 700;
}

.campo input,
.campo select,
.campo textarea {
  width: 100%;
  min-height: 42px;
  border: 1px solid #d0d5dd;
  border-radius: 9px;
  padding: 9px 11px;
  background: #fff;
  color: #101828;
  font: inherit;
  outline: none;
}

.campo input:focus,
.campo select:focus,
.campo textarea:focus {
  border-color: #84adff;
  box-shadow: 0 0 0 3px rgb(37 99 235 / 10%);
}

.campo textarea {
  resize: vertical;
}

.campo.leitura {
  min-height: 42px;
  justify-content: center;
  padding: 8px 11px;
  border: 1px solid #e4e7ec;
  border-radius: 9px;
  background: #f9fafb;
}

.campo.leitura strong {
  font-size: 13px;
}

.campo-duplo {
  grid-column: span 2;
}

.produtos {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 14px;
}

.produto {
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr) 34px;
  gap: 10px;
  align-items: start;
  padding: 13px;
  border: 1px solid #e4e7ec;
  border-radius: 11px;
  background: #f9fafb;
}

.produto-numero {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border-radius: 50%;
  background: #dbeafe;
  color: #175cd3;
  font-size: 12px;
  font-weight: 800;
}

.produto-grid {
  display: grid;
  grid-template-columns:
    minmax(120px, 0.8fr)
    minmax(250px, 2fr)
    90px
    110px;
  gap: 10px;
}

.produto-grid .campo {
  margin: 0;
}

.remover-produto {
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #b42318;
  font-size: 22px;
  cursor: pointer;
}

.remover-produto:hover:not(:disabled) {
  background: #fee4e2;
}

.remover-produto:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.ocr-acoes {
  position: sticky;
  bottom: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 24px;
  border-top: 1px solid #eaecf0;
  background: #fff;
}

.ocr-acoes > div:last-child {
  display: flex;
  gap: 9px;
}

.seguranca {
  color: #667085;
  font-size: 12px;
}

@media (max-width: 900px) {
  .conferencia-layout {
    grid-template-columns: 1fr;
  }

  .foto-coluna {
    border-right: 0;
    border-bottom: 1px solid #eaecf0;
  }

  .foto-sticky {
    position: static;
  }

  .foto-sticky img {
    max-height: 360px;
  }

  .grid-form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .produto-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 620px) {
  .ocr-fundo {
    padding: 0;
  }

  .ocr-modal {
    width: 100%;
    max-height: 100vh;
    min-height: 100vh;
    border-radius: 0;
  }

  .grid-form,
  .produto-grid {
    grid-template-columns: 1fr;
  }

  .campo-duplo {
    grid-column: span 1;
  }

  .selecao-acoes,
  .ocr-acoes,
  .ocr-acoes > div:last-child {
    width: 100%;
    flex-direction: column;
  }

  .ocr-acoes .botao,
  .selecao-acoes .botao {
    width: 100%;
  }

  .seguranca {
    text-align: center;
  }
}


/* V4 — múltiplas fotos e PDF */
.selecao-documento {
  align-items: center;
}

.paginas-selecionadas {
  width: min(900px, 100%);
  margin-top: 22px;
  padding: 18px;
  border: 1px solid #e4e7ec;
  border-radius: 14px;
  background: #f9fafb;
  text-align: left;
}

.paginas-topo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}

.paginas-topo > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.paginas-topo span {
  color: #667085;
  font-size: 12px;
}

.limpar-paginas {
  border: 0;
  background: transparent;
  color: #b42318;
  font-weight: 700;
  cursor: pointer;
}

.paginas-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
  gap: 12px;
}

.pagina-card {
  min-width: 0;
  padding: 8px;
  border: 1px solid #eaecf0;
  border-radius: 11px;
  background: #fff;
}

.pagina-preview {
  position: relative;
  height: 155px;
  overflow: hidden;
  border-radius: 8px;
  background: #f2f4f7;
}

.pagina-preview img,
.mini-paginas img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  background: #f2f4f7;
}

.pagina-numero {
  position: absolute;
  top: 7px;
  left: 7px;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 999px;
  background: #101828;
  color: #fff;
  font-size: 11px;
  font-weight: 800;
}

.pagina-nome {
  overflow: hidden;
  margin-top: 7px;
  color: #344054;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pagina-acoes {
  display: flex;
  gap: 6px;
  margin-top: 7px;
}

.pagina-acoes button {
  flex: 1;
  height: 30px;
  border: 1px solid #d0d5dd;
  border-radius: 7px;
  background: #fff;
  cursor: pointer;
}

.pagina-acoes button:disabled {
  opacity: .35;
  cursor: default;
}

.pdf-preview {
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  background: #fef3f2;
  color: #b42318;
  font-size: 24px;
  font-weight: 900;
}

.pdf-preview.lateral {
  height: 180px;
  border-radius: 10px;
}

.dica-sobreposicao {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 9px;
  background: #eff8ff;
  color: #175cd3;
  font-size: 12px;
  line-height: 1.5;
}

.processar-nota {
  width: 100%;
  margin-top: 14px;
}

.mini-paginas {
  display: grid;
  gap: 8px;
  margin-top: 10px;
}

.mini-paginas img {
  max-height: 220px;
  border: 1px solid #eaecf0;
  border-radius: 9px;
}

@media (max-width: 720px) {
  .paginas-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .pagina-preview {
    height: 130px;
  }
}

</style>
