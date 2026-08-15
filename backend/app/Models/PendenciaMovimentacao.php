<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendenciaMovimentacao extends Model
{
    use HasFactory;

    protected $table = 'pendencia_movimentacoes';

    protected $fillable = [
        'pendencia_id',
        'tentativa_id',
        'tipo',
        'quantidade',
        'observacao',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
        ];
    }

    public function pendencia(): BelongsTo
    {
        return $this->belongsTo(Pendencia::class);
    }

    public function tentativa(): BelongsTo
    {
        return $this->belongsTo(
            TentativaEntrega::class,
            'tentativa_id'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'usuario_id'
        );
    }
}