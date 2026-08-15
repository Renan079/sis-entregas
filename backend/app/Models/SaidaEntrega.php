<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaidaEntrega extends Model
{
    use HasFactory;

    protected $table = 'saida_entregas';

    protected $fillable = [
        'saida_id',
        'entrega_id',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }
    
    public function itensProgramados(): HasMany
    {
        return $this->hasMany(
            SaidaEntregaItem::class,
            'saida_entrega_id'
        );
    }

    public function saida(): BelongsTo
    {
        return $this->belongsTo(Saida::class);
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }
}