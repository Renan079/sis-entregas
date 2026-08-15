<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tentativa_entregas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrega_id')
                ->constrained('entregas');

            $table->foreignId('saida_id')
                ->constrained('saidas');

            $table->enum('resultado', [
                'COMPLETA',
                'PARCIAL',
                'NAO_ENTREGUE',
            ]);

            $table->string('motivo_nao_entrega', 255)
                ->nullable();

            $table->text('observacao')
                ->nullable();

            $table->foreignId('registrado_por')
                ->constrained('users');

            $table->timestampTz('data_hora')
                ->useCurrent();

            $table->timestamps();

            $table->index('entrega_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentativa_entregas');
    }
};