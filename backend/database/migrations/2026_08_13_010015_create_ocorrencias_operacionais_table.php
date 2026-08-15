<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocorrencias_operacionais', function (Blueprint $table) {
            $table->id();

            $table->enum('tipo', [
                'FUNCIONARIO_INDISPONIVEL',
                'VEICULO_INDISPONIVEL',
                'QUEBRA_VEICULO',
                'OUTRO',
            ]);

            $table->foreignId('funcionario_id')
                ->nullable()
                ->constrained('funcionarios')
                ->nullOnDelete();

            $table->foreignId('veiculo_id')
                ->nullable()
                ->constrained('veiculos')
                ->nullOnDelete();

            $table->foreignId('saida_id')
                ->nullable()
                ->constrained('saidas')
                ->nullOnDelete();

            $table->text('descricao');

            $table->timestampTz('inicio')
                ->useCurrent();

            $table->timestampTz('fim')
                ->nullable();

            $table->foreignId('registrado_por')
                ->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencias_operacionais');
    }
};