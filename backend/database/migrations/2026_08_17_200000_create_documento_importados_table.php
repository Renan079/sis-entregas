<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * O projeto já previa esta tabela.
         * Só criamos se nenhuma das duas grafias existir,
         * evitando conflito com uma migration antiga.
         */
        if (
            Schema::hasTable('documento_importados') ||
            Schema::hasTable('documentos_importados')
        ) {
            return;
        }

        Schema::create('documento_importados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('tipo_arquivo', 100);
            $table->string('nome_arquivo', 255);
            $table->string('arquivo_path', 500);
            $table->string('hash_sha256', 64)->unique();

            /*
             * ENVIADO -> PROCESSANDO -> CONFERENCIA -> IMPORTADO
             *                                  \-> ERRO
             */
            $table->string('status', 30)
                ->default('ENVIADO');

            $table->text('texto_extraido')->nullable();
            $table->jsonb('dados_extraidos')->nullable();
            $table->text('erro_processamento')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        /*
         * Não apagamos uma tabela que possa ter sido criada
         * anteriormente por outra migration do projeto.
         */
    }
};
