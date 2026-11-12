<?php

namespace App\Observers;

use App\Models\Bien;
use App\Models\Prestamo;

class PrestamoObserver
{
    /**
     * Equivalente al trigger trg_actualizar_estado_prestamo:
     * sincroniza bienes.estado_prestamo cuando cambia el estado del préstamo.
     */
    public function updated(Prestamo $prestamo): void
    {
        if (! $prestamo->isDirty('estado_prestamo')) {
            return;
        }

        $estado = $prestamo->estado_prestamo;

        if ($estado === 'aprobado') {
            Bien::query()
                ->whereKey($prestamo->cve_bien)
                ->update(['estado_prestamo' => 'prestado']);
        } elseif ($estado === 'devuelto') {
            Bien::query()
                ->whereKey($prestamo->cve_bien)
                ->update(['estado_prestamo' => 'disponible']);
        }
    }
}
