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
- histórico da carrada;
- clientes e regiões utilizados na operação;
- usuários responsáveis pelas operações do sistema;
- importação de pedidos por foto ou PDF;
- visão operacional por dashboard;
- relatórios para acompanhamento da operação.

O sistema não depende, neste momento, de integração direta com o ERP ou sistema comercial da loja. Quando necessário, os dados das entregas podem ser cadastrados manualmente ou importados a partir de documentos digitalizados.

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
- dificuldade de identificar pedidos antigos que ainda aguardam atendimento;
- dificuldade de reorganizar entregas quando existe falta de motorista ou problema em veículo;
- retrabalho ao transcrever dados de pedidos para o controle logístico;
- falta de uma visão consolidada da situação das entregas do dia e das pendências em aberto.

O sistema centraliza essas informações e permite que a operação seja acompanhada com maior clareza e rastreabilidade.

---

## 4. Objetivo principal

Permitir que a loja saiba, a qualquer momento:

> O que precisa ser entregue, o que já foi programado, o que já saiu da loja, o que foi realmente entregue, o que voltou para reentrega, o que foi devolvido e em qual carrada cada operação aconteceu.

Além disso, o sistema deve ajudar o responsável pela logística a identificar rapidamente quais pedidos precisam de maior atenção, incluindo entregas antigas, pendências abertas e carradas ainda não concluídas.

---

## 5. Usuários do sistema

O sistema é voltado inicialmente para os funcionários responsáveis pela organização e acompanhamento das entregas da loja.

Existem atualmente dois perfis de acesso:

### 5.1 ADMIN

O administrador possui acesso às funções operacionais e também à gestão de usuários.

Pode, entre outras operações:

- consultar e operar entregas;
- organizar carradas;
- acompanhar pendências e reentregas;
- consultar dashboard e relatórios;
- cadastrar novos usuários;
- editar usuários;
- alterar perfil de acesso;
- ativar ou desativar usuários;
- redefinir senhas.

### 5.2 OPERADOR

O operador utiliza as funções necessárias para o trabalho diário da logística, sem acesso às operações administrativas de gestão de usuários.

Entre as principais atividades operacionais estão:

- cadastrar e editar entregas;
- importar pedidos;
- organizar carradas;
- definir rotas;
- escolher produtos e quantidades que irão em cada carrada;
- iniciar e finalizar carradas;
- registrar retornos;
- registrar reentregas;
- registrar devoluções;
- consultar históricos;
- acompanhar dashboard, pendências e informações operacionais disponíveis ao seu perfil.

Usuários desativados não devem conseguir acessar o sistema.

---

## 6. Conceitos principais

### 6.1 Entrega

Representa um pedido ou conjunto de produtos que precisam chegar até um cliente.

Uma entrega pode ser dividida entre várias carradas.

Exemplo:

- Pedido: 100 blocos;
- Carrada 1: 55 blocos;
- Carrada 2: 45 blocos.

A entrega também mantém sua data de lançamento no sistema, permitindo identificar pedidos mais antigos e dar atenção àqueles que estão aguardando há mais tempo.

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

### 6.8 Cliente

Representa o cadastro reutilizável do comprador ou destinatário da entrega.

Uma entrega mantém também os dados necessários ao contexto operacional para que seu histórico continue compreensível mesmo que o cadastro do cliente seja alterado posteriormente.

---

### 6.9 Região

Representa uma área utilizada para organizar entregas e auxiliar a montagem de rotas e carradas.

As regiões permitem agrupar pedidos que podem ser atendidos dentro de um mesmo percurso.

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

Isso permite representar situações reais da operação e evita que uma mesma quantidade seja programada duas vezes.

Outro diferencial é a rastreabilidade. Entregas, carradas, tentativas, pendências, reentregas, devoluções e eventos relevantes possuem histórico suficiente para explicar como a operação chegou ao estado atual.

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

O produto atualmente cobre os seguintes módulos e fluxos:

### Acesso e usuários

- login e logout;
- autenticação por sessão com Laravel Sanctum;
- usuários ativos e inativos;
- perfis `ADMIN` e `OPERADOR`;
- cadastro de usuários por administrador;
- listagem e edição de usuários;
- alteração de perfil;
- ativação e desativação;
- redefinição de senha.

### Cadastros operacionais

- clientes;
- histórico de clientes;
- regiões;
- veículos utilizados na operação.

### Entregas

- cadastro manual de entregas;
- importação de entregas por documento;
- itens da entrega;
- prioridade;
- status;
- edição e cancelamento conforme regras de negócio;
- data de lançamento;
- ordenação por entregas mais antigas ou mais recentes;
- divisão de itens entre carradas;
- saldo disponível para programação;
- histórico da entrega.

### Carradas

- criação de carradas;
- rota ordenada;
- seleção de veículo;
- associação de entregas;
- associação de reentregas;
- programação parcial de produtos;
- início da carrada;
- registro de retornos;
- finalização e cancelamento conforme regras de negócio;
- histórico da carrada;
- eventos da carrada.

### Pendências e devoluções

- criação de pendências a partir do retorno;
- programação de reentrega;
- retorno de reentrega;
- resolução parcial ou total;
- devoluções;
- histórico e movimentações relacionadas.

### Dashboard

- entregas realizadas no mês;
- pedidos aguardando carrada;
- carradas do dia;
- reentregas abertas;
- pedidos com problema;
- volume por região;
- pendências mais antigas;
- pedidos antigos ainda sem carrada;
- visão rápida da situação operacional.

### Relatórios

O sistema já possui um módulo inicial de relatórios para acompanhamento da operação, que continua em evolução e refinamento conforme novas necessidades forem identificadas.

---

## 11. Evoluções futuras

Funcionalidades que podem ser desenvolvidas ou ampliadas em etapas posteriores incluem:

- gestão visual mais completa de equipe por carrada;
- tratamento mais amplo de ocorrências operacionais;
- reprogramação assistida em casos de falta de motorista ou indisponibilidade de veículo;
- planejamento mais avançado de rotas;
- análise de custo de reentrega;
- indicadores gerenciais adicionais;
- ampliação do módulo de relatórios;
- notificações e alertas operacionais;
- integração com outros sistemas, caso exista API disponível;
- automações adicionais para reduzir ainda mais a digitação manual.

---

## 12. Direção do produto

O sistema deve continuar evoluindo com foco em três princípios:

1. **Representar a operação real da loja**  
   O sistema deve se adaptar ao modo como as entregas realmente acontecem, incluindo divisões, faltas, reentregas, devoluções e imprevistos.

2. **Preservar rastreabilidade**  
   Alterações relevantes devem deixar informações suficientes para que seja possível entender quem fez determinada operação, quando ela aconteceu e qual foi o resultado.

3. **Facilitar a decisão operacional**  
   A interface deve ajudar o responsável a perceber rapidamente o que está atrasado, pendente, programado, em andamento ou concluído, reduzindo a dependência de memória, papel e conferências manuais.
