<?php
// ═══════════════════════════════════════════════════════════════════════════
//  Configuración de IA (Anthropic Claude)
// ═══════════════════════════════════════════════════════════════════════════
//
//  ⚠️  IMPORTANTE: Este archivo contiene la API key. NO debe ser accesible
//      vía web (el .htaccess de esta carpeta lo bloquea).
//      NO subir este archivo a repositorios públicos con la key real.
//
//  La API key se obtiene en: https://console.anthropic.com/  →  API Keys
// ═══════════════════════════════════════════════════════════════════════════

return [
    // API key — pega tu key aquí entre las comillas, o defínela como
    // variable de entorno ANTHROPIC_API_KEY (se usará si esta queda vacía).
    'api_key'    => '',

    // Modelo por defecto. Otras opciones:
    //   - claude-haiku-4-5    → rápido y barato (recomendado por defecto)
    //   - claude-sonnet-4-6   → punto medio coste/calidad
    //   - claude-opus-4-7     → el más capaz, más caro
    'modelo'     => 'claude-haiku-4-5',

    // Tokens máximos en la respuesta. Ajustar según tipo de tarea:
    //   - 256   → clasificación / etiquetas cortas
    //   - 1024  → respuestas breves (sugerencias, frases)
    //   - 4096  → textos medianos (biografías, mensajes largos)
    'max_tokens' => 1024,

    // Timeout en segundos para la petición HTTP
    'timeout'    => 60,
];
