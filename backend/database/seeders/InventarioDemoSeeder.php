<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventarioDemoSeeder extends Seeder
{
    /**
     * Datos demo alineados con el script de presentación del SQL:
     * 100 bienes en las aulas de DSM, préstamos, bitácora, auditorías,
     * resguardos y folios SIIA.
     */
    public function run(): void
    {
        $articulos = DB::table('articulos')->pluck('cve_articulo')->all();
        $modelos = DB::table('modelos')->pluck('cve_modelo')->all();
        $aulas = DB::table('aula')->whereIn('nombre', ['A-101', 'A-102', 'Lab-1', 'Lab-2', 'CC-1', 'CC-2'])
            ->pluck('cve_aula', 'nombre')->all();

        // Distribución de aulas como en el SQL (rn 1-100)
        $aulaPorRango = fn (int $rn) => match (true) {
            $rn <= 17 => $aulas['A-101'],
            $rn <= 34 => $aulas['A-102'],
            $rn <= 51 => $aulas['Lab-1'],
            $rn <= 68 => $aulas['Lab-2'],
            $rn <= 84 => $aulas['CC-1'],
            default => $aulas['CC-2'],
        };

        // Encargados como en el SQL
        $encargadoPorRango = fn (int $rn) => match (true) {
            $rn <= 20 => 1,   // admin
            $rn <= 40 => 2,   // director
            $rn <= 60 => 3,   // profesor
            $rn <= 80 => 8,   // alumno
            default => 16,    // Jorge (DSM)
        };

        $prestadoPorRango = fn (int $rn) => in_array(true, [
            $rn >= 1 && $rn <= 4,
            $rn >= 22 && $rn <= 25,
            $rn >= 41 && $rn <= 44,
            $rn >= 61 && $rn <= 62,
            $rn >= 85 && $rn <= 86,
        ], true);

        $bienes = [];
        for ($rn = 1; $rn <= 100; $rn++) {
            $bienes[] = [
                'nombre' => $this->nombreBien($articulos[$rn - 1]),
                'codigo_barras' => 'BAR-'.str_pad((string) $rn, 3, '0', STR_PAD_LEFT),
                'nfc' => 'NFC-'.str_pad((string) $rn, 3, '0', STR_PAD_LEFT),
                'codigo_qr' => 'QR-'.str_pad((string) $rn, 3, '0', STR_PAD_LEFT),
                'no_serie' => 'SN-'.str_pad((string) $rn, 3, '0', STR_PAD_LEFT).'-'.(($rn * 13) % 999),
                'descripcion' => 'Equipo académico - Carrera DSM',
                'cve_modelo' => $modelos[($rn * 7) % count($modelos)],
                'cve_articulo' => $articulos[$rn - 1],
                'costo_unitario' => 500 + (($rn * 97) % 15000),
                'cve_aula' => $aulaPorRango($rn),
                'cve_encargado' => $encargadoPorRango($rn),
                'estado_fisico' => $rn % 5 === 0 ? 'regular' : 'bueno',
                'estado_prestamo' => $prestadoPorRango($rn) ? 'prestado' : 'disponible',
                'activo' => true,
                'fecha_registro' => now()->toDateString(),
            ];
        }

        foreach ($bienes as $bien) {
            DB::table('bienes')->insert($bien);
        }

        // ----- Préstamos (los bienes "prestado" quedan como aprobados) -----
        $prestamos = [];
        $index = 0;
        foreach ($bienes as $i => $bien) {
            $rn = $i + 1;
            if (! $prestadoPorRango($rn)) {
                continue;
            }
            $index++;
            $solicita = $index % 2 === 0 ? 8 : 3; // alumno / profesor
            $prestamos[] = [
                'cve_bien' => $rn,
                'cve_persona_solicita' => $solicita,
                'fecha_solicitud' => now()->subDays($index),
                'fecha_devolucion_pactada' => now()->addDays(3),
                'estado_prestamo' => 'aprobado',
                'observaciones' => 'Préstamo de práctica',
            ];
        }

        // Un par de préstamos pendientes de aprobación
        $pendientes = DB::table('bienes')->where('estado_prestamo', 'disponible')->orderBy('cve_bien')->limit(2)->pluck('cve_bien');
        foreach ($pendientes as $cveBien) {
            $prestamos[] = [
                'cve_bien' => $cveBien,
                'cve_persona_solicita' => 8,
                'fecha_solicitud' => now()->subDay(),
                'fecha_devolucion_pactada' => now()->addDays(3),
                'estado_prestamo' => 'pendiente',
                'observaciones' => 'Préstamo de práctica',
            ];
        }

        DB::table('prestamos')->insert($prestamos);

        // ----- Bitácora de movimientos (motivo 1 = Revisión de Inventario) -----
        $motivoRevision = DB::table('motivo_de_movimiento')->where('nombre_motivo', 'Reparación')->value('cve_motivo');
        $bitacora = [];
        foreach (DB::table('bienes')->orderBy('cve_bien')->limit(5)->get(['cve_bien', 'cve_encargado']) as $bien) {
            $bitacora[] = [
                'cve_bien' => $bien->cve_bien,
                'cve_motivo' => $motivoRevision,
                'cve_persona_accion' => 1,
                'fecha_movimiento' => now()->subDays(7),
                'observaciones' => 'Revisión semestral aprobada',
            ];
        }
        foreach (DB::table('bienes')->orderBy('cve_bien')->skip(5)->limit(3)->get(['cve_bien', 'cve_encargado']) as $bien) {
            $bitacora[] = [
                'cve_bien' => $bien->cve_bien,
                'cve_motivo' => $motivoRevision,
                'cve_persona_accion' => 3,
                'fecha_movimiento' => now()->subDays(2),
                'observaciones' => 'Se mandó a mantenimiento de rutina',
            ];
        }
        DB::table('bitacora_movimientos')->insert($bitacora);

        // ----- Auditorías -----
        DB::table('auditorias')->insert([
            ['fecha_auditoria' => now()->subDays(30)->toDateString(), 'cve_auditor' => 2, 'observaciones_generales' => 'Auditoría general por cierre de mes'],
            ['fecha_auditoria' => now()->subDays(2)->toDateString(), 'cve_auditor' => 1, 'observaciones_generales' => 'Auditoría aleatoria en Edificio A'],
        ]);

        // ----- Resguardos y folios SIIA (uno por bien) -----
        $anio = now()->year;
        $resguardos = [];
        $folios = [];
        foreach ($bienes as $i => $bien) {
            $cveBien = $i + 1;
            $resguardos[] = [
                'no_resguardo' => 'RGTO-'.str_pad((string) $cveBien, 4, '0', STR_PAD_LEFT).'-'.$anio,
                'cve_persona' => $bien['cve_encargado'],
                'fecha' => now()->subDays($cveBien % 120)->toDateString(),
                'estado' => true,
                'fecha_registro' => now()->toDateString(),
            ];
            $folios[] = [
                'clave_siia' => 'SIIA-'.str_pad((string) $cveBien, 4, '0', STR_PAD_LEFT),
                'cve_persona' => $bien['cve_encargado'],
                'fecha' => now()->subDays($cveBien % 90)->toDateString(),
                'estado' => true,
            ];
        }
        DB::table('numero_resguardo')->insert($resguardos);
        DB::table('folio_siia')->insert($folios);
    }

    private function nombreBien(int $cveArticulo): string
    {
        $nombre = DB::table('articulos')->where('cve_articulo', $cveArticulo)->value('nombre');

        return mb_substr((string) $nombre, 0, 100);
    }
}
