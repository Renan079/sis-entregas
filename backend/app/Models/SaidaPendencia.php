<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaidaPendencia extends Model
{
    use HasFactory;

    protected $table = 'saida_pendencias';

    protected $fillable = [
        'saida_id',
        'pendencia_id',
    ];

    public function saida(): BelongsTo
    {
        return $this->belongsTo(Saida::class);
    }

    public function pendencia(): BelongsTo
    {
        return $this->belongsTo(Pendencia::class);
    }
}