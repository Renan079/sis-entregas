Modelo de Entidades

1. Objetivo

Este documento descreve o modelo de dados atual do Sistema de Gerenciamento de Entregas.

O objetivo é registrar:

principais entidades;

responsabilidade de cada tabela;

relacionamentos;

estados importantes;

entidades de histórico;

entidades de autenticação;

entidades de importação e OCR;

entidades utilizadas em entregas, carradas, pendências, reentregas e devoluções;

cálculos derivados do modelo;

regras estruturais que devem ser preservadas.

Este documento deve ser atualizado sempre que houver alteração estrutural relevante no banco de dados.

Observação: quando o tipo físico exato de uma coluna não é essencial para compreender o domínio, este documento descreve a função do campo em vez de tentar substituir as migrations como fonte definitiva do schema.

2. Visão geral do domínio

O sistema pode ser dividido nos seguintes grupos.

2.1 Autenticação e usuários

users;

estruturas de sessão utilizadas pelo Laravel, quando aplicável.

2.2 Cadastros

clientes;

regiaos;

funcionarios;

veiculos.

2.3 Importação

documento_importados.

2.4 Entregas

entregas;

entrega_items;

entrega_eventos.

2.5 Carradas

saidas;

saida_entregas;

saida_entrega_itens;

saida_equipes;

saida_eventos.

2.6 Tentativas de entrega

tentativa_entregas;

tentativa_items.

2.7 Pendências e reentregas

pendencias;

pendencia_movimentacoes;

saida_pendencias;

saida_pendencia_retornos.

2.8 Devoluções e ocorrências

devolucoes;

ocorrencias_operacionais.

3. Diagrama geral

erDiagram
    USERS ||--o{ ENTREGAS : cria
    USERS ||--o{ SAIDAS : cria
    USERS ||--o{ DOCUMENTO_IMPORTADOS : importa
    USERS ||--o{ PENDENCIA_MOVIMENTACOES : registra
    USERS ||--o{ DEVOLUCOES : registra
    USERS ||--o{ SAIDA_PENDENCIA_RETORNOS : registra
    USERS ||--o{ ENTREGA_EVENTOS : registra
    USERS ||--o{ SAIDA_EVENTOS : registra

    CLIENTES ||--o{ ENTREGAS : possui
    REGIAOS ||--o{ ENTREGAS : agrupa
    REGIAOS ||--o{ SAIDAS : principal
    VEICULOS ||--o{ SAIDAS : utilizado_em

    DOCUMENTO_IMPORTADOS ||--o| ENTREGAS : pode_originar

    ENTREGAS ||--|{ ENTREGA_ITEMS : possui
    ENTREGAS ||--o{ ENTREGA_EVENTOS : historico

    SAIDAS ||--o{ SAIDA_ENTREGAS : possui
    ENTREGAS ||--o{ SAIDA_ENTREGAS : participa

    SAIDA_ENTREGAS ||--|{ SAIDA_ENTREGA_ITENS : programa
    ENTREGA_ITEMS ||--o{ SAIDA_ENTREGA_ITENS : item

    SAIDAS ||--o{ TENTATIVA_ENTREGAS : gera
    ENTREGAS ||--o{ TENTATIVA_ENTREGAS : recebe

    TENTATIVA_ENTREGAS ||--|{ TENTATIVA_ITEMS : possui
    ENTREGA_ITEMS ||--o{ TENTATIVA_ITEMS : resultado

    ENTREGAS ||--o{ PENDENCIAS : possui
    ENTREGA_ITEMS ||--o{ PENDENCIAS : referencia
    TENTATIVA_ENTREGAS ||--o{ PENDENCIAS : origina

    PENDENCIAS ||--o{ PENDENCIA_MOVIMENTACOES : historico

    SAIDAS ||--o{ SAIDA_PENDENCIAS : programa
    PENDENCIAS ||--o{ SAIDA_PENDENCIAS : reentrega

    SAIDAS ||--o{ SAIDA_PENDENCIA_RETORNOS : retorno
    PENDENCIAS ||--o{ SAIDA_PENDENCIA_RETORNOS : retorno

    ENTREGAS ||--o{ DEVOLUCOES : possui
    ENTREGA_ITEMS ||--o{ DEVOLUCOES : item
    SAIDAS ||--o{ DEVOLUCOES : ocorreu_em
    TENTATIVA_ENTREGAS ||--o{ DEVOLUCOES : origem
    PENDENCIAS ||--o{ DEVOLUCOES : origem_reentrega

    SAIDAS ||--o{ SAIDA_EVENTOS : historico

4. Autenticação e usuários

4.1 users

Representa os usuários autenticados do sistema.

Campos conhecidos:

Campo

Função

id

identificador

name

nome do usuário

email

e-mail de autenticação

password

senha armazenada com hash

perfil

perfil de acesso

ativo

controla se a conta pode operar

remember_token

suporte do Laravel, quando utilizado

created_at

criação

updated_at

atualização

Perfis atuais:

ADMIN
OPERADOR

Regras importantes:

usuário inativo não deve operar o sistema;

senha nunca deve ser persistida em texto puro;

ações administrativas devem ser autorizadas no backend;

não existe cadastro público livre de usuário no fluxo atual.

Relacionamentos relevantes:

users 1 ─── N entregas
users 1 ─── N saidas
users 1 ─── N documento_importados
users 1 ─── N pendencia_movimentacoes
users 1 ─── N devolucoes
users 1 ─── N eventos

5. Cadastros

5.1 clientes

Representa o cadastro reutilizável de clientes.

Campos conhecidos do domínio:

Campo

Função

id

identificador

codigo_externo

código do cliente em sistema externo, quando existir

nome

nome principal

apelido

identificação informal, quando utilizada

telefone

telefone

ativo

situação cadastral

created_at

criação

updated_at

atualização

Relacionamento:

clientes 1 ─── N entregas

Snapshot na entrega

A entrega mantém dados próprios do cliente, como:

cliente_nome;

cliente_apelido;

cliente_telefone;

endereço;

referência.

Isso evita que uma alteração posterior no cadastro destrua o significado histórico de uma entrega antiga.

5.2 regiaos

O nome físico da tabela atual é regiaos.

Representa as regiões utilizadas para organização logística.

Campos conceituais conhecidos:

id;

nome;

cidade;

observacao;

ativo;

timestamps.

Relacionamentos:

regiaos 1 ─── N entregas
regiaos 1 ─── N saidas

Utilizações principais:

filtro de entregas;

organização operacional;

região principal da carrada;

composição da rota;

busca de entregas disponíveis;

busca de pendências disponíveis;

indicadores do Dashboard.

A rota completa não depende apenas de regiao_principal_id, pois saidas.rota funciona como snapshot ordenado.

5.3 funcionarios

Representa funcionários que podem participar da operação logística.

Campos conceituais conhecidos:

id;

nome;

funcao;

pode_dirigir;

ativo;

observacao;

timestamps.

Relacionamento operacional:

funcionarios N ─── N saidas
          através de saida_equipes

Essa entidade é diferente de users.

funcionarios representa pessoas da operação logística.

users representa contas de acesso ao sistema.

Uma mesma pessoa pode conceitualmente pertencer aos dois contextos, mas eles não devem ser confundidos automaticamente.

5.4 veiculos

Representa os veículos utilizados nas carradas.

Campos conhecidos:

id;

nome;

placa;

tipo;

status;

observacao;

ativo;

timestamps.

Estados utilizados:

DISPONIVEL
INDISPONIVEL
MANUTENCAO

Relacionamento:

veiculos 1 ─── N saidas

Uma troca de veículo em uma carrada não deve recriar a entrega ou alterar suas quantidades.

6. Importação de documentos

6.1 documento_importados

Registra documentos enviados para processamento e OCR.

Diferentemente das versões anteriores da documentação, esta entidade já faz parte do fluxo atual do sistema.

Campos conhecidos:

Campo

Função

id

identificador

usuario_id

usuário que realizou a importação

tipo_arquivo

tipo do arquivo

nome_arquivo

nome do arquivo

arquivo_path

localização armazenada

hash_sha256

hash para rastreabilidade/identificação

status

estado do processamento

texto_extraido

texto resultante do OCR

dados_extraidos

representação estruturada, quando disponível

erro_processamento

informação de falha, quando existir

created_at

momento da importação

Relacionamentos conceituais:

users 1 ─── N documento_importados

documento_importados 1 ─── 0..1 entregas

A entrega pode possuir:

documento_importado_id

para manter vínculo com sua origem.

6.2 Função histórica do documento

O documento importado não deve existir apenas como arquivo temporário sem contexto.

Ele permite rastrear:

arquivo recebido
→ texto extraído
→ dados interpretados
→ conferência
→ entrega gerada

6.3 OCR não substitui os dados confirmados

texto_extraido e dados_extraidos representam resultado automático.

A entrega definitiva representa os dados confirmados pelo operador.

Portanto:

resultado OCR
!=
verdade definitiva da entrega

O usuário pode corrigir a prévia antes da criação.

7. Entregas

7.1 entregas

É a entidade principal do pedido logístico.

Campos conhecidos:

Campo

Função

id

identificador interno

numero_pedido

número do pedido externo

documento_importado_id

documento de origem, quando existir

cliente_id

cliente cadastrado, quando utilizado

regiao_id

região

cliente_nome

snapshot do nome

cliente_apelido

snapshot do apelido

cliente_telefone

snapshot do telefone

endereco

endereço da entrega

referencia

referência de localização

prioridade

prioridade operacional

status

estado da entrega

observacao

observações

criado_por

usuário responsável pelo cadastro

created_at

data de lançamento no sistema

updated_at

última atualização

Prioridades atuais:

BAIXA
NORMAL
ALTA
URGENTE

Estados atuais:

PENDENTE
PROGRAMADA
EM_SAIDA
PARCIAL
CONCLUIDA
NAO_ENTREGUE
CANCELADA

Relacionamentos:

cliente 1 ─── N entregas
regiao 1 ─── N entregas

entregas 1 ─── N entrega_items
entregas 1 ─── N saida_entregas
entregas 1 ─── N tentativa_entregas
entregas 1 ─── N pendencias
entregas 1 ─── N devolucoes
entregas 1 ─── N entrega_eventos

7.2 created_at como data operacional de entrada

entregas.created_at representa quando a entrega foi lançada no sistema.

Essa data é usada para:

mostrar “Lançada em”;

calcular tempo de espera visual;

ordenar mais antigas primeiro;

ordenar mais recentes primeiro;

destacar pedidos que podem exigir atenção.

Alterar a entrega não deve reescrever sua data original de entrada.

7.3 Uma entrega pode participar de várias carradas

Relacionamento:

entregas N ─── N saidas

através de:

saida_entregas

Isso permite programação parcial.

Exemplo:

Pedido: 100 blocos

Carrada A: 55
Carrada B: 45

Nenhuma nova entrega é criada apenas porque o pedido foi dividido.

8. Itens da entrega

8.1 entrega_items

Representa cada produto solicitado em uma entrega.

Estrutura conhecida:

Campo

Tipo / característica

id

bigint

entrega_id

FK para entregas

codigo_produto

varchar, nullable

descricao_produto

descrição do item

unidade

unidade, nullable

quantidade_solicitada

quantidade solicitada

observacao

texto opcional

created_at

timestamp

updated_at

timestamp

Relacionamento:

entregas 1 ─── N entrega_items

Nome correto da coluna

O campo atual é:

descricao_produto

Não utilizar:

descricao

quando a operação estiver trabalhando diretamente com entrega_items.

8.2 Produto não depende de cadastro mestre

No escopo atual, entrega_items não depende de um cadastro central com todos os produtos da loja.

Isso permite importar diretamente itens presentes na nota/documento.

O item conserva:

código;

descrição;

unidade;

quantidade.

9. Carradas

9.1 saidas

Representa uma carrada.

Cada registro corresponde a uma saída física planejada ou realizada.

Campos conceituais importantes:

id;

regiao_principal_id;

veiculo_id;

data_saida;

status;

rota;

observacao;

criado_por;

timestamps.

Estados:

PLANEJADA
EM_ANDAMENTO
FINALIZADA
CANCELADA

Relacionamentos principais:

veiculo 1 ─── N saidas
regiao 1 ─── N saidas
users 1 ─── N saidas

saidas N ─── N entregas
saidas N ─── N pendencias

9.2 rota

saidas.rota é armazenada como uma estrutura ordenada.

Exemplo:

[
  {
    "regiao_id": 1,
    "nome": "Gameleira"
  },
  {
    "regiao_id": 2,
    "nome": "Serra de São João"
  },
  {
    "regiao_id": null,
    "nome": "Banco de Areia"
  }
]

A rota funciona como snapshot.

Vantagens:

preserva ordem;

preserva o nome utilizado no momento;

aceita parada livre;

evita alteração retroativa em carradas antigas.

10. Entregas dentro da carrada

10.1 saida_entregas

Tabela associativa entre carradas e entregas normais.

Relacionamento:

saidas N ─── N entregas

Ela representa:

esta entrega participa desta carrada.

Também pode manter informações específicas da participação, como ordem operacional.

10.2 saida_entrega_itens

Define quais produtos e quantidades de uma entrega foram programados em uma carrada específica.

Campos conhecidos:

id;

saida_entrega_id;

entrega_item_id;

quantidade_programada;

timestamps.

Existe unicidade conceitualmente importante para:

(saida_entrega_id, entrega_item_id)

Exemplo

Pedido:

100 blocos

Carrada atual:

55 blocos

Então:

saida_entrega_itens.quantidade_programada = 55

Os outros 45 continuam fora dessa programação.

10.3 Reserva de quantidade

Enquanto a carrada estiver:

PLANEJADA
ou
EM_ANDAMENTO sem retorno

a quantidade programada representa uma reserva.

Ela não deve aparecer novamente como disponível em outra carrada.

11. Equipe da carrada

11.1 saida_equipes

Relaciona funcionários a uma carrada.

Serve para registrar quem participou da operação.

Exemplos de papéis:

motorista;

ajudante;

carregador;

outros papéis definidos pelo domínio.

Relacionamento:

saidas N ─── N funcionarios
       via saida_equipes

Esse módulo pode evoluir futuramente sem alterar a identidade das entregas já registradas.

12. Eventos da carrada

12.1 saida_eventos

Registra a linha do tempo da carrada.

Campos conhecidos:

id;

saida_id;

usuario_id;

tipo_evento;

descricao;

dados;

created_at.

dados pode armazenar JSON com contexto do evento.

Eventos relevantes incluem:

SAIDA_CRIADA
ENTREGA_ADICIONADA
ENTREGA_ATUALIZADA
ENTREGA_REMOVIDA
PENDENCIA_ADICIONADA
PENDENCIA_REMOVIDA
SAIDA_INICIADA
RESULTADO_ENTREGA_REGISTRADO
RESULTADO_REENTREGA_REGISTRADO
VEICULO_TROCADO
SAIDA_FINALIZADA
SAIDA_CANCELADA

O evento explica a sequência.

Ele não substitui tabelas operacionais necessárias para cálculo.

13. Tentativas de entrega

13.1 tentativa_entregas

Representa uma tentativa de entrega normal dentro de uma carrada.

Uma tentativa pertence a:

uma entrega;

uma carrada.

Campos conceituais:

id;

entrega_id;

saida_id;

resultado;

motivo_nao_entrega;

observacao;

data_hora;

timestamps.

Resultados:

COMPLETA
PARCIAL
NAO_ENTREGUE

Regra importante:

uma entrega não deve possuir dois resultados da mesma natureza
para a mesma carrada

13.2 tentativa_items

Representa o resultado por produto dentro de uma tentativa.

Campos conceituais:

id;

tentativa_id;

entrega_item_id;

quantidade_entregue;

observacao.

Exemplo:

Programado: 50
Entregue:   45

A tabela registra:

quantidade_entregue = 45

Os 5 restantes são tratados separadamente como:

REENTREGA
ou
DEVOLVIDO

14. Pendências

14.1 pendencias

Representa uma obrigação de reentrega.

Estrutura conhecida:

Campo

Regra

id

PK

entrega_id

FK para entregas

entrega_item_id

FK para entrega_items

tentativa_origem_id

tentativa que originou a pendência, quando aplicável

quantidade_original

quantidade originalmente pendente

quantidade_resolvida

quantidade já tratada

motivo

motivo, quando aplicável

status

estado

observacao

texto opcional

data_abertura

abertura

data_resolucao

resolução, quando ocorrer

timestamps

Laravel

Estados:

ABERTA
PROGRAMADA
RESOLVIDA
CANCELADA

14.2 Saldo da pendência

saldo pendente
=
quantidade_original
-
quantidade_resolvida

Exemplo:

quantidade_original:  5
quantidade_resolvida: 3
saldo:                 2

14.3 Pendência não é um novo produto

A pendência referencia:

entrega_item_id

Ela não duplica o produto.

Ela representa uma obrigação logística relacionada a uma quantidade daquele item original.

15. Histórico da pendência

15.1 pendencia_movimentacoes

Registra como o estado da pendência evoluiu.

Campos conhecidos:

Campo

Função

id

identificador

pendencia_id

pendência

tentativa_id

tentativa relacionada, quando existir

tipo

tipo de movimentação

quantidade

quantidade relacionada

observacao

observação

usuario_id

responsável

timestamps

Laravel

Tipos conhecidos:

CRIACAO
PROGRAMACAO
ENTREGA_PARCIAL
RESOLUCAO
AJUSTE
CANCELAMENTO

Princípio:

pendencias
→ estado atual

pendencia_movimentacoes
→ como o estado foi alcançado

16. Programação de reentrega

16.1 saida_pendencias

Tabela associativa entre carrada e pendência.

Representa:

esta pendência foi programada nesta carrada.

Relacionamento:

saidas N ─── N pendencias

Fluxo:

Pendência ABERTA
      ↓
adicionada à carrada
      ↓
saida_pendencias
      ↓
Pendência PROGRAMADA

Se removida de carrada ainda planejada:

PROGRAMADA → ABERTA

17. Retorno de reentrega

17.1 saida_pendencia_retornos

Registra o resultado específico de uma reentrega.

Essa tabela é necessária porque reentrega é uma operação diferente de uma tentativa normal.

Estrutura conhecida:

Campo

Função

id

PK

saida_id

carrada

pendencia_id

pendência

quantidade_programada

quantidade levada

quantidade_entregue

entregue na reentrega

quantidade_devolvida

encerrada como devolução

quantidade_reentrega

quantidade que continuará pendente

destino_restante

REENTREGA ou DEVOLVIDO

motivo_devolucao

motivo, quando necessário

observacao

observação

registrado_por

usuário

data_hora

momento do retorno

timestamps

Laravel

Restrição importante:

(saida_id, pendencia_id)

deve identificar um único retorno daquela pendência naquela carrada.

17.2 Exemplo de retorno parcial

Pendência original:

5 argamassas

Resultado:

quantidade_programada: 5
quantidade_entregue:   3
quantidade_devolvida:  0
quantidade_reentrega:  2
destino_restante: REENTREGA

Consequência:

3 resolvidas
2 continuam pendentes

18. Devoluções

18.1 devolucoes

Representa uma quantidade encerrada logisticamente sem entrega ao cliente.

Estrutura conhecida:

Campo

Função

id

PK

entrega_id

entrega

entrega_item_id

item

saida_id

carrada, quando aplicável

tentativa_origem_id

tentativa normal de origem, quando aplicável

pendencia_id

pendência/reentrega de origem, quando aplicável

quantidade

quantidade devolvida

motivo

motivo

observacao

observação

registrado_por

usuário

data_hora

momento

timestamps

Laravel

Motivos previstos:

CLIENTE_RECUSOU
CLIENTE_DESISTIU
PRODUTO_INCORRETO
PRODUTO_AVARIADO
QUANTIDADE_EXCEDENTE
OUTRO

18.2 Origem da devolução

Uma devolução pode vir de uma entrega normal:

tentativa_origem_id != null

ou de uma reentrega:

pendencia_id != null

saida_id permite identificar em qual carrada a ocorrência foi registrada.

18.3 Devolução não altera o pedido original

Exemplo:

Solicitado: 50
Entregue:   45
Devolvido:   5

Continua:

quantidade_solicitada = 50

A devolução é uma entidade separada.

19. Histórico da entrega

19.1 entrega_eventos

Registra eventos associados à entrega.

Exemplos:

ENTREGA_CRIADA
ENTREGA_ATUALIZADA
ENTREGA_CANCELADA
RESULTADO_ENTREGA_REGISTRADO

Campos conceituais:

id;

entrega_id;

usuario_id;

tipo_evento;

descricao;

dados;

created_at.

Eventos complementam o histórico estruturado.

Eles não substituem:

tentativas;

pendências;

devoluções.

20. Ocorrências operacionais

20.1 ocorrencias_operacionais

Entidade destinada a registrar situações excepcionais da operação.

Exemplos de uso:

veículo quebrado;

motorista ausente;

mudança operacional;

problema durante execução;

ocorrência que precise ficar registrada.

Esse módulo ainda pode evoluir funcionalmente.

A existência da entidade permite que futuras telas sejam adicionadas sem misturar ocorrências com eventos comuns de entrega/carrada.

21. Cálculos derivados do modelo

21.1 Total entregue

Conceitualmente:

total entregue
=
entregas normais efetivamente entregues
+
reentregas efetivamente entregues

As fontes incluem:

tentativa_items
+
saida_pendencia_retornos

A implementação deve evitar dupla contagem.

21.2 Total devolvido

total devolvido
=
SUM(devolucoes.quantidade)

por item/entrega conforme a consulta.

21.3 Saldo logístico

saldo logístico
=
quantidade_solicitada
-
quantidade_entregue
-
quantidade_devolvida

21.4 Saldo pendente

saldo pendente
=
SUM(
    quantidade_original
    -
    quantidade_resolvida
)

considerando pendências ainda ativas.

21.5 Reserva normal

Representa quantidade de entrega normal já programada em carrada ainda não encerrada por retorno.

Fonte principal:

saida_entrega_itens

considerando o estado da carrada e a existência do retorno.

21.6 Saldo normal disponível

Conceitualmente:

disponível normal
=
solicitado
-
entregue
-
devolvido
-
pendências ativas
-
reservas ativas

Essa é uma das fórmulas mais importantes do sistema.

21.7 Quantidade ainda não enviada

Conceitualmente:

ainda não enviado
=
saldo restante
-
pendências ativas
-
quantidade reservada

respeitando as regras do domínio para evitar valores duplicados ou negativos.

22. Exemplo completo de saldo

Pedido:

100 blocos

Primeira carrada:

programado: 55
entregue:   50
reentrega:   5

Situação:

Solicitado:        100
Entregue:           50
Devolvido:           0
Saldo restante:     50
Pendência:            5
Em carrada:           0
Ainda não enviado:   45

A conta incorreta seria:

100 - 50 - 5 = 45 de saldo restante

Isso estaria errado porque a pendência já está dentro do saldo de 50.

O correto é:

saldo restante = 100 - 50 = 50

e dentro do saldo:

5  = pendência
45 = ainda não enviado

23. Estados principais do domínio

23.1 Entrega

PENDENTE
PROGRAMADA
EM_SAIDA
PARCIAL
CONCLUIDA
NAO_ENTREGUE
CANCELADA

23.2 Carrada

PLANEJADA
EM_ANDAMENTO
FINALIZADA
CANCELADA

23.3 Pendência

ABERTA
PROGRAMADA
RESOLVIDA
CANCELADA

23.4 Resultado de tentativa

COMPLETA
PARCIAL
NAO_ENTREGUE

23.5 Perfil de usuário

ADMIN
OPERADOR

24. Snapshots históricos

Algumas informações precisam continuar representando o passado mesmo quando o cadastro atual mudar.

Exemplos:

entregas.cliente_nome
entregas.cliente_apelido
entregas.cliente_telefone
entregas.endereco
entregas.referencia
saidas.rota

Princípio:

cadastro atual
não deve reescrever
fato histórico

25. Eventos x entidades operacionais

Eventos e entidades estruturadas possuem funções diferentes.

Evento

Responde:

o que aconteceu?
quando?
quem fez?

Entidade operacional

Responde:

qual é o estado atual?
qual quantidade entra no cálculo?
qual relacionamento existe?

Exemplo:

RESULTADO_ENTREGA_REGISTRADO

pode existir em saida_eventos.

Mas as quantidades reais precisam continuar presentes em:

tentativa_entregas
tentativa_items
devolucoes
pendencias

26. Relação entre entrega normal, pendência e devolução

flowchart TD
    A[entrega_items] --> B[saida_entrega_itens]
    B --> C[tentativa_items]

    C --> D{Faltou quantidade carregada?}

    D -->|Não| E[Quantidade entregue]
    D -->|Sim + REENTREGA| F[pendencias]
    D -->|Sim + DEVOLVIDO| G[devolucoes]

    F --> H[saida_pendencias]
    H --> I[saida_pendencia_retornos]

    I --> J{Restou quantidade?}
    J -->|Não| K[Pendência RESOLVIDA]
    J -->|Sim + REENTREGA| F
    J -->|Sim + DEVOLVIDO| G

27. Relação da importação com a entrega

flowchart LR
    A[documento_importados] --> B[texto_extraido]
    B --> C[dados_extraidos]
    C --> D[conferência do usuário]
    D --> E[entregas]
    E --> F[entrega_items]

O documento mantém a origem.

A entrega mantém os dados operacionais definitivos.

28. Relação entre autenticação e auditoria

flowchart TD
    A[users] --> B[criado_por]
    A --> C[registrado_por]
    A --> D[usuario_id]

    B --> E[entregas / saidas]
    C --> F[devoluções / retornos]
    D --> G[eventos / movimentações]

O sistema deve preservar o responsável quando a ação possuir importância operacional ou administrativa.

29. Integridade transacional

Operações que alteram múltiplas tabelas devem ocorrer dentro de transações quando necessário.

Exemplo:

registrar retorno

pode afetar:

tentativa_entregas
tentativa_items
pendencias
pendencia_movimentacoes
devolucoes
entregas
entrega_eventos
saida_eventos

Princípio:

ou a operação inteira é persistida
ou nenhuma parte fica gravada

30. Regras estruturais importantes

30.1 Não duplicar obrigação logística

Uma mesma unidade não pode estar simultaneamente:

disponível como saldo normal;

pendente para reentrega;

reservada em mais de uma carrada;

devolvida e ainda aguardando entrega;

entregue e ainda disponível.

30.2 Pendência não substitui o item original

pendencias referencia entrega_items.

Ela não cria um novo produto.

30.3 Devolução não reduz a quantidade solicitada original

O pedido original deve continuar preservado.

A devolução é registrada separadamente.

30.4 Histórico não deve ser apagado ao resolver uma pendência

Ao resolver uma pendência, não excluir:

pendência;

movimentações;

associações históricas;

retornos;

devoluções.

O status representa o estado atual.

As demais tabelas preservam como ele chegou ali.

30.5 Registros operacionais devem preferir cancelamento/inativação

Quando o dado participa de histórico, preferir:

ativo = false;

estado CANCELADA;

resolução;

evento;

em vez de remoção física destrutiva.

30.6 Paginação e ordenação pertencem à consulta

A ordenação de entregas por created_at deve ser realizada antes da paginação.

Exemplo:

ORDER BY created_at ASC
→ mais antigas primeiro

ORDER BY created_at DESC
→ mais recentes primeiro

Ordenar apenas os registros recebidos no frontend não representa corretamente o conjunto inteiro.

31. Índices e constraints

O banco deve utilizar constraints para proteger regras estruturais quando apropriado.

Exemplos conhecidos/importantes:

users.email
→ único

saida_entrega_itens
→ unicidade de item dentro da associação da carrada

saida_pendencia_retornos
→ unicidade por saída + pendência

Foreign keys devem ser utilizadas para preservar relacionamentos sempre que o domínio exigir.

32. Datas e timestamps

O banco utiliza timestamps para rastreabilidade.

Campos com função importante incluem:

entregas.created_at
pendencias.data_abertura
pendencias.data_resolucao
tentativa_entregas.data_hora
devolucoes.data_hora
saida_pendencia_retornos.data_hora
eventos.created_at

A aplicação diferencia:

armazenamento
e
horário operacional

O fuso operacional atual é:

America/Fortaleza

Consultas por período devem respeitar essa configuração.

33. Resumo dos relacionamentos principais

User
├── cria Entregas
├── cria Carradas
├── importa Documentos
├── registra Movimentações
├── registra Devoluções
└── aparece em Eventos

Documento importado
└── pode originar Entrega

Cliente
└── Entregas
     ├── Itens
     │    ├── Programações em carradas
     │    ├── Resultados de tentativa
     │    ├── Pendências
     │    └── Devoluções
     │
     ├── Tentativas
     ├── Eventos
     └── Pendências

Carrada
├── Rota
├── Veículo
├── Equipe
├── Entregas normais
│    └── Itens programados
├── Pendências / reentregas
│    └── Retornos
├── Devoluções
└── Eventos

34. Tabelas centrais do sistema atual

Para compreender o núcleo logístico, as tabelas mais importantes são:

entregas
entrega_items

saidas
saida_entregas
saida_entrega_itens

tentativa_entregas
tentativa_items

pendencias
pendencia_movimentacoes
saida_pendencias
saida_pendencia_retornos

devolucoes

entrega_eventos
saida_eventos

Para compreender o sistema completo atual, acrescentar:

users
clientes
regiaos
funcionarios
veiculos
documento_importados
ocorrencias_operacionais

35. Ciclo representado pelo modelo

DOCUMENTO / CADASTRO MANUAL
          ↓
        ENTREGA
          ↓
         ITENS
          ↓
     PLANEJAMENTO
          ↓
        CARRADA
          ↓
        RETORNO
          ↓
   ┌──────┼─────────┐
   ↓      ↓         ↓
ENTREGUE REENTREGA DEVOLVIDO
          ↓
      PENDÊNCIA
          ↓
     NOVA CARRADA
          ↓
       RETORNO
          ↓
      RESOLUÇÃO
          ↓
       HISTÓRICO

36. Evolução futura do modelo

O modelo atual já suporta o núcleo operacional construído.

Evoluções futuras podem exigir novas entidades ou extensão das atuais para:

ocorrências operacionais completas;

custos por viagem;

custos de reentrega;

capacidade de veículo;

peso e volume de cargas;

otimização de rota;

integração com ERP;

integrações externas;

planejamento automático;

módulos mobile.

Essas extensões não devem quebrar o histórico existente.

37. Regra de manutenção deste documento

Quando uma migration estrutural for criada ou alterada, verificar impacto em:

Models;

relacionamentos;

Enums;

Services;

Resources;

Types TypeScript;

endpoints;

testes;

docs/banco/modelo_entidades.md.

O banco de dados é parte da arquitetura do produto e sua documentação deve permanecer alinhada ao código.