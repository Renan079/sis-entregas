<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentoImportado extends Model
{
    use HasFactory;

    protected $table = 'documento_importados';

    protected $fillable = [
        'usuario_id',
        'tipo_arquivo',
        'nome_arquivo',
        'arquivo_path',
        'hash_sha256',
        'status',
        'texto_extraido',
        'dados_extraidos',
        'erro_processamento',
    ];

    protected function casts(): array
    {
        return [
            'dados_extraidos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(
            Entrega::class,
            'documento_importado_id'
        );
    }
}