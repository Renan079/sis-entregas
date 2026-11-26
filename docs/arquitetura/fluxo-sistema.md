Fluxo do Sistema

1. Objetivo

Este documento descreve o fluxo operacional atual do Sistema de Gerenciamento de Entregas.

O objetivo é registrar como o sistema se comporta desde o acesso do usuário até a conclusão logística de uma entrega, incluindo:

autenticação;

cadastro manual de entregas;

formação de carradas;   

definição de rota;

programação parcial de produtos;

retorno da entrega;

pendências;

reentregas;

devoluções;

históricos;

Dashboard operacional;

relatórios.

O documento descreve o fluxo funcional. Regras específicas e fórmulas devem ser consultadas também em docs/requisitos/04-regras-negocio.md.

2. Visão geral do fluxo

flowchart TD
    A[Usuário acessa o sistema] --> B{Autenticado?}
    B -->|Não| C[Login]
    C --> D{Credenciais válidas e usuário ativo?}
    D -->|Não| C
    D -->|Sim| E[Área autenticada]
    B -->|Sim| E

    E --> F[Dashboard]
    E --> G[Entregas]
    E --> H[Carradas]
    E --> I[Pendências]
    E --> J[Clientes / Regiões / Veículos]
    E --> K[Relatórios]
    E --> L{Perfil ADMIN?}

    L -->|Sim| M[Gestão de usuários]

    G --> O[Cadastrar entrega]

    O --> S[Entrega disponível para planejamento]

    S --> T[Criar carrada]
    T --> U[Definir data, rota e veículo]
    U --> V[Selecionar entregas normais]
    U --> W[Selecionar reentregas]

    V --> X[Selecionar produtos e quantidades]
    W --> Y[Adicionar pendência à carrada]

    X --> Z[Carrada PLANEJADA]
    Y --> Z

    Z --> AA[Iniciar carrada]
    AA --> AB[Carrada EM_ANDAMENTO]

    AB --> AC[Registrar retorno das entregas]
    AB --> AD[Registrar retorno das reentregas]

    AC --> AE{Quantidade não entregue?}
    AE -->|Não| AF[Registrar quantidade entregue]
    AE -->|Sim| AG{Destino}

    AG -->|REENTREGA| AH[Criar pendência]
    AG -->|DEVOLVIDO| AI[Registrar devolução]

    AD --> AJ{Reentrega concluída?}
    AJ -->|Sim| AK[Resolver pendência]
    AJ -->|Não| AL{Destino do restante}

    AL -->|REENTREGA| AM[Pendência volta a ABERTA]
    AL -->|DEVOLVIDO| AN[Registrar devolução e resolver saldo correspondente]

    AF --> AO[Recalcular situação da entrega]
    AH --> AO
    AI --> AO
    AK --> AO
    AM --> AO
    AN --> AO

    AO --> AP{Todos os retornos da carrada registrados?}
    AP -->|Não| AB
    AP -->|Sim| AQ[Finalizar carrada]

    AQ --> AR[Atualizar históricos e indicadores]

3. Acesso ao sistema

3.1 Login

O sistema possui área autenticada.

O usuário informa:

e-mail;

senha.

O backend valida:

credenciais;

situação da conta;

sessão.

Um usuário inativo não deve conseguir operar o sistema.

3.2 Perfis

Os perfis atuais são:

ADMIN;

OPERADOR.

O perfil OPERADOR utiliza os módulos operacionais permitidos.

O perfil ADMIN também pode acessar a gestão de usuários.

A interface pode ocultar ações não autorizadas, mas a autorização definitiva deve ocorrer no backend.

3.3 Logout

Ao sair do sistema:

a sessão é encerrada;

o usuário deixa de ter acesso às rotas protegidas;

uma nova autenticação passa a ser necessária.

5. Cadastro manual da entrega

Uma entrega representa um pedido que precisa ser atendido logisticamente.

O cadastro pode conter:

número do pedido;

cliente;

região;

endereço;

referência;

prioridade;

observação;

produtos;

quantidade solicitada de cada produto.

Exemplo

Pedido #89567:

Produto

Código

Quantidade

Argamassa AC3

148

50 UN

Após o cadastro, a entrega recebe sua data de lançamento no sistema e passa a ficar disponível para planejamento conforme:

status;

saldo;

região;

reservas existentes;

pendências existentes.

7. Listagem e prioridade operacional das entregas

A tela de entregas deve permitir:

busca;

filtro por região;

filtro por status;

filtro por prioridade;

paginação;

visualização da data de lançamento.

A listagem pode ser ordenada por:

mais antigas primeiro;

mais recentes primeiro.

A visão padrão pode priorizar as mais antigas para facilitar a identificação de pedidos que aguardam atendimento há mais tempo.

Antiguidade não altera automaticamente a prioridade cadastrada.

8. Formação da carrada

Uma carrada representa uma saída física da loja.

Na criação podem ser definidos:

data;

região principal;

veículo;

rota;

observação.

A carrada começa como:

PLANEJADA

9. Definição da rota

A rota é uma lista ordenada de paradas.

Exemplo:

Gameleira;

Serra de São João;

Malhada Redonda;

Banco de Areia.

Uma parada pode ser:

uma região cadastrada, com regiao_id;

uma parada livre de texto.

A ordem deve ser preservada no histórico.

Somente regiões cadastradas podem ser utilizadas para associação automática com:

entregas disponíveis;

pendências disponíveis.

10. Planejamento da carrada

Ao abrir uma carrada PLANEJADA, o sistema separa duas naturezas de obrigação.

10.1 Entregas normais disponíveis

São quantidades que ainda podem ser enviadas como saldo normal do pedido.

O sistema considera:

quantidade solicitada;

quantidade entregue;

quantidade devolvida;

pendências;

reservas em outras carradas;

região da rota.

10.2 Pendências disponíveis na rota

São quantidades que:

já saíram da loja anteriormente;

não foram entregues;

continuam devendo ser entregues.

Elas devem aparecer separadamente das entregas normais.

flowchart LR
    A[Saldo restante] --> B{Classificação}
    B -->|Nunca saiu / saldo normal| C[Entrega normal disponível]
    B -->|Já saiu e deve voltar ao cliente| D[Pendência / Reentrega]
    B -->|Cliente não receberá mais| E[Devolvido]
    B -->|Já está reservado| F[Em carrada]

    C --> G[Pode ser programado]
    D --> H[Programação como reentrega]
    E --> I[Não volta para programação]
    F --> J[Aguardar retorno ou liberação]

11. Programação parcial de produtos

Uma entrega pode ser dividida entre várias carradas.

Exemplo

Pedido:

100 blocos.

Planejamento:

carrada 1: 55;

ainda não enviado: 45.

O sistema deve garantir:

quantidade programada <= quantidade disponível

A quantidade já reservada em outra carrada não deve ficar novamente disponível.

12. Adição de reentrega

Quando uma pendência ABERTA é adicionada a uma carrada:

ABERTA → PROGRAMADA

A mesma pendência não pode ficar programada simultaneamente em duas carradas.

Se ela for removida de uma carrada ainda planejada:

PROGRAMADA → ABERTA

13. Início da carrada

Uma carrada só pode ser iniciada quando possuir pelo menos:

uma entrega normal; ou

uma reentrega.

Ao iniciar:

PLANEJADA → EM_ANDAMENTO

A partir desse momento, a viagem passa a aguardar os retornos reais.

14. Retorno de uma entrega normal

O retorno é registrado por entrega e por produto.

A operação compara:

quantidade programada/carregada;

quantidade realmente entregue.

Exemplo:

Produto

Carregado

Entregue

Faltou

Destino

Argamassa AC3

50

45

5

Reentrega

Cimento

20

18

2

Devolvido

Torneira

1

1

0

—

Quando:

quantidade_entregue = quantidade_carregada

não existe diferença a classificar.

Quando:

quantidade_entregue < quantidade_carregada

o operador precisa informar o destino da diferença.

15. Destino da quantidade não entregue

Existem dois destinos principais.

15.1 REENTREGA

Usado quando o cliente ainda deve receber a quantidade.

Resultado:

cria ou mantém obrigação logística;

gera pendência;

a quantidade poderá entrar em uma nova carrada;

a entrega continua com saldo a tratar.

15.2 DEVOLVIDO

Usado quando a quantidade não deve mais ser entregue ao cliente.

Motivos podem incluir:

cliente recusou;

cliente desistiu;

produto incorreto;

produto avariado;

quantidade excedente;

outro.

Resultado:

registra devolução;

não gera pendência daquela quantidade;

a quantidade não volta para nova carrada;

a obrigação logística correspondente é encerrada.

16. Quantidade tratada

Para encerramento logístico:

quantidade tratada = quantidade entregue + quantidade devolvida

Exemplo A — conclusão com devolução

Solicitado:  50
Entregue:    45
Devolvido:    5
----------------
Tratado:     50

Não existe mais obrigação logística.

Exemplo B — pedido parcial com reentrega

Solicitado: 50
Entregue:   45
Pendência:   5

Ainda existem 5 unidades que devem chegar ao cliente.

17. Criação da pendência

Uma pendência nasce quando uma quantidade:

saiu fisicamente da loja;

não foi entregue;

ainda deverá ser entregue.

Exemplo

Carregado: 55
Entregue:  50
Faltou:     5
Destino: REENTREGA

Resultado:

Pendência criada: 5
Status: ABERTA

A pendência é parte do saldo restante e não deve ser novamente tratada como saldo normal.

18. Planejamento da reentrega

Quando uma nova carrada passa pela região da pendência, ela pode aparecer em:

Pendências disponíveis na rota

Exemplo:

Pedido #89567
Cliente: Doquinha de cicero
Região: Serra de São João

Argamassa AC3
Cód. 148

Pendente: 5 UN

Ao adicionar à carrada:

ABERTA → PROGRAMADA

19. Retorno de uma reentrega

Uma reentrega precisa de retorno próprio.

19.1 Reentrega completa

Programado: 5
Entregue:   5

Resultado:

Pendência → RESOLVIDA

19.2 Reentrega parcial que continuará

Programado: 5
Entregue:   3
Restante:   2
Destino: REENTREGA

Resultado:

3 resolvidos
2 continuam pendentes
Pendência → ABERTA

19.3 Reentrega parcial com devolução

Programado: 5
Entregue:   3
Restante:   2
Destino: DEVOLVIDO

Resultado:

3 entregues
2 devolvidos
Pendência → RESOLVIDA

20. Estados da pendência

stateDiagram-v2
    [*] --> ABERTA

    ABERTA --> PROGRAMADA: adicionada a carrada
    PROGRAMADA --> ABERTA: removida da carrada planejada
    PROGRAMADA --> RESOLVIDA: saldo totalmente tratado
    PROGRAMADA --> ABERTA: retorno parcial com nova reentrega
    PROGRAMADA --> RESOLVIDA: restante devolvido

    ABERTA --> CANCELADA: cancelamento permitido
    PROGRAMADA --> CANCELADA: cancelamento permitido

    RESOLVIDA --> [*]
    CANCELADA --> [*]

21. Status da entrega

A entrega pode utilizar os estados:

PENDENTE;

PROGRAMADA;

EM_SAIDA;

PARCIAL;

CONCLUIDA;

NAO_ENTREGUE;

CANCELADA.

A situação deve considerar o histórico real da obrigação logística.

Uma entrega pode ser concluída quando a quantidade solicitada estiver integralmente tratada por:

entrega; e/ou

devolução.

22. Finalização da carrada

A carrada não deve ser finalizada enquanto existir operação obrigatória sem retorno.

Devem estar tratados:

retornos das entregas normais;

retornos das reentregas.

Quando tudo estiver registrado:

EM_ANDAMENTO → FINALIZADA

A finalização não deve apagar informações intermediárias da viagem.

23. Histórico da entrega

O histórico da entrega deve permitir entender a situação de cada produto.

Informações relevantes:

comprado/solicitado;

entregue;

devolvido;

saldo restante;

pendência;

em carrada;

ainda não enviado;

tentativas realizadas.

Exemplo

Argamassa AC3
Cód. 148

Comprado:         50
Entregue:         45
Devolvido:         0
Saldo restante:    5
Pendência:          5
Em carrada:         0
Ainda não enviado:  0

24. Histórico da carrada

O histórico da carrada deve representar o que aconteceu naquela viagem.

Pode conter seções para:

resumo;

rota;

entregas normais;

reentregas;

devoluções;

pendências geradas;

linha do tempo.

Uma carrada contendo somente reentregas não está vazia.

Exemplo:

Entregas normais: 0
Reentregas: 1
Atendimentos: 1

25. Linha do tempo e eventos

Eventos relevantes devem ser registrados para rastreabilidade.

Exemplos:

Carrada criada
Entrega adicionada
Entrega atualizada
Entrega removida
Pendência adicionada
Pendência removida
Carrada iniciada
Resultado da entrega registrado
Resultado da reentrega registrado
Veículo trocado
Carrada finalizada
Carrada cancelada

O histórico da própria entrega também pode registrar:

Entrega criada
Entrega atualizada
Entrega cancelada
Resultado registrado

26. Clientes no fluxo

Clientes podem ser cadastrados e reutilizados em várias entregas.

Quando uma entrega utiliza um cliente cadastrado, os dados necessários à operação são mantidos também como snapshot da entrega.

Assim, alterar o cadastro do cliente no futuro não deve tornar incompreensível uma entrega antiga.

O histórico do cliente permite acompanhar suas operações relacionadas.

27. Regiões no fluxo

Regiões servem para:

organizar entregas;

filtrar a listagem;

compor rotas;

localizar entregas disponíveis;

localizar pendências disponíveis;

gerar indicadores operacionais.

Uma parada livre de rota não substitui uma região cadastrada para associação automática.

28. Dashboard operacional

O Dashboard funciona como visão resumida da operação.

Ele pode apresentar:

entregas realizadas no mês;

pedidos aguardando carrada;

carradas do dia;

reentregas abertas;

pedidos com problema;

volume por região;

pendências antigas;

entregas antigas ainda sem carrada.

flowchart LR
    A[Dados operacionais] --> B[Dashboard]
    B --> C[Entregas]
    B --> D[Carradas]
    B --> E[Pendências]
    B --> F[Regiões]

O Dashboard não substitui os registros detalhados.

Ele serve para ajudar o operador a decidir onde precisa olhar primeiro.

29. Relatórios

O módulo de relatórios consulta e consolida informações operacionais.

Filtros por período devem considerar o fuso operacional:

America/Fortaleza

O relatório deve ser uma operação de leitura e não deve alterar:

entregas;

carradas;

pendências;

devoluções.

30. Gestão de usuários

A gestão de usuários é administrativa.

Fluxo:

flowchart TD
    A[ADMIN acessa Usuários] --> B[Listar usuários]
    B --> C{Ação}
    C --> D[Cadastrar novo usuário]
    C --> E[Editar dados]
    C --> F[Alterar perfil]
    C --> G[Ativar / Desativar]
    C --> H[Redefinir senha]

Novos usuários operacionais são criados no fluxo atual como:

perfil: OPERADOR
ativo: true

Ações administrativas devem ser protegidas pelo backend.

31. Fluxo resumido de uma entrega

flowchart TD
    A[Entrega criada] --> B[Saldo normal disponível]
    B --> C[Produto programado em carrada]
    C --> D[Carrada iniciada]
    D --> E[Registrar retorno]

    E --> F{Entregou tudo carregado?}

    F -->|Sim| G[Registrar entregue]
    F -->|Não| H{Destino do restante}

    H -->|REENTREGA| I[Gerar pendência]
    H -->|DEVOLVIDO| J[Registrar devolução]

    G --> K[Recalcular entrega]
    I --> K
    J --> K

    K --> L{Existe obrigação logística restante?}

    L -->|Sim| M[PENDENTE / PARCIAL / estado operacional]
    L -->|Não| N[CONCLUIDA]

32. Fluxo resumido de uma pendência

flowchart TD
    A[Pendência ABERTA] --> B[Nova carrada passa pela região]
    B --> C[Adicionar reentrega]
    C --> D[Pendência PROGRAMADA]
    D --> E[Carrada iniciada]
    E --> F[Registrar retorno da reentrega]

    F --> G{Saldo totalmente tratado?}

    G -->|Sim| H[Pendência RESOLVIDA]
    G -->|Não| I{Destino do restante}

    I -->|REENTREGA| J[Pendência ABERTA novamente]
    I -->|DEVOLVIDO| K[Registrar devolução]
    K --> H

34. Princípio central do sistema

Uma mesma unidade física de produto não pode estar simultaneamente em classificações logísticas conflitantes.

O sistema deve distinguir:

ENTREGUE
DEVOLVIDO
PENDENTE / REENTREGA
EM CARRADA
AINDA NÃO ENVIADO

Essa separação é essencial para evitar:

dupla programação;

pendências duplicadas;

saldo incorreto;

reentregas indevidas;

histórico inconsistente.

35. Tratamento de imprevistos operacionais

O sistema deve permitir que a operação seja reorganizada manualmente diante de situações como:

ausência de motorista;

problema mecânico;

troca de veículo;

necessidade de empurrar entregas para outro dia;

mudança de prioridade operacional.

O sistema não deve assumir uma capacidade diária fixa de entregas, pois a capacidade real depende de fatores como:

peso;

volume;

distância;

tempo de descarga;

tipo de material;

equipe disponível.

Automação avançada dessas decisões permanece como evolução futura.

36. Fluxos ainda futuros

Os seguintes fluxos continuam fora do núcleo concluído:

integração automática com ERP;

otimização automática de rotas;

cálculo automático de capacidade do veículo;

cálculo automático de custo de reentrega;

planejamento automático com peso, volume e distância;

gestão operacional avançada da equipe;

tratamento completo de ocorrências operacionais;

análises gerenciais mais avançadas.

Dashboard e relatórios básicos não devem mais ser tratados como funcionalidades futuras, pois já fazem parte do sistema atual.

37. Manutenção deste documento

Quando um fluxo operacional mudar, devem ser revisados em conjunto:

requisitos;

regras de negócio;

arquitetura;

modelo de dados;

endpoints;

frontend;

backend;

testes automatizados.

O objetivo é impedir que a documentação descreva um comportamento diferente do sistema real.