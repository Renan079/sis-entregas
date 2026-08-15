<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saida_entregas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saida_id')
                ->constrained('saidas')
                ->cascadeOnDelete();

            $table->foreignId('entrega_id')
                ->constrained('entregas');

            // Ordem opcional de entrega dentro da carrada
            $table->unsignedInteger('ordem')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'saida_id',
                'entrega_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saida_entregas');
    }
};