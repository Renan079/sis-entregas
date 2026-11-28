# Sistema de Entregas — Guia de Comandos e Operação

Guia rápido com os comandos mais usados para manter o sistema rodando, testar integração com o Firebird (`loja.fdb`), fazer backups e implantar no computador da loja.

---

## 1. Subir, Pausar e Conferir Status (Docker)

### No seu notebook (Linux)
```bash
cd ~/Área\ de\ trabalho/sistema-entregas

# Subir / atualizar containers
docker compose -f docker-compose.prod.yml up -d --build

# Ver se tudo está rodando
docker compose -f docker-compose.prod.yml ps
docker ps --format "table {{.Names}}\t{{.Status}}"

# Pausar tudo para economizar memória (sem apagar dados)
docker compose -f docker-compose.prod.yml stop
docker stop firebird25_temp

# Religar depois de pausar
docker start firebird25_temp
docker compose -f docker-compose.prod.yml start
```

### No servidor da loja (Windows — PowerShell)
```powershell
cd C:\sistema-entregas

# Subir / atualizar containers (com Docker Desktop aberto)
docker compose -f docker-compose.prod.yml up -d --build

# Conferir status dos serviços
docker compose -f docker-compose.prod.yml ps

# Pausar ou religar serviços
docker compose -f docker-compose.prod.yml stop
docker compose -f docker-compose.prod.yml start
```

> **Atenção:** Evite usar `docker compose down -v`, pois a flag `-v` remove os volumes e apaga os dados do banco PostgreSQL.

---

## 2. Comandos do Laravel (Migrations, Seeder e Cache)

Execute sempre dentro do container `entregas_app_prod`:

```bash
# Rodar migrações no banco PostgreSQL
docker exec entregas_app_prod php artisan migrate --force

# Criar usuário administrador inicial (na primeira instalação)
docker exec entregas_app_prod php artisan db:seed --class=AdminSeeder --force

# Limpar cache de configuração após alterar o .env.production
docker exec entregas_app_prod php artisan config:clear
docker exec entregas_app_prod php artisan cache:clear
```

---

## 3. Logs e Diagnóstico Rápido

```bash
# Ver logs em tempo real do backend (PHP/Laravel)
docker logs -f entregas_app_prod

# Ver logs do Nginx ou do PostgreSQL
docker logs --tail 50 entregas_nginx_prod
docker logs --tail 50 entregas_db_prod
```

---

## 4. Testar Conexão e Leitura do Firebird (`loja.fdb`)

O sistema apenas lê (`SELECT`) os dados do `loja.fdb` no `PedidoExternoService` e grava as entregas/clientes no PostgreSQL local.

```bash
docker exec -i entregas_app_prod php << 'EOF'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ref = new ReflectionClass(App\Services\PedidoExternoService::class);
$conectar = $ref->getMethod('conectar');
$conectar->setAccessible(true);
$pdo = $conectar->invoke(app(App\Services\PedidoExternoService::class));

$total = $pdo->query('SELECT COUNT(*) AS QTD FROM PEDIDO')->fetch(PDO::FETCH_ASSOC);
$ultimos = $pdo->query('SELECT FIRST 10 PEDIDO, DATA, CB FROM PEDIDO ORDER BY PEDIDO DESC')->fetchAll(PDO::FETCH_ASSOC);

echo "Total de pedidos na tabela PEDIDO: " . ($total['QTD'] ?? 0) . "\n";
print_r($ultimos);
EOF
```

---

## 5. Backup do Banco PostgreSQL

### Manual (Linux ou Windows)
```bash
# Gerar dump SQL do banco sistema_entregas
docker compose -f docker-compose.prod.yml exec -T db pg_dump -U entregas_user sistema_entregas > backup_entregas.sql
```

### Automático no Windows (`backup.bat`)
Salve o script em `C:\sistema-entregas\infra\scripts\backup.bat` e agende no **Agendador de Tarefas** (`taskschd.msc`):

```bat
@echo off
set DATA=%date:~6,4%-%date:~3,2%-%date:~0,2%_%time:~0,2%-%time:~3,2%
set DATA=%DATA: =0%

if not exist "C:\sistema-entregas\backups" mkdir "C:\sistema-entregas\backups"

docker compose -f "C:\sistema-entregas\docker-compose.prod.yml" exec -T db pg_dump -U entregas_user sistema_entregas > "C:\sistema-entregas\backups\backup_entregas_%DATA%.sql"
```

---

## 6. Atualização de Código via Git

```bash
# Baixar novidades e reconstruir apenas o que mudou
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker exec entregas_app_prod php artisan migrate --force
docker exec entregas_app_prod php artisan config:clear
```

---

## 7. Acesso no Dia a Dia (Sem Internet)

* **No próprio computador do sistema:** acesse `http://localhost` no navegador.
* **Em outros PCs ou celulares da rede local:** acesse `http://IP-DO-COMPUTADOR-SERVIDOR`.
* **Atalho na Área de Trabalho:**
  * **No Windows (Chrome/Edge):** menu `⋮` → **Salvar e compartilhar → Criar atalho...** (ou **Apps → Instalar este site como um aplicativo**).
  * **No Linux:** lançador `.desktop` apontando para `xdg-open http://localhost` com permissão `chmod +x`.