# Melhoria — data de lançamento das entregas

Esta atualização adiciona à tela **Entregas**:

- coluna **Lançada em** com data e hora de criação;
- indicador **Hoje / Há 1 dia / Há X dias** para entregas ainda abertas;
- seletor de ordenação **Mais antigas primeiro / Mais recentes primeiro**;
- padrão operacional: **mais antigas primeiro**;
- ordenação feita no backend antes da paginação.

## Arquivos alterados

- `backend/app/Http/Controllers/EntregaController.php`
- `frontend/src/pages/Entregas/EntregasView.vue`
- `frontend/src/services/entregaService.ts`
- `frontend/src/styles/pages/entrega/entregas.css`

## Não precisa alterar

`EntregaResource.php` já envia `created_at` e `updated_at`.

`src/types/Entrega.ts` já possui `created_at: string` e `updated_at: string`.

Não há migration nova e nenhuma regra de entrega/pendência/carrada foi modificada.

## Instalação

Extraia o ZIP na raiz do projeto, onde existem as pastas `backend/` e `frontend/`:

```bash
unzip -o entregas_data_ordenacao_v1.zip -d .
```

Depois teste o frontend:

```bash
cd frontend
npm run build
```
