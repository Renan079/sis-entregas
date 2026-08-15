<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrega_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrega_id')
                ->constrained('entregas')
                ->cascadeOnDelete();

            // Não existe produto_id propositalmente.
            // O produto vem diretamente da nota.
            $table->string('codigo_produto', 80)
                ->nullable();

            $table->string('descricao_produto', 255);

            $table->string('unidade', 20)
                ->nullable();

            $table->decimal(
                'quantidade_solicitada',
                12,
                3
            );

            $table->text('observacao')
                ->nullable();

            $table->timestamps();

            $table->index('codigo_produto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_items');
    }
};