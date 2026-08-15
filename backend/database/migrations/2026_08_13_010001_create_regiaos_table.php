<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regiaos', function (Blueprint $table) {
            $table->id();

            $table->string('nome', 120)->unique();
            $table->string('cidade', 120)->nullable();

            $table->text('observacao')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regiaos');
    }
};