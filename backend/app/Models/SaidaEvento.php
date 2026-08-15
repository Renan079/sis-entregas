<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaidaEvento extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'saida_id',
        'usuario_id',
        'tipo_evento',
        'descricao',
        'dados',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function saida(): BelongsTo
    {
        return $this->belongsTo(
            Saida::class
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