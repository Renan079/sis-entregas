<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devolucao extends Model
{
    use HasFactory;

    protected $table = 'devolucoes';

    protected $fillable = [
        'entrega_id',
        'entrega_item_id',
        'saida_id',
        'tentativa_origem_id',
        'pendencia_id',
        'quantidade',
        'motivo',
        'observacao',
        'registrado_por',
        'data_hora',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'data_hora' => 'datetime',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(
            Entrega::class
        );
    }

    public function entregaItem(): BelongsTo
    {
        return $this->belongsTo(
            EntregaItem::class,
            'entrega_item_id'
        );
    }

    public function saida(): BelongsTo
    {
        return $this->belongsTo(
            Saida::class
        );
    }

    public function tentativaOrigem(): BelongsTo
    {
        return $this->belongsTo(
            TentativaEntrega::class,
            'tentativa_origem_id'
        );
    }

    public function pendencia(): BelongsTo
    {
        return $this->belongsTo(
            Pendencia::class
        );
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'registrado_por'
        );
    }
}
