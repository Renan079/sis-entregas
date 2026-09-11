04 — Regras de Negócio

1. Objetivo

Este documento registra as regras que determinam o comportamento do Sistema de Gerenciamento de Entregas.

As regras aqui descritas são referência para:

backend;

frontend;

banco de dados;

testes automatizados;

manutenção;

documentação da API.

Quando houver divergência entre uma regra visual do frontend e uma regra crítica do backend, o backend deve ser considerado a autoridade final.

2. Regras de quantidade

RN-001 — Saldo restante

O saldo restante de um produto representa a quantidade que ainda não foi encerrada logisticamente.

Considerando devoluções:

saldo restante = quantidade solicitada - quantidade entregue - quantidade devolvida

RN-002 — Pendência faz parte do saldo restante

A pendência não deve ser subtraída novamente do saldo restante apresentado ao usuário.

Exemplo:

comprado: 100;

entregue: 50;

pendência: 5.

Saldo restante:

100 - 50 = 50

Dentro desses 50:

5 estão classificados como pendência;

45 ainda não foram enviados.

A pendência é uma classificação de parte do saldo restante, não uma quantidade adicional.

RN-003 — Saldo normal disponível para programação

Para uma nova programação normal:

disponível normal = solicitado - entregue - devolvido - pendências abertas/programadas - reservas ativas

Isso impede que uma quantidade já classificada como pendência seja programada novamente como entrega normal.

RN-004 — Reserva ativa

Uma quantidade programada em carrada PLANEJADA ou EM_ANDAMENTO, ainda sem retorno registrado, deve ser considerada reservada.

Essa quantidade não deve aparecer novamente como saldo livre para outra programação.

RN-005 — Retorno substitui a reserva

Depois que o retorno da carrada é registrado, a quantidade programada deixa de funcionar como reserva.

A partir desse momento, o sistema deve considerar o que realmente aconteceu:

quantidade entregue;

quantidade destinada à reentrega;

quantidade devolvida.

RN-006 — Uma unidade física não pode ser contada duas vezes

A mesma unidade não pode estar simultaneamente classificada como:

saldo normal disponível;

pendência aberta;

reentrega programada;

reserva de uma entrega normal;

quantidade devolvida;

quantidade já entregue.

Essa regra é a base do controle de saldo do sistema.

3. Regras da entrega

RN-007 — Uma entrega representa uma obrigação logística

A entrega representa um pedido ou conjunto de produtos que devem chegar ao cliente.

Cada item possui uma quantidade solicitada que deve ser encerrada por:

entrega efetiva; ou

devolução definitiva.

RN-008 — Uma entrega pode ser dividida entre várias carradas

Uma entrega não precisa ser carregada integralmente em uma única viagem.

Exemplo:

solicitado: 100;

carrada A: 55;

carrada B: 45.

A divisão não cria uma nova entrega.

RN-009 — Programação não pode superar saldo disponível

A quantidade programada de um item não pode ser superior ao saldo realmente disponível para aquela operação.

RN-010 — Quantidade entregue não pode superar carregado

No retorno:

quantidade entregue <= quantidade programada/carregada

O backend deve impedir qualquer valor acima do efetivamente enviado naquela carrada.

RN-011 — Entrega integral não gera diferença

Quando:

entregue = carregado

não existe quantidade restante daquela programação para classificar.

RN-012 — Diferença precisa de destino

Quando:

entregue < carregado

a quantidade não entregue deve receber um destino operacional.

Os destinos permitidos são:

REENTREGA;

DEVOLVIDO.

RN-013 — Itens não devem ser substituídos após existir tentativa

Depois que uma entrega possuir histórico de tentativa, seus itens não devem ser substituídos de forma que altere o significado do histórico já registrado.

O sistema deve preservar a rastreabilidade daquilo que realmente foi carregado e entregue.

RN-014 — Entrega concluída não pode ser cancelada

Uma entrega em estado CONCLUIDA não deve ser cancelada.

Cancelamento é destinado a entregas que ainda possuem operação administrativa compatível com essa ação.

RN-015 — Data de lançamento é imutável como referência histórica

A data de criação da entrega (created_at) representa o momento em que ela foi lançada no sistema.

Alterações posteriores não devem substituir essa data.

Ela pode ser usada para:

identificar pedidos mais antigos;

ordenar a fila operacional;

destacar pedidos que merecem atenção.

RN-016 — Antiguidade não altera automaticamente a prioridade

Uma entrega antiga pode merecer atenção operacional, mas seu tempo de espera não deve alterar automaticamente o campo de prioridade.

Prioridade e antiguidade são conceitos diferentes.

4. Regras da carrada

RN-017 — Carrada planejada pode receber alterações de planejamento

Somente uma carrada PLANEJADA deve receber ou remover:

entregas normais;

reentregas;

programações de itens.

RN-018 — Carrada precisa ter conteúdo

Uma carrada só pode ser iniciada quando possuir pelo menos:

uma entrega normal; ou

uma reentrega.

Uma carrada contendo somente reentregas não está vazia.

RN-019 — Rota preserva ordem

A ordem das paradas deve ser preservada durante a operação e no histórico da carrada.

RN-020 — Região cadastrada e parada livre possuem funções diferentes

Somente uma parada que possua regiao_id pode ser usada para localizar automaticamente:

entregas disponíveis;

pendências disponíveis.

Paradas de texto livre são apenas informativas.

RN-021 — Carrada iniciada entra em operação

Ao iniciar uma carrada, ela deixa o estado de planejamento e passa ao estado operacional correspondente.

A partir desse momento, alterações que comprometam o histórico devem ser bloqueadas.

RN-022 — Carrada não pode ser finalizada com retornos pendentes

Uma carrada não pode ser finalizada enquanto houver retorno obrigatório não registrado.

Isso vale para:

entregas normais;

reentregas.

RN-023 — Cancelamento deve liberar reservas

Quando uma carrada planejada for cancelada, as reservas de entrega normal devem deixar de bloquear saldo.

Pendências programadas naquela carrada devem voltar a um estado compatível com nova programação.

RN-024 — Troca de veículo não altera as obrigações da carrada

Quando operacionalmente permitido, trocar o veículo de uma carrada não deve recriar entregas, pendências ou quantidades.

A alteração deve preservar o restante da operação e sua rastreabilidade.

5. Regras de pendência e reentrega

RN-025 — Quando nasce uma pendência

Uma pendência nasce somente quando uma quantidade:

saiu fisicamente da loja;

não foi entregue;

ainda deve ser enviada novamente ao cliente.

RN-026 — Pendência não é saldo normal

Uma pendência deve permanecer separada do saldo normal disponível.

Isso evita que a mesma quantidade seja programada:

uma vez como pendência; e

outra vez como entrega normal.

RN-027 — Estados da pendência

Uma pendência pode estar em:

ABERTA;

PROGRAMADA;

RESOLVIDA;

CANCELADA.

RN-028 — Pendência aberta

ABERTA significa que existe quantidade aguardando uma futura reentrega.

RN-029 — Pendência programada

PROGRAMADA significa que a reentrega está vinculada a uma carrada.

RN-030 — Sem dupla programação de pendência

Uma pendência PROGRAMADA não pode ser adicionada simultaneamente a outra carrada.

RN-031 — Remoção da carrada devolve pendência à fila

Ao remover uma pendência de uma carrada ainda planejada:

PROGRAMADA → ABERTA

RN-032 — Reentrega completa resolve a quantidade programada

Exemplo:

pendência: 5;

reentrega programada: 5;

entregue: 5.

Resultado:

quantidade resolvida aumenta em 5;

saldo da pendência vira 0;

status: RESOLVIDA.

RN-033 — Reentrega parcial pode continuar

Exemplo:

pendência: 5;

entregue: 3;

restante: 2;

destino: REENTREGA.

Resultado:

3 unidades ficam resolvidas;

2 continuam pendentes;

pendência volta para ABERTA.

RN-034 — Reentrega parcial pode terminar em devolução

Exemplo:

pendência: 5;

entregue: 3;

restante: 2;

destino: DEVOLVIDO.

Resultado:

3 unidades são entregues;

2 unidades são devolvidas;

nenhuma quantidade daquela pendência permanece para reentrega;

pendência fica RESOLVIDA.

RN-035 — Pendência pode ser resolvida em mais de uma carrada

Uma pendência não precisa ser encerrada em uma única reentrega.

A quantidade resolvida deve ser acumulada até que o saldo chegue a zero.

RN-036 — Pendência só é resolvida quando não existe saldo pendente

O estado RESOLVIDA só deve ser utilizado quando a quantidade restante daquela pendência tiver sido totalmente tratada.

6. Regras de devolução

RN-037 — Devolução não gera nova pendência

Quantidade marcada como DEVOLVIDO não deve criar uma nova pendência.

RN-038 — Devolução encerra obrigação logística

Uma quantidade devolvida deixa de fazer parte do saldo que ainda precisa ser entregue ao cliente.

RN-039 — Motivo da devolução deve ser registrado

Quando houver devolução, deve existir um motivo compatível com a operação.

Motivos previstos incluem:

cliente recusou;

cliente desistiu;

produto incorreto;

produto avariado;

quantidade excedente;

outro.

RN-040 — Devolução deve ser rastreável

A devolução deve permanecer associada ao contexto que a originou, permitindo identificar:

entrega;

item;

tentativa ou reentrega;

quantidade;

motivo;

data;

usuário responsável.

7. Regras de status da entrega

RN-041 — Quantidade tratada

Para saber quanto de um item já foi encerrado logisticamente:

quantidade tratada = quantidade entregue + quantidade devolvida

RN-042 — Conclusão logística

Quando toda a quantidade solicitada estiver tratada e não houver obrigação restante, a entrega pode ficar CONCLUIDA.

Exemplo:

comprado: 50;

entregue: 45;

devolvido: 5.

Resultado:

45 + 5 = 50

Logo:

CONCLUIDA

RN-043 — Entrega parcial

Quando já houve quantidade entregue, mas ainda existe obrigação logística restante, a entrega deve permanecer PARCIAL.

Exemplo:

comprado: 50;

entregue: 45;

pendência: 5.

Resultado:

PARCIAL

RN-044 — Tentativa não entregue

Quando uma tentativa não entrega nenhuma unidade, o resultado daquela tentativa pode ser classificado como NAO_ENTREGUE.

O estado geral da entrega deve continuar considerando:

histórico anterior;

saldo restante;

devoluções;

pendências;

futuras programações.

RN-045 — Status não deve ignorar devoluções

Ao determinar se uma entrega está concluída, quantidades devolvidas definitivamente devem ser consideradas encerradas logisticamente.

8. Regras de cliente, região e cadastros operacionais

RN-046 — Cliente cadastrado deve gerar snapshot na entrega

Quando uma entrega utiliza um cliente cadastrado, os dados necessários para a operação devem ser copiados para a própria entrega.

Isso preserva o contexto histórico mesmo que o cadastro do cliente seja alterado futuramente.

RN-047 — Alteração do cliente não deve reescrever histórico antigo

Atualizar nome, apelido ou telefone no cadastro do cliente não deve modificar retroativamente os snapshots de entregas antigas.

RN-048 — Região define associação operacional

A região cadastrada é utilizada para:

filtrar entregas;

organizar rotas;

localizar entregas disponíveis;

localizar pendências disponíveis.

RN-049 — Inativação deve ser preferida à exclusão destrutiva

Em cadastros que possuem histórico operacional, deve-se preferir:

inativação;

cancelamento;

alteração de estado;

em vez de exclusão física que prejudique a rastreabilidade.

9. Regras de autenticação e usuários

RN-050 — Usuário precisa estar autenticado

Operações internas do sistema devem exigir usuário autenticado.

RN-051 — Usuário inativo não pode operar o sistema

Uma conta marcada como inativa deve ser impedida de acessar as funcionalidades protegidas.

Esse bloqueio não deve depender apenas do frontend.

RN-052 — Perfis de acesso

Os perfis atualmente utilizados são:

ADMIN;

OPERADOR.

RN-053 — Funções administrativas exigem ADMIN

Operações administrativas devem validar o perfil no backend.

Esconder um botão no frontend não é suficiente como mecanismo de segurança.

RN-054 — Cadastro público de usuário não é permitido

Novos usuários do sistema não devem ser cadastrados livremente por visitantes.

O cadastro deve ser realizado por usuário autorizado.

RN-055 — Novos usuários operacionais iniciam ativos

No fluxo administrativo atual, novos usuários são criados como:

perfil OPERADOR;

situação ativa.

Alterações posteriores de perfil devem ser realizadas por administração autorizada.

RN-056 — Administrador não deve comprometer a própria conta de forma insegura

A gestão de usuários deve impedir ações que possam remover de forma acidental a capacidade administrativa da própria sessão, de acordo com as proteções implementadas.

RN-057 — Senha nunca deve ser armazenada em texto puro

Senhas devem ser persistidas utilizando mecanismo de hash seguro fornecido pelo framework.

10. Regras de importação e OCR

RN-058 — OCR é auxílio, não fonte definitiva

Resultado de OCR pode conter erro.

Nenhuma extração deve ser considerada automaticamente correta apenas por ter sido reconhecida pelo sistema.

RN-059 — Importação precisa de conferência humana

Antes de criar a entrega definitiva, o usuário deve poder revisar e corrigir os dados extraídos.

RN-060 — Documento pode ser imagem ou PDF

O fluxo de importação pode receber:

imagem única;

múltiplas imagens ordenadas;

PDF com múltiplas páginas.

O limite operacional atualmente adotado é de até 12 imagens ou 12 páginas por importação.

RN-061 — Ordem das páginas precisa ser preservada

Quando várias imagens representam o mesmo documento, a ordem definida no envio deve ser preservada durante o processamento.

RN-062 — Sobreposição entre páginas deve gerar atenção

Quando houver indício de conteúdo repetido entre páginas ou imagens consecutivas, o sistema deve alertar para conferência.

O objetivo é reduzir risco de duplicação de produtos.

RN-063 — Documento importado deve permanecer rastreável

Sempre que possível, o sistema deve manter vínculo entre:

arquivo importado;

processamento realizado;

dados extraídos;

entrega criada.

RN-064 — Cliente pode ser reutilizado ou criado na confirmação

Durante a confirmação da importação, o sistema pode:

utilizar um cliente existente; ou

criar o cadastro necessário.

A decisão deve ocorrer antes da criação definitiva da entrega.

11. Regras de Dashboard e priorização visual

RN-065 — Dashboard não substitui os dados operacionais

Indicadores do Dashboard são uma visão resumida.

A fonte oficial continua sendo os registros operacionais do sistema.

RN-066 — Entregas antigas devem ser identificáveis

Entregas ainda aguardando organização podem ser apresentadas por ordem de antiguidade para facilitar a priorização manual.

RN-067 — Pendências antigas merecem destaque operacional

Pendências abertas há mais tempo podem receber destaque visual no Dashboard.

Esse destaque não altera automaticamente a quantidade, o status ou a prioridade da entrega.

RN-068 — Indicadores devem respeitar o estado real da operação

Contadores de:

entregas realizadas;

carradas do dia;

entregas sem carrada;

reentregas;

pedidos com problema;

devem ser calculados a partir dos estados e eventos efetivamente registrados.

12. Regras de relatórios e tempo

RN-069 — Períodos devem respeitar o fuso operacional

Consultas por período devem considerar o fuso configurado para a operação.

Configuração atual:

America/Fortaleza

RN-070 — Datas locais devem ser convertidas corretamente para consulta

Quando o banco utilizar timestamps em referência diferente da apresentação local, os limites de início e fim de período devem ser convertidos corretamente antes da consulta.

RN-071 — Relatório não deve alterar dados operacionais

Consultas e consolidações do módulo de relatórios são operações de leitura e não devem alterar o estado de entregas, carradas ou pendências.

13. Regras de histórico e auditoria

RN-072 — Histórico deve refletir o fato ocorrido

Históricos não devem ser recalculados de forma que alterem o significado do que aconteceu em uma operação passada.

Quando necessário, snapshots e eventos devem preservar dados históricos.

RN-073 — Histórico da entrega deve explicar o saldo

Por produto, o histórico deve permitir entender:

quantidade comprada;

quantidade entregue;

quantidade devolvida;

saldo restante;

pendência;

quantidade em carrada;

quantidade ainda não enviada.

RN-074 — Histórico da carrada deve separar naturezas diferentes

O histórico da carrada deve diferenciar:

entregas normais;

reentregas;

pendências geradas;

devoluções;

resultados;

eventos operacionais.

RN-075 — Carrada apenas com reentrega não está vazia

Se uma carrada possui reentregas e nenhuma entrega normal, o histórico deve apresentar essas reentregas normalmente.

Não deve ser exibida como carrada sem conteúdo.

RN-076 — Ações críticas devem registrar responsável e momento

Sempre que aplicável, ações críticas devem possuir informação suficiente para identificar:

usuário responsável;

data;

hora;

operação realizada.

14. Exemplos oficiais

Exemplo A — Entrega parcial com reentrega

Pedido:

50 argamassas.

Carrada:

carregado: 50;

entregue: 45;

faltou: 5;

destino: REENTREGA.

Resultado:

entregue: 45;

devolvido: 0;

pendência: 5;

status: PARCIAL.

Exemplo B — Entrega parcial com devolução

Pedido:

50 argamassas.

Carrada:

carregado: 50;

entregue: 45;

faltou: 5;

destino: DEVOLVIDO.

Resultado:

entregue: 45;

devolvido: 5;

pendência: 0;

saldo logístico: 0;

status: CONCLUIDA.

Exemplo C — Pedido dividido entre carradas

Pedido:

100 blocos.

Carrada 1:

programado: 55;

entregue: 50;

reentrega: 5.

Situação:

comprado: 100;

entregue: 50;

pendência: 5;

ainda não enviado: 45;

saldo restante: 50.

A pendência de 5 não deve ser novamente subtraída do saldo restante.

Exemplo D — Reentrega posteriormente devolvida

Situação inicial:

pendência: 5.

Nova carrada:

reentrega programada: 5;

entregue: 3;

faltou: 2;

destino: DEVOLVIDO.

Resultado:

3 unidades adicionais entregues;

2 unidades devolvidas;

pendência resolvida;

nenhuma nova reentrega criada.

Exemplo E — Reserva de saldo

Pedido:

solicitado: 100;

entregue anteriormente: 20;

pendência aberta: 5.

Saldo restante:

100 - 20 = 80

Saldo normal antes de nova reserva:

80 - 5 = 75

Se uma carrada planejada reservar 30:

75 - 30 = 45

Logo:

5 continuam como pendência;

30 estão reservados em carrada;

45 permanecem disponíveis como saldo normal.

15. Regra geral de segurança operacional

Sempre que houver dúvida entre:

saldo normal;

reserva;

pendência;

reentrega;

devolução;

quantidade entregue;

o sistema deve priorizar a integridade do saldo e evitar dupla contagem.

Uma mesma unidade física não pode representar simultaneamente duas obrigações logísticas diferentes.

16. Regra geral de segurança de acesso

Permissões visuais do frontend são apenas uma camada de experiência.

Toda operação sensível deve ser validada novamente no backend.

Isso se aplica especialmente a:

gestão de usuários;

alteração de perfis;

ativação e desativação de contas;

redefinição de senha;

operações administrativas futuras.

17. Manutenção deste documento

Sempre que uma regra de negócio for alterada no código, deve-se verificar impacto em:

Services;

Controllers;

Form Requests;

Models;

Resources;

tipos TypeScript;

telas;

testes automatizados;

endpoints documentados.

Mudanças de regra não devem ficar registradas apenas no código.