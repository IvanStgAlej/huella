<?php
// ═══════════════════════════════════════════════════════════════════════════
//  Configuración de la API de Groq (transcripción Whisper)
//
//  PLANTILLA: cópiala como config/groq.php y pon tu key (o usa GROQ_API_KEY).
//  config/groq.php está en .gitignore. NO debe ser accesible
//      vía web (el .htaccess de esta carpeta lo bloquea con "Deny from all").
//      NO subir este archivo a repositorios públicos con la key real.
//
//  La API key se obtiene GRATIS sin tarjeta en: https://console.groq.com/keys
//  Free tier (mayo 2026): 7.200 transcripciones / día con whisper-large-v3-turbo.
// ═══════════════════════════════════════════════════════════════════════════

return [
    // API key — pega tu key aquí, o defínela como variable de entorno GROQ_API_KEY
    'api_key'    => '',

    // Modelo de transcripción. Opciones:
    //   - whisper-large-v3-turbo   → rápido y preciso (recomendado)
    //   - whisper-large-v3         → ligeramente más preciso, más lento
    //   - distil-whisper-large-v3-en → solo inglés, el más rápido
    'modelo'     => 'whisper-large-v3-turbo',

    // Modelo de generación de texto (chat). Opciones gratuitas (mayo 2026):
    //   - llama-3.3-70b-versatile  → el mejor para español, recomendado
    //   - llama-3.1-8b-instant     → más rápido, menos pulido
    //   - mixtral-8x7b-32768       → buen rendimiento, contexto largo
    'modelo_chat' => 'llama-3.3-70b-versatile',
    'max_tokens'  => 1024,
    'temperature' => 0.7,

    // Timeout en segundos. Whisper de Groq es muy rápido (<5 s para <1 min de audio).
    'timeout'    => 30,

    // Tamaño máximo de audio aceptado (en bytes). Groq permite hasta 25 MB.
    // 10 MB es suficiente para ~10 min de audio webm/opus.
    'max_size'   => 10 * 1024 * 1024,
];
