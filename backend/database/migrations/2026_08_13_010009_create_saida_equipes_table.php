<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saida_equipes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saida_id')
                ->constrained('saidas')
                ->cascadeOnDelete();

            $table->foreignId('funcionario_id')
                ->constrained('funcionarios');

            $table->enum('papel', [
                'MOTORISTA',
                'AJUDANTE',
            ]);

            $table->timestamps();

            $table->unique([
                'saida_id',
                'funcionario_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saida_equipes');
    }
};