<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrega_eventos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrega_id')
                ->constrained('entregas')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('tipo_evento', 60);

            $table->text('descricao')
                ->nullable();

            $table->jsonb('dados')
                ->nullable();

            $table->timestamps();

            $table->index('entrega_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_eventos');
    }
};