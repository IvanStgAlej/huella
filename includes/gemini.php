<?php
declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════
//  Wrapper para llamar a la API de Gemini (Google)
//
//  Uso básico:
//      require_once __DIR__ . '/includes/gemini.php';
//
//      $r = llamarGemini('Redacta una breve biografía de prueba.');
//      if ($r['ok']) echo $r['texto'];
//      else          echo 'Error: ' . $r['error'];
//
//  Con prompt de sistema:
//      $r = llamarGemini($prompt, 'Eres un asistente de memoriales.');
//
//  Devuelve:
//      ['ok' => true,  'texto' => string, 'usage' => array|null]
//      ['ok' => false, 'error' => string, 'codigo' => int|null]
// ═══════════════════════════════════════════════════════════════════════════

function llamarGemini(string $prompt, ?string $sistema = null, array $opciones = []): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/gemini.php';
    }

    $apiKey = $config['api_key'] ?: (getenv('GEMINI_API_KEY') ?: '');
    if ($apiKey === '') {
        return [
            'ok'    => false,
            'error' => 'API key de Gemini no configurada. Edita config/gemini.php o define la variable de entorno GEMINI_API_KEY.',
        ];
    }

    $modelo    = $opciones['modelo']     ?? $config['modelo'];
    $maxTokens = $opciones['max_tokens'] ?? $config['max_tokens'];
    $timeout   = $opciones['timeout']    ?? $config['timeout'];

    $body = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
        ],
    ];

    if ($sistema !== null && $sistema !== '') {
        $body['system_instruction'] = [
            'parts' => [['text' => $sistema]],
        ];
    }

    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        rawurlencode($modelo),
        rawurlencode($apiKey)
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
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
        return ['ok' => false, 'error' => 'Respuesta no válida de la API', 'codigo' => $codigoHttp];
    }

    if ($codigoHttp !== 200) {
        $msg = $datos['error']['message'] ?? ('Error HTTP ' . $codigoHttp);
        return ['ok' => false, 'error' => $msg, 'codigo' => $codigoHttp];
    }

    $candidato  = $datos['candidates'][0] ?? [];
    $finishReason = $candidato['finishReason'] ?? 'UNKNOWN';
    $texto        = $candidato['content']['parts'][0]['text'] ?? '';

    // Gemini devuelve 200 pero con candidato vacío cuando filtra el contenido
    if ($texto === '' && $finishReason !== 'STOP') {
        return [
            'ok'    => false,
            'error' => 'Respuesta bloqueada o vacía (motivo: ' . $finishReason . ')',
            'codigo'=> 200,
        ];
    }

    return [
        'ok'          => true,
        'texto'       => trim($texto),
        'usage'       => $datos['usageMetadata'] ?? null,
        'stop_reason' => $finishReason,
        'modelo'      => $modelo,
    ];
}
