<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'saida_pendencia_retornos',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('saida_id')
                    ->constrained('saidas')
                    ->cascadeOnDelete();

                $table->foreignId('pendencia_id')
                    ->constrained('pendencias')
                    ->cascadeOnDelete();

                /*
                 * Snapshot do saldo da pendência quando
                 * esta reentrega retornou da rua.
                 */
                $table->decimal(
                    'quantidade_programada',
                    12,
                    3
                );

                $table->decimal(
                    'quantidade_entregue',
                    12,
                    3
                )->default(0);

                $table->decimal(
                    'quantidade_devolvida',
                    12,
                    3
                )->default(0);

                $table->decimal(
                    'quantidade_reentrega',
                    12,
                    3
                )->default(0);

                /*
                 * Só é preenchido quando ainda restou
                 * quantidade não entregue.
                 */
                $table->enum('destino_restante', [
                    'REENTREGA',
                    'DEVOLVIDO',
                ])->nullable();

                $table->string(
                    'motivo_devolucao',
                    100
                )->nullable();

                $table->text('observacao')
                    ->nullable();

                $table->foreignId('registrado_por')
                    ->constrained('users');

                $table->timestampTz('data_hora')
                    ->useCurrent();

                $table->timestamps();

                /*
                 * Uma pendência só pode ter um retorno
                 * registrado por carrada.
                 */
                $table->unique([
                    'saida_id',
                    'pendencia_id',
                ]);

                $table->index('pendencia_id');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'saida_pendencia_retornos'
        );
    }
};
