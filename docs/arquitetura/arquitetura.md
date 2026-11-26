Arquitetura do Sistema

1. Objetivo

Este documento descreve a arquitetura técnica atual do Sistema de Gerenciamento de Entregas.

O objetivo é registrar:

tecnologias utilizadas;

relação entre frontend, backend e banco;

responsabilidades de cada camada;

organização dos módulos;

localização das regras de negócio;

autenticação e autorização;

processamento de documentos e OCR;

padrões de histórico e rastreabilidade;

convenções que devem ser preservadas durante a evolução do projeto.

A arquitetura deve continuar priorizando:

separação de responsabilidades;

integridade dos dados;

segurança;

rastreabilidade;

manutenibilidade;

clareza do domínio.

2. Stack atual

2.1 Backend

PHP 8.2+

Laravel 12

Laravel Sanctum

Eloquent ORM

Form Requests

API Resources

Services

Enums PHP

PostgreSQL

2.2 Frontend

Vue 3

TypeScript

Vite

Pinia

Vue Router

Axios

Vitest

2.3 Infraestrutura

Docker e Docker Compose

PostgreSQL 17

Nginx (proxy reverso e servidor de arquivos estáticos em produção)

ambiente local Linux / Windows

em desenvolvimento: frontend e backend executados separadamente

em produção offline: containers fechados empacotados com servidor web único

2.4 Ferramentas auxiliares de importação

O módulo de importação utiliza processamento local para leitura de documentos.

Componentes utilizados no fluxo atual incluem:

Tesseract OCR;

Poppler;

ImageMagick.

Esses componentes auxiliam na transformação de:

imagem / PDF
    ↓
texto
    ↓
dados estruturados
    ↓
prévia editável

3. Ambientes de execução

3.1 Ambiente de desenvolvimento

Configuração utilizada durante o desenvolvimento local:

Frontend:   http://127.0.0.1:5173
Backend:    http://127.0.0.1:8001
PostgreSQL: 127.0.0.1:5433

O PostgreSQL executa em container Docker na porta externa 5433.

3.2 Ambiente de produção offline (empacotado)

Configuração utilizada na máquina servidora da loja (100% sem internet):

Acesso local:      http://localhost (porta 80)
Acesso rede local: http://<IP-DA-MAQUINA-NA-LOJA> (porta 80)

Arquitetura em containers (docker-compose.prod.yml):

nginx (porta 80)
 ├── /                                       → arquivos estáticos compilados do Vue (dist/)
 └── /api, /login, /logout, /register, /sanctum → proxy FastCGI interno para app:9000

app (PHP 8.3-FPM + Laravel + Tesseract/Poppler/ImageMagick)
 └── conecta internamente em db:5432

db (PostgreSQL 17)
 └── dados persistidos em volume local (dados_producao/postgres)

Detalhes de geração de pacote, instalação e backup estão documentados em docs/deploy/.
4. Visão geral da arquitetura

flowchart LR
    U[Usuário] --> FE[Vue 3 + TypeScript]

    FE --> RT[Vue Router]
    FE --> ST[Pinia]
    FE --> SV[Services HTTP]

    SV --> AX[Axios]
    AX --> SAN[Laravel Sanctum]
    SAN --> API[Laravel]

    API --> MW[Middleware]
    MW --> C[Controllers]

    C --> FR[Form Requests]
    C --> BS[Services de negócio]
    C --> RS[API Resources / JSON]

    BS --> M[Eloquent Models]
    BS --> EV[Eventos / Históricos]
    M --> DB[(PostgreSQL)]
    EV --> DB

    API --> OCR[Serviços de importação / OCR]
    OCR --> DB

Fluxo conceitual principal:

Interface
   ↓
Router / Store
   ↓
Service HTTP
   ↓
Axios
   ↓
Laravel
   ↓
Middleware / autenticação
   ↓
Controller
   ↓
Form Request
   ↓
Service de negócio
   ↓
Models / banco
   ↓
Resource / JSON
   ↓
Frontend

5. Princípio de separação de responsabilidades

Cada camada deve possuir uma responsabilidade principal.

Frontend
→ experiência e apresentação

Service HTTP
→ comunicação com API

Controller
→ orquestração HTTP

Form Request
→ validação de entrada

Service de negócio
→ regras e coordenação do domínio

Model
→ entidade e persistência

Resource
→ contrato de saída

Banco
→ estado persistido oficial

Uma regra crítica não deve existir somente no frontend.

6. Arquitetura do frontend

6.1 Responsabilidades

O frontend é responsável por:

apresentar informações;

coletar dados do usuário;

fornecer feedback visual;

executar validações de experiência;

navegar entre telas;

manter estado temporário;

chamar serviços HTTP;

refletir os resultados retornados pelo backend.

O frontend não é a fonte oficial das regras críticas.

Exemplo:

entregue <= carregado

A interface pode impedir visualmente uma quantidade inválida, mas o backend deve validar novamente.

7. Organização atual do frontend

A organização evoluiu para uma estrutura por domínio.

Referência atual:

frontend/
└── src/
    ├── components/
    │   ├── Clientes/
    │   ├── Dashboard/
    │   ├── Entregas/
    │   ├── Pendencias/
    │   ├── Regioes/
    │   ├── Saidas/
    │   └── Usuarios/
    │
    ├── pages/
    │   ├── Clientes/
    │   ├── Dashboard/
    │   ├── Entregas/
    │   ├── Pendencias/
    │   ├── Regioes/
    │   ├── Saidas/
    │   └── Usuarios/
    │
    ├── services/
    ├── stores/
    ├── router/
    ├── styles/
    │   └── pages/
    ├── types/
    └── main.ts

A estrutura pode crescer, mas o princípio deve ser preservado:

página
≠ componente reutilizável
≠ service HTTP
≠ type
≠ store
≠ CSS

8. Páginas e componentes

8.1 Regra de composição

Páginas devem funcionar principalmente como orquestradoras.

Elas podem:

carregar dados;

controlar estado da tela;

chamar services;

abrir ou fechar componentes;

navegar entre rotas.

Componentes devem encapsular partes menores da interface.

8.2 Refatoração aplicada

Telas que cresceram demais foram divididas.

Exemplo do módulo de saídas:

src/pages/Saidas/
├── SaidasView.vue
├── SaidaNovaView.vue
├── SaidaDetalheView.vue
└── SaidaHistoricoView.vue

Componentes relacionados:

src/components/Saidas/
├── CarradaDetalhesPainel.vue
├── HistoricoCarradaPainel.vue
├── RetornoEntregaModal.vue
├── RetornoPendenciaModal.vue
└── SelecionarItensModal.vue

8.3 Entregas

Estrutura adotada:

src/pages/Entregas/
├── EntregasView.vue
├── EntregaFormularioView.vue
└── EntregaHistoricoView.vue

Componentes:

src/components/Entregas/
├── EntregaHistoricoPainel.vue
└── ImportarEntregaModal.vue

8.4 Clientes

Estrutura atual:

src/pages/Clientes/
├── ClientesView.vue
└── ClienteHistoricoView.vue

Componentes:

src/components/Clientes/
├── ClienteFormularioModal.vue
└── ClienteHistoricoPainel.vue

8.5 Regiões

Estrutura atual:

src/pages/Regioes/
└── RegioesView.vue

src/components/Regioes/
└── RegiaoFormularioModal.vue

8.6 Dashboard

A Dashboard também foi dividida em componentes menores.

Referência:

src/pages/Dashboard/
└── DashboardView.vue

Componentes:

src/components/Dashboard/
├── DashboardMetricas.vue
├── DashboardRegioesPainel.vue
├── DashboardCarradasPainel.vue
├── DashboardPendenciasPainel.vue
└── DashboardEntregasPainel.vue

9. Estilos do frontend

Os estilos específicos foram separados por domínio.

Exemplos:

src/styles/pages/saida/
src/styles/pages/entrega/
src/styles/pages/cliente/
src/styles/pages/regiao/
src/styles/pages/dashboard/

Também existe um design system global:

src/styles/design-system.css

Ele concentra princípios visuais compartilhados.

9.1 Identidade visual

O sistema utiliza atualmente:

azul-marinho na navegação;

azul para ações principais;

laranja como destaque operacional;

branco e cinza claro em superfícies;

verde para sucesso;

vermelho para perigo;

sombras sutis e cartões claros.

Valores aproximados utilizados:

navy:       #0B1F44
azul:       #155EEF / #2563EB
laranja:    #F97316
azul claro: #EFF6FF
laranja claro: #FFF7ED
fundo:      #F5F7FB

A intenção é manter consistência entre módulos.

10. Services HTTP do frontend

Arquivos de services centralizam comunicação com a API.

Exemplos:

src/services/api.ts
src/services/entregaService.ts
src/services/saidaService.ts
src/services/clienteService.ts
src/services/regiaoService.ts

Responsabilidades:

definir endpoint;

definir método HTTP;

enviar parâmetros;

tipar requests;

tipar responses;

retornar ao componente somente os dados necessários.

Exemplo conceitual:

entregaService.listar(...)
saidaService.registrarResultadoEntrega(...)

A página Vue não deve conhecer detalhes repetitivos do Axios para cada operação.

11. Axios e API base

A instância central de Axios utiliza:

withCredentials: true
withXSRFToken: true

Isso permite trabalhar com autenticação por sessão e proteção CSRF do Laravel Sanctum.

Arquitetura:

Vue
 ↓
api.ts
 ↓
Axios
 ↓
Cookie de sessão / XSRF
 ↓
Laravel

Configuração da baseURL (src/services/api.ts):

baseURL: import.meta.env.VITE_API_URL || ''

Em desenvolvimento, VITE_API_URL aponta para http://127.0.0.1:8001.

Em produção offline, VITE_API_URL fica vazio e as chamadas utilizam caminhos relativos no mesmo host/porta servidos pelo Nginx.

12. Types TypeScript

Os arquivos em src/types representam os contratos consumidos pela interface.

Exemplos:

src/types/Entrega.ts
src/types/Saida.ts
src/types/Cliente.ts
src/types/Regiao.ts

Os tipos devem refletir o contrato real da API.

Regra:

sempre que a resposta HTTP mudar de forma relevante, verificar os types correspondentes.

13. Stores

Pinia é utilizado para estado global quando necessário.

O exemplo mais importante atualmente é autenticação.

src/stores/auth.ts

Responsabilidades atuais:

usuário autenticado;

login;

logout;

carregamento do usuário atual;

criação administrativa de usuário;

estado de carregamento relacionado à autenticação.

Estados de tela muito locais não precisam ser colocados em Pinia.

14. Router

Vue Router controla:

rotas públicas;

rotas autenticadas;

páginas por módulo;

proteção por perfil.

Exemplo conceitual:

/login
/
/entregas
/entregas/nova
/entregas/:id/editar
/entregas/:id/historico
/saidas
/saidas/nova
/saidas/:id
/saidas/:id/historico
/clientes
/clientes/:id/historico
/usuarios
/usuarios/novo

Rotas administrativas podem utilizar metadados como:

requiresAdmin

A proteção visual do router não substitui autorização no backend.

15. Arquitetura do backend

Estrutura de referência:

backend/
├── app/
│   ├── Enums/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   └── Services/
│
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
│
├── routes/
│   ├── api.php
│   └── web.php
│
└── tests/
    └── Feature/

16. Rotas

16.1 API operacional

Rotas da aplicação ficam principalmente em:

backend/routes/api.php

A maior parte das operações do sistema é protegida por:

Route::middleware('auth:sanctum')

16.2 Rotas de autenticação

O fluxo de sessão utiliza rotas web específicas como:

POST /login
POST /register
POST /logout

/register não é cadastro público.

Ele exige usuário autenticado e autorizado como administrador.

17. CORS

Como frontend e backend executam em portas diferentes no desenvolvimento, CORS precisa aceitar explicitamente a origem do frontend.

Configuração atual inclui:

http://127.0.0.1:5173
http://localhost:5173

Rotas relevantes de sessão também precisam participar da configuração CORS, incluindo:

sanctum/csrf-cookie
login
logout
register

Com credenciais:

supports_credentials = true

Em produção offline, como frontend e backend respondem no mesmo host e porta através do Nginx, as requisições operam em mesma origem (same-origin).

O arquivo config/cors.php aceita configuração via variável de ambiente CORS_ALLOWED_ORIGINS, e config/sanctum.php utiliza Sanctum::currentRequestHost() para reconhecer dinamicamente o IP local da máquina servidora na rede da loja.

18. Laravel Sanctum

A autenticação é baseada em sessão.

Fluxo:

sequenceDiagram
    participant U as Usuário
    participant V as Vue
    participant A as Axios
    participant S as Sanctum
    participant L as Laravel

    U->>V: informa e-mail e senha
    V->>A: GET /sanctum/csrf-cookie
    A->>S: solicita proteção CSRF
    S-->>A: cookie XSRF
    V->>A: POST /login
    A->>L: credenciais + cookies
    L-->>A: sessão autenticada
    V->>A: GET /api/user
    A->>L: sessão
    L-->>V: usuário autenticado

19. Autorização

Os perfis atuais são:

ADMIN
OPERADOR

O backend é a autoridade final.

Exemplo:

Frontend esconde botão
+
Router bloqueia navegação
+
Backend valida perfil

A última camada é obrigatória.

20. Controllers

Controllers lidam com a camada HTTP.

Exemplos:

AuthController
EntregaController
SaidaController
ClienteController
RegiaoController
VeiculoController
PendenciaController
DocumentoImportacaoController
EntregaHistoricoController
SaidaHistoricoController

Responsabilidades:

receber request;

receber parâmetros da rota;

obter usuário autenticado;

utilizar dados validados;

chamar Services;

carregar relações necessárias;

retornar Resource ou JSON.

Controllers não devem concentrar lógica complexa de domínio.

21. Form Requests

Form Requests centralizam validações de entrada.

Exemplos do domínio:

StoreEntregaRequest
UpdateEntregaRequest
StoreSaidaRequest
AdicionarEntregaSaidaRequest
RegistrarResultadoEntregaRequest
RegistrarResultadoPendenciaRequest
ImportarDocumentoPreviewRequest

O objetivo é permitir que Services recebam dados previamente validados.

22. API Resources

Resources padronizam respostas HTTP.

Exemplos:

EntregaResource
SaidaResource

Benefícios:

controle de campos expostos;

normalização de enums;

organização de relacionamentos;

contrato mais estável;

redução de dependência direta do frontend sobre Models.

Fluxo:

Model
 ↓
Resource
 ↓
JSON
 ↓
TypeScript

23. Services do backend

A camada de Services concentra coordenação de regras de negócio.

Estrutura relevante inclui:

backend/app/Services/
├── SaidaService.php
├── EntregaService.php
├── EntregaStatusService.php
├── PendenciaService.php
├── SaidaHistoricoService.php
└── DocumentoImportacaoService.php

Outros services podem ser adicionados conforme novos módulos exigirem isolamento de responsabilidade.

24. SaidaService

Responsável principalmente por planejamento e ciclo operacional da carrada.

Responsabilidades incluem:

criar carrada;

adicionar entrega;

remover entrega;

programar itens;

adicionar pendência;

remover pendência;

iniciar carrada;

finalizar carrada;

cancelar carrada;

controlar disponibilidade;

controlar reservas;

sincronizar estados;

registrar eventos.

Princípio:

SaidaService
→ planejamento + estado da carrada

25. EntregaService

Responsável principalmente pelo resultado de uma entrega normal.

Operação central:

registrarResultado()

Fluxo:

flowchart TD
    A[Retorno recebido] --> B[Validar carrada]
    B --> C[Validar entrega vinculada]
    C --> D[Validar itens programados]
    D --> E[Validar quantidades]
    E --> F[Criar tentativa]
    F --> G[Criar itens da tentativa]
    G --> H{Diferença não entregue?}
    H -->|REENTREGA| I[Sincronizar pendência]
    H -->|DEVOLVIDO| J[Registrar devolução]
    I --> K[Sincronizar status]
    J --> K
    K --> L[Registrar eventos]

26. PendenciaService

Responsável pelas obrigações de reentrega.

Responsabilidades:

criar/sincronizar pendência;

atualizar quantidade resolvida;

registrar movimentações;

adicionar pendência em carrada;

remover pendência;

registrar retorno de reentrega;

registrar devolução originada da reentrega;

decidir entre ABERTA, PROGRAMADA e RESOLVIDA.

Regra central:

pendência != saldo normal

27. EntregaStatusService

Centraliza cálculo do estado logístico da entrega.

Deve considerar:

quantidade solicitada;

quantidade entregue;

quantidade devolvida;

obrigações restantes.

Conceito principal:

quantidade tratada
=
quantidade entregue
+
quantidade devolvida

Conclusão não pode depender somente da quantidade entregue.

28. SaidaHistoricoService

Monta a representação histórica de uma carrada.

Combina dados de:

saída;

rota;

entregas normais;

tentativas;

itens;

pendências;

reentregas;

devoluções;

eventos.

Carrada com somente reentrega continua sendo uma operação real e deve aparecer corretamente.

29. DocumentoImportacaoService

Responsável pela etapa técnica de importação de documentos.

Fluxo conceitual:

arquivo
 ↓
armazenamento
 ↓
extração de texto
 ↓
interpretação
 ↓
prévia
 ↓
confirmação pelo usuário
 ↓
entrega

Responsabilidades podem incluir:

receber imagem ou PDF;

armazenar arquivo;

calcular hash;

extrair texto;

processar várias páginas;

gerar dados preliminares;

identificar possíveis duplicidades/sobreposições;

preparar dados para conferência.

O Service de importação não deve considerar o OCR infalível.

30. Pipeline OCR

Visão técnica:

flowchart TD
    A[Upload] --> B{Tipo}
    B -->|Imagem| C[Normalização]
    B -->|PDF| D[Conversão de páginas]

    D --> C
    C --> E[Tesseract OCR]
    E --> F[Texto extraído]
    F --> G[Parser]
    G --> H[Dados estruturados]
    H --> I[Prévia editável]
    I --> J[Confirmação]
    J --> K[Persistência]

Para múltiplas imagens:

imagem 1
imagem 2
imagem 3
...

a ordem informada deve ser preservada.

31. Models

Models Eloquent representam entidades persistidas.

Principais entidades atuais incluem:

User
Cliente
Regiao
Veiculo
DocumentoImportado

Entrega
EntregaItem
EntregaEvento

Saida
SaidaEntrega
SaidaEntregaItem
SaidaEquipe
SaidaEvento

TentativaEntrega
TentativaItem

Pendencia
PendenciaMovimentacao

Devolucao
SaidaPendencia
SaidaPendenciaRetorno

OcorrenciaOperacional

Nem todo Model precisa possuir Service próprio.

A necessidade depende da complexidade da regra.

32. Responsabilidade dos Models

Models devem concentrar principalmente:

configuração da tabela;

fillable;

casts;

relacionamentos;

scopes simples;

comportamentos diretamente ligados à entidade.

Models não devem virar Services escondidos.

Quando uma operação coordena várias entidades, deve preferencialmente ficar em Service.

33. PostgreSQL

PostgreSQL é a fonte oficial do estado persistido.

O banco armazena:

cadastros;

dados operacionais;

histórico;

snapshots;

movimentações;

eventos;

documentos importados;

autenticação/sessões quando configurado.

34. Recursos utilizados no banco

O projeto utiliza conceitos como:

foreign keys;

numeric com precisão adequada;

enums/checks;

JSONB;

timestamps;

índices;

constraints;

relacionamentos N;

tabelas de histórico.

35. JSONB

Um uso importante é a rota da carrada.

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

Nesse caso JSONB funciona como snapshot ordenado.

Vantagens:

preserva ordem;

preserva nome histórico;

aceita parada livre;

evita alteração retroativa quando um cadastro de região muda.

36. Transações

Operações que alteram múltiplas entidades devem utilizar transação.

Exemplos:

criar entrega e itens;

registrar retorno;

criar tentativa e itens;

gerar pendência;

registrar devolução;

adicionar reentrega;

remover reentrega;

finalizar operação de pendência;

sincronizar estados relacionados.

Exemplo:

DB::transaction(function () {
    // alterações relacionadas
});

Objetivo:

tudo acontece
ou
nada acontece

37. Histórico e rastreabilidade

O sistema utiliza diferentes mecanismos complementares.

37.1 Estado persistido

Exemplos:

tentativas;

itens de tentativa;

pendências;

devoluções;

retornos de reentrega.

Essas entidades possuem significado próprio para cálculo.

37.2 Eventos

Exemplos:

ENTREGA_CRIADA
ENTREGA_ATUALIZADA
ENTREGA_CANCELADA

SAIDA_CRIADA
ENTREGA_ADICIONADA
PENDENCIA_ADICIONADA
SAIDA_INICIADA
RESULTADO_ENTREGA_REGISTRADO
SAIDA_FINALIZADA

Eventos ajudam a explicar a sequência da operação.

37.3 Regra arquitetural

Evento não substitui uma entidade operacional necessária.

Exemplo:

devolução

precisa existir como registro persistido próprio.

Não é suficiente guardar apenas:

{
  "evento": "produto devolvido"
}

38. Snapshots

Snapshots são utilizados quando alterações futuras em cadastros não podem modificar a interpretação do passado.

Exemplos:

nome do cliente na entrega;

telefone do cliente na entrega;

endereço;

referência;

rota da carrada.

Princípio:

cadastro atual
!=
fato histórico

39. Fluxo técnico de uma entrega normal

sequenceDiagram
    participant U as Usuário
    participant V as Vue
    participant FS as saidaService.ts
    participant C as SaidaController
    participant ES as EntregaService
    participant PS as PendenciaService
    participant SS as EntregaStatusService
    participant DB as PostgreSQL

    U->>V: Registra retorno
    V->>FS: registrarResultadoEntrega()
    FS->>C: POST endpoint de resultado
    C->>ES: registrarResultado()

    ES->>DB: cria tentativa
    ES->>DB: cria itens da tentativa

    alt diferença para REENTREGA
        ES->>PS: sincronizar pendência
        PS->>DB: cria/atualiza pendência
    else diferença DEVOLVIDA
        ES->>DB: registra devolução
    end

    ES->>SS: sincronizar status
    SS->>DB: atualiza entrega

    ES->>DB: registra eventos
    ES-->>C: resultado
    C-->>FS: JSON
    FS-->>V: dados atualizados
    V-->>U: interface atualizada

40. Fluxo técnico de uma reentrega

sequenceDiagram
    participant U as Usuário
    participant V as Vue
    participant FS as saidaService.ts
    participant C as SaidaController
    participant PS as PendenciaService
    participant SS as EntregaStatusService
    participant DB as PostgreSQL

    U->>V: Registra retorno da reentrega
    V->>FS: registrarResultadoPendencia()
    FS->>C: POST resultado da pendência
    C->>PS: registrar retorno

    PS->>DB: registra retorno
    PS->>DB: atualiza pendência

    alt restante devolvido
        PS->>DB: registra devolução
    else restante continua
        PS->>DB: pendência retorna a ABERTA
    end

    PS->>SS: sincroniza entrega
    SS->>DB: atualiza status

    PS-->>C: resultado
    C-->>FS: JSON
    FS-->>V: dados atualizados

41. Fluxo técnico de importação

sequenceDiagram
    participant U as Usuário
    participant V as ImportarEntregaModal
    participant DS as Service HTTP
    participant C as DocumentoImportacaoController
    participant IS as DocumentoImportacaoService
    participant OCR as OCR local
    participant DB as PostgreSQL

    U->>V: seleciona foto(s) ou PDF
    V->>DS: envia arquivo(s)
    DS->>C: request de preview
    C->>IS: gerarPreview()
    IS->>DB: registra documento
    IS->>OCR: extrair texto
    OCR-->>IS: texto extraído
    IS->>IS: parser / estruturação
    IS-->>C: prévia
    C-->>DS: JSON
    DS-->>V: campos editáveis
    U->>V: confere/corrige
    V->>DS: confirma importação
    DS->>C: confirmação
    C->>DB: cria/reutiliza cliente e entrega

42. Dashboard

A Dashboard utiliza dados consolidados da operação.

Arquiteturalmente:

Banco
 ↓
consulta/serviço do Dashboard
 ↓
API
 ↓
DashboardView
 ↓
componentes específicos

A Dashboard não deve duplicar regras de saldo.

Ela deve consumir resultados calculados a partir das fontes oficiais.

43. Relatórios

O módulo de relatórios segue o mesmo princípio.

Filtro do usuário
 ↓
Service HTTP
 ↓
Controller / consulta
 ↓
PostgreSQL
 ↓
agregação
 ↓
JSON
 ↓
tela de relatório

Consultas de período devem utilizar o fuso operacional:

America/Fortaleza

A conversão de limites locais para consulta deve acontecer de forma consistente.

44. Tempo e timezone

Existe distinção entre:

horário de armazenamento
e
horário operacional

O sistema utiliza configuração:

APP_OPERATION_TIMEZONE=America/Fortaleza

Consultas como:

01/09/2026 00:00
até
01/09/2026 23:59:59

devem ser convertidas corretamente antes de consultar timestamps armazenados.

45. Gestão de usuários

A gestão de usuários é um módulo administrativo.

Arquitetura:

UsuariosView
 ↓
auth/service
 ↓
API protegida
 ↓
validação ADMIN
 ↓
User model
 ↓
PostgreSQL

Operações incluem:

listar usuários;

criar;

editar;

alterar perfil;

ativar;

desativar;

redefinir senha.

A segurança não pode depender da existência ou ausência de botões na interface.

46. Segurança das senhas

Senhas devem utilizar hashing fornecido pelo Laravel.

Nunca persistir senha em texto puro.

O Model pode utilizar cast de senha com hash e/ou Hash::make() conforme o fluxo implementado.

47. Usuário ativo

A propriedade:

ativo

é parte da segurança operacional.

O usuário inativo deve ser bloqueado pelo backend.

Isso evita que uma sessão ou chamada direta à API ignore a restrição visual.

48. Regras críticas no backend

As regras abaixo devem existir no backend mesmo quando também forem verificadas no frontend:

entregue <= carregado

programado <= saldo disponível

pendência não pode estar em duas carradas

carrada só inicia com conteúdo

carrada só finaliza com todos os retornos

DEVOLVIDO não gera reentrega

REENTREGA não volta para saldo normal

usuário inativo não opera

ação ADMIN exige autorização

dados OCR exigem confirmação

O frontend melhora a experiência.

O backend protege a integridade.

49. Data de lançamento das entregas

created_at da entrega representa sua data de entrada no sistema.

A API já expõe esse valor.

A listagem permite ordenar por:

mais antigas primeiro
mais recentes primeiro

A ordenação deve ser feita no backend antes da paginação.

Motivo:

ordenar somente os 20 itens carregados
!=
ordenar o conjunto real

50. Paginação

Listagens com muitos registros devem preferir paginação no backend.

Exemplos:

entregas;

carradas;

clientes;

usuários quando necessário.

O frontend deve utilizar os metadados retornados pela API, como:

current_page
last_page
per_page
total

51. Testes

Regras críticas devem possuir testes automatizados.

A suíte mais importante criada durante o desenvolvimento está em:

tests/Feature/SaidaTest.php

Cenários já trabalhados incluem:

criação de carrada;

adição de entrega;

programação parcial;

bloqueio acima do saldo;

início;

retorno;

pendência;

devolução;

reentrega;

reentrega parcial;

finalização;

cancelamento.

A arquitetura deve continuar favorecendo testabilidade.

52. Convenções arquiteturais

52.1 Regra de negócio

Preferir:

Service

Evitar duplicar lógica em:

Controller
Vue
Resource

52.2 Validação HTTP

Preferir:

FormRequest

quando a operação possuir payload relevante.

52.3 Saída HTTP

Preferir:

Resource

para entidades e listagens padronizadas.

Utilizar JSON explícito para endpoints operacionais muito específicos quando fizer mais sentido.

52.4 Tipagem

Quando contrato HTTP mudar:

backend
 ↓
Resource / JSON
 ↓
TypeScript
 ↓
componentes

Todos os pontos afetados devem ser revisados.

52.5 Histórico

Não recalcular fatos passados de forma destrutiva.

Preferir:

tabelas históricas;

snapshots;

eventos;

movimentações.

52.6 Linguagem de domínio

Utilizar termos próximos da operação real:

Carrada
Reentrega
Devolução
Pendência
Carregado
Entregue

Isso aproxima:

código;

documentação;

interface;

operação real.

52.7 Componentização

Não criar componentes apenas por criar.

Extrair quando houver ganho real em:

legibilidade;

reutilização;

testabilidade;

manutenção.

52.8 CSS

Evitar CSS gigante dentro de páginas quando o módulo já possui estilo próprio.

Preferir:

styles/pages/<dominio>/

53. Princípio de fonte única de verdade

O sistema deve manter uma fonte oficial para cada fato.

Exemplos:

Entrega realizada
→ tentativa_items

Obrigação de reentrega
→ pendencias

Retorno de reentrega
→ saida_pendencia_retornos

Quantidade encerrada por devolução
→ devolucoes

Histórico temporal da carrada
→ saida_eventos

Data de lançamento
→ entregas.created_at

Usuário autenticado
→ sessão / backend

Evitar representar o mesmo fato em lugares concorrentes com regras diferentes.

54. Dependência entre camadas

Dependências devem apontar preferencialmente para dentro do domínio, não o contrário.

Exemplo desejado:

Vue
 ↓
service HTTP
 ↓
Controller
 ↓
Service
 ↓
Model

Evitar:

Model
→ componente Vue

Service de negócio
→ detalhe visual

Controller
→ CSS/estado de interface

55. Evolução futura

A arquitetura já suporta crescimento para:

ocorrências operacionais mais completas;

planejamento avançado;

cálculo de custo;

otimização de rotas;

cálculo de capacidade;

mobile;

integração com ERP;

integrações externas;

relatórios gerenciais mais avançados.

OCR, Dashboard e relatórios básicos deixam de ser classificados como arquitetura futura porque já fazem parte da aplicação.

56. Fluxo padrão para novos módulos

Ao adicionar um novo módulo, seguir preferencialmente:

1. Entidade / regra
2. Migration
3. Model
4. Enum, se necessário
5. Form Request
6. Service de negócio
7. Controller
8. Resource / contrato JSON
9. Route
10. TypeScript types
11. Service HTTP
12. Página
13. Componentes
14. CSS
15. Testes
16. Documentação

Nem todo módulo precisará de todas as camadas.

A complexidade deve justificar a abstração.

57. Princípio arquitetural central

A arquitetura deve manter alinhamento entre:

operação real
↓
regra de negócio
↓
modelo de dados
↓
backend
↓
API
↓
frontend
↓
documentação
↓
testes

Quando qualquer uma dessas partes evoluir, as demais devem ser revisadas quando aplicável.

O objetivo não é apenas fazer a tela funcionar.

O objetivo é manter um sistema:

confiável;

compreensível;

rastreável;

seguro;

testável;

evolutivo.