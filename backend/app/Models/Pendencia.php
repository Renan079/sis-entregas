<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\StatusPendencia;

class Pendencia extends Model
{
    use HasFactory;

    protected $table = 'pendencias';

    protected $fillable = [
        'entrega_id',
        'entrega_item_id',
        'tentativa_origem_id',
        'quantidade_original',
        'quantidade_resolvida',
        'motivo',
        'status',
        'observacao',
        'data_abertura',
        'data_resolucao',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_original' => 'decimal:3',
            'quantidade_resolvida' => 'decimal:3',
            'data_abertura' => 'datetime',
            'data_resolucao' => 'datetime',
            'status' => StatusPendencia::class,
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function entregaItem(): BelongsTo
    {
        return $this->belongsTo(
            EntregaItem::class,
            'entrega_item_id'
        );
    }

    public function tentativaOrigem(): BelongsTo
    {
        return $this->belongsTo(
            TentativaEntrega::class,
            'tentativa_origem_id'
        );
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(
            PendenciaMovimentacao::class,
            'pendencia_id'
        );
    }

    public function saidas(): BelongsToMany
    {
        return $this->belongsToMany(
            Saida::class,
            'saida_pendencias'
        )->withTimestamps();
    }
}