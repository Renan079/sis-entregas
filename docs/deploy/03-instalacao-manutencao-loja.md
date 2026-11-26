# 03 — Instalação, Rede Local e Manutenção na Loja

## 1. Objetivo

Este documento descreve o procedimento prático de **implantação, configuração de rede e manutenção diária** do **Sistema de Gerenciamento de Entregas** no computador servidor da loja, operando 100% offline.

O objetivo é registrar:

- os passos de instalação a partir do pendrive sem acesso à internet;
- a configuração de IP fixo e liberação da porta 80 para acesso na rede local (intranet);
- a criação e gestão do acesso administrativo inicial;
- a rotina de backup diário e o procedimento de restauração em caso de troca de equipamento;
- os comandos rápidos de diagnóstico operacional.

---

## 2. Pré-requisitos na Máquina Servidora da Loja

Antes de iniciar a instalação no computador que hospedará o sistema na loja, confirme:

1. **Docker instalado e ativo:** Docker Desktop (Windows) ou Docker Engine + Docker Compose v2 (Linux) previamente instalados.
2. **Porta 80 livre:** Nenhum outro servidor web (IIS, Apache, XAMPP, WAMP) ocupando a porta `80` da máquina.
3. **Permissões administrativas:** Acesso de administrador no sistema operacional para configurar firewall, IP fixo e executar os scripts.

---

## 3. Passo a Passo de Instalação Offline

1. **Copiar o pacote do pendrive:**
   Copie a pasta `Pacote_Instalacao_Entregas` do pendrive para um diretório fixo e permanente no disco do servidor (exemplo: `C:\SistemaEntregas\` no Windows ou `/opt/sistema-entregas/` no Linux).
   > **Importante:** Nunca execute o sistema diretamente de dentro do pendrive, pois os dados do banco (`dados_producao/`) serão gravados nessa pasta.

2. **Executar o instalador automático:**
   - **No Linux:** Abra o terminal dentro da pasta `scripts/` e execute:
     ```bash
     chmod +x instalar.sh backup.sh
     ./instalar.sh
     ```
   - **No Windows:** Entre na pasta `scripts/` e dê duplo clique em **`instalar.bat`** (ou execute via Prompt de Comando).

3. **Execução manual (alternativa caso não utilize os scripts):**
   Na pasta raiz onde está o `docker-compose.prod.yml`, execute na ordem:
   ```bash
   docker load -i sistema-entregas-v1.0.tar
   docker compose -f docker-compose.prod.yml up -d
   docker exec entregas_app_prod php artisan db:seed --class=AdminSeeder --force

    Validar o funcionamento local:
    Abra o navegador no próprio servidor e acesse:
    Plaintext

    http://localhost

        Efetue o login com o usuário criado pelo AdminSeeder (admin@loja.local / Admin@2026).

        Acesse imediatamente o menu Usuários, altere a senha padrão e cadastre os operadores da loja.

4. Configuração de Rede Local (Acesso pelos Demais Computadores e Celulares)

Para que os outros terminais da loja (balcão, expedição, gerência) acessem o sistema pelo navegador sem depender de internet:
4.1 Definir IP Fixo no Servidor da Loja

Configure um endereço IP estático na placa de rede (ou reserva DHCP no roteador da loja) para que o endereço do servidor não mude após quedas de energia.

    Exemplo de configuração recomendada:

        Endereço IPv4: 192.168.1.100

        Máscara de sub-rede: 255.255.255.0

        Gateway padrão: 192.168.1.1 (IP do roteador da loja)

4.2 Liberar a Porta 80 no Firewall

    Windows (PowerShell como Administrador):
    PowerShell

    New-NetFirewallRule -DisplayName "Sistema de Entregas (HTTP 80)" -Direction Inbound -LocalPort 80 -Protocol TCP -Action Allow

    Linux (UFW):
    Bash

    sudo ufw allow 80/tcp

4.3 Acesso nos Terminais Clientes

Em qualquer computador ou smartphone conectado à mesma rede Wi-Fi ou cabo da loja, abra o navegador e acesse:
Plaintext

[http://192.168.1.100](http://192.168.1.100)

(Substituindo pelo IP fixo definido para o servidor).
5. Rotina de Backup e Restauração

Todos os dados operacionais ficam concentrados em dois locais físicos dentro da pasta do sistema:

    Banco de dados: ./dados_producao/postgres

    Storage da aplicação: ./dados_producao/storage

5.1 Backup Diário (backup.sh / backup.bat)

O backup deve ser executado diariamente ao final do expediente, salvando o arquivo gerado em um HD externo, pendrive ou pasta sincronizada separada do disco principal.

    Comando Linux (scripts/backup.sh):
    Bash

    #!/bin/sh
    set -e

    DATA=$(date +%Y-%m-%d_%H-%M)
    DESTINO="../backups"
    mkdir -p "$DESTINO"

    docker exec entregas_db_prod pg_dump -U entregas_user sistema_entregas > "$DESTINO/backup_entregas_$DATA.sql"
    tar -czf "$DESTINO/storage_entregas_$DATA.tar.gz" -C ../dados_producao storage

    echo "Backup concluído em $DESTINO/backup_entregas_$DATA.sql"

    Comando Windows (scripts/backup.bat):
    DOS

    @echo off
    set DATA=%date:~6,4%-%date:~3,2%-%date:~0,2%
    if not exist "..\backups" mkdir "..\backups"

    docker exec entregas_db_prod pg_dump -U entregas_user sistema_entregas > "..\backups\backup_entregas_%DATA%.sql"
    echo Backup do banco concluido em ..\backups\backup_entregas_%DATA%.sql
    pause

5.2 Restauração em Caso de Troca de Computador

    Instale o sistema normalmente no novo computador seguindo a Seção 3.

    Pare apenas o container da aplicação para evitar escrita durante a restauração:
    Bash

    docker stop entregas_app_prod

    Restaure o arquivo .sql do backup no banco de dados:
    Bash

    cat backup_entregas_DATA.sql | docker exec -i entregas_db_prod psql -U entregas_user -d sistema_entregas

    Copie a pasta storage/ do backup para dentro de ./dados_producao/storage/.

    Reinicie os containers:
    Bash

    docker compose -f docker-compose.prod.yml up -d

6. Diagnóstico Rápido e Manutenção

Os containers estão configurados com restart: always, iniciando automaticamente junto com o sistema operacional após reinicializações ou quedas de energia.

Comandos úteis para verificação técnica na pasta raiz do sistema:

    Verificar se os 3 containers estão ativos (Up):
    Bash

    docker compose -f docker-compose.prod.yml ps

    logs do backend (Laravel)

    docker compose -f docker-compose.prod.yml logs -f app

    Reiniciar todo o sistema sem perder dados:
    Bash

    docker compose -f docker-compose.prod.yml restart