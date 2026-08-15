# 04 — Regras de Negócio

## 1. Objetivo

Este documento registra as regras que determinam como entregas, carradas, pendências, reentregas e devoluções devem funcionar.

As regras abaixo devem ser consideradas fonte de referência para manutenção do backend, frontend e testes.

---

# 2. Regras de quantidade

## RN-001 — Saldo restante

O saldo restante de um produto representa o que ainda não foi encerrado logisticamente.

Com devoluções:

`saldo restante = quantidade comprada - quantidade entregue - quantidade devolvida`

---

## RN-002 — Pendência faz parte do saldo restante

A pendência não deve ser subtraída novamente do saldo restante exibido ao usuário.

Exemplo:

- comprado: 100;
- entregue: 50;
- pendência: 5.

Saldo restante:

`100 - 50 = 50`

Dentro desses 50:

- 5 são pendência;
- 45 ainda não foram enviados.

---

## RN-003 — Saldo normal disponível para programação

Para novas entregas normais:

`disponível normal = solicitado - entregue - devolvido - pendências abertas/programadas - reservas ativas`

Isso impede que uma quantidade já classificada como pendência seja programada novamente como entrega normal.

---

## RN-004 — Reserva ativa

Uma quantidade programada em carrada `PLANEJADA` ou `EM_ANDAMENTO`, ainda sem retorno, deve ser considerada reservada.

---

## RN-005 — Retorno substitui reserva

Depois que o retorno da carrada for registrado, a quantidade programada deixa de funcionar como reserva.

A partir daí, o sistema considera o que realmente aconteceu:

- entregue;
- reentrega;
- devolução.

---

# 3. Regras da carrada

## RN-006 — Carrada planejada

Somente uma carrada `PLANEJADA` pode receber ou remover entregas e reentregas.

---

## RN-007 — Carrada precisa ter conteúdo

Uma carrada só pode ser iniciada quando possuir pelo menos:

- uma entrega normal; ou
- uma reentrega.

---

## RN-008 — Rota preserva ordem

A ordem das paradas deve ser preservada no histórico.

---

## RN-009 — Região cadastrada x parada livre

Somente uma parada que possua `regiao_id` pode ser usada para localizar automaticamente:

- entregas da rota;
- pendências da rota.

Paradas de texto livre são apenas informativas.

---

## RN-010 — Finalização

Uma carrada não pode ser finalizada enquanto houver retorno obrigatório não registrado.

Isso vale para:

- entrega normal;
- reentrega.

---

# 4. Regras de entrega normal

## RN-011 — Programação parcial

Uma entrega pode ser dividida entre várias carradas.

Exemplo:

- solicitado: 100;
- carrada A: 55;
- carrada B: 45.

---

## RN-012 — Limite de carregamento

A quantidade programada de um item não pode ser maior que seu saldo disponível.

---

## RN-013 — Entregue não pode superar carregado

No retorno:

`quantidade entregue <= quantidade carregada`

---

## RN-014 — Entrega integral

Quando:

`entregue = carregado`

não existe diferença a classificar.

---

## RN-015 — Diferença precisa de destino

Quando:

`entregue < carregado`

a quantidade restante precisa ser classificada como:

- `REENTREGA`; ou
- `DEVOLVIDO`.

---

# 5. Regras de pendência e reentrega

## RN-016 — Quando nasce uma pendência

Uma pendência nasce somente quando uma quantidade que saiu fisicamente da loja não foi entregue e ainda deverá ser enviada novamente.

---

## RN-017 — Pendência não é saldo normal

Uma pendência deve permanecer separada do saldo normal disponível.

Motivo:

evitar que a mesma quantidade seja adicionada uma vez como pendência e outra vez como saldo normal.

---

## RN-018 — Estados da pendência

Uma pendência pode estar:

- `ABERTA`;
- `PROGRAMADA`;
- `RESOLVIDA`;
- `CANCELADA`.

---

## RN-019 — Pendência aberta

`ABERTA` significa que existe quantidade aguardando uma futura reentrega.

---

## RN-020 — Pendência programada

`PROGRAMADA` significa que a reentrega está vinculada a uma carrada.

---

## RN-021 — Sem dupla programação

Uma pendência `PROGRAMADA` não pode ser adicionada a outra carrada ao mesmo tempo.

---

## RN-022 — Remoção da carrada

Ao remover uma pendência de uma carrada ainda planejada:

`PROGRAMADA → ABERTA`

---

## RN-023 — Reentrega completa

Exemplo:

- pendência: 5;
- reentrega programada: 5;
- entregue: 5.

Resultado:

- quantidade resolvida aumenta em 5;
- saldo da pendência vira 0;
- status: `RESOLVIDA`.

---

## RN-024 — Reentrega parcial com nova tentativa

Exemplo:

- pendência: 5;
- entregue: 3;
- restante: 2;
- destino: `REENTREGA`.

Resultado:

- 3 ficam resolvidos;
- 2 continuam pendentes;
- pendência volta para `ABERTA`.

---

## RN-025 — Reentrega parcial com devolução

Exemplo:

- pendência: 5;
- entregue: 3;
- restante: 2;
- destino: `DEVOLVIDO`.

Resultado:

- 3 entregues;
- 2 devolvidos;
- nenhuma quantidade daquela pendência permanece para reentrega;
- pendência fica `RESOLVIDA`.

---

# 6. Regras de devolução

## RN-026 — Devolução não gera pendência

Quantidade marcada como `DEVOLVIDO` não deve criar pendência.

---

## RN-027 — Devolução encerra obrigação logística

Uma quantidade devolvida deixa de fazer parte do saldo que ainda precisa ser entregue.

---

## RN-028 — Motivo obrigatório

Quando houver devolução, deve existir um motivo.

Motivos previstos:

- cliente recusou;
- cliente desistiu;
- produto incorreto;
- produto avariado;
- quantidade excedente;
- outro.

---

## RN-029 — Devolução pertence a uma ocorrência real

A devolução deve estar associada ao contexto que a originou, permitindo rastrear:

- entrega;
- item;
- tentativa ou reentrega;
- quantidade;
- motivo;
- data;
- usuário responsável.

---

# 7. Regras de status da entrega

## RN-030 — Quantidade tratada

Para saber se um pedido ainda possui obrigação logística:

`quantidade tratada = quantidade entregue + quantidade devolvida`

---

## RN-031 — Conclusão logística

Quando toda quantidade solicitada estiver tratada, e não houver obrigação de reentrega restante, a entrega pode ficar `CONCLUIDA`.

Exemplo:

- comprado: 50;
- entregue: 45;
- devolvido: 5.

Resultado:

`45 + 5 = 50`

Logo:

`CONCLUIDA`

---

## RN-032 — Parcial

Quando já houve entrega, mas ainda existe quantidade que precisa chegar ao cliente, o pedido deve permanecer parcial.

Exemplo:

- comprado: 50;
- entregue: 45;
- pendência: 5.

Resultado:

`PARCIAL`

---

## RN-033 — Não entregue

Quando uma tentativa não entrega nenhuma unidade, o resultado da tentativa pode ser classificado como `NAO_ENTREGUE`.

O estado geral do pedido ainda deve considerar seu histórico e saldo atual.

---

# 8. Regras de histórico

## RN-034 — Histórico deve refletir o fato ocorrido

Históricos não devem ser recalculados de forma que altere o significado do que aconteceu em uma carrada passada.

Sempre que necessário, snapshots e eventos devem preservar dados históricos.

---

## RN-035 — Carrada com apenas reentrega não está vazia

Se uma carrada possui reentregas e nenhuma entrega normal, o histórico deve mostrar as reentregas.

Não deve exibir simplesmente:

> Esta carrada não possui entregas.

---

## RN-036 — Histórico da entrega

Por produto, o histórico deve permitir entender:

- quantidade comprada;
- quantidade entregue;
- quantidade devolvida;
- saldo restante;
- pendência;
- quantidade atualmente em carrada;
- quantidade ainda não enviada.

---

## RN-037 — Histórico da carrada

O histórico da carrada deve diferenciar:

- entregas normais;
- reentregas;
- pendências geradas;
- devoluções;
- resultados;
- eventos operacionais.

---

# 9. Exemplos oficiais

## Exemplo A — Entrega parcial com reentrega

Pedido:

- 50 argamassas.

Carrada:

- carregado: 50;
- entregue: 45;
- faltou: 5;
- destino: REENTREGA.

Resultado:

- entregue: 45;
- devolvido: 0;
- pendência: 5;
- status: PARCIAL.

---

## Exemplo B — Entrega parcial com devolução

Pedido:

- 50 argamassas.

Carrada:

- carregado: 50;
- entregue: 45;
- faltou: 5;
- destino: DEVOLVIDO.

Resultado:

- entregue: 45;
- devolvido: 5;
- pendência: 0;
- saldo logístico: 0;
- status: CONCLUIDA.

---

## Exemplo C — Pedido dividido entre carradas

Pedido:

- 100 blocos.

Carrada 1:

- programado: 55;
- entregue: 50;
- reentrega: 5.

Situação:

- comprado: 100;
- entregue: 50;
- pendência: 5;
- ainda não enviado: 45;
- saldo restante: 50.

---

## Exemplo D — Reentrega posteriormente devolvida

Situação inicial:

- pendência: 5.

Nova carrada:

- reentrega programada: 5;
- entregue: 3;
- faltou: 2;
- destino: DEVOLVIDO.

Resultado:

- 3 unidades adicionais entregues;
- 2 unidades devolvidas;
- pendência resolvida;
- nenhuma nova reentrega criada.

---

# 10. Regra geral de segurança

Sempre que houver dúvida entre:

- saldo normal;
- pendência;
- reentrega;
- devolução;

o sistema deve evitar dupla contagem.

Uma mesma unidade física não pode estar simultaneamente classificada em duas obrigações logísticas diferentes.
