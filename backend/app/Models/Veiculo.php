<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veiculo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'placa',
        'tipo',
        'status',
        'observacao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function saidas(): HasMany
    {
        return $this->hasMany(Saida::class);
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(OcorrenciaOperacional::class);
    }
}