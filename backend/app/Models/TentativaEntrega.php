<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\ResultadoEntrega;

class TentativaEntrega extends Model
{
    use HasFactory;

    protected $table = 'tentativa_entregas';

    protected $fillable = [
        'entrega_id',
        'saida_id',
        'resultado',
        'motivo_nao_entrega',
        'observacao',
        'registrado_por',
        'data_hora',
    ];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'resultado' => ResultadoEntrega::class,
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function saida(): BelongsTo
    {
        return $this->belongsTo(Saida::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'registrado_por'
        );
    }

    public function itens(): HasMany
    {
        return $this->hasMany(
            TentativaItem::class,
            'tentativa_id'
        );
    }

    public function pendenciasOriginadas(): HasMany
    {
        return $this->hasMany(
            Pendencia::class,
            'tentativa_origem_id'
        );
    }
}