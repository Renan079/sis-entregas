02 — Requisitos do Sistema

1. Objetivo

Este documento registra os requisitos funcionais e não funcionais do Sistema de Gerenciamento de Entregas.

Os requisitos representam o comportamento atual do sistema e servem como referência para manutenção do backend, frontend, banco de dados, testes e documentação técnica.

2. Requisitos funcionais

2.1 Entregas

RF-001 — Cadastrar entrega

O sistema deve permitir cadastrar uma entrega contendo:

número do pedido;

cliente;

região;

endereço;

referência;

prioridade;

observação;

um ou mais produtos.

Cada produto deve possuir:

código, quando disponível;

descrição;

unidade;

quantidade solicitada;

observação, quando necessária.

RF-002 — Editar entrega

O sistema deve permitir editar uma entrega enquanto ela não estiver em estado final incompatível com edição.

Quando a entrega já possuir histórico de tentativa, os itens não devem ser substituídos de forma que comprometa o histórico operacional.

RF-003 — Cancelar entrega

O sistema deve permitir cancelar uma entrega quando permitido pelas regras de negócio.

Uma entrega concluída não deve ser cancelada.

RF-004 — Listar e pesquisar entregas

O sistema deve permitir listar entregas com paginação e pesquisar por informações relevantes, incluindo:

número do pedido;

nome do cliente;

apelido do cliente;

telefone;

endereço.

RF-005 — Filtrar entregas

O sistema deve permitir filtrar entregas por:

região;

status;

prioridade.

RF-006 — Exibir data de lançamento da entrega

A listagem de entregas deve exibir a data em que cada entrega foi lançada no sistema.

A informação deve permitir identificar pedidos mais antigos que mereçam maior atenção operacional.

RF-007 — Ordenar entregas por antiguidade

O sistema deve permitir ordenar a listagem de entregas por:

mais antigas primeiro;

mais recentes primeiro.

A ordenação deve ser aplicada no backend antes da paginação.

Por padrão, a visão operacional pode priorizar as entregas mais antigas.

2.2 Carradas e planejamento

RF-008 — Cadastrar carrada

O sistema deve permitir criar uma carrada informando:

data;

região principal, quando aplicável;

veículo;

rota;

observação.

RF-009 — Definir rota ordenada

O sistema deve permitir cadastrar uma rota contendo uma sequência ordenada de paradas.

Cada parada pode ser:

uma região cadastrada;

uma parada livre apenas para referência textual.

A ordem deve ser preservada.

RF-010 — Buscar carrada

O sistema deve permitir localizar uma carrada pelo número.

Exemplos aceitos:

35;

#35;

Carrada 35.

RF-011 — Listar entregas disponíveis pela rota

Ao montar uma carrada, o sistema deve localizar entregas normais disponíveis nas regiões cadastradas da rota.

Paradas de texto livre não devem ser usadas para associação automática com entregas.

RF-012 — Adicionar entrega à carrada

O sistema deve permitir adicionar uma entrega à carrada enquanto a carrada estiver em estado compatível com planejamento.

RF-013 — Selecionar produtos da entrega

Ao adicionar uma entrega, o sistema deve permitir selecionar quais produtos irão naquela carrada.

RF-014 — Programar quantidade parcial

O sistema deve permitir programar somente parte do saldo disponível de um produto.

Exemplo:

pedido: 100;

levar agora: 55;

saldo ainda não programado: 45.

RF-015 — Impedir programação acima do saldo

O sistema não deve permitir programar quantidade superior ao saldo realmente disponível.

RF-016 — Remover entrega de carrada planejada

O sistema deve permitir remover uma entrega de uma carrada enquanto ela estiver em estado PLANEJADA.

A remoção deve liberar novamente a quantidade anteriormente reservada.

RF-017 — Iniciar carrada

O sistema deve permitir iniciar uma carrada planejada que possua pelo menos:

uma entrega normal; ou

uma reentrega.

RF-018 — Finalizar carrada

O sistema deve permitir finalizar uma carrada somente depois que todos os retornos obrigatórios forem registrados.

Isso inclui:

entregas normais;

reentregas.

RF-019 — Cancelar carrada

O sistema deve permitir cancelar uma carrada quando permitido pelas regras de negócio.

Quando uma carrada planejada for cancelada, as quantidades e pendências reservadas devem voltar a ficar disponíveis de forma consistente.

2.3 Retorno das entregas

RF-020 — Registrar retorno de entrega

O sistema deve permitir registrar o resultado de cada entrega pertencente a uma carrada em andamento.

Para cada produto devem ser informados:

quantidade carregada/programada;

quantidade realmente entregue;

destino da quantidade não entregue, quando houver;

observação opcional.

RF-021 — Definir destino da quantidade não entregue

Quando:

quantidade entregue < quantidade carregada

o sistema deve exigir o destino da diferença.

Destinos permitidos:

REENTREGA;

DEVOLVIDO.

RF-022 — Criar pendência

Quando o destino for REENTREGA, o sistema deve criar ou manter uma pendência correspondente à quantidade que continua devendo ser entregue ao cliente.

RF-023 — Registrar devolução

Quando o destino for DEVOLVIDO, o sistema deve registrar uma devolução e não criar nova pendência daquela quantidade.

RF-024 — Registrar motivo da devolução

O sistema deve permitir registrar o motivo da devolução.

Motivos previstos incluem:

cliente recusou;

cliente desistiu;

produto incorreto;

produto avariado;

quantidade excedente;

outro.

2.4 Pendências e reentregas

RF-025 — Listar pendências pela rota

Ao montar uma carrada, o sistema deve listar separadamente as pendências abertas das regiões cadastradas no percurso.

RF-026 — Adicionar reentrega à carrada

O sistema deve permitir adicionar uma pendência aberta a uma carrada planejada.

Após a programação, a pendência deve ficar com status PROGRAMADA.

RF-027 — Impedir duplicidade de reentrega

Uma pendência não pode estar programada simultaneamente em duas carradas.

RF-028 — Remover reentrega de carrada

Enquanto a carrada estiver planejada, o sistema deve permitir remover uma reentrega.

A pendência deve voltar ao estado ABERTA.

RF-029 — Registrar retorno de reentrega

O sistema deve permitir registrar o retorno de uma reentrega.

Exemplo:

pendência programada: 5;

entregue: 3;

restante: 2.

Para o restante deve ser possível escolher:

nova reentrega;

devolução.

RF-030 — Resolver pendência

Quando toda a quantidade pendente for tratada, a pendência deve ser marcada como RESOLVIDA.

Uma pendência pode ser resolvida ao longo de mais de uma carrada.

RF-031 — Resolver pendência por devolução

O sistema deve permitir encerrar logisticamente uma quantidade pendente como devolvida, registrando o motivo e deixando essa quantidade de ser obrigação de entrega.

2.5 Históricos e rastreabilidade

RF-032 — Histórico da entrega

O sistema deve apresentar o histórico da entrega com informações por produto.

Devem ser exibidos, quando aplicável:

comprado;

entregue;

devolvido;

saldo restante;

pendência;

em carrada;

ainda não enviado;

tentativas realizadas.

RF-033 — Histórico da carrada

O histórico da carrada deve exibir:

dados da carrada;

rota;

entregas normais;

reentregas;

resultados;

pendências geradas;

devoluções;

eventos da carrada.

Uma carrada contendo apenas reentregas não pode ser apresentada como vazia.

RF-034 — Registrar eventos da carrada

O sistema deve registrar eventos relevantes, como:

carrada criada;

entrega adicionada;

entrega atualizada;

entrega removida;

pendência adicionada;

pendência removida;

carrada iniciada;

carrada finalizada;

veículo trocado;

carrada cancelada;

resultado registrado.

RF-035 — Registrar eventos da entrega

Alterações relevantes na entrega devem gerar histórico suficiente para explicar sua evolução.

Exemplos:

criação;

atualização;

cancelamento;

resultados de tentativas.

2.6 Clientes, regiões e veículos

RF-036 — Cadastrar e gerenciar clientes

O sistema deve permitir cadastrar, consultar e editar clientes utilizados nas entregas.

O cadastro do cliente deve poder ser reutilizado em novas entregas.

RF-037 — Preservar snapshot do cliente na entrega

Ao utilizar um cliente cadastrado, a entrega deve guardar uma cópia dos dados operacionais necessários para que o histórico continue compreensível mesmo que o cadastro do cliente seja alterado futuramente.

RF-038 — Consultar histórico do cliente

O sistema deve permitir visualizar o histórico relacionado a um cliente, incluindo suas entregas e movimentações relevantes disponíveis no sistema.

RF-039 — Cadastrar e gerenciar regiões

O sistema deve permitir cadastrar, editar e controlar regiões utilizadas para:

organização das entregas;

filtros;

formação de rotas;

localização de entregas disponíveis;

localização de pendências disponíveis.

RF-040 — Cadastrar e gerenciar veículos

O sistema deve permitir cadastrar veículos utilizados nas carradas e controlar sua disponibilidade operacional.

2.7 Autenticação e usuários

RF-041 — Realizar login

O sistema deve permitir autenticação por e-mail e senha.

A autenticação deve utilizar sessão protegida pelo Laravel Sanctum.

RF-042 — Realizar logout

O usuário autenticado deve poder encerrar sua sessão.

O logout deve invalidar a sessão atual e renovar o token de proteção correspondente.

RF-043 — Bloquear usuário inativo

Usuários marcados como inativos não devem conseguir acessar o sistema.

O bloqueio deve ser aplicado pelo backend, independentemente da interface.

RF-044 — Trabalhar com perfis de acesso

O sistema deve possuir, no mínimo, os perfis:

ADMIN;

OPERADOR.

Funcionalidades administrativas devem ser autorizadas no backend.

RF-045 — Cadastrar usuário

Somente um usuário ADMIN deve poder cadastrar novos usuários.

Novos usuários operacionais devem ser criados com os dados obrigatórios de autenticação e com situação ativa.

RF-046 — Listar usuários

Usuários administradores devem poder consultar a lista de usuários do sistema.

A tela deve permitir identificar:

nome;

e-mail;

perfil;

situação ativa/inativa.

RF-047 — Editar usuário

Usuários administradores devem poder alterar dados permitidos de outros usuários, incluindo:

nome;

e-mail;

perfil, quando permitido.

RF-048 — Ativar ou desativar usuário

Usuários administradores devem poder ativar ou desativar contas.

O sistema deve impedir ações administrativas que comprometam de forma insegura a própria conta administrativa, conforme as regras implementadas.

RF-049 — Redefinir senha de usuário

Usuários administradores devem poder redefinir a senha de um usuário.

2.8 Importação de documentos e OCR

RF-050 — Importar entrega por imagem

O sistema deve permitir importar documento de entrega a partir de imagem compatível.

RF-051 — Importar múltiplas imagens

O sistema deve permitir importar várias imagens pertencentes ao mesmo documento, mantendo a ordem informada pelo usuário.

O fluxo atual suporta até 12 imagens por importação.

RF-052 — Importar documento PDF

O sistema deve permitir importar documento em PDF.

O fluxo atual suporta documentos de até 12 páginas por importação.

RF-053 — Extrair dados por OCR

O sistema deve processar o documento importado e tentar extrair informações relevantes da entrega, como:

número do pedido;

cliente;

endereço;

produtos;

códigos;

unidades;

quantidades.

RF-054 — Exigir conferência antes da confirmação

Dados extraídos automaticamente não devem criar uma entrega definitiva sem conferência do usuário.

O usuário deve poder revisar e corrigir os campos antes de confirmar.

RF-055 — Criar ou reutilizar cliente na importação

Durante a confirmação da importação, o sistema deve permitir utilizar um cliente existente ou criar o cadastro necessário de acordo com os dados confirmados.

RF-056 — Preservar documento importado

O sistema deve manter registro do documento utilizado na importação, incluindo informações necessárias para rastreabilidade do processamento.

RF-057 — Identificar possíveis sobreposições entre páginas

Quando o processamento de múltiplas imagens ou páginas detectar conteúdo potencialmente repetido entre partes do documento, o sistema deve apresentar aviso para conferência, evitando criação incorreta de itens duplicados.

2.9 Dashboard

RF-058 — Exibir visão operacional

O sistema deve possuir Dashboard com visão resumida da operação.

RF-059 — Exibir indicadores principais

O Dashboard deve apresentar indicadores operacionais relevantes, incluindo, quando disponíveis:

entregas realizadas no mês;

pedidos aguardando carrada;

carradas do dia;

reentregas abertas;

pedidos com problema.

RF-060 — Exibir informações por região

O Dashboard deve permitir visualizar concentração ou volume de entregas por região.

RF-061 — Destacar pendências antigas

O Dashboard deve apresentar pendências antigas que merecem atenção operacional.

RF-062 — Destacar entregas antigas sem carrada

O Dashboard deve apresentar pedidos mais antigos que continuam aguardando programação em uma carrada.

RF-063 — Exibir carradas do dia

O Dashboard deve apresentar as carradas relevantes para a operação do dia e seus estados.

2.10 Relatórios

RF-064 — Consultar relatórios operacionais

O sistema deve possuir módulo de relatórios para consulta consolidada dos dados operacionais disponíveis.

RF-065 — Respeitar período local da operação

Filtros de data utilizados em relatórios devem considerar o fuso horário operacional configurado para a aplicação.

A configuração atual utiliza:

America/Fortaleza

2.11 Navegação e experiência

RF-066 — Navegar pelos módulos autenticados

Após autenticação, o usuário deve poder acessar os módulos permitidos pelo seu perfil por meio da navegação principal do sistema.

RF-067 — Ocultar opções não autorizadas

A interface deve evitar apresentar ações administrativas a usuários sem permissão.

Essa ocultação é apenas uma medida de experiência de uso e não substitui a autorização no backend.

3. Requisitos não funcionais

RNF-001 — Integridade dos dados

O sistema deve impedir dupla contagem de quantidades e manter consistência entre:

solicitado;

entregue;

devolvido;

pendente;

reservado em carrada;

ainda não enviado.

RNF-002 — Persistência

Os dados operacionais devem ser armazenados em PostgreSQL.

RNF-003 — Segurança

As rotas operacionais da API devem ser protegidas por autenticação.

O projeto utiliza Laravel Sanctum com autenticação baseada em sessão para o frontend da aplicação.

RNF-004 — Autorização no backend

Restrições de perfil não devem depender somente da interface.

Operações administrativas devem ser validadas pelo backend.

RNF-005 — Rastreabilidade

Operações relevantes devem manter histórico suficiente para explicar o estado atual de:

entrega;

carrada;

pendência;

devolução;

reentrega.

RNF-006 — Consistência transacional

Operações que alteram múltiplas entidades relacionadas devem ser executadas dentro de transações de banco de dados quando necessário.

RNF-007 — Interface responsiva

A interface deve continuar utilizável em diferentes larguras de tela compatíveis com a operação prevista.

RNF-008 — Usabilidade operacional

O sistema deve usar linguagem próxima da operação real da loja.

Exemplos:

carrada;

reentrega;

devolvido;

faltou;

carregado;

entregue.

RNF-009 — Separação de responsabilidades

O frontend deve concentrar apresentação, interação e comunicação com a API.

Regras críticas de negócio devem permanecer protegidas no backend.

RNF-010 — Organização modular do frontend

Páginas grandes devem ser divididas em componentes e estilos por domínio quando isso melhorar manutenção e legibilidade.

A organização deve preservar separação entre:

páginas;

componentes;

services;

types;

stores;

estilos.

RNF-011 — Contratos tipados no frontend

As estruturas consumidas pela interface devem ser representadas por tipos TypeScript sempre que aplicável.

Mudanças relevantes da API devem ser refletidas nos contratos do frontend.

RNF-012 — Tratamento de datas e fuso horário

Operações sensíveis a períodos de tempo devem utilizar de forma consistente o fuso horário operacional configurado.

RNF-013 — Confiabilidade da importação

OCR deve ser tratado como mecanismo de auxílio e não como fonte infalível.

Dados extraídos automaticamente devem permanecer editáveis antes da confirmação.

RNF-014 — Preservação histórica

Alterações cadastrais posteriores não devem tornar incompreensíveis registros operacionais antigos.

Snapshots e eventos devem ser utilizados quando necessário.

RNF-015 — Testabilidade

Regras críticas de negócio devem ser passíveis de testes automatizados.

Casos de saldo, programação parcial, retorno, pendência, reentrega e devolução devem possuir cobertura de teste sempre que possível.

RNF-016 — Manutenibilidade

O código deve permanecer organizado por responsabilidades, evitando concentração desnecessária de lógica em controllers ou componentes visuais.

RNF-017 — Consistência visual

As telas do sistema devem seguir um padrão visual comum para:

navegação;

botões;

formulários;

cards;

tabelas;

estados;

mensagens de sucesso e erro.

4. Requisitos ainda não concluídos

Os seguintes itens permanecem como evolução futura e não devem ser tratados como funcionalidades concluídas enquanto não forem implementados e testados:

integração automática com ERP ou sistema comercial externo;

otimização automática de rota;

cálculo automático de capacidade do veículo;

cálculo automático de custo de reentrega;

planejamento automático de entregas com base em peso, volume, distância e tempo de descarga;

gestão operacional avançada de equipe;

tratamento completo de ocorrências operacionais;

expansão dos relatórios para análises gerenciais mais avançadas;

integrações externas adicionais.

5. Observação sobre evolução dos requisitos

Este documento representa o estado conhecido do sistema no momento de sua atualização.

Sempre que uma funcionalidade for adicionada, alterada ou removida, os requisitos correspondentes devem ser revisados para manter alinhamento entre:

documentação;

implementação;

banco de dados;

API;

interface;

testes.