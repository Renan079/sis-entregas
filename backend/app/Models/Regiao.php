<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regiao extends Model
{
    use HasFactory;

    protected $table = 'regiaos';

    protected $fillable = [
        'nome',
        'cidade',
        'observacao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    public function saidas(): HasMany
    {
        return $this->hasMany(Saida::class, 'regiao_principal_id');
    }
}