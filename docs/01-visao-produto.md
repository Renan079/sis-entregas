# 01 — Visão do Produto

## 1. Nome do projeto

**Sistema de Gerenciamento de Entregas para Loja de Material de Construção**

## 2. Visão geral

O sistema foi criado para substituir o controle manual de entregas feito em papel por um processo digital, rastreável e organizado.

O foco principal é permitir que a loja controle:

- pedidos que ainda precisam ser entregues;
- formação de carradas;
- divisão de um pedido entre várias carradas;
- rota de cada carrada;
- produtos efetivamente carregados;
- retorno das entregas;
- entregas completas e parciais;
- pendências e reentregas;
- devoluções;
- histórico do pedido;
- histórico da carrada.

O sistema não depende, neste momento, de integração direta com o ERP ou sistema comercial da loja.

---

## 3. Problema que o produto resolve

O processo manual apresenta problemas como:

- uso de papéis e anotações para organizar entregas;
- dificuldade de acompanhar o que já foi entregue;
- dificuldade de identificar produtos que ficaram faltando;
- risco de esquecer reentregas;
- dificuldade de saber em qual carrada um pedido foi enviado;
- falta de histórico confiável;
- dificuldade de visualizar entregas por região;
- dificuldade de reorganizar entregas quando existe falta de motorista ou problema em veículo.

O sistema centraliza essas informações.

---

## 4. Objetivo principal

Permitir que a loja saiba, a qualquer momento:

> O que precisa ser entregue, o que já foi enviado, o que foi realmente entregue, o que voltou para reentrega, o que foi devolvido e em qual carrada cada operação aconteceu.

---

## 5. Usuário principal

O sistema é voltado inicialmente para o operador responsável pela organização das entregas da loja.

Esse usuário pode:

- cadastrar entregas;
- organizar carradas;
- definir rotas;
- escolher produtos e quantidades que irão em cada carrada;
- iniciar e finalizar carradas;
- registrar retornos;
- registrar reentregas;
- registrar devoluções;
- consultar históricos.

---

## 6. Conceitos principais

### 6.1 Entrega

Representa um pedido ou conjunto de produtos que precisam chegar até um cliente.

Uma entrega pode ser dividida entre várias carradas.

Exemplo:

- Pedido: 100 blocos;
- Carrada 1: 55 blocos;
- Carrada 2: 45 blocos.

---

### 6.2 Carrada

Representa uma saída física de um veículo da loja.

Uma carrada pode conter:

- várias entregas;
- partes de uma entrega;
- reentregas;
- uma rota ordenada;
- um veículo;
- histórico próprio.

---

### 6.3 Rota

É o percurso planejado da carrada.

Exemplo:

1. Gameleira;
2. Serra de São João;
3. Malhada Redonda;
4. Banco de Areia.

A ordem da rota deve ser preservada no histórico da carrada.

---

### 6.4 Pendência

Uma pendência representa uma quantidade que saiu da loja para ser entregue, mas não foi entregue e ainda deve ser enviada novamente ao cliente.

Exemplo:

- carregado: 55;
- entregue: 50;
- não entregue: 5;
- destino: **REENTREGA**;
- pendência criada: 5.

A pendência não é saldo normal do pedido. Ela é tratada separadamente para evitar programação duplicada.

---

### 6.5 Reentrega

É uma pendência programada em uma nova carrada.

Uma reentrega pode ser:

- totalmente entregue;
- parcialmente entregue;
- novamente marcada para reentrega;
- parcialmente ou totalmente devolvida.

---

### 6.6 Devolução

Representa uma quantidade que não será mais entregue ao cliente.

Exemplos:

- cliente recusou;
- cliente desistiu;
- produto incorreto;
- produto avariado;
- quantidade excedente;
- outro motivo.

Uma devolução não gera nova pendência.

---

## 7. Estados importantes do fluxo

### Entrega

Estados utilizados:

- `PENDENTE`
- `PROGRAMADA`
- `EM_SAIDA`
- `PARCIAL`
- `CONCLUIDA`
- `NAO_ENTREGUE`
- `CANCELADA`

### Carrada

Estados utilizados:

- `PLANEJADA`
- `EM_ANDAMENTO`
- `FINALIZADA`
- `CANCELADA`

### Pendência

Estados utilizados:

- `ABERTA`
- `PROGRAMADA`
- `RESOLVIDA`
- `CANCELADA`

---

## 8. Diferencial do sistema

O sistema não considera apenas se um pedido foi “entregue ou não”.

Ele diferencia:

- quantidade comprada;
- quantidade entregue;
- quantidade devolvida;
- quantidade pendente;
- quantidade em carrada;
- quantidade ainda não enviada.

Isso permite representar situações reais da operação.

---

## 9. Exemplo completo

Pedido:

- 50 argamassas.

Primeira carrada:

- carregado: 50;
- entregue: 45;
- faltaram: 5;
- destino: REENTREGA.

Resultado:

- entregue: 45;
- pendência: 5;
- saldo restante: 5.

Segunda carrada:

- reentrega: 5;
- entregue: 3;
- faltaram: 2;
- destino: DEVOLVIDO.

Resultado final:

- comprado: 50;
- entregue: 48;
- devolvido: 2;
- pendência: 0;
- saldo logístico: 0;
- entrega concluída.

---

## 10. Escopo atual

O núcleo atualmente trabalhado cobre:

- cadastro de entregas;
- itens da entrega;
- regiões;
- veículos;
- carradas;
- rota ordenada;
- divisão de itens entre carradas;
- saldo disponível para programação;
- retorno da entrega;
- pendências;
- reentregas;
- devoluções;
- histórico da entrega;
- histórico da carrada;
- eventos da carrada.

---

## 11. Evoluções futuras

Funcionalidades planejadas para etapas posteriores incluem:

- importação de notas por foto ou PDF;
- OCR;
- dashboard operacional;
- indicadores;
- relatórios;
- gestão visual de equipe;
- ocorrências operacionais;
- planejamento mais avançado de rotas;
- análise de custos de reentrega;
- integração com outros sistemas, caso exista API disponível.
