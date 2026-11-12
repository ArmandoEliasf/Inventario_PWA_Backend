<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('cve_rol');
            $table->string('nombre', 50)->unique();
            $table->text('descripcion')->nullable();
            $table->timestamp('fecha_alta')->useCurrent();
        });

        Schema::create('persona', function (Blueprint $table) {
            $table->id('cve_persona');
            $table->string('nombre', 50);
            $table->string('apellido_paterno', 35)->nullable();
            $table->string('apellido_materno', 35)->nullable();
            $table->unsignedInteger('cve_carrera')->nullable();
            $table->unsignedInteger('cve_nivel_estudio')->nullable();
            $table->timestamp('fecha_alta')->useCurrent();
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('cve_usuario');
            $table->string('usuario', 50)->unique();
            $table->string('contrasena_hash', 255);
            $table->foreignId('cve_rol')->nullable()->constrained('roles', 'cve_rol');
            $table->string('nombre', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('cve_persona')->nullable()->constrained('persona', 'cve_persona');
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamp('fecha_actualizacion')->useCurrent();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('persona');
        Schema::dropIfExists('roles');
    }
};
