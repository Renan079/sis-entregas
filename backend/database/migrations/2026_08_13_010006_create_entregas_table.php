<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();

            // Número vindo do sistema comercial
            $table->string('numero_pedido', 50)
                ->nullable()
                ->index();

            $table->foreignId('documento_importado_id')
                ->nullable()
                ->constrained('documento_importados')
                ->nullOnDelete();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete();

            $table->foreignId('regiao_id')
                ->constrained('regiaos');

            // Snapshot dos dados utilizados naquela entrega
            $table->string('cliente_nome', 180);

            $table->string('cliente_apelido', 150)
                ->nullable();

            $table->string('cliente_telefone', 30)
                ->nullable();

            $table->text('endereco');

            $table->text('referencia')
                ->nullable();

            $table->enum('prioridade', [
                'BAIXA',
                'NORMAL',
                'ALTA',
                'URGENTE',
            ])->default('NORMAL');

            $table->enum('status', [
                'PENDENTE',
                'PROGRAMADA',
                'EM_SAIDA',
                'PARCIAL',
                'CONCLUIDA',
                'NAO_ENTREGUE',
                'CANCELADA',
            ])->default('PENDENTE');

            $table->text('observacao')
                ->nullable();

            $table->foreignId('criado_por')
                ->constrained('users');

            $table->timestamps();

            // Consulta muito comum no sistema
            $table->index(['regiao_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};