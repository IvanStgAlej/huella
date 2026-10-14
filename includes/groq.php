<?php
declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════
//  Wrapper para transcripción de audio con la API de Groq (Whisper)
//
//  Uso:
//      require_once __DIR__ . '/includes/groq.php';
//
//      $r = transcribirAudio('/ruta/al/audio.webm', 'es');
//      if ($r['ok']) echo $r['texto'];
//      else          echo 'Error: ' . $r['error'];
//
//  Devuelve:
//      ['ok' => true,  'texto' => string]
//      ['ok' => false, 'error' => string, 'codigo' => int|null]
// ═══════════════════════════════════════════════════════════════════════════

// config/groq.php (con la key) no se sube al repo; si no existe se usa la plantilla
// y la key se lee de la variable de entorno GROQ_API_KEY.
function cargarConfigGroq(): array {
    $local = __DIR__ . '/../config/groq.php';
    return require (file_exists($local) ? $local : __DIR__ . '/../config/groq.example.php');
}

function transcribirAudio(string $rutaArchivo, string $idiomaISO = 'es'): array {
    static $config = null;
    if ($config === null) {
        $config = cargarConfigGroq();
    }

    $apiKey = $config['api_key'] ?: (getenv('GROQ_API_KEY') ?: '');
    if ($apiKey === '') {
        return [
            'ok'    => false,
            'error' => 'API key de Groq no configurada. Edita config/groq.php o define la variable de entorno GROQ_API_KEY.',
        ];
    }

    if (!is_file($rutaArchivo) || !is_readable($rutaArchivo)) {
        return ['ok' => false, 'error' => 'Archivo de audio no accesible'];
    }

    $tamano = filesize($rutaArchivo);
    if ($tamano === false || $tamano === 0) {
        return ['ok' => false, 'error' => 'Archivo de audio vacío'];
    }
    if ($tamano > $config['max_size']) {
        return ['ok' => false, 'error' => 'Audio demasiado grande (máx. ' . round($config['max_size'] / 1024 / 1024) . ' MB)'];
    }

    $modelo  = $config['modelo'];
    $timeout = $config['timeout'];

    // Detectar mime para CURLFile (Groq usa la extensión del nombre del archivo,
    // así que el nombre que mandamos debe acabar en una extensión que Groq acepte).
    $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));
    $aceptadas = ['flac','mp3','mp4','mpeg','mpga','m4a','ogg','wav','webm'];
    if (!in_array($extension, $aceptadas, true)) {
        $extension = 'webm';
    }
    $nombreEnvio = 'audio.' . $extension;

    $mime  = mime_content_type($rutaArchivo) ?: 'audio/' . $extension;
    $file  = new CURLFile($rutaArchivo, $mime, $nombreEnvio);

    $post = [
        'file'            => $file,
        'model'           => $modelo,
        'language'        => $idiomaISO,
        'response_format' => 'json',
        'temperature'     => '0',
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $respuestaCruda = curl_exec($ch);
    $codigoHttp     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl      = curl_error($ch);
    curl_close($ch);

    if ($respuestaCruda === false) {
        return ['ok' => false, 'error' => 'Error de conexión cURL: ' . $errorCurl];
    }

    $datos = json_decode($respuestaCruda, true);
    if (!is_array($datos)) {
        return ['ok' => false, 'error' => 'Respuesta no válida de Groq', 'codigo' => $codigoHttp];
    }

    if ($codigoHttp !== 200) {
        $msg = $datos['error']['message'] ?? ('Error HTTP ' . $codigoHttp);
        return ['ok' => false, 'error' => $msg, 'codigo' => $codigoHttp];
    }

    $texto = trim((string)($datos['text'] ?? ''));
    if ($texto === '') {
        return ['ok' => false, 'error' => 'No se ha detectado voz en el audio.'];
    }

    return ['ok' => true, 'texto' => $texto];
}

// ═══════════════════════════════════════════════════════════════════════════
//  Generación de texto con Groq (Llama, Mixtral…)
//
//  Uso:
//      $r = llamarGroq('Redacta una biografía…', 'Eres un asistente…');
//      if ($r['ok']) echo $r['texto'];
//      else          echo 'Error: ' . $r['error'];
// ═══════════════════════════════════════════════════════════════════════════

function llamarGroq(string $prompt, ?string $sistema = null, array $opciones = []): array {
    static $config = null;
    if ($config === null) {
        $config = cargarConfigGroq();
    }

    $apiKey = $config['api_key'] ?: (getenv('GROQ_API_KEY') ?: '');
    if ($apiKey === '') {
        return [
            'ok'    => false,
            'error' => 'API key de Groq no configurada. Edita config/groq.php o define la variable de entorno GROQ_API_KEY.',
        ];
    }

    $modelo      = $opciones['modelo']      ?? ($config['modelo_chat'] ?? 'llama-3.3-70b-versatile');
    $maxTokens   = $opciones['max_tokens']  ?? ($config['max_tokens']  ?? 1024);
    $temperatura = $opciones['temperature'] ?? ($config['temperature'] ?? 0.7);
    $timeout     = $opciones['timeout']     ?? ($config['timeout']     ?? 30);

    $messages = [];
    if ($sistema !== null && $sistema !== '') {
        $messages[] = ['role' => 'system', 'content' => $sistema];
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $body = [
        'model'       => $modelo,
        'messages'    => $messages,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperatura,
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $respuestaCruda = curl_exec($ch);
    $codigoHttp     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl      = curl_error($ch);
    curl_close($ch);

    if ($respuestaCruda === false) {
        return ['ok' => false, 'error' => 'Error de conexión cURL: ' . $errorCurl];
    }

    $datos = json_decode($respuestaCruda, true);
    if (!is_array($datos)) {
        return ['ok' => false, 'error' => 'Respuesta no válida de Groq', 'codigo' => $codigoHttp];
    }

    if ($codigoHttp !== 200) {
        $msg = $datos['error']['message'] ?? ('Error HTTP ' . $codigoHttp);
        return ['ok' => false, 'error' => $msg, 'codigo' => $codigoHttp];
    }

    $texto = trim((string)($datos['choices'][0]['message']['content'] ?? ''));
    if ($texto === '') {
        return ['ok' => false, 'error' => 'Respuesta vacía de Groq', 'codigo' => $codigoHttp];
    }

    return [
        'ok'          => true,
        'texto'       => $texto,
        'usage'       => $datos['usage']                      ?? null,
        'stop_reason' => $datos['choices'][0]['finish_reason'] ?? null,
        'modelo'      => $datos['model']                      ?? $modelo,
    ];
}
