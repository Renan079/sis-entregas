# 02 — Requisitos do Sistema

## 1. Objetivo

Este documento registra os requisitos funcionais e não funcionais do Sistema de Gerenciamento de Entregas.

Os requisitos estão organizados considerando o comportamento atual do sistema e as regras já definidas para a operação.

---

# 2. Requisitos funcionais

## RF-001 — Cadastrar entrega

O sistema deve permitir cadastrar uma entrega contendo:

- número do pedido;
- cliente;
- região;
- endereço;
- referência;
- prioridade;
- observação;
- um ou mais produtos.

Cada produto deve possuir:

- código;
- descrição;
- unidade;
- quantidade solicitada;
- observação.

---

## RF-002 — Editar entrega

O sistema deve permitir editar uma entrega enquanto ela não estiver em estado final incompatível com edição.

---

## RF-003 — Cancelar entrega

O sistema deve permitir cancelar uma entrega quando permitido pelas regras de negócio.

---

## RF-004 — Cadastrar carrada

O sistema deve permitir criar uma carrada informando:

- data;
- região principal, quando aplicável;
- veículo;
- rota;
- observação.

---

## RF-005 — Definir rota ordenada

O sistema deve permitir cadastrar uma rota contendo uma sequência ordenada de paradas.

Cada parada pode ser:

- uma região cadastrada;
- uma parada livre apenas para referência textual.

A ordem deve ser preservada.

---

## RF-006 — Buscar carrada

O sistema deve permitir localizar uma carrada pelo número.

Exemplos aceitos:

- `35`;
- `#35`;
- `Carrada 35`.

---

## RF-007 — Listar entregas disponíveis pela rota

Ao montar uma carrada, o sistema deve localizar entregas normais disponíveis nas regiões cadastradas da rota.

Paradas de texto livre não devem ser usadas para associação automática com entregas.

---

## RF-008 — Adicionar entrega à carrada

O sistema deve permitir adicionar uma entrega à carrada.

---

## RF-009 — Selecionar produtos da entrega

Ao adicionar uma entrega, o sistema deve permitir selecionar quais produtos irão naquela carrada.

---

## RF-010 — Programar quantidade parcial

O sistema deve permitir programar somente parte do saldo disponível de um produto.

Exemplo:

- pedido: 100;
- levar agora: 55;
- saldo ainda não programado: 45.

---

## RF-011 — Impedir programação acima do saldo

O sistema não deve permitir programar quantidade superior ao saldo realmente disponível.

---

## RF-012 — Remover entrega de carrada planejada

O sistema deve permitir remover uma entrega de uma carrada enquanto ela estiver em estado `PLANEJADA`.

---

## RF-013 — Iniciar carrada

O sistema deve permitir iniciar uma carrada planejada que possua pelo menos uma entrega normal ou uma reentrega.

---

## RF-014 — Registrar retorno de entrega

O sistema deve permitir registrar o resultado de cada entrega da carrada em andamento.

Para cada produto devem ser informados:

- quantidade carregada;
- quantidade realmente entregue;
- destino da quantidade não entregue, quando houver;
- observação opcional.

---

## RF-015 — Definir destino da quantidade não entregue

Quando:

`quantidade entregue < quantidade carregada`

o sistema deve exigir o destino da diferença.

Destinos permitidos:

- `REENTREGA`;
- `DEVOLVIDO`.

---

## RF-016 — Criar pendência

Quando o destino for `REENTREGA`, o sistema deve criar uma pendência com a quantidade não entregue.

---

## RF-017 — Registrar devolução

Quando o destino for `DEVOLVIDO`, o sistema deve registrar uma devolução e não criar pendência daquela quantidade.

---

## RF-018 — Registrar motivo da devolução

O sistema deve permitir registrar o motivo da devolução.

Motivos previstos:

- cliente recusou;
- cliente desistiu;
- produto incorreto;
- produto avariado;
- quantidade excedente;
- outro.

---

## RF-019 — Listar pendências pela rota

Ao montar uma carrada, o sistema deve listar separadamente as pendências abertas das regiões cadastradas no percurso.

---

## RF-020 — Adicionar reentrega à carrada

O sistema deve permitir adicionar uma pendência aberta a uma carrada planejada.

Após a programação, a pendência deve ficar com status `PROGRAMADA`.

---

## RF-021 — Impedir duplicidade de reentrega

Uma pendência não pode estar programada simultaneamente em duas carradas.

---

## RF-022 — Remover reentrega de carrada

Enquanto a carrada estiver planejada, o sistema deve permitir remover uma reentrega.

A pendência deve voltar ao estado `ABERTA`.

---

## RF-023 — Registrar retorno de reentrega

O sistema deve permitir registrar o retorno de uma reentrega.

Exemplo:

- pendência programada: 5;
- entregue: 3;
- restante: 2.

Para o restante deve ser possível escolher:

- nova reentrega;
- devolução.

---

## RF-024 — Resolver pendência

Quando toda a quantidade pendente for tratada, a pendência deve ser marcada como `RESOLVIDA`.

---

## RF-025 — Finalizar carrada

O sistema deve permitir finalizar uma carrada somente depois que todos os retornos obrigatórios forem registrados.

Isso inclui:

- entregas normais;
- reentregas.

---

## RF-026 — Histórico da entrega

O sistema deve apresentar o histórico da entrega com informações por produto.

Devem ser exibidos, quando aplicável:

- comprado;
- entregue;
- devolvido;
- saldo restante;
- pendência;
- em carrada;
- ainda não enviado;
- tentativas realizadas.

---

## RF-027 — Histórico da carrada

O histórico da carrada deve exibir:

- dados da carrada;
- rota;
- entregas normais;
- reentregas;
- resultados;
- pendências geradas;
- devoluções;
- eventos da carrada.

Uma carrada contendo apenas reentregas não pode ser apresentada como vazia.

---

## RF-028 — Registrar eventos da carrada

O sistema deve registrar eventos relevantes, como:

- carrada criada;
- entrega adicionada;
- entrega atualizada;
- entrega removida;
- pendência adicionada;
- pendência removida;
- carrada iniciada;
- carrada finalizada;
- veículo trocado;
- carrada cancelada;
- resultado registrado.

---

# 3. Requisitos não funcionais

## RNF-001 — Integridade dos dados

O sistema deve impedir dupla contagem de quantidades.

---

## RNF-002 — Persistência

Os dados operacionais devem ser armazenados em PostgreSQL.

---

## RNF-003 — Segurança

As rotas da API devem ser protegidas por autenticação.

O projeto utiliza autenticação baseada em Laravel Sanctum.

---

## RNF-004 — Rastreabilidade

Operações relevantes devem manter histórico suficiente para explicar o estado atual de uma entrega ou carrada.

---

## RNF-005 — Consistência transacional

Operações que alteram múltiplas entidades relacionadas devem ser executadas dentro de transações de banco de dados quando necessário.

---

## RNF-006 — Interface responsiva

A interface deve continuar utilizável em diferentes larguras de tela.

---

## RNF-007 — Usabilidade operacional

O sistema deve usar linguagem próxima da operação real da loja.

Exemplos:

- carrada;
- reentrega;
- devolvido;
- faltou;
- carregado;
- entregue.

---

## RNF-008 — Evolução

A arquitetura deve permitir inclusão futura de:

- OCR;
- importação de documentos;
- relatórios;
- dashboards;
- ocorrências;
- integrações externas.

---

# 4. Requisitos ainda não concluídos

Os seguintes itens fazem parte da visão futura e não devem ser tratados como núcleo finalizado enquanto não forem implementados e testados:

- OCR de nota;
- integração automática com ERP;
- otimização automática de rota;
- cálculo automático de capacidade do veículo;
- cálculo automático de custo de reentrega;
- dashboard gerencial completo;
- relatórios consolidados;
- gestão operacional completa da equipe.
