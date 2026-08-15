<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaidaEquipe extends Model
{
    use HasFactory;

    protected $table = 'saida_equipes';

    protected $fillable = [
        'saida_id',
        'funcionario_id',
        'papel',
    ];

    public function saida(): BelongsTo
    {
        return $this->belongsTo(Saida::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }
}