<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesor', function (Blueprint $table) {
            $table->id('cve_profesor');
            $table->foreignId('cve_persona')->constrained('persona', 'cve_persona');
            $table->foreignId('cve_tipo_profesor')->constrained('tipo_profesor', 'cve_tipo_profesor');
            $table->foreignId('cve_area')->nullable()->constrained('area', 'cve_area');
            $table->boolean('activo')->default(true);
        });

        Schema::create('alumnos', function (Blueprint $table) {
            $table->id('cve_alumno');
            $table->foreignId('cve_persona')->constrained('persona', 'cve_persona');
            $table->string('matricula', 15);
            $table->date('fecha_inscrito');
            $table->boolean('inscrito');
            $table->boolean('activo')->default(true);
        });

        Schema::create('aula', function (Blueprint $table) {
            $table->id('cve_aula');
            $table->foreignId('cve_edificio')->constrained('edificio', 'cve_edificio');
            $table->foreignId('cve_tipo_aula')->constrained('tipo_aula', 'cve_tipo_aula');
            $table->foreignId('cve_profesor')->nullable()->constrained('profesor', 'cve_profesor');
            $table->foreignId('cve_adscripcion')->nullable()->constrained('adscripcion', 'cve_adscripcion');
            $table->string('nombre', 30);
            $table->string('codigo_barras', 50)->nullable()->unique();
            $table->string('codigo_qr', 255)->nullable()->unique();
            $table->unsignedSmallInteger('capacidad')->nullable();
        });

        Schema::create('adscripcion_persona', function (Blueprint $table) {
            $table->id('cve_adscripcion_persona');
            $table->foreignId('cve_adscripcion')->constrained('adscripcion', 'cve_adscripcion');
            $table->foreignId('cve_persona')->constrained('persona', 'cve_persona');
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_registro')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adscripcion_persona');
        Schema::dropIfExists('aula');
        Schema::dropIfExists('alumnos');
        Schema::dropIfExists('profesor');
    }
};
