<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id('cve_auditoria');
            $table->date('fecha_auditoria')->useCurrent();
            $table->foreignId('cve_auditor')->nullable()->constrained('persona', 'cve_persona');
            $table->text('observaciones_generales')->nullable();
        });

        Schema::create('auditoria_detalle', function (Blueprint $table) {
            $table->id('cve_auditoria_det');
            $table->foreignId('cve_auditoria')->nullable()->constrained('auditorias', 'cve_auditoria');
            $table->foreignId('cve_bien')->nullable()->constrained('bienes', 'cve_bien');
            $table->boolean('encontrado')->nullable();
            $table->string('estado_encontrado', 50)->nullable();
            $table->text('observaciones_evidencia')->nullable();
        });

        Schema::create('prestamos', function (Blueprint $table) {
            $table->id('cve_prestamo');
            $table->foreignId('cve_bien')->constrained('bienes', 'cve_bien');
            $table->foreignId('cve_persona_solicita')->constrained('persona', 'cve_persona');
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_devolucion_pactada')->nullable();
            $table->timestamp('fecha_devolucion_real')->nullable();
            $table->string('estado_prestamo', 20)->default('pendiente');
            $table->text('observaciones')->nullable();
        });

        Schema::create('bitacora_movimientos', function (Blueprint $table) {
            $table->id('cve_movimiento');
            $table->foreignId('cve_bien')->nullable()->constrained('bienes', 'cve_bien');
            $table->foreignId('cve_motivo')->nullable()->constrained('motivo_de_movimiento', 'cve_motivo');
            $table->foreignId('cve_persona_accion')->nullable()->constrained('persona', 'cve_persona');
            $table->timestamp('fecha_movimiento')->useCurrent();
            $table->text('observaciones')->nullable();
        });

        Schema::create('folio_siia', function (Blueprint $table) {
            $table->id('cve_folio');
            $table->string('clave_siia', 50);
            $table->foreignId('cve_persona')->constrained('persona', 'cve_persona');
            $table->date('fecha')->useCurrent();
            $table->time('hora')->nullable();
            $table->boolean('estado')->default(true);
        });

        Schema::create('numero_resguardo', function (Blueprint $table) {
            $table->id('cve_resguardo_pk');
            $table->string('no_resguardo', 50);
            $table->foreignId('cve_persona')->constrained('persona', 'cve_persona');
            $table->date('fecha')->useCurrent();
            $table->time('hora')->nullable();
            $table->boolean('estado')->default(true);
            $table->date('fecha_registro')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numero_resguardo');
        Schema::dropIfExists('folio_siia');
        Schema::dropIfExists('bitacora_movimientos');
        Schema::dropIfExists('prestamos');
        Schema::dropIfExists('auditoria_detalle');
        Schema::dropIfExists('auditorias');
    }
};
