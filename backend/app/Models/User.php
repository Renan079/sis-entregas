<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'perfil',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    public function documentosImportados(): HasMany
    {
        return $this->hasMany(DocumentoImportado::class, 'usuario_id');
    }

    public function entregasCriadas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'criado_por');
    }

    public function saidasCriadas(): HasMany
    {
        return $this->hasMany(Saida::class, 'criado_por');
    }

    public function tentativasRegistradas(): HasMany
    {
        return $this->hasMany(TentativaEntrega::class, 'registrado_por');
    }

    public function movimentacoesPendencia(): HasMany
    {
        return $this->hasMany(PendenciaMovimentacao::class, 'usuario_id');
    }

    public function ocorrenciasRegistradas(): HasMany
    {
        return $this->hasMany(OcorrenciaOperacional::class, 'registrado_por');
    }

    public function eventosEntrega(): HasMany
    {
        return $this->hasMany(EntregaEvento::class, 'usuario_id');
    }
}