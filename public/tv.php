<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

$tema    = cargarTema();
$huellas = listarHuellas();
$db      = getDB();

// ── Filtro de palabras prohibidas ─────────────────────────────────────────
$_palabrasProhibidas = require __DIR__ . '/../config/palabras_prohibidas.php';

function normalizar(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, [
        'á'=>'a','à'=>'a','ä'=>'a','â'=>'a',
        'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
        'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
        'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o',
        'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
        'ñ'=>'n','ç'=>'c',
    ]);
    return $s;
}

function contieneOfensiva(string $texto): bool {
    global $_palabrasProhibidas;
    $textoNorm = normalizar($texto);
    foreach ($_palabrasProhibidas as $palabra) {
        $palabraNorm = normalizar($palabra);
        // Escapar la expresión y usar límites de "no letra" para palabras completas
        $patron = '/(?<![a-z0-9])' . preg_quote($palabraNorm, '/') . '(?![a-z0-9])/u';
        if (preg_match($patron, $textoNorm)) {
            return true;
        }
    }
    return false;
}

// ── Huella seleccionada ────────────────────────────────────────────────────────
$linkActual = $_GET['link'] ?? null;
$huella     = $linkActual ? obtenerHuellaByLink($linkActual) : null;

$slides      = [];
$fallecido   = '';
$fechas      = '';
$fotoFallecido = null;

if ($huella) {
    $idH = $huella['Id_Huella'];

    $nombre = trim(
        ($huella['Fallecido_Nombre']     ?? '') . ' ' .
        ($huella['Fallecido_Apellido_1'] ?? '') . ' ' .
        ($huella['Fallecido_Apellido_2'] ?? '')
    );
    $fallecido = $nombre ?: 'Sin nombre';

    $nac = $huella['Fallecido_Fecha_Nacimiento'] ?? null;
    $def = $huella['Fallecido_Fecha_Defunción']  ?? null;
    if ($nac && $nac !== '0000-00-00') $fechas  = date('d/m/Y', strtotime($nac));
    if ($def && $def !== '0000-00-00') $fechas .= ($fechas ? ' — ' : '') . date('d/m/Y', strtotime($def));

    if (!empty($huella['Fallecido_Foto'])) $fotoFallecido = asset($huella['Fallecido_Foto']);

    // ── Cargar posts con imágenes ─────────────────────────────────────────────
    $h   = $db->real_escape_string($idH);
    $posts = $db->query("
        SELECT Id_Post, Post_Autor, Post_Texto, Post_Fecha
        FROM h_huellapost
        WHERE Id_Huella = '$h'
          AND Post_Eliminado  = 0
          AND Post_Vetado     = 0
          AND Post_Autorizado = 1
          AND Post_Parent_Id IS NULL
          AND (Post_Texto != '' OR Post_Texto IS NOT NULL)
        ORDER BY Post_Fecha DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($posts as $p) {
        $textoPost = $p['Post_Texto'] ?? '';

        // Omitir mensajes con palabras prohibidas
        if (contieneOfensiva($textoPost)) continue;

        $pid  = $db->real_escape_string($p['Id_Post']);
        $imgs = $db->query("
            SELECT Img_Ruta_Carpeta, Img_Nombre
            FROM h_huellapost_imagenes
            WHERE Id_Huella='$h' AND Id_Post='$pid'
            ORDER BY Img_Orden ASC
        ")->fetch_all(MYSQLI_ASSOC);

        $slide = [
            'autor'  => $p['Post_Autor'] ?? '',
            'texto'  => $textoPost,
            'fecha'  => $p['Post_Fecha'] ?? '',
            'fotos'  => [],
        ];
        foreach ($imgs as $img) {
            $slide['fotos'][] = asset($img['Img_Ruta_Carpeta'] . $img['Img_Nombre']);
        }
        $slides[] = $slide;
    }

    // Mezclar para que no salgan siempre en el mismo orden
    shuffle($slides);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla memorial<?= $fallecido ? ' — ' . htmlspecialchars($fallecido) : '' ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #080808;
            color: #fff;
            font-family: 'Georgia', 'Times New Roman', serif;
            overflow: hidden;
            height: 100vh;
            width: 100vw;
            user-select: none;
        }

        /* ══ PANTALLA SELECTOR ══════════════════════════════════════════════ */
        #pantalla-selector {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            gap: 2rem;
            background: #080808;
        }
        .selector-logo {
            font-size: 1.5rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #888;
            font-family: 'Segoe UI', sans-serif;
        }
        .selector-titulo {
            font-size: 2.2rem;
            font-weight: 400;
            color: #e8e0d8;
            letter-spacing: .04em;
        }
        .selector-sub {
            font-size: .95rem;
            color: #555;
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .selector-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            max-width: 900px;
        }
        .selector-btn {
            padding: 1rem 2rem;
            background: #151515;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            color: #ccc;
            font-size: 1.1rem;
            font-family: 'Georgia', serif;
            cursor: pointer;
            transition: background .2s, border-color .2s, color .2s;
            text-decoration: none;
            display: block;
        }
        .selector-btn:hover {
            background: #1e1e1e;
            border-color: #888;
            color: #fff;
        }
        .selector-btn__nombre { display: block; font-size: 1.05rem; }
        .selector-btn__hint   { display: block; font-size: .75rem; color: #555; font-family: 'Segoe UI', sans-serif; margin-top: .25rem; }

        /* ══ PANTALLA SLIDESHOW ═════════════════════════════════════════════ */
        #pantalla-tv {
            display: block;
            position: relative;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }

        /* Fondo difuminado global */
        #fondo-blur {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            filter: blur(40px) brightness(.25) saturate(.6);
            transform: scale(1.1);
            transition: background-image .8s;
        }

        /* Vignette */
        #pantalla-tv::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at center, transparent 30%, rgba(0,0,0,.7) 100%);
            pointer-events: none;
            z-index: 1;
        }

        /* ── CABECERA FIJA ── */
        .tv-header {
            position: absolute;
            top: 0; left: 0; right: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.75rem 3rem 1rem;
            background: linear-gradient(to bottom, rgba(0,0,0,.7) 0%, transparent 100%);
        }
        .tv-header__nombre {
            font-size: 1.4rem;
            font-weight: 400;
            letter-spacing: .08em;
            color: rgba(255,255,255,.85);
            text-transform: uppercase;
        }
        .tv-header__fechas {
            font-size: .85rem;
            color: rgba(255,255,255,.4);
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .1em;
            margin-top: .2rem;
        }
        .tv-header__logo {
            font-size: .85rem;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: rgba(255,255,255,.3);
            font-family: 'Segoe UI', sans-serif;
        }

        /* ── SLIDES ── */
        .slide {
            position: absolute;
            inset: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 1s ease;
            padding: 6rem 5vw 5rem;
        }
        .slide.activa  { opacity: 1; }
        .slide.saliendo { opacity: 0; }

        /* Slide INTRO (nombre del fallecido) */
        .slide--intro {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        .slide--intro .intro-nombre {
            font-size: clamp(2.5rem, 6vw, 5.5rem);
            font-weight: 300;
            letter-spacing: .08em;
            color: #f0ece6;
            line-height: 1.15;
        }
        .slide--intro .intro-fechas {
            font-size: clamp(.9rem, 1.8vw, 1.4rem);
            color: rgba(255,255,255,.4);
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .2em;
            text-transform: uppercase;
            margin-top: .5rem;
        }
        .slide--intro .intro-linea {
            width: 60px;
            height: 1px;
            background: rgba(255,255,255,.25);
            margin: 1rem auto;
        }

        /* Slide TEXTO */
        .slide--texto {
            flex-direction: column;
            gap: 2.5rem;
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }
        .slide-comilla {
            font-size: clamp(4rem, 8vw, 8rem);
            color: rgba(255,255,255,.08);
            font-family: Georgia, serif;
            line-height: 1;
            margin-bottom: -2rem;
        }
        .slide-texto__body {
            font-size: clamp(1.2rem, 2.8vw, 2.4rem);
            font-weight: 300;
            line-height: 1.65;
            color: rgba(255,255,255,.88);
            letter-spacing: .01em;
        }
        .slide-texto__autor {
            font-size: clamp(.85rem, 1.5vw, 1.1rem);
            color: rgba(255,255,255,.4);
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .15em;
            text-transform: uppercase;
        }
        .slide-texto__autor::before { content: '— '; }

        /* Slide FOTO */
        .slide--foto {
            flex-direction: row;
            gap: 4vw;
            align-items: center;
            padding: 5rem 5vw;
        }
        .slide-foto__img-wrap {
            flex: 0 0 auto;
            max-width: 55vw;
            max-height: 80vh;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 20px 80px rgba(0,0,0,.6);
        }
        .slide-foto__img-wrap img {
            display: block;
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }
        .slide-foto__info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .slide-foto__texto {
            font-size: clamp(1rem, 2vw, 1.8rem);
            font-weight: 300;
            line-height: 1.65;
            color: rgba(255,255,255,.85);
        }
        .slide-foto__autor {
            font-size: clamp(.75rem, 1.2vw, 1rem);
            color: rgba(255,255,255,.4);
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .15em;
            text-transform: uppercase;
        }
        .slide-foto__autor::before { content: '— '; }

        /* Slide SOLO FOTO (sin texto) */
        .slide--foto-sola { justify-content: center; align-items: center; }
        .slide--foto-sola .slide-foto__img-wrap {
            max-width: 80vw;
            max-height: 85vh;
        }
        .slide--foto-sola .slide-foto__autor-abajo {
            position: absolute;
            bottom: 5rem;
            left: 0; right: 0;
            text-align: center;
            font-size: clamp(.75rem, 1.2vw, 1rem);
            color: rgba(255,255,255,.35);
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .15em;
            text-transform: uppercase;
        }
        .slide--foto-sola .slide-foto__autor-abajo::before { content: '— '; }

        /* ── BARRA DE PROGRESO ── */
        .tv-progress {
            position: absolute;
            bottom: 0; left: 0;
            height: 2px;
            background: rgba(255,255,255,.18);
            z-index: 20;
            width: 100%;
        }
        .tv-progress__bar {
            height: 100%;
            background: rgba(255,255,255,.55);
            width: 0%;
            transition: width linear;
        }

        /* ── CONTADOR ── */
        .tv-contador {
            position: absolute;
            bottom: 1.5rem;
            right: 2.5rem;
            z-index: 20;
            font-size: .75rem;
            color: rgba(255,255,255,.2);
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .1em;
        }

        /* ── CONTROLES ── */
        .tv-controles {
            position: absolute;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 20;
            display: flex;
            gap: .75rem;
            opacity: 0;
            transition: opacity .3s;
        }
        #pantalla-tv:hover .tv-controles { opacity: 1; }
        .tv-ctrl-btn {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.15);
            color: rgba(255,255,255,.7);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem;
            transition: background .2s;
            backdrop-filter: blur(4px);
        }
        .tv-ctrl-btn:hover { background: rgba(255,255,255,.2); }

        /* ── BTN VOLVER ── */
        .btn-volver {
            position: absolute;
            top: 1.5rem; right: 2.5rem;
            z-index: 20;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            color: rgba(255,255,255,.4);
            font-size: .75rem;
            font-family: 'Segoe UI', sans-serif;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: .4rem .9rem;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            opacity: 0;
            transition: opacity .3s, color .2s;
        }
        #pantalla-tv:hover .btn-volver { opacity: 1; }
        .btn-volver:hover { color: rgba(255,255,255,.8); }

        /* ── PANTALLA VACÍA ── */
        .tv-vacio {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            z-index: 5;
        }
        .tv-vacio p { color: rgba(255,255,255,.3); font-family: 'Segoe UI', sans-serif; font-size: 1rem; letter-spacing: .1em; }
    </style>
</head>
<body>

<?php if (!$huella): ?>
<!-- ══ SELECTOR ══════════════════════════════════════════════════════════════ -->
<div id="pantalla-selector">
    <div class="selector-logo">Huella</div>
    <h1 class="selector-titulo">Pantalla memorial</h1>
    <p class="selector-sub">Selecciona el memorial que deseas mostrar</p>
    <div class="selector-grid">
        <?php foreach ($huellas as $h):
            $nom = trim(($h['Fallecido_Nombre'] ?? '') . ' ' . ($h['Fallecido_Apellido_1'] ?? '') . ' ' . ($h['Fallecido_Apellido_2'] ?? ''));
        ?>
        <a href="/huella/tv.php?link=<?= urlencode($h['link']) ?>" class="selector-btn">
            <span class="selector-btn__nombre"><?= htmlspecialchars($nom) ?></span>
            <span class="selector-btn__hint"><?= htmlspecialchars($h['link']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<!-- ══ PANTALLA TV ═══════════════════════════════════════════════════════════ -->
<div id="pantalla-tv">
    <div id="fondo-blur"></div>

    <header class="tv-header">
        <div>
            <div class="tv-header__nombre"><?= htmlspecialchars($fallecido) ?></div>
            <?php if ($fechas): ?>
            <div class="tv-header__fechas"><?= htmlspecialchars($fechas) ?></div>
            <?php endif; ?>
        </div>
        <div class="tv-header__logo">Huella</div>
    </header>

    <a href="/huella/tv.php" class="btn-volver">← Volver</a>

    <div id="slides-wrap">
    <?php if (empty($slides)): ?>
        <div class="tv-vacio">
            <p>No hay mensajes publicados en este memorial.</p>
        </div>
    <?php else: ?>

        <!-- Slide intro -->
        <div class="slide slide--intro activa" data-tipo="intro" data-fondo="">
            <div class="intro-nombre"><?= htmlspecialchars($fallecido) ?></div>
            <div class="intro-linea"></div>
            <?php if ($fechas): ?>
            <div class="intro-fechas"><?= htmlspecialchars($fechas) ?></div>
            <?php endif; ?>
        </div>

        <?php foreach ($slides as $i => $s):
            $texto  = htmlspecialchars($s['texto'] ?? '');
            $autor  = htmlspecialchars($s['autor'] ?? '');
            $fotos  = $s['fotos'];
            $fotoData = !empty($fotos) ? htmlspecialchars($fotos[0]) : '';
        ?>

        <?php if (!empty($fotos) && $texto): ?>
            <!-- Slide foto + texto -->
            <div class="slide slide--foto" data-tipo="foto" data-fondo="<?= $fotoData ?>">
                <div class="slide-foto__img-wrap">
                    <img src="<?= $fotoData ?>" alt="" loading="lazy">
                </div>
                <div class="slide-foto__info">
                    <p class="slide-foto__texto"><?= $texto ?></p>
                    <?php if ($autor): ?><p class="slide-foto__autor"><?= $autor ?></p><?php endif; ?>
                </div>
            </div>

        <?php elseif (!empty($fotos)): ?>
            <!-- Slide solo foto -->
            <div class="slide slide--foto slide--foto-sola" data-tipo="foto" data-fondo="<?= $fotoData ?>">
                <div class="slide-foto__img-wrap">
                    <img src="<?= $fotoData ?>" alt="" loading="lazy">
                </div>
                <?php if ($autor): ?><p class="slide-foto__autor-abajo"><?= $autor ?></p><?php endif; ?>
            </div>

        <?php elseif ($texto): ?>
            <!-- Slide solo texto -->
            <div class="slide slide--texto" data-tipo="texto" data-fondo="">
                <div class="slide-comilla">"</div>
                <p class="slide-texto__body"><?= nl2br($texto) ?></p>
                <?php if ($autor): ?><p class="slide-texto__autor"><?= $autor ?></p><?php endif; ?>
            </div>

        <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>
    </div>

    <div class="tv-progress"><div class="tv-progress__bar" id="progress-bar"></div></div>
    <div class="tv-contador" id="contador"></div>

    <div class="tv-controles">
        <button class="tv-ctrl-btn" onclick="anterior()" title="Anterior">&#8592;</button>
        <button class="tv-ctrl-btn" id="btn-pausa" onclick="togglePausa()" title="Pausar">&#9646;&#9646;</button>
        <button class="tv-ctrl-btn" onclick="siguiente()" title="Siguiente">&#8594;</button>
    </div>
</div>

<script>
const DURACION   = 8000;   // ms por diapositiva
const TRANSICION = 1000;   // ms fade

const slides    = Array.from(document.querySelectorAll('.slide'));
const fondoEl   = document.getElementById('fondo-blur');
const progressEl = document.getElementById('progress-bar');
const contadorEl = document.getElementById('contador');
const btnPausa  = document.getElementById('btn-pausa');

let actual    = 0;
let pausado   = false;
let timer     = null;
let progTimer = null;
let enTransicion = false;

function mostrarSlide(idx, dir = 1) {
    if (enTransicion) return;
    enTransicion = true;

    const anterior = slides[actual];
    actual = (idx + slides.length) % slides.length;
    const nueva = slides[actual];

    // Fondo blur
    const fondo = nueva.dataset.fondo;
    if (fondo) fondoEl.style.backgroundImage = `url(${fondo})`;
    else        fondoEl.style.backgroundImage = 'none';

    // Fade
    anterior.classList.remove('activa');
    nueva.style.opacity = '0';
    nueva.classList.add('activa');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            nueva.style.transition = `opacity ${TRANSICION}ms ease`;
            nueva.style.opacity    = '1';
        });
    });

    setTimeout(() => {
        anterior.style.opacity = '';
        anterior.style.transition = '';
        nueva.style.transition = '';
        nueva.style.opacity    = '';
        enTransicion = false;
    }, TRANSICION);

    // Contador
    if (contadorEl) contadorEl.textContent = `${actual + 1} / ${slides.length}`;

    // Progreso
    arrancarProgreso();
}

function arrancarProgreso() {
    clearTimeout(progTimer);
    progressEl.style.transition = 'none';
    progressEl.style.width      = '0%';

    if (pausado) return;

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            progressEl.style.transition = `width ${DURACION}ms linear`;
            progressEl.style.width      = '100%';
        });
    });

    clearTimeout(timer);
    timer = setTimeout(() => siguienteAuto(), DURACION);
}

function siguienteAuto() {
    if (!pausado) mostrarSlide(actual + 1);
}

function siguiente() { clearTimeout(timer); mostrarSlide(actual + 1); }
function anterior()  { clearTimeout(timer); mostrarSlide(actual - 1); }

function togglePausa() {
    pausado = !pausado;
    btnPausa.innerHTML = pausado ? '&#9654;' : '&#9646;&#9646;';
    if (pausado) {
        clearTimeout(timer);
        progressEl.style.transition = 'none';
    } else {
        arrancarProgreso();
    }
}

// Teclado
document.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); siguiente(); }
    if (e.key === 'ArrowLeft')                   { e.preventDefault(); anterior(); }
    if (e.key === 'p' || e.key === 'P')          togglePausa();
    if (e.key === 'f' || e.key === 'F')          document.documentElement.requestFullscreen?.();
    if (e.key === 'Escape')                      window.location.href = '/huella/tv.php';
});

// Iniciar
if (slides.length) {
    contadorEl.textContent = `1 / ${slides.length}`;
    arrancarProgreso();
}

// Fullscreen automático al hacer click en la pantalla
document.getElementById('pantalla-tv').addEventListener('dblclick', () => {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
    else document.exitFullscreen?.();
});
</script>
<?php endif; ?>

</body>
</html>
