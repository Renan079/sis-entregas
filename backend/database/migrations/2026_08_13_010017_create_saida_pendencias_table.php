<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saida_pendencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saida_id')
                ->constrained('saidas')
                ->cascadeOnDelete();

            $table->foreignId('pendencia_id')
                ->constrained('pendencias');

            $table->timestamps();

            $table->unique([
                'saida_id',
                'pendencia_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saida_pendencias');
    }
};