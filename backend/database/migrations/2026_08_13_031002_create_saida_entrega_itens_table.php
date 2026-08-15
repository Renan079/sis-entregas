<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saida_entrega_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saida_entrega_id')
                ->constrained('saida_entregas')
                ->cascadeOnDelete();

            $table->foreignId('entrega_item_id')
                ->constrained('entrega_items');

            $table->decimal(
                'quantidade_programada',
                12,
                3
            );

            $table->timestamps();

            $table->unique([
                'saida_entrega_id',
                'entrega_item_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saida_entrega_itens');
    }
};