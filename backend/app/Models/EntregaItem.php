<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntregaItem extends Model
{
    use HasFactory;

    protected $table = 'entrega_items';

    protected $fillable = [
        'entrega_id',
        'codigo_produto',
        'descricao_produto',
        'unidade',
        'quantidade_solicitada',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_solicitada' => 'decimal:3',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function tentativaItens(): HasMany
    {
        return $this->hasMany(
            TentativaItem::class,
            'entrega_item_id'
        );
    }

    public function pendencias(): HasMany
    {
        return $this->hasMany(
            Pendencia::class,
            'entrega_item_id'
        );
    }
}