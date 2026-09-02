<script setup lang="ts">
import type {
  MotivoDevolucao,
  Pendencia,
  StatusPendencia,
} from '@/types/Pendencia'

defineProps<{
  aberto: boolean
  pendencia: Pendencia | null
}>()

const emit = defineEmits<{
  fechar: []
}>()

const motivos: Record<
  MotivoDevolucao,
  string
> = {
  CLIENTE_DESISTIU:
    'Cliente desistiu do produto',
  CLIENTE_RECUSOU:
    'Cliente recusou o produto',
  PRODUTO_INCORRETO:
    'Produto incorreto',
  PRODUTO_AVARIADO:
    'Produto avariado',
  QUANTIDADE_EXCEDENTE:
    'Quantidade excedente',
  OUTRO:
    'Outro',
}

function statusLabel(
  status: StatusPendencia,
) {
  const labels: Record<
    StatusPendencia,
    string
  > = {
    ABERTA: 'Aberta',
    PROGRAMADA: 'Programada',
    RESOLVIDA: 'Resolvida',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function motivoLabel(
  motivo: string | null,
) {
  if (!motivo) {
    return '—'
  }

  return (
    motivos[
      motivo as MotivoDevolucao
    ] ?? motivo
  )
}

function formatarQuantidade(
  valor: number,
) {
  return new Intl.NumberFormat(
    'pt-BR',
    {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3,
    },
  ).format(Number(valor))
}

function formatarDataHora(
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
      timeZone: 'America/Fortaleza',
    },
  ).format(valor)
}
</script>

<template>
  <div
    v-if="aberto && pendencia"
    class="modal-fundo"
    @click.self="emit('fechar')"
  >
    <section class="modal detalhes-modal">
      <header class="modal-cabecalho">
        <div>
          <span class="eyebrow">
            Pendência
            #{{ pendencia.id }}
          </span>

          <h2>
            {{
              pendencia.produto.descricao
            }}
          </h2>

          <p>
            {{
              pendencia.numero_pedido
                ? `Pedido #${pendencia.numero_pedido}`
                : `Pedido #${pendencia.entrega_id}`
            }}
            ·
            {{ pendencia.cliente_nome }}
          </p>
        </div>

        <button
          class="fechar"
          type="button"
          @click="emit('fechar')"
        >
          ×
        </button>
      </header>

      <div class="detalhes-grid">
        <div>
          <small>Status</small>

          <strong>
            {{
              statusLabel(
                pendencia.status,
              )
            }}
          </strong>
        </div>

        <div>
          <small>Região</small>

          <strong>
            {{ pendencia.regiao.nome }}
          </strong>
        </div>

        <div>
          <small>
            Quantidade original
          </small>

          <strong>
            {{
              formatarQuantidade(
                pendencia
                  .quantidade_original,
              )
            }}
          </strong>
        </div>

        <div>
          <small>
            Quantidade resolvida
          </small>

          <strong>
            {{
              formatarQuantidade(
                pendencia
                  .quantidade_resolvida,
              )
            }}
          </strong>
        </div>

        <div>
          <small>Saldo pendente</small>

          <strong class="saldo-destaque">
            {{
              formatarQuantidade(
                pendencia
                  .saldo_pendente,
              )
            }}
            {{ pendencia.produto.unidade ?? '' }}
          </strong>
        </div>

        <div>
          <small>Aberta em</small>

          <strong>
            {{
              formatarDataHora(
                pendencia
                  .data_abertura,
              )
            }}
          </strong>
        </div>
      </div>

      <div
        v-if="pendencia.carrada_atual"
        class="carrada-detalhe"
      >
        <small>
          Programação atual
        </small>

        <strong>
          Carrada
          #{{ pendencia.carrada_atual.id }}
        </strong>

        <span>
          {{
            pendencia.carrada_atual.status ===
              'PLANEJADA'
              ? 'Planejada'
              : 'Em andamento'
          }}
        </span>
      </div>

      <div class="detalhe-texto">
        <small>Motivo</small>

        <p>
          {{
            motivoLabel(
              pendencia.motivo,
            )
          }}
        </p>
      </div>

      <div class="detalhe-texto">
        <small>Observação</small>

        <p>
          {{
            pendencia.observacao ||
            'Nenhuma observação registrada.'
          }}
        </p>
      </div>

      <footer class="modal-acoes">
        <button
          class="botao secundario"
          type="button"
          @click="emit('fechar')"
        >
          Fechar
        </button>
      </footer>
    </section>
  </div>
</template>

<style
  scoped
  src="../../styles/pages/pendencia/pendencia-detalhes-modal.css"
></style>
