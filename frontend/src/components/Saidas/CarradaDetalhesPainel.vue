<script setup lang="ts">
import type {
  EntregaDisponivelRota,
  PendenciaRota,
} from '@/services/saidaService.ts'
import type {
  Saida,
  SaidaEntrega,
  StatusSaida,
} from '@/types/Saida.ts'

defineProps<{
  saidaSelecionada: Saida | null
  pendenciasNaCarrada: PendenciaRota[]
  pendenciasDisponiveis: PendenciaRota[]
  entregasDisponiveis: EntregaDisponivelRota[]
  processandoPendenciaId: number | null
  carregandoItens: boolean
}>()

const emit = defineEmits<{
  retornoEntrega: [entrega: SaidaEntrega]
  removerEntrega: [entregaId: number]
  retornoPendencia: [pendencia: PendenciaRota]
  removerPendencia: [pendencia: PendenciaRota]
  adicionarPendencia: [pendencia: PendenciaRota]
  verItens: [entrega: EntregaDisponivelRota]
  iniciar: []
  finalizar: []
}>()

function abrirRetorno(entrega: SaidaEntrega) {
  emit('retornoEntrega', entrega)
}

function removerEntrega(entregaId: number) {
  emit('removerEntrega', entregaId)
}

function abrirRetornoPendencia(pendencia: PendenciaRota) {
  emit('retornoPendencia', pendencia)
}

function removerPendenciaCarrada(pendencia: PendenciaRota) {
  emit('removerPendencia', pendencia)
}

function adicionarPendenciaCarrada(pendencia: PendenciaRota) {
  emit('adicionarPendencia', pendencia)
}

function abrirItensEntrega(entrega: EntregaDisponivelRota) {
  emit('verItens', entrega)
}

function iniciarSaida() {
  emit('iniciar')
}

function finalizarSaida() {
  emit('finalizar')
}

function statusLabel(status: StatusSaida) {
  const labels: Record<StatusSaida, string> = {
    PLANEJADA: 'Planejada',
    EM_ANDAMENTO: 'Em andamento',
    FINALIZADA: 'Finalizada',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function formatarData(data: string) {
  if (!data) {
    return '—'
  }

  const somenteData = data.split('T')[0]

  if (!somenteData) {
    return '—'
  }

  const [ano, mes, dia] = somenteData.split('-')

  if (!ano || !mes || !dia) {
    return data
  }

  return `${dia}/${mes}/${ano}`
}
</script>

<template>
  <section
    v-if="saidaSelecionada"
    class="carrada-detalhes-painel"
  >
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
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
<style scoped src="../../styles/pages/saida/saida-detalhe.css"></style>
