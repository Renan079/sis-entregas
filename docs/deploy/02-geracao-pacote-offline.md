# 02 — Geração do Pacote Offline (Build e Exportação para Pendrive)

## 1. Objetivo

Este documento descreve o procedimento padrão executado na **máquina de desenvolvimento** para compilar, empacotar e exportar uma versão fechada do **Sistema de Gerenciamento de Entregas** pronta para transporte via pendrive e instalação 100% offline na loja.

O objetivo é garantir:

- que nenhuma dependência externa (Composer, NPM ou Docker Hub) seja baixada durante a instalação na loja;
- versionamento claro das imagens Docker (`1.0`, `1.1`, etc.);
- isolamento entre as credenciais de desenvolvimento e as credenciais definitivas de produção;
- padronização dos arquivos entregues no pacote de instalação.

---

## 2. Pré-requisitos na Máquina de Desenvolvimento

Antes de gerar o pacote, confirme que:

1. Todas as alterações de código (`backend/` e `frontend/`) estão salvas e testadas.
2. O Docker Engine / Docker Desktop está em execução com acesso à internet (necessário apenas nesta etapa de build).
3. O arquivo `backend/.env.production` existe e está revisado.

---

## 3. Checklist Pré-Build (Segurança e Configuração)

Antes de construir as imagens para uma instalação nova na loja, verifique o arquivo `backend/.env.production`:

- **`APP_ENV=production`** e **`APP_DEBUG=false`**.
- **`APP_KEY`:** Gere uma chave criptográfica exclusiva para a loja executando no diretório `backend/`:
  ```bash
  php artisan key:generate --show

Cole o valor gerado (base64:...) na variável APP_KEY do backend/.env.production.

    DB_PASSWORD: Certifique-se de que a senha definida em backend/.env.production seja idêntica à configurada em POSTGRES_PASSWORD no docker-compose.prod.yml.

    Frontend sem URL fixa: O infra/docker/frontend.Dockerfile já define ENV VITE_API_URL= durante o build, garantindo chamadas relativas na porta 80.

4. Construção das Imagens de Produção

Na raiz do projeto (sistema-entregas/), execute o build das imagens de produção sem usar cache antigo quando houver atualização de código:
Bash

docker compose -f docker-compose.prod.yml build --no-cache

Ao final do processo, confirme com docker images se as três imagens necessárias estão disponíveis localmente:

    sistema-entregas-nginx:1.0 (Frontend compilado + Nginx)

    sistema-entregas-app:1.0 (Laravel 12 + PHP 8.3-FPM + OCR)

    postgres:17-alpine (Banco de dados PostgreSQL 17)

(Nota: Caso o postgres:17-alpine ainda não tenha sido baixado localmente, execute docker pull postgres:17-alpine antes de exportar).
5. Exportação das Imagens para Arquivo Único (.tar)

Para transportar as imagens sem depender de internet na loja, empacote as três imagens em um único arquivo .tar utilizando docker save:
Bash

docker save -o sistema-entregas-v1.0.tar \
  sistema-entregas-nginx:1.0 \
  sistema-entregas-app:1.0 \
  postgres:17-alpine

Esse comando gera o arquivo sistema-entregas-v1.0.tar na raiz do projeto, contendo todo o sistema operacional dos containers, PHP, Tesseract, Node compilado e PostgreSQL.
6. Montagem da Pasta de Entrega (Pendrive)

Crie uma pasta limpa (por exemplo, Pacote_Instalacao_Entregas/) e organize exatamente os seguintes arquivos para copiar ao pendrive:
Plaintext

Pacote_Instalacao_Entregas/
├── sistema-entregas-v1.0.tar      # Imagens Docker exportadas
├── docker-compose.prod.yml        # Orquestração dos containers
├── backend/
│   └── .env.production            # Variáveis de ambiente da loja
├── scripts/
│   ├── instalar.sh                # Instalador automático (Linux)
│   ├── instalar.bat               # Instalador automático (Windows)
│   ├── backup.sh                  # Rotina de backup do banco (Linux)
│   └── backup.bat                 # Rotina de backup do banco (Windows)
└── LEIA-ME-INSTALACAO.txt         # Resumo rápido de comandos e acesso

6.1 Conteúdo dos Scripts de Instalação Rápida

Linux (scripts/instalar.sh):
Bash

#!/bin/sh
set -e

echo "1. Carregando imagens Docker offline..."
docker load -i ../sistema-entregas-v1.0.tar

echo "2. Subindo containers em segundo plano..."
docker compose -f ../docker-compose.prod.yml up -d

echo "3. Criando usuário administrador inicial (se não existir)..."
docker exec entregas_app_prod php artisan db:seed --class=AdminSeeder --force

echo "Instalação concluída! Acesse http://localhost"

Windows (scripts/instalar.bat):
DOS

@echo off
echo 1. Carregando imagens Docker offline...
docker load -i ..\sistema-entregas-v1.0.tar

echo 2. Subindo containers em segundo plano...
docker compose -f ..\docker-compose.prod.yml up -d

echo 3. Criando usuario administrador inicial...
docker exec entregas_app_prod php artisan db:seed --class=AdminSeeder --force

echo Instalacao concluida! Acesse http://localhost
pause

7. Fluxo de Atualização de Versão (Releases Futuras)

Quando novas funcionalidades ou correções forem desenvolvidas e precisarem ser aplicadas na loja sem perder os dados já cadastrados:

    Incremente a tag da imagem no docker-compose.prod.yml (ex: de :1.0 para :1.1).

    Execute docker compose -f docker-compose.prod.yml build --no-cache.

    Exporte apenas as imagens alteradas (sistema-entregas-nginx:1.1 e sistema-entregas-app:1.1) para um novo arquivo sistema-entregas-v1.1.tar.

    Na loja, carregue o novo .tar (docker load) e rode docker compose -f docker-compose.prod.yml up -d.

    O entrypoint.sh aplicará automaticamente as novas migrations sobre o banco existente em ./dados_producao/postgres sem apagar nenhum registro.