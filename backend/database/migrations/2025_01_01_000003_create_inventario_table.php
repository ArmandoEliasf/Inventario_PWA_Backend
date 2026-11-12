<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('familias_articulos', function (Blueprint $table) {
            $table->id('cve_familia');
            $table->string('clave', 2);
            $table->string('nombre', 100);
            $table->foreignId('cve_tipo')->nullable()->constrained('tipos_bien', 'cve_tipo');
        });

        Schema::create('modelos', function (Blueprint $table) {
            $table->id('cve_modelo');
            $table->string('nombre_modelo', 100);
            $table->foreignId('cve_marca')->nullable()->constrained('marcas', 'cve_marca');
            $table->text('descripcion')->nullable();
        });

        Schema::create('articulos', function (Blueprint $table) {
            $table->id('cve_articulo');
            $table->string('clave', 4)->unique();
            $table->string('nombre', 255);
            $table->foreignId('cve_familia')->nullable()->constrained('familias_articulos', 'cve_familia');
        });

        Schema::create('bienes', function (Blueprint $table) {
            $table->id('cve_bien');
            $table->string('nombre', 100);
            $table->string('codigo_barras', 50)->nullable()->unique();
            $table->string('nfc', 50)->nullable()->unique();
            $table->string('codigo_qr', 255)->nullable()->unique();
            $table->string('no_serie', 50)->nullable()->unique();
            $table->string('no_factura', 50)->nullable()->unique();
            $table->text('descripcion')->nullable();
            $table->string('foto_url', 500)->nullable();
            $table->string('foto_drive_id', 255)->nullable();
            $table->foreignId('cve_modelo')->nullable()->constrained('modelos', 'cve_modelo');
            $table->foreignId('cve_articulo')->nullable()->constrained('articulos', 'cve_articulo');
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->foreignId('cve_aula')->nullable()->constrained('aula', 'cve_aula');
            $table->foreignId('cve_encargado')->nullable()->constrained('persona', 'cve_persona');
            $table->string('estado_fisico', 20)->nullable();
            $table->string('estado_prestamo', 20)->default('disponible');
            $table->boolean('activo')->default(true);
            $table->date('fecha_registro')->useCurrent();

            $table->index('codigo_qr', 'idx_bienes_qr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bienes');
        Schema::dropIfExists('articulos');
        Schema::dropIfExists('modelos');
        Schema::dropIfExists('familias_articulos');
    }
};
