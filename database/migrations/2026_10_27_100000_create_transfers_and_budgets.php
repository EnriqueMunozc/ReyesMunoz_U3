<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operaciones_financieras', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->nullable();
        });
        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('operacion_id')->nullable()->unique()->constrained('operaciones_financieras')->restrictOnDelete();
        });
        // El orden previo por fecha e ID se conserva entre movimientos existentes.
        DB::table('movimientos')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $order = DB::table('operaciones_financieras')->insertGetId(['created_at' => $row->created_at]);
                DB::table('movimientos')->where('id', $row->id)->update(['operacion_id' => $order]);
            }
        });
        Schema::table('movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('operacion_id')->nullable(false)->change();
        });
        Schema::create('transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('operacion_id')->unique()->constrained('operaciones_financieras')->restrictOnDelete();
            $table->unsignedBigInteger('origen_id');
            $table->unsignedBigInteger('destino_id');
            $table->decimal('importe', 15, 2);
            $table->date('fecha');
            $table->string('descripcion', 500)->nullable();
            $table->uuid('clave_idempotencia');
            $table->string('huella', 64);
            $table->timestamps();
            $table->unique(['user_id', 'clave_idempotencia']);
            $table->foreign(['origen_id', 'user_id'])->references(['id', 'user_id'])->on('cuentas')->restrictOnDelete();
            $table->foreign(['destino_id', 'user_id'])->references(['id', 'user_id'])->on('cuentas')->restrictOnDelete();
            $table->index(['user_id', 'fecha', 'id']);
        });
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('categoria_id');
            $table->string('mes', 7);
            $table->decimal('limite', 15, 2);
            $table->timestamps();
            $table->foreign(['categoria_id', 'user_id'])->references(['id', 'user_id'])->on('categorias')->restrictOnDelete();
            $table->unique(['user_id', 'categoria_id', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
        Schema::dropIfExists('transferencias');
        Schema::table('movimientos', fn (Blueprint $table) => $table->dropConstrainedForeignId('operacion_id'));
        Schema::dropIfExists('operaciones_financieras');
    }
};
