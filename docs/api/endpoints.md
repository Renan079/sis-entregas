Endpoints da API

1. Objetivo

Este documento registra os endpoints atuais do Sistema de Gerenciamento de Entregas.

Ele serve como referência para:

frontend;

backend;

testes;

manutenção;

integração entre módulos;

evolução futura da API.

A documentação considera as rotas atuais do projeto, incluindo:

autenticação;

usuários;

regiões;

clientes;

veículos;

entregas;

pendências;

carradas;

históricos;

Dashboard;

relatórios.

2. Endereços do ambiente local

Backend:

http://127.0.0.1:8001

Frontend:

http://127.0.0.1:5173

Rotas operacionais da API utilizam o prefixo:

/api

Exemplo:

GET http://127.0.0.1:8001/api/entregas

As rotas de sessão:

/login
/register
/logout

não utilizam o prefixo /api.

3. Autenticação e proteção das rotas

As rotas operacionais atuais são agrupadas com:

Route::middleware([
    'auth:sanctum',
    EnsureUserIsActive::class,
])

Portanto, para acessar a API operacional, o usuário precisa:

possuir sessão autenticada;

estar com a conta ativa.

Arquitetura:

Vue
 ↓
Axios
 ↓
cookies / XSRF
 ↓
Laravel Sanctum
 ↓
auth:sanctum
 ↓
EnsureUserIsActive
 ↓
Controller

4. Formato geral das respostas

Resources normalmente seguem:

{
  "data": {}
}

Listagens podem seguir:

{
  "data": [],
  "meta": {}
}

Alguns endpoints retornam também informações extras, como:

{
  "data": [],
  "resumo": {},
  "meta": {}
}

Erros de validação utilizam o padrão do Laravel.

Exemplo conceitual:

{
  "message": "The given data was invalid.",
  "errors": {
    "campo": [
      "Mensagem de validação."
    ]
  }
}

5. CSRF — Laravel Sanctum

GET /sanctum/csrf-cookie

Inicializa a proteção CSRF necessária para autenticação por sessão.

O frontend chama esse endpoint antes do login.

Fluxo:

GET /sanctum/csrf-cookie
↓
POST /login
↓
GET /api/user

6. Login

POST /login

Realiza autenticação.

Payload

{
  "email": "admin@sistema.com",
  "password": "senha"
}

O e-mail é normalizado para minúsculas.

Regras

e-mail é obrigatório;

senha é obrigatória;

credenciais precisam ser válidas;

usuário precisa estar ativo.

Resposta de sucesso

{
  "message": "Login realizado com sucesso.",
  "user": {
    "id": 1,
    "name": "Administrador",
    "email": "admin@sistema.com",
    "perfil": "ADMIN",
    "ativo": true
  }
}

Usuário inativo

A sessão é encerrada e a API retorna erro de validação.

7. Logout

POST /logout

Exige sessão autenticada.

Encerra a sessão atual.

Resposta

{
  "message": "Logout realizado com sucesso."
}

O backend:

realiza logout;

invalida a sessão;

regenera o token CSRF.

8. Usuário autenticado

GET /api/user

Retorna o usuário da sessão atual.

Exemplo

{
  "id": 1,
  "name": "Administrador",
  "email": "admin@sistema.com",
  "perfil": "ADMIN",
  "ativo": true
}

Esse endpoint é utilizado pelo frontend para restaurar o estado de autenticação.

9. Cadastro administrativo de usuário

POST /register

O sistema não possui cadastro público de usuário.

A rota exige:

sessão autenticada
+
perfil ADMIN

Payload

{
  "name": "Operador 01",
  "email": "operador@sistema.com",
  "password": "senha-segura",
  "password_confirmation": "senha-segura"
}

Resultado

O usuário é criado automaticamente como:

perfil = OPERADOR
ativo = true

Resposta

201 Created

Exemplo:

{
  "message": "Usuário cadastrado com sucesso.",
  "user": {
    "id": 2,
    "name": "Operador 01",
    "email": "operador@sistema.com",
    "perfil": "OPERADOR",
    "ativo": true
  }
}

Sem permissão

Usuário não administrador recebe:

403 Forbidden

10. Gestão de usuários

As rotas abaixo são administrativas.

O UsuarioController também valida explicitamente que o usuário autenticado possui:

perfil = ADMIN
ativo = true

10.1 Listar usuários

GET /api/usuarios

Query parameters

busca
page
per_page

Valores permitidos para per_page:

10
20
50

busca procura por:

nome;

e-mail.

Exemplo

GET /api/usuarios?busca=renan&page=1&per_page=10

Resposta

{
  "data": [
    {
      "id": 1,
      "name": "Administrador",
      "email": "admin@sistema.com",
      "perfil": "ADMIN",
      "ativo": true,
      "created_at": "2026-08-01T12:00:00.000000Z",
      "updated_at": "2026-09-01T12:00:00.000000Z"
    }
  ],
  "resumo": {
    "total": 2,
    "ativos": 2,
    "admins": 1,
    "operadores": 1
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 2
  }
}

A ordenação coloca administradores primeiro e depois ordena por nome.

10.2 Atualizar usuário

PUT /api/usuarios/{usuario}

Payload

{
  "name": "Operador Atualizado",
  "email": "operador@sistema.com",
  "perfil": "OPERADOR",
  "ativo": true
}

Perfis aceitos

ADMIN
OPERADOR

Proteções

O administrador autenticado não pode:

remover seu próprio perfil ADMIN;

desativar a própria conta.

Resposta

{
  "message": "Usuário atualizado com sucesso.",
  "user": {}
}

10.3 Redefinir senha

PUT /api/usuarios/{usuario}/senha

Payload

{
  "password": "nova-senha",
  "password_confirmation": "nova-senha"
}

Resposta

{
  "message": "Senha de Operador redefinida com sucesso."
}

11. Regiões

As rotas são geradas por:

Route::apiResource(
    'regioes',
    RegiaoController::class
)

A URL utiliza regioes, mas a tabela física atual é regiaos.

Método

Endpoint

Objetivo

GET

/api/regioes

Listar regiões

POST

/api/regioes

Criar região

GET

/api/regioes/{regiao}

Buscar região

PUT/PATCH

/api/regioes/{regiao}

Atualizar região

DELETE

/api/regioes/{regiao}

Remover/inativar conforme regra atual

Regiões são utilizadas em:

entregas;

rotas;

filtros;

Dashboard;

localização de entregas disponíveis;

localização de pendências.

12. Clientes

As rotas básicas são geradas por:

Route::apiResource(
    'clientes',
    ClienteController::class
)

Método

Endpoint

Objetivo

GET

/api/clientes

Listar/pesquisar clientes

POST

/api/clientes

Criar cliente

GET

/api/clientes/{cliente}

Buscar cliente

PUT/PATCH

/api/clientes/{cliente}

Atualizar cliente

DELETE

/api/clientes/{cliente}

Remover/inativar conforme regra

13. Histórico de cliente

GET /api/clientes/{cliente}/entregas

Retorna o histórico de entregas associado ao cliente.

Query parameters

page
per_page

Resposta conceitual

{
  "data": {
    "cliente": {
      "id": 1,
      "codigo_externo": null,
      "nome": "João",
      "apelido": null,
      "telefone": null,
      "ativo": true
    },
    "resumo": {
      "total": 10,
      "pendentes": 2,
      "programadas": 1,
      "em_saida": 0,
      "parciais": 1,
      "concluidas": 6,
      "nao_entregues": 0,
      "canceladas": 0
    },
    "entregas": [],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 10
    }
  }
}

Cada entrega pode incluir:

número do pedido;

status;

prioridade;

endereço;

referência;

observação;

região;

itens;

created_at.

14. Veículos

As rotas são geradas por:

Route::apiResource(
    'veiculos',
    VeiculoController::class
)

Método

Endpoint

Objetivo

GET

/api/veiculos

Listar veículos

POST

/api/veiculos

Criar veículo

GET

/api/veiculos/{veiculo}

Buscar veículo

PUT/PATCH

/api/veiculos/{veiculo}

Atualizar veículo

DELETE

/api/veiculos/{veiculo}

Remover/inativar conforme regra

15. Entregas — rotas básicas

As rotas são geradas por:

Route::apiResource(
    'entregas',
    EntregaController::class
)

Método

Endpoint

Objetivo

GET

/api/entregas

Listar entregas

POST

/api/entregas

Criar entrega

GET

/api/entregas/{entrega}

Buscar entrega

PUT/PATCH

/api/entregas/{entrega}

Atualizar entrega

DELETE

/api/entregas/{entrega}

Cancelar entrega

16. Listar entregas

GET /api/entregas

A listagem é paginada em:

20 registros por página

Query parameters

busca
regiao_id
status
prioridade
ordem
page

16.1 Busca

busca pesquisa por:

numero_pedido;

cliente_nome;

cliente_apelido;

cliente_telefone;

endereco.

PostgreSQL utiliza busca case-insensitive com ILIKE.

16.2 Filtro por região

regiao_id

Exemplo:

GET /api/entregas?regiao_id=2

16.3 Filtro por status

status

Estados:

PENDENTE
PROGRAMADA
EM_SAIDA
PARCIAL
CONCLUIDA
NAO_ENTREGUE
CANCELADA

16.4 Filtro por prioridade

prioridade

Valores:

BAIXA
NORMAL
ALTA
URGENTE

16.5 Ordenação por data de lançamento

ordem

Valores utilizados pelo frontend:

antigas
recentes

Mais antigas primeiro

GET /api/entregas?ordem=antigas

Ordenação:

created_at ASC
id ASC

Mais recentes primeiro

GET /api/entregas?ordem=recentes

Ordenação:

created_at DESC
id DESC

Padrão

Se ordem não for recentes, o backend utiliza as mais antigas primeiro.

A ordenação acontece antes da paginação.

17. Estrutura de uma entrega

O EntregaResource expõe campos como:

{
  "id": 10,
  "numero_pedido": "89567",
  "cliente": {
    "id": 1,
    "nome": "João",
    "apelido": null
  },
  "regiao": {
    "id": 2,
    "nome": "Serra de São João",
    "cidade": null
  },
  "cliente_nome": "João",
  "cliente_apelido": null,
  "cliente_telefone": null,
  "endereco": "Rua A",
  "referencia": null,
  "prioridade": "NORMAL",
  "status": "PENDENTE",
  "observacao": null,
  "itens": [],
  "created_at": "2026-09-11T12:00:00.000000Z",
  "updated_at": "2026-09-11T12:00:00.000000Z"
}

created_at é usado pela tela para mostrar a data de lançamento.

18. Criar entrega

POST /api/entregas

Payload conceitual

{
  "numero_pedido": "89567",
  "cliente_id": 1,
  "regiao_id": 2,
  "cliente_nome": "João",
  "cliente_apelido": null,
  "cliente_telefone": null,
  "endereco": "Rua A",
  "referencia": "Próximo à praça",
  "prioridade": "NORMAL",
  "observacao": null,
  "itens": [
    {
      "codigo_produto": "148",
      "descricao_produto": "Argamassa AC3",
      "unidade": "UN",
      "quantidade_solicitada": 50,
      "observacao": null
    }
  ]
}

Se cliente_id for informado, o backend utiliza os dados atuais do cliente como snapshot.

A entrega nasce com:

status = PENDENTE

Também é registrado:

criado_por = usuário autenticado

E um evento:

ENTREGA_CRIADA

Resposta

201 Created

19. Atualizar entrega

PUT/PATCH /api/entregas/{entrega}

Atualiza dados permitidos da entrega.

Regra crítica

Depois que a entrega já possuir tentativa, seus itens não podem ser substituídos.

O backend retorna erro de validação se uma atualização tentar alterar os itens depois de existir histórico operacional.

A atualização gera evento:

ENTREGA_ATUALIZADA

20. Cancelar entrega

DELETE /api/entregas/{entrega}

O endpoint não realiza exclusão física.

Ele altera o status para:

CANCELADA

Proteção

Entrega CONCLUIDA não pode ser cancelada.

Evento

ENTREGA_CANCELADA

Resposta

204 No Content

21. Histórico da entrega

GET /api/entregas/{entrega}/historico

Retorna a visão quantitativa e histórica da entrega.

Informações por item incluem:

comprado
entregue
saldo_restante
pendente
em_carrada
nao_enviado

O histórico também inclui:

tentativas;

veículo utilizado;

resultado;

observações;

pendências.

Estrutura conceitual

{
  "data": {
    "entrega": {},
    "itens": [],
    "tentativas": [],
    "pendencias": []
  }
}

22. Pendências — listagem

GET /api/pendencias

Lista pendências de forma paginada.

Query parameters

busca
status
regiao_id
data_inicio
data_fim
per_page
page

Resposta conceitual

{
  "data": [],
  "resumo": {
    "abertas": 0,
    "programadas": 0,
    "resolvidas_30_dias": 0,
    "total_30_dias": 0
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 0
  }
}

Cada pendência pode informar:

pedido;

cliente;

região;

produto;

quantidade original;

quantidade resolvida;

saldo pendente;

status;

data de abertura;

data de resolução;

carrada atual;

possibilidade de resolução manual.

23. Resolver pendência manualmente

POST /api/pendencias/{pendencia}/resolver-manualmente

Utilizado quando uma pendência será encerrada administrativamente como devolução.

Payload

{
  "motivo_devolucao": "CLIENTE_DESISTIU",
  "observacao": "Cliente não deseja mais receber o produto."
}

Motivos aceitos

CLIENTE_RECUSOU
CLIENTE_DESISTIU
PRODUTO_INCORRETO
PRODUTO_AVARIADO
QUANTIDADE_EXCEDENTE
OUTRO

observacao:

opcional
máximo 500 caracteres

Resposta conceitual

{
  "data": {
    "id": 1,
    "status": "RESOLVIDA",
    "quantidade_original": 5,
    "quantidade_resolvida": 5,
    "saldo_pendente": 0,
    "data_resolucao": "2026-09-11T12:00:00Z"
  }
}

24. Carradas — listagem

GET /api/saidas

Lista carradas.

Query parameters conhecidos

busca
status
data
page

Busca por número

Exemplos aceitos:

35
#35
Carrada 35

Exemplo

GET /api/saidas?status=PLANEJADA&page=1

25. Criar carrada

POST /api/saidas

Payload

{
  "data_saida": "2026-09-11",
  "regiao_principal_id": 1,
  "veiculo_id": 2,
  "rota": [
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
  ],
  "observacao": null
}

Regras conhecidas

data_saida:

required
date

regiao_principal_id:

nullable
exists:regiaos,id

veiculo_id:

nullable
exists:veiculos,id

rota:

nullable
array
máximo 30 paradas

rota.*.regiao_id:

nullable
exists:regiaos,id

rota.*.nome:

string
máximo 150 caracteres

observacao:

nullable
máximo 2000 caracteres

26. Buscar carrada

GET /api/saidas/{saida}

Retorna detalhes da carrada.

Pode incluir:

região principal;

veículo;

rota;

entregas normais;

itens programados;

equipe;

pendências/reentregas.

27. Entregas disponíveis pela rota

GET /api/saidas/{saida}/entregas-disponiveis

Retorna entregas que podem ser adicionadas à carrada.

O backend considera as regiões cadastradas presentes em rota.

Estados considerados

PENDENTE
PROGRAMADA
PARCIAL
NAO_ENTREGUE

Regras

região precisa fazer parte da rota;

entrega já adicionada à carrada é excluída;

precisa existir saldo normal disponível;

entrega com somente pendência não volta como entrega normal;

a ordem acompanha a rota.

28. Itens disponíveis para planejamento

GET /api/planejamento/entregas/{entrega}/itens-disponiveis

Retorna produtos e quantidade disponível para uma nova programação normal.

Exemplo

{
  "data": {
    "entrega_id": 10,
    "numero_pedido": "89567",
    "cliente_nome": "João",
    "endereco": "Rua A",
    "itens": [
      {
        "entrega_item_id": 20,
        "codigo_produto": "148",
        "descricao_produto": "Argamassa AC3",
        "unidade": "UN",
        "quantidade_solicitada": 50,
        "quantidade_disponivel": 45
      }
    ]
  }
}

quantidade_disponivel considera:

entregue;

devolvido;

pendências ativas;

reservas em carradas.

29. Adicionar entrega à carrada

POST /api/saidas/{saida}/entregas

Payload conceitual

{
  "entrega_id": 10,
  "ordem": 1,
  "itens": [
    {
      "entrega_item_id": 20,
      "quantidade_programada": 30
    }
  ]
}

Regras

carrada precisa estar PLANEJADA;

entrega precisa estar em estado compatível;

item precisa pertencer à entrega;

quantidade precisa ser positiva;

quantidade não pode superar saldo disponível.

30. Remover entrega da carrada

DELETE /api/saidas/{saida}/entregas/{entrega}

Permitido enquanto a carrada estiver:

PLANEJADA

A remoção libera as reservas correspondentes.

31. Pendências disponíveis pela rota

GET /api/saidas/{saida}/pendencias-disponiveis

Lista pendências ABERTA que podem entrar como reentrega.

Regras

região precisa pertencer à rota cadastrada;

saldo pendente precisa ser maior que zero;

status precisa ser ABERTA;

pendência já adicionada não aparece novamente;

ordenação acompanha a rota.

32. Pendências vinculadas à carrada

GET /api/saidas/{saida}/pendencias

Retorna reentregas que já pertencem à carrada.

A resposta pode incluir:

resultado_registrado
resultado_id
quantidade_programada_resultado
quantidade_entregue_resultado
quantidade_devolvida_resultado
quantidade_reentrega_resultado
destino_restante
motivo_devolucao
observacao_resultado
data_hora_resultado

33. Adicionar pendência à carrada

POST /api/saidas/{saida}/pendencias/{pendencia}

Programa a pendência como reentrega.

Regras

carrada precisa estar PLANEJADA;

pendência precisa possuir saldo;

pendência resolvida ou cancelada não pode ser programada;

a mesma pendência não pode estar em duas carradas ao mesmo tempo.

Estado

ABERTA → PROGRAMADA

Resposta

204 No Content

34. Remover pendência da carrada

DELETE /api/saidas/{saida}/pendencias/{pendencia}

Remove a reentrega de uma carrada planejada.

Resultado

PROGRAMADA → ABERTA

Resposta

204 No Content

35. Iniciar carrada

POST /api/saidas/{saida}/iniciar

Estado

PLANEJADA → EM_ANDAMENTO

Regra

A carrada precisa possuir pelo menos:

uma entrega normal; ou

uma reentrega.

36. Registrar retorno de entrega normal

POST /api/saidas/{saida}/entregas/{entrega}/resultado

Registra o resultado real dos itens carregados.

Payload

{
  "itens": [
    {
      "entrega_item_id": 20,
      "quantidade_entregue": 45,
      "destino_nao_entregue": "REENTREGA",
      "motivo_devolucao": null,
      "observacao": null
    },
    {
      "entrega_item_id": 21,
      "quantidade_entregue": 18,
      "destino_nao_entregue": "DEVOLVIDO",
      "motivo_devolucao": "CLIENTE_RECUSOU",
      "observacao": "Cliente não quis receber duas unidades."
    }
  ],
  "motivo_nao_entrega": null,
  "observacao": null
}

Destino da diferença

REENTREGA
DEVOLVIDO

Regras

carrada deve estar EM_ANDAMENTO;

entrega precisa pertencer à carrada;

retorno não pode ser duplicado;

quantidade entregue não pode ser negativa;

quantidade entregue não pode ultrapassar carregado;

item precisa estar programado.

Efeitos

REENTREGA
→ cria/sincroniza pendência

DEVOLVIDO
→ registra devolução
→ não cria pendência daquela quantidade

Resposta

201 Created

37. Registrar retorno de reentrega

POST /api/saidas/{saida}/pendencias/{pendencia}/resultado

Reentrega completa

{
  "quantidade_entregue": 5,
  "destino_restante": null,
  "motivo_devolucao": null,
  "observacao": null
}

Reentrega parcial que continuará

{
  "quantidade_entregue": 3,
  "destino_restante": "REENTREGA",
  "motivo_devolucao": null,
  "observacao": null
}

Reentrega parcial com devolução

{
  "quantidade_entregue": 3,
  "destino_restante": "DEVOLVIDO",
  "motivo_devolucao": "CLIENTE_DESISTIU",
  "observacao": "Cliente não deseja mais receber o restante."
}

Regras

pendência precisa pertencer à carrada;

quantidade entregue não pode ultrapassar programada;

se restar quantidade, o destino precisa ser definido;

REENTREGA mantém obrigação pendente;

DEVOLVIDO registra devolução.

Resposta

201 Created

38. Finalizar carrada

POST /api/saidas/{saida}/finalizar

Finaliza a carrada em andamento.

Regra crítica

Todos os retornos precisam estar registrados:

entregas normais
+
reentregas

Estado

EM_ANDAMENTO → FINALIZADA

39. Cancelar carrada

POST /api/saidas/{saida}/cancelar

Cancela uma carrada quando permitido pelas regras do Service.

Resposta

204 No Content

Reservas e associações precisam ser tratadas de forma consistente sem destruir o histórico.

40. Histórico da carrada

GET /api/saidas/{saida}/historico

Retorna a representação histórica completa da viagem.

Estrutura conceitual

{
  "data": {
    "saida": {},
    "resumo": {},
    "entregas": [],
    "reentregas": [],
    "eventos": []
  }
}

Resumo pode incluir

total_entregas
total_entregas_normais
total_reentregas
total_atendimentos
completas
parciais
nao_entregues
sem_resultado
sem_resultado_entregas
sem_resultado_reentregas
quantidade_pendencias_geradas
quantidade_reentrega_programada
quantidade_entregue_reentrega
quantidade_devolvida

Carrada contendo apenas reentrega deve continuar aparecendo como operação válida.

41. Dashboard

GET /api/dashboard

Não exige parâmetros no fluxo atual.

Retorna a visão operacional resumida.

Estrutura

{
  "data": {
    "resumo": {
      "entregas_realizadas_mes": 0,
      "aguardando_programacao": 0,
      "carradas_hoje": 0,
      "carradas_planejadas_hoje": 0,
      "carradas_em_andamento_hoje": 0,
      "carradas_finalizadas_hoje": 0,
      "reentregas_abertas": 0,
      "pedidos_com_problema": 0
    },
    "entregas_por_regiao": [],
    "proximas_entregas": [],
    "pendencias_atencao": [],
    "carradas_hoje": [],
    "periodo": {
      "inicio_mes": "2026-09-01",
      "fim_mes": "2026-09-30",
      "atualizado_em": "2026-09-11T12:00:00Z"
    }
  }
}

41.1 Entregas antigas no Dashboard

proximas_entregas pode conter:

id
numero_pedido
cliente_nome
endereco
status
prioridade
regiao
total_itens
criada_em

Essas informações permitem destacar pedidos antigos que continuam sem programação adequada.

41.2 Pendências em atenção

pendencias_atencao pode conter:

id
entrega_id
numero_pedido
cliente_nome
produto
saldo_pendente
regiao
data_abertura
dias_aberta


44. Relatórios

GET /api/relatorios

Gera relatório operacional consolidado.

Query parameters obrigatórios

inicio
fim

Formato:

YYYY-MM-DD

Query parameters opcionais

regiao_id
status

Exemplo

GET /api/relatorios?inicio=2026-09-01&fim=2026-09-30

Com filtros:

GET /api/relatorios?inicio=2026-09-01&fim=2026-09-30&regiao_id=2&status=CONCLUIDA

44.1 Validações de período

fim precisa ser:

>= inicio

Período máximo:

366 dias

regiao_id precisa existir em:

regiaos

status precisa ser um estado válido de entrega.

44.2 Timezone

Os limites do período devem respeitar:

APP_OPERATION_TIMEZONE=America/Fortaleza

Datas locais são convertidas corretamente para consulta dos timestamps armazenados.

44.3 Resposta do relatório

Estrutura:

{
  "data": {
    "periodo": {
      "inicio": "2026-09-01",
      "fim": "2026-09-30"
    },
    "filtros": {
      "regiao_id": null,
      "status": null
    },
    "opcoes": {
      "regioes": []
    },
    "resumo": {
      "total_entregas": 0,
      "concluidas": 0,
      "parciais": 0,
      "nao_entregues": 0,
      "canceladas": 0,
      "pendencias_abertas": 0,
      "pendencias_programadas": 0,
      "quantidade_devolvida": 0
    },
    "entregas_por_status": [],
    "entregas_por_regiao": [],
    "pendencias": {
      "total_criadas": 0,
      "abertas": 0,
      "programadas": 0,
      "resolvidas": 0,
      "canceladas": 0
    },
    "devolucoes": {
      "quantidade_total": 0,
      "registros": 0,
      "por_motivo": []
    },
    "entregas_recentes": [],
    "gerado_em": "2026-09-11T12:00:00Z"
  }
}

45. Estados utilizados pela API

45.1 Entrega

PENDENTE
PROGRAMADA
EM_SAIDA
PARCIAL
CONCLUIDA
NAO_ENTREGUE
CANCELADA

45.2 Prioridade

BAIXA
NORMAL
ALTA
URGENTE

45.3 Carrada

PLANEJADA
EM_ANDAMENTO
FINALIZADA
CANCELADA

45.4 Pendência

ABERTA
PROGRAMADA
RESOLVIDA
CANCELADA

45.5 Resultado de tentativa

COMPLETA
PARCIAL
NAO_ENTREGUE

45.6 Destino da quantidade não entregue

REENTREGA
DEVOLVIDO

45.7 Perfil de usuário

ADMIN
OPERADOR

46. Motivos de devolução

Código

Exibição sugerida

CLIENTE_RECUSOU

Cliente recusou

CLIENTE_DESISTIU

Cliente desistiu

PRODUTO_INCORRETO

Produto incorreto

PRODUTO_AVARIADO

Produto avariado

QUANTIDADE_EXCEDENTE

Quantidade excedente

OUTRO

Outro

47. Resumo das rotas atuais

Sessão

Método

Endpoint

GET

/sanctum/csrf-cookie

POST

/login

POST

/register

POST

/logout

Usuário

Método

Endpoint

GET

/api/user

GET

/api/usuarios

PUT

/api/usuarios/{usuario}

PUT

/api/usuarios/{usuario}/senha

Regiões

Método

Endpoint

GET

/api/regioes

POST

/api/regioes

GET

/api/regioes/{regiao}

PUT/PATCH

/api/regioes/{regiao}

DELETE

/api/regioes/{regiao}

Clientes

Método

Endpoint

GET

/api/clientes

POST

/api/clientes

GET

/api/clientes/{cliente}

PUT/PATCH

/api/clientes/{cliente}

DELETE

/api/clientes/{cliente}

GET

/api/clientes/{cliente}/entregas

Entregas

Método

Endpoint

GET

/api/entregas

POST

/api/entregas

GET

/api/entregas/{entrega}

PUT/PATCH

/api/entregas/{entrega}

DELETE

/api/entregas/{entrega}

GET

/api/entregas/{entrega}/historico

GET

/api/planejamento/entregas/{entrega}/itens-disponiveis

Pendências

Método

Endpoint

GET

/api/pendencias

POST

/api/pendencias/{pendencia}/resolver-manualmente

Veículos

Método

Endpoint

GET

/api/veiculos

POST

/api/veiculos

GET

/api/veiculos/{veiculo}

PUT/PATCH

/api/veiculos/{veiculo}

DELETE

/api/veiculos/{veiculo}

Carradas

Método

Endpoint

GET

/api/saidas

POST

/api/saidas

GET

/api/saidas/{saida}

GET

/api/saidas/{saida}/entregas-disponiveis

GET

/api/saidas/{saida}/pendencias-disponiveis

GET

/api/saidas/{saida}/pendencias

POST

/api/saidas/{saida}/entregas

DELETE

/api/saidas/{saida}/entregas/{entrega}

POST

/api/saidas/{saida}/pendencias/{pendencia}

DELETE

/api/saidas/{saida}/pendencias/{pendencia}

POST

/api/saidas/{saida}/iniciar

POST

/api/saidas/{saida}/entregas/{entrega}/resultado

POST

/api/saidas/{saida}/pendencias/{pendencia}/resultado

POST

/api/saidas/{saida}/finalizar

POST

/api/saidas/{saida}/cancelar

GET

/api/saidas/{saida}/historico

Dashboard

Método

Endpoint

GET

/api/dashboard

Método

Endpoint

Relatórios

Método

Endpoint

GET

/api/relatorios

48. Fluxo operacional principal pela API

sequenceDiagram
    participant F as Frontend
    participant A as API

    F->>A: GET /sanctum/csrf-cookie
    F->>A: POST /login
    A-->>F: Sessão

    F->>A: GET /api/entregas
    A-->>F: Entregas

    F->>A: POST /api/saidas
    A-->>F: Carrada criada

    F->>A: GET /api/saidas/{id}/entregas-disponiveis
    A-->>F: Entregas disponíveis

    F->>A: GET /api/saidas/{id}/pendencias-disponiveis
    A-->>F: Reentregas disponíveis

    F->>A: POST /api/saidas/{id}/entregas
    A-->>F: Entrega adicionada

    F->>A: POST /api/saidas/{id}/pendencias/{pendencia}
    A-->>F: Reentrega programada

    F->>A: POST /api/saidas/{id}/iniciar
    A-->>F: Carrada EM_ANDAMENTO

    F->>A: POST /api/saidas/{id}/entregas/{entrega}/resultado
    A-->>F: Resultado normal

    F->>A: POST /api/saidas/{id}/pendencias/{pendencia}/resultado
    A-->>F: Resultado da reentrega

    F->>A: POST /api/saidas/{id}/finalizar
    A-->>F: Carrada FINALIZADA


50. Fluxo administrativo de usuários

sequenceDiagram
    participant A as ADMIN
    participant API as Backend

    A->>API: GET /api/usuarios
    API-->>A: usuários

    A->>API: POST /register
    API-->>A: operador criado

    A->>API: PUT /api/usuarios/{id}
    API-->>A: usuário atualizado

    A->>API: PUT /api/usuarios/{id}/senha
    API-->>A: senha redefinida

51. Códigos HTTP utilizados

Os principais códigos utilizados ou esperados no projeto são:

200 OK
201 Created
204 No Content
401 Unauthorized
403 Forbidden
404 Not Found
422 Unprocessable Entity

Uso conceitual:

200
→ consulta ou alteração com corpo de resposta

201
→ criação

204
→ operação concluída sem conteúdo

401
→ sem autenticação

403
→ autenticado sem autorização

404
→ recurso não encontrado

422
→ validação/regra de entrada não atendida

52. Regras para criação de novos endpoints

Ao adicionar novos endpoints:

utilizar /api para operações da API;

proteger rotas privadas com auth:sanctum;

aplicar bloqueio de usuário inativo;

validar autorização de perfil no backend;

utilizar Form Request quando houver payload relevante;

validar IDs relacionados;

manter regras críticas em Services;

utilizar Resource quando o retorno representar entidade;

utilizar JSON explícito para operações específicas quando apropriado;

utilizar códigos HTTP coerentes;

evitar duplicar cálculos críticos em Controller;

atualizar Types TypeScript;

atualizar Service HTTP do frontend;

adicionar testes quando houver regra importante;

documentar o endpoint neste arquivo.

53. Regra de manutenção

Quando uma rota for:

criada;

removida;

renomeada;

tiver payload alterado;

tiver resposta alterada;

receber nova regra de autorização;

este documento deve ser revisado.

A API é o contrato entre frontend e backend e não deve evoluir silenciosamente sem atualização da documentação.