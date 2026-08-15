<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_importados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users');

            $table->enum('tipo_arquivo', [
                'FOTO',
                'PDF',
                'CSV',
                'XLS',
                'XLSX',
                'XML',
            ]);

            $table->string('nome_arquivo', 255);

            $table->string('arquivo_path', 500);

            $table->string('hash_sha256', 64)
                ->nullable();

            $table->enum('status', [
                'ENVIADO',
                'PROCESSANDO',
                'AGUARDANDO_CONFIRMACAO',
                'CONFIRMADO',
                'ERRO',
            ])->default('ENVIADO');

            // Texto bruto retornado pelo OCR
            $table->text('texto_extraido')
                ->nullable();

            // Dados estruturados extraídos da nota
            $table->jsonb('dados_extraidos')
                ->nullable();

            $table->text('erro_processamento')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_importados');
    }
};