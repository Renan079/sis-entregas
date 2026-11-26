# Como iniciar o Sistema de Entregas

Este guia contém a sequência para iniciar o projeto no Ubuntu.

## 1. Primeira inicialização ou banco vazio

Use esta sequência quando estiver preparando o projeto pela primeira vez, quando o banco estiver vazio ou quando houver migrations novas.

### Terminal 1 — iniciar o PostgreSQL pelo Docker

```bash
cd "$HOME/Área de trabalho/sistema-entregas"
docker compose up -d
docker compose ps
```

Confira se o serviço do PostgreSQL está ativo (`Up`) e se a porta local `5433` está mapeada para a porta `5432` do container.

Se o Docker não estiver iniciado, execute:

```bash
sudo systemctl start docker
```

Depois, repita `docker compose up -d`.

### Terminal 2 — conferir e executar as migrations

```bash
cd "$HOME/Área de trabalho/sistema-entregas/backend"
php artisan migrate:status
php artisan migrate
```

O comando `migrate` cria no PostgreSQL as tabelas definidas pelas migrations que ainda não foram executadas.

**Atenção:** não use `php artisan migrate:fresh` para resolver problemas de inicialização. Esse comando apaga as tabelas existentes e recria o banco.

### Terminal 2 — iniciar o Backend (Laravel)

Depois que o PostgreSQL estiver ativo e as migrations tiverem sido executadas:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Mantenha esse terminal aberto.

### Terminal 3 — iniciar o Frontend (Vue)

```bash
cd "$HOME/Área de trabalho/sistema-entregas/frontend"
npm run dev
```

Mantenha esse terminal aberto também.

### Abrir o sistema

Acesse no navegador:

**http://127.0.0.1:5173**

## 2. Inicializações seguintes (uso normal)

Quando o banco já estiver criado e não houver migrations novas, não é necessário executar `php artisan migrate` toda vez.

1. No Terminal 1, inicie o banco:

   ```bash
   cd "$HOME/Área de trabalho/sistema-entregas"
   docker compose up -d
   docker compose ps
   ```

2. No Terminal 2, inicie o Backend:

   ```bash
   cd "$HOME/Área de trabalho/sistema-entregas/backend"
   php artisan serve --host=127.0.0.1 --port=8001
   ```

3. No Terminal 3, inicie o Frontend:

   ```bash
   cd "$HOME/Área de trabalho/sistema-entregas/frontend"
   npm run dev
   ```

4. Abra **http://127.0.0.1:5173**.

Execute `php artisan migrate` novamente somente quando houver migrations pendentes — por exemplo, depois de atualizar o projeto com novas migrations ou ao preparar um banco novo.

## 3. Endereços locais

| Serviço | Endereço |
|---|---|
| Frontend (Vue/Vite) | `http://127.0.0.1:5173` |
| Backend (Laravel) | `http://127.0.0.1:8001` |
| PostgreSQL | `127.0.0.1:5433` |

## 4. Erro: `SQLSTATE[08006]` / `Connection refused` na porta 5433

Esse erro significa que o Laravel tentou acessar o PostgreSQL, mas não encontrou um servidor respondendo em `127.0.0.1:5433`. Na maioria das vezes, o container do banco ainda não foi iniciado.

1. Abra o Terminal 1 e entre na pasta do projeto:

   ```bash
   cd "$HOME/Área de trabalho/sistema-entregas"
   ```

2. Inicie os serviços:

   ```bash
   docker compose up -d
   ```

3. Confira o estado:

   ```bash
   docker compose ps
   ```

4. Se o PostgreSQL não ficar ativo, consulte os logs:

   ```bash
   docker compose logs --tail=100
   ```

5. Quando o PostgreSQL estiver ativo, volte ao Backend e confira/execute as migrations:

   ```bash
   cd "$HOME/Área de trabalho/sistema-entregas/backend"
   php artisan migrate:status
   php artisan migrate
   ```

6. Inicie ou reinicie o Backend e atualize a página no navegador.

Se o container estiver ativo, mas o erro continuar, confira se o arquivo `.env` do Backend aponta para os dados corretos do banco, especialmente:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=sistema_entregas
```

Não altere usuário, senha, nome do banco ou configuração de portas sem conferir os valores usados no `docker-compose.yml` e no `.env`.

## 5. Como encerrar

- Nos terminais do Backend e do Frontend, pressione `Ctrl + C`.
- Para parar os containers do projeto, entre na pasta raiz e execute:

  ```bash
  cd "$HOME/Área de trabalho/sistema-entregas"
  docker compose stop
  ```

**Importante:** não execute `docker compose down -v` para encerrar o sistema. A opção `-v` remove os volumes e pode apagar os dados persistidos do PostgreSQL.
