<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;


class S3Helper
{


    // Sube a S3 un archivo que subió el usuario, en path.
    public function storeUploadedFile(UploadedFile $uploadedFile, string $path): void
    {
        Storage::disk('s3')->putFileAs(dirname($path), $uploadedFile, basename($path));
    }


    // Descarga url y la sube a S3 como pathWithoutExtension, con la extensión de la url. Devuelve la ruta completa.
    // Pasa por un archivo temporal para no cargar un video entero en memoria. Va la ruta del temporal y no un recurso
    // abierto, porque el cliente HTTP cierra el recurso al terminar la descarga.
    public function storeFromUrl(string $url, string $pathWithoutExtension): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $path = "{$pathWithoutExtension}.{$extension}";

        $temporaryPath = tempnam(sys_get_temp_dir(), 's3-download-');
        try {
            Http::sink($temporaryPath)->get($url)->throw();
            Storage::disk('s3')->putFileAs(dirname($path), $temporaryPath, basename($path));
        } finally {
            unlink($temporaryPath);
        }

        return $path;
    }


    // Enlace temporal para ver el archivo sin credenciales, o null si no hay ruta, por ejemplo cuando la descarga
    // falló. Se firma por 7 días, el máximo de S3, y se guarda 6 en el caché: así cada pantalla recibe el mismo enlace
    // y el navegador reutiliza lo que ya descargó.
    public function getTemporaryUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return Cache::remember(
            "s3_temporary_url:{$path}",
            now()->addDays(6),
            fn (): string => Storage::disk('s3')->temporaryUrl($path, now()->addDays(7)),
        );
    }


    public function getFileContents(string $path): string
    {
        return Storage::disk('s3')->get($path);
    }


    public function delete(string $path): void
    {
        Storage::disk('s3')->delete($path);
    }

}
