<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\StatusEntrega;

class Entrega extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_pedido',
        'documento_importado_id',
        'cliente_id',
        'regiao_id',
        'cliente_nome',
        'cliente_apelido',
        'cliente_telefone',
        'endereco',
        'referencia',
        'prioridade',
        'status',
        'observacao',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEntrega::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function regiao(): BelongsTo
    {
        return $this->belongsTo(Regiao::class);
    }

    public function documentoImportado(): BelongsTo
    {
        return $this->belongsTo(
            DocumentoImportado::class,
            'documento_importado_id'
        );
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'criado_por'
        );
    }

    public function itens(): HasMany
    {
        return $this->hasMany(EntregaItem::class);
    }

    public function tentativas(): HasMany
    {
        return $this->hasMany(TentativaEntrega::class);
    }

    public function pendencias(): HasMany
    {
        return $this->hasMany(Pendencia::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EntregaEvento::class);
    }

    public function saidas(): BelongsToMany
    {
        return $this->belongsToMany(
            Saida::class,
            'saida_entregas'
        )
        ->withPivot('ordem')
        ->withTimestamps();
    }


}