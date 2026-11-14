<?php

namespace App\Observers;

use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use App\Models\MotivoMovimiento;

class BienObserver
{
    /**
     * Equivalente al trigger trg_log_estado_fisico:
     * registra en la bitácora cuando cambia el estado físico del bien.
     */
    public function updated(Bien $bien): void
    {
        if (! $bien->isDirty('estado_fisico')) {
            return;
        }

        $motivo = MotivoMovimiento::query()
            ->where('nombre_motivo', 'Revisión de Inventario')
            ->value('cve_motivo') ?? 1;

        BitacoraMovimiento::query()->create([
            'cve_bien' => $bien->getKey(),
            'cve_motivo' => $motivo,
            'cve_persona_accion' => $bien->cve_encargado ?? 1,
            'fecha_movimiento' => now(),
            'observaciones' => 'El estado físico cambió a: '.$bien->estado_fisico,
        ]);
    }
}
