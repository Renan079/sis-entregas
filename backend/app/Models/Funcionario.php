<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Funcionario extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'funcao',
        'pode_dirigir',
        'ativo',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'pode_dirigir' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function equipesSaida(): HasMany
    {
        return $this->hasMany(SaidaEquipe::class);
    }

    public function saidas(): BelongsToMany
    {
        return $this->belongsToMany(
            Saida::class,
            'saida_equipes'
        )->withPivot('papel')
         ->withTimestamps();
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(OcorrenciaOperacional::class);
    }
}