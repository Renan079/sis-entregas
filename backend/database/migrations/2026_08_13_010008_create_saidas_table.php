<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saidas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('regiao_principal_id')
                ->nullable()
                ->constrained('regiaos')
                ->nullOnDelete();

            $table->foreignId('veiculo_id')
                ->nullable()
                ->constrained('veiculos')
                ->nullOnDelete();

            $table->date('data_saida');

            /*
             * Rota completa da carrada.
             *
             * Exemplo:
             * Gameleira
             * → Serra de São João
             * → Malhada Redonda
             * → Banco de Areia
             */
            $table->jsonb('rota')
                ->default('[]');

            $table->enum('status', [
                'PLANEJADA',
                'EM_ANDAMENTO',
                'FINALIZADA',
                'CANCELADA',
            ])->default('PLANEJADA');

            $table->text('observacao')->nullable();

            $table->foreignId('criado_por')
                ->constrained('users');

            $table->timestamps();

            $table->index([
                'data_saida',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saidas');
    }
};