<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaidaPendenciaRetorno extends Model
{
    use HasFactory;

    protected $table =
        'saida_pendencia_retornos';

    protected $fillable = [
        'saida_id',
        'pendencia_id',
        'quantidade_programada',
        'quantidade_entregue',
        'quantidade_devolvida',
        'quantidade_reentrega',
        'destino_restante',
        'motivo_devolucao',
        'observacao',
        'registrado_por',
        'data_hora',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_programada' =>
                'decimal:3',

            'quantidade_entregue' =>
                'decimal:3',

            'quantidade_devolvida' =>
                'decimal:3',

            'quantidade_reentrega' =>
                'decimal:3',

            'data_hora' =>
                'datetime',
        ];
    }

    public function saida(): BelongsTo
    {
        return $this->belongsTo(
            Saida::class
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
