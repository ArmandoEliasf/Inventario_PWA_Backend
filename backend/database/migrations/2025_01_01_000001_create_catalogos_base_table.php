<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area', function (Blueprint $table) {
            $table->id('cve_area');
            $table->foreignId('cve_area_padre')->nullable()->constrained('area', 'cve_area');
            $table->string('abreviatura', 5);
            $table->string('nombre', 60);
            $table->boolean('activo')->default(true);
        });

        Schema::create('tipo_profesor', function (Blueprint $table) {
            $table->id('cve_tipo_profesor');
            $table->string('nombre', 30);
            $table->text('descripcion')->nullable();
        });

        Schema::create('edificio', function (Blueprint $table) {
            $table->id('cve_edificio');
            $table->string('nombre', 80)->nullable();
            $table->string('abreviatura', 5)->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('tipo_aula', function (Blueprint $table) {
            $table->id('cve_tipo_aula');
            $table->string('nombre', 45);
        });

        Schema::create('tipos_bien', function (Blueprint $table) {
            $table->id('cve_tipo');
            $table->string('clave', 2);
            $table->string('nombre', 100);
        });

        Schema::create('marcas', function (Blueprint $table) {
            $table->id('cve_marca');
            $table->string('nombre_marca', 50)->unique();
        });

        Schema::create('motivo_de_movimiento', function (Blueprint $table) {
            $table->id('cve_motivo');
            $table->string('nombre_motivo', 50);
            $table->boolean('requiere_aprobacion')->default(false);
        });

        Schema::create('adscripcion', function (Blueprint $table) {
            $table->id('cve_adscripcion');
            $table->string('clave_adscripcion', 20)->unique();
            $table->string('nombre_adscripcion', 100);
            $table->boolean('estado')->default(true);
            $table->date('fecha_registro')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adscripcion');
        Schema::dropIfExists('motivo_de_movimiento');
        Schema::dropIfExists('marcas');
        Schema::dropIfExists('tipos_bien');
        Schema::dropIfExists('tipo_aula');
        Schema::dropIfExists('edificio');
        Schema::dropIfExists('tipo_profesor');
        Schema::dropIfExists('area');
    }
};
