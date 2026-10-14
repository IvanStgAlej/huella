<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if (!sesionActiva()) {
    header('Location: /huella/login.php?back=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
if (!esAdminDeAlgunaHuella()) {
    header('Location: /huella/');
    exit;
}

// Cargar todos los clientes existentes para el selector
$db       = getDB();
$clientes = $db->query("SELECT Id_Cliente, Literal FROM h_clientesconfig ORDER BY Literal")->fetch_all(MYSQLI_ASSOC);
$tema     = cargarTema();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de estilos — Huella</title>
    <?php require __DIR__ . '/includes/session_guard.php'; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 14px;
            background: #f0f0f0;
            color: #1a1a1a;
            min-height: 100vh;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: #1a1a1a;
            color: #fff;
            padding: 0 1.5rem;
            height: 52px;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar__titulo { font-size: 1rem; font-weight: 700; letter-spacing: .02em; }
        .topbar__sep    { flex: 1; }
        .topbar__badge  {
            font-size: .72rem;
            background: #3d3d3d;
            border-radius: 20px;
            padding: .2rem .65rem;
            color: #ccc;
        }

        /* ── LAYOUT ── */
        .layout {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 0;
            min-height: calc(100vh - 52px);
        }

        /* ── PANEL IZQUIERDO (formulario) ── */
        .panel-form {
            background: #fff;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .panel-form__header {
            padding: 1.25rem 1.5rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
        }

        .selector-cliente {
            display: flex;
            gap: .5rem;
            align-items: center;
        }
        .selector-cliente select,
        .selector-cliente input {
            flex: 1;
            padding: .45rem .65rem;
            border: 1.5px solid #d0d0d0;
            border-radius: 8px;
            font-size: .85rem;
            font-family: inherit;
            color: #1a1a1a;
            background: #fafafa;
            outline: none;
        }
        .selector-cliente select:focus,
        .selector-cliente input:focus { border-color: #555; }

        .btn-nuevo {
            padding: .45rem .8rem;
            background: #1a1a1a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-nuevo:hover { background: #333; }

        /* ── SECCIONES DEL FORM ── */
        .panel-form__body { padding: 1rem 1.5rem 2rem; flex: 1; }

        .seccion {
            margin-bottom: 1.75rem;
        }
        .seccion__titulo {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #888;
            margin-bottom: .85rem;
            padding-bottom: .4rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .campo { margin-bottom: .85rem; }
        .campo label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: #555;
            margin-bottom: .3rem;
        }
        .campo input[type="text"],
        .campo input[type="number"] {
            width: 100%;
            padding: .45rem .65rem;
            border: 1.5px solid #d8d8d8;
            border-radius: 8px;
            font-size: .85rem;
            font-family: inherit;
            color: #1a1a1a;
            background: #fafafa;
            outline: none;
            transition: border-color .15s;
        }
        .campo input[type="text"]:focus,
        .campo input[type="number"]:focus { border-color: #555; background: #fff; }

        /* ── CAMPO COLOR ── */
        .campo-color {
            display: grid;
            grid-template-columns: 36px 1fr;
            gap: .5rem;
            align-items: center;
            margin-bottom: .75rem;
        }
        .campo-color label {
            grid-column: 1 / -1;
            font-size: .75rem;
            font-weight: 600;
            color: #555;
            margin-bottom: .25rem;
        }
        .campo-color input[type="color"] {
            width: 36px;
            height: 36px;
            padding: 2px;
            border: 1.5px solid #d8d8d8;
            border-radius: 8px;
            cursor: pointer;
            background: #fff;
        }
        .campo-color input[type="text"] {
            padding: .4rem .65rem;
            border: 1.5px solid #d8d8d8;
            border-radius: 8px;
            font-size: .85rem;
            font-family: 'Courier New', monospace;
            color: #1a1a1a;
            background: #fafafa;
            outline: none;
            width: 100%;
        }
        .campo-color input[type="text"]:focus { border-color: #555; background: #fff; }

        /* ── FOOTER FORMULARIO ── */
        .panel-form__footer {
            padding: 1rem 1.5rem 1.25rem;
            border-top: 1px solid #f0f0f0;
            position: sticky;
            bottom: 0;
            background: #fff;
        }
        .btn-guardar {
            width: 100%;
            padding: .75rem;
            background: #1a1a1a;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s, opacity .15s;
            letter-spacing: .02em;
        }
        .btn-guardar:hover    { background: #333; }
        .btn-guardar:disabled { opacity: .5; cursor: not-allowed; }

        .toast {
            margin-top: .65rem;
            font-size: .8rem;
            text-align: center;
            padding: .45rem;
            border-radius: 8px;
            display: none;
        }
        .toast--ok  { background: #e6f9ee; color: #1a6b3c; }
        .toast--err { background: #fdecea; color: #b71c1c; }

        /* ── PANEL DERECHO (preview) ── */
        .panel-preview {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .panel-preview__header {
            padding: .75rem 1.5rem;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #888;
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .preview-ancho {
            display: flex;
            gap: .35rem;
        }
        .preview-ancho button {
            padding: .25rem .55rem;
            border: 1.5px solid #d0d0d0;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 600;
            cursor: pointer;
            background: #fafafa;
            color: #555;
        }
        .preview-ancho button.active {
            background: #1a1a1a;
            color: #fff;
            border-color: #1a1a1a;
        }

        .panel-preview__body {
            flex: 1;
            overflow: auto;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 1.5rem;
            background: #e5e5e5;
        }

        /* ── PREVIEW CARD ── */
        #preview-wrapper {
            width: 100%;
            max-width: 100%;
            transition: max-width .3s;
        }
        #preview-wrapper.ancho-movil  { max-width: 560px; }
        #preview-wrapper.ancho-tablet { max-width: 820px; }
        #preview-wrapper.ancho-full   { max-width: 100%; }

        .preview-app {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0,0,0,.18);
        }
        .preview-header {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .75rem 1rem;
            background: var(--prev-fondo-card);
            border-bottom: 1px solid var(--prev-borde);
        }
        .preview-header__logo {
            width: 28px; height: 28px;
            border-radius: 6px;
            background: var(--prev-primario);
            opacity: .85;
        }
        .preview-header__nombre {
            font-family: var(--prev-fuente-titulo);
            font-size: .9rem;
            font-weight: 700;
            color: var(--prev-texto);
        }
        .preview-body { background: var(--prev-fondo); padding: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .preview-card {
            background: var(--prev-fondo-card);
            border-radius: var(--prev-radio);
            box-shadow: var(--prev-sombra);
            padding: 1.25rem;
            grid-column: 1 / -1;
        }
        .preview-card__titulo {
            font-family: var(--prev-fuente-titulo);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--prev-texto);
            margin-bottom: .35rem;
        }
        .preview-card__subtitulo {
            font-size: .8rem;
            color: var(--prev-texto-suave);
            margin-bottom: .75rem;
        }
        .preview-btn {
            display: inline-block;
            padding: .45rem 1rem;
            background: var(--prev-primario);
            color: #fff;
            border-radius: 20px;
            font-size: .8rem;
            font-weight: 600;
            font-family: var(--prev-fuente-cuerpo);
        }
        .preview-btn--outline {
            background: transparent;
            color: var(--prev-primario);
            border: 2px solid var(--prev-primario);
            margin-left: .4rem;
        }
        .preview-post {
            background: var(--prev-fondo-card);
            border-radius: var(--prev-radio);
            box-shadow: var(--prev-sombra);
            padding: 1rem 1.1rem;
            border-left: 3px solid var(--prev-acento);
        }
        .preview-post__autor {
            font-size: .75rem;
            font-weight: 700;
            color: var(--prev-primario);
            margin-bottom: .2rem;
        }
        .preview-post__texto {
            font-size: .82rem;
            color: var(--prev-texto);
            line-height: 1.5;
        }
        .preview-tag {
            display: inline-block;
            font-size: .7rem;
            padding: .15rem .5rem;
            border-radius: 20px;
            background: var(--prev-secundario);
            color: #fff;
            margin-top: .5rem;
        }
        .preview-swatches {
            display: flex;
            gap: .4rem;
            flex-wrap: wrap;
            margin-top: .65rem;
        }
        .preview-swatch {
            width: 28px; height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0,0,0,.1);
            flex-shrink: 0;
        }
        .preview-nav {
            display: flex;
            background: var(--prev-fondo-card);
            border-top: 1px solid var(--prev-borde);
            padding: .5rem 0 .25rem;
        }
        .preview-nav__item {
            flex: 1;
            text-align: center;
            font-size: .65rem;
            color: var(--prev-texto-suave);
            padding: .25rem 0;
        }
        .preview-nav__item--active { color: var(--prev-primario); font-weight: 700; }
        .preview-nav__dot {
            width: 18px; height: 18px;
            border-radius: 50%;
            background: var(--prev-primario);
            opacity: .25;
            margin: 0 auto .2rem;
        }
        .preview-nav__item--active .preview-nav__dot { opacity: 1; }
    </style>
</head>
<body>

<div class="topbar">
    <span class="topbar__titulo">Panel de estilos</span>
    <span class="topbar__sep"></span>
    <span class="topbar__badge">h_clientesconfig</span>
</div>

<div class="layout">

    <!-- ══ FORMULARIO ══ -->
    <aside class="panel-form">

        <div class="panel-form__header">
            <div class="selector-cliente">
                <select id="sel-cliente" onchange="cargarCliente(this.value)">
                    <option value="">— Nuevo cliente —</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= htmlspecialchars($c['Id_Cliente']) ?>">
                        <?= htmlspecialchars($c['Literal'] ?: $c['Id_Cliente']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-nuevo" onclick="nuevoCliente()">+ Nuevo</button>
            </div>
        </div>

        <div class="panel-form__body">

            <!-- Identificación -->
            <div class="seccion">
                <div class="seccion__titulo">Identificación</div>
                <div class="campo">
                    <label for="f-id">ID Cliente <span style="color:#aaa;font-weight:400">(clave primaria)</span></label>
                    <input type="text" id="f-id" placeholder="Ej: 0000000001" maxlength="10" oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-literal">Nombre del cliente</label>
                    <input type="text" id="f-literal" placeholder="Ej: Funeraria García" oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-nombre-producto">Nombre del producto</label>
                    <input type="text" id="f-nombre-producto" placeholder="Ej: Funeraria Demo" oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-logo-path">Ruta del logo <span style="color:#aaa;font-weight:400">(relativa a /huella/)</span></label>
                    <input type="text" id="f-logo-path" placeholder="uploads/assets/logo.jpg" oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-logo-alt">Alt del logo</label>
                    <input type="text" id="f-logo-alt" placeholder="Logo funeraria" oninput="actualizarPreview()">
                </div>
            </div>

            <!-- Colores -->
            <div class="seccion">
                <div class="seccion__titulo">Colores</div>

                <?php
                $coloresDef = [
                    'color_primario'   => ['Primario',    '#3d2b1f'],
                    'color_secundario' => ['Secundario',  '#7a5c4e'],
                    'color_acento'     => ['Acento',      '#c4a882'],
                    'color_fondo'      => ['Fondo',       '#f7f4f1'],
                    'color_fondo_card' => ['Fondo card',  '#ffffff'],
                    'color_texto'      => ['Texto',       '#2c2c2c'],
                    'color_texto_suave'=> ['Texto suave', '#6b6b6b'],
                    'color_borde'      => ['Borde',       '#e0d8d0'],
                ];
                foreach ($coloresDef as $key => [$label, $default]): ?>
                <div class="campo-color">
                    <label><?= $label ?></label>
                    <input type="color" id="picker-<?= $key ?>"
                           value="<?= $default ?>"
                           oninput="sincronizarColor('<?= $key ?>', this.value)">
                    <input type="text"  id="txt-<?= $key ?>"
                           value="<?= $default ?>" maxlength="30"
                           placeholder="<?= $default ?>"
                           oninput="sincronizarDesdeTexto('<?= $key ?>', this.value)">
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tipografía -->
            <div class="seccion">
                <div class="seccion__titulo">Tipografía</div>
                <div class="campo">
                    <label for="f-fuente-titulo">Fuente títulos</label>
                    <input type="text" id="f-fuente-titulo"
                           value="'Georgia', 'Times New Roman', serif"
                           placeholder="'Georgia', serif"
                           oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-fuente-cuerpo">Fuente cuerpo</label>
                    <input type="text" id="f-fuente-cuerpo"
                           value="'Segoe UI', Arial, sans-serif"
                           placeholder="'Segoe UI', sans-serif"
                           oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-tamanio">Tamaño base</label>
                    <input type="text" id="f-tamanio"
                           value="16px"
                           placeholder="16px"
                           oninput="actualizarPreview()">
                </div>
            </div>

            <!-- Layout -->
            <div class="seccion">
                <div class="seccion__titulo">Layout</div>
                <div class="campo">
                    <label for="f-max-ancho">Ancho máximo contenido</label>
                    <input type="text" id="f-max-ancho"
                           value="480px"
                           placeholder="480px"
                           oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-radio">Radio de tarjeta</label>
                    <input type="text" id="f-radio"
                           value="16px"
                           placeholder="16px"
                           oninput="actualizarPreview()">
                </div>
                <div class="campo">
                    <label for="f-sombra">Sombra de tarjeta</label>
                    <input type="text" id="f-sombra"
                           value="0 2px 12px rgba(0,0,0,0.08)"
                           placeholder="0 2px 12px rgba(0,0,0,0.08)"
                           oninput="actualizarPreview()">
                </div>
            </div>

        </div>

        <div class="panel-form__footer">
            <button class="btn-guardar" id="btn-guardar" onclick="guardar()">Guardar estilo</button>
            <div class="toast" id="toast"></div>
        </div>

    </aside>

    <!-- ══ PREVIEW ══ -->
    <section class="panel-preview">
        <div class="panel-preview__header">
            Vista previa
            <div class="preview-ancho">
                <button onclick="setAncho('movil',  this)">Móvil</button>
                <button onclick="setAncho('tablet', this)">Tablet</button>
                <button onclick="setAncho('full',   this)" class="active">Completo</button>
            </div>
        </div>
        <div class="panel-preview__body">
            <div id="preview-wrapper" class="ancho-full">
                <div class="preview-app" id="preview-app">
                    <div class="preview-header">
                        <div class="preview-header__logo"></div>
                        <span class="preview-header__nombre" id="prev-nombre-producto">Huella</span>
                    </div>
                    <div class="preview-body">
                        <div class="preview-card">
                            <div class="preview-card__titulo">María González López</div>
                            <div class="preview-card__subtitulo">12 enero 1945 — 3 mayo 2026</div>
                            <span class="preview-btn">Muro de condolencias</span>
                            <span class="preview-btn preview-btn--outline">Actos</span>
                            <div class="preview-swatches" id="prev-swatches"></div>
                        </div>
                        <div class="preview-post">
                            <div class="preview-post__autor">Ana Martínez</div>
                            <div class="preview-post__texto">Siempre te recordaremos con una sonrisa. Descansa en paz.</div>
                            <span class="preview-tag">Condolencia</span>
                        </div>
                        <div class="preview-post">
                            <div class="preview-post__autor">Carlos Ruiz</div>
                            <div class="preview-post__texto">Un abrazo enorme para toda la familia en estos momentos difíciles.</div>
                        </div>
                    </div>
                    <div class="preview-nav">
                        <div class="preview-nav__item">
                            <div class="preview-nav__dot"></div>
                            Inicio
                        </div>
                        <div class="preview-nav__item">
                            <div class="preview-nav__dot"></div>
                            Actos
                        </div>
                        <div class="preview-nav__item preview-nav__item--active">
                            <div class="preview-nav__dot"></div>
                            Muro
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>

<script>
// ── Datos de clientes cargados desde PHP ──────────────────────────────────────
const CLIENTES_DATA = <?= json_encode(
    array_column(
        $db->query("SELECT * FROM h_clientesconfig")->fetch_all(MYSQLI_ASSOC),
        null,
        'Id_Cliente'
    )
) ?>;

const DEFAULTS = {
    color_primario:    '#3d2b1f',
    color_secundario:  '#7a5c4e',
    color_acento:      '#c4a882',
    color_fondo:       '#f7f4f1',
    color_fondo_card:  '#ffffff',
    color_texto:       '#2c2c2c',
    color_texto_suave: '#6b6b6b',
    color_borde:       '#e0d8d0',
    fuente_titulo:     "'Georgia', 'Times New Roman', serif",
    fuente_cuerpo:     "'Segoe UI', 'Helvetica Neue', Arial, sans-serif",
    tamanio_base:      '16px',
    max_ancho_contenido: '480px',
    radio_tarjeta:     '16px',
    sombra_tarjeta:    '0 2px 12px rgba(0,0,0,0.08)',
};

const COLORES = ['color_primario','color_secundario','color_acento','color_fondo',
                 'color_fondo_card','color_texto','color_texto_suave','color_borde'];

// ── Cargar cliente seleccionado ───────────────────────────────────────────────
function cargarCliente(id) {
    if (!id) { nuevoCliente(); return; }
    const c = CLIENTES_DATA[id] || {};
    document.getElementById('f-id').value             = id;
    document.getElementById('f-literal').value        = c.Literal           || '';
    document.getElementById('f-nombre-producto').value= c.nombre_producto   || '';
    document.getElementById('f-logo-path').value      = c.logo_path         || '';
    document.getElementById('f-logo-alt').value       = c.logo_alt          || '';
    document.getElementById('f-fuente-titulo').value  = c.fuente_titulo     || DEFAULTS.fuente_titulo;
    document.getElementById('f-fuente-cuerpo').value  = c.fuente_cuerpo     || DEFAULTS.fuente_cuerpo;
    document.getElementById('f-tamanio').value        = c.tamanio_base      || DEFAULTS.tamanio_base;
    document.getElementById('f-max-ancho').value      = c.max_ancho_contenido || DEFAULTS.max_ancho_contenido;
    document.getElementById('f-radio').value          = c.radio_tarjeta     || DEFAULTS.radio_tarjeta;
    document.getElementById('f-sombra').value         = c.sombra_tarjeta    || DEFAULTS.sombra_tarjeta;

    COLORES.forEach(k => {
        const val = c[k] || DEFAULTS[k];
        const picker = document.getElementById('picker-' + k);
        const txt    = document.getElementById('txt-' + k);
        if (txt)    txt.value    = val;
        if (picker && esColorHex(val)) picker.value = val;
    });

    actualizarPreview();
}

function nuevoCliente() {
    document.getElementById('sel-cliente').value = '';
    document.getElementById('f-id').value             = '';
    document.getElementById('f-literal').value        = '';
    document.getElementById('f-nombre-producto').value= '';
    document.getElementById('f-logo-path').value      = '';
    document.getElementById('f-logo-alt').value       = '';
    document.getElementById('f-fuente-titulo').value  = DEFAULTS.fuente_titulo;
    document.getElementById('f-fuente-cuerpo').value  = DEFAULTS.fuente_cuerpo;
    document.getElementById('f-tamanio').value        = DEFAULTS.tamanio_base;
    document.getElementById('f-max-ancho').value      = DEFAULTS.max_ancho_contenido;
    document.getElementById('f-radio').value          = DEFAULTS.radio_tarjeta;
    document.getElementById('f-sombra').value         = DEFAULTS.sombra_tarjeta;
    COLORES.forEach(k => {
        const picker = document.getElementById('picker-' + k);
        const txt    = document.getElementById('txt-' + k);
        if (txt)    txt.value    = DEFAULTS[k];
        if (picker) picker.value = DEFAULTS[k];
    });
    actualizarPreview();
}

// ── Sincronizar color picker ↔ texto ─────────────────────────────────────────
function sincronizarColor(key, val) {
    const txt = document.getElementById('txt-' + key);
    if (txt) txt.value = val;
    actualizarPreview();
}

function sincronizarDesdeTexto(key, val) {
    const picker = document.getElementById('picker-' + key);
    if (picker && esColorHex(val)) picker.value = val;
    actualizarPreview();
}

function esColorHex(v) {
    return /^#[0-9a-fA-F]{3,8}$/.test(v.trim());
}

// ── Actualizar preview ────────────────────────────────────────────────────────
function actualizarPreview() {
    const app = document.getElementById('preview-app');
    if (!app) return;

    const get = id => document.getElementById(id)?.value?.trim() || '';

    const vars = {
        '--prev-primario':    get('txt-color_primario')    || DEFAULTS.color_primario,
        '--prev-secundario':  get('txt-color_secundario')  || DEFAULTS.color_secundario,
        '--prev-acento':      get('txt-color_acento')      || DEFAULTS.color_acento,
        '--prev-fondo':       get('txt-color_fondo')       || DEFAULTS.color_fondo,
        '--prev-fondo-card':  get('txt-color_fondo_card')  || DEFAULTS.color_fondo_card,
        '--prev-texto':       get('txt-color_texto')       || DEFAULTS.color_texto,
        '--prev-texto-suave': get('txt-color_texto_suave') || DEFAULTS.color_texto_suave,
        '--prev-borde':       get('txt-color_borde')       || DEFAULTS.color_borde,
        '--prev-fuente-titulo': get('f-fuente-titulo')     || DEFAULTS.fuente_titulo,
        '--prev-fuente-cuerpo': get('f-fuente-cuerpo')     || DEFAULTS.fuente_cuerpo,
        '--prev-radio':       get('f-radio')               || DEFAULTS.radio_tarjeta,
        '--prev-sombra':      get('f-sombra')              || DEFAULTS.sombra_tarjeta,
    };

    for (const [prop, val] of Object.entries(vars)) {
        app.style.setProperty(prop, val);
    }

    // Nombre del producto
    const nombrePrev = document.getElementById('prev-nombre-producto');
    if (nombrePrev) nombrePrev.textContent = get('f-nombre-producto') || 'Huella';

    // Swatches de color
    const swatchesEl = document.getElementById('prev-swatches');
    if (swatchesEl) {
        swatchesEl.innerHTML = COLORES.map(k =>
            `<div class="preview-swatch" style="background:${get('txt-' + k) || DEFAULTS[k]}" title="${k}"></div>`
        ).join('');
    }
}

// ── Guardar ───────────────────────────────────────────────────────────────────
async function guardar() {
    const get = id => document.getElementById(id)?.value?.trim() || '';

    const idCliente = get('f-id');
    if (!idCliente) { mostrarToast('Introduce un ID de cliente.', false); return; }

    const payload = {
        id_cliente:          idCliente,
        literal:             get('f-literal'),
        nombre_producto:     get('f-nombre-producto'),
        logo_path:           get('f-logo-path'),
        logo_alt:            get('f-logo-alt'),
        color_primario:      get('txt-color_primario'),
        color_secundario:    get('txt-color_secundario'),
        color_acento:        get('txt-color_acento'),
        color_fondo:         get('txt-color_fondo'),
        color_fondo_card:    get('txt-color_fondo_card'),
        color_texto:         get('txt-color_texto'),
        color_texto_suave:   get('txt-color_texto_suave'),
        color_borde:         get('txt-color_borde'),
        fuente_titulo:       get('f-fuente-titulo'),
        fuente_cuerpo:       get('f-fuente-cuerpo'),
        tamanio_base:        get('f-tamanio'),
        max_ancho_contenido: get('f-max-ancho'),
        radio_tarjeta:       get('f-radio'),
        sombra_tarjeta:      get('f-sombra'),
    };

    const btn = document.getElementById('btn-guardar');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    try {
        const res  = await fetch('/huella/panel_estilos_api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.ok) {
            mostrarToast(data.msg || 'Guardado correctamente.', true);
            // Actualizar datos en memoria y selector
            CLIENTES_DATA[idCliente] = {
                Id_Cliente: idCliente,
                Literal:    payload.literal,
                ...payload
            };
            actualizarSelector(idCliente, payload.literal || idCliente);
        } else {
            mostrarToast(data.msg || 'Error al guardar.', false);
        }
    } catch (e) {
        mostrarToast('Error de conexión.', false);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar estilo';
    }
}

function actualizarSelector(id, literal) {
    const sel = document.getElementById('sel-cliente');
    let opt = sel.querySelector(`option[value="${id}"]`);
    if (!opt) {
        opt = document.createElement('option');
        opt.value = id;
        sel.appendChild(opt);
    }
    opt.textContent = literal || id;
    sel.value = id;
}

function mostrarToast(msg, ok) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast ' + (ok ? 'toast--ok' : 'toast--err');
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
}

// ── Ancho del preview ─────────────────────────────────────────────────────────
function setAncho(clase, btn) {
    document.getElementById('preview-wrapper').className = 'ancho-' + clase;
    document.querySelectorAll('.preview-ancho button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// ── Inicializar ───────────────────────────────────────────────────────────────
actualizarPreview();
</script>

</body>
</html>
