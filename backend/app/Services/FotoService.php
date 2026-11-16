<?php

namespace App\Services;

use App\Models\Bien;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Masbug\Flysystem\GoogleDriveAdapter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fotos de los bienes.
 *
 * Las fotos se guardan en el disco configurado en config/services.php
 * (clave 'fotos'): 'local' para dev/tests y 'google' (Google Drive)
 * para producción. La ruta del archivo queda en bienes.foto_drive_id
 * y en bienes.foto_url se guarda el enlace público cuando el disco
 * lo permite (Google Drive); la PWA consume la foto vía el endpoint
 * proxy GET /api/bienes/{bien}/foto, que respeta la autorización.
 */
class FotoService
{
    /** Extensiones permitidas (coinciden con la validación del request). */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];

    /** Peso máximo de la foto (5 MB). */
    public const MAX_KILOBYTES = 5120;

    /**
     * Sube (o reemplaza) la foto del bien y actualiza sus columnas.
     */
    public function subir(Bien $bien, UploadedFile $foto): Bien
    {
        $disco = $this->disco();
        $extension = Str::lower($foto->getClientOriginalExtension() ?: 'jpg');
        // Nombre único por versión para evitar duplicados por nombre en Drive:
        // al reemplazar se genera una ruta distinta y la anterior se borra.
        $ruta = $this->carpeta().'/foto-'.$bien->getKey().'-'.now()->format('YmdHisu').'.'.$extension;

        $this->borrarArchivo($bien->foto_drive_id);

        $stream = fopen($foto->getRealPath(), 'rb');
        try {
            $disco->writeStream($ruta, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $bien->foto_drive_id = $ruta;
        $bien->foto_url = $this->urlPublica($disco, $ruta);
        $bien->save();

        return $bien;
    }

    /**
     * Elimina la foto del bien (archivo y referencias).
     */
    public function eliminar(Bien $bien): Bien
    {
        $this->borrarArchivo($bien->foto_drive_id);

        $bien->foto_drive_id = null;
        $bien->foto_url = null;
        $bien->save();

        return $bien;
    }

    /**
     * Borra solo el archivo (se usa al purgar el bien, cuando la fila
     * del bien ya no existe).
     */
    public function borrarArchivo(?string $ruta): bool
    {
        if ($ruta === null || $ruta === '') {
            return false;
        }

        $disco = $this->disco();

        if (! $disco->exists($ruta)) {
            return false;
        }

        return $disco->delete($ruta);
    }

    /**
     * Respuesta streaming de la foto del bien para el endpoint proxy.
     */
    public function respuesta(Bien $bien): StreamedResponse
    {
        $disco = $this->disco();
        $ruta = $bien->foto_drive_id;

        if ($ruta === null || ! $disco->exists($ruta)) {
            abort(404, 'El bien no tiene foto.');
        }

        $tipo = $disco->mimeType($ruta);
        $stream = $disco->readStream($ruta);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $tipo,
            'Content-Disposition' => 'inline; filename="'.basename($ruta).'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function disco(): Filesystem
    {
        return Storage::disk(config('services.fotos.disk', 'local'));
    }

    private function carpeta(): string
    {
        return trim((string) config('services.fotos.carpeta', 'bienes'), '/');
    }

    /**
     * Enlace público de Drive (si el disco lo permite); con disco local
     * no hay URL pública y queda null (la PWA usa el endpoint proxy).
     */
    private function urlPublica(Filesystem $disco, string $ruta): ?string
    {
        try {
            $adapter = $disco->getAdapter();

            if ($adapter instanceof GoogleDriveAdapter) {
                return $adapter->getUrl($ruta);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
