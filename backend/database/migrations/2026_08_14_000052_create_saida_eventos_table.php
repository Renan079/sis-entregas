<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saida_eventos', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('saida_id')
                ->constrained('saidas')
                ->cascadeOnDelete();

            $table
                ->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('tipo_evento', 80);

            $table->text('descricao');

            $table
                ->jsonb('dados')
                ->nullable();

            $table->timestamp('created_at');

            $table->index([
                'saida_id',
                'created_at',
            ]);
        });

        /*
         * Para carradas antigas já existentes,
         * conseguimos recuperar com segurança
         * apenas o momento em que foram criadas.
         *
         * Não inventamos horários de início,
         * finalização etc.
         */
        DB::table('saidas')
            ->select([
                'id',
                'criado_por',
                'created_at',
            ])
            ->orderBy('id')
            ->chunkById(
                200,
                function ($saidas) {
                    $eventos = [];

                    foreach ($saidas as $saida) {
                        $eventos[] = [
                            'saida_id' =>
                                $saida->id,

                            'usuario_id' =>
                                $saida->criado_por,

                            'tipo_evento' =>
                                'SAIDA_CRIADA',

                            'descricao' =>
                                "Carrada #{$saida->id} criada no sistema.",

                            'dados' =>
                                json_encode([
                                    'origem' =>
                                        'BACKFILL',
                                ]),

                            'created_at' =>
                                $saida->created_at,
                        ];
                    }

                    if ($eventos) {
                        DB::table(
                            'saida_eventos'
                        )->insert($eventos);
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'saida_eventos'
        );
    }
};