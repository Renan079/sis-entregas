import { reactive, ref } from 'vue'

import { saidaService } from '@/services/saidaService.ts'
import type {
  EntregaDisponivelRota,
  PendenciaRota,
} from '@/services/saidaService.ts'

import type {
  DestinoNaoEntregue,
  EntregaDisponivelCarrada,
  ItemProgramacaoCarrada,
  ItemRetornoCarrada,
  MotivoDevolucao,
  Saida,
  SaidaEntrega,
} from '@/types/Saida.ts'

export function useSaidaDetalhe() {
  const saidaSelecionada = ref<Saida | null>(null)
  const entregasDisponiveis = ref<EntregaDisponivelRota[]>([])
  const pendenciasDisponiveis = ref<PendenciaRota[]>([])
  const pendenciasNaCarrada = ref<PendenciaRota[]>([])

  const carregando = ref(false)
  const carregandoItens = ref(false)
  const salvando = ref(false)
  const erro = ref('')
  const processandoPendenciaId = ref<number | null>(null)

  const modalSelecionarItens = ref(false)
  const entregaParaAdicionar =
    ref<EntregaDisponivelCarrada | null>(null)
  const quantidadesProgramadas = reactive<Record<number, number>>({})

  const modalRetorno = ref(false)
  const entregaRetorno = ref<SaidaEntrega | null>(null)
  const itensRetorno = ref<ItemRetornoCarrada[]>([])
  const motivoRetorno = ref('')
  const observacaoRetorno = ref('')
  const registrandoRetorno = ref(false)

  const modalRetornoPendencia = ref(false)
  const pendenciaRetorno = ref<PendenciaRota | null>(null)
  const quantidadeEntreguePendencia = ref(0)
  const destinoRestantePendencia = ref<DestinoNaoEntregue | null>(null)
  const motivoDevolucaoPendencia = ref<MotivoDevolucao | null>(null)
  const observacaoRetornoPendencia = ref('')
  const registrandoRetornoPendencia = ref(false)

  function limparEstado() {
    saidaSelecionada.value = null
    entregasDisponiveis.value = []
    pendenciasDisponiveis.value = []
    pendenciasNaCarrada.value = []
    erro.value = ''
  }

  async function carregar(saidaId: number) {
    carregando.value = true
    erro.value = ''

    try {
      saidaSelecionada.value = await saidaService.buscar(saidaId)

      await Promise.all([
        carregarEntregasDisponiveis(),
        carregarPendenciasCarrada(),
      ])
    } catch {
      limparEstado()
      erro.value =
        'Não foi possível carregar os dados da carrada.'
    } finally {
      carregando.value = false
    }
  }

  async function recarregarSaida() {
    if (!saidaSelecionada.value) {
      return
    }

    saidaSelecionada.value = await saidaService.buscar(
      saidaSelecionada.value.id,
    )
  }

  async function carregarEntregasDisponiveis() {
    if (!saidaSelecionada.value) {
      return
    }

    try {
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
      const [disponiveis, naCarrada] = await Promise.all([
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

      await Promise.all([
        carregarPendenciasCarrada(),
        recarregarSaida(),
      ])
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

      await Promise.all([
        carregarPendenciasCarrada(),
        recarregarSaida(),
      ])
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
      const dados = await saidaService.buscarItensDisponiveis(
        entrega.id,
      )

      entregaParaAdicionar.value = dados

      for (const chave of Object.keys(quantidadesProgramadas)) {
        delete quantidadesProgramadas[Number(chave)]
      }

      for (const item of dados.itens) {
        quantidadesProgramadas[item.entrega_item_id] = 0
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

    for (const chave of Object.keys(quantidadesProgramadas)) {
      delete quantidadesProgramadas[Number(chave)]
    }
  }

  function levarTudo() {
    if (!entregaParaAdicionar.value) {
      return
    }

    for (const item of entregaParaAdicionar.value.itens) {
      quantidadesProgramadas[item.entrega_item_id] = Number(
        item.quantidade_disponivel,
      )
    }
  }

  function limparQuantidades() {
    if (!entregaParaAdicionar.value) {
      return
    }

    for (const item of entregaParaAdicionar.value.itens) {
      quantidadesProgramadas[item.entrega_item_id] = 0
    }
  }

  async function confirmarAdicionarEntrega() {
    if (!saidaSelecionada.value || !entregaParaAdicionar.value) {
      return
    }

    erro.value = ''

    const itens: ItemProgramacaoCarrada[] =
      entregaParaAdicionar.value.itens
        .map((item) => ({
          entrega_item_id: item.entrega_item_id,
          quantidade_programada: Number(
            quantidadesProgramadas[item.entrega_item_id] ?? 0,
          ),
        }))
        .filter((item) => item.quantidade_programada > 0)

    if (itens.length === 0) {
      erro.value =
        'Informe a quantidade de pelo menos um produto.'
      return
    }

    const possuiQuantidadeInvalida =
      entregaParaAdicionar.value.itens.some((item) => {
        const quantidade = Number(
          quantidadesProgramadas[item.entrega_item_id] ?? 0,
        )

        return (
          quantidade < 0 ||
          quantidade > Number(item.quantidade_disponivel)
        )
      })

    if (possuiQuantidadeInvalida) {
      erro.value =
        'Existe produto com quantidade maior que o saldo disponível.'
      return
    }

    salvando.value = true

    try {
      const ordem = saidaSelecionada.value.entregas.length + 1

      saidaSelecionada.value = await saidaService.adicionarEntrega(
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
    } catch {
      erro.value =
        'Não foi possível adicionar os produtos à carrada.'
    } finally {
      salvando.value = false
    }
  }

  async function removerEntrega(entregaId: number) {
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
      saidaSelecionada.value = await saidaService.removerEntrega(
        saidaSelecionada.value.id,
        entregaId,
      )

      await Promise.all([
        carregarEntregasDisponiveis(),
        carregarPendenciasCarrada(),
      ])
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
      saidaSelecionada.value = await saidaService.iniciar(
        saidaSelecionada.value.id,
      )
    } catch {
      erro.value = 'Não foi possível iniciar a saída.'
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
      saidaSelecionada.value = await saidaService.finalizar(
        saidaSelecionada.value.id,
      )
    } catch {
      erro.value = 'Não foi possível finalizar a saída.'
    }
  }

  function abrirRetorno(entrega: SaidaEntrega) {
    erro.value = ''
    entregaRetorno.value = entrega

    itensRetorno.value = entrega.itens_programados.map((item) => ({
      entrega_item_id: item.entrega_item_id,
      quantidade_entregue: Number(item.quantidade_programada),
      destino_nao_entregue: null,
      motivo_devolucao: null,
      observacao: null,
    }))

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

  function itemRetorno(entregaItemId: number) {
    return itensRetorno.value.find(
      (item) => item.entrega_item_id === entregaItemId,
    )
  }

  function quantidadeFaltanteRetorno(
    carregado: string | number,
    entregaItemId: number,
  ) {
    return Math.max(
      0,
      Number(carregado) -
        Number(itemRetorno(entregaItemId)?.quantidade_entregue ?? 0),
    )
  }

  function limparDestinoSemFalta(
    carregado: string | number,
    entregaItemId: number,
  ) {
    if (quantidadeFaltanteRetorno(carregado, entregaItemId) > 0) {
      return
    }

    const retorno = itemRetorno(entregaItemId)

    if (!retorno) {
      return
    }

    retorno.destino_nao_entregue = null
    retorno.motivo_devolucao = null
  }

  async function confirmarRetorno() {
    if (!saidaSelecionada.value || !entregaRetorno.value) {
      return
    }

    erro.value = ''

    const quantidadeInvalida =
      entregaRetorno.value.itens_programados.some((programado) => {
        const retorno = itemRetorno(programado.entrega_item_id)
        const entregue = Number(retorno?.quantidade_entregue ?? 0)
        const carregado = Number(programado.quantidade_programada)

        return entregue < 0 || entregue > carregado
      })

    if (quantidadeInvalida) {
      erro.value =
        'A quantidade entregue deve ficar entre zero e a quantidade carregada.'
      return
    }

    const faltaSemDestino =
      entregaRetorno.value.itens_programados.some((programado) => {
        const faltou = quantidadeFaltanteRetorno(
          programado.quantidade_programada,
          programado.entrega_item_id,
        )

        if (faltou <= 0) {
          return false
        }

        return !itemRetorno(programado.entrega_item_id)
          ?.destino_nao_entregue
      })

    if (faltaSemDestino) {
      erro.value =
        'Informe se cada quantidade não entregue será reentregue ou devolvida.'
      return
    }

    const devolucaoSemMotivo =
      entregaRetorno.value.itens_programados.some((programado) => {
        const retorno = itemRetorno(programado.entrega_item_id)
        const faltou = quantidadeFaltanteRetorno(
          programado.quantidade_programada,
          programado.entrega_item_id,
        )

        return (
          faltou > 0 &&
          retorno?.destino_nao_entregue === 'DEVOLVIDO' &&
          !retorno.motivo_devolucao
        )
      })

    if (devolucaoSemMotivo) {
      erro.value =
        'Informe o motivo de cada produto marcado como devolvido.'
      return
    }

    for (const programado of entregaRetorno.value.itens_programados) {
      limparDestinoSemFalta(
        programado.quantidade_programada,
        programado.entrega_item_id,
      )
    }

    registrandoRetorno.value = true

    try {
      await saidaService.registrarResultadoEntrega(
        saidaSelecionada.value.id,
        entregaRetorno.value.id,
        {
          itens: itensRetorno.value,
          motivo_nao_entrega: motivoRetorno.value.trim() || null,
          observacao: observacaoRetorno.value.trim() || null,
        },
      )

      await recarregarSaida()
      fecharRetorno()

      await Promise.all([
        carregarPendenciasCarrada(),
        carregarEntregasDisponiveis(),
      ])
    } catch {
      erro.value =
        'Não foi possível registrar o retorno da entrega.'
    } finally {
      registrandoRetorno.value = false
    }
  }

  function abrirRetornoPendencia(pendencia: PendenciaRota) {
    erro.value = ''
    pendenciaRetorno.value = pendencia
    quantidadeEntreguePendencia.value = Number(
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
      Number(pendenciaRetorno.value.saldo_pendente) -
        Number(quantidadeEntreguePendencia.value),
    )
  }

  async function confirmarRetornoPendencia() {
    if (!saidaSelecionada.value || !pendenciaRetorno.value) {
      return
    }

    erro.value = ''

    const programado = Number(pendenciaRetorno.value.saldo_pendente)
    const entregue = Number(quantidadeEntreguePendencia.value)

    if (entregue < 0 || entregue > programado) {
      erro.value =
        'A quantidade entregue na reentrega deve ficar entre zero e o saldo programado.'
      return
    }

    const faltou = quantidadeFaltantePendencia()

    if (faltou > 0 && !destinoRestantePendencia.value) {
      erro.value =
        'Informe o destino da quantidade que ainda não foi entregue.'
      return
    }

    if (
      faltou > 0 &&
      destinoRestantePendencia.value === 'DEVOLVIDO' &&
      !motivoDevolucaoPendencia.value
    ) {
      erro.value = 'Informe o motivo da devolução.'
      return
    }

    registrandoRetornoPendencia.value = true

    try {
      await saidaService.registrarResultadoPendencia(
        saidaSelecionada.value.id,
        pendenciaRetorno.value.id,
        {
          quantidade_entregue: entregue,
          destino_restante:
            faltou > 0 ? destinoRestantePendencia.value : null,
          motivo_devolucao:
            faltou > 0 &&
            destinoRestantePendencia.value === 'DEVOLVIDO'
              ? motivoDevolucaoPendencia.value
              : null,
          observacao:
            observacaoRetornoPendencia.value.trim() || null,
        },
      )

      await recarregarSaida()
      fecharRetornoPendencia()

      await Promise.all([
        carregarPendenciasCarrada(),
        carregarEntregasDisponiveis(),
      ])
    } catch {
      erro.value =
        'Não foi possível registrar o retorno da reentrega.'
    } finally {
      registrandoRetornoPendencia.value = false
    }
  }

  return {
    saidaSelecionada,
    entregasDisponiveis,
    pendenciasDisponiveis,
    pendenciasNaCarrada,
    carregando,
    carregandoItens,
    salvando,
    erro,
    processandoPendenciaId,
    modalSelecionarItens,
    entregaParaAdicionar,
    quantidadesProgramadas,
    modalRetorno,
    entregaRetorno,
    itensRetorno,
    motivoRetorno,
    observacaoRetorno,
    registrandoRetorno,
    modalRetornoPendencia,
    pendenciaRetorno,
    quantidadeEntreguePendencia,
    destinoRestantePendencia,
    motivoDevolucaoPendencia,
    observacaoRetornoPendencia,
    registrandoRetornoPendencia,
    carregar,
    adicionarPendenciaCarrada,
    removerPendenciaCarrada,
    abrirItensEntrega,
    fecharSelecaoItens,
    levarTudo,
    limparQuantidades,
    confirmarAdicionarEntrega,
    removerEntrega,
    iniciarSaida,
    finalizarSaida,
    abrirRetorno,
    fecharRetorno,
    confirmarRetorno,
    abrirRetornoPendencia,
    fecharRetornoPendencia,
    confirmarRetornoPendencia,
  }
}
