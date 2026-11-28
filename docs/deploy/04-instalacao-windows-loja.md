# Guia de Implantação e Operação — Sistema de Entregas (Windows / Loja)

Use este guia ao instalar e operar o sistema no computador da empresa. Diferente do modo de desenvolvimento no Ubuntu, aqui **não é necessário abrir vários terminais nem rodar `npm run dev` ou `php artisan serve`**: tudo roda em segundo plano pelo Docker.

---

## 1. Pré-requisitos no computador da loja

* **Docker Desktop** instalado e aberto (ícone da baleia parado/estável na bandeja do relógio).
* **Git** instalado (ou a pasta do projeto copiada para `C:\sistema-entregas`).
* Caminho exato do arquivo do banco da loja (`LOJA.FDB`) e porta do Firebird (padrão `3050`).

---

## 2. Primeira instalação na loja

Abra o **PowerShell** no Windows e siga os passos na ordem:

### Passo 1 — Baixar o projeto em `C:\sistema-entregas`
```powershell
cd C:\
git clone <URL_DO_SEU_REPOSITORIO> sistema-entregas
cd C:\sistema-entregas
```

### Passo 2 — Criar e ajustar o arquivo `backend\.env.production`
```powershell
copy backend\.env.production.example backend\.env.production
```
Abra o arquivo `backend\.env.production` no Bloco de Notas e ajuste **apenas** a seção do Firebird com os dados reais do computador da loja (use barra normal `/` no caminho do arquivo):
```env
FIREBIRD_HOST=host.docker.internal
FIREBIRD_PORT=3050
FIREBIRD_DATABASE=C:/Caminho/Real/Da/Loja/LOJA.FDB
FIREBIRD_USERNAME=SYSDBA
FIREBIRD_PASSWORD=masterkey
FIREBIRD_CHARSET=WIN1252
```

### Passo 3 — Subir o sistema (build + containers)
```powershell
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml ps
```
Verifique se os containers (`entregas_db_prod`, `entregas_app_prod`, `entregas_nginx_prod`) aparecem com status `Up`.

### Passo 4 — Criar as tabelas e o usuário administrador inicial
```powershell
docker exec entregas_app_prod php artisan migrate --force
docker exec entregas_app_prod php artisan db:seed --class=AdminSeeder --force
```

---

## 3. Como acessar no dia a dia (sem internet)

* **No computador servidor da loja:** abra o navegador e acesse **`http://localhost`**
* **Em outros computadores ou celulares na mesma rede Wi-Fi/cabo:** acesse **`http://IP-DO-SERVIDOR`** (descubra o IP digitando `ipconfig` no PowerShell).
* **Criar atalho com cara de aplicativo na Área de Trabalho (Chrome ou Edge):**
  1. Acesse `http://localhost`.
  2. Clique nos **três pontinhos (`⋮`)** → **Salvar e compartilhar** → **Criar atalho...** (ou **Apps → Instalar este site como um aplicativo**).
  3. Marque **Abrir como janela** e confirme.

### Quando desligar e ligar o computador no dia seguinte
Configure o **Docker Desktop** para iniciar automaticamente junto com o Windows (**Settings → General → Start Docker Desktop when you sign in to your computer**). Como os containers usam `restart: always`, o sistema volta ao ar sozinho sem precisar digitar comandos.

---

## 4. Backup automático diário (`backup.bat`)

1. Salve o arquivo `C:\sistema-entregas\infra\scripts\backup.bat` com o conteúdo abaixo:
```bat
@echo off
set DATA=%date:~6,4%-%date:~3,2%-%date:~0,2%_%time:~0,2%-%time:~3,2%
set DATA=%DATA: =0%

if not exist "C:\sistema-entregas\backups" mkdir "C:\sistema-entregas\backups"

docker compose -f "C:\sistema-entregas\docker-compose.prod.yml" exec -T db pg_dump -U entregas_user sistema_entregas > "C:\sistema-entregas\backups\backup_entregas_%DATA%.sql"
```
2. Agende no **Agendador de Tarefas** (`taskschd.msc`) para rodar diariamente no fim do expediente.

---

## 5. Atualizações futuras e resolução de problemas

### Atualizar o sistema quando houver novidades no Git
```powershell
cd C:\sistema-entregas
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker exec entregas_app_prod php artisan migrate --force
docker exec entregas_app_prod php artisan config:clear
```

### Se a página não abrir (`http://localhost` fora do ar)
```powershell
cd C:\sistema-entregas
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml up -d
```

### Como pausar ou encerrar (sem perder dados)
```powershell
cd C:\sistema-entregas
docker compose -f docker-compose.prod.yml stop
```
> **Atenção:** Nunca use `docker compose down -v`, pois a opção `-v` apaga os volumes e remove todos os dados cadastrados no PostgreSQL.