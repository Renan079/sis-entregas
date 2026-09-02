<script setup lang="ts">
import { computed } from 'vue'

import type {
  SaidaHistorico,
} from '@/services/saidaService.ts'
import type {
  StatusSaida,
} from '@/types/Saida.ts'

const props = defineProps<{
  historico: SaidaHistorico
}>()

interface EventoHistoricoCarrada {
  id: number
  data: string
  titulo: string
  descricao: string
  tipo: string
  origem: string
}

const eventosHistoricoCarrada = computed<
  EventoHistoricoCarrada[]
>(() => {
  return props.historico.eventos.map(
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

function normalizarTimestamp(
  data: string,
) {
  return data.replace(
    /\.(\d{3})\d+Z$/,
    '.$1Z',
  )
}

function formatarDataHoraHistorico(
  data: string | null,
) {
  if (!data) {
    return '—'
  }

  const valor = new Date(
    normalizarTimestamp(data),
  )

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat(
    'pt-BR',
    {
      dateStyle: 'short',
      timeStyle: 'short',
      timeZone: 'America/Fortaleza',
    },
  ).format(valor)
}

function formatarDataHistorico(
  data: string | null,
) {
  if (!data) {
    return '—'
  }

  const somenteData = data.split('T')[0]

  if (!somenteData) {
    return '—'
  }

  const [ano, mes, dia] =
    somenteData.split('-')

  if (!ano || !mes || !dia) {
    return data
  }

  return `${dia}/${mes}/${ano}`
}

function tituloEventoCarrada(
  tipo: string,
) {
  const titulos: Record<string, string> = {
    SAIDA_CRIADA: 'Carrada criada',
    ENTREGA_ADICIONADA: 'Pedido adicionado',
    ENTREGA_ATUALIZADA: 'Programação atualizada',
    ENTREGA_REMOVIDA: 'Pedido removido',
    PENDENCIA_ADICIONADA: 'Pendência adicionada',
    PENDENCIA_REMOVIDA: 'Pendência removida',
    SAIDA_INICIADA: 'Carrada iniciada',
    RESULTADO_ENTREGA_REGISTRADO: 'Resultado de entrega',
    RESULTADO_REENTREGA_REGISTRADO: 'Resultado da reentrega',
    VEICULO_TROCADO: 'Veículo alterado',
    SAIDA_FINALIZADA: 'Carrada finalizada',
    SAIDA_CANCELADA: 'Carrada cancelada',
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
  if (!data) {
    return '—'
  }

  const somenteData = data.split('T')[0]

  if (!somenteData) {
    return '—'
  }

  const [ano, mes, dia] =
    somenteData.split('-')

  if (!ano || !mes || !dia) {
    return data
  }

  return `${dia}/${mes}/${ano}`
}
</script>

<template>
  <section class="historico-carrada-painel">
    <div class="historico-carrada-identificacao">
      <div>
        <small>Status</small>

        <strong>
          {{
            statusLabel(
              historico.saida.status,
            )
          }}
        </strong>
      </div>

      <div>
        <small>Região</small>

        <strong>
          {{
            historico.saida.regiao?.nome ??
            'Sem região'
          }}
        </strong>
      </div>

      <div>
        <small>Veículo</small>

        <strong>
          {{
            historico.saida.veiculo?.nome ??
            'Sem veículo'
          }}
        </strong>

        <span
          v-if="
            historico.saida.veiculo?.placa
          "
        >
          {{
            historico.saida.veiculo.placa
          }}
        </span>
      </div>

      <div>
        <small>Criada em</small>

        <strong>
          {{
            formatarDataHoraHistorico(
              historico.saida.criada_em,
            )
          }}
        </strong>
      </div>
    </div>

    <section
      v-if="
        historico.saida.rota.length > 0
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
            historico.saida.rota
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
              historico.saida.rota.length - 1
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
              historico.resumo
                .total_entregas_normais
            }}
          </strong>
        </div>

        <div class="resumo-carrada-card reentrega">
          <small>Reentregas</small>
          <strong>
            {{
              historico.resumo
                .total_reentregas
            }}
          </strong>
        </div>

        <div class="resumo-carrada-card">
          <small>Atendimentos</small>
          <strong>
            {{
              historico.resumo
                .total_atendimentos
            }}
          </strong>
        </div>

        <div class="resumo-carrada-card sucesso">
          <small>Completas normais</small>
          <strong>
            {{ historico.resumo.completas }}
          </strong>
        </div>

        <div class="resumo-carrada-card alerta">
          <small>Parciais normais</small>
          <strong>
            {{ historico.resumo.parciais }}
          </strong>
        </div>

        <div class="resumo-carrada-card perigo">
          <small>Não entregues</small>
          <strong>
            {{
              historico.resumo
                .nao_entregues
            }}
          </strong>
        </div>

        <div class="resumo-carrada-card">
          <small>Sem resultado</small>
          <strong>
            {{
              historico.resumo
                .sem_resultado
            }}
          </strong>
        </div>

        <div class="resumo-carrada-card pendencia">
          <small>Pendências geradas</small>
          <strong>
            {{
              formatarQuantidadeHistorico(
                historico.resumo
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
                historico.resumo
                  .quantidade_devolvida,
              )
            }}
          </strong>
        </div>
      </div>

      <p
        v-if="historico.saida.observacao"
        class="historico-carrada-observacao"
      >
        <strong>Observação da carrada:</strong>
        {{ historico.saida.observacao }}
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
          historico.entregas.length === 0
        "
        class="historico-carrada-vazio"
      >
        <template
          v-if="historico.reentregas.length > 0"
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
          entrega in historico.entregas
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
      v-if="historico.reentregas.length > 0"
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
        v-for="reentrega in historico.reentregas"
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
  </section>
</template>

<style scoped src="../../styles/pages/saida/saidas.css"></style>
