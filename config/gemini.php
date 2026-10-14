<?php
// ═══════════════════════════════════════════════════════════════════════════
//  Configuración de la API de Gemini (Google)
//
//  ⚠️  IMPORTANTE: Este archivo contiene la API key. NO debe ser accesible
//      vía web (el .htaccess de esta carpeta lo bloquea con "Deny from all").
//      NO subir este archivo a repositorios públicos con la key real.
//
//  La API key se obtiene gratis en: https://aistudio.google.com/app/apikey
// ═══════════════════════════════════════════════════════════════════════════

return [
    // API key — pega tu key aquí, o defínela como variable de entorno GEMINI_API_KEY
    // (se obtiene gratis sin tarjeta en https://aistudio.google.com/app/apikey)
    'api_key'    => '',

    // Modelo por defecto. Otras opciones gratuitas:
    //   - gemini-2.0-flash        → rápido y eficiente (recomendado por defecto)
    //   - gemini-2.5-flash        → más capaz, mayor latencia
    //   - gemini-1.5-flash        → alternativa estable si falla 2.0
    'modelo'     => 'gemini-2.0-flash',

    // Tokens máximos en la respuesta (~500 palabras = ~700 tokens)
    'max_tokens' => 1024,

    // Timeout en segundos. Gemini Flash responde en <10 s normalmente;
    // 30 s es suficiente (Claude usa 60 s por prompts más largos).
    'timeout'    => 30,
];
