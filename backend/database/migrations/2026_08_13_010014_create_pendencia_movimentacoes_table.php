<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendencia_movimentacoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pendencia_id')
                ->constrained('pendencias')
                ->cascadeOnDelete();

            $table->foreignId('tentativa_id')
                ->nullable()
                ->constrained('tentativa_entregas')
                ->nullOnDelete();

            $table->enum('tipo', [
                'CRIACAO',
                'PROGRAMACAO',
                'ENTREGA_PARCIAL',
                'RESOLUCAO',
                'AJUSTE',
                'CANCELAMENTO',
            ]);

            $table->decimal('quantidade', 12, 3)
                ->nullable();

            $table->text('observacao')
                ->nullable();

            $table->foreignId('usuario_id')
                ->constrained('users');

            $table->timestamps();

            $table->index('pendencia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendencia_movimentacoes');
    }
};