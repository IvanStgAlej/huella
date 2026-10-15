<?php

/**
 * Sube un archivo de media de un post y devuelve [carpeta, nombre_archivo].
 * Crea automáticamente la subcarpeta uploads/posts/{huella}/{post}/{tipo}/{año}/{mes}/{dia}/
 *
 * @param array  $file      Entrada de $_FILES['campo']
 * @param string $idHuella
 * @param string $idPost
 * @param string $tipo      'img' | 'video' | 'audio'
 * @return array{carpeta: string, nombre: string}
 * @throws RuntimeException si el tipo de archivo no está permitido o falla la subida
 */
function subirMediaPost(array $file, string $idHuella, string $idPost, string $tipo): array {
    $extensionesPermitidas = [
        'img'   => ['jpg','jpeg','png','gif','webp'],
        'video' => ['mp4','webm','mov'],
        'audio' => ['mp3','ogg','wav','m4a'],
    ];

    if (!isset($extensionesPermitidas[$tipo])) {
        throw new RuntimeException("Tipo de media no válido: $tipo");
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error en la subida del archivo (código {$file['error']})");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extensionesPermitidas[$tipo], true)) {
        throw new RuntimeException("Extensión .$ext no permitida para $tipo");
    }

    // Construcción de la ruta con fecha
    $hoy    = new DateTimeImmutable();
    $anio   = $hoy->format('Y');
    $mes    = $hoy->format('m');
    $dia    = $hoy->format('d');

    $carpetaRelativa = "uploads/posts/{$idHuella}/{$idPost}/{$tipo}/{$anio}/{$mes}/{$dia}/";
    $carpetaAbsoluta = __DIR__ . '/../public/' . $carpetaRelativa;

    if (!is_dir($carpetaAbsoluta) && !mkdir($carpetaAbsoluta, 0755, true)) {
        throw new RuntimeException("No se pudo crear la carpeta de destino");
    }

    // Nombre único para evitar colisiones
    $nombreArchivo = uniqid('', true) . '.' . $ext;
    $destino       = $carpetaAbsoluta . $nombreArchivo;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new RuntimeException("No se pudo mover el archivo al destino");
    }

    return [
        'carpeta' => $carpetaRelativa,
        'nombre'  => $nombreArchivo,
    ];
}

/**
 * Devuelve la URL pública completa de un archivo de media.
 */
function urlMedia(string $carpeta, string $nombre): string {
    return '/' . ltrim($carpeta, '/') . $nombre;
}
