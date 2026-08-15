<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaEvento extends Model
{
    use HasFactory;

    protected $table = 'entrega_eventos';

    protected $fillable = [
        'entrega_id',
        'usuario_id',
        'tipo_evento',
        'descricao',
        'dados',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}