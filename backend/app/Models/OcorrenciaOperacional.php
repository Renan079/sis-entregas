<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcorrenciaOperacional extends Model
{
    use HasFactory;

    protected $table = 'ocorrencias_operacionais';

    protected $fillable = [
        'tipo',
        'funcionario_id',
        'veiculo_id',
        'saida_id',
        'descricao',
        'inicio',
        'fim',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
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
}