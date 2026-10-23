<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('tipo', 20);
            $table->decimal('saldo_inicial', 15, 2);
            $table->date('fecha_inicial');
            $table->boolean('archivada')->default(false);
            $table->timestamps();
            $table->unique(['id', 'user_id']);
            $table->index(['user_id', 'archivada']);
        });
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('nombre_clave', 100);
            $table->string('tipo', 10);
            $table->timestamps();
            $table->unique(['user_id', 'tipo', 'nombre_clave']);
            $table->unique(['id', 'user_id']);
        });
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cuenta_id');
            $table->unsignedBigInteger('categoria_id');
            $table->string('tipo', 10);
            $table->decimal('importe', 15, 2);
            $table->date('fecha');
            $table->string('descripcion', 500)->nullable();
            $table->timestamps();
            $table->foreign(['cuenta_id', 'user_id'])->references(['id', 'user_id'])->on('cuentas')->restrictOnDelete();
            $table->foreign(['categoria_id', 'user_id'])->references(['id', 'user_id'])->on('categorias')->restrictOnDelete();
            $table->index(['user_id', 'fecha', 'id']);
            $table->index(['cuenta_id', 'fecha', 'id']);
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('movimientos');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('cuentas');
    }
};
