<script setup lang="ts">
import type {
  ClienteHistorico,
  ClienteHistoricoEntrega,
  StatusEntregaCliente,
} from '@/types/ClienteHistorico'

const props = defineProps<{
  historico: ClienteHistorico
}>()

const emit = defineEmits<{
  pagina: [page: number]
}>()

function statusEntregaLabel(status: StatusEntregaCliente) {
  const labels: Record<StatusEntregaCliente, string> = {
    PENDENTE: 'Pendente',
    PROGRAMADA: 'Programada',
    EM_SAIDA: 'Em saída',
    PARCIAL: 'Parcial',
    CONCLUIDA: 'Concluída',
    NAO_ENTREGUE: 'Não entregue',
    CANCELADA: 'Cancelada',
  }

  return labels[status]
}

function formatarData(data: string) {
  const valor = new Date(data)

  if (Number.isNaN(valor.getTime())) {
    return data
  }

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(valor)
}

function formatarQuantidade(quantidade: number) {
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(Number(quantidade))
}

function tituloPedido(entrega: ClienteHistoricoEntrega) {
  return entrega.numero_pedido
    ? `#${entrega.numero_pedido}`
    : `#${entrega.id}`
}
</script>

<template>
  <div class="historico-cliente-painel">
    <section class="historico-resumo">
      <article class="resumo-item total">
        <div class="resumo-icone">▤</div>
        <div>
          <small>Total de entregas</small>
          <strong>{{ historico.resumo.total }}</strong>
        </div>
      </article>

      <article class="resumo-item pendente">
        <div class="resumo-icone">!</div>
        <div>
          <small>Pendentes</small>
          <strong>{{ historico.resumo.pendentes }}</strong>
        </div>
      </article>

      <article class="resumo-item parcial">
        <div class="resumo-icone">↗</div>
        <div>
          <small>Parciais</small>
          <strong>{{ historico.resumo.parciais }}</strong>
        </div>
      </article>

      <article class="resumo-item concluida">
        <div class="resumo-icone">✓</div>
        <div>
          <small>Concluídas</small>
          <strong>{{ historico.resumo.concluidas }}</strong>
        </div>
      </article>
    </section>

    <section class="card subresumo-card">
      <span>Programadas <strong>{{ historico.resumo.programadas }}</strong></span>
      <span>Em saída <strong>{{ historico.resumo.em_saida }}</strong></span>
      <span>Não entregues <strong>{{ historico.resumo.nao_entregues }}</strong></span>
      <span>Canceladas <strong>{{ historico.resumo.canceladas }}</strong></span>
    </section>

    <section class="card historico-lista">
      <header class="historico-lista-cabecalho">
        <div>
          <span class="eyebrow">Movimentação</span>
          <h2>Entregas</h2>
          <p>Da mais recente para a mais antiga.</p>
        </div>

        <strong>{{ historico.meta.total }} registro(s)</strong>
      </header>

      <div v-if="historico.entregas.length === 0" class="historico-vazio">
        Este cliente ainda não possui entregas.
      </div>

      <article
        v-for="entrega in historico.entregas"
        v-else
        :key="entrega.id"
        class="entrega-cliente-card"
      >
        <header class="entrega-cliente-topo">
          <div>
            <strong class="pedido-numero">
              {{ tituloPedido(entrega) }}
            </strong>
            <span class="entrega-data">
              {{ formatarData(entrega.created_at) }}
            </span>
          </div>

          <span
            class="status status-entrega"
            :class="entrega.status.toLowerCase()"
          >
            {{ statusEntregaLabel(entrega.status) }}
          </span>
        </header>

        <div class="entrega-local">
          <div>
            <small>Região</small>
            <strong>{{ entrega.regiao?.nome ?? 'Sem região' }}</strong>
          </div>

          <div>
            <small>Endereço</small>
            <strong>{{ entrega.endereco }}</strong>
            <span v-if="entrega.referencia">
              Ref.: {{ entrega.referencia }}
            </span>
          </div>
        </div>

        <div class="entrega-produtos">
          <div class="produtos-titulo">
            <strong>Produtos</strong>
            <span>{{ entrega.itens.length }} item(ns)</span>
          </div>

          <div
            v-for="item in entrega.itens"
            :key="item.id"
            class="produto-linha"
          >
            <div>
              <strong>{{ item.descricao_produto }}</strong>
              <small v-if="item.codigo_produto">
                Cód. {{ item.codigo_produto }}
              </small>
            </div>

            <strong class="produto-quantidade">
              {{ formatarQuantidade(item.quantidade_solicitada) }}
              {{ item.unidade ?? '' }}
            </strong>
          </div>
        </div>

        <div v-if="entrega.observacao" class="entrega-observacao">
          <small>Observação</small>
          <p>{{ entrega.observacao }}</p>
        </div>
      </article>

      <footer
        v-if="historico.meta.last_page > 1"
        class="paginacao historico-paginacao"
      >
        <button
          class="botao secundario"
          type="button"
          :disabled="historico.meta.current_page <= 1"
          @click="emit('pagina', historico.meta.current_page - 1)"
        >
          Anterior
        </button>

        <span>
          Página {{ historico.meta.current_page }} de {{ historico.meta.last_page }}
        </span>

        <button
          class="botao secundario"
          type="button"
          :disabled="historico.meta.current_page >= historico.meta.last_page"
          @click="emit('pagina', historico.meta.current_page + 1)"
        >
          Próxima
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped src="../../styles/pages/cliente/cliente-historico-painel.css"></style>
