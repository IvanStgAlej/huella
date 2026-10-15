<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

// ── Resolución de huella y control de acceso ──────────────────────────────
$linkActual = $_GET['link'] ?? null;
if (!$linkActual) {
    header('Location: /huella/');
    exit;
}

if (!sesionActiva()) {
    header('Location: /huella/login.php?back=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$huella = obtenerHuellaByLink($linkActual);

if (!$huella || !esAdminHuella($huella['Id_Huella'])) {
    http_response_code(403);
    header('Location: /huella/' . rawurlencode($linkActual));
    exit;
}

// ── Datos del tema y del fallecido ────────────────────────────────────────
$tema    = cargarTema();
$tema    = obtenerTemaCliente($huella['Id_Cliente'], $tema);
$cssVars = generarVariablesCSS($tema['colores'], $tema);

$idIdioma = $huella['Id_Idioma'] ?? 'CAS';

$nombreFallecido = trim(
    ($huella['Fallecido_Nombre']     ?? '') . ' ' .
    ($huella['Fallecido_Apellido_1'] ?? '') . ' ' .
    ($huella['Fallecido_Apellido_2'] ?? '')
);

$bioExistente = !empty(trim($huella['Fallecido_Texto'] ?? ''));
$idHuella     = $huella['Id_Huella'];

$idiomasOpciones = [
    'CAS' => 'CAS',
    'CAT' => 'CAT',
    'ENG' => 'ENG',
    'EUS' => 'EUS',
];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Asistente de Biografía</title>
<style>:root { <?= $cssVars ?> }</style>
<link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/bio_asistente.css') ?>">
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
        <option value="<?= htmlspecialchars($cod) ?>"<?= $cod === $idIdioma ? ' selected' : '' ?>><?= htmlspecialchars($etiqueta) ?></option>
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
  <p class="generando-titulo">Generando…</p>
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
const ID_HUELLA     = <?= json_encode($idHuella) ?>;
const LINK_HUELLA   = <?= json_encode($linkActual) ?>;
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
let pasoActual    = 0;
let respuestas    = ['', '', '', '', ''];
let mediaRecorder = null;
let mediaStream   = null;
let chunksAudio   = [];
let mimeAudio     = '';
let grabando      = false;
let timerInterval = null;
let timerSegundos = 0;

const audioSoportado = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);

// ─── Init ─────────────────────────────────────────────────────────────────
(function init() {
  if (!audioSoportado) document.getElementById('tabAudio').style.display = 'none';
  mostrarPaso(0);
})();

// ─── Navegación entre preguntas ───────────────────────────────────────────
function mostrarPaso(paso) {
  pasoActual = paso;

  document.querySelectorAll('.stories-progress__seg').forEach((seg, i) => {
    seg.className = 'stories-progress__seg';
    if (i < paso)        seg.classList.add('stories-progress__seg--done');
    else if (i === paso) seg.classList.add('stories-progress__seg--active');
  });

  const q = PREGUNTAS[paso];
  document.getElementById('numeroPregunta').textContent    = 'Pregunta ' + (paso + 1) + ' de 5';
  document.getElementById('tituloPreguntaNum').textContent = q.num;
  document.getElementById('tituloPregunta').textContent    = q.titulo;
  document.getElementById('subtituloPregunta').textContent = q.subtitulo;

  const ta = document.getElementById('respuestaTexto');
  ta.placeholder = q.placeholder;
  ta.value       = respuestas[paso];

  document.getElementById('btnAtras').style.visibility = paso === 0 ? 'hidden' : 'visible';
  document.getElementById('btnSiguiente').textContent  = paso === 4 ? '✨ Generar →' : 'Siguiente →';
  document.getElementById('errorPanel').style.display  = 'none';

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
    const resp = await fetch('/huella/api/bio_asistente.php', {
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
  mostrarPaso(pasoActual);
  // mostrarPaso oculta el errorPanel, por eso lo mostramos DESPUÉS
  document.getElementById('errorMsg').textContent     = msg;
  document.getElementById('errorPanel').style.display = '';
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
    const resp = await fetch('/huella/api/bio_asistente.php', {
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
}

// ─── Audio (MediaRecorder + Whisper backend) ──────────────────────────────
function elegirMimeAudio() {
  // Orden de preferencia por compatibilidad con Groq Whisper
  const candidatos = [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/ogg;codecs=opus',
    'audio/ogg',
    'audio/mp4',
    'audio/mpeg',
    'audio/wav',
  ];
  if (!window.MediaRecorder || !MediaRecorder.isTypeSupported) return '';
  for (const tipo of candidatos) {
    if (MediaRecorder.isTypeSupported(tipo)) return tipo;
  }
  return '';
}

function extensionDeMime(mime) {
  if (!mime) return 'webm';
  if (mime.includes('webm')) return 'webm';
  if (mime.includes('ogg'))  return 'ogg';
  if (mime.includes('mp4'))  return 'm4a';
  if (mime.includes('mpeg')) return 'mp3';
  if (mime.includes('wav'))  return 'wav';
  return 'webm';
}

async function iniciarGrabacion() {
  if (!audioSoportado || grabando) return;

  document.getElementById('transcripcionPreview').textContent = 'Solicitando acceso al micrófono…';

  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
  } catch (err) {
    const msgsPermiso = {
      'NotAllowedError':    'Permiso de micrófono denegado. Permítelo en los ajustes del navegador e inténtalo de nuevo.',
      'NotFoundError':      'No se ha encontrado un micrófono. Comprueba que tienes uno conectado.',
      'NotReadableError':   'El micrófono está siendo usado por otra aplicación.',
      'SecurityError':      'El navegador bloquea el micrófono. Accede vía HTTPS o localhost.',
    };
    document.getElementById('transcripcionPreview').textContent =
      msgsPermiso[err.name] || ('No se ha podido acceder al micrófono: ' + err.message);
    return;
  }

  mimeAudio   = elegirMimeAudio();
  chunksAudio = [];

  try {
    mediaRecorder = mimeAudio
      ? new MediaRecorder(mediaStream, { mimeType: mimeAudio })
      : new MediaRecorder(mediaStream);
  } catch (err) {
    detenerStream();
    document.getElementById('transcripcionPreview').textContent =
      'Este navegador no soporta la grabación de audio. Escribe la respuesta a mano.';
    return;
  }

  // Si el navegador eligió uno diferente (ej. Safari → mp4), respetarlo.
  if (mediaRecorder.mimeType) mimeAudio = mediaRecorder.mimeType;

  mediaRecorder.ondataavailable = (e) => { if (e.data && e.data.size > 0) chunksAudio.push(e.data); };
  mediaRecorder.onstop          = procesarGrabacion;
  mediaRecorder.onerror         = (e) => {
    detenerStream();
    document.getElementById('transcripcionPreview').textContent =
      'Error de grabación: ' + (e.error?.message || 'desconocido');
    resetUiGrabacion();
  };

  grabando      = true;
  timerSegundos = 0;
  document.getElementById('audioGrabando').style.display       = '';
  document.getElementById('btnParar').style.display            = '';
  document.getElementById('btnIniciarGrabacion').style.display = 'none';
  document.getElementById('estadoGrabacion').textContent       = '● Grabando...';
  document.getElementById('transcripcionPreview').textContent  = 'Habla con naturalidad. Pulsa "Parar" cuando termines.';

  timerInterval = setInterval(() => {
    timerSegundos++;
    const m = String(Math.floor(timerSegundos / 60)).padStart(2, '0');
    const s = String(timerSegundos % 60).padStart(2, '0');
    document.getElementById('timerGrabacion').textContent = m + ':' + s;

    // Corte de seguridad a 5 min
    if (timerSegundos >= 300) pararGrabacion();
  }, 1000);

  mediaRecorder.start();
}

function pararGrabacion() {
  if (mediaRecorder && grabando) {
    grabando = false;
    clearInterval(timerInterval);
    try { mediaRecorder.stop(); } catch (e) { /* ignore */ }
  }
}

async function procesarGrabacion() {
  detenerStream();

  const ext  = extensionDeMime(mimeAudio);
  const blob = new Blob(chunksAudio, { type: mimeAudio || 'audio/webm' });

  if (blob.size === 0) {
    resetUiGrabacion();
    document.getElementById('transcripcionPreview').textContent =
      'No se ha grabado audio. Inténtalo de nuevo.';
    return;
  }

  document.getElementById('estadoGrabacion').textContent = '⏳ Transcribiendo…';
  document.getElementById('btnParar').style.display      = 'none';
  document.getElementById('audioGrabando').style.display = '';
  document.getElementById('transcripcionPreview').textContent = 'Enviando audio para transcribir…';

  const fd = new FormData();
  fd.append('action',    'transcribir');
  fd.append('id_huella', ID_HUELLA);
  fd.append('idioma',    document.getElementById('selectIdioma').value);
  fd.append('audio',     blob, 'audio.' + ext);

  try {
    const resp = await fetch('/huella/api/bio_asistente.php', { method: 'POST', body: fd });
    const data = await resp.json();

    if (data.ok && data.texto) {
      // Concatenar si ya había texto escrito
      const ta     = document.getElementById('respuestaTexto');
      const previo = ta.value.trim();
      ta.value     = previo ? (previo + ' ' + data.texto) : data.texto;
      document.getElementById('transcripcionPreview').textContent = data.texto;
      resetUiGrabacion();
      cambiarTab('texto');
    } else {
      resetUiGrabacion();
      document.getElementById('transcripcionPreview').textContent =
        'Error al transcribir: ' + (data.error || 'desconocido');
    }
  } catch (e) {
    resetUiGrabacion();
    document.getElementById('transcripcionPreview').textContent =
      'Error de conexión al transcribir. Inténtalo de nuevo.';
  }
}

function detenerStream() {
  if (mediaStream) {
    mediaStream.getTracks().forEach(t => t.stop());
    mediaStream = null;
  }
}

function resetUiGrabacion() {
  grabando = false;
  clearInterval(timerInterval);
  document.getElementById('audioGrabando').style.display       = 'none';
  document.getElementById('btnParar').style.display            = 'none';
  document.getElementById('btnIniciarGrabacion').style.display = '';
}
</script>

</body>
</html>
