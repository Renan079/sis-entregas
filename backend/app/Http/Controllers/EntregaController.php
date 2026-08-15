<?php

namespace App\Http\Controllers;

use App\Enums\StatusEntrega;
use App\Http\Requests\Entregas\StoreEntregaRequest;
use App\Http\Requests\Entregas\UpdateEntregaRequest;
use App\Http\Resources\EntregaResource;
use App\Models\Cliente;
use App\Models\Entrega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntregaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entregas = Entrega::query()
            ->with([
                'cliente',
                'regiao',
                'itens',
            ])

            ->when($request->filled('busca'), function ($query) use ($request) {
                $busca = $request->string('busca')->toString();

                $query->where(function ($query) use ($busca) {
                    $query
                        ->where('numero_pedido', 'ilike', "%{$busca}%")
                        ->orWhere('cliente_nome', 'ilike', "%{$busca}%")
                        ->orWhere('cliente_apelido', 'ilike', "%{$busca}%")
                        ->orWhere('cliente_telefone', 'ilike', "%{$busca}%")
                        ->orWhere('endereco', 'ilike', "%{$busca}%");
                });
            })

            ->when(
                $request->filled('regiao_id'),
                fn ($query) => $query->where(
                    'regiao_id',
                    $request->integer('regiao_id')
                )
            )

            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->toString()
                )
            )

            ->when(
                $request->filled('prioridade'),
                fn ($query) => $query->where(
                    'prioridade',
                    $request->string('prioridade')->toString()
                )
            )

            ->latest()
            ->paginate(20);

        return EntregaResource::collection($entregas);
    }

    public function store(
        StoreEntregaRequest $request
    ): JsonResponse {
        $entrega = DB::transaction(function () use ($request) {
            $dados = $request->validated();

            $itens = $dados['itens'];
            unset($dados['itens']);

            /*
             * Se foi escolhido um cliente cadastrado,
             * usamos os dados atuais dele como snapshot.
             */
            if (! empty($dados['cliente_id'])) {
                $cliente = Cliente::findOrFail(
                    $dados['cliente_id']
                );

                $dados['cliente_nome'] = $cliente->nome;
                $dados['cliente_apelido'] = $cliente->apelido;
                $dados['cliente_telefone'] = $cliente->telefone;
            }

            $dados['status'] = StatusEntrega::PENDENTE;
            $dados['criado_por'] = $request->user()->id;

            $entrega = Entrega::create($dados);

            $entrega->itens()->createMany($itens);

            $entrega->eventos()->create([
                'usuario_id' => $request->user()->id,
                'tipo_evento' => 'ENTREGA_CRIADA',
                'descricao' => 'Entrega criada no sistema.',
                'dados' => [
                    'numero_pedido' => $entrega->numero_pedido,
                ],
            ]);

            return $entrega;
        });

        $entrega->load([
            'cliente',
            'regiao',
            'itens',
        ]);

        return (new EntregaResource($entrega))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Entrega $entrega
    ): EntregaResource {
        $entrega->load([
            'cliente',
            'regiao',
            'itens',
            'pendencias',
            'tentativas.itens',
            'eventos',
        ]);

        return new EntregaResource($entrega);
    }

    public function update(
        UpdateEntregaRequest $request,
        Entrega $entrega
    ): EntregaResource {
        DB::transaction(function () use ($request, $entrega) {
            $dados = $request->validated();

            $itens = $dados['itens'] ?? null;
            unset($dados['itens']);

            if (
                isset($dados['cliente_id'])
                && $dados['cliente_id']
            ) {
                $cliente = Cliente::findOrFail(
                    $dados['cliente_id']
                );

                $dados['cliente_nome'] = $cliente->nome;
                $dados['cliente_apelido'] = $cliente->apelido;
                $dados['cliente_telefone'] = $cliente->telefone;
            }

            $entrega->update($dados);

            /*
             * Depois que uma entrega já teve tentativa,
             * seus itens não podem ser substituídos.
             */
            if ($itens !== null) {
                if ($entrega->tentativas()->exists()) {
                    throw ValidationException::withMessages([
                        'itens' => [
                            'Os itens não podem ser alterados porque esta entrega já possui histórico de tentativa.',
                        ],
                    ]);
                }

                $entrega->itens()->delete();
                $entrega->itens()->createMany($itens);
            }

            $entrega->eventos()->create([
                'usuario_id' => $request->user()->id,
                'tipo_evento' => 'ENTREGA_ATUALIZADA',
                'descricao' => 'Dados da entrega atualizados.',
            ]);
        });

        $entrega->load([
            'cliente',
            'regiao',
            'itens',
        ]);

        return new EntregaResource($entrega);
    }

    public function destroy(
        Request $request,
        Entrega $entrega
    ): Response {
        if ($entrega->status === StatusEntrega::CONCLUIDA) {
            throw ValidationException::withMessages([
                'entrega' => [
                    'Uma entrega concluída não pode ser cancelada.',
                ],
            ]);
        }

        DB::transaction(function () use ($request, $entrega) {
            $entrega->update([
                'status' => StatusEntrega::CANCELADA,
            ]);

            $entrega->eventos()->create([
                'usuario_id' => $request->user()->id,
                'tipo_evento' => 'ENTREGA_CANCELADA',
                'descricao' => 'Entrega cancelada.',
            ]);
        });

        return response()->noContent();
    }
}