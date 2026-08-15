<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrega_id')
                ->constrained('entregas');

            $table->foreignId('entrega_item_id')
                ->constrained('entrega_items');

            $table->foreignId('tentativa_origem_id')
                ->nullable()
                ->constrained('tentativa_entregas')
                ->nullOnDelete();

            $table->decimal(
                'quantidade_original',
                12,
                3
            );

            $table->decimal(
                'quantidade_resolvida',
                12,
                3
            )->default(0);

            $table->string('motivo', 100)
                ->nullable();

            $table->enum('status', [
                'ABERTA',
                'PROGRAMADA',
                'RESOLVIDA',
                'CANCELADA',
            ])->default('ABERTA');

            $table->text('observacao')
                ->nullable();

            $table->timestampTz('data_abertura')
                ->useCurrent();

            $table->timestampTz('data_resolucao')
                ->nullable();

            $table->timestamps();

            $table->index('status');

            $table->index([
                'entrega_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendencias');
    }
};