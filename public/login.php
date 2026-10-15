<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

if (sesionActiva()) {
    $back = filter_var($_GET['back'] ?? '', FILTER_SANITIZE_URL);
    if (!$back || !str_starts_with($back, '/')) $back = '/huella/';
    header('Location: ' . $back);
    exit;
}

$tema           = cargarTema();
$cssVars        = generarVariablesCSS($tema['colores'], $tema);
$nombreProducto = $tema['nombre_producto'];
$logoPath       = asset($tema['logo_path']);
$logoAlt        = $tema['logo_alt'];
$back           = filter_var($_GET['back'] ?? '', FILTER_SANITIZE_URL) ?: '/huella/';
if (!str_starts_with($back, '/')) $back = '/huella/';

// Extraer el contexto de huella desde la URL de retorno
// Soporta: ?link=slug, ?id=slug, y rutas limpias /huella/{slug}[/muro|/actos|...]
$idHuellaVinculo = null;
$backParts = parse_url($back);
parse_str($backParts['query'] ?? '', $backParams);
$slugBack = $backParams['link'] ?? ($backParams['id'] ?? null);
if (!$slugBack) {
    // URL limpia: /huella/{slug} o /huella/{slug}/muro etc.
    if (preg_match('#^/huella/([a-zA-Z0-9_-]+)(?:/|$)#', $backParts['path'] ?? '', $m)) {
        $slugBack = $m[1];
    }
}
if ($slugBack) {
    $db  = getDB();
    $stH = $db->prepare("SELECT Id_Huella FROM h_huella WHERE (link = ? OR Id_Huella = ?) AND Ocultar = 0 LIMIT 1");
    $stH->bind_param('ss', $slugBack, $slugBack);
    $stH->execute();
    $rowH = $stH->get_result()->fetch_assoc();
    $idHuellaVinculo = $rowH['Id_Huella'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — <?= htmlspecialchars($nombreProducto) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <style>:root { <?= $cssVars ?> }</style>
    <style>
        .login-wrap {
            min-height: calc(100vh - 57px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 360px;
            background: var(--color-fondo-card);
            border-radius: var(--radio-card);
            box-shadow: var(--sombra-card);
            padding: 2rem 1.75rem 2.25rem;
        }
        .login-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: var(--color-fondo);
            border: 2px solid var(--color-borde);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            color: var(--color-texto-suave);
        }
        .login-avatar svg { width: 30px; height: 30px; }
        .login-titulo {
            font-family: var(--fuente-titulo);
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-texto);
            text-align: center;
            margin-bottom: 0.35rem;
        }
        .login-sub {
            font-size: 0.82rem;
            color: var(--color-texto-suave);
            text-align: center;
            margin-bottom: 1.75rem;
            line-height: 1.5;
        }
        .login-label {
            display: block;
            font-size: 0.74rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--color-texto-suave);
            margin-bottom: 0.4rem;
        }
        .login-input {
            width: 100%;
            padding: 0.72rem 0.9rem;
            border: 1.5px solid var(--color-borde);
            border-radius: 10px;
            font-size: 1rem;
            font-family: var(--fuente-cuerpo);
            color: var(--color-texto);
            background: var(--color-fondo);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .login-input:focus {
            border-color: var(--color-primario);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primario) 12%, transparent);
        }
        .login-input:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .login-field { margin-top: 1rem; }
        .login-btn {
            display: block;
            width: 100%;
            margin-top: 1.25rem;
            padding: 0.8rem;
            background: var(--color-primario);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: var(--fuente-cuerpo);
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .login-btn:hover    { opacity: 0.88; }
        .login-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .login-error {
            margin-top: 0.7rem;
            font-size: 0.82rem;
            color: #c0392b;
            text-align: center;
            display: none;
        }
        /* Bottom sheet modal */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.45);
            display: flex; align-items: flex-end; justify-content: center;
            z-index: 300;
            opacity: 0; pointer-events: none;
            transition: opacity 0.25s;
        }
        .modal-overlay.visible { opacity: 1; pointer-events: all; }
        .modal-sheet {
            width: 100%; max-width: 480px;
            background: var(--color-fondo-card);
            border-radius: 20px 20px 0 0;
            padding: 1.25rem 1.75rem 2.5rem;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(.4,0,.2,1);
        }
        .modal-overlay.visible .modal-sheet { transform: translateY(0); }
        .modal-handle {
            width: 36px; height: 4px;
            background: var(--color-borde);
            border-radius: 2px;
            margin: 0 auto 1.5rem;
        }
        .modal-titulo {
            font-family: var(--fuente-titulo);
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-texto);
            margin-bottom: 0.3rem;
        }
        .modal-sub {
            font-size: 0.82rem;
            color: var(--color-texto-suave);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<header class="header">
    <a href="/huella/" class="header__logo">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($logoAlt) ?>">
        <span class="header__nombre"><?= htmlspecialchars($nombreProducto) ?></span>
    </a>
</header>

<div class="login-wrap">
    <div class="login-card">
        <div class="login-avatar">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h1 class="login-titulo">Identificación</h1>
        <p class="login-sub">Introduce tu teléfono o correo electrónico para continuar</p>

        <label class="login-label" for="inp-id">Teléfono o correo electrónico</label>
        <input id="inp-id" class="login-input" type="text"
               placeholder="600 000 000 o correo@ejemplo.com"
               autocomplete="username" maxlength="100">

        <div id="paso-password" class="login-field" style="display:none;">
            <label class="login-label" for="inp-password">Contraseña</label>
            <input id="inp-password" class="login-input" type="password"
                   placeholder="Tu contraseña" autocomplete="current-password">
        </div>

        <button id="btn-continuar" class="login-btn" data-label="Continuar">Continuar</button>
        <p id="login-error" class="login-error"></p>
    </div>
</div>

<!-- Modal: nueva cuenta -->
<div id="modal-registro" class="modal-overlay" role="dialog" aria-modal="true">
    <div class="modal-sheet">
        <div class="modal-handle"></div>
        <p class="modal-titulo">Nueva cuenta</p>
        <p class="modal-sub">Es la primera vez que accedes con este número. Completa tus datos.</p>

        <label class="login-label" for="inp-nombre">Tu nombre</label>
        <input id="inp-nombre" class="login-input" type="text"
               placeholder="Nombre y apellido" autocomplete="name" maxlength="50">

        <div class="login-field">
            <label class="login-label" for="inp-reg-password">Contraseña</label>
            <input id="inp-reg-password" class="login-input" type="password"
                   placeholder="Mínimo 6 caracteres" autocomplete="new-password">
        </div>

        <div class="login-field" style="margin-top:1.1rem;">
            <label style="display:flex;align-items:flex-start;gap:0.55rem;cursor:pointer;font-size:0.84rem;color:var(--color-texto-suave);line-height:1.5;">
                <input id="chk-privacidad" type="checkbox" checked
                       style="margin-top:0.2rem;accent-color:var(--color-primario);flex-shrink:0;width:16px;height:16px;cursor:pointer;">
                <span>Acepto las <a href="/huella/politicas-de-privacidad" style="color:var(--color-primario);text-decoration:underline;text-underline-offset:2px;">políticas de privacidad</a></span>
            </label>
        </div>

        <button id="btn-registrar" class="login-btn" data-label="Crear cuenta" style="margin-top:1.25rem;">Crear cuenta</button>
        <p id="registro-error" class="login-error"></p>
    </div>
</div>

<script>
var BACK_URL  = <?= json_encode($back) ?>;
var HUELLA_ID = <?= json_encode($idHuellaVinculo) ?>;
var paso = 1;

function mostrarError(el, msg) { el.textContent = msg; el.style.display = 'block'; }
function ocultarError(el)      { el.style.display = 'none'; }
function setLoading(btn, on)   { btn.disabled = on; btn.textContent = on ? 'Cargando…' : btn.dataset.label; }

function post(body) {
    return fetch('/huella/api/auth.php', {
        method:  'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body:    body
    }).then(function(r){ return r.json(); });
}

var btnContinuar  = document.getElementById('btn-continuar');
var btnRegistrar  = document.getElementById('btn-registrar');
var loginError    = document.getElementById('login-error');
var registroError = document.getElementById('registro-error');
var modal         = document.getElementById('modal-registro');
var inpId         = document.getElementById('inp-id');
var inpPassword   = document.getElementById('inp-password');
var inpNombre     = document.getElementById('inp-nombre');
var inpRegPass    = document.getElementById('inp-reg-password');
var chkPrivacidad = document.getElementById('chk-privacidad');

// ── Device ID (UUID persistente en localStorage) ──────────────────────────
function getOrCreateDeviceId() {
    var key = 'huella_device_id';
    var id  = localStorage.getItem(key);
    if (!id) {
        id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        localStorage.setItem(key, id);
    }
    return id;
}

btnContinuar.addEventListener('click', function () {
    ocultarError(loginError);

    if (paso === 1) {
        var id = inpId.value.trim();
        if (!id) { mostrarError(loginError, 'Introduce tu teléfono o correo electrónico.'); return; }
        setLoading(btnContinuar, true);

        post('action=check&id=' + encodeURIComponent(id)
           + (HUELLA_ID ? '&id_huella=' + encodeURIComponent(HUELLA_ID) : ''))
        .then(function(data) {
            setLoading(btnContinuar, false);
            if (!data.ok) { mostrarError(loginError, data.msg); return; }

            if (data.exists) {
                paso = 2;
                document.getElementById('paso-password').style.display = 'block';
                btnContinuar.dataset.label = 'Entrar';
                btnContinuar.textContent   = 'Entrar';
                inpId.disabled = true;
                inpPassword.focus();
            } else {
                modal.classList.add('visible');
                inpNombre.focus();
            }
        })
        .catch(function() {
            setLoading(btnContinuar, false);
            mostrarError(loginError, 'Error de conexión. Inténtalo de nuevo.');
        });

    } else {
        var id   = inpId.value.trim();
        var pass = inpPassword.value;
        if (!pass) { mostrarError(loginError, 'Introduce tu contraseña.'); return; }
        setLoading(btnContinuar, true);

        post('action=login&id=' + encodeURIComponent(id) + '&password=' + encodeURIComponent(pass)
           + (HUELLA_ID ? '&id_huella=' + encodeURIComponent(HUELLA_ID) : ''))
        .then(function(d) {
            setLoading(btnContinuar, false);
            if (d.ok) { sessionStorage.setItem('huella_tab', '1'); window.location.href = BACK_URL; }
            else { mostrarError(loginError, d.msg); }
        })
        .catch(function() {
            setLoading(btnContinuar, false);
            mostrarError(loginError, 'Error de conexión. Inténtalo de nuevo.');
        });
    }
});

btnRegistrar.addEventListener('click', function () {
    var id     = inpId.value.trim();
    var nombre = inpNombre.value.trim();
    var pass   = inpRegPass.value;
    if (!nombre) { mostrarError(registroError, 'Introduce tu nombre.'); return; }
    if (!pass)   { mostrarError(registroError, 'Introduce una contraseña.'); return; }
    if (!chkPrivacidad.checked) { mostrarError(registroError, 'Debes aceptar las políticas de privacidad.'); return; }
    ocultarError(registroError);
    setLoading(btnRegistrar, true);

    post('action=register&id='  + encodeURIComponent(id)
       + '&nombre='             + encodeURIComponent(nombre)
       + '&password='           + encodeURIComponent(pass)
       + '&device_id='          + encodeURIComponent(getOrCreateDeviceId())
       + (HUELLA_ID ? '&id_huella=' + encodeURIComponent(HUELLA_ID) : ''))
    .then(function(data) {
        setLoading(btnRegistrar, false);
        if (data.ok) { sessionStorage.setItem('huella_tab', '1'); window.location.href = BACK_URL; }
        else { mostrarError(registroError, data.msg); }
    })
    .catch(function() {
        setLoading(btnRegistrar, false);
        mostrarError(registroError, 'Error de conexión. Inténtalo de nuevo.');
    });
});

modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.remove('visible'); });
inpId.addEventListener('keydown',       function(e) { if (e.key === 'Enter') btnContinuar.click(); });
inpPassword.addEventListener('keydown', function(e) { if (e.key === 'Enter') btnContinuar.click(); });
inpNombre.addEventListener('keydown',   function(e) { if (e.key === 'Enter') inpRegPass.focus(); });
inpRegPass.addEventListener('keydown',  function(e) { if (e.key === 'Enter') btnRegistrar.click(); });
</script>
</body>
</html>
