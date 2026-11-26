# 01 — Ambiente de Produção Offline (Empacotado)

## 1. Objetivo

Este documento registra a arquitetura de implantação do **Sistema de Gerenciamento de Entregas** para operação 100% offline na rede local da loja.

O objetivo é documentar:

- como frontend, backend e banco são empacotados e integrados;
- responsabilidade de cada container e arquivo de infraestrutura;
- como a autenticação por sessão (Laravel Sanctum) funciona em rede local com IP dinâmico;
- onde os dados e documentos importados são persistidos fisicamente;
- como o ambiente já está preparado para integração futura com o banco do ERP da loja.

---

## 2. Arquitetura dos Containers

Em produção, o sistema não utiliza servidores de desenvolvimento separados (`:5173` e `:8001`). Tudo é unificado atrás de um único servidor web Nginx na porta **80**, orquestrado pelo arquivo `docker-compose.prod.yml` na raiz do projeto.

```text
Navegador (PC da loja / Wi-Fi)
       │
       ▼  http://<IP-DO-SERVIDOR> (porta 80)
┌──────────────────────────────────────────────────────────┐
│  Container: entregas_nginx_prod (nginx:1.27-alpine)      │
│                                                          │
│  ├── /                      ──► Arquivos estáticos Vue   │
│  └── /api, /login, /logout,     (pasta /dist compilada)  │
│      /register, /sanctum    ──► Proxy FastCGI (:9000)    │
└──────────────────────────────────┬───────────────────────┘
                                   │ rede interna: entregas_net
                                   ▼
┌──────────────────────────────────────────────────────────┐
│  Container: entregas_app_prod (PHP 8.3-FPM + Laravel 12) │
│                                                          │
│  ├── Regras de negócio, API e Sanctum                    │
│  └── Storage persistido em ./dados_producao/storage      │
└──────────────────────────────────┬───────────────────────┘
                                   │ rede interna: entregas_net
                                   ▼
┌──────────────────────────────────────────────────────────┐
│  Container: entregas_db_prod (postgres:17-alpine)        │
│                                                          │
│  └── Banco sistema_entregas (:5432 interno)              │
│      Dados persistidos em ./dados_producao/postgres      │
└──────────────────────────────────────────────────────────┘

3. Estrutura de Arquivos de Infraestrutura

Os arquivos responsáveis pelo empacotamento ficam isolados na raiz e na pasta infra/:
Plaintext

sistema-entregas/
├── .dockerignore                     # Impede cópia de vendor/, node_modules/ e caches locais no build
├── docker-compose.prod.yml           # Orquestração dos 3 serviços de produção
├── dados_producao/                   # Volumes físicos persistentes (ignorado no Git)
│   ├── postgres/                     # Dados reais do PostgreSQL 17
│   └── storage/                      # Arquivos e logs persistentes da aplicação
├── backend/
│   └── .env.production               # Variáveis exclusivas do ambiente empacotado
└── infra/
    ├── docker/
    │   ├── backend.Dockerfile        # Imagem PHP 8.3-FPM + extensões Postgres
    │   ├── frontend.Dockerfile       # Multi-stage build (Node 22 compila Vue -> Nginx serve dist/)
    │   └── entrypoint.sh             # Script de inicialização do container backend
    └── nginx/
        └── default.conf              # Roteamento SPA + proxy reverso para o Laravel

4. Detalhamento dos Componentes
4.1 Nginx (infra/nginx/default.conf e frontend.Dockerfile)

    Build em dois estágios: O frontend.Dockerfile utiliza node:22-alpine para rodar npm run build com VITE_API_URL= vazio, descartando o Node ao final e copiando apenas a pasta dist/ para uma imagem enxuta nginx:1.27-alpine.

    Roteamento SPA: Qualquer rota de tela (/entregas, /saidas, /dashboard) cai em try_files $uri $uri/ /index.html para que o Vue Router assuma a navegação.

    Proxy FastCGI: Requisições que começam com /api, /sanctum, /login, /logout ou /register são encaminhadas diretamente para app:9000 (/var/www/html/public/index.php).

    - **Limite de upload:** Configurado com `client_max_body_size 10M` para requisições padrão da API.

### 4.2 Backend (`infra/docker/backend.Dockerfile`)

- Baseado em `php:8.3-fpm-bookworm` com extensões `pdo_pgsql`, `pgsql`, `gd`, `zip`, `mbstring`, `bcmath`, `xml` e `pcntl`.
- Instala dependências PHP com `composer install --no-dev --optimize-autoloader`, removendo pacotes exclusivos de desenvolvimento (como `laravel/boost` e `fakerphp/faker`).
- Ajusta limites do PHP (`upload_max_filesize = 10M`, `post_max_size = 12M`, `memory_limit = 256M`, `max_execution_time = 60`).
4.3 Inicialização Automática (infra/docker/entrypoint.sh)

Executado automaticamente toda vez que o container entregas_app_prod sobe:

    Ajusta permissões de escrita (www-data, modo 775) em storage/ e bootstrap/cache/.

    Aguarda em loop até que o PostgreSQL (db:5432) esteja pronto para aceitar conexões PDO.

    Executa php artisan migrate --force para aplicar automaticamente qualquer migration pendente.

    Gera os caches otimizados (config:cache, route:cache, view:cache) e inicia o php-fpm.

5. Autenticação e Rede Local (Sanctum + Axios)

Para que o sistema funcione em qualquer computador da loja (acessado via http://localhost ou pelo IP na rede Wi-Fi, como http://192.168.1.100) sem internet e sem certificado HTTPS:

    Axios (frontend/src/services/api.ts): Utiliza baseURL: import.meta.env.VITE_API_URL || ''. Em produção, VITE_API_URL é vazio, fazendo todas as requisições usarem caminhos relativos no mesmo host/porta em que a página foi aberta.

    Laravel Sanctum (backend/config/sanctum.php): Mantém Sanctum::currentRequestHost() ativo e SANCTUM_STATEFUL_DOMAINS comentado no .env.production. Assim, o Sanctum reconhece dinamicamente qualquer IP local atribuído pelo roteador da loja.

    Cookies de sessão (backend/.env.production):

        SESSION_DOMAIN=null (não trava o cookie em localhost ou 127.0.0.1);

        SESSION_SECURE_COOKIE=false (permite gravação do cookie de sessão via HTTP na intranet);

        SESSION_LIFETIME=480 (sessão válida por 8 horas para cobrir o turno operacional).

6. Persistência, Tolerância a Falhas e Integração Futura

    Persistência física:** Os dados do banco e o storage da aplicação são gravados fora dos containers, na pasta `./dados_producao/`, garantindo que atualizações de imagem Docker não apaguem o histórico da loja.

    Recuperação automática: Os três serviços possuem restart: always, subindo automaticamente após reinicialização do computador ou queda de energia.

    Preparação para ERP externo: O container app possui extra_hosts: ["host.docker.internal:host-gateway"] configurado no docker-compose.prod.yml, permitindo que, na etapa futura de integração, o Laravel acesse diretamente o banco de dados do sistema comercial instalado na máquina hospedeira da loja.