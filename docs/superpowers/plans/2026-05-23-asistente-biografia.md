# Asistente de Biografía con IA — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir un asistente de IA en el panel de administración de la huella que genera la biografía del fallecido a partir de 5 preguntas tipo Instagram Stories respondidas por texto o voz, usando la API gratuita de Gemini.

**Architecture:** Nueva página `bio_asistente.php` con UI tipo Stories (una pregunta a la vez, barra segmentada, fondo con color primario del tema del cliente). La generación ocurre vía `bio_asistente_api.php` que llama a Gemini. El resultado se muestra para revisión antes de guardar en `h_huellafallecido.Fallecido_Texto`. El audio usa la Web Speech API del navegador (transcripción en tiempo real, sin coste).

**Tech Stack:** PHP 8 puro · MySQL/mysqli · JavaScript vanilla · Web Speech API · Gemini REST API (cURL) · CSS variables (tema del cliente)

---

## Mapa de archivos

| Acción | Archivo | Responsabilidad |
|---|---|---|
| CREAR | `config/gemini.php` | API key + modelo + opciones de Gemini |
| CREAR | `includes/gemini.php` | Wrapper `llamarGemini()` — cURL a la API de Gemini |
| CREAR | `bio_asistente_api.php` | API interna: `action=generar` y `action=guardar` |
| CREAR | `assets/css/bio_asistente.css` | Estilos Stories (usa CSS vars del tema, sin colores hardcoded) |
| CREAR | `bio_asistente.php` | Página principal: pasos 1-5 Stories + spinner + resultado |
| EDITAR | `.htaccess` | Regla `/bio-asistente` |
| EDITAR | `completar.php` | Botón "✨ Asistente" en bloque Biografía + toast `bio_ok` |
| EDITAR | `assets/css/completar.css` | Estilos del botón `.btn-asistente-bio` |

---

## Task 1: Routing + config Gemini

**Files:**
- Modify: `.htaccess`
- Create: `config/gemini.php`

- [ ] **Step 1.1 — Añadir regla de URL en `.htaccess`**

Editar `C:/xampp/htdocs/huella/.htaccess`. Añadir la nueva regla ANTES de la regla genérica de slug (la última línea):

```apache
Options -Indexes
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# /huella/{slug}/actos
RewriteRule ^([a-zA-Z0-9_-]+)/actos/?$           actos.php?link=$1           [QSA,L]

# /huella/{slug}/muro
RewriteRule ^([a-zA-Z0-9_-]+)/muro/?$            muro.php?link=$1            [QSA,L]

# /huella/{slug}/completar
RewriteRule ^([a-zA-Z0-9_-]+)/completar/?$       completar.php?link=$1       [QSA,L]

# /huella/{slug}/bio-asistente
RewriteRule ^([a-zA-Z0-9_-]+)/bio-asistente/?$   bio_asistente.php?link=$1   [QSA,L]

# /huella/politicas-de-privacidad
RewriteRule ^politicas-de-privacidad/?$           politicas.php               [QSA,L]

# /huella/{slug}  (página de inicio del fallecido)
RewriteRule ^([a-zA-Z0-9_-]+)/?$                 index.php?link=$1           [QSA,L]
```

- [ ] **Step 1.2 — Crear `config/gemini.php`**

Crear `C:/xampp/htdocs/huella/config/gemini.php`:

```php
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
    'api_key'    => '',

    // Modelo gratuito recomendado
    'modelo'     => 'gemini-2.0-flash',

    // Tokens máximos en la respuesta (~500 palabras = ~700 tokens)
    'max_tokens' => 1024,

    // Timeout en segundos para la petición HTTP
    'timeout'    => 30,
];
```

- [ ] **Step 1.3 — Verificar**

Abrir en el navegador: `http://localhost/huella/lanzador.php`  
Abre una huella de prueba, p. ej. `http://localhost/huella/{slug}/bio-asistente`.  
Resultado esperado: error PHP "No such file or directory" para `bio_asistente.php` (la ruta funciona, el archivo no existe todavía). Cualquier otro error indica que `.htaccess` no está bien.

---

## Task 2: Wrapper Gemini

**Files:**
- Create: `includes/gemini.php`

- [ ] **Step 2.1 — Crear `includes/gemini.php`**

Crear `C:/xampp/htdocs/huella/includes/gemini.php`:

```php
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

    $texto = $datos['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return [
        'ok'    => true,
        'texto' => trim($texto),
        'usage' => $datos['usageMetadata'] ?? null,
    ];
}
```

- [ ] **Step 2.2 — Verificar el wrapper con un script temporal**

Crear `C:/xampp/htdocs/huella/__setup/test_gemini.php` (solo para prueba, se borra después):

```php
<?php
require_once __DIR__ . '/../includes/gemini.php';
$r = llamarGemini('Di "hola" en castellano en una sola palabra.');
echo '<pre>' . print_r($r, true) . '</pre>';
```

Abrir: `http://localhost/huella/__setup/test_gemini.php`

Resultado esperado si la API key está vacía:
```
Array ( [ok] => [error] => API key de Gemini no configurada... )
```

Resultado esperado si la API key está configurada en `config/gemini.php`:
```
Array ( [ok] => 1 [texto] => Hola [usage] => Array(...) )
```

- [ ] **Step 2.3 — Borrar el script temporal**

Borrar `C:/xampp/htdocs/huella/__setup/test_gemini.php`.

---

## Task 3: API interna `bio_asistente_api.php`

**Files:**
- Create: `bio_asistente_api.php`

- [ ] **Step 3.1 — Crear `bio_asistente_api.php`**

Crear `C:/xampp/htdocs/huella/bio_asistente_api.php`:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/gemini.php';

header('Content-Type: application/json; charset=utf-8');

// ── Autenticación ──────────────────────────────────────────────────────────
if (!sesionActiva()) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$action   = $_POST['action']   ?? '';
$idHuella = trim($_POST['id_huella'] ?? '');

if ($idHuella === '') {
    echo json_encode(['ok' => false, 'error' => 'Huella no especificada']);
    exit;
}

if (!esAdminHuella($idHuella)) {
    echo json_encode(['ok' => false, 'error' => 'Sin permisos de administrador para esta huella']);
    exit;
}

// ── action=generar ────────────────────────────────────────────────────────
if ($action === 'generar') {
    $idioma = trim($_POST['idioma'] ?? 'CAS');
    $r = [
        trim($_POST['r1'] ?? ''),
        trim($_POST['r2'] ?? ''),
        trim($_POST['r3'] ?? ''),
        trim($_POST['r4'] ?? ''),
        trim($_POST['r5'] ?? ''),
    ];

    // Cargar datos del fallecido
    $db = getDB();
    $st = $db->prepare("
        SELECT f.Fallecido_Nombre, f.Fallecido_Apellido_1, f.Fallecido_Apellido_2,
               f.Fallecido_Fecha_Nacimiento, f.`Fallecido_Fecha_Defunción`
        FROM h_huella h
        LEFT JOIN h_huellafallecido f ON f.Id_Huella = h.Id_Huella
        WHERE h.Id_Huella = ?
    ");
    $st->bind_param('s', $idHuella);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Huella no encontrada']);
        exit;
    }

    $nombre = trim(
        ($row['Fallecido_Nombre']     ?? '') . ' ' .
        ($row['Fallecido_Apellido_1'] ?? '') . ' ' .
        ($row['Fallecido_Apellido_2'] ?? '')
    ) ?: 'el fallecido';

    $fechaNac = $row['Fallecido_Fecha_Nacimiento']  ?? null;
    $fechaDef = $row['Fallecido_Fecha_Defunción']   ?? null;

    $nombresIdioma = [
        'CAS' => 'castellano',
        'CAT' => 'catalán',
        'ENG' => 'inglés',
        'EUS' => 'euskera',
    ];
    $idiomaTexto = $nombresIdioma[$idioma] ?? 'castellano';

    $labels = [
        'Profesión o dedicación',
        'Hobbies y aficiones',
        'Personalidad y carácter',
        'Lo más importante para él/ella',
        'Recuerdo especial o anécdota',
    ];

    $infoLineas = '';
    foreach ($r as $i => $resp) {
        $val = $resp !== '' ? $resp : 'No indicado';
        $infoLineas .= "- {$labels[$i]}: {$val}\n";
    }

    $sistema = "Eres un asistente que redacta biografías para memoriales digitales. "
             . "Tu tono es cálido, respetuoso y personal. Escribes en tercera persona. "
             . "No uses markdown, ni títulos, ni viñetas: solo texto continuo en párrafos. "
             . "Máximo 500 palabras.";

    $prompt = "Redacta la biografía de {$nombre}";
    if ($fechaNac) $prompt .= ", nacido el {$fechaNac}";
    if ($fechaDef) $prompt .= " y fallecido el {$fechaDef}";
    $prompt .= ".\nResponde en {$idiomaTexto}.\n\n";
    $prompt .= "Información proporcionada por la familia:\n{$infoLineas}";

    $resultado = llamarGemini($prompt, $sistema, ['max_tokens' => 1024]);
    echo json_encode($resultado);
    exit;
}

// ── action=guardar ────────────────────────────────────────────────────────
if ($action === 'guardar') {
    $texto = trim($_POST['texto'] ?? '');

    $db = getDB();
    $st = $db->prepare("UPDATE h_huellafallecido SET Fallecido_Texto=? WHERE Id_Huella=?");
    $st->bind_param('ss', $texto, $idHuella);
    $ok = $st->execute();
    $st->close();

    echo json_encode([
        'ok'    => $ok,
        'error' => $ok ? null : 'Error al guardar en la base de datos',
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción desconocida: ' . htmlspecialchars($action)]);
```

- [ ] **Step 3.2 — Verificar la API**

Con sesión de admin activa, abrir la consola del navegador en cualquier página de la huella y ejecutar:

```javascript
fetch('/huella/bio_asistente_api.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
  body: new URLSearchParams({
    action: 'generar',
    id_huella: 'XXXXXXXXXXXXXXXXX', // reemplaza con un Id_Huella real
    idioma: 'CAS',
    r1: 'Maestro de escuela',
    r2: 'Le gustaba la pesca y el ajedrez',
    r3: 'Tranquilo y reflexivo',
    r4: 'Sus estudiantes y su familia',
    r5: 'Cada año organizaba una excursión de fin de curso'
  })
}).then(r => r.json()).then(console.log);
```

Resultado esperado (con API key configurada): `{ok: true, texto: "..."}`  
Resultado esperado (sin API key): `{ok: false, error: "API key de Gemini no configurada..."}`

---

## Task 4: CSS del asistente

**Files:**
- Create: `assets/css/bio_asistente.css`
- Modify: `assets/css/completar.css`

- [ ] **Step 4.1 — Crear `assets/css/bio_asistente.css`**

Crear `C:/xampp/htdocs/huella/assets/css/bio_asistente.css`:

```css
/* ================================================
   BIO ASISTENTE — Formato Instagram Stories
   Todos los colores usan variables CSS del tema
   ================================================ */

/* ── LAYOUT GLOBAL ── */
#app-stories,
#app-generando {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    max-width: 480px;
    margin: 0 auto;
    background: var(--color-primario);
}

/* ── BARRA DE PROGRESO ── */
.stories-progress {
    display: flex;
    gap: 3px;
    padding: 0.65rem 0.9rem 0.35rem;
    flex-shrink: 0;
}

.stories-progress__seg {
    flex: 1;
    height: 3px;
    border-radius: 2px;
    background: rgba(255,255,255,0.25);
    position: relative;
    overflow: hidden;
}

.stories-progress__seg--done {
    background: rgba(255,255,255,1);
}

.stories-progress__seg--active::after {
    content: '';
    position: absolute;
    inset: 0;
    background: #fff;
    animation: seg-fill 0.25s ease forwards;
}

@keyframes seg-fill {
    from { width: 0; }
    to   { width: 100%; }
}

/* ── HEADER DE STORIES ── */
.stories-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.2rem 0.9rem 0;
    flex-shrink: 0;
}

.stories-header__label {
    color: rgba(255,255,255,0.8);
    font-size: 0.72rem;
    font-weight: 600;
}

.stories-header__idioma {
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 14px;
    color: #fff;
    font-size: 0.68rem;
    padding: 0.2rem 0.5rem;
    cursor: pointer;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
}

/* ── ZONA DE PREGUNTA ── */
.stories-question {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.25rem 1.5rem 0.75rem;
    text-align: center;
}

.stories-question__numero {
    color: rgba(255,255,255,0.5);
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.6rem;
}

.stories-question__titulo {
    color: #fff;
    font-family: var(--fuente-titulo);
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.4;
    margin: 0 0 0.4rem;
}

.stories-question__subtitulo {
    color: rgba(255,255,255,0.5);
    font-size: 0.77rem;
    line-height: 1.4;
}

/* ── PANEL DE RESPUESTA ── */
.stories-panel {
    background: var(--color-fondo-card);
    border-radius: 16px 16px 0 0;
    padding: 0.9rem 0.9rem 1.25rem;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
    flex-shrink: 0;
}

/* ── TABS ESCRIBIR / AUDIO ── */
.stories-tabs {
    display: flex;
    gap: 3px;
    background: var(--color-fondo);
    border-radius: 20px;
    padding: 3px;
    margin-bottom: 0.7rem;
}

.stories-tab {
    flex: 1;
    background: transparent;
    border: none;
    border-radius: 18px;
    padding: 0.35rem;
    font-size: 0.73rem;
    color: var(--color-texto-suave);
    cursor: pointer;
    transition: background 0.15s, color 0.15s, box-shadow 0.15s;
    -webkit-tap-highlight-color: transparent;
    outline: none;
}

.stories-tab--active {
    background: var(--color-fondo-card);
    color: var(--color-texto);
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

/* ── TEXTAREA DE RESPUESTA ── */
.stories-textarea {
    width: 100%;
    border: 1px solid var(--color-borde);
    border-radius: 10px;
    padding: 0.55rem 0.65rem;
    font-size: 0.82rem;
    font-family: var(--fuente-cuerpo);
    color: var(--color-texto);
    background: var(--color-fondo);
    resize: none;
    min-height: 80px;
    outline: none;
    transition: border-color 0.15s;
    display: block;
}

.stories-textarea:focus {
    border-color: var(--color-acento);
}

/* ── MODO AUDIO ── */
.audio-grabando {
    background: #fff0f0;
    border: 1.5px solid #e87070;
    border-radius: 10px;
    padding: 0.65rem;
    text-align: center;
    margin-bottom: 0.5rem;
}

.audio-grabando__estado {
    color: #c0392b;
    font-size: 0.72rem;
    font-weight: 600;
    margin-bottom: 0.3rem;
}

.audio-ondas {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    height: 28px;
    margin-bottom: 0.25rem;
}

.audio-ondas__barra {
    width: 3px;
    background: #e87070;
    border-radius: 2px;
    animation: onda 0.8s ease-in-out infinite alternate;
}
.audio-ondas__barra:nth-child(1) { height: 8px;  animation-delay: 0.0s; }
.audio-ondas__barra:nth-child(2) { height: 20px; animation-delay: 0.1s; }
.audio-ondas__barra:nth-child(3) { height: 14px; animation-delay: 0.2s; }
.audio-ondas__barra:nth-child(4) { height: 24px; animation-delay: 0.15s; }
.audio-ondas__barra:nth-child(5) { height: 10px; animation-delay: 0.3s; }
.audio-ondas__barra:nth-child(6) { height: 22px; animation-delay: 0.05s; }
.audio-ondas__barra:nth-child(7) { height: 16px; animation-delay: 0.25s; }

@keyframes onda {
    from { transform: scaleY(0.4); }
    to   { transform: scaleY(1.0); }
}

.audio-transcripcion {
    background: var(--color-fondo);
    border: 1px solid var(--color-borde);
    border-radius: 8px;
    padding: 0.5rem 0.6rem;
    font-size: 0.78rem;
    color: var(--color-texto);
    min-height: 44px;
    font-style: italic;
    margin-bottom: 0.5rem;
    line-height: 1.45;
}

.audio-botonera {
    display: flex;
    gap: 0.4rem;
}

/* ── NAVEGACIÓN ── */
.stories-nav {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.65rem;
}

.stories-nav__atras {
    width: 40px;
    height: 40px;
    background: var(--color-fondo);
    border: 1px solid var(--color-borde);
    border-radius: 50%;
    font-size: 0.9rem;
    cursor: pointer;
    flex-shrink: 0;
    color: var(--color-texto-suave);
    display: flex;
    align-items: center;
    justify-content: center;
    -webkit-tap-highlight-color: transparent;
    outline: none;
}

.stories-nav__siguiente {
    flex: 1;
    background: var(--color-primario);
    color: #fff;
    border: none;
    border-radius: 24px;
    padding: 0.6rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: opacity 0.15s;
    outline: none;
}
.stories-nav__siguiente:active { opacity: 0.85; }

.stories-panel__hint {
    font-size: 0.65rem;
    color: var(--color-texto-suave);
    text-align: center;
    margin-top: 0.4rem;
    opacity: 0.6;
}

/* ── PANEL DE ERROR (dentro de stories) ── */
.error-panel {
    background: #fff0f0;
    border: 1px solid #e87070;
    border-radius: 10px;
    padding: 0.65rem 0.9rem;
    margin: 0 0.9rem 0.5rem;
    font-size: 0.78rem;
    color: #c0392b;
    display: flex;
    gap: 0.4rem;
    align-items: flex-start;
    line-height: 1.4;
}

/* ── PASO 6: GENERANDO ── */
#app-generando {
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2rem;
}

.generando-icono {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    animation: pulso 1.5s ease-in-out infinite;
}

@keyframes pulso {
    0%, 100% { transform: scale(1);   opacity: 1;   }
    50%       { transform: scale(1.1); opacity: 0.7; }
}

.generando-titulo {
    color: #fff;
    font-family: var(--fuente-titulo);
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
}

.generando-sub {
    color: rgba(255,255,255,0.55);
    font-size: 0.8rem;
}

.generando-dots {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 1.25rem;
}

.generando-dots span {
    width: 8px;
    height: 8px;
    background: rgba(255,255,255,0.5);
    border-radius: 50%;
    animation: dot-bounce 1.2s ease-in-out infinite;
}
.generando-dots span:nth-child(2) { animation-delay: 0.2s; }
.generando-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes dot-bounce {
    0%, 80%, 100% { transform: scale(0.8); opacity: 0.4; }
    40%           { transform: scale(1.1); opacity: 1.0; }
}

/* ── PASO 7: RESULTADO ── */
#app-resultado {
    max-width: 480px;
    margin: 0 auto;
    padding: 0 1rem 2rem;
    min-height: 100vh;
    background: var(--color-fondo);
}

.resultado-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0 0.85rem;
    border-bottom: 1px solid var(--color-borde);
    margin-bottom: 0.85rem;
}

.resultado-header__icono {
    width: 32px;
    height: 32px;
    background: var(--color-fondo-card);
    border: 1px solid var(--color-borde);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.resultado-badge {
    background: #f0f7f0;
    border: 1px solid #c3dfc3;
    border-radius: 8px;
    padding: 0.45rem 0.7rem;
    font-size: 0.75rem;
    color: #3a6b3a;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.resultado-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--color-texto);
    margin-bottom: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.resultado-textarea {
    width: 100%;
    border: 1px solid var(--color-borde);
    border-radius: 10px;
    padding: 0.65rem;
    font-size: 0.82rem;
    font-family: var(--fuente-cuerpo);
    color: var(--color-texto);
    background: var(--color-fondo-card);
    resize: vertical;
    min-height: 180px;
    outline: none;
    transition: border-color 0.15s;
}
.resultado-textarea:focus { border-color: var(--color-acento); }

.resultado-contador {
    text-align: right;
    font-size: 0.65rem;
    color: var(--color-texto-suave);
    margin: 0.2rem 0 0.75rem;
}

.aviso-reemplazo {
    background: #fff8ec;
    border: 1px solid #f0d080;
    border-radius: 8px;
    padding: 0.5rem 0.7rem;
    font-size: 0.73rem;
    color: #8a6a00;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    line-height: 1.45;
}

.btn-usar {
    width: 100%;
    background: var(--color-primario);
    color: #fff;
    border: none;
    border-radius: 24px;
    padding: 0.65rem;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 0.45rem;
    -webkit-tap-highlight-color: transparent;
    display: block;
    text-align: center;
}

.btn-regenerar {
    width: 100%;
    background: transparent;
    color: var(--color-primario);
    border: 1px solid var(--color-acento);
    border-radius: 24px;
    padding: 0.6rem;
    font-size: 0.82rem;
    cursor: pointer;
    margin-bottom: 0.45rem;
    display: block;
    text-align: center;
}

.btn-volver-sin-guardar {
    width: 100%;
    background: transparent;
    color: var(--color-texto-suave);
    border: 1px solid var(--color-borde);
    border-radius: 24px;
    padding: 0.55rem;
    font-size: 0.78rem;
    cursor: pointer;
    display: block;
    text-align: center;
}
```

- [ ] **Step 4.2 — Añadir `.btn-asistente-bio` al final de `assets/css/completar.css`**

Añadir al final del archivo `C:/xampp/htdocs/huella/assets/css/completar.css`:

```css
/* ── BOTÓN ASISTENTE DE BIOGRAFÍA ── */
.btn-asistente-bio {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.55rem 0.85rem;
    margin-top: 0.65rem;
    background: transparent;
    border: 1px dashed var(--color-acento);
    border-radius: 10px;
    color: var(--color-texto-suave);
    font-size: 0.78rem;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.18s, color 0.18s, border-color 0.18s;
    -webkit-tap-highlight-color: transparent;
    font-family: var(--fuente-cuerpo);
}
.btn-asistente-bio:hover {
    background: var(--color-fondo);
    border-color: var(--color-primario);
    color: var(--color-primario);
}
.btn-asistente-bio svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    opacity: 0.7;
}
```

---

## Task 5: Página principal `bio_asistente.php`

**Files:**
- Create: `bio_asistente.php`

- [ ] **Step 5.1 — Crear `bio_asistente.php`**

Crear `C:/xampp/htdocs/huella/bio_asistente.php`:

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

// ── Resolución de huella y control de acceso ──────────────────────────────
$linkActual = $_GET['link'] ?? null;
if (!$linkActual) {
    header('Location: /huella/');
    exit;
}

$huella = obtenerHuellaByLink($linkActual);

if (!$huella || !esAdminHuella($huella['Id_Huella'])) {
    if (!sesionActiva()) {
        header('Location: /huella/login.php?back=' . urlencode($_SERVER['REQUEST_URI']));
    } else {
        http_response_code(403);
        header('Location: /huella/' . htmlspecialchars($linkActual));
    }
    exit;
}

// ── Datos del tema y del fallecido ────────────────────────────────────────
$tema    = cargarTema();
$tema    = obtenerTemaCliente($huella['Id_Cliente'], $tema);
$colores = $tema['colores'];
$cssVars = generarVariablesCSS($colores, $tema);

$idIdioma = usuarioActual()['idioma'] ?? ($huella['Id_Idioma'] ?? 'CAS');

$nombreFallecido = trim(
    ($huella['Fallecido_Nombre']     ?? '') . ' ' .
    ($huella['Fallecido_Apellido_1'] ?? '') . ' ' .
    ($huella['Fallecido_Apellido_2'] ?? '')
);

$bioExistente   = !empty(trim($huella['Fallecido_Texto'] ?? ''));
$idHuella       = $huella['Id_Huella'];
$nombreProducto = $tema['nombre_producto'];
$logoPath       = asset($tema['logo_path']);

$idiomasOpciones = [
    'CAS' => '🇪🇸 Castellano',
    'CAT' => '🏴󠁥󠁳󠁣󠁴󠁿 Català',
    'ENG' => '🇬🇧 English',
    'EUS' => '🏴 Euskera',
];
?><!DOCTYPE html>
<html lang="<?= strtolower($idIdioma) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Asistente de Biografía — <?= htmlspecialchars($nombreProducto) ?></title>
<style><?= $cssVars ?></style>
<link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/bio_asistente.css') ?>">
<?php include __DIR__ . '/includes/session_guard.php'; ?>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     PASOS 1–5: STORIES
     ═══════════════════════════════════════════════════════════ -->
<div id="app-stories">

  <!-- Barra de progreso segmentada (5 tramos) -->
  <div class="stories-progress">
    <div class="stories-progress__seg" data-seg="1"></div>
    <div class="stories-progress__seg" data-seg="2"></div>
    <div class="stories-progress__seg" data-seg="3"></div>
    <div class="stories-progress__seg" data-seg="4"></div>
    <div class="stories-progress__seg" data-seg="5"></div>
  </div>

  <!-- Header: contador + selector de idioma -->
  <div class="stories-header">
    <span class="stories-header__label" id="numeroPregunta">Pregunta 1 de 5</span>
    <select class="stories-header__idioma" id="selectIdioma">
      <?php foreach ($idiomasOpciones as $cod => $etiqueta): ?>
        <option value="<?= $cod ?>"<?= $cod === $idIdioma ? ' selected' : '' ?>><?= $etiqueta ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Aviso de error al generar -->
  <div class="error-panel" id="errorPanel" style="display:none">
    <span>⚠️</span><span id="errorMsg"></span>
  </div>

  <!-- Texto de la pregunta (centrado) -->
  <div class="stories-question">
    <p class="stories-question__numero"  id="tituloPreguntaNum"></p>
    <h2 class="stories-question__titulo" id="tituloPregunta"></h2>
    <p class="stories-question__subtitulo" id="subtituloPregunta"></p>
  </div>

  <!-- Panel blanco de respuesta -->
  <div class="stories-panel">

    <!-- Tabs Escribir / Audio -->
    <div class="stories-tabs">
      <button class="stories-tab stories-tab--active" data-tab="texto" onclick="cambiarTab('texto')">✏️ Escribir</button>
      <button class="stories-tab" data-tab="audio" id="tabAudio" onclick="cambiarTab('audio')">🎤 Audio</button>
    </div>

    <!-- Modo texto -->
    <div id="modoTexto">
      <textarea id="respuestaTexto" class="stories-textarea" rows="3"></textarea>
    </div>

    <!-- Modo audio -->
    <div id="modoAudio" style="display:none">
      <div class="audio-grabando" id="audioGrabando" style="display:none">
        <div class="audio-grabando__estado">
          <span id="estadoGrabacion">● Grabando...</span>
          <span id="timerGrabacion" style="margin-left:0.5rem;opacity:0.7">00:00</span>
        </div>
        <div class="audio-ondas">
          <div class="audio-ondas__barra"></div>
          <div class="audio-ondas__barra"></div>
          <div class="audio-ondas__barra"></div>
          <div class="audio-ondas__barra"></div>
          <div class="audio-ondas__barra"></div>
          <div class="audio-ondas__barra"></div>
          <div class="audio-ondas__barra"></div>
        </div>
      </div>
      <div class="audio-transcripcion" id="transcripcionPreview">Pulsa "Grabar" y habla con naturalidad…</div>
      <div class="audio-botonera">
        <button class="stories-nav__siguiente" id="btnIniciarGrabacion" onclick="iniciarGrabacion()" style="font-size:0.8rem;padding:0.5rem">🎤 Grabar</button>
        <button class="stories-nav__siguiente" id="btnParar" onclick="pararGrabacion()" style="display:none;background:#e87070;font-size:0.8rem;padding:0.5rem">⏹ Parar</button>
      </div>
    </div>

    <!-- Navegación -->
    <div class="stories-nav">
      <button class="stories-nav__atras" id="btnAtras" onclick="atras()" style="visibility:hidden">←</button>
      <button class="stories-nav__siguiente" id="btnSiguiente" onclick="siguiente()">Siguiente →</button>
    </div>
    <p class="stories-panel__hint">Todos los campos son opcionales</p>

  </div><!-- /.stories-panel -->
</div><!-- /#app-stories -->


<!-- ═══════════════════════════════════════════════════════════
     PASO 6: GENERANDO
     ═══════════════════════════════════════════════════════════ -->
<div id="app-generando" style="display:none">
  <div class="generando-icono">✨</div>
  <p class="generando-titulo">Generando la biografía…</p>
  <p class="generando-sub">Esto solo tarda unos segundos</p>
  <div class="generando-dots"><span></span><span></span><span></span></div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     PASO 7: RESULTADO
     ═══════════════════════════════════════════════════════════ -->
<div id="app-resultado" style="display:none">

  <div class="resultado-header">
    <div class="resultado-header__icono">✨</div>
    <div>
      <div style="font-weight:600;font-size:0.9rem;color:var(--color-texto)">Asistente de Biografía</div>
      <?php if ($nombreFallecido): ?>
      <div style="font-size:0.68rem;color:var(--color-texto-suave);text-transform:uppercase;letter-spacing:0.04em"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></div>
      <?php endif; ?>
    </div>
    <button onclick="volverSinGuardar()" style="margin-left:auto;background:none;border:none;font-size:0.72rem;color:var(--color-texto-suave);cursor:pointer;padding:0.25rem">✕</button>
  </div>

  <div class="resultado-badge">✅ Biografía generada correctamente</div>

  <span class="resultado-label">Biografía generada</span>
  <textarea id="textoBiografia"
            class="resultado-textarea"
            oninput="document.getElementById('cntChars').textContent=this.value.length"></textarea>
  <div class="resultado-contador"><span id="cntChars">0</span> / 3000</div>

  <div class="aviso-reemplazo" id="avisoReemplazo" style="display:none">
    <span>⚠️</span>
    <span>Este memorial ya tiene una biografía escrita. Si confirmas, será reemplazada por el texto generado.</span>
  </div>

  <button class="btn-usar"               onclick="usarBiografia()">✅ Usar esta biografía</button>
  <button class="btn-regenerar"          onclick="regenerar()">🔄 Regenerar</button>
  <button class="btn-volver-sin-guardar" onclick="volverSinGuardar()">← Volver sin guardar</button>

</div><!-- /#app-resultado -->


<script>
// ─── Constantes PHP → JS ──────────────────────────────────────────────────
const ID_HUELLA    = <?= json_encode($idHuella) ?>;
const LINK_HUELLA  = <?= json_encode($linkActual) ?>;
const BIO_EXISTENTE = <?= $bioExistente ? 'true' : 'false' ?>;

// ─── Preguntas ────────────────────────────────────────────────────────────
const PREGUNTAS = [
  {
    num: 'Profesión',
    titulo: '¿Cuál era su profesión o a qué se dedicó?',
    subtitulo: 'Su trabajo, carrera o forma de vida',
    placeholder: 'Ej: Maestra de primaria durante 30 años…'
  },
  {
    num: 'Aficiones',
    titulo: '¿Qué le apasionaba? ¿Cuáles eran sus hobbies?',
    subtitulo: 'Sus actividades favoritas, pasiones, entretenimientos',
    placeholder: 'Ej: La huerta, la música, el fútbol…'
  },
  {
    num: 'Personalidad',
    titulo: '¿Cómo era su personalidad?',
    subtitulo: '¿Cómo le describirían quienes le conocieron?',
    placeholder: 'Ej: Alegre, generosa, siempre dispuesta a ayudar…'
  },
  {
    num: 'Valores',
    titulo: '¿Qué fue lo más importante para él/ella?',
    subtitulo: 'Familia, valores, legado, lo que más le importaba',
    placeholder: 'Ej: Su familia lo era todo, se enorgullecía de sus hijos…'
  },
  {
    num: 'Recuerdo',
    titulo: '¿Hay algún recuerdo especial o anécdota que destacar?',
    subtitulo: 'Algo que le defina o que quieras que quede en su historia',
    placeholder: 'Ej: Cada verano organizaba una paella para todo el barrio…'
  }
];

// ─── Estado ───────────────────────────────────────────────────────────────
let pasoActual   = 0;
let respuestas   = ['', '', '', '', ''];
let recognition  = null;
let grabando     = false;
let timerInterval = null;
let timerSegundos = 0;

const SR             = window.SpeechRecognition || window.webkitSpeechRecognition;
const audioSoportado = !!SR;
const langMap        = { CAS: 'es-ES', CAT: 'ca-ES', ENG: 'en-GB', EUS: 'eu-ES' };

// ─── Init ─────────────────────────────────────────────────────────────────
(function init() {
  if (!audioSoportado) document.getElementById('tabAudio').style.display = 'none';
  mostrarPaso(0);
})();

// ─── Navegación entre preguntas ───────────────────────────────────────────
function mostrarPaso(paso) {
  pasoActual = paso;

  // Barra segmentada
  document.querySelectorAll('.stories-progress__seg').forEach((seg, i) => {
    seg.className = 'stories-progress__seg';
    if (i < paso)       seg.classList.add('stories-progress__seg--done');
    else if (i === paso) seg.classList.add('stories-progress__seg--active');
  });

  const q = PREGUNTAS[paso];
  document.getElementById('numeroPregunta').textContent     = 'Pregunta ' + (paso + 1) + ' de 5';
  document.getElementById('tituloPreguntaNum').textContent  = q.num;
  document.getElementById('tituloPregunta').textContent     = q.titulo;
  document.getElementById('subtituloPregunta').textContent  = q.subtitulo;

  const ta = document.getElementById('respuestaTexto');
  ta.placeholder = q.placeholder;
  ta.value       = respuestas[paso];

  document.getElementById('btnAtras').style.visibility  = paso === 0 ? 'hidden' : 'visible';
  document.getElementById('btnSiguiente').textContent   = paso === 4 ? '✨ Generar →' : 'Siguiente →';
  document.getElementById('errorPanel').style.display   = 'none';

  if (grabando) pararGrabacion();
  cambiarTab('texto');
}

function siguiente() {
  respuestas[pasoActual] = document.getElementById('respuestaTexto').value.trim();
  if (pasoActual < 4) {
    mostrarPaso(pasoActual + 1);
  } else {
    iniciarGeneracion();
  }
}

function atras() {
  if (pasoActual > 0) {
    respuestas[pasoActual] = document.getElementById('respuestaTexto').value.trim();
    mostrarPaso(pasoActual - 1);
  }
}

// ─── Generación ───────────────────────────────────────────────────────────
async function iniciarGeneracion() {
  document.getElementById('app-stories').style.display   = 'none';
  document.getElementById('app-generando').style.display = 'flex';

  const idioma = document.getElementById('selectIdioma').value;
  const params = new URLSearchParams({
    action:    'generar',
    id_huella: ID_HUELLA,
    idioma:    idioma,
    r1: respuestas[0],
    r2: respuestas[1],
    r3: respuestas[2],
    r4: respuestas[3],
    r5: respuestas[4],
  });

  try {
    const resp = await fetch('/huella/bio_asistente_api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    params.toString(),
    });
    const data = await resp.json();
    document.getElementById('app-generando').style.display = 'none';

    if (data.ok) {
      mostrarResultado(data.texto);
    } else {
      mostrarErrorStories(data.error || 'Error al generar la biografía.');
    }
  } catch (e) {
    document.getElementById('app-generando').style.display = 'none';
    mostrarErrorStories('Error de conexión. Comprueba tu conexión e inténtalo de nuevo.');
  }
}

function mostrarResultado(texto) {
  document.getElementById('textoBiografia').value         = texto;
  document.getElementById('cntChars').textContent         = texto.length;
  document.getElementById('avisoReemplazo').style.display = BIO_EXISTENTE ? '' : 'none';
  document.getElementById('app-resultado').style.display  = '';
}

function mostrarErrorStories(msg) {
  document.getElementById('app-stories').style.display = '';
  document.getElementById('errorMsg').textContent      = msg;
  document.getElementById('errorPanel').style.display  = '';
  mostrarPaso(pasoActual);
}

// ─── Guardar ──────────────────────────────────────────────────────────────
async function usarBiografia() {
  const texto  = document.getElementById('textoBiografia').value.trim();
  const params = new URLSearchParams({
    action:    'guardar',
    id_huella: ID_HUELLA,
    texto:     texto,
  });

  try {
    const resp = await fetch('/huella/bio_asistente_api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    params.toString(),
    });
    const data = await resp.json();

    if (data.ok) {
      window.location.href = '/huella/' + LINK_HUELLA + '/completar?bio_ok=1';
    } else {
      alert('Error al guardar: ' + (data.error || 'Error desconocido'));
    }
  } catch (e) {
    alert('Error de conexión al guardar. Inténtalo de nuevo.');
  }
}

function regenerar() {
  document.getElementById('app-resultado').style.display = 'none';
  document.getElementById('app-stories').style.display  = '';
  mostrarPaso(0);
}

function volverSinGuardar() {
  window.location.href = '/huella/' + LINK_HUELLA + '/completar';
}

// ─── Tabs ─────────────────────────────────────────────────────────────────
function cambiarTab(tab) {
  const esTexto = tab === 'texto';
  document.getElementById('modoTexto').style.display = esTexto ? '' : 'none';
  document.getElementById('modoAudio').style.display = esTexto ? 'none' : '';
  document.querySelectorAll('.stories-tab').forEach(btn => {
    btn.classList.toggle('stories-tab--active', btn.dataset.tab === tab);
  });
  if (!esTexto && !grabando) iniciarGrabacion();
}

// ─── Audio (Web Speech API) ───────────────────────────────────────────────
function iniciarGrabacion() {
  if (!audioSoportado || grabando) return;

  const idioma = document.getElementById('selectIdioma').value;
  recognition  = new SR();
  recognition.continuous     = false;
  recognition.interimResults = true;
  recognition.lang           = langMap[idioma] || 'es-ES';

  let transcripcionFinal = '';

  recognition.onstart = () => {
    grabando     = true;
    timerSegundos = 0;
    document.getElementById('audioGrabando').style.display       = '';
    document.getElementById('btnParar').style.display            = '';
    document.getElementById('btnIniciarGrabacion').style.display = 'none';
    document.getElementById('estadoGrabacion').textContent       = '● Grabando...';
    timerInterval = setInterval(() => {
      timerSegundos++;
      const m = String(Math.floor(timerSegundos / 60)).padStart(2, '0');
      const s = String(timerSegundos % 60).padStart(2, '0');
      document.getElementById('timerGrabacion').textContent = m + ':' + s;
    }, 1000);
  };

  recognition.onresult = (event) => {
    let interim = '';
    transcripcionFinal = '';
    for (let i = 0; i < event.results.length; i++) {
      if (event.results[i].isFinal) {
        transcripcionFinal += event.results[i][0].transcript;
      } else {
        interim += event.results[i][0].transcript;
      }
    }
    document.getElementById('transcripcionPreview').textContent = transcripcionFinal + interim;
  };

  recognition.onend = () => {
    grabando = false;
    clearInterval(timerInterval);
    document.getElementById('audioGrabando').style.display       = 'none';
    document.getElementById('btnParar').style.display            = 'none';
    document.getElementById('btnIniciarGrabacion').style.display = '';
    if (transcripcionFinal.trim()) {
      document.getElementById('respuestaTexto').value = transcripcionFinal.trim();
    }
    cambiarTab('texto');
  };

  recognition.onerror = (event) => {
    grabando = false;
    clearInterval(timerInterval);
    document.getElementById('audioGrabando').style.display       = 'none';
    document.getElementById('btnParar').style.display            = 'none';
    document.getElementById('btnIniciarGrabacion').style.display = '';
    document.getElementById('transcripcionPreview').textContent  =
      'Error: ' + event.error + '. Inténtalo de nuevo.';
  };

  recognition.start();
}

function pararGrabacion() {
  if (recognition && grabando) recognition.stop();
}
</script>

</body>
</html>
```

- [ ] **Step 5.2 — Verificar la página**

Abrir `http://localhost/huella/{slug}/bio-asistente` (con sesión de admin activa).

Lista de comprobación:
- ✅ Se carga la página sin errores PHP (ver logs en `C:/xampp/logs/php_error_log` si hay problemas)
- ✅ El fondo de la zona de pregunta es el color primario del tema (marrón por defecto, o el del cliente)
- ✅ La barra segmentada muestra 5 tramos, el primero activo
- ✅ Se ve el texto "Pregunta 1 de 5" y el selector de idioma
- ✅ Se ve la pregunta centrada sobre el fondo oscuro
- ✅ El panel blanco aparece con los tabs "✏️ Escribir" y "🎤 Audio"
- ✅ El botón "←" está oculto (primera pregunta)
- ✅ El botón dice "Siguiente →"

Navegar por las preguntas:
- ✅ Pulsar "Siguiente →" avanza a la pregunta 2, barra muestra 2 activa
- ✅ En la pregunta 2 aparece el botón "←"
- ✅ Pulsar "←" vuelve a la pregunta 1 con el texto guardado
- ✅ En la pregunta 5 el botón dice "✨ Generar →"
- ✅ Sin API key configurada: al pulsar "✨ Generar →" aparece el panel de error con el mensaje de API key
- ✅ Si accedes sin sesión → redirige a `login.php`
- ✅ Si accedes con sesión pero sin ser admin → 403 → redirige a la huella

Probar audio (Chrome/Edge):
- ✅ Pestaña "🎤 Audio" visible en Chrome
- ✅ Al pulsar la tab, el navegador pide permiso de micrófono
- ✅ Al aceptar, aparece la animación de ondas y el contador de tiempo
- ✅ Al hablar, el texto aparece en el preview
- ✅ Al parar, el texto se vuelca al textarea y se vuelve a la tab de escritura

---

## Task 6: Botón en `completar.php` + toast

**Files:**
- Modify: `completar.php`

- [ ] **Step 6.1 — Añadir el toast `bio_ok` en `completar.php`**

En `C:/xampp/htdocs/huella/completar.php`, localizar el bloque donde se inicializan `$toastMsg` (alrededor de la línea 37-38):

```php
$toastMsg   = '';    // mensaje de confirmación tipo toast
```

Añadir inmediatamente después:

```php
if (isset($_GET['bio_ok'])) {
    $toastMsg = 'Biografía generada y guardada correctamente.';
}
```

- [ ] **Step 6.2 — Añadir el botón en el bloque de Biografía**

En `completar.php`, localizar el bloque `<!-- ── BIOGRAFÍA ── -->` (alrededor de la línea 648). El bloque actual es:

```php
        <!-- ── BIOGRAFÍA ── -->
        <div class="card">
            <div class="campo-grupo">
                <label class="campo-label" for="inputTexto"><?= $lang['completar_texto_bio'] ?></label>
                <textarea id="inputTexto"
                          name="texto"
                          class="campo-textarea"
                          placeholder="<?= $lang['completar_texto_bio_ph'] ?>"
                          oninput="actualizarContador(this, 'cntTexto', 3000)"><?= $textoActual ?></textarea>
                <div class="campo-contador"><span id="cntTexto"><?= mb_strlen(html_entity_decode($textoActual)) ?></span> / 3000</div>
            </div>
        </div>
```

Reemplazarlo por:

```php
        <!-- ── BIOGRAFÍA ── -->
        <div class="card">
            <div class="campo-grupo">
                <label class="campo-label" for="inputTexto"><?= $lang['completar_texto_bio'] ?></label>
                <textarea id="inputTexto"
                          name="texto"
                          class="campo-textarea"
                          placeholder="<?= $lang['completar_texto_bio_ph'] ?>"
                          oninput="actualizarContador(this, 'cntTexto', 3000)"><?= $textoActual ?></textarea>
                <div class="campo-contador"><span id="cntTexto"><?= mb_strlen(html_entity_decode($textoActual)) ?></span> / 3000</div>
            </div>
            <a href="<?= urlHuella($linkActual, 'bio-asistente') ?>" class="btn-asistente-bio">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9"/><path d="M20 3v4"/><path d="M22 5h-4"/></svg>
                Generar biografía con asistente
            </a>
        </div>
```

- [ ] **Step 6.3 — Verificar el flujo completo**

1. Abrir `http://localhost/huella/{slug}/completar` con sesión de admin.
2. Bajar hasta el bloque "Completar su historia" y abrirlo.
3. Comprobar que aparece el botón "✨ Generar biografía con asistente" debajo del textarea de biografía, con estilo de borde discontinuo.
4. Pulsar el botón → debe navegar a `http://localhost/huella/{slug}/bio-asistente`.
5. Responder las 5 preguntas y pulsar "✨ Generar →".
6. Con API key configurada: aparece el spinner, luego el texto generado en el resultado.
7. Pulsar "✅ Usar esta biografía" → guarda y redirige a `completar.php`.
8. En `completar.php` debe aparecer el toast "Biografía generada y guardada correctamente."
9. Abrir el formulario de historia → el textarea de biografía debe contener el texto generado.

**Flujo con bio existente:**
1. Si la huella ya tiene texto en el campo de biografía, al llegar al paso 7 debe aparecer el aviso amarillo ⚠️ antes del botón "Usar esta biografía".

---

## Task 7: Commit final

- [ ] **Step 7.1 — Revisar todos los archivos tocados**

```
git status
```

Archivos esperados en el diff:
- `A  bio_asistente.php`
- `A  bio_asistente_api.php`
- `A  config/gemini.php`
- `A  includes/gemini.php`
- `A  assets/css/bio_asistente.css`
- `M  assets/css/completar.css`
- `M  completar.php`
- `M  .htaccess`

- [ ] **Step 7.2 — Commit**

```bash
git add bio_asistente.php bio_asistente_api.php config/gemini.php includes/gemini.php assets/css/bio_asistente.css assets/css/completar.css completar.php .htaccess docs/superpowers/specs/2026-05-23-asistente-biografia-design.md docs/superpowers/plans/2026-05-23-asistente-biografia.md
git commit -m "feat: asistente de biografía con IA (Gemini) formato Stories

- Nueva página bio_asistente.php con formato Instagram Stories
- 5 preguntas una a la vez con barra de progreso segmentada
- Entrada por texto o voz (Web Speech API, sin coste)
- API gratuita de Gemini para generar la biografía
- Vista previa antes de guardar, con aviso si ya existe bio
- Hereda tema visual del cliente (CSS variables)
- Botón de acceso en el bloque Biografía de completar.php

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Notas de configuración post-implementación

**Para que la IA funcione**, el admin del sistema debe configurar la API key de Gemini (gratuita):

1. Ir a https://aistudio.google.com/app/apikey y crear una key gratuita
2. Pegar la key en `config/gemini.php` en el campo `'api_key'`:
   ```php
   'api_key' => 'AIzaSy...',
   ```
3. Verificar con el script de prueba del Task 2 (si aún no se ha borrado) o probando el flujo completo desde `completar.php`
