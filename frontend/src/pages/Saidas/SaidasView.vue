<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'

import { saidaService } from '@/services/saidaService.ts'
import type {
  EntregaDisponivelRota,
  PendenciaRota,
  SaidaHistorico,
} from '@/services/saidaService.ts'
import { regiaoService } from '@/services/regiaoService.ts'
import { veiculoService } from '@/services/veiculoService.ts'

import type {
  DestinoNaoEntregue,
  EntregaDisponivelCarrada,
  ItemProgramacaoCarrada,
  MotivoDevolucao,
  Saida,
  SaidaForm,
  SaidaRotaParada,
  StatusSaida,
  ItemRetornoCarrada,
  SaidaEntrega,
} from '@/types/Saida.ts'

import type { Regiao } from '@/types/Regiao.ts'
import type { Veiculo } from '@/types/Veiculo.ts'

const saidas = ref<Saida[]>([])
const regioes = ref<Regiao[]>([])
const veiculos = ref<Veiculo[]>([])
const entregasDisponiveis = ref<EntregaDisponivelRota[]>([])
const pendenciasDisponiveis = ref<PendenciaRota[]>([])
const pendenciasNaCarrada = ref<PendenciaRota[]>([])
const processandoPendenciaId = ref<number | null>(null)

const carregando = ref(false)
const carregandoItens = ref(false)
const salvando = ref(false)
const modalRetorno = ref(false)

const entregaRetorno =
  ref<SaidaEntrega | null>(null)

const itensRetorno =
  ref<ItemRetornoCarrada[]>([])

const motivoRetorno = ref('')
const observacaoRetorno = ref('')

const registrandoRetorno = ref(false)

const modalRetornoPendencia = ref(false)
const pendenciaRetorno = ref<PendenciaRota | null>(null)
const quantidadeEntreguePendencia = ref(0)
const destinoRestantePendencia =
  ref<DestinoNaoEntregue | null>(null)
const motivoDevolucaoPendencia =
  ref<MotivoDevolucao | null>(null)
const observacaoRetornoPendencia = ref('')
const registrandoRetornoPendencia = ref(false)

const erro = ref('')

const modalNovaSaida = ref(false)
const modalCarrada = ref(false)
const modalSelecionarItens = ref(false)

const modalHistorico = ref(false)
const carregandoHistorico = ref(false)
const historicoCarrada = ref<SaidaHistorico | null>(null)

const saidaSelecionada = ref<Saida | null>(null)

const entregaParaAdicionar =
  ref<EntregaDisponivelCarrada | null>(null)

const quantidadesProgramadas = reactive<
  Record<number, number>
>({})

const paginaAtual = ref(1)
const ultimaPagina = ref(1)
const total = ref(0)

function hojeLocal() {
  const agora = new Date()

  const ano = agora.getFullYear()

  const mes = String(
    agora.getMonth() + 1,
  ).padStart(2, '0')

  const dia = String(
    agora.getDate(),
  ).padStart(2, '0')

  return `${ano}-${mes}-${dia}`
}

const filtros = reactive({
  busca: '',
  status: '' as StatusSaida | '',
  data: '',
})

const paradaRegiaoSelecionada =
  ref<number | null>(null)

const paradaLivre = ref('')

const formulario = reactive<SaidaForm>({
  data_saida: hojeLocal(),
  regiao_principal_id: null,
  veiculo_id: null,
  rota: [],
  observacao: '',
})

interface EventoHistoricoCarrada {
  id: number
  data: string
  titulo: string
  descricao: string
  tipo: string
  origem: string
}

const veiculosDisponiveis = computed(() =>
  veiculos.value.filter(
    (veiculo) =>
      veiculo.ativo &&
      veiculo.status === 'DISPONIVEL',
  ),
)

const eventosHistoricoCarrada = computed<
  EventoHistoricoCarrada[]
>(() => {
  if (!historicoCarrada.value) {
    return []
  }

  /*
   * A timeline agora vem diretamente de
   * saida_eventos.
   *
   * O frontend não tenta mais reconstruir
   * o histórico sozinho.
   */
  return historicoCarrada.value.eventos.map(
    (evento) => ({
      id: evento.id,
      data: evento.data_hora,
      titulo: tituloEventoCarrada(
        evento.tipo_evento,
      ),
      descricao: evento.descricao,
      tipo: evento.tipo_evento,
      origem: evento.origem,
    }),
  )
})

async function carregarSaidas(page = 1) {
  carregando.value = true
  erro.value = ''

  try {
    const response = await saidaService.listar({
      busca: filtros.busca,
      status: filtros.status,
      data: filtros.data,
      page,
    })

    saidas.value = response.data
    paginaAtual.value = response.meta.current_page
    ultimaPagina.value = response.meta.last_page
    total.value = response.meta.total
  } catch {
    erro.value = 'Não foi possível carregar as saídas.'
  } finally {
    carregando.value = false
  }
}

async function carregarDadosAuxiliares() {
  try {
    const [regioesResponse, veiculosResponse] =
      await Promise.all([
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
  }
}

function adicionarRegiaoNaRota() {
  if (
    paradaRegiaoSelecionada.value === null
  ) {
    return
  }

  const regiao = regioes.value.find(
    (item) =>
      item.id ===
      paradaRegiaoSelecionada.value,
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
  const nome =
    paradaLivre.value.trim()

  if (!nome) {
    return
  }

  formulario.rota.push({
    regiao_id: null,
    nome,
  })

  paradaLivre.value = ''
}

function removerParadaRota(
  index: number,
) {
  formulario.rota.splice(
    index,
    1,
  )
}

function moverParadaRota(
  index: number,
  direcao: -1 | 1,
) {
  const destino =
    index + direcao

  if (
    destino < 0 ||
    destino >= formulario.rota.length
  ) {
    return
  }

  const [parada] =
    formulario.rota.splice(
      index,
      1,
    )

  if (!parada) {
    return
  }

  formulario.rota.splice(
    destino,
    0,
    parada,
  )
}

function rotaResumo(
  rota: SaidaRotaParada[],
) {
  if (!rota.length) {
    return 'Sem rota definida'
  }

  const nomes =
    rota.map(
      (parada) => parada.nome,
    )

  if (nomes.length <= 4) {
    return nomes.join(' → ')
  }

  return `${
    nomes.slice(0, 4).join(' → ')
  } → ...`
}

function novaSaida() {
  formulario.data_saida =
    hojeLocal()

  formulario.regiao_principal_id = null
  formulario.veiculo_id = null
  formulario.rota = []
  formulario.observacao = ''

  paradaRegiaoSelecionada.value = null
  paradaLivre.value = ''

  modalNovaSaida.value = true
}

function abrirRetorno(
  entrega: SaidaEntrega,
) {
  erro.value = ''
  entregaRetorno.value = entrega

  /*
   * Começamos assumindo que tudo foi entregue.
   * Se o operador reduzir a quantidade entregue,
   * deverá escolher o destino do saldo daquele item.
   */
  itensRetorno.value =
    entrega.itens_programados.map(
      (item) => ({
        entrega_item_id:
          item.entrega_item_id,

        quantidade_entregue:
          Number(
            item.quantidade_programada,
          ),

        destino_nao_entregue: null,
        motivo_devolucao: null,
        observacao: null,
      }),
    )

  motivoRetorno.value = ''
  observacaoRetorno.value = ''
  modalRetorno.value = true
}

function fecharRetorno() {
  modalRetorno.value = false
  entregaRetorno.value = null
  itensRetorno.value = []
  motivoRetorno.value = ''
  observacaoRetorno.value = ''
}

function itemRetorno(
  entregaItemId: number,
) {
  return itensRetorno.value.find(
    (item) =>
      item.entrega_item_id ===
      entregaItemId,
  )
}

function quantidadeFaltanteRetorno(
  carregado: string | number,
  entregaItemId: number,
) {
  return Math.max(
    0,
    Number(carregado) -
      Number(
        itemRetorno(entregaItemId)
          ?.quantidade_entregue ?? 0,
      ),
  )
}

function limparDestinoSemFalta(
  carregado: string | number,
  entregaItemId: number,
) {
  if (
    quantidadeFaltanteRetorno(
      carregado,
      entregaItemId,
    ) > 0
  ) {
    return
  }

  const retorno =
    itemRetorno(entregaItemId)

  if (!retorno) {
    return
  }

  retorno.destino_nao_entregue = null
  retorno.motivo_devolucao = null
}

function alterarDestinoItemRetorno(
  entregaItemId: number,
) {
  const retorno =
    itemRetorno(entregaItemId)

  if (!retorno) {
    return
  }

  if (
    retorno.destino_nao_entregue !==
    'DEVOLVIDO'
  ) {
    retorno.motivo_devolucao = null
  }
}

async function confirmarRetorno() {
  if (
    !saidaSelecionada.value ||
    !entregaRetorno.value
  ) {
    return
  }

  erro.value = ''

  const quantidadeInvalida =
    entregaRetorno.value
      .itens_programados
      .some((programado) => {
        const retorno =
          itemRetorno(
            programado.entrega_item_id,
          )

        const entregue =
          Number(
            retorno
              ?.quantidade_entregue ?? 0,
          )

        const carregado =
          Number(
            programado
              .quantidade_programada,
          )

        return (
          entregue < 0 ||
          entregue > carregado
        )
      })

  if (quantidadeInvalida) {
    erro.value =
      'A quantidade entregue deve ficar entre zero e a quantidade carregada.'
    return
  }

  const faltaSemDestino =
    entregaRetorno.value
      .itens_programados
      .some((programado) => {
        const faltou =
          quantidadeFaltanteRetorno(
            programado.quantidade_programada,
            programado.entrega_item_id,
          )

        if (faltou <= 0) {
          return false
        }

        return !itemRetorno(
          programado.entrega_item_id,
        )?.destino_nao_entregue
      })

  if (faltaSemDestino) {
    erro.value =
      'Informe se cada quantidade não entregue será reentregue ou devolvida.'
    return
  }

  const devolucaoSemMotivo =
    entregaRetorno.value
      .itens_programados
      .some((programado) => {
        const retorno =
          itemRetorno(
            programado.entrega_item_id,
          )

        const faltou =
          quantidadeFaltanteRetorno(
            programado.quantidade_programada,
            programado.entrega_item_id,
          )

        return (
          faltou > 0 &&
          retorno?.destino_nao_entregue ===
            'DEVOLVIDO' &&
          !retorno.motivo_devolucao
        )
      })

  if (devolucaoSemMotivo) {
    erro.value =
      'Informe o motivo de cada produto marcado como devolvido.'
    return
  }

  /*
   * Remove valores antigos caso o operador tenha
   * diminuído a quantidade, escolhido um destino e
   * depois voltado a informar entrega completa.
   */
  for (
    const programado of
    entregaRetorno.value.itens_programados
  ) {
    limparDestinoSemFalta(
      programado.quantidade_programada,
      programado.entrega_item_id,
    )
  }

  registrandoRetorno.value = true

  try {
    await saidaService
      .registrarResultadoEntrega(
        saidaSelecionada.value.id,
        entregaRetorno.value.id,
        {
          itens: itensRetorno.value,

          motivo_nao_entrega:
            motivoRetorno.value.trim() ||
            null,

          observacao:
            observacaoRetorno.value.trim() ||
            null,
        },
      )

    saidaSelecionada.value =
      await saidaService.buscar(
        saidaSelecionada.value.id,
      )

    fecharRetorno()

    await Promise.all([
      carregarPendenciasCarrada(),
      carregarSaidas(
        paginaAtual.value,
      ),
    ])
  } catch {
    erro.value =
      'Não foi possível registrar o retorno da entrega.'
  } finally {
    registrandoRetorno.value = false
  }
}

/* =========================================================
 * RETORNO DE REENTREGA / PENDÊNCIA
 * ========================================================= */
function abrirRetornoPendencia(
  pendencia: PendenciaRota,
) {
  erro.value = ''
  pendenciaRetorno.value = pendencia

  quantidadeEntreguePendencia.value =
    Number(
      pendencia.saldo_pendente,
    )

  destinoRestantePendencia.value = null
  motivoDevolucaoPendencia.value = null
  observacaoRetornoPendencia.value = ''
  modalRetornoPendencia.value = true
}

function fecharRetornoPendencia() {
  modalRetornoPendencia.value = false
  pendenciaRetorno.value = null
  quantidadeEntreguePendencia.value = 0
  destinoRestantePendencia.value = null
  motivoDevolucaoPendencia.value = null
  observacaoRetornoPendencia.value = ''
}

function quantidadeFaltantePendencia() {
  if (!pendenciaRetorno.value) {
    return 0
  }

  return Math.max(
    0,
    Number(
      pendenciaRetorno.value.saldo_pendente,
    ) -
      Number(
        quantidadeEntreguePendencia.value,
      ),
  )
}

function alterarDestinoPendencia() {
  if (
    destinoRestantePendencia.value !==
    'DEVOLVIDO'
  ) {
    motivoDevolucaoPendencia.value = null
  }
}

async function confirmarRetornoPendencia() {
  if (
    !saidaSelecionada.value ||
    !pendenciaRetorno.value
  ) {
    return
  }

  erro.value = ''

  const programado =
    Number(
      pendenciaRetorno.value.saldo_pendente,
    )

  const entregue =
    Number(
      quantidadeEntreguePendencia.value,
    )

  if (
    entregue < 0 ||
    entregue > programado
  ) {
    erro.value =
      'A quantidade entregue na reentrega deve ficar entre zero e o saldo programado.'
    return
  }

  const faltou =
    quantidadeFaltantePendencia()

  if (
    faltou > 0 &&
    !destinoRestantePendencia.value
  ) {
    erro.value =
      'Informe o destino da quantidade que ainda não foi entregue.'
    return
  }

  if (
    faltou > 0 &&
    destinoRestantePendencia.value ===
      'DEVOLVIDO' &&
    !motivoDevolucaoPendencia.value
  ) {
    erro.value =
      'Informe o motivo da devolução.'
    return
  }

  registrandoRetornoPendencia.value = true

  try {
    await saidaService
      .registrarResultadoPendencia(
        saidaSelecionada.value.id,
        pendenciaRetorno.value.id,
        {
          quantidade_entregue: entregue,

          destino_restante:
            faltou > 0
              ? destinoRestantePendencia.value
              : null,

          motivo_devolucao:
            faltou > 0 &&
            destinoRestantePendencia.value ===
              'DEVOLVIDO'
              ? motivoDevolucaoPendencia.value
              : null,

          observacao:
            observacaoRetornoPendencia.value
              .trim() || null,
        },
      )

    saidaSelecionada.value =
      await saidaService.buscar(
        saidaSelecionada.value.id,
      )

    fecharRetornoPendencia()

    await Promise.all([
      carregarPendenciasCarrada(),
      carregarEntregasDisponiveis(),
      carregarSaidas(
        paginaAtual.value,
      ),
    ])
  } catch {
    erro.value =
      'Não foi possível registrar o retorno da reentrega.'
  } finally {
    registrandoRetornoPendencia.value = false
  }
}

function fecharNovaSaida() {
  modalNovaSaida.value = false
}

async function criarSaida() {
  erro.value = ''
  salvando.value = true

  try {
    const saida = await saidaService.criar({
      data_saida: formulario.data_saida,

      regiao_principal_id:
        formulario.regiao_principal_id || null,

      veiculo_id:
        formulario.veiculo_id || null,

      rota:
        formulario.rota.map(
          (parada) => ({
            regiao_id:
              parada.regiao_id,

            nome:
              parada.nome,
          }),
        ),

      observacao:
        formulario.observacao?.trim() || null,
    })

    modalNovaSaida.value = false

    await carregarSaidas()

    await abrirCarrada(saida)
  } catch {
    erro.value = 'Não foi possível criar a carrada.'
  } finally {
    salvando.value = false
  }
}

async function abrirCarrada(saida: Saida) {
  erro.value = ''

  try {
    saidaSelecionada.value =
      await saidaService.buscar(saida.id)

    modalCarrada.value = true

    await Promise.all([
      carregarEntregasDisponiveis(),
      carregarPendenciasCarrada(),
    ])
  } catch {
    erro.value =
      'Não foi possível carregar os dados da carrada.'
  }
}

function fecharCarrada() {
  modalCarrada.value = false
  saidaSelecionada.value = null
  entregasDisponiveis.value = []
  pendenciasDisponiveis.value = []
  pendenciasNaCarrada.value = []
  processandoPendenciaId.value = null

  fecharSelecaoItens()
}

async function carregarEntregasDisponiveis() {
  if (!saidaSelecionada.value) {
    return
  }

  try {
    /*
     * O backend considera todas as regiões
     * cadastradas na rota da carrada.
     *
     * As entregas já retornam na ordem
     * do percurso e sem as que já foram
     * adicionadas a esta carrada.
     */
    entregasDisponiveis.value =
      await saidaService.buscarEntregasDisponiveis(
        saidaSelecionada.value.id,
      )
  } catch {
    erro.value =
      'Não foi possível carregar as entregas disponíveis da rota.'
  }
}

async function carregarPendenciasCarrada() {
  if (!saidaSelecionada.value) {
    return
  }

  try {
    const [disponiveis, naCarrada] =
      await Promise.all([
        saidaService.buscarPendenciasDisponiveis(
          saidaSelecionada.value.id,
        ),
        saidaService.buscarPendenciasDaCarrada(
          saidaSelecionada.value.id,
        ),
      ])

    pendenciasDisponiveis.value = disponiveis
    pendenciasNaCarrada.value = naCarrada
  } catch {
    erro.value =
      'Não foi possível carregar as pendências da rota.'
  }
}

async function adicionarPendenciaCarrada(
  pendencia: PendenciaRota,
) {
  if (!saidaSelecionada.value) {
    return
  }

  processandoPendenciaId.value = pendencia.id
  erro.value = ''

  try {
    await saidaService.adicionarPendencia(
      saidaSelecionada.value.id,
      pendencia.id,
    )

    await carregarPendenciasCarrada()

    await carregarSaidas(
      paginaAtual.value,
    )
  } catch {
    erro.value =
      'Não foi possível adicionar a pendência à carrada.'
  } finally {
    processandoPendenciaId.value = null
  }
}

async function removerPendenciaCarrada(
  pendencia: PendenciaRota,
) {
  if (!saidaSelecionada.value) {
    return
  }

  const confirmou = window.confirm(
    'Deseja remover esta reentrega da carrada?',
  )

  if (!confirmou) {
    return
  }

  processandoPendenciaId.value = pendencia.id
  erro.value = ''

  try {
    await saidaService.removerPendencia(
      saidaSelecionada.value.id,
      pendencia.id,
    )

    await carregarPendenciasCarrada()

    await carregarSaidas(
      paginaAtual.value,
    )
  } catch {
    erro.value =
      'Não foi possível remover a pendência da carrada.'
  } finally {
    processandoPendenciaId.value = null
  }
}

async function abrirItensEntrega(
  entrega: EntregaDisponivelRota,
) {
  carregandoItens.value = true
  erro.value = ''

  try {
    const dados =
      await saidaService.buscarItensDisponiveis(
        entrega.id,
      )

    entregaParaAdicionar.value = dados

    /*
     * Limpa quantidades anteriores.
     */
    for (const chave of Object.keys(
      quantidadesProgramadas,
    )) {
      delete quantidadesProgramadas[
        Number(chave)
      ]
    }

    /*
     * Começa tudo com zero.
     * O usuário escolhe exatamente quanto levar.
     */
    for (const item of dados.itens) {
      quantidadesProgramadas[
        item.entrega_item_id
      ] = 0
    }

    modalSelecionarItens.value = true
  } catch {
    erro.value =
      'Não foi possível carregar os produtos da entrega.'
  } finally {
    carregandoItens.value = false
  }
}

function fecharSelecaoItens() {
  modalSelecionarItens.value = false
  entregaParaAdicionar.value = null

  for (const chave of Object.keys(
    quantidadesProgramadas,
  )) {
    delete quantidadesProgramadas[
      Number(chave)
    ]
  }
}

function levarTudo() {
  if (!entregaParaAdicionar.value) {
    return
  }

  for (
    const item of entregaParaAdicionar.value.itens
  ) {
    quantidadesProgramadas[
      item.entrega_item_id
    ] = Number(item.quantidade_disponivel)
  }
}

function limparQuantidades() {
  if (!entregaParaAdicionar.value) {
    return
  }

  for (
    const item of entregaParaAdicionar.value.itens
  ) {
    quantidadesProgramadas[
      item.entrega_item_id
    ] = 0
  }
}

async function confirmarAdicionarEntrega() {
  if (
    !saidaSelecionada.value ||
    !entregaParaAdicionar.value
  ) {
    return
  }

  erro.value = ''

  const itens: ItemProgramacaoCarrada[] =
    entregaParaAdicionar.value.itens
      .map((item) => ({
        entrega_item_id:
          item.entrega_item_id,

        quantidade_programada:
          Number(
            quantidadesProgramadas[
              item.entrega_item_id
            ] ?? 0,
          ),
      }))
      .filter(
        (item) =>
          item.quantidade_programada > 0,
      )

  if (itens.length === 0) {
    erro.value =
      'Informe a quantidade de pelo menos um produto.'
    return
  }

  const possuiQuantidadeInvalida =
    entregaParaAdicionar.value.itens.some(
      (item) => {
        const quantidade = Number(
          quantidadesProgramadas[
            item.entrega_item_id
          ] ?? 0,
        )

        return (
          quantidade < 0 ||
          quantidade >
            Number(item.quantidade_disponivel)
        )
      },
    )

  if (possuiQuantidadeInvalida) {
    erro.value =
      'Existe produto com quantidade maior que o saldo disponível.'
    return
  }

  salvando.value = true

  try {
    const ordem =
      saidaSelecionada.value.entregas.length + 1

    saidaSelecionada.value =
      await saidaService.adicionarEntrega(
        saidaSelecionada.value.id,
        entregaParaAdicionar.value.entrega_id,
        ordem,
        itens,
      )

    fecharSelecaoItens()

    await Promise.all([
      carregarEntregasDisponiveis(),
      carregarPendenciasCarrada(),
    ])

    await carregarSaidas(
      paginaAtual.value,
    )
  } catch {
    erro.value =
      'Não foi possível adicionar os produtos à carrada.'
  } finally {
    salvando.value = false
  }
}

async function removerEntrega(
  entregaId: number,
) {
  if (!saidaSelecionada.value) {
    return
  }

  const confirmou = window.confirm(
    'Deseja remover esta entrega da carrada?',
  )

  if (!confirmou) {
    return
  }

  try {
    saidaSelecionada.value =
      await saidaService.removerEntrega(
        saidaSelecionada.value.id,
        entregaId,
      )

    await Promise.all([
      carregarEntregasDisponiveis(),
      carregarPendenciasCarrada(),
    ])

    await carregarSaidas(
      paginaAtual.value,
    )
  } catch {
    erro.value =
      'Não foi possível remover a entrega da carrada.'
  }
}

async function iniciarSaida() {
  if (!saidaSelecionada.value) {
    return
  }

  const confirmou = window.confirm(
    'Deseja iniciar esta saída?',
  )

  if (!confirmou) {
    return
  }

  try {
    saidaSelecionada.value =
      await saidaService.iniciar(
        saidaSelecionada.value.id,
      )

    await carregarSaidas(
      paginaAtual.value,
    )
  } catch {
    erro.value =
      'Não foi possível iniciar a saída.'
  }
}

async function finalizarSaida() {
  if (!saidaSelecionada.value) {
    return
  }

  const confirmou = window.confirm(
    'Deseja finalizar esta saída?',
  )

  if (!confirmou) {
    return
  }

  try {
    saidaSelecionada.value =
      await saidaService.finalizar(
        saidaSelecionada.value.id,
      )

    await carregarSaidas(
      paginaAtual.value,
    )
  } catch {
    erro.value =
      'Não foi possível finalizar a saída.'
  }
}

async function cancelarSaida(
  saida: Saida,
) {
  const confirmou = window.confirm(
    `Deseja cancelar a carrada #${saida.id}?`,
  )

  if (!confirmou) {
    return
  }

  try {
    await saidaService.cancelar(saida.id)

    await carregarSaidas(
      paginaAtual.value,
    )
  } catch {
    erro.value =
      'Não foi possível cancelar a saída.'
  }
}

async function abrirHistorico(
  saida: Saida,
) {
  erro.value = ''
  carregandoHistorico.value = true
  historicoCarrada.value = null
  modalHistorico.value = true

  try {
    historicoCarrada.value =
      await saidaService.buscarHistorico(
        saida.id,
      )
  } catch {
    modalHistorico.value = false
    erro.value =
      'Não foi possível carregar o histórico da carrada.'
  } finally {
    carregandoHistorico.value = false
  }
}

function fecharHistorico() {
  modalHistorico.value = false
  historicoCarrada.value = null
}

function formatarQuantidadeHistorico(
  valor: number,
) {
  return new Intl.NumberFormat(
    'pt-BR',
    {
      maximumFractionDigits: 3,
    },
  ).format(Number(valor))
}

function formatarDataHoraHistorico(
  data: string | null,
) {
  if (!data) {
    return '—'
  }

  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat(
    'pt-BR',
    {
      dateStyle: 'short',
      timeStyle: 'short',
    },
  ).format(valor)
}

function formatarDataHistorico(
  data: string | null,
) {
  if (!data) {
    return '—'
  }

  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat(
    'pt-BR',
    {
      dateStyle: 'short',
    },
  ).format(valor)
}

function tituloEventoCarrada(
  tipo: string,
) {
  const titulos: Record<string, string> = {
    SAIDA_CRIADA:
      'Carrada criada',

    ENTREGA_ADICIONADA:
      'Pedido adicionado',

    ENTREGA_ATUALIZADA:
      'Programação atualizada',

    ENTREGA_REMOVIDA:
      'Pedido removido',

    PENDENCIA_ADICIONADA:
      'Pendência adicionada',

    PENDENCIA_REMOVIDA:
      'Pendência removida',

    SAIDA_INICIADA:
      'Carrada iniciada',

    RESULTADO_ENTREGA_REGISTRADO:
      'Resultado de entrega',

    RESULTADO_REENTREGA_REGISTRADO:
      'Resultado da reentrega',

    VEICULO_TROCADO:
      'Veículo alterado',

    SAIDA_FINALIZADA:
      'Carrada finalizada',

    SAIDA_CANCELADA:
      'Carrada cancelada',
  }

  return titulos[tipo] ?? tipo
}

function resultadoHistoricoLabel(
  resultado: string | null,
) {
  if (!resultado) {
    return 'Aguardando resultado'
  }

  const labels: Record<string, string> = {
    COMPLETA: 'Completa',
    PARCIAL: 'Parcial',
    NAO_ENTREGUE: 'Não entregue',
  }

  return labels[resultado] ?? resultado
}

function statusPendenciaHistoricoLabel(
  status: string,
) {
  const labels: Record<string, string> = {
    ABERTA: 'Aberta',
    PROGRAMADA: 'Programada',
    RESOLVIDA: 'Resolvida',
    CANCELADA: 'Cancelada',
  }

  return labels[status] ?? status
}

function motivoDevolucaoLabel(
  motivo: string | null,
) {
  if (!motivo) {
    return '—'
  }

  const labels: Record<string, string> = {
    CLIENTE_RECUSOU: 'Cliente recusou',
    CLIENTE_DESISTIU: 'Cliente desistiu',
    PRODUTO_INCORRETO: 'Produto incorreto',
    PRODUTO_AVARIADO: 'Produto avariado',
    QUANTIDADE_EXCEDENTE: 'Quantidade excedente',
    OUTRO: 'Outro',
  }

  return labels[motivo] ?? motivo
}

function filtrar() {
  carregarSaidas(1)
}

function limparFiltros() {
  filtros.busca = ''
  filtros.status = ''
  filtros.data = ''

  carregarSaidas(1)
}

function statusLabel(
  status: StatusSaida,
) {
  const labels: Record<
    StatusSaida,
    string
  > = {
    PLANEJADA: 'Planejada',
    EM_ANDAMENTO: 'Em andamento',
    FINALIZADA: 'Finalizada',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function formatarData(data: string) {
  const [ano, mes, dia] = data.split('-')

  return `${dia}/${mes}/${ano}`
}

onMounted(async () => {
  await carregarDadosAuxiliares()
  await carregarSaidas()
})
</script>

  <template>
    <main class="pagina">
    <header class="cabecalho">
      <div>
        <h1>Saídas / Carradas</h1>

        <p>
          Organize as entregas que irão juntas
          em cada viagem.
        </p>
      </div>

      <button
        class="botao primario"
        type="button"
        @click="novaSaida"
      >
        + Nova carrada
      </button>
    </header>

    <section class="card filtros">
      <form
        class="grid-filtros"
        @submit.prevent="filtrar"
      >
        <input
          v-model="filtros.busca"
          type="text"
          placeholder="Buscar carrada: #12 ou 12"
        />

        <input
          v-model="filtros.data"
          type="date"
        />

        <select v-model="filtros.status">
          <option value="">
            Todos os status
          </option>

          <option value="PLANEJADA">
            Planejada
          </option>

          <option value="EM_ANDAMENTO">
            Em andamento
          </option>

          <option value="FINALIZADA">
            Finalizada
          </option>

          <option value="CANCELADA">
            Cancelada
          </option>
        </select>

        <button
          class="botao primario"
          type="submit"
        >
          Filtrar
        </button>

        <button
          class="botao secundario"
          type="button"
          @click="limparFiltros"
        >
          Limpar
        </button>
      </form>
    </section>

    <p
      v-if="erro"
      class="erro"
    >
      {{ erro }}
    </p>

    <section class="card">
      <div class="topo-tabela">
        <strong>
          {{ total }} carrada(s)
        </strong>
      </div>

      <div
        v-if="carregando"
        class="estado"
      >
        Carregando...
      </div>

      <div
        v-else-if="saidas.length === 0"
        class="estado"
      >
        Nenhuma carrada encontrada.
      </div>

      <div
        v-else
        class="tabela-container"
      >
        <table>
          <thead>
            <tr>
              <th>Carrada</th>
              <th>Data</th>
              <th>Região</th>
              <th>Rota</th>
              <th>Veículo</th>
              <th>Entregas</th>
              <th>Status</th>
              <th class="acoes-coluna">
                Ações
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="saida in saidas"
              :key="saida.id"
              class="linha-carrada"
              title="Clique para ver o histórico da carrada"
              @click="abrirHistorico(saida)"
            >
              <td>
                <strong>
                  #{{ saida.id }}
                </strong>
              </td>

              <td>
                {{
                  formatarData(
                    saida.data_saida,
                  )
                }}
              </td>

              <td>
                {{
                  saida.regiao_principal
                    ?.nome ??
                  'Sem região principal'
                }}
              </td>

              <td class="rota-tabela">
                {{
                  rotaResumo(
                    saida.rota,
                  )
                }}
              </td>

              <td>
                <template
                  v-if="saida.veiculo"
                >
                  <strong>
                    {{ saida.veiculo.nome }}
                  </strong>

                  <small>
                    {{
                      saida.veiculo.placa ||
                      ''
                    }}
                  </small>
                </template>

                <span v-else>
                  —
                </span>
              </td>

              <td>
                {{ saida.total_entregas }}
              </td>

              <td>
                <span
                  class="status"
                  :class="
                    saida.status.toLowerCase()
                  "
                >
                  {{
                    statusLabel(
                      saida.status,
                    )
                  }}
                </span>
              </td>

              <td class="acoes">
                <button
                  class="acao"
                  type="button"
                  @click.stop="
                    abrirCarrada(saida)
                  "
                >
                  Abrir
                </button>

                <button
                  v-if="
                    saida.status ===
                    'PLANEJADA'
                  "
                  class="acao perigo"
                  type="button"
                  @click.stop="
                    cancelarSaida(saida)
                  "
                >
                  Cancelar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="ultimaPagina > 1"
        class="paginacao"
      >
        <button
          class="botao secundario"
          :disabled="
            paginaAtual <= 1
          "
          @click="
            carregarSaidas(
              paginaAtual - 1,
            )
          "
        >
          Anterior
        </button>

        <span>
          Página {{ paginaAtual }}
          de {{ ultimaPagina }}
        </span>

        <button
          class="botao secundario"
          :disabled="
            paginaAtual >=
            ultimaPagina
          "
          @click="
            carregarSaidas(
              paginaAtual + 1,
            )
          "
        >
          Próxima
        </button>
      </footer>
    </section>


    <!-- HISTÓRICO DA CARRADA -->

    <div
      v-if="modalHistorico"
      class="modal-fundo historico-carrada-fundo"
      @click.self="fecharHistorico"
    >
      <section class="modal historico-carrada-modal">
        <header class="modal-cabecalho">
          <div>
            <h2>
              Histórico da carrada
              <template v-if="historicoCarrada">
                #{{ historicoCarrada.saida.id }}
              </template>
            </h2>

            <p v-if="historicoCarrada">
              {{
                formatarData(
                  historicoCarrada.saida.data_saida,
                )
              }}
              ·
              {{
                historicoCarrada.saida.regiao?.nome ??
                'Sem região'
              }}
              ·
              {{
                historicoCarrada.saida.veiculo?.nome ??
                'Sem veículo'
              }}
            </p>

            <p v-else>
              Carregando informações...
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharHistorico"
          >
            ×
          </button>
        </header>

        <div
          v-if="carregandoHistorico"
          class="estado historico-carrada-carregando"
        >
          Carregando histórico...
        </div>

        <template v-else-if="historicoCarrada">
          <div class="historico-carrada-identificacao">
            <div>
              <small>Status</small>

              <strong>
                {{
                  statusLabel(
                    historicoCarrada.saida.status,
                  )
                }}
              </strong>
            </div>

            <div>
              <small>Região</small>

              <strong>
                {{
                  historicoCarrada.saida.regiao?.nome ??
                  'Sem região'
                }}
              </strong>
            </div>

            <div>
              <small>Veículo</small>

              <strong>
                {{
                  historicoCarrada.saida.veiculo?.nome ??
                  'Sem veículo'
                }}
              </strong>

              <span
                v-if="
                  historicoCarrada.saida.veiculo?.placa
                "
              >
                {{
                  historicoCarrada.saida.veiculo.placa
                }}
              </span>
            </div>

            <div>
              <small>Criada em</small>

              <strong>
                {{
                  formatarDataHoraHistorico(
                    historicoCarrada.saida.criada_em,
                  )
                }}
              </strong>
            </div>
          </div>

          <section
            v-if="
              historicoCarrada.saida.rota.length > 0
            "
            class="historico-carrada-secao historico-rota"
          >
            <div class="historico-carrada-titulo">
              <div>
                <h3>Rota planejada</h3>

                <p>
                  Caminho definido quando a carrada
                  foi criada.
                </p>
              </div>
            </div>

            <div class="rota-visual">
              <template
                v-for="
                  (parada, index) in
                  historicoCarrada.saida.rota
                "
                :key="`${parada.nome}-${index}`"
              >
                <span class="rota-chip">
                  <b>{{ index + 1 }}</b>
                  {{ parada.nome }}
                </span>

                <span
                  v-if="
                    index <
                    historicoCarrada.saida.rota.length - 1
                  "
                  class="rota-seta"
                >
                  →
                </span>
              </template>
            </div>
          </section>

          <section class="historico-carrada-secao">
            <div class="historico-carrada-titulo">
              <div>
                <h3>Resumo da carrada</h3>

                <p>
                  Resultado das entregas realizadas
                  especificamente nesta viagem.
                </p>
              </div>
            </div>

            <div class="historico-carrada-resumo">
              <div class="resumo-carrada-card">
                <small>Entregas normais</small>
                <strong>
                  {{
                    historicoCarrada.resumo
                      .total_entregas_normais
                  }}
                </strong>
              </div>

              <div class="resumo-carrada-card reentrega">
                <small>Reentregas</small>
                <strong>
                  {{
                    historicoCarrada.resumo
                      .total_reentregas
                  }}
                </strong>
              </div>

              <div class="resumo-carrada-card">
                <small>Atendimentos</small>
                <strong>
                  {{
                    historicoCarrada.resumo
                      .total_atendimentos
                  }}
                </strong>
              </div>

              <div class="resumo-carrada-card sucesso">
                <small>Completas normais</small>
                <strong>
                  {{ historicoCarrada.resumo.completas }}
                </strong>
              </div>

              <div class="resumo-carrada-card alerta">
                <small>Parciais normais</small>
                <strong>
                  {{ historicoCarrada.resumo.parciais }}
                </strong>
              </div>

              <div class="resumo-carrada-card perigo">
                <small>Não entregues</small>
                <strong>
                  {{
                    historicoCarrada.resumo
                      .nao_entregues
                  }}
                </strong>
              </div>

              <div class="resumo-carrada-card">
                <small>Sem resultado</small>
                <strong>
                  {{
                    historicoCarrada.resumo
                      .sem_resultado
                  }}
                </strong>
              </div>

              <div class="resumo-carrada-card pendencia">
                <small>Pendências geradas</small>
                <strong>
                  {{
                    formatarQuantidadeHistorico(
                      historicoCarrada.resumo
                        .quantidade_pendencias_geradas,
                    )
                  }}
                </strong>
              </div>

              <div class="resumo-carrada-card devolucao">
                <small>Devolvidos</small>
                <strong>
                  {{
                    formatarQuantidadeHistorico(
                      historicoCarrada.resumo
                        .quantidade_devolvida,
                    )
                  }}
                </strong>
              </div>
            </div>

            <p
              v-if="historicoCarrada.saida.observacao"
              class="historico-carrada-observacao"
            >
              <strong>Observação da carrada:</strong>
              {{ historicoCarrada.saida.observacao }}
            </p>
          </section>

          <section class="historico-carrada-secao">
            <div class="historico-carrada-titulo">
              <div>
                <h3>Entregas normais desta carrada</h3>

                <p>
                  O histórico abaixo não muda caso uma
                  pendência seja resolvida em outra carrada.
                </p>
              </div>
            </div>

            <div
              v-if="
                historicoCarrada.entregas.length === 0
              "
              class="historico-carrada-vazio"
            >
              <template
                v-if="historicoCarrada.reentregas.length > 0"
              >
                Esta carrada não possui entregas normais.
                As reentregas estão listadas abaixo.
              </template>

              <template v-else>
                Esta carrada não possui entregas nem reentregas.
              </template>
            </div>

            <article
              v-for="
                entrega in historicoCarrada.entregas
              "
              :key="entrega.saida_entrega_id"
              class="historico-entrega-carrada"
            >
              <header class="historico-entrega-topo">
                <div class="historico-entrega-identidade">
                  <span
                    v-if="entrega.ordem"
                    class="historico-ordem"
                  >
                    {{ entrega.ordem }}
                  </span>

                  <div>
                    <strong>
                      Pedido
                      {{
                        entrega.numero_pedido
                          ? `#${entrega.numero_pedido}`
                          : `#${entrega.id}`
                      }}
                    </strong>

                    <span>
                      {{ entrega.cliente_nome }}
                    </span>

                    <small>
                      {{ entrega.endereco }}
                    </small>
                  </div>
                </div>

                <div class="historico-entrega-resultado">
                  <span
                    class="resultado-carrada"
                    :class="
                      entrega.resultado
                        ? entrega.resultado.toLowerCase()
                        : 'aguardando'
                    "
                  >
                    {{
                      resultadoHistoricoLabel(
                        entrega.resultado,
                      )
                    }}
                  </span>

                  <small
                    v-if="entrega.data_hora_resultado"
                  >
                    {{
                      formatarDataHoraHistorico(
                        entrega.data_hora_resultado,
                      )
                    }}
                  </small>
                </div>
              </header>

              <div class="historico-produtos-carrada">
                <div class="historico-produtos-cabecalho">
                  <span>Produto</span>
                  <span>Carregado</span>
                  <span>Entregue</span>
                  <span>Reentrega</span>
                  <span>Devolvido</span>
                  <span>Não entregue</span>
                </div>

                <div
                  v-for="item in entrega.itens"
                  :key="item.entrega_item_id"
                  class="historico-produto-linha"
                >
                  <div>
                    <strong>
                      {{ item.descricao_produto }}
                    </strong>

                    <small
                      v-if="item.codigo_produto"
                    >
                      Cód. {{ item.codigo_produto }}
                    </small>
                  </div>

                  <span>
                    {{
                      formatarQuantidadeHistorico(
                        item.carregado,
                      )
                    }}
                    {{ item.unidade || '' }}
                  </span>

                  <strong class="historico-qtd-entregue">
                    {{
                      formatarQuantidadeHistorico(
                        item.entregue,
                      )
                    }}
                    {{ item.unidade || '' }}
                  </strong>

                  <strong class="historico-qtd-reentrega">
                    {{
                      formatarQuantidadeHistorico(
                        item.reentrega,
                      )
                    }}
                    {{ item.unidade || '' }}
                  </strong>

                  <strong class="historico-qtd-devolvida">
                    {{
                      formatarQuantidadeHistorico(
                        item.devolvido,
                      )
                    }}
                    {{ item.unidade || '' }}
                  </strong>

                  <strong
                    :class="{
                      'historico-qtd-faltante':
                        item.nao_entregue > 0,
                    }"
                  >
                    {{
                      formatarQuantidadeHistorico(
                        item.nao_entregue,
                      )
                    }}
                    {{ item.unidade || '' }}
                  </strong>
                </div>
              </div>

              <div
                v-if="
                  entrega.motivo_nao_entrega ||
                  entrega.observacao_resultado
                "
                class="historico-resultado-observacoes"
              >
                <p
                  v-if="entrega.motivo_nao_entrega"
                >
                  <strong>Motivo:</strong>
                  {{ entrega.motivo_nao_entrega }}
                </p>

                <p
                  v-if="entrega.observacao_resultado"
                >
                  <strong>Observação:</strong>
                  {{ entrega.observacao_resultado }}
                </p>
              </div>

              <div
                v-if="
                  entrega.pendencias_geradas.length > 0
                "
                class="historico-pendencias-geradas"
              >
                <h4>
                  Pendências geradas nesta carrada
                </h4>

                <div
                  v-for="
                    pendencia in
                    entrega.pendencias_geradas
                  "
                  :key="pendencia.id"
                  class="historico-pendencia-carrada"
                >
                  <div class="historico-pendencia-produto">
                    <strong>
                      {{ pendencia.descricao_produto }}
                    </strong>

                    <small
                      v-if="pendencia.codigo_produto"
                    >
                      Cód. {{ pendencia.codigo_produto }}
                    </small>

                    <small
                      v-if="pendencia.data_abertura"
                    >
                      Criada em
                      {{
                        formatarDataHistorico(
                          pendencia.data_abertura,
                        )
                      }}
                    </small>

                    <small
                      v-if="pendencia.data_resolucao"
                    >
                      Resolvida em
                      {{
                        formatarDataHistorico(
                          pendencia.data_resolucao,
                        )
                      }}
                    </small>
                  </div>

                  <div class="historico-pendencia-numeros">
                    <span>
                      Gerada:
                      <strong>
                        {{
                          formatarQuantidadeHistorico(
                            pendencia.quantidade_original,
                          )
                        }}
                        {{ pendencia.unidade || '' }}
                      </strong>
                    </span>

                    <span>
                      Resolvida:
                      <strong>
                        {{
                          formatarQuantidadeHistorico(
                            pendencia.quantidade_resolvida,
                          )
                        }}
                        {{ pendencia.unidade || '' }}
                      </strong>
                    </span>

                    <span>
                      Saldo atual:
                      <strong>
                        {{
                          formatarQuantidadeHistorico(
                            pendencia.saldo_atual,
                          )
                        }}
                        {{ pendencia.unidade || '' }}
                      </strong>
                    </span>
                  </div>

                  <span
                    class="status-pendencia-carrada"
                    :class="
                      pendencia.status_atual
                        .toLowerCase()
                    "
                  >
                    {{
                      statusPendenciaHistoricoLabel(
                        pendencia.status_atual,
                      )
                    }}
                  </span>

                  <p
                    v-if="
                      pendencia.motivo ||
                      pendencia.observacao
                    "
                    class="historico-pendencia-texto"
                  >
                    {{
                      pendencia.motivo ||
                      pendencia.observacao
                    }}
                  </p>
                </div>
              </div>
            </article>
          </section>

          <section
            v-if="historicoCarrada.reentregas.length > 0"
            class="historico-carrada-secao historico-reentregas-secao"
          >
            <div class="historico-carrada-titulo">
              <div>
                <h3>Reentregas desta carrada</h3>

                <p>
                  Pendências de carradas anteriores que foram
                  programadas para esta viagem.
                </p>
              </div>
            </div>

            <article
              v-for="reentrega in historicoCarrada.reentregas"
              :key="reentrega.pendencia_id"
              class="historico-reentrega-card"
            >
              <header class="historico-reentrega-topo">
                <div>
                  <span class="reentrega-badge">
                    Reentrega
                  </span>

                  <strong>
                    Pedido
                    {{
                      reentrega.numero_pedido
                        ? `#${reentrega.numero_pedido}`
                        : `#${reentrega.entrega_id}`
                    }}
                  </strong>

                  <span>{{ reentrega.cliente_nome }}</span>

                  <small>
                    {{ reentrega.regiao_nome }}
                    ·
                    {{ reentrega.endereco }}
                  </small>
                </div>

                <div class="historico-entrega-resultado">
                  <span
                    class="resultado-carrada"
                    :class="
                      reentrega.resultado_registrado
                        ? 'completa'
                        : 'aguardando'
                    "
                  >
                    {{
                      reentrega.resultado_registrado
                        ? 'Retorno registrado'
                        : 'Sem resultado'
                    }}
                  </span>

                  <small
                    v-if="reentrega.data_hora_resultado"
                  >
                    {{
                      formatarDataHoraHistorico(
                        reentrega.data_hora_resultado,
                      )
                    }}
                  </small>
                </div>
              </header>

              <div class="historico-reentrega-produto">
                <div class="historico-reentrega-produto-nome">
                  <small>Produto</small>
                  <strong>
                    {{ reentrega.descricao_produto }}
                  </strong>
                  <span v-if="reentrega.codigo_produto">
                    Cód. {{ reentrega.codigo_produto }}
                  </span>
                </div>

                <div>
                  <small>Programado</small>
                  <strong>
                    {{
                      formatarQuantidadeHistorico(
                        reentrega.quantidade_programada,
                      )
                    }}
                    {{ reentrega.unidade || '' }}
                  </strong>
                </div>

                <div>
                  <small>Entregue</small>
                  <strong class="historico-qtd-entregue">
                    {{
                      formatarQuantidadeHistorico(
                        reentrega.quantidade_entregue,
                      )
                    }}
                    {{ reentrega.unidade || '' }}
                  </strong>
                </div>

                <div>
                  <small>Devolvido</small>
                  <strong class="historico-qtd-devolvida">
                    {{
                      formatarQuantidadeHistorico(
                        reentrega.quantidade_devolvida,
                      )
                    }}
                    {{ reentrega.unidade || '' }}
                  </strong>
                </div>

                <div>
                  <small>Nova reentrega</small>
                  <strong class="historico-qtd-reentrega">
                    {{
                      formatarQuantidadeHistorico(
                        reentrega.quantidade_reentrega,
                      )
                    }}
                    {{ reentrega.unidade || '' }}
                  </strong>
                </div>
              </div>

              <div
                v-if="
                  reentrega.motivo_devolucao ||
                  reentrega.observacao_resultado ||
                  reentrega.adicionada_em
                "
                class="historico-resultado-observacoes"
              >
                <p v-if="reentrega.adicionada_em">
                  <strong>Adicionada à carrada:</strong>
                  {{
                    formatarDataHoraHistorico(
                      reentrega.adicionada_em,
                    )
                  }}
                </p>

                <p v-if="reentrega.motivo_devolucao">
                  <strong>Motivo da devolução:</strong>
                  {{
                    motivoDevolucaoLabel(
                      reentrega.motivo_devolucao,
                    )
                  }}
                </p>

                <p v-if="reentrega.observacao_resultado">
                  <strong>Observação do retorno:</strong>
                  {{ reentrega.observacao_resultado }}
                </p>
              </div>
            </article>
          </section>

          <section
            v-if="
              eventosHistoricoCarrada.length > 0
            "
            class="historico-carrada-secao"
          >
            <div class="historico-carrada-titulo">
              <div>
                <h3>Linha do tempo</h3>

                <p>
                  Eventos reais registrados durante
                  a operação desta carrada.
                </p>
              </div>
            </div>

            <div class="historico-timeline">
              <div
                v-for="
                  evento in
                  eventosHistoricoCarrada
                "
                :key="evento.id"
                class="historico-timeline-item"
              >
                <div class="historico-timeline-marcador"></div>

                <div>
                  <small>
                    {{
                      formatarDataHoraHistorico(
                        evento.data,
                      )
                    }}
                  </small>

                  <strong>
                    {{ evento.titulo }}
                  </strong>

                  <span>
                    {{ evento.descricao }}
                  </span>

                  <small
                    v-if="
                      evento.origem !==
                      'TEMPO_REAL'
                    "
                    class="evento-recuperado"
                  >
                    Histórico recuperado de
                    registros antigos
                  </small>
                </div>
              </div>
            </div>
          </section>

          <footer class="modal-acoes">
            <button
              class="botao secundario"
              type="button"
              @click="fecharHistorico"
            >
              Fechar
            </button>
          </footer>
        </template>
      </section>
    </div>

    <!-- NOVA CARRADA -->

    <div
      v-if="modalNovaSaida"
      class="modal-fundo"
      @click.self="fecharNovaSaida"
    >
      <section class="modal medio">
        <header class="modal-cabecalho">
          <div>
            <h2>
              Nova carrada
            </h2>

            <p>
              Defina os dados básicos
              da saída.
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharNovaSaida"
          >
            ×
          </button>
        </header>

        <form
          @submit.prevent="criarSaida"
        >
          <div class="campo">
            <label>
              Data *
            </label>

            <input
              v-model="
                formulario.data_saida
              "
              type="date"
              required
            />
          </div>

          <div class="campo">
            <label>
              Região principal
            </label>

            <select
              v-model="
                formulario.regiao_principal_id
              "
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
            <label>
              Rota da carrada
            </label>

            <p class="ajuda-rota">
              Monte as paradas na ordem em que
              o veículo deverá passar.
            </p>

            <div class="rota-adicionar">
              <select
                v-model="
                  paradaRegiaoSelecionada
                "
              >
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
                @keydown.enter.prevent="
                  adicionarParadaLivre
                "
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
                v-for="
                  (parada, index) in
                  formulario.rota
                "
                :key="`${parada.nome}-${index}`"
                class="rota-parada"
              >
                <span class="rota-numero">
                  {{ index + 1 }}
                </span>

                <strong>
                  {{ parada.nome }}
                </strong>

                <div class="rota-acoes">
                  <button
                    type="button"
                    title="Mover para cima"
                    :disabled="index === 0"
                    @click="
                      moverParadaRota(
                        index,
                        -1,
                      )
                    "
                  >
                    ↑
                  </button>

                  <button
                    type="button"
                    title="Mover para baixo"
                    :disabled="
                      index ===
                      formulario.rota.length - 1
                    "
                    @click="
                      moverParadaRota(
                        index,
                        1,
                      )
                    "
                  >
                    ↓
                  </button>

                  <button
                    class="remover-rota"
                    type="button"
                    @click="
                      removerParadaRota(
                        index,
                      )
                    "
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
          </div>

          <div class="campo">
            <label>
              Veículo
            </label>

            <select
              v-model="
                formulario.veiculo_id
              "
            >
              <option :value="null">
                Selecione...
              </option>

              <option
                v-for="
                  veiculo in
                  veiculosDisponiveis
                "
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
              v-if="
                veiculosDisponiveis.length ===
                0
              "
              class="ajuda"
            >
              Nenhum veículo disponível
              cadastrado.
            </small>
          </div>

          <div class="campo">
            <label>
              Observação
            </label>

            <textarea
              v-model="
                formulario.observacao
              "
              rows="3"
            ></textarea>
          </div>

          <footer
            class="modal-acoes"
          >
            <button
              class="botao secundario"
              type="button"
              @click="
                fecharNovaSaida
              "
            >
              Cancelar
            </button>

            <button
              class="botao primario"
              type="submit"
              :disabled="salvando"
            >
              {{
                salvando
                  ? 'Criando...'
                  : 'Criar carrada'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>

    <!-- DETALHES DA CARRADA -->

    <div
      v-if="
        modalCarrada &&
        saidaSelecionada
      "
      class="modal-fundo"
      @click.self="fecharCarrada"
    >
      <section class="modal grande">
        <header
          class="modal-cabecalho"
        >
          <div>
            <h2>
              Carrada
              #{{ saidaSelecionada.id }}
            </h2>

            <p>
              {{
                formatarData(
                  saidaSelecionada
                    .data_saida,
                )
              }}
              ·
              {{
                saidaSelecionada
                  .regiao_principal
                  ?.nome ??
                'Sem região definida'
              }}
              ·
              {{
                saidaSelecionada
                  .veiculo?.nome ??
                'Sem veículo'
              }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharCarrada"
          >
            ×
          </button>
        </header>

        <div class="resumo">
          <div>
            <small>Status</small>

            <strong>
              {{
                statusLabel(
                  saidaSelecionada.status,
                )
              }}
            </strong>
          </div>

          <div>
            <small>Entregas</small>

            <strong>
              {{
                saidaSelecionada
                  .entregas.length
              }}
            </strong>
          </div>

          <div>
            <small>Veículo</small>

            <strong>
              {{
                saidaSelecionada
                  .veiculo?.nome ??
                '—'
              }}
            </strong>
          </div>
        </div>

        <section
          v-if="
            saidaSelecionada.rota.length > 0
          "
          class="bloco rota-detalhes"
        >
          <div class="titulo-bloco">
            <div>
              <h3>Rota da carrada</h3>

              <p>
                Ordem planejada das paradas.
              </p>
            </div>
          </div>

          <div class="rota-visual">
            <template
              v-for="
                (parada, index) in
                saidaSelecionada.rota
              "
              :key="`${parada.nome}-${index}`"
            >
              <span class="rota-chip">
                <b>{{ index + 1 }}</b>
                {{ parada.nome }}
              </span>

              <span
                v-if="
                  index <
                  saidaSelecionada.rota.length - 1
                "
                class="rota-seta"
              >
                →
              </span>
            </template>
          </div>
        </section>

        <!-- ENTREGAS JÁ NA CARRADA -->

        <section class="bloco">
          <div
            class="titulo-bloco"
          >
            <div>
              <h3>
                Entregas da carrada
              </h3>

              <p>
                Produtos e quantidades
                planejadas nesta saída.
              </p>
            </div>
          </div>

          <div
            v-if="
              saidaSelecionada
                .entregas.length === 0
            "
            class="estado pequeno-estado"
          >
            Nenhuma entrega adicionada.
          </div>

          <div
            v-for="
              entrega in
              saidaSelecionada.entregas
            "
            :key="entrega.id"
            class="entrega-card"
          >
            <div class="ordem">
              {{
                entrega.ordem ?? '—'
              }}
            </div>

            <div class="entrega-info">
              <strong>
                Pedido
                {{
                  entrega.numero_pedido ??
                  `#${entrega.id}`
                }}
              </strong>

              <span>
                {{
                  entrega.cliente_nome
                }}
              </span>

              <small>
                {{ entrega.regiao_nome }}
                ·
                {{ entrega.endereco }}
              </small>
            </div>

            <div
              class="itens-carrada"
            >
              <div
                v-for="
                  item in
                  entrega.itens_programados
                "
                :key="
                  item.entrega_item_id
                "
                class="item-carrada"
              >
                <span>
                  {{
                    item.descricao_produto
                  }}
                </span>

                <strong>
                  {{
                    item.quantidade_programada
                  }}
                  {{
                    item.unidade || ''
                  }}
                </strong>
              </div>
            </div>

            <button
              v-if="
                saidaSelecionada.status ===
                  'EM_ANDAMENTO' &&
                !entrega.resultado_registrado
              "
              class="botao retorno"
              type="button"
              @click="abrirRetorno(entrega)"
            >
              Registrar retorno
            </button>

            <span
              v-if="
                saidaSelecionada.status ===
                  'EM_ANDAMENTO' &&
                entrega.resultado_registrado
              "
              class="retorno-registrado"
            >
              ✓ Retorno registrado
            </span>

            <button
              v-if="
                saidaSelecionada.status ===
                'PLANEJADA'
              "
              class="acao perigo"
              type="button"
              @click="
                removerEntrega(
                  entrega.id,
                )
              "
            >
              Remover
            </button>
          </div>
        </section>

        <!-- PENDÊNCIAS / REENTREGAS JÁ NA CARRADA -->

        <section
          v-if="pendenciasNaCarrada.length > 0"
          class="bloco pendencias-carrada"
        >
          <div class="titulo-bloco">
            <div>
              <h3>
                Pendências / reentregas da carrada
              </h3>

              <p>
                Produtos que ficaram faltando em tentativas
                anteriores e foram programados para esta saída.
              </p>
            </div>
          </div>

          <div
            v-for="pendencia in pendenciasNaCarrada"
            :key="pendencia.id"
            class="pendencia-operacional-card"
          >
            <div class="pendencia-operacional-info">
              <div class="pendencia-operacional-topo">
                <span class="reentrega-badge">
                  Reentrega
                </span>

                <strong>
                  Pedido
                  {{
                    pendencia.numero_pedido ??
                    `#${pendencia.entrega_id}`
                  }}
                </strong>
              </div>

              <span>
                {{ pendencia.cliente_nome }}
              </span>

              <small>
                {{ pendencia.regiao_nome }}
                ·
                {{ pendencia.endereco }}
              </small>

              <div class="pendencia-produto-operacional">
                <div>
                  <strong>
                    {{ pendencia.descricao_produto }}
                  </strong>

                  <small v-if="pendencia.codigo_produto">
                    Cód. {{ pendencia.codigo_produto }}
                  </small>
                </div>

                <div class="pendencia-saldo-operacional">
                  <small>Pendente</small>
                  <strong>
                    {{ pendencia.saldo_pendente }}
                    {{ pendencia.unidade || '' }}
                  </strong>
                </div>
              </div>
            </div>

            <div class="acoes-reentrega-carrada">
              <button
                v-if="
                  saidaSelecionada.status ===
                  'PLANEJADA'
                "
                class="acao perigo"
                type="button"
                :disabled="
                  processandoPendenciaId ===
                  pendencia.id
                "
                @click="
                  removerPendenciaCarrada(
                    pendencia,
                  )
                "
              >
                Remover
              </button>

              <button
                v-if="
                  saidaSelecionada.status ===
                    'EM_ANDAMENTO' &&
                  !pendencia.resultado_registrado
                "
                class="botao retorno"
                type="button"
                @click="
                  abrirRetornoPendencia(
                    pendencia,
                  )
                "
              >
                Registrar retorno
              </button>

              <div
                v-if="pendencia.resultado_registrado"
                class="resultado-reentrega-card"
              >
                <strong>✓ Retorno registrado</strong>

                <small>
                  Entregue:
                  {{
                    pendencia.quantidade_entregue_resultado
                  }}
                  {{ pendencia.unidade || '' }}
                </small>

                <small
                  v-if="
                    pendencia.quantidade_devolvida_resultado > 0
                  "
                >
                  Devolvido:
                  {{
                    pendencia.quantidade_devolvida_resultado
                  }}
                  {{ pendencia.unidade || '' }}
                </small>

                <small
                  v-if="
                    pendencia.quantidade_reentrega_resultado > 0
                  "
                >
                  Nova reentrega:
                  {{
                    pendencia.quantidade_reentrega_resultado
                  }}
                  {{ pendencia.unidade || '' }}
                </small>
              </div>
            </div>
          </div>
        </section>

        <!-- PENDÊNCIAS DISPONÍVEIS NA ROTA -->

        <section
          v-if="
            saidaSelecionada.status ===
            'PLANEJADA'
          "
          class="bloco disponiveis pendencias-disponiveis"
        >
          <div class="titulo-bloco">
            <div>
              <h3>
                Pendências disponíveis na rota
              </h3>

              <p>
                Reentregas abertas das regiões cadastradas
                no percurso desta carrada.
              </p>
            </div>
          </div>

          <div
            v-if="pendenciasDisponiveis.length === 0"
            class="estado pequeno-estado"
          >
            Nenhuma pendência aberta para as regiões
            desta carrada.
          </div>

          <div
            v-for="pendencia in pendenciasDisponiveis"
            :key="pendencia.id"
            class="pendencia-operacional-card disponivel"
          >
            <div class="pendencia-operacional-info">
              <div class="pendencia-operacional-topo">
                <span class="reentrega-badge">
                  Reentrega
                </span>

                <strong>
                  Pedido
                  {{
                    pendencia.numero_pedido ??
                    `#${pendencia.entrega_id}`
                  }}
                </strong>
              </div>

              <span>
                {{ pendencia.cliente_nome }}
              </span>

              <small>
                {{ pendencia.regiao_nome }}
                ·
                {{ pendencia.endereco }}
              </small>

              <div class="pendencia-produto-operacional">
                <div>
                  <strong>
                    {{ pendencia.descricao_produto }}
                  </strong>

                  <small v-if="pendencia.codigo_produto">
                    Cód. {{ pendencia.codigo_produto }}
                  </small>
                </div>

                <div class="pendencia-saldo-operacional">
                  <small>Pendente</small>
                  <strong>
                    {{ pendencia.saldo_pendente }}
                    {{ pendencia.unidade || '' }}
                  </strong>
                </div>
              </div>

              <p
                v-if="pendencia.motivo || pendencia.observacao"
                class="pendencia-motivo-operacional"
              >
                {{ pendencia.motivo || pendencia.observacao }}
              </p>
            </div>

            <button
              class="botao secundario"
              type="button"
              :disabled="
                processandoPendenciaId ===
                pendencia.id
              "
              @click="
                adicionarPendenciaCarrada(
                  pendencia,
                )
              "
            >
              {{
                processandoPendenciaId ===
                pendencia.id
                  ? 'Adicionando...'
                  : 'Adicionar reentrega'
              }}
            </button>
          </div>
        </section>

        <!-- ENTREGAS DISPONÍVEIS -->

        <section
          v-if="
            saidaSelecionada.status ===
            'PLANEJADA'
          "
          class="bloco disponiveis"
        >
          <div
            class="titulo-bloco"
          >
            <div>
              <h3>
                Entregas normais disponíveis
              </h3>

              <p>
                Saldo normal ainda não enviado das regiões
                cadastradas na rota. Pendências aparecem
                separadamente acima.
              </p>
            </div>
          </div>

          <div
            v-if="
              entregasDisponiveis.length ===
              0
            "
            class="estado pequeno-estado"
          >
            Nenhuma entrega disponível
            para as regiões desta carrada.
          </div>

          <div
            v-for="
              entrega in
              entregasDisponiveis
            "
            :key="entrega.id"
            class="entrega-card"
          >
            <div class="entrega-info">
              <strong>
                Pedido
                {{
                  entrega.numero_pedido ??
                  `#${entrega.id}`
                }}
              </strong>

              <span>
                {{
                  entrega.cliente_nome
                }}
              </span>

              <small>
                {{ entrega.regiao_nome }}
                ·
                {{ entrega.endereco }}
              </small>
            </div>

            <button
              class="botao secundario"
              type="button"
              :disabled="
                carregandoItens
              "
              @click="
                abrirItensEntrega(
                  entrega,
                )
              "
            >
              Ver itens
            </button>
          </div>
        </section>

        <footer
          class="modal-acoes"
        >
          <button
            class="botao secundario"
            type="button"
            @click="fecharCarrada"
          >
            Fechar
          </button>

          <button
            v-if="
              saidaSelecionada.status ===
              'PLANEJADA'
            "
            class="botao iniciar"
            type="button"
            @click="iniciarSaida"
          >
            Iniciar saída
          </button>

          <button
            v-if="
              saidaSelecionada.status ===
              'EM_ANDAMENTO'
            "
            class="botao finalizar"
            type="button"
            @click="finalizarSaida"
          >
            Finalizar saída
          </button>
        </footer>
      </section>
    </div>

    <!-- SELEÇÃO DOS PRODUTOS -->

    <div
      v-if="
        modalSelecionarItens &&
        entregaParaAdicionar
      "
      class="modal-fundo itens-modal-fundo"
      @click.self="
        fecharSelecaoItens
      "
    >
      <section class="modal medio">
        <header
          class="modal-cabecalho"
        >
          <div>
            <h2>
              Produtos do pedido
              {{
                entregaParaAdicionar
                  .numero_pedido
                  ? `#${entregaParaAdicionar.numero_pedido}`
                  : ''
              }}
            </h2>

            <p>
              {{
                entregaParaAdicionar
                  .cliente_nome
              }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="
              fecharSelecaoItens
            "
          >
            ×
          </button>
        </header>

        <div
          class="endereco-entrega"
        >
          <small>
            Endereço
          </small>

          <strong>
            {{
              entregaParaAdicionar
                .endereco
            }}
          </strong>
        </div>

        <div
          class="acoes-quantidade"
        >
          <button
            class="botao secundario"
            type="button"
            @click="levarTudo"
          >
            Levar todo o saldo
          </button>

          <button
            class="botao secundario"
            type="button"
            @click="
              limparQuantidades
            "
          >
            Zerar quantidades
          </button>
        </div>

        <div class="tabela-itens">
          <div
            class="item-cabecalho"
          >
            <span>
              Produto
            </span>

            <span>
              Pedido
            </span>

            <span>
              Disponível
            </span>

            <span>
              Levar agora
            </span>
          </div>

          <div
            v-for="
              item in
              entregaParaAdicionar.itens
            "
            :key="
              item.entrega_item_id
            "
            class="item-selecao"
          >
            <div>
              <strong>
                {{
                  item.descricao_produto
                }}
              </strong>

              <small
                v-if="
                  item.codigo_produto
                "
              >
                Cód.
                {{
                  item.codigo_produto
                }}
              </small>
            </div>

            <div>
              {{
                item.quantidade_solicitada
              }}
              {{ item.unidade || '' }}
            </div>

            <div>
              <strong>
                {{
                  item.quantidade_disponivel
                }}
              </strong>

              {{ item.unidade || '' }}
            </div>

            <div>
              <input
                v-model.number="
                  quantidadesProgramadas[
                    item.entrega_item_id
                  ]
                "
                type="number"
                min="0"
                :max="
                  item.quantidade_disponivel
                "
                step="0.001"
              />
            </div>
          </div>
        </div>

        <p
          class="aviso-capacidade"
        >
          Confira os produtos e as
          quantidades para decidir se
          o veículo escolhido suporta
          esta carrada.
        </p>

        <footer
          class="modal-acoes"
        >
          <button
            class="botao secundario"
            type="button"
            @click="
              fecharSelecaoItens
            "
          >
            Cancelar
          </button>

          <button
            class="botao primario"
            type="button"
            :disabled="salvando"
            @click="
              confirmarAdicionarEntrega
            "
          >
            {{
              salvando
                ? 'Adicionando...'
                : 'Adicionar à carrada'
            }}
          </button>
        </footer>
      </section>
    </div>
  
    <!-- RETORNO DA ENTREGA NORMAL -->

    <div
      v-if="
        modalRetorno &&
        entregaRetorno
      "
      class="modal-fundo retorno-modal-fundo"
      @click.self="fecharRetorno"
    >
      <section class="modal grande retorno-modal">
        <header class="modal-cabecalho">
          <div>
            <h2>Registrar retorno</h2>

            <p>
              Pedido
              {{
                entregaRetorno.numero_pedido ??
                `#${entregaRetorno.id}`
              }}
              ·
              {{ entregaRetorno.cliente_nome }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharRetorno"
          >
            ×
          </button>
        </header>

        <div class="aviso-retorno">
          Informe quanto foi realmente entregue. Quando
          houver falta, escolha o destino de cada produto:
          <strong>Reentrega</strong> ou
          <strong>Devolvido</strong>.
        </div>

        <div class="tabela-itens">
          <div class="retorno-cabecalho">
            <span>Produto</span>
            <span>Carregado</span>
            <span>Entregue</span>
            <span>Faltou</span>
            <span>Destino</span>
          </div>

          <div
            v-for="item in entregaRetorno.itens_programados"
            :key="item.entrega_item_id"
            class="retorno-item"
          >
            <div>
              <strong>{{ item.descricao_produto }}</strong>

              <small v-if="item.codigo_produto">
                Cód. {{ item.codigo_produto }}
              </small>
            </div>

            <div>
              <strong>{{ item.quantidade_programada }}</strong>
              {{ item.unidade || '' }}
            </div>

            <div>
              <input
                v-if="itemRetorno(item.entrega_item_id)"
                v-model.number="
                  itemRetorno(
                    item.entrega_item_id,
                  )!.quantidade_entregue
                "
                type="number"
                min="0"
                :max="Number(item.quantidade_programada)"
                step="0.001"
                @input="
                  limparDestinoSemFalta(
                    item.quantidade_programada,
                    item.entrega_item_id,
                  )
                "
              />
            </div>

            <div>
              <strong
                :class="{
                  'tem-falta':
                    quantidadeFaltanteRetorno(
                      item.quantidade_programada,
                      item.entrega_item_id,
                    ) > 0,
                }"
              >
                {{
                  quantidadeFaltanteRetorno(
                    item.quantidade_programada,
                    item.entrega_item_id,
                  )
                }}
                {{ item.unidade || '' }}
              </strong>
            </div>

            <div class="destino-retorno">
              <template
                v-if="
                  quantidadeFaltanteRetorno(
                    item.quantidade_programada,
                    item.entrega_item_id,
                  ) > 0 &&
                  itemRetorno(item.entrega_item_id)
                "
              >
                <select
                  v-model="
                    itemRetorno(
                      item.entrega_item_id,
                    )!.destino_nao_entregue
                  "
                  @change="
                    alterarDestinoItemRetorno(
                      item.entrega_item_id,
                    )
                  "
                >
                  <option :value="null">
                    Selecione...
                  </option>
                  <option value="REENTREGA">
                    Reentrega
                  </option>
                  <option value="DEVOLVIDO">
                    Devolvido
                  </option>
                </select>

                <select
                  v-if="
                    itemRetorno(
                      item.entrega_item_id,
                    )?.destino_nao_entregue ===
                    'DEVOLVIDO'
                  "
                  v-model="
                    itemRetorno(
                      item.entrega_item_id,
                    )!.motivo_devolucao
                  "
                  class="motivo-devolucao-select"
                >
                  <option :value="null">
                    Motivo...
                  </option>
                  <option value="CLIENTE_RECUSOU">
                    Cliente recusou
                  </option>
                  <option value="CLIENTE_DESISTIU">
                    Cliente desistiu
                  </option>
                  <option value="PRODUTO_INCORRETO">
                    Produto incorreto
                  </option>
                  <option value="PRODUTO_AVARIADO">
                    Produto avariado
                  </option>
                  <option value="QUANTIDADE_EXCEDENTE">
                    Quantidade excedente
                  </option>
                  <option value="OUTRO">
                    Outro
                  </option>
                </select>
              </template>

              <span v-else>—</span>
            </div>
          </div>
        </div>

        <div class="campo retorno-campo">
          <label>Motivo geral da ocorrência</label>

          <input
            v-model="motivoRetorno"
            type="text"
            placeholder="Ex.: cliente ausente, acesso bloqueado..."
          />

          <small>
            Opcional. O motivo de devolução é escolhido
            individualmente no produto.
          </small>
        </div>

        <div class="campo">
          <label>Observação</label>

          <textarea
            v-model="observacaoRetorno"
            rows="3"
            placeholder="Observações sobre esta entrega..."
          ></textarea>
        </div>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            @click="fecharRetorno"
          >
            Cancelar
          </button>

          <button
            class="botao finalizar"
            type="button"
            :disabled="registrandoRetorno"
            @click="confirmarRetorno"
          >
            {{
              registrandoRetorno
                ? 'Registrando...'
                : 'Confirmar retorno'
            }}
          </button>
        </footer>
      </section>
    </div>

    <!-- RETORNO DA REENTREGA -->

    <div
      v-if="
        modalRetornoPendencia &&
        pendenciaRetorno
      "
      class="modal-fundo retorno-modal-fundo"
      @click.self="fecharRetornoPendencia"
    >
      <section class="modal medio">
        <header class="modal-cabecalho">
          <div>
            <h2>Registrar retorno da reentrega</h2>

            <p>
              Pedido
              {{
                pendenciaRetorno.numero_pedido ??
                `#${pendenciaRetorno.entrega_id}`
              }}
              ·
              {{ pendenciaRetorno.cliente_nome }}
            </p>
          </div>

          <button
            class="fechar"
            type="button"
            @click="fecharRetornoPendencia"
          >
            ×
          </button>
        </header>

        <div class="reentrega-retorno-produto">
          <div>
            <small>Produto</small>
            <strong>
              {{ pendenciaRetorno.descricao_produto }}
            </strong>
            <span v-if="pendenciaRetorno.codigo_produto">
              Cód. {{ pendenciaRetorno.codigo_produto }}
            </span>
          </div>

          <div>
            <small>Programado para reentrega</small>
            <strong>
              {{ pendenciaRetorno.saldo_pendente }}
              {{ pendenciaRetorno.unidade || '' }}
            </strong>
          </div>
        </div>

        <div class="grid-retorno-reentrega">
          <div class="campo">
            <label>Entregue ao cliente</label>
            <input
              v-model.number="quantidadeEntreguePendencia"
              type="number"
              min="0"
              :max="pendenciaRetorno.saldo_pendente"
              step="0.001"
            />
          </div>

          <div class="campo">
            <label>Ainda faltou</label>
            <strong
              class="saldo-faltante-reentrega"
              :class="{
                'tem-falta':
                  quantidadeFaltantePendencia() > 0,
              }"
            >
              {{ quantidadeFaltantePendencia() }}
              {{ pendenciaRetorno.unidade || '' }}
            </strong>
          </div>
        </div>

        <div
          v-if="quantidadeFaltantePendencia() > 0"
          class="campo"
        >
          <label>
            Destino da quantidade restante
          </label>

          <select
            v-model="destinoRestantePendencia"
            @change="alterarDestinoPendencia"
          >
            <option :value="null">
              Selecione...
            </option>
            <option value="REENTREGA">
              Reentrega novamente
            </option>
            <option value="DEVOLVIDO">
              Devolvido
            </option>
          </select>
        </div>

        <div
          v-if="
            quantidadeFaltantePendencia() > 0 &&
            destinoRestantePendencia ===
              'DEVOLVIDO'
          "
          class="campo"
        >
          <label>Motivo da devolução</label>

          <select v-model="motivoDevolucaoPendencia">
            <option :value="null">
              Selecione...
            </option>
            <option value="CLIENTE_RECUSOU">
              Cliente recusou
            </option>
            <option value="CLIENTE_DESISTIU">
              Cliente desistiu
            </option>
            <option value="PRODUTO_INCORRETO">
              Produto incorreto
            </option>
            <option value="PRODUTO_AVARIADO">
              Produto avariado
            </option>
            <option value="QUANTIDADE_EXCEDENTE">
              Quantidade excedente
            </option>
            <option value="OUTRO">
              Outro
            </option>
          </select>
        </div>

        <div class="campo">
          <label>Observação</label>
          <textarea
            v-model="observacaoRetornoPendencia"
            rows="3"
            placeholder="Observações sobre esta reentrega..."
          ></textarea>
        </div>

        <footer class="modal-acoes">
          <button
            class="botao secundario"
            type="button"
            @click="fecharRetornoPendencia"
          >
            Cancelar
          </button>

          <button
            class="botao finalizar"
            type="button"
            :disabled="registrandoRetornoPendencia"
            @click="confirmarRetornoPendencia"
          >
            {{
              registrandoRetornoPendencia
                ? 'Registrando...'
                : 'Confirmar retorno'
            }}
          </button>
        </footer>
      </section>
    </div>
    </main>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.pagina {
  min-height: 100vh;
  padding: 32px;
  color: #172033;
}

.cabecalho {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.cabecalho h1,
.modal h2,
.bloco h3 {
  margin: 0;
}

.cabecalho p,
.modal-cabecalho p,
.titulo-bloco p {
  margin: 5px 0 0;
  color: #667085;
}

.card {
  overflow: hidden;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
  background: white;
}

.filtros {
  margin-bottom: 20px;
  padding: 16px;
}

.grid-filtros {
  display: grid;
  grid-template-columns: 1.7fr 1fr 1fr auto auto;
  gap: 10px;
}

input,
select,
textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: white;
  font: inherit;
}

input,
select {
  min-height: 42px;
}

textarea {
  resize: vertical;
}

.botao {
  min-height: 42px;
  padding: 0 16px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.primario {
  border: 0;
  background: #172033;
  color: white;
}

.secundario {
  border: 1px solid #d0d5dd;
  background: white;
  color: #344054;
}

.iniciar {
  border: 0;
  background: #175cd3;
  color: white;
}

.finalizar {
  border: 0;
  background: #067647;
  color: white;
}

.botao:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.erro {
  padding: 12px;
  border: 1px solid #fecdca;
  border-radius: 8px;
  background: #fef3f2;
  color: #b42318;
}

.ajuda {
  display: block;
  margin-top: 6px;
  color: #b42318;
}

.topo-tabela {
  padding: 18px 20px;
  border-bottom: 1px solid #eaecf0;
}

.tabela-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  padding: 12px 16px;
  background: #f9fafb;
  color: #667085;
  font-size: 12px;
  text-align: left;
}

td {
  padding: 16px;
  border-top: 1px solid #eaecf0;
}

td small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.status {
  display: inline-flex;
  padding: 4px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.status.planejada {
  background: #fff7ed;
  color: #c2410c;
}

.status.em_andamento {
  background: #eff6ff;
  color: #1d4ed8;
}

.status.finalizada {
  background: #ecfdf3;
  color: #027a48;
}

.status.cancelada {
  background: #f2f4f7;
  color: #667085;
}

.acoes,
.acoes-coluna {
  text-align: right;
}

.acao {
  margin-left: 10px;
  border: 0;
  background: transparent;
  font-weight: 600;
  cursor: pointer;
}

.acao.perigo {
  color: #b42318;
}

.estado {
  padding: 50px 20px;
  color: #667085;
  text-align: center;
}

.pequeno-estado {
  padding: 25px;
}

.paginacao {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-top: 1px solid #eaecf0;
}

.modal-fundo {
  position: fixed;
  z-index: 100;
  inset: 0;
  overflow-y: auto;
  padding: 35px 20px;
  background: rgb(16 24 40 / 60%);
}

.modal {
  width: 100%;
  margin: auto;
  padding: 26px;
  border-radius: 14px;
  background: white;
}

.modal.pequeno {
  max-width: 600px;
}

.modal.medio {
  max-width: 850px;
}

.modal.grande {
  max-width: 1050px;
}

.modal-cabecalho {
  display: flex;
  justify-content: space-between;
  margin-bottom: 24px;
}

.fechar {
  border: 0;
  background: transparent;
  font-size: 28px;
  cursor: pointer;
}

.campo {
  margin-bottom: 18px;
}

.campo label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
}

.resumo {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 25px;
}

.resumo div {
  padding: 16px;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
  background: #f9fafb;
}

.resumo small,
.resumo strong {
  display: block;
}

.resumo small {
  margin-bottom: 5px;
  color: #667085;
}

.bloco {
  margin-top: 24px;
}

.disponiveis {
  padding-top: 24px;
  border-top: 1px solid #eaecf0;
}

.titulo-bloco {
  margin-bottom: 14px;
}

.entrega-card {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 8px;
  padding: 14px;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
}

.ordem {
  display: flex;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #172033;
  color: white;
  font-weight: 700;
}

.entrega-info {
  display: flex;
  min-width: 200px;
  flex: 1;
  flex-direction: column;
}

.entrega-info span {
  margin-top: 3px;
}

.entrega-info small {
  margin-top: 3px;
  color: #667085;
}

/* PRODUTOS JÁ NA CARRADA */

.itens-carrada {
  min-width: 300px;
  padding-left: 15px;
  border-left: 1px solid #eaecf0;
}

.item-carrada {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 4px 0;
  font-size: 12px;
}

.item-carrada span {
  color: #667085;
}

/* MODAL PRODUTOS */

.itens-modal-fundo {
  z-index: 200;
}

.endereco-entrega {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 18px;
  padding: 14px;
  border-radius: 8px;
  background: #f9fafb;
}

.endereco-entrega small {
  color: #667085;
}

.acoes-quantidade {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}

.tabela-itens {
  overflow: hidden;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
}

.item-cabecalho,
.item-selecao {
  display: grid;
  grid-template-columns: 3fr 1fr 1fr 1.2fr;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
}

.item-cabecalho {
  background: #f9fafb;
  color: #667085;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.item-selecao {
  border-top: 1px solid #eaecf0;
}

.item-selecao small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.item-selecao input {
  min-height: 38px;
}

.aviso-capacidade {
  margin-top: 14px;
  color: #667085;
  font-size: 13px;
}

.modal-acoes {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 28px;
}


.retorno {
  border: 0;
  background: #175cd3;
  color: white;
}

.retorno-registrado {
  white-space: nowrap;
  color: #067647;
  font-size: 12px;
  font-weight: 700;
}

.retorno-modal-fundo {
  z-index: 300;
}

.aviso-retorno {
  margin-bottom: 18px;
  padding: 12px 14px;
  border: 1px solid #b2ddff;
  border-radius: 8px;
  background: #eff8ff;
  color: #175cd3;
  font-size: 13px;
}

.retorno-cabecalho,
.retorno-item {
  display: grid;
  grid-template-columns: 2.2fr 0.8fr 1fr 0.8fr 1.8fr;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
}

.retorno-cabecalho {
  background: #f9fafb;
  color: #667085;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.retorno-item {
  border-top: 1px solid #eaecf0;
}

.retorno-item small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.retorno-item input {
  min-height: 38px;
}

.tem-falta {
  color: #b42318;
}

.retorno-campo {
  margin-top: 22px;
}

.retorno-campo small {
  display: block;
  margin-top: 5px;
  color: #667085;
}


.linha-carrada {
  cursor: pointer;
}

.linha-carrada:hover {
  background: #f9fafb;
}

.historico-carrada-fundo {
  z-index: 400;
}

.modal.historico-carrada-modal {
  max-width: 1150px;
}

.historico-carrada-carregando {
  padding: 70px 20px;
}

.historico-carrada-identificacao {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 28px;
}

.historico-carrada-identificacao > div {
  padding: 14px;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
  background: #f9fafb;
}

.historico-carrada-identificacao small,
.historico-carrada-identificacao strong,
.historico-carrada-identificacao span {
  display: block;
}

.historico-carrada-identificacao small {
  margin-bottom: 5px;
  color: #667085;
}

.historico-carrada-identificacao span {
  margin-top: 3px;
  color: #667085;
  font-size: 12px;
}

.historico-carrada-secao {
  margin-top: 30px;
}

.historico-carrada-titulo {
  margin-bottom: 14px;
}

.historico-carrada-titulo h3 {
  margin: 0;
}

.historico-carrada-titulo p {
  margin: 4px 0 0;
  color: #667085;
  font-size: 13px;
}

.historico-carrada-resumo {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.resumo-carrada-card {
  padding: 14px;
  border-radius: 9px;
  background: #f9fafb;
}

.resumo-carrada-card small,
.resumo-carrada-card strong {
  display: block;
}

.resumo-carrada-card small {
  margin-bottom: 5px;
  color: #667085;
  font-size: 11px;
}

.resumo-carrada-card strong {
  font-size: 20px;
}

.resumo-carrada-card.sucesso {
  background: #ecfdf3;
}

.resumo-carrada-card.sucesso strong {
  color: #027a48;
}

.resumo-carrada-card.alerta {
  background: #fff7ed;
}

.resumo-carrada-card.alerta strong,
.resumo-carrada-card.pendencia strong {
  color: #c2410c;
}

.resumo-carrada-card.perigo {
  background: #fef2f2;
}

.resumo-carrada-card.perigo strong {
  color: #b42318;
}

.resumo-carrada-card.pendencia {
  background: #fffaeb;
}

.historico-carrada-observacao {
  margin: 12px 0 0;
  padding: 12px 14px;
  border-radius: 8px;
  background: #f9fafb;
  color: #475467;
  font-size: 13px;
}

.historico-entrega-carrada {
  margin-bottom: 16px;
  padding: 16px;
  border: 1px solid #e4e7ec;
  border-radius: 12px;
}

.historico-entrega-topo {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 14px;
}

.historico-entrega-identidade {
  display: flex;
  align-items: center;
  gap: 12px;
}

.historico-entrega-identidade > div {
  display: flex;
  flex-direction: column;
}

.historico-entrega-identidade span {
  margin-top: 3px;
}

.historico-entrega-identidade small {
  margin-top: 3px;
  color: #667085;
}

.historico-ordem {
  display: flex;
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #172033;
  color: white;
  font-weight: 700;
}

.historico-entrega-resultado {
  display: flex;
  align-items: flex-end;
  flex-direction: column;
  gap: 5px;
}

.historico-entrega-resultado small {
  color: #667085;
}

.resultado-carrada {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.resultado-carrada.completa {
  background: #ecfdf3;
  color: #027a48;
}

.resultado-carrada.parcial {
  background: #fff7ed;
  color: #c2410c;
}

.resultado-carrada.nao_entregue {
  background: #fef2f2;
  color: #b42318;
}

.resultado-carrada.aguardando {
  background: #f2f4f7;
  color: #667085;
}

.historico-produtos-carrada {
  overflow: hidden;
  border: 1px solid #eaecf0;
  border-radius: 9px;
}

.historico-produtos-cabecalho,
.historico-produto-linha {
  display: grid;
  grid-template-columns: 2.2fr repeat(5, 1fr);
  gap: 12px;
  align-items: center;
  padding: 11px 13px;
}

.historico-produtos-cabecalho {
  background: #f9fafb;
  color: #667085;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.historico-produto-linha {
  border-top: 1px solid #eaecf0;
  font-size: 13px;
}

.historico-produto-linha small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.historico-qtd-entregue {
  color: #027a48;
}

.historico-qtd-faltante {
  color: #b42318;
}

.historico-resultado-observacoes {
  margin-top: 12px;
  padding: 12px;
  border-radius: 8px;
  background: #f9fafb;
  color: #475467;
  font-size: 13px;
}

.historico-resultado-observacoes p {
  margin: 0;
}

.historico-resultado-observacoes p + p {
  margin-top: 6px;
}

.historico-pendencias-geradas {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #eaecf0;
}

.historico-pendencias-geradas h4 {
  margin: 0 0 10px;
}

.historico-pendencia-carrada {
  position: relative;
  margin-top: 8px;
  padding: 13px;
  border: 1px solid #fedf89;
  border-radius: 9px;
  background: #fffaeb;
}

.historico-pendencia-produto {
  padding-right: 110px;
}

.historico-pendencia-produto small {
  display: block;
  margin-top: 3px;
  color: #667085;
}

.historico-pendencia-numeros {
  display: flex;
  flex-wrap: wrap;
  gap: 18px;
  margin-top: 10px;
  font-size: 13px;
}

.status-pendencia-carrada {
  position: absolute;
  top: 13px;
  right: 13px;
  padding: 4px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.status-pendencia-carrada.aberta,
.status-pendencia-carrada.programada {
  background: #fff7ed;
  color: #c2410c;
}

.status-pendencia-carrada.resolvida {
  background: #ecfdf3;
  color: #027a48;
}

.status-pendencia-carrada.cancelada {
  background: #f2f4f7;
  color: #667085;
}

.historico-pendencia-texto {
  margin: 9px 0 0;
  color: #667085;
  font-size: 13px;
}

.historico-carrada-vazio {
  padding: 24px;
  border: 1px dashed #d0d5dd;
  border-radius: 10px;
  color: #667085;
  text-align: center;
}

.historico-timeline {
  padding-left: 7px;
}

.historico-timeline-item {
  position: relative;
  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 10px;
  padding-bottom: 18px;
}

.historico-timeline-item:not(:last-child)::before {
  position: absolute;
  top: 12px;
  bottom: 0;
  left: 5px;
  width: 2px;
  background: #e4e7ec;
  content: '';
}

.historico-timeline-marcador {
  position: relative;
  z-index: 1;
  width: 12px;
  height: 12px;
  margin-top: 4px;
  border: 2px solid white;
  border-radius: 50%;
  background: #175cd3;
  box-shadow: 0 0 0 1px #b2ddff;
}

.historico-timeline-item > div:last-child {
  display: flex;
  flex-direction: column;
}

.historico-timeline-item small {
  margin-bottom: 2px;
  color: #667085;
}

.historico-timeline-item span {
  margin-top: 2px;
  color: #667085;
  font-size: 13px;
}

.historico-timeline-item .evento-recuperado {
  display: inline-flex;
  width: fit-content;
  margin-top: 6px;
  padding: 3px 7px;
  border-radius: 20px;
  background: #f2f4f7;
  color: #667085;
  font-size: 10px;
  font-weight: 700;
}


.rota-tabela {
  min-width: 260px;
  max-width: 420px;
  color: #475467;
  line-height: 1.5;
}

.ajuda-rota {
  margin: -2px 0 10px;
  color: #667085;
  font-size: 12px;
}

.rota-adicionar {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  margin-bottom: 8px;
}

.rota-lista {
  overflow: hidden;
  margin-top: 12px;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
}

.rota-parada {
  display: grid;
  grid-template-columns: 34px 1fr auto;
  gap: 10px;
  align-items: center;
  padding: 10px 12px;
  background: white;
}

.rota-parada + .rota-parada {
  border-top: 1px solid #eaecf0;
}

.rota-numero {
  display: flex;
  width: 28px;
  height: 28px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #172033;
  color: white;
  font-size: 12px;
  font-weight: 700;
}

.rota-acoes {
  display: flex;
  gap: 5px;
}

.rota-acoes button {
  min-width: 32px;
  min-height: 32px;
  padding: 0 8px;
  border: 1px solid #d0d5dd;
  border-radius: 6px;
  background: white;
  color: #344054;
  cursor: pointer;
}

.rota-acoes button:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.rota-acoes .remover-rota {
  color: #b42318;
}

.rota-vazia {
  margin-top: 10px;
  padding: 14px;
  border: 1px dashed #d0d5dd;
  border-radius: 8px;
  color: #667085;
  font-size: 13px;
  text-align: center;
}

.rota-detalhes {
  padding-bottom: 4px;
}

.rota-visual {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  padding: 14px;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
  background: #f9fafb;
}

.rota-chip {
  display: inline-flex;
  gap: 7px;
  align-items: center;
  padding: 7px 10px;
  border: 1px solid #d0d5dd;
  border-radius: 20px;
  background: white;
  font-size: 13px;
}

.rota-chip b {
  display: inline-flex;
  width: 20px;
  height: 20px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #172033;
  color: white;
  font-size: 10px;
}

.rota-seta {
  color: #98a2b3;
  font-weight: 700;
}

.historico-rota {
  margin-top: 20px;
}

@media (max-width: 700px) {
  .pagina {
    padding: 18px;
  }

  .cabecalho {
    align-items: stretch;
    flex-direction: column;
  }

  .grid-filtros,
  .resumo,
  .rota-adicionar {
    grid-template-columns: 1fr;
  }

  .rota-parada {
    grid-template-columns: 34px 1fr;
  }

  .rota-acoes {
    grid-column: 1 / -1;
    justify-content: flex-end;
  }

  .entrega-card {
    align-items: stretch;
    flex-direction: column;
  }

  .itens-carrada {
    width: 100%;
    min-width: 0;
    padding: 12px 0 0;
    border-top: 1px solid #eaecf0;
    border-left: 0;
  }

  .item-cabecalho {
    display: none;
  }

  .item-selecao {
    grid-template-columns: 1fr;
  }

  .acoes-quantidade {
    flex-direction: column;
  }

  .retorno-cabecalho {
    display: none;
  }

  .retorno-item {
    grid-template-columns: 1fr;
  }

  .historico-carrada-identificacao,
  .historico-carrada-resumo {
    grid-template-columns: 1fr 1fr;
  }

  .historico-entrega-topo {
    flex-direction: column;
  }

  .historico-entrega-resultado {
    align-items: flex-start;
  }

  .historico-produtos-cabecalho {
    display: none;
  }

  .historico-produto-linha {
    grid-template-columns: 1fr;
  }

  .historico-pendencia-produto {
    padding-right: 0;
  }

  .status-pendencia-carrada {
    position: static;
    display: inline-flex;
    margin-top: 10px;
  }
}

.pendencias-carrada,
.pendencias-disponiveis {
  border-color: #fedf89;
}

.pendencia-operacional-card {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 18px;
  align-items: center;
  margin-top: 10px;
  padding: 16px;
  border: 1px solid #fedf89;
  border-radius: 10px;
  background: #fffaeb;
}

.pendencia-operacional-card.disponivel {
  background: #fffdf5;
}

.pendencia-operacional-info {
  min-width: 0;
}

.pendencia-operacional-info > span,
.pendencia-operacional-info > small {
  display: block;
  margin-top: 4px;
}

.pendencia-operacional-info > small,
.pendencia-produto-operacional small {
  color: #667085;
}

.pendencia-operacional-topo {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.reentrega-badge {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 20px;
  background: #fff1d6;
  color: #b54708;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.pendencia-produto-operacional {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: center;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #fedf89;
}

.pendencia-produto-operacional > div:first-child {
  min-width: 0;
}

.pendencia-produto-operacional small {
  display: block;
  margin-top: 3px;
}

.pendencia-saldo-operacional {
  flex-shrink: 0;
  text-align: right;
}

.pendencia-saldo-operacional strong {
  display: block;
  margin-top: 2px;
  color: #b54708;
  font-size: 18px;
}

.pendencia-motivo-operacional {
  margin: 10px 0 0;
  color: #667085;
  font-size: 13px;
}


/* =========================================================
 * DEVOLUÇÃO / REENTREGA NO RETORNO
 * ========================================================= */
.retorno-modal {
  max-width: 1180px;
}

.destino-retorno {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.destino-retorno select,
.motivo-devolucao-select {
  width: 100%;
  min-height: 38px;
}

.acoes-reentrega-carrada {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-end;
}

.resultado-reentrega-card {
  min-width: 175px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #ecfdf3;
  color: #027a48;
  text-align: right;
}

.resultado-reentrega-card strong,
.resultado-reentrega-card small {
  display: block;
}

.resultado-reentrega-card small {
  margin-top: 3px;
  color: #475467;
}

.reentrega-retorno-produto {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 16px;
  margin-bottom: 18px;
  padding: 16px;
  border: 1px solid #fedf89;
  border-radius: 10px;
  background: #fffaeb;
}

.reentrega-retorno-produto > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.reentrega-retorno-produto small,
.reentrega-retorno-produto span {
  color: #667085;
}

.grid-retorno-reentrega {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.saldo-faltante-reentrega {
  display: flex;
  min-height: 42px;
  align-items: center;
  padding: 0 12px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: #f9fafb;
}

/* =========================================================
 * HISTÓRICO COM REENTREGAS E DEVOLUÇÕES
 * ========================================================= */
.resumo-carrada-card.reentrega {
  background: #fff7ed;
}

.resumo-carrada-card.reentrega strong,
.historico-qtd-reentrega {
  color: #c2410c;
}

.resumo-carrada-card.devolucao {
  background: #f2f4f7;
}

.resumo-carrada-card.devolucao strong,
.historico-qtd-devolvida {
  color: #475467;
}

.historico-reentrega-card {
  margin-bottom: 14px;
  padding: 16px;
  border: 1px solid #fedf89;
  border-radius: 12px;
  background: #fffdf5;
}

.historico-reentrega-topo {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 14px;
}

.historico-reentrega-topo > div:first-child {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.historico-reentrega-topo small {
  color: #667085;
}

.historico-reentrega-produto {
  display: grid;
  grid-template-columns: 2fr repeat(4, 1fr);
  gap: 12px;
  padding: 13px;
  border: 1px solid #eaecf0;
  border-radius: 9px;
  background: white;
}

.historico-reentrega-produto > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.historico-reentrega-produto small {
  color: #667085;
  font-size: 11px;
  text-transform: uppercase;
}

.historico-reentrega-produto span {
  color: #667085;
  font-size: 12px;
}


@media (max-width: 900px) {
  .acoes-reentrega-carrada {
    width: 100%;
    align-items: stretch;
  }

  .resultado-reentrega-card {
    text-align: left;
  }

  .reentrega-retorno-produto,
  .grid-retorno-reentrega,
  .historico-reentrega-produto {
    grid-template-columns: 1fr;
  }

  .historico-reentrega-topo {
    flex-direction: column;
  }
}

</style>