<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/catalogos.php');

        // ----- Catálogos base -----
        DB::table('roles')->insert($data['roles']);

        foreach ($data['tipos_bien'] as $row) {
            DB::table('tipos_bien')->insert($row);
        }

        foreach ($data['area'] as $row) {
            DB::table('area')->insert($row);
        }

        foreach ($data['tipo_profesor'] as $row) {
            DB::table('tipo_profesor')->insert($row);
        }

        foreach ($data['motivo_de_movimiento'] as $row) {
            DB::table('motivo_de_movimiento')->insert($row);
        }

        // Motivo adicional del backend (no existe en el dump): restaurar
        // bienes desde la papelera registra su movimiento en bitácora.
        DB::table('motivo_de_movimiento')->insert([
            'nombre_motivo' => 'Restauración',
            'requiere_aprobacion' => false,
        ]);

        foreach ($data['edificio'] as $row) {
            DB::table('edificio')->insert($row);
        }

        foreach ($data['tipo_aula'] as $row) {
            DB::table('tipo_aula')->insert($row);
        }

        foreach ($data['adscripcion'] as $row) {
            DB::table('adscripcion')->insert($row);
        }

        foreach ($data['marcas'] as $row) {
            DB::table('marcas')->insert($row);
        }

        foreach ($data['persona'] as $row) {
            DB::table('persona')->insert($row);
        }

        foreach ($data['usuarios'] as $row) {
            DB::table('usuarios')->insert($row);
        }

        // ----- Familias (resuelven subselect sobre tipos_bien) -----
        foreach ($data['familias_articulos'] as $row) {
            $row['cve_tipo'] = $this->resolverLookup($row['cve_tipo']);
            DB::table('familias_articulos')->insert($row);
        }

        // ----- Artículos (resuelven subselect sobre familias) -----
        foreach ($data['articulos'] as $row) {
            $row['cve_familia'] = $this->resolverLookup($row['cve_familia']);
            DB::table('articulos')->insert($row);
        }

        // ----- Modelos -----
        foreach ($data['modelos'] as $row) {
            DB::table('modelos')->insert($row);
        }

        // ----- Aulas (resuelven subselect sobre adscripcion) -----
        foreach ($data['aula'] as $row) {
            $row['cve_adscripcion'] = $this->resolverLookup($row['cve_adscripcion']);
            DB::table('aula')->insert($row);
        }

        // ----- Profesores (equivalente al INSERT SELECT del SQL) -----
        $profesores = [];
        foreach (range(1, 5) as $cvePersona) {
            $profesores[] = ['cve_persona' => $cvePersona, 'cve_tipo_profesor' => 1, 'cve_area' => 1, 'activo' => true];
        }
        foreach (range(16, 20) as $cvePersona) {
            $profesores[] = ['cve_persona' => $cvePersona, 'cve_tipo_profesor' => 1, 'cve_area' => 1, 'activo' => true];
        }
        DB::table('profesor')->insert($profesores);

        // ----- Alumnos (personas 6-15 y 21-25, matrícula MAT-XXXX) -----
        $alumnos = [];
        foreach (array_merge(range(6, 15), range(21, 25)) as $cvePersona) {
            $alumnos[] = [
                'cve_persona' => $cvePersona,
                'matricula' => 'MAT-'.str_pad((string) $cvePersona, 4, '0', STR_PAD_LEFT),
                'fecha_inscrito' => now()->toDateString(),
                'inscrito' => true,
                'activo' => true,
            ];
        }
        DB::table('alumnos')->insert($alumnos);

        // ----- Adscripción-Persona (vinculaciones del SQL) -----
        $vinculos = [
            '73113100' => ['Juan Pérez', 'María López', 'Carlos Sánchez', 'Sofía Hernández', 'Jorge Rivas', 'Andrés García'],
            '73112100' => ['Ana Torres', 'Laura Mendoza', 'Ricardo Flores', 'Brenda Ramírez'],
            '73112200' => ['Luis Ramírez', 'Miguel Ángel', 'Verónica Lozano', 'Sara Navarro'],
            '73112500' => ['Elena Gómez', 'Lucía Castro', 'Diana Vargas', 'Carmen Ortega', 'Iván Guerrero'],
            '73112400' => ['Roberto Díaz', 'Fernando Méndez'],
            '73112300' => ['Diego Ruiz', 'Héctor Cruz'],
            '73113000' => ['Oscar Ortega'],
            '73114100' => ['Patricia Morales', 'Rosa María Delgado'],
            '73190000' => ['Gabriel Reyes'],
        ];

        $personas = DB::table('persona')->get(['cve_persona', 'nombre', 'apellido_paterno'])
            ->keyBy(fn ($p) => $p->nombre.' '.$p->apellido_paterno);
        $adscripciones = DB::table('adscripcion')->pluck('cve_adscripcion', 'clave_adscripcion');

        $adscripcionPersona = [];
        foreach ($vinculos as $claveAdscripcion => $nombres) {
            $cveAdscripcion = $adscripciones[$claveAdscripcion];
            foreach ($nombres as $nombreCompleto) {
                $persona = $personas[$nombreCompleto] ?? null;
                if (! $persona) {
                    continue;
                }
                $adscripcionPersona[] = [
                    'cve_adscripcion' => $cveAdscripcion,
                    'cve_persona' => $persona->cve_persona,
                    'activo' => true,
                    'fecha_registro' => now(),
                ];
            }
        }
        DB::table('adscripcion_persona')->insert($adscripcionPersona);
    }

    private function resolverLookup(mixed $valor): mixed
    {
        if (! is_array($valor) || ! isset($valor['__raw'])) {
            return $valor;
        }

        $raw = $valor['__raw'];

        if (preg_match("/FROM\\s+tipos_bien\\s+WHERE\\s+clave\\s+=\\s+'(\\w+)'/", $raw, $m)) {
            return DB::table('tipos_bien')->where('clave', $m[1])->value('cve_tipo');
        }

        if (preg_match("/FROM\\s+familias_articulos\\s+WHERE\\s+clave\\s+=\\s+'(\\w+)'/", $raw, $m)) {
            return DB::table('familias_articulos')->where('clave', $m[1])->value('cve_familia');
        }

        if (preg_match("/FROM\\s+adscripcion\\s+WHERE\\s+clave_adscripcion\\s+=\\s+'(\\d+)'/", $raw, $m)) {
            return DB::table('adscripcion')->where('clave_adscripcion', $m[1])->value('cve_adscripcion');
        }

        return null;
    }
}
