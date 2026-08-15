<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaidaEntregaItem extends Model
{
    protected $table = 'saida_entrega_itens';

    protected $fillable = [
        'saida_entrega_id',
        'entrega_item_id',
        'quantidade_programada',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_programada' => 'decimal:3',
        ];
    }

    public function saidaEntrega(): BelongsTo
    {
        return $this->belongsTo(
            SaidaEntrega::class,
            'saida_entrega_id'
        );
    }

    public function entregaItem(): BelongsTo
    {
        return $this->belongsTo(
            EntregaItem::class,
            'entrega_item_id'
        );
    }
}