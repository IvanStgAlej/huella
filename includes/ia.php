<?php
declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════
//  Wrapper para llamar a la API de Claude (Anthropic)
//
//  Uso básico:
//      require_once __DIR__ . '/includes/ia.php';
//
//      $r = llamarIA('Sugiere 3 frases de condolencia breves');
//      if ($r['ok']) echo $r['texto'];
//      else          echo 'Error: ' . $r['error'];
//
//  Con prompt del sistema (se cachea automáticamente → ~90% más barato si
//  se reutiliza el mismo sistema en varias llamadas):
//      $r = llamarIA(
//          'Escribe un mensaje empático y respetuoso para Galileo Galilei',
//          'Eres un asistente que redacta mensajes para memoriales digitales.'
//      );
//
//  Opciones extra (todas opcionales):
//      llamarIA($prompt, $sistema, [
//          'modelo'     => 'claude-sonnet-4-6',  // override del modelo
//          'max_tokens' => 2048,                  // override de tokens máx
//      ]);
//
//  Devuelve un array:
//      [
//          'ok'          => true|false,
//          'texto'       => string,        // texto de la respuesta (si ok)
//          'usage'       => array,         // tokens usados (si ok)
//          'stop_reason' => string,        // por qué paró (si ok)
//          'error'       => string,        // mensaje de error (si !ok)
//          'codigo'      => int,           // código HTTP (si !ok)
//      ]
// ═══════════════════════════════════════════════════════════════════════════

function llamarIA(string $prompt, ?string $sistema = null, array $opciones = []): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/ia.php';
    }

    // API key: primero del archivo, si está vacía intenta la variable de entorno
    $apiKey = $config['api_key'] ?: (getenv('ANTHROPIC_API_KEY') ?: '');
    if ($apiKey === '') {
        return [
            'ok'    => false,
            'error' => 'API key no configurada. Edita config/ia.php o define la variable de entorno ANTHROPIC_API_KEY.',
        ];
    }

    $modelo    = $opciones['modelo']     ?? $config['modelo'];
    $maxTokens = $opciones['max_tokens'] ?? $config['max_tokens'];
    $timeout   = $opciones['timeout']    ?? $config['timeout'];

    // ── Construir el cuerpo de la petición ────────────────────────────────
    $body = [
        'model'      => $modelo,
        'max_tokens' => $maxTokens,
        'messages'   => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ];

    // System prompt con cache_control automático.
    // El cache abarata ~90% las llamadas posteriores con el mismo system prompt
    // (el sistema debe tener cierto tamaño mínimo para que el cache se active).
    if ($sistema !== null && $sistema !== '') {
        $body['system'] = [
            [
                'type'          => 'text',
                'text'          => $sistema,
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];
    }

    // ── Petición HTTP a la API de Anthropic ──────────────────────────────
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $respuestaCruda = curl_exec($ch);
    $codigoHttp     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl      = curl_error($ch);
    curl_close($ch);

    if ($respuestaCruda === false) {
        return [
            'ok'    => false,
            'error' => 'Error de conexión: ' . $errorCurl,
        ];
    }

    $datos = json_decode($respuestaCruda, true);
    if (!is_array($datos)) {
        return [
            'ok'    => false,
            'error' => 'Respuesta no válida de la API',
            'codigo'=> $codigoHttp,
        ];
    }

    // ── Error de la API (400, 401, 429, 500, etc.) ───────────────────────
    if ($codigoHttp !== 200) {
        return [
            'ok'     => false,
            'error'  => $datos['error']['message'] ?? ('Error HTTP ' . $codigoHttp),
            'codigo' => $codigoHttp,
            'tipo'   => $datos['error']['type'] ?? null,
        ];
    }

    // ── Éxito: concatenar todos los bloques de texto de la respuesta ─────
    $texto = '';
    foreach ($datos['content'] ?? [] as $bloque) {
        if (($bloque['type'] ?? '') === 'text') {
            $texto .= $bloque['text'];
        }
    }

    return [
        'ok'          => true,
        'texto'       => trim($texto),
        'usage'       => $datos['usage']       ?? null,
        'stop_reason' => $datos['stop_reason'] ?? null,
        'modelo'      => $datos['model']       ?? $modelo,
    ];
}
