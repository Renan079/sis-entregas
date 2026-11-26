# 04 — Guia de Instalação e Operação no Windows da Loja (via Git)

## 1. Objetivo

Este documento reúne o passo a passo completo para implantar, configurar na rede local e manter o **Sistema de Gerenciamento de Entregas** no computador servidor da loja utilizando **Windows** e acesso à internet via **Git**.

---

## 2. Preparação na Máquina de Desenvolvimento (Linux)

Execute esta etapa no seu computador Linux antes de ir para a loja:

### 2.1 Proteger quebras de linha (`LF`) para o Docker no Windows

Crie um arquivo `.gitattributes` na raiz do projeto (`sistema-entregas/.gitattributes`) para impedir que o Git no Windows altere o formato do `entrypoint.sh`:

```gitattributes
*.sh text eol=lf

2.2 Garantir o AdminSeeder.php

Confirme que o arquivo backend/database/seeders/AdminSeeder.php está criado sem uso do Faker:
PHP

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@loja.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin@2026'),
                'perfil' => 'ADMIN',
                'ativo' => true,
            ]
        );
    }
}

2.3 Conferir .gitignore e enviar para o Git

No .gitignore da raiz, garanta que os dados locais e senhas não sejam enviados, mas que a pasta infra/ e o docker-compose.prod.yml subam normalmente:
Code snippet

/dados_producao
backend/.env.production

Envie tudo para o repositório remoto:
Bash

git add .
git commit -m "chore: prepara infraestrutura docker, gitattributes e AdminSeeder para producao"
git push

3. Pré-requisitos no Computador da Loja (Windows)

Antes de baixar o sistema no computador servidor da loja, instale e verifique:

    Git for Windows: Instalado (habilita o terminal Git Bash e comandos git no PowerShell).

    Docker Desktop for Windows: Instalado, aberto e com o motor Linux/WSL2 ativo (ícone da baleia verde/estável na bandeja do sistema).

    Porta 80 livre: Certifique-se de que nenhum outro programa (IIS, XAMPP, WAMP, Skype antigo) esteja usando a porta 80.

4. Instalação do Sistema no Windows

Abra o PowerShell (ou Git Bash) no computador da loja e execute os passos abaixo:
4.1 Clonar o repositório em pasta fixa

Escolha um local permanente no disco (ex: C:\sistema-entregas):
PowerShell

cd C:\
git clone <URL-DO-SEU-REPOSITORIO> sistema-entregas
cd C:\sistema-entregas

4.2 Criar o arquivo backend/.env.production

Como o .env.production não fica no Git por segurança, crie-o dentro de C:\sistema-entregas\backend\.env.production (pode usar o Bloco de Notas ou VS Code) com o conteúdo abaixo:
Code snippet

APP_NAME="Sistema de Entregas"
APP_ENV=production
APP_KEY=base64:Mb19MbPJAv37Hqhyu/qBQ1Uarxzk5bfl0u8KJyIgEuU=
APP_DEBUG=false
APP_URL=http://localhost

CORS_ALLOWED_ORIGINS=*

APP_OPERATION_TIMEZONE=America/Fortaleza
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=single
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=sistema_entregas
DB_USERNAME=entregas_user
DB_PASSWORD=entregas_prod_2026

SESSION_DRIVER=database
SESSION_LIFETIME=480
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="sistema@loja.local"
MAIL_FROM_NAME="${APP_NAME}"

    Atenção: A senha em DB_PASSWORD deve ser exatamente igual à senha definida em POSTGRES_PASSWORD no arquivo docker-compose.prod.yml.

4.3 Construir e iniciar os containers

Ainda dentro de C:\sistema-entregas, execute:
PowerShell

docker compose -f docker-compose.prod.yml up -d --build

Aguarde a finalização do download e compilação. Depois, confirme se os 3 containers estão ativos (Up):
PowerShell

docker compose -f docker-compose.prod.yml ps

4.4 Criar o usuário administrador inicial

Execute o seeder de produção:
PowerShell

docker exec -it entregas_app_prod php artisan db:seed --class=AdminSeeder --force

4.5 Validar o acesso local

Abra o navegador no computador da loja e acesse http://localhost.

    Entre com admin@loja.local e senha Admin@2026.

    Acesse imediatamente o menu Usuários, altere a senha padrão e cadastre os operadores da loja.

5. Configuração da Rede Local no Windows (Acesso nos Demais PCs e Celulares)

Para que outros computadores e celulares da loja acessem o sistema pelo navegador:
5.1 Liberar a porta 80 no Firewall do Windows

Abra o PowerShell como Administrador e execute:
PowerShell

New-NetFirewallRule -DisplayName "Sistema de Entregas (HTTP 80)" -Direction Inbound -LocalPort 80 -Protocol TCP -Action Allow

5.2 Descobrir ou fixar o IP do servidor na rede

    No PowerShell, rode ipconfig e anote o Endereço IPv4 da rede local (exemplo: 192.168.1.100).

    Recomenda-se fixar esse IP nas configurações do adaptador de rede do Windows (ou reservar o IP pelo MAC Address no roteador da loja) para que ele não mude ao reiniciar o PC.

    Em qualquer computador ou celular conectado ao Wi-Fi/rede da loja, basta acessar:
    Plaintext

    [http://192.168.1.100](http://192.168.1.100)

5.3 Inicialização automática com o Windows

Nas configurações do Docker Desktop (Settings > General), marque a opção "Start Docker Desktop when you sign in to your computer". Como os containers possuem restart: always, o sistema voltará sozinho sempre que o computador for ligado.
6. Backup Diário e Atualizações no Windows
6.1 Script de backup (infra/scripts/backup.bat)

Crie o arquivo C:\sistema-entregas\infra\scripts\backup.bat para gerar cópias de segurança do banco de dados:
DOS

@echo off
set DATA=%date:~6,4%-%date:~3,2%-%date:~0,2%
if not exist "C:\sistema-entregas\backups" mkdir "C:\sistema-entregas\backups"

docker exec entregas_db_prod pg_dump -U entregas_user sistema_entregas > "C:\sistema-entregas\backups\backup_entregas_%DATA%.sql"
echo Backup concluido em C:\sistema-entregas\backups\backup_entregas_%DATA%.sql
pause

    Copie periodicamente a pasta C:\sistema-entregas\backups e C:\sistema-entregas\dados_producao\storage para um pendrive ou HD externo.

6.2 Como atualizar o sistema quando houver novidades no Git

No PowerShell, dentro de C:\sistema-entregas, execute:
PowerShell

git pull
docker compose -f docker-compose.prod.yml up -d --build

O banco de dados e os arquivos importados em dados_producao\ permanecem preservados durante a atualização.