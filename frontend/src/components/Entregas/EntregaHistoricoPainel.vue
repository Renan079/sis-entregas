<script setup lang="ts">
import type { EntregaHistorico } from '@/services/entregaService'
import type { StatusEntrega } from '@/types/Entrega'

const props = defineProps<{
  historico: EntregaHistorico
}>()

function formatarQuantidade(valor: number) {
  return new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 3,
  }).format(Number(valor))
}

function formatarDataHora(data: string | null) {
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

function formatarDataHistorico(data: string | null) {
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

function resultadoEntregaLabel(resultado: string) {
  const labels: Record<string, string> = {
    COMPLETA: 'Completa',
    PARCIAL: 'Parcial',
    NAO_ENTREGUE: 'Não entregue',
  }

  return labels[resultado] ?? resultado
}

function statusPendenciaLabel(status: string) {
  const labels: Record<string, string> = {
    ABERTA: 'Aberta',
    PROGRAMADA: 'Programada',
    RESOLVIDA: 'Resolvida',
    CANCELADA: 'Cancelada',
  }

  return labels[status] ?? status
}

function statusLabel(status: StatusEntrega) {
  const labels: Record<StatusEntrega, string> = {
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
</script>

<template>
  <section class="historico-card historico-conteudo">
    <div class="historico-identificacao">
      <div>
        <small>Cliente</small>
        <strong>
          {{ props.historico.entrega.cliente_nome }}
        </strong>
      </div>

      <div>
        <small>Endereço</small>
        <strong>
          {{ props.historico.entrega.endereco }}
        </strong>
      </div>

      <div>
        <small>Status atual</small>
        <strong>
          {{ statusLabel(props.historico.entrega.status as StatusEntrega) }}
        </strong>
      </div>
    </div>

    <section class="historico-secao">
      <div class="historico-titulo">
        <div>
          <h3>Resumo dos produtos</h3>
          <p>
            O saldo restante inclui as pendências.
          </p>
        </div>
      </div>

      <div
        v-for="item in props.historico.itens"
        :key="item.entrega_item_id"
        class="historico-produto"
      >
        <div class="historico-produto-topo">
          <div>
            <strong>
              {{ item.descricao_produto }}
            </strong>

            <small v-if="item.codigo_produto">
              Cód. {{ item.codigo_produto }}
            </small>
          </div>

          <span>
            {{ item.unidade || '' }}
          </span>
        </div>

        <div class="historico-resumo-grid">
          <div class="historico-indicador">
            <small>Comprado</small>
            <strong>
              {{ formatarQuantidade(item.comprado) }}
            </strong>
          </div>

          <div class="historico-indicador entregue">
            <small>Entregue</small>
            <strong>
              {{ formatarQuantidade(item.entregue) }}
            </strong>
          </div>

          <div class="historico-indicador saldo">
            <small>Saldo restante</small>
            <strong>
              {{ formatarQuantidade(item.saldo_restante) }}
            </strong>
          </div>

          <div class="historico-indicador pendente">
            <small>Pendência</small>
            <strong>
              {{ formatarQuantidade(item.pendente) }}
            </strong>
          </div>

          <div class="historico-indicador">
            <small>Em carrada</small>
            <strong>
              {{ formatarQuantidade(item.em_carrada) }}
            </strong>
          </div>

          <div class="historico-indicador">
            <small>Ainda não enviado</small>
            <strong>
              {{ formatarQuantidade(item.nao_enviado) }}
            </strong>
          </div>
        </div>

        <div class="historico-explicacao">
          <span>
            Saldo restante:
            <strong>
              {{ formatarQuantidade(item.saldo_restante) }}
            </strong>
          </span>

          <span>=</span>

          <span>
            Pendência:
            <strong>
              {{ formatarQuantidade(item.pendente) }}
            </strong>
          </span>

          <span>+</span>

          <span>
            Em carrada:
            <strong>
              {{ formatarQuantidade(item.em_carrada) }}
            </strong>
          </span>

          <span>+</span>

          <span>
            Ainda não enviado:
            <strong>
              {{ formatarQuantidade(item.nao_enviado) }}
            </strong>
          </span>
        </div>
      </div>
    </section>

    <section class="historico-secao">
      <div class="historico-titulo">
        <div>
          <h3>Tentativas de entrega</h3>
          <p>
            Carradas, quantidades carregadas e o que
            o cliente realmente recebeu.
          </p>
        </div>
      </div>

      <div
        v-if="props.historico.tentativas.length === 0"
        class="historico-vazio"
      >
        Ainda não houve tentativa de entrega.
      </div>

      <div
        v-for="tentativa in props.historico.tentativas"
        :key="tentativa.tentativa_id"
        class="historico-evento"
      >
        <div class="historico-evento-topo">
          <div>
            <strong>
              {{ formatarDataHora(tentativa.data_hora) }}
            </strong>

            <span>
              Carrada #{{ tentativa.saida_id }}
            </span>
          </div>

          <span
            class="resultado-historico"
            :class="tentativa.resultado.toLowerCase()"
          >
            {{ resultadoEntregaLabel(tentativa.resultado) }}
          </span>
        </div>

        <div
          v-if="tentativa.veiculo"
          class="historico-veiculo"
        >
          Veículo:
          <strong>
            {{ tentativa.veiculo.nome }}
          </strong>

          <span v-if="tentativa.veiculo.placa">
            · {{ tentativa.veiculo.placa }}
          </span>
        </div>

        <div class="historico-itens-tentativa">
          <div class="historico-item-cabecalho">
            <span>Produto</span>
            <span>Carregado</span>
            <span>Entregue</span>
            <span>Não entregue</span>
          </div>

          <div
            v-for="item in tentativa.itens"
            :key="item.entrega_item_id"
            class="historico-item-linha"
          >
            <div>
              <strong>
                {{ item.descricao_produto }}
              </strong>

              <small v-if="item.codigo_produto">
                Cód. {{ item.codigo_produto }}
              </small>
            </div>

            <span>
              {{ formatarQuantidade(item.programado) }}
              {{ item.unidade || '' }}
            </span>

            <strong class="quantidade-entregue">
              {{ formatarQuantidade(item.entregue) }}
              {{ item.unidade || '' }}
            </strong>

            <strong
              :class="{
                'quantidade-faltante':
                  item.nao_entregue > 0,
              }"
            >
              {{ formatarQuantidade(item.nao_entregue) }}
              {{ item.unidade || '' }}
            </strong>
          </div>
        </div>

        <div
          v-if="
            tentativa.motivo_nao_entrega ||
            tentativa.observacao
          "
          class="historico-observacoes"
        >
          <p v-if="tentativa.motivo_nao_entrega">
            <strong>Motivo:</strong>
            {{ tentativa.motivo_nao_entrega }}
          </p>

          <p v-if="tentativa.observacao">
            <strong>Observação:</strong>
            {{ tentativa.observacao }}
          </p>
        </div>
      </div>
    </section>

    <section class="historico-secao">
      <div class="historico-titulo">
        <div>
          <h3>Pendências</h3>
          <p>
            Produtos que saíram para entrega e
            não foram totalmente entregues.
          </p>
        </div>
      </div>

      <div
        v-if="props.historico.pendencias.length === 0"
        class="historico-vazio"
      >
        Esta entrega não possui pendências.
      </div>

      <div
        v-for="pendencia in props.historico.pendencias"
        :key="pendencia.id"
        class="historico-pendencia"
      >
        <div>
          <strong>
            {{ pendencia.descricao_produto }}
          </strong>

          <small>
            Aberta em
            {{ formatarDataHistorico(pendencia.data_abertura) }}
          </small>

          <small v-if="pendencia.data_resolucao">
            Resolvida em
            {{ formatarDataHistorico(pendencia.data_resolucao) }}
          </small>
        </div>

        <div class="historico-pendencia-quantidades">
          <span>
            Original:
            <strong>
              {{ formatarQuantidade(pendencia.quantidade_original) }}
            </strong>
          </span>

          <span>
            Resolvida:
            <strong>
              {{ formatarQuantidade(pendencia.quantidade_resolvida) }}
            </strong>
          </span>

          <span>
            Saldo:
            <strong>
              {{ formatarQuantidade(pendencia.saldo) }}
            </strong>
          </span>
        </div>

        <span
          class="pendencia-status"
          :class="pendencia.status.toLowerCase()"
        >
          {{ statusPendenciaLabel(pendencia.status) }}
        </span>

        <p
          v-if="pendencia.motivo || pendencia.observacao"
          class="historico-pendencia-motivo"
        >
          {{ pendencia.motivo || pendencia.observacao }}
        </p>
      </div>
    </section>
  </section>
</template>

<style scoped src="../../styles/pages/entrega/entrega-historico-painel.css"></style>
