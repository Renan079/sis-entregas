<?php

namespace App\Models;

use App\Enums\StatusSaida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Saida extends Model
{
    protected $fillable = [
        'regiao_principal_id',
        'veiculo_id',
        'data_saida',
        'rota',
        'status',
        'observacao',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'data_saida' => 'date',
            'status' => StatusSaida::class,
            'rota' => 'array',
        ];
    }

    public function regiaoPrincipal(): BelongsTo
    {
        return $this->belongsTo(
            Regiao::class,
            'regiao_principal_id'
        );
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'criado_por'
        );
    }

    public function equipe(): HasMany
    {
        return $this->hasMany(
            SaidaEquipe::class,
            'saida_id'
        );
    }

    public function funcionarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Funcionario::class,
            'saida_equipes'
        )
            ->withPivot('papel')
            ->withTimestamps();
    }

    public function entregas(): BelongsToMany
    {
        return $this->belongsToMany(
            Entrega::class,
            'saida_entregas'
        )
            ->withPivot('ordem')
            ->withTimestamps();
    }

    public function saidaEntregas(): HasMany
    {
        return $this->hasMany(
            SaidaEntrega::class,
            'saida_id'
        );
    }

    public function tentativas(): HasMany
    {
        return $this->hasMany(
            TentativaEntrega::class,
            'saida_id'
        );
    }

    public function pendencias(): BelongsToMany
    {
        return $this->belongsToMany(
            Pendencia::class,
            'saida_pendencias'
        )->withTimestamps();
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(
            OcorrenciaOperacional::class,
            'saida_id'
        );
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(
            SaidaEvento::class
        )->orderBy('created_at');
    }
}