<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TentativaItem extends Model
{
    use HasFactory;

    protected $table = 'tentativa_items';

    protected $fillable = [
        'tentativa_id',
        'entrega_item_id',
        'quantidade_entregue',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_entregue' => 'decimal:3',
        ];
    }

    public function tentativa(): BelongsTo
    {
        return $this->belongsTo(
            TentativaEntrega::class,
            'tentativa_id'
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