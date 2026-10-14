# Asistente de Biografía con IA — Especificación de Diseño

**Fecha:** 2026-05-23  
**Proyecto:** Huella — Portal de condolencias  
**Estado:** Aprobado

---

## Qué se construye

Una nueva página (`bio_asistente.php`) accesible desde el panel de administración del memorial (`completar.php`). Permite al administrador de una huella generar automáticamente la biografía del fallecido respondiendo 5 preguntas mediante texto o voz, enviando las respuestas a la API gratuita de Gemini para generar un texto de calidad.

---

## Flujo completo

```
completar.php
  └── Bloque "Biografía" → botón "✨ Generar biografía con asistente"
        ↓ enlace a /huella/{slug}/bio-asistente
  bio_asistente.php
        ├── Pasos 1–5: Preguntas formato Instagram Stories
        │     ├── Barra de progreso segmentada en el header (5 segmentos)
        │     ├── Pregunta centrada sobre fondo oscuro (color-primario del tema)
        │     ├── Panel blanco inferior con tabs "Escribir / Audio"
        │     │     ├── Escribir: textarea libre
        │     │     └── Audio: Web Speech API (transcripción en tiempo real)
        │     ├── Botón "Siguiente →" (avanza) y botón "←" (retrocede)
        │     └── Selector de idioma compacto en el header (defecto: Id_Idioma de la huella)
        │
        ├── Paso 6: Spinner "Generando…"
        │     └── Llamada fetch a bio_asistente_api.php?action=generar
        │
        └── Paso 7: Resultado
              ├── Texto generado (editable)
              ├── Contador de caracteres (máx. 3000)
              ├── Aviso ⚠️ si ya existe una biografía (se mostrará solo si hay texto previo)
              ├── Botón "✅ Usar esta biografía" → guarda en BD → redirect completar.php
              ├── Botón "🔄 Regenerar" → vuelve al paso 1 con respuestas guardadas
              └── Botón "← Volver sin guardar" → redirect completar.php
```

---

## Preguntas del asistente

| # | Pregunta | Placeholder de ejemplo |
|---|---|---|
| 1 | ¿Cuál era su profesión o a qué se dedicó? | Maestra de primaria durante 30 años… |
| 2 | ¿Qué le apasionaba? ¿Cuáles eran sus hobbies o aficiones? | La huerta, la música, el fútbol… |
| 3 | ¿Cómo era su personalidad? ¿Cómo le describirían quienes le conocieron? | Alegre, generosa, siempre dispuesta a ayudar… |
| 4 | ¿Qué fue lo más importante para él/ella? (familia, valores, legado) | Su familia lo era todo… |
| 5 | ¿Hay algún recuerdo especial o anécdota que quieras incluir? | Cada verano organizaba una paella para el barrio… |

Todos los campos son opcionales. Se enviará a Gemini el subconjunto de respuestas que el admin haya rellenado.

---

## Diseño visual (Stories)

- **Fondo de la zona de preguntas:** `var(--color-primario)` del tema del cliente. Texto blanco sobre él.
- **Barra de progreso segmentada:** 5 tramos. Completados = blanco sólido. Activo = degradado (llenándose). Pendientes = blanco al 25% de opacidad.
- **Selector de idioma:** `<select>` compacto en el header de la página, sobre el fondo oscuro. Opciones: CAS / CAT / ENG / EUS. Valor por defecto: `Id_Idioma` de la huella.
- **Panel de respuesta:** bloque blanco con `border-radius: 16px 16px 0 0` pegado al borde inferior.
- **Tabs Escribir / Audio:** pastilla con dos opciones. El activo tiene fondo blanco con sombra suave; el inactivo es transparente.
- **Sin colores hardcodeados:** toda la paleta usa variables CSS del tema (`--color-primario`, `--color-fondo-card`, `--color-borde`, `--color-texto`, etc.).

---

## Audio — Web Speech API

- Implementación: `window.SpeechRecognition || window.webkitSpeechRecognition`
- Configuración: `continuous: false`, `interimResults: true`, `lang` = idioma seleccionado mapeado a BCP-47 (CAS → `es-ES`, CAT → `ca-ES`, ENG → `en-GB`, EUS → `eu-ES`)
- Mientras graba: visualización de ondas animadas + contador de tiempo + texto transcrito en tiempo real
- Al parar: el texto transcrito se vuelca al textarea (editable antes de continuar)
- Detección de soporte: si el navegador no soporta la API, el tab "Audio" se oculta automáticamente y solo aparece "Escribir"

---

## API de Gemini

**Config:** `config/gemini.php` (protegido por `config/.htaccess` con `Deny from all`)

```php
return [
    'api_key'    => '',  // pegar key o usar GEMINI_API_KEY como env var
    'modelo'     => 'gemini-2.0-flash',
    'max_tokens' => 1024,
    'timeout'    => 30,
];
```

**Wrapper:** `includes/gemini.php` — función `llamarGemini(string $prompt, ?string $sistema, array $opciones): array`  
Misma firma y estructura de retorno que `llamarIA()` existente: `['ok', 'texto', 'error']`  
Endpoint: `https://generativelanguage.googleapis.com/v1beta/models/{modelo}:generateContent?key={api_key}`

**Prompt de sistema:**
> Eres un asistente que redacta biografías para memoriales digitales. Tu tono es cálido, respetuoso y personal. Escribes en tercera persona. No uses markdown, ni títulos, ni viñetas: solo texto continuo. Máximo 500 palabras.

**Prompt de usuario** (construido en `bio_asistente_api.php`):
> Redacta la biografía de [Nombre completo], nacido el [fecha] y fallecido el [fecha].  
> Responde en [idioma].  
> Información proporcionada por la familia:  
> - Profesión: [respuesta 1 o "No indicado"]  
> - Hobbies: [respuesta 2 o "No indicado"]  
> - Personalidad: [respuesta 3 o "No indicado"]  
> - Lo más importante para él/ella: [respuesta 4 o "No indicado"]  
> - Recuerdo especial: [respuesta 5 o "No indicado"]

---

## API interna — `bio_asistente_api.php`

Recibe peticiones `fetch` (POST, `application/x-www-form-urlencoded`), devuelve JSON. Mismo patrón que el resto de APIs del proyecto (`muro_api.php`, `admin_api.php`…). La acción viaja en `$_POST['action']`.

### `action=generar`

**Entrada:** `action=generar`, `id_huella`, `r1`…`r5` (respuestas), `idioma`  
**Proceso:** verifica sesión admin sobre esa huella concreta → construye prompt → llama `llamarGemini()` → devuelve texto  
**Salida:** `{ ok: true, texto: "..." }` o `{ ok: false, error: "..." }`

### `action=guardar`

**Entrada:** `action=guardar`, `id_huella`, `texto`  
**Proceso:** verifica sesión admin sobre esa huella concreta → `UPDATE h_huellafallecido SET Fallecido_Texto=? WHERE Id_Huella=?`  
**Salida:** `{ ok: true }` o `{ ok: false, error: "..." }`

---

## Seguridad y acceso

- `bio_asistente.php` y `bio_asistente_api.php`: verifican sesión activa + rol ADM u OWN sobre esa huella, igual que `completar.php`.
- Si no hay sesión: redirect a `login.php`.
- Si hay sesión pero sin permisos: 403 → redirect a la huella.
- Todas las entradas de usuario se validan y se usan con sentencias preparadas `mysqli`.
- El texto generado por Gemini se pasa por `htmlspecialchars()` al renderizar.

---

## Modificaciones en archivos existentes

### `completar.php`
Añadir en el bloque `<!-- ── BIOGRAFÍA ── -->`, debajo del textarea, un botón enlace:

```html
<a href="/huella/{$linkActual}/bio-asistente" class="btn-asistente-bio">
    ✨ Generar biografía con asistente
</a>
```

### `.htaccess`
Nueva regla antes de la regla genérica de slug:

```apache
RewriteRule ^([a-zA-Z0-9_-]+)/bio-asistente/?$ bio_asistente.php?link=$1 [QSA,L]
```

---

## Archivos a crear / modificar

| Acción | Archivo | Descripción |
|---|---|---|
| CREAR | `bio_asistente.php` | Página principal (Stories + resultado) |
| CREAR | `bio_asistente_api.php` | API interna (generar + guardar) |
| CREAR | `config/gemini.php` | Configuración de la API de Gemini |
| CREAR | `includes/gemini.php` | Wrapper `llamarGemini()` |
| CREAR | `assets/css/bio_asistente.css` | Estilos del asistente (usa CSS vars del tema) |
| EDITAR | `completar.php` | Botón "✨ Generar biografía con asistente" en bloque Biografía |
| EDITAR | `.htaccess` | Regla de URL `/bio-asistente` |

---

## Consideraciones y casos límite

- **Gemini no configurada:** si `api_key` está vacía, la API devuelve un error claro y la página muestra un aviso en el paso 6.
- **Texto > 3000 caracteres:** si Gemini genera más de 3000 chars, se muestra aviso pero se permite usar el texto igualmente (el usuario puede recortarlo en el textarea de `completar.php`).
- **Sin respuestas:** el admin puede pulsar "Siguiente" en todas las preguntas sin escribir nada. La IA generará algo basado solo en el nombre y fechas del fallecido; es válido.
- **Regenerar:** al volver al paso 1, las respuestas anteriores se mantienen en memoria (JS) para no tener que reescribirlas.
- **Idiomas del selector:** mapeados a BCP-47 para la Web Speech API y como instrucción de idioma en el prompt de Gemini.
