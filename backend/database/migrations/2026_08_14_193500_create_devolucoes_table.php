<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrega_id')
                ->constrained('entregas')
                ->cascadeOnDelete();

            $table->foreignId('entrega_item_id')
                ->constrained('entrega_items')
                ->cascadeOnDelete();

            $table->foreignId('saida_id')
                ->nullable()
                ->constrained('saidas')
                ->nullOnDelete();

            $table->foreignId('tentativa_origem_id')
                ->nullable()
                ->constrained('tentativa_entregas')
                ->nullOnDelete();

            $table->foreignId('pendencia_id')
                ->nullable()
                ->constrained('pendencias')
                ->nullOnDelete();

            $table->decimal(
                'quantidade',
                12,
                3
            );

            /*
             * Código do motivo escolhido no retorno.
             *
             * Exemplos:
             * CLIENTE_RECUSOU
             * CLIENTE_DESISTIU
             * PRODUTO_INCORRETO
             * PRODUTO_AVARIADO
             * QUANTIDADE_EXCEDENTE
             * OUTRO
             */
            $table->string('motivo', 100)
                ->nullable();

            $table->text('observacao')
                ->nullable();

            $table->foreignId('registrado_por')
                ->constrained('users');

            $table->timestampTz('data_hora')
                ->useCurrent();

            $table->timestamps();

            $table->index('entrega_item_id');
            $table->index('saida_id');
            $table->index('pendencia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucoes');
    }
};
