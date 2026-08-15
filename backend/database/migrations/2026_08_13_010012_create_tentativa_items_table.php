<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tentativa_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tentativa_id')
                ->constrained('tentativa_entregas')
                ->cascadeOnDelete();

            $table->foreignId('entrega_item_id')
                ->constrained('entrega_items');

            $table->decimal(
                'quantidade_entregue',
                12,
                3
            )->default(0);

            $table->text('observacao')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'tentativa_id',
                'entrega_item_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentativa_items');
    }
};