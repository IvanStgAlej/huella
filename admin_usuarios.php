<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

if (!sesionActiva()) {
    header('Location: /huella/login.php?back=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$uActual   = usuarioActual();
$idUser    = (int)$uActual['id'];
$idHuella  = $uActual['id_huella'] ?? '';
$tema      = cargarTema();
$idIdioma       = $uActual['idioma'] ?? 'CAS';
$nombreProducto = $tema['nombre_producto'];
$logoPath       = asset($tema['logo_path']);
$logoAlt        = $tema['logo_alt'];

if (!$idHuella || !esAdminDeAlgunaHuella()) {
    header('Location: /huella/');
    exit;
}

$db = getDB();

// Datos del fallecido
$stH = $db->prepare("
    SELECT h.Id_Cliente, h.Empresa, h.link,
           f.Fallecido_Nombre, f.Fallecido_Apellido_1, f.Fallecido_Apellido_2
    FROM h_huella h
    LEFT JOIN h_huellafallecido f ON f.Id_Huella = h.Id_Huella
    WHERE h.Id_Huella = ?
");
$stH->bind_param('s', $idHuella);
$stH->execute();
$huellaData = $stH->get_result()->fetch_assoc();
$stH->close();

// Usuarios de esta huella
$st2 = $db->prepare("
    SELECT Id, User_Identificador, User_Nombre_Apellidos, Id_Rol, User_Baneado
    FROM h_huellausersroles
    WHERE Id_Huella = ?
    ORDER BY FIELD(Id_Rol,'OWN','ADM','COL','GFC','INV','SOL'), User_Nombre_Apellidos
");
$st2->bind_param('s', $idHuella);
$st2->execute();
$usuarios = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
$st2->close();

// Posts malsonantes pendientes
$st3 = $db->prepare("
    SELECT Id_Post, Post_Autor, Post_Texto, Post_Fecha
    FROM h_huellapost
    WHERE Id_Huella = ? AND Post_Flag_Malsonante = 1 AND Post_Eliminado = 0
    ORDER BY Post_Fecha DESC
");
$st3->bind_param('s', $idHuella);
$st3->execute();
$postsMalsonantes = $st3->get_result()->fetch_all(MYSQLI_ASSOC);
$st3->close();

if (!empty($huellaData['Id_Cliente'])) $tema = obtenerTemaCliente($huellaData['Id_Cliente'], $tema);
$cssVars = generarVariablesCSS($tema['colores'], $tema);

$nombreFallecido = trim(
    ($huellaData['Fallecido_Nombre']     ?? '') . ' ' .
    ($huellaData['Fallecido_Apellido_1'] ?? '') . ' ' .
    ($huellaData['Fallecido_Apellido_2'] ?? '')
) ?: ($huellaData['link'] ?? '');

$rolesLabel = [
    'OWN' => 'Propietario', 'ADM' => 'Administrador', 'COL' => 'Colaborador',
    'GFC' => 'Cliente',     'INV' => 'Invitado',       'SOL' => 'Solicitante',
];
?>
<!DOCTYPE html>
<html lang="<?= strtolower($idIdioma) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios — <?= htmlspecialchars($nombreProducto) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <?php require __DIR__ . '/includes/session_guard.php'; ?>
    <style>:root { <?= $cssVars ?> }</style>
    <style>
    .admin-section-header {
        display: flex; align-items: center; gap: .75rem;
        margin: .9rem .9rem .75rem;
    }
    .admin-section-header__icono {
        width: 44px; height: 44px; background: var(--color-fondo-card);
        border: 1px solid var(--color-borde); border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: var(--color-primario); flex-shrink: 0;
    }
    .admin-section-header__icono svg { width: 22px; height: 22px; }
    .admin-section-header__titulo { font-size: 1.15rem; font-weight: 700; color: var(--color-texto); margin: 0; font-family: var(--fuente-titulo); }
    .admin-section-header__sub   { font-size: .78rem; color: var(--color-texto-suave); margin: .1rem 0 0; }

    .bloque { margin: 0 .9rem 1rem; }
    .bloque__label { font-size: .68rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--color-texto-suave); margin: 0 0 .45rem .1rem; }

    .usuario-card { background: var(--color-fondo-card); border: 1px solid var(--color-borde); border-radius: 14px; padding: .8rem 1rem; display: flex; align-items: center; gap: .75rem; margin-bottom: .45rem; }
    .usuario-card--baneado { opacity: .55; border-color: #fca5a5; }
    .usuario-card--baneado .usuario-card__avatar { background: #ef4444; }
    .usuario-card__avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--color-primario); color: #fff; font-weight: 700; font-size: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-family: var(--fuente-titulo); }
    .usuario-card__info { flex: 1; min-width: 0; }
    .usuario-card__nombre { font-size: .88rem; font-weight: 600; color: var(--color-texto); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .usuario-card__contacto { font-size: .74rem; color: var(--color-texto-suave); margin-top: .1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .usuario-card__acciones { display: flex; align-items: center; gap: .4rem; flex-shrink: 0; }

    .rol-chip { font-size: .67rem; font-weight: 700; letter-spacing: .04em; padding: .2rem .5rem; border-radius: 20px; border: 1.5px solid transparent; white-space: nowrap; }
    .rol-chip--ADM, .rol-chip--OWN { background: #ede9fe; border-color: #c4b5fd; color: #6d28d9; }
    .rol-chip--COL { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
    .rol-chip--GFC { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }
    .rol-chip--INV { background: #fdf4ff; border-color: #e879f9; color: #86198f; }
    .rol-chip--SOL { background: #fff7ed; border-color: #fdba74; color: #c2410c; }

    .btn-rol { border: none; cursor: pointer; border-radius: 8px; padding: .32rem .6rem; font-size: .73rem; font-weight: 600; white-space: nowrap; }
    .btn-rol--promover { background: #ede9fe; color: #6d28d9; border: 1.5px solid #c4b5fd; }
    .btn-rol--degradar { background: #fce7f3; color: #be185d; border: 1.5px solid #f9a8d4; }
    .btn-accion { border: none; cursor: pointer; background: none; padding: .3rem; border-radius: 8px; color: var(--color-texto-suave); display: flex; align-items: center; transition: background .15s, color .15s; }
    .btn-accion:hover { background: #fee2e2; color: #dc2626; }
    .btn-accion--verde:hover { background: #dcfce7; color: #15803d; }
    .btn-accion svg { width: 15px; height: 15px; }

    .buscar-wrap { display: flex; gap: .5rem; margin-bottom: .6rem; }
    .buscar-wrap__campo { flex: 1; position: relative; display: flex; align-items: center; }
    .buscar-wrap input { width: 100%; padding: .65rem 2.2rem .65rem .9rem; border-radius: 10px; border: 1.5px solid var(--color-borde); background: var(--color-fondo-card); color: var(--color-texto); font-size: .84rem; outline: none; box-sizing: border-box; transition: border-color .15s; }
    .buscar-wrap input:focus { border-color: var(--color-primario); }
    .buscar-wrap > button { padding: .65rem 1rem; border-radius: 10px; border: none; background: var(--color-primario); color: #fff; font-size: .84rem; font-weight: 600; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
    .buscar-clear { position: absolute; right: .6rem; background: none; border: none; cursor: pointer; color: var(--color-texto-suave); padding: .15rem; display: none; line-height: 1; border-radius: 4px; }
    .buscar-clear:hover { color: var(--color-texto); background: var(--color-borde); }
    .buscar-clear svg { width: 14px; height: 14px; display: block; }
    .sin-resultados-filtro { text-align: center; padding: 1.5rem 1rem; color: var(--color-texto-suave); font-size: .84rem; display: none; }

    .lista-vacia { text-align: center; padding: 2rem 1rem; color: var(--color-texto-suave); font-size: .85rem; }
    .lista-vacia svg { width: 36px; height: 36px; margin-bottom: .6rem; opacity: .4; display: block; margin-inline: auto; }

    .toast-adm { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; padding: .6rem 1.2rem; border-radius: 20px; font-size: .82rem; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity .2s; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,.2); }
    .toast-adm.show { opacity: 1; }
    .main { padding-bottom: 3rem; }

    .alertas-header { display: flex; align-items: center; gap: .6rem; margin: 0 0 .7rem .1rem; }
    .alertas-header__titulo { font-size: .68rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #b45309; }
    .alertas-badge { background: #dc2626; color: #fff; font-size: .65rem; font-weight: 700; padding: .1rem .45rem; border-radius: 20px; line-height: 1.4; }
    .alerta-card { background: #fffbeb; border: 1.5px solid #fcd34d; border-radius: 12px; padding: .85rem 1rem; margin-bottom: .5rem; }
    .alerta-card__meta { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; }
    .alerta-card__autor { font-size: .78rem; font-weight: 700; color: #92400e; }
    .alerta-card__fecha { font-size: .72rem; color: #b45309; margin-left: auto; }
    .alerta-card__texto { font-size: .88rem; color: #1a1a1a; line-height: 1.55; margin-bottom: .75rem; white-space: pre-wrap; word-break: break-word; background: #fff; border: 1px solid #fde68a; border-radius: 8px; padding: .6rem .8rem; }
    .alerta-card__acciones { display: flex; gap: .5rem; flex-wrap: wrap; }
    .btn-alerta { font-size: .75rem; font-weight: 600; padding: .35rem .85rem; border-radius: 8px; border: none; cursor: pointer; transition: opacity .15s; }
    .btn-alerta:hover { opacity: .85; }
    .btn-alerta--eliminar { background: #fee2e2; color: #dc2626; }
    .btn-alerta--descensurar { background: #dcfce7; color: #15803d; }
    .alerta-card--eliminando { opacity: 0; transition: opacity .3s; }
    </style>
</head>
<body>

<header class="header">
    <a href="/huella/" class="header__logo">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($logoAlt) ?>">
        <span class="header__nombre"><?= htmlspecialchars($nombreProducto) ?></span>
    </a>
    <div class="header__acciones">
        <?= renderMenuUsuario($uActual, $_SERVER['REQUEST_URI']) ?>
    </div>
</header>

<main class="main">

    <div class="admin-section-header">
        <div class="admin-section-header__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <h1 class="admin-section-header__titulo">Gestión de usuarios</h1>
            <p class="admin-section-header__sub"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
        </div>
    </div>

    <!-- Alertas de contenido inapropiado -->
    <?php if (!empty($postsMalsonantes)): ?>
    <div class="bloque" id="bloque-alertas">
        <div class="alertas-header">
            <span class="alertas-header__titulo">Alertas de contenido</span>
            <span class="alertas-badge" id="alertas-contador"><?= count($postsMalsonantes) ?></span>
        </div>
        <?php foreach ($postsMalsonantes as $pm): ?>
        <div class="alerta-card" id="alerta-<?= htmlspecialchars($pm['Id_Post']) ?>">
            <div class="alerta-card__meta">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span class="alerta-card__autor"><?= htmlspecialchars($pm['Post_Autor'] ?: 'Anónimo') ?></span>
                <span class="alerta-card__fecha"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($pm['Post_Fecha']))) ?></span>
            </div>
            <div class="alerta-card__texto"><?= htmlspecialchars($pm['Post_Texto']) ?></div>
            <div class="alerta-card__acciones">
                <button class="btn-alerta btn-alerta--eliminar"
                        onclick="accionAlerta('eliminar_post','<?= htmlspecialchars($pm['Id_Post']) ?>',this)">Eliminar mensaje</button>
                <button class="btn-alerta btn-alerta--descensurar"
                        onclick="accionAlerta('descensurar_post','<?= htmlspecialchars($pm['Id_Post']) ?>',this)">Descensurar para todos</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filtro -->
    <div class="bloque">
        <p class="bloque__label">Buscar usuario</p>
        <div class="buscar-wrap">
            <div class="buscar-wrap__campo">
                <input type="text" id="js-buscar-input" placeholder="Filtrar por nombre o identificador...">
                <button id="js-buscar-clear" class="buscar-clear" title="Limpiar filtro" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <button id="js-buscar-btn">Buscar</button>
        </div>
    </div>

    <!-- Lista de usuarios -->
    <div class="bloque">
        <p class="bloque__label" id="js-contador-label">Usuarios en esta huella (<?= count($usuarios) ?>)</p>
        <div id="js-usuarios-lista">
        <?php if (empty($usuarios)): ?>
        <div class="lista-vacia">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            No hay usuarios registrados en esta huella.
        </div>
        <?php else: ?>
        <?php foreach ($usuarios as $u): ?>
        <?php
            $inicial   = mb_strtoupper(mb_substr($u['User_Nombre_Apellidos'] ?? '?', 0, 1, 'UTF-8'), 'UTF-8');
            $esYo      = ((int)$u['Id'] === $idUser);
            $rolActual = $u['Id_Rol'];
            $esAdm     = in_array($rolActual, ['ADM','OWN'], true);
            $label     = $rolesLabel[$rolActual] ?? $rolActual;
            $baneado   = !empty($u['User_Baneado']);
        ?>
        <div class="usuario-card <?= $baneado ? 'usuario-card--baneado' : '' ?>"
             data-user="<?= (int)$u['Id'] ?>"
             data-rol="<?= htmlspecialchars($rolActual) ?>"
             data-buscar="<?= htmlspecialchars(mb_strtolower(($u['User_Nombre_Apellidos'] ?? '') . ' ' . ($u['User_Identificador'] ?? ''), 'UTF-8')) ?>">
            <div class="usuario-card__avatar"><?= htmlspecialchars($inicial) ?></div>
            <div class="usuario-card__info">
                <div class="usuario-card__nombre">
                    <?= htmlspecialchars($u['User_Nombre_Apellidos'] ?? 'Sin nombre') ?>
                    <?php if ($esYo): ?><span style="font-size:.7rem;font-weight:400;color:var(--color-texto-suave)"> (tú)</span><?php endif; ?>
                </div>
                <div class="usuario-card__contacto"><?= htmlspecialchars($u['User_Identificador'] ?? '') ?></div>
            </div>
            <div class="usuario-card__acciones">
                <span class="rol-chip rol-chip--<?= htmlspecialchars($rolActual) ?> js-rol-chip"><?= htmlspecialchars($label) ?></span>
                <?php if (!$esYo && $rolActual !== 'OWN'): ?>
                <?php if ($esAdm): ?>
                <button class="btn-rol btn-rol--degradar js-btn-degradar">Quitar ADM</button>
                <?php else: ?>
                <button class="btn-rol btn-rol--promover js-btn-promover">Hacer ADM</button>
                <?php endif; ?>
                <?php if (!$baneado): ?>
                <button class="btn-accion js-btn-banear" title="Banear usuario">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                </button>
                <?php else: ?>
                <button class="btn-accion btn-accion--verde js-btn-desbanear" title="Desbanear usuario">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                </button>
                <?php endif; ?>
                <button class="btn-accion js-btn-eliminar" title="Eliminar usuario de la huella">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <p class="sin-resultados-filtro" id="js-sin-resultados">Sin resultados para este filtro.</p>
        </div>
    </div>

    <div style="height:5rem"></div>
</main>

<div class="toast-adm" id="toast-adm"></div>

<script>
(function () {
    var rolesLabel = { OWN:'Propietario', ADM:'Administrador', COL:'Colaborador', GFC:'Cliente', INV:'Invitado', SOL:'Solicitante' };

    function toast(msg, err) {
        var t = document.getElementById('toast-adm');
        t.textContent = msg;
        t.style.background = err ? '#dc2626' : '#1e293b';
        t.classList.add('show');
        setTimeout(function(){ t.classList.remove('show'); }, 2500);
    }

    function api(data, cb) {
        fetch('/huella/admin_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: Object.entries(data).map(function(p){ return encodeURIComponent(p[0])+'='+encodeURIComponent(p[1]); }).join('&')
        }).then(function(r){ return r.json(); }).then(cb).catch(function(){ toast('Error de red', true); });
    }

    document.getElementById('js-usuarios-lista').addEventListener('click', function(e) {
        var card = e.target.closest('.usuario-card');
        if (!card) return;
        var uid = card.dataset.user;

        if (e.target.closest('.js-btn-promover')) {
            api({ action:'set_rol', id_user:uid, rol:'ADM' }, function(r) {
                if (r.ok) { actualizarCard(card, 'ADM'); toast('Ascendido a Administrador'); }
                else toast(r.error||'Error', true);
            });
        }
        if (e.target.closest('.js-btn-degradar')) {
            api({ action:'set_rol', id_user:uid, rol:'COL' }, function(r) {
                if (r.ok) { actualizarCard(card, 'COL'); toast('Rol cambiado a Colaborador'); }
                else toast(r.error||'Error', true);
            });
        }
        if (e.target.closest('.js-btn-banear')) {
            if (!confirm('¿Banear este usuario?')) return;
            api({ action:'banear', id_user:uid }, function(r) {
                if (r.ok) { card.classList.add('usuario-card--baneado'); toast('Usuario baneado'); }
                else toast(r.error||'Error', true);
            });
        }
        if (e.target.closest('.js-btn-desbanear')) {
            api({ action:'desbanear', id_user:uid }, function(r) {
                if (r.ok) { card.classList.remove('usuario-card--baneado'); toast('Usuario desbaneado'); }
                else toast(r.error||'Error', true);
            });
        }
        if (e.target.closest('.js-btn-eliminar')) {
            if (!confirm('¿Eliminar este usuario de la huella?')) return;
            api({ action:'eliminar', id_user:uid }, function(r) {
                if (r.ok) {
                    card.style.transition = 'opacity .3s'; card.style.opacity = '0';
                    setTimeout(function(){ card.remove(); actualizarContador(-1); }, 300);
                    toast('Usuario eliminado');
                } else toast(r.error||'Error', true);
            });
        }
    });

    function actualizarCard(card, nuevoRol) {
        card.dataset.rol = nuevoRol;
        var chip = card.querySelector('.js-rol-chip');
        chip.textContent = rolesLabel[nuevoRol] || nuevoRol;
        chip.className = 'rol-chip rol-chip--' + nuevoRol + ' js-rol-chip';
        var btnP = card.querySelector('.js-btn-promover');
        var btnD = card.querySelector('.js-btn-degradar');
        var acc  = card.querySelector('.usuario-card__acciones');
        var ref  = card.querySelector('.js-btn-banear') || card.querySelector('.js-btn-desbanear');
        if (btnP) btnP.remove();
        if (btnD) btnD.remove();
        var nb = document.createElement('button');
        var esAdm = (nuevoRol === 'ADM' || nuevoRol === 'OWN');
        nb.className = esAdm ? 'btn-rol btn-rol--degradar js-btn-degradar' : 'btn-rol btn-rol--promover js-btn-promover';
        nb.textContent = esAdm ? 'Quitar ADM' : 'Hacer ADM';
        acc.insertBefore(nb, ref);
    }

    function actualizarContador(delta) {
        var lbl = document.getElementById('js-contador-label');
        if (!lbl) return;
        var m = lbl.textContent.match(/\((\d+)\)/);
        if (m) lbl.textContent = lbl.textContent.replace(/\(\d+\)/, '(' + (parseInt(m[1]) + delta) + ')');
    }

    // Filtro
    var inputB   = document.getElementById('js-buscar-input');
    var btnB     = document.getElementById('js-buscar-btn');
    var btnClear = document.getElementById('js-buscar-clear');
    var sinRes   = document.getElementById('js-sin-resultados');

    function filtrar() {
        var q = inputB.value.trim().toLowerCase();
        var cards = document.querySelectorAll('#js-usuarios-lista .usuario-card');
        var visible = 0;
        cards.forEach(function(card) {
            var mostrar = !q || (card.dataset.buscar || '').indexOf(q) !== -1;
            card.style.display = mostrar ? '' : 'none';
            if (mostrar) visible++;
        });
        btnClear.style.display = q ? 'block' : 'none';
        sinRes.style.display = (q && visible === 0) ? 'block' : 'none';
        btnB.textContent = q ? 'Limpiar' : 'Buscar';
    }
    function limpiar() { inputB.value = ''; filtrar(); inputB.focus(); }

    btnB.addEventListener('click', function() { if (inputB.value.trim()) limpiar(); else filtrar(); });
    btnClear.addEventListener('click', limpiar);
    inputB.addEventListener('input', filtrar);
    inputB.addEventListener('keydown', function(e) { if (e.key==='Enter') filtrar(); if (e.key==='Escape') limpiar(); });

    // Alertas
    window.accionAlerta = function(action, idPost, btn) {
        if (!confirm('¿Confirmas esta acción?')) return;
        btn.disabled = true;
        api({ action:action, id_post:idPost }, function(r) {
            if (!r.ok) { toast(r.error||'Error', true); btn.disabled = false; return; }
            var card = document.getElementById('alerta-' + idPost);
            card.classList.add('alerta-card--eliminando');
            setTimeout(function() {
                card.remove();
                var badge = document.getElementById('alertas-contador');
                if (badge) {
                    var n = parseInt(badge.textContent) - 1;
                    if (n <= 0) { var b = document.getElementById('bloque-alertas'); if (b) b.remove(); }
                    else badge.textContent = n;
                }
            }, 320);
            toast(action === 'eliminar_post' ? 'Mensaje eliminado' : 'Mensaje descensurado');
        });
    };
})();
</script>
</body>
</html>
