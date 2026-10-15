<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

$tema    = cargarTema();
$huellas = listarHuellas();

// Resuelve la huella por link (URL amigable)
$linkActual = $_GET['link'] ?? null;
if ($linkActual) {
    $huella = obtenerHuellaByLink($linkActual);
} else {
    $linkActual = $huellas[0]['link'] ?? null;
    $huella     = $linkActual ? obtenerHuellaByLink($linkActual) : null;
}

$idIdioma = usuarioActual()['idioma'] ?? ($huella['Id_Idioma'] ?? 'CAS');
$lang     = cargarIdioma($idIdioma);
if ($huella) $tema = obtenerTemaCliente($huella['Id_Cliente'], $tema);
$colores  = $tema['colores'];
$cssVars  = generarVariablesCSS($colores, $tema);

$nombreProducto = $tema['nombre_producto'];
$logoPath       = asset($tema['logo_path']);
$logoAlt        = $tema['logo_alt'];

$uActual  = usuarioActual();
$logueado = !empty($uActual);

$actos = [];
$asistencia = [];
$nombreFallecido = '';

if ($huella) {
    $actos = obtenerActos($huella['Id_Huella'], $huella['Id_Cliente'], $huella['Empresa']);
    $asistencia = obtenerAsistencia($huella['Id_Huella'], $uActual['id'] ?? null);
    $nombreFallecido = trim(
        ($huella['Fallecido_Nombre'] ?? '') . ' ' .
        ($huella['Fallecido_Apellido_1'] ?? '') . ' ' .
        ($huella['Fallecido_Apellido_2'] ?? '')
    );
}
?>
<!DOCTYPE html>
<html lang="<?= strtolower($idIdioma) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['actividades'] ?> — <?= htmlspecialchars($nombreFallecido ?: $nombreProducto) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/actos.css') ?>">
    <?php require __DIR__ . '/../includes/session_guard.php'; ?>
    <style>:root { <?= $cssVars ?> }</style>
</head>
<body>

<!-- CABECERA -->
<header class="header">
    <a href="<?= urlHuella($linkActual ?? '') ?>" class="header__logo">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($logoAlt) ?>">
        <span class="header__nombre"><?= htmlspecialchars($nombreProducto) ?></span>
    </a>
    <div class="header__acciones">
        <a href="https://www.interflora.es" target="_blank" rel="noopener noreferrer" class="btn-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22v-5" stroke="#4a7c59" stroke-width="2"/><path d="M6 11c0-4 3-8 6-8s6 4 6 8c0 3.3-2.7 6-6 6s-6-2.7-6-6z" fill="#e8a0b4" stroke="#d4607a" stroke-width="1.5"/></svg>
            <?= $lang['enviar_flores'] ?>
        </a>
        <a href="#" class="btn-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            <?= $lang['compartir'] ?>
        </a>
        <?php if ($uActual): ?>
        <?= renderMenuUsuario($uActual, $_SERVER['REQUEST_URI']) ?>
        <?php else: ?>
        <a href="/huella/login.php?back=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
           class="btn-icon" title="Iniciar sesión">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>
    </div>
</header>

<!-- CONTENIDO -->
<main class="main">

    <!-- SELECTOR DE PRUEBA -->
    <?php if (!empty($huellas)): ?>
    <div class="selector-prueba" hidden>
        <strong>⚙️ <?= $lang['selector_prueba'] ?>:</strong>
        <select onchange="window.location='/huella/logout.php?back='+encodeURIComponent('/huella/'+this.value)">
            <?php foreach ($huellas as $h): ?>
                <?php $nombreH = trim($h['Fallecido_Nombre'].' '.$h['Fallecido_Apellido_1'].' '.$h['Fallecido_Apellido_2']); ?>
                <option value="<?= htmlspecialchars($h['link']) ?>" <?= $h['link'] === $linkActual ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nombreH) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <?php if (!$huella): ?>
        <div class="card card-bio"><p><?= $lang['error_huella'] ?></p></div>
    <?php else: ?>

    <!-- CABECERA DE SECCIÓN -->
    <div class="actos-header">
        <div class="actos-header__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M10 4h4"/><path d="M5 22V10l7-6 7 6v12H5z"/><rect x="9" y="15" width="6" height="7"/></svg>
        </div>
        <div>
            <h1 class="actos-header__titulo"><?= $lang['actividades'] ?></h1>
            <?php if ($nombreFallecido): ?>
                <p class="actos-header__subtitulo"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($actos)): ?>
        <!-- Sin actos -->
        <div class="card actos-vacio">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p>No hay eventos programados</p>
        </div>
    <?php else: ?>

        <?php foreach ($actos as $acto): ?>
        <div class="card acto-card">

            <!-- Cabecera del acto -->
            <div class="acto-card__header">
                <div class="acto-card__icono">
                    <?= iconoActo($acto['Id_TareaBase']) ?>
                </div>
                <div class="acto-card__tipo-wrap">
                    <span class="acto-card__tipo"><?= htmlspecialchars($lang['acto_' . $acto['Id_TareaBase']] ?? $acto['Tipo_Literal'] ?? $acto['Id_TareaBase']) ?></span>
                </div>
                <!-- Acciones rápidas -->
                <div class="acto-card__acciones">
                    <?php if (!empty($acto['Lugar_Teléfono'])): ?>
                    <a href="tel:<?= htmlspecialchars($acto['Lugar_Teléfono']) ?>" class="acto-accion" title="Llamar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.38 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.69a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($acto['Lugar_Ubicacion'])): ?>
                    <a href="<?= htmlspecialchars($acto['Lugar_Ubicacion']) ?>" target="_blank" class="acto-accion" title="Ver en mapa">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lugar -->
            <?php if (!empty($acto['Lugar_Direccion'])): ?>
            <div class="acto-card__lugar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?php $lugarParts = preg_split('/\s*\x{2014}\s*/u', $acto['Lugar_Direccion'], 2); $lugarNombre = $lugarParts[0]; $lugarDir = $lugarParts[1] ?? null; ?>
                <span class="acto-card__lugar-texto">
                    <div class="acto-card__lugar-nombre__div">
                        <span class="acto-card__lugar-nombre"><?= htmlspecialchars($lugarNombre) ?></span>
                    </div>

                    <div class="acto-card__lugar-dir__div">
                        <?php if ($lugarDir): ?>
                        <span class="acto-card__lugar-dir"><?= htmlspecialchars($lugarDir) ?></span>
                        <?php endif; ?>
                    </div>
                </span>
            </div>
            <?php endif; ?>

            <!-- Sala -->
            <?php if (!empty($acto['Act_Sala'])): ?>
            <div class="acto-card__sala">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                <?= htmlspecialchars($acto['Act_Sala']) ?>
            </div>
            <?php endif; ?>

            <!-- Fechas -->
            <div class="acto-card__fechas">
                <div class="acto-card__fecha">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <div>
                        <span class="acto-card__fecha-label"><?= $lang['inicio'] ?>:</span>
                        <?= formatearFechaHora($acto['Act_FechaHora_Inicio'], $idIdioma) ?>
                    </div>
                </div>
                <?php if (!empty($acto['Act_FechaHora_Fin'])): ?>
                <div class="acto-card__fecha">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <div>
                        <span class="acto-card__fecha-label"><?= $lang['fin'] ?>:</span>
                        <?= formatearFechaHora($acto['Act_FechaHora_Fin'], $idIdioma) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Texto adicional -->
            <?php if (!empty($acto['Act_Texto'])): ?>
            <div class="acto-card__texto">
                <?= htmlspecialchars($acto['Act_Texto']) ?>
            </div>
            <?php endif; ?>

            <!-- Asistencia -->
            <?php
            $idTarea = $acto['Id_TareaBase'];
            $asi = $asistencia[$idTarea] ?? ['si' => 0, 'no' => 0, 'voto' => null];
            ?>
            <div class="acto-asistencia"
                 data-huella="<?= htmlspecialchars($huella['Id_Huella']) ?>"
                 data-tarea="<?= htmlspecialchars($idTarea) ?>">
                <span class="acto-asistencia__label"><?= $lang['confirma_asistencia'] ?></span>
                <div class="acto-asistencia__btns">
                    <button type="button"
                            class="btn-asistir<?= $asi['voto'] === 1 ? ' activo' : '' ?>"
                            onclick="votarAsistencia(this, 1)"
                            title="<?= $lang['asistire'] ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    <button type="button"
                            class="btn-no-asistir<?= $asi['voto'] === 0 ? ' activo' : '' ?>"
                            onclick="votarAsistencia(this, 0)"
                            title="<?= $lang['no_asistire'] ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

        </div>
        <?php endforeach; ?>

    <?php endif; ?>
    <?php endif; ?>

    <div style="height:5rem"></div>

</main>

<!-- NAVEGACIÓN INFERIOR STICKY -->
<nav class="nav-bottom">
    <a href="<?= urlHuella($linkActual ?? '') ?>" class="nav-bottom__item">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
        <?= $lang['inicio'] ?>
    </a>
    <a href="<?= urlHuella($linkActual ?? '', 'actos') ?>" class="nav-bottom__item active">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M10 4h4"/><path d="M5 22V10l7-6 7 6v12H5z"/><rect x="9" y="15" width="6" height="7"/></svg></span>
        <?= $lang['actividades'] ?>
    </a>
    <?php if ($huella && ($huella['AutorizarMuro_SN'] ?? false)): ?>
    <a href="<?= urlHuella($linkActual ?? '', 'muro') ?>" class="nav-bottom__item nav-bottom__item--mensajes">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
        <?= $lang['muro'] ?>
    </a>
    <?php endif; ?>
    <?php if ($huella && esAdminHuella($huella['Id_Huella'])): ?>
    <a href="<?= urlHuella($linkActual ?? '', 'completar') ?>" class="nav-bottom__item">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
        <?= $lang['completar'] ?>
    </a>
    <?php endif; ?>
</nav>

<!-- Modal login asistencia -->
<div id="modal-login-asi" class="modal-login-asi" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-login-asi__box">
        <p><?= htmlspecialchars($lang['asistencia_login']) ?></p>
        <a href="/huella/login.php?back=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn-login-asi">
            <?= htmlspecialchars($lang['iniciar_sesion']) ?>
        </a>
        <button type="button" class="btn-cancel-asi"
                onclick="document.getElementById('modal-login-asi').classList.remove('show')">
            <?= htmlspecialchars($lang['cancelar']) ?>
        </button>
    </div>
</div>

<script>
var LOGUEADO = <?= $logueado ? 'true' : 'false' ?>;

function votarAsistencia(btn, asiste) {
    if (!LOGUEADO) {
        document.getElementById('modal-login-asi').classList.add('show');
        return;
    }
    var wrap = btn.closest('.acto-asistencia');
    var idHuella = wrap.dataset.huella;
    var idTarea  = wrap.dataset.tarea;

    fetch('/huella/api/asistencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=votar'
            + '&id_huella=' + encodeURIComponent(idHuella)
            + '&id_tarea='  + encodeURIComponent(idTarea)
            + '&asiste='    + asiste
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        var btnSi  = wrap.querySelector('.btn-asistir');
        var btnNo  = wrap.querySelector('.btn-no-asistir');
        var cntSi  = btnSi.querySelector('.btn-asistencia__count');
        var cntNo  = btnNo.querySelector('.btn-asistencia__count');
        btnSi.classList.toggle('activo', d.voto === 1);
        btnNo.classList.toggle('activo', d.voto === 0);
        cntSi.textContent = d.si || '';
        cntNo.textContent = d.no || '';
    })
    .catch(function() {});
}
</script>
</body>
</html>
