<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

$tema    = cargarTema();
$huellas = listarHuellas();

$linkActual = $_GET['link'] ?? null;
if ($linkActual) {
    $huella = obtenerHuellaByLink($linkActual);
} else {
    $linkActual = $huellas[0]['link'] ?? null;
    $huella     = $linkActual ? obtenerHuellaByLink($linkActual) : null;
}

$uActual       = usuarioActual();
$logueado      = $uActual !== null;
$nombreUsuario = $logueado ? ($uActual['nombre'] ?? '') : '';
$loginUrl      = '/huella/login.php?back=' . urlencode($_SERVER['REQUEST_URI']);
$idIdioma      = $uActual['idioma'] ?? ($huella['Id_Idioma'] ?? 'CAS');
$lang          = cargarIdioma($idIdioma);
if ($huella) $tema = obtenerTemaCliente($huella['Id_Cliente'], $tema);
$colores       = $tema['colores'];
$cssVars       = generarVariablesCSS($colores, $tema);

$nombreProducto  = $tema['nombre_producto'];
$logoPath        = asset($tema['logo_path']);
$logoAlt         = $tema['logo_alt'];
$posts           = [];
$nombreFallecido = '';
$muroAutorizado  = false;
$idHuella        = '';

if ($huella) {
    $muroAutorizado  = (bool)($huella['AutorizarMuro_SN'] ?? false);
    $idHuella        = $huella['Id_Huella'];
    $nombreFallecido = trim(
        ($huella['Fallecido_Nombre']     ?? '') . ' ' .
        ($huella['Fallecido_Apellido_1'] ?? '') . ' ' .
        ($huella['Fallecido_Apellido_2'] ?? '')
    );
    if ($muroAutorizado) {
        $posts = obtenerPosts($idHuella);
    }
}

$idUsuarioActual = $logueado ? (string)$uActual['id'] : '';
$esAdmin         = $logueado && $idHuella !== '' && esAdminHuella($idHuella);

function inicialPost(?string $autor): string {
    $texto = !empty($autor) ? $autor : '?';
    return mb_strtoupper(mb_substr(trim($texto), 0, 1, 'UTF-8'), 'UTF-8');
}

function fechaPostCorta(?string $fecha): string {
    if (!$fecha) return '';
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $ts    = strtotime($fecha);
    return date('j', $ts) . ' ' . $meses[(int)date('n', $ts) - 1] . '. ' . date('Y', $ts);
}

// URL de la API (mismo directorio)
$apiUrl = asset('api/muro.php');
?>
<!DOCTYPE html>
<html lang="<?= strtolower($idIdioma) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['muro'] ?> — <?= htmlspecialchars($nombreFallecido ?: $nombreProducto) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/muro.css') ?>">
    <?php require __DIR__ . '/../includes/session_guard.php'; ?>
    <style>:root { <?= $cssVars ?> }
    .compositor__nombre--sesion {
        font-size: .9rem;
        font-weight: 600;
        color: var(--color-texto);
        padding: .25rem 0;
        line-height: 1.4;
    }
    .muro-login-aviso { text-align: center; gap: .75rem; }
    .muro-login-aviso .btn-login { display: inline-block; margin-top: .5rem; padding: .55rem 1.4rem; background: var(--color-primario); color: #fff; border-radius: 20px; text-decoration: none; font-size: .88rem; font-weight: 600; }
    </style>
</head>
<body>

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
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            <?= $lang['compartir'] ?>
        </a>
        <?php if ($uActual): ?>
        <?= renderMenuUsuario($uActual, $_SERVER['REQUEST_URI']) ?>
        <?php else: ?>
        <a href="/huella/login.php?back=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
           class="btn-icon" title="Iniciar sesión">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>
    </div>
</header>

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

        <div class="card card-bio" style="margin-top:.75rem;">
            <p><?= $lang['error_huella'] ?></p>
        </div>

    <?php elseif (!$muroAutorizado): ?>

        <div class="card muro-estado" style="margin-top:.75rem;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <p><?= $lang['muro_privado'] ?></p>
        </div>

    <?php else: ?>

    <!-- CABECERA SECCIÓN -->
    <div class="muro-header">
        <div class="muro-header__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div>
            <h1 class="muro-header__titulo"><?= $lang['muro'] ?></h1>
            <?php if ($nombreFallecido): ?>
            <p class="muro-header__subtitulo"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── COMPOSITOR PRINCIPAL ── -->
    <?php if ($logueado): ?>
    <div class="card compositor" id="compositorPrincipal">
        <div class="compositor__top">
            <div class="compositor__avatar" id="avatarPrincipal"><?= mb_strtoupper(mb_substr($nombreUsuario, 0, 1, 'UTF-8'), 'UTF-8') ?></div>
            <span class="compositor__nombre compositor__nombre--sesion"><?= htmlspecialchars($nombreUsuario) ?></span>
        </div>
        <textarea class="compositor__texto" id="textoPrincipal"
                  placeholder="<?= $lang['escribe_mensaje'] ?>" rows="3"
                  oninput="autoResize(this)"></textarea>
        <div class="compositor__link-panel" id="linkPanel-principal" style="display:none">
            <input type="url" class="compositor__texto compositor__link-input"
                   placeholder="https://" autocomplete="off">
        </div>
        <div class="compositor__previews" id="previewsPrincipal"></div>
        <div class="compositor__barra">
            <div class="compositor__media-btns">
                <button type="button" class="compositor__btn-media" title="<?= $lang['adjuntar_foto'] ?>"
                        onclick="triggerInput('inputFotosPrincipal')">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </button>
                <button type="button" class="compositor__btn-media" title="<?= $lang['adjuntar_video'] ?>"
                        onclick="triggerInput('inputVideoPrincipal')">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                </button>
                <button type="button" class="compositor__btn-media" title="<?= $lang['adjuntar_audio'] ?>"
                        onclick="triggerInput('inputAudioPrincipal')">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                </button>
                <button type="button" id="btnLink-principal" class="compositor__btn-media" title="<?= $lang['adjuntar_link'] ?>"
                        onclick="toggleLinkPanel('principal')">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </button>
            </div>
            <button type="button" class="compositor__enviar"
                    onclick="submitPost(null, 'principal')">
                <?= $lang['publicar_mensaje'] ?>
            </button>
        </div>
        <input type="file" id="inputFotosPrincipal" accept="image/*" multiple style="display:none"
               onchange="handleFotos(this, 'previewsPrincipal')">
        <input type="file" id="inputVideoPrincipal" accept="video/mp4,video/webm,video/mov" style="display:none"
               onchange="handleArchivo(this, 'previewsPrincipal', 'video')">
        <input type="file" id="inputAudioPrincipal" accept="audio/mp3,audio/ogg,audio/wav,audio/m4a,audio/*" style="display:none"
               onchange="handleArchivo(this, 'previewsPrincipal', 'audio')">
    </div>
    <?php else: ?>
    <div class="card muro-estado muro-login-aviso">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <p style="text-align:center;"><?= $lang['login_para_comentar'] ?></p>
        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn-login"><?= $lang['iniciar_sesion'] ?></a>
    </div>
    <?php endif; ?>

    <!-- ── FEED DE POSTS ── -->
    <div id="feedPosts">
    <?php if (empty($posts)): ?>
        <div class="card muro-estado" id="estadoVacio">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <p><?= $lang['muro_vacio'] ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
        <?= renderizarPost($post, false, $lang, $logueado, $idUsuarioActual, $esAdmin, $nombreUsuario) ?>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <?php endif; ?>

    <div style="height:5rem"></div>

</main>

<!-- MODAL DE CONFIRMACIÓN -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </div>
        <p class="modal__titulo"><?= $lang['confirmar_eliminar'] ?></p>
        <p class="modal__desc"><?= $lang['confirmar_eliminar_desc'] ?></p>
        <div class="modal__botones">
            <button class="modal__btn modal__btn--cancelar" id="modalCancelar"><?= $lang['cancelar'] ?></button>
            <button class="modal__btn modal__btn--confirmar" id="modalConfirmar"><?= $lang['eliminar'] ?></button>
        </div>
    </div>
</div>

<nav class="nav-bottom">
    <a href="<?= urlHuella($linkActual ?? '') ?>" class="nav-bottom__item">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
        <?= $lang['inicio'] ?>
    </a>
    <a href="<?= urlHuella($linkActual ?? '', 'actos') ?>" class="nav-bottom__item">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M10 4h4"/><path d="M5 22V10l7-6 7 6v12H5z"/><rect x="9" y="15" width="6" height="7"/></svg></span>
        <?= $lang['actividades'] ?>
    </a>
    <?php if ($huella && ($huella['AutorizarMuro_SN'] ?? false)): ?>
    <a href="<?= urlHuella($linkActual ?? '', 'muro') ?>" class="nav-bottom__item nav-bottom__item--mensajes active">
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

<?php
// ── FUNCIÓN PHP DE RENDERIZADO DE POST/RESPUESTA ──────────────────────────────
function renderizarPost(array $post, bool $esRespuesta, array $lang, bool $logueado = true, string $idUsuarioActual = '', bool $esAdmin = false, string $nombreUsuario = ''): string {
    $idPost   = htmlspecialchars($post['Id_Post']);
    $autor    = htmlspecialchars($post['Post_Autor'] ?? $lang['sin_nombre']);
    $inicial  = inicialPost($post['Post_Autor'] ?? null);
    $likes    = (int)($post['total_likes'] ?? 0);
    $replies  = (int)($post['total_respuestas'] ?? 0);
    $texto    = htmlspecialchars(filtrarMalsonantes($post['Post_Texto'] ?? ''));
    $fecha    = fechaPostCorta($post['Post_Fecha'] ?? null);
    $esPropio = $idUsuarioActual !== '' && ($post['Post_Id_user'] ?? '') === $idUsuarioActual;
    $puedeModerar = $esPropio || $esAdmin;
    $svgHeart = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
    $svgDel   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>';

    // ── REPLY (compacto) ──────────────────────────────────────────────────────
    if ($esRespuesta) {
        $likeCnt = $likes > 0 ? " <span class=\"reply-like__count\">{$likes}</span>" : '';
        $delBtnReply = $puedeModerar
            ? "<button class=\"btn-delete btn-delete--sm\" onclick=\"eliminarPost('{$idPost}', this)\" title=\"{$lang['eliminar']}\">{$svgDel}</button>"
            : '';
        $media   = renderizarMedia($post);
        $html    = "<div class=\"reply-card\" id=\"post-{$idPost}\" data-id=\"{$idPost}\">";
        if ($media) $html .= "<div class=\"reply-card__media\">{$media}</div>";
        $html .= <<<HTML
            <div class="reply-card__body">
                <div class="reply-card__top">
                    <div class="reply-card__avatar">{$inicial}</div>
                    <div class="reply-card__meta">
                        <span class="reply-card__autor">{$autor}</span>
                        <span class="reply-card__fecha">{$fecha}</span>
                    </div>
                    <button class="btn-like btn-like--sm" data-id="{$idPost}" onclick="toggleLike(this)">{$svgHeart}{$likeCnt}</button>
                    {$delBtnReply}
                </div>
            HTML;
        if ($texto) $html .= "<p class=\"reply-card__texto\">{$texto}</p>";
        $html .= '</div></div>';
        return $html;
    }

    // ── POST PRINCIPAL ────────────────────────────────────────────────────────
    $likesCount   = $likes > 0 ? $likes : '';
    $repliesLabel = $replies > 0
        ? "{$lang['ver_respuestas_n']} {$replies} · {$lang['responder']}"
        : $lang['responder'];

    $media = renderizarMedia($post);
    $html  = "<div class=\"card post-card\" id=\"post-{$idPost}\" data-id=\"{$idPost}\" data-replies=\"{$replies}\">";
    $html .= '<div class="post-card__body">';

    $svgEdit = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
    $svgOk   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

    if ($puedeModerar) {
        $html .= "<div class=\"post-card__header\" id=\"header-{$idPost}\">"
               . "<button class=\"btn-edit\" onclick=\"editarPost('{$idPost}')\" title=\"{$lang['editar']}\">{$svgEdit}</button>"
               . "<button class=\"btn-delete\" onclick=\"eliminarPost('{$idPost}', this)\" title=\"{$lang['eliminar']}\">{$svgDel}</button>"
               . "</div>";
    }

    // Media encima del texto
    if ($media) $html .= "<div class=\"post-card__media\">{$media}</div>";

    // Corazón como sangría + texto
    $html .= "<div class=\"post-card__content\">";
    $html .= "<button class=\"btn-like\" data-id=\"{$idPost}\" onclick=\"toggleLike(this)\">{$svgHeart}</button>";
    if ($texto) $html .= "<p class=\"post-card__texto\">" . nl2br($texto) . "</p>";
    $html .= "</div>";

    // Fila autor (izq) + responder (der)
    $html .= "<div class=\"post-card__actions\"><span class=\"post-card__autor\">{$autor}</span><button class=\"post-card__reply-link\" onclick=\"focusReply('{$idPost}')\">{$repliesLabel}</button></div>";

    $html .= '</div>'; // body

    // Zona de respuestas
    if ($logueado && $nombreUsuario) {
        $inicialSesion = mb_strtoupper(mb_substr($nombreUsuario, 0, 1, 'UTF-8'), 'UTF-8');
        $nombreEsc     = htmlspecialchars($nombreUsuario);
        $topReplyHtml  = "<div class=\"compositor__avatar\" id=\"avatar-reply-{$idPost}\">{$inicialSesion}</div>"
                       . "<span class=\"compositor__nombre compositor__nombre--sesion\">{$nombreEsc}</span>";
    } else {
        $topReplyHtml  = "<div class=\"compositor__avatar\" id=\"avatar-reply-{$idPost}\">?</div>"
                       . "<input type=\"text\" class=\"compositor__nombre\" id=\"nombre-reply-{$idPost}\""
                       . " placeholder=\"{$lang['tu_nombre']}\" maxlength=\"80\""
                       . " oninput=\"actualizarAvatar('avatar-reply-{$idPost}', this.value)\">";
    }
    $html .= <<<HTML
        <div class="post-replies" id="replies-{$idPost}">
            <div class="post-replies__lista" id="lista-{$idPost}" data-loaded="0"></div>
            <div class="compositor compositor--reply" id="compositor-reply-{$idPost}">
                <div class="compositor__top">
                    {$topReplyHtml}
                </div>
                <textarea class="compositor__texto" id="texto-reply-{$idPost}"
                          placeholder="{$lang['escribe_respuesta']}" rows="2"
                          oninput="autoResize(this)"></textarea>
                <div class="compositor__link-panel" id="linkPanel-reply-{$idPost}" style="display:none">
                    <input type="url" class="compositor__texto compositor__link-input" placeholder="https://" autocomplete="off">
                </div>
                <div class="compositor__previews" id="previews-reply-{$idPost}"></div>
                <div class="compositor__barra">
                    <div class="compositor__media-btns">
                        <button type="button" class="compositor__btn-media" onclick="triggerInput('inputFotos-reply-{$idPost}')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="compositor__btn-media" onclick="triggerInput('inputVideo-reply-{$idPost}')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                        <button type="button" class="compositor__btn-media" onclick="triggerInput('inputAudio-reply-{$idPost}')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                        </button>
                        <button type="button" id="btnLink-reply-{$idPost}" class="compositor__btn-media" onclick="toggleLinkPanel('reply-{$idPost}')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        </button>
                    </div>
                    <button type="button" class="compositor__enviar compositor__enviar--sm"
                            onclick="submitPost('{$idPost}', 'reply-{$idPost}')">
                        {$lang['responder']}
                    </button>
                </div>
                <input type="file" id="inputFotos-reply-{$idPost}" accept="image/*" multiple style="display:none"
                       onchange="handleFotos(this, 'previews-reply-{$idPost}')">
                <input type="file" id="inputVideo-reply-{$idPost}" accept="video/mp4,video/webm,video/mov" style="display:none"
                       onchange="handleArchivo(this, 'previews-reply-{$idPost}', 'video')">
                <input type="file" id="inputAudio-reply-{$idPost}" accept="audio/*" style="display:none"
                       onchange="handleArchivo(this, 'previews-reply-{$idPost}', 'audio')">
            </div>
        </div>
        HTML;

    $html .= '</div>'; // .post-card
    return $html;
}

function renderizarMedia(array $post): string {
    $html = '';
    if (!empty($post['imagenes'])) {
        $n     = min(count($post['imagenes']), 4);
        $html .= "<div class=\"post-card__imagenes post-card__imagenes--{$n}\">";
        foreach (array_slice($post['imagenes'], 0, 4) as $img) {
            $src   = htmlspecialchars(asset($img['Img_Ruta_Carpeta'] . $img['Img_Nombre']));
            $html .= "<img class=\"post-img\" src=\"{$src}\" alt=\"\" loading=\"lazy\" onerror=\"this.style.display='none'\">";
        }
        $html .= '</div>';
    }
    foreach (($post['videos'] ?? []) as $vid) {
        $src   = htmlspecialchars(asset($vid['Video_Ruta_Carpeta'] . $vid['Video_Nombre']));
        $html .= "<video class=\"post-video\" controls preload=\"metadata\"><source src=\"{$src}\"></video>";
    }
    foreach (($post['audios'] ?? []) as $aud) {
        $src   = htmlspecialchars(asset($aud['Audio_Ruta_Carpeta'] . $aud['Audio_Nombre']));
        $html .= "<div class=\"post-audio-item\"><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M9 18V5l12-2v13\"/><circle cx=\"6\" cy=\"18\" r=\"3\"/><circle cx=\"18\" cy=\"16\" r=\"3\"/></svg><audio controls preload=\"none\"><source src=\"{$src}\"></audio></div>";
    }
    if (!empty($post['Post_Link'])) {
        $rawUrl  = $post['Post_Link'];
        $safeUrl = preg_match('#^https?://#i', $rawUrl) ? $rawUrl : 'https://' . $rawUrl;
        $host    = parse_url($safeUrl, PHP_URL_HOST) ?: $rawUrl;
        $favicon = 'https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=32';
        $display = htmlspecialchars($host);
        $safeUrl = htmlspecialchars($safeUrl);
        $html .= "<a class=\"post-link-card\" href=\"{$safeUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">"
               . "<img class=\"post-link-card__favicon\" src=\"{$favicon}\" alt=\"\" onerror=\"this.style.display='none'\">"
               . "<span class=\"post-link-card__url\">{$display}</span></a>";
    }
    return $html;
}
?>

<script>
const API_URL    = '<?= $apiUrl ?>';
const ID_HUELLA  = '<?= htmlspecialchars($idHuella) ?>';
const ASSET_BASE = '<?= asset('') ?>';
const USER_LOGGED  = <?= $logueado ? 'true' : 'false' ?>;
const USER_NOMBRE  = '<?= addslashes($nombreUsuario) ?>';
const USER_ID      = '<?= addslashes($idUsuarioActual) ?>';
const USER_ADMIN   = <?= $esAdmin ? 'true' : 'false' ?>;
const LOGIN_URL    = '<?= addslashes($loginUrl) ?>';
const LANG = {
    sin_nombre:       '<?= addslashes($lang['sin_nombre']) ?>',
    me_gusta:         '<?= addslashes($lang['me_gusta']) ?>',
    responder:        '<?= addslashes($lang['responder']) ?>',
    escribe_respuesta:'<?= addslashes($lang['escribe_respuesta']) ?>',
    tu_nombre:        '<?= addslashes($lang['tu_nombre']) ?>',
    confirmar_eliminar:'<?= addslashes($lang['confirmar_eliminar']) ?>',
    publicar_mensaje: '<?= addslashes($lang['publicar_mensaje']) ?>',
    hace_momento:     '<?= addslashes($lang['hace_momento']) ?>',
    adjuntar_link:    '<?= addslashes($lang['adjuntar_link']) ?>',
};

// ── TOKEN DE LIKE (UUID por navegador) ───────────────────────────────────────
function getLikeToken() {
    let t = localStorage.getItem('huella_like_token');
    if (!t) {
        t = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
        localStorage.setItem('huella_like_token', t);
    }
    return t;
}

window.addEventListener('DOMContentLoaded', () => {
    if (USER_LOGGED) actualizarAvatar('avatarPrincipal', USER_NOMBRE);
});

// ── AVATAR INICIAL ────────────────────────────────────────────────────────────
function actualizarAvatar(avatarId, nombre) {
    const el = document.getElementById(avatarId);
    if (!el) return;
    const letra = nombre.trim() ? nombre.trim()[0].toUpperCase() : '?';
    el.textContent = letra;
}

// ── AUTO-RESIZE TEXTAREA ──────────────────────────────────────────────────────
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

// ── PANEL DE ENLACE ───────────────────────────────────────────────────────────
function toggleLinkPanel(sfx) {
    const panel = document.getElementById('linkPanel-' + sfx);
    const btn   = document.getElementById('btnLink-' + sfx);
    if (!panel) return;
    const visible = panel.style.display === 'block';
    panel.style.display = visible ? 'none' : 'block';
    btn?.classList.toggle('compositor__btn-media--active', !visible);
    if (!visible) {
        panel.querySelector('.compositor__link-input')?.focus();
    } else {
        const inp = panel.querySelector('.compositor__link-input');
        if (inp) inp.value = '';
    }
}

// ── GESTIÓN DE ARCHIVOS ───────────────────────────────────────────────────────
function triggerInput(inputId) {
    document.getElementById(inputId)?.click();
}

function handleFotos(input, previewsId) {
    const previews = document.getElementById(previewsId);
    const fotos = previews.querySelectorAll('[data-tipo="foto"]');
    fotos.forEach(f => f.remove());
    Array.from(input.files).slice(0, 4).forEach(file => {
        const chip = crearChip(file.name, 'foto', () => { input.value = ''; chip.remove(); });
        previews.appendChild(chip);
    });
}

function handleArchivo(input, previewsId, tipo) {
    const previews = document.getElementById(previewsId);
    const previo = previews.querySelector(`[data-tipo="${tipo}"]`);
    if (previo) previo.remove();
    if (!input.files[0]) return;
    const chip = crearChip(input.files[0].name, tipo, () => { input.value = ''; chip.remove(); });
    previews.appendChild(chip);
}

function crearChip(nombre, tipo, onRemove) {
    const chip = document.createElement('div');
    chip.className = 'compositor__chip';
    chip.dataset.tipo = tipo;
    const icons = { foto: '📷', video: '🎥', audio: '🎵' };
    chip.innerHTML = `<span>${icons[tipo] || '📎'} ${nombre.substring(0, 25)}${nombre.length > 25 ? '…' : ''}</span>`;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.innerHTML = '×';
    btn.onclick = onRemove;
    chip.appendChild(btn);
    return chip;
}

// ── ENVIAR POST ───────────────────────────────────────────────────────────────
async function submitPost(parentId, compositorId) {
    const sfx     = compositorId;
    const nombreEl = document.getElementById('nombre' + (compositorId === 'principal' ? 'Principal' : '-reply-' + parentId));
    const textoEl  = document.getElementById('texto'  + (compositorId === 'principal' ? 'Principal' : '-reply-' + parentId));
    const fotosEl  = document.getElementById('inputFotos' + (compositorId === 'principal' ? 'Principal' : '-reply-' + parentId));
    const videoEl  = document.getElementById('inputVideo' + (compositorId === 'principal' ? 'Principal' : '-reply-' + parentId));
    const audioEl  = document.getElementById('inputAudio' + (compositorId === 'principal' ? 'Principal' : '-reply-' + parentId));

    const autor    = USER_LOGGED ? USER_NOMBRE : (nombreEl?.value || '').trim();
    const texto    = (textoEl?.value  || '').trim();
    const linkSfx  = compositorId === 'principal' ? 'principal' : 'reply-' + parentId;
    const linkInput = document.querySelector('#linkPanel-' + linkSfx + ' .compositor__link-input');
    const postLink  = (linkInput?.value || '').trim();

    const tieneMedia = (fotosEl?.files?.length > 0) || (videoEl?.files?.length > 0) || (audioEl?.files?.length > 0);
    if (!texto && !tieneMedia && !postLink) { textoEl?.focus(); return; }

    const btn = document.querySelector(`#compositor${compositorId === 'principal' ? 'Principal' : '-reply-' + parentId} .compositor__enviar`);
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    const fd = new FormData();
    fd.append('action',    'crear_post');
    fd.append('id_huella', ID_HUELLA);
    fd.append('autor',     autor || LANG.sin_nombre);
    fd.append('texto',     texto);
    if (parentId) fd.append('parent_id', parentId);

    if (fotosEl?.files) Array.from(fotosEl.files).slice(0, 4).forEach(f => fd.append('fotos[]', f));
    if (videoEl?.files?.[0]) fd.append('video', videoEl.files[0]);
    if (audioEl?.files?.[0]) fd.append('audio', audioEl.files[0]);
    if (postLink) fd.append('post_link', postLink);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg);

        // Limpiar compositor
        if (textoEl) { textoEl.value = ''; textoEl.style.height = 'auto'; }
        if (fotosEl) fotosEl.value = '';
        if (videoEl) videoEl.value = '';
        if (audioEl) audioEl.value = '';
        document.getElementById('previews' + (compositorId === 'principal' ? 'Principal' : '-reply-' + parentId))?.querySelectorAll('.compositor__chip').forEach(c => c.remove());
        if (linkInput) linkInput.value = '';
        const linkPanel = document.getElementById('linkPanel-' + linkSfx);
        if (linkPanel) linkPanel.style.display = 'none';
        document.getElementById('btnLink-' + linkSfx)?.classList.remove('compositor__btn-media--active');

        // Ocultar estado vacío
        document.getElementById('estadoVacio')?.remove();

        if (!parentId) {
            // Insertar post nuevo al principio del feed
            const feed = document.getElementById('feedPosts');
            feed.insertAdjacentHTML('afterbegin', renderPostJS(data.post, false));
        } else {
            // Insertar respuesta en la lista de replies
            const lista = document.getElementById('lista-' + parentId);
            if (lista) {
                lista.querySelector('.post-replies__cargando')?.remove();
                lista.insertAdjacentHTML('beforeend', renderPostJS(data.post, true));
            }
            // Actualizar contador de respuestas
            actualizarCounterReplies(parentId, +1);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = parentId ? LANG.responder : LANG.publicar_mensaje; }
    }
}

// ── LIKE / UNLIKE ─────────────────────────────────────────────────────────────
async function toggleLike(btn) {
    if (!USER_LOGGED) { window.location.href = LOGIN_URL; return; }
    const idPost   = btn.dataset.id;
    const token    = getLikeToken();
    const wasLiked = btn.classList.contains('liked');
    btn.classList.toggle('liked');

    try {
        const fd = new FormData();
        fd.append('action',    'like');
        fd.append('id_huella', ID_HUELLA);
        fd.append('id_post',   idPost);
        fd.append('token',     token);
        const res  = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg);
        btn.classList.toggle('liked', data.liked);

    } catch {
        btn.classList.toggle('liked', wasLiked);
    }
}

// ── CARGAR RESPUESTAS ─────────────────────────────────────────────────────────
async function cargarRespuestas(idPost) {
    const lista = document.getElementById('lista-' + idPost);
    if (!lista || lista.dataset.loaded === '1') return;
    lista.dataset.loaded = '1';

    try {
        const res  = await fetch(`${API_URL}?action=respuestas&id_huella=${ID_HUELLA}&id_post=${idPost}`);
        const data = await res.json();
        lista.innerHTML = '';
        if (data.ok && data.replies.length > 0) {
            data.replies.forEach(r => lista.insertAdjacentHTML('beforeend', renderPostJS(r, true)));
        }
    } catch {
        lista.innerHTML = '';
    }
}

// ── RESPONDER: mostrar/ocultar sección de replies ────────────────────────────
function focusReply(idPost) {
    if (!USER_LOGGED) { window.location.href = LOGIN_URL; return; }
    const section = document.getElementById('replies-' + idPost);
    if (!section) return;
    const opening = !section.classList.contains('post-replies--open');
    section.classList.toggle('post-replies--open', opening);
    if (opening) {
        cargarRespuestas(idPost);
        const ta = document.getElementById('texto-reply-' + idPost);
        if (ta) {
            setTimeout(() => {
                ta.focus();
                ta.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
        }
    }
}

// ── MODAL DE CONFIRMACIÓN ─────────────────────────────────────────────────────
function mostrarModal(onConfirm) {
    const overlay = document.getElementById('modalOverlay');
    overlay.classList.add('visible');
    const confirmar = document.getElementById('modalConfirmar');
    const cancelar  = document.getElementById('modalCancelar');
    const cerrar = () => { overlay.classList.remove('visible'); };
    confirmar.onclick = () => { cerrar(); onConfirm(); };
    cancelar.onclick  = cerrar;
    overlay.onclick   = (e) => { if (e.target === overlay) cerrar(); };
}

// ── ELIMINAR POST ─────────────────────────────────────────────────────────────
function eliminarPost(idPost, btn) {
    mostrarModal(async () => {
        const fd = new FormData();
        fd.append('action',    'eliminar_post');
        fd.append('id_huella', ID_HUELLA);
        fd.append('id_post',   idPost);

        try {
            const res  = await fetch(API_URL, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.msg);

            const card = document.getElementById('post-' + idPost);
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(0.97)';
                setTimeout(() => card.remove(), 300);
            }
        } catch(e) {
            alert('Error: ' + e.message);
        }
    });
}

// ── ACTUALIZAR CONTADOR REPLIES ───────────────────────────────────────────────
function actualizarCounterReplies(parentId, delta) {
    const btn = document.querySelector(`#post-${parentId} .post-accion--reply`);
    if (!btn) return;
    let countEl = btn.querySelector('.post-accion__count');
    const actual = countEl ? parseInt(countEl.textContent) : 0;
    const nuevo  = actual + delta;
    if (nuevo > 0) {
        if (!countEl) { countEl = document.createElement('span'); countEl.className = 'post-accion__count'; btn.appendChild(countEl); }
        countEl.textContent = nuevo;
    } else {
        countEl?.remove();
    }
}

// ── RENDERIZADO JS (para posts creados dinámicamente) ─────────────────────────
function tiempoRelativoJS() { return LANG.hace_momento; }

function renderMediaJS(post) {
    let html = '';
    if (post.imagenes?.length) {
        const n = Math.min(post.imagenes.length, 4);
        html += `<div class="post-card__imagenes post-card__imagenes--${n}">`;
        post.imagenes.slice(0, 4).forEach(img => {
            html += `<img class="post-img" src="${ASSET_BASE}${img.Img_Ruta_Carpeta}${img.Img_Nombre}" alt="" loading="lazy" onerror="this.style.display='none'">`;
        });
        html += '</div>';
    }
    post.videos?.forEach(v => {
        html += `<video class="post-video" controls preload="metadata"><source src="${ASSET_BASE}${v.Video_Ruta_Carpeta}${v.Video_Nombre}"></video>`;
    });
    post.audios?.forEach(a => {
        html += `<div class="post-audio-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
            <audio controls preload="none"><source src="${ASSET_BASE}${a.Audio_Ruta_Carpeta}${a.Audio_Nombre}"></audio>
        </div>`;
    });
    if (post.Post_Link) {
        const raw     = post.Post_Link;
        const safeUrl = /^https?:\/\//i.test(raw) ? raw : 'https://' + raw;
        let host = raw;
        try { host = new URL(safeUrl).hostname; } catch {}
        const favicon = `https://www.google.com/s2/favicons?domain=${encodeURIComponent(host)}&sz=32`;
        html += `<a class="post-link-card" href="${escHtml(safeUrl)}" target="_blank" rel="noopener noreferrer">
            <img class="post-link-card__favicon" src="${favicon}" alt="" onerror="this.style.display='none'">
            <span class="post-link-card__url">${escHtml(host)}</span>
        </a>`;
    }
    return html;
}

const SVG_HEART = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>`;
const SVG_DEL   = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>`;
const SVG_EDIT  = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>`;
const SVG_OK    = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;

function puedeModerar(post) {
    return USER_ADMIN || (USER_LOGGED && USER_ID !== '' && (post.Post_Id_user || '') === USER_ID);
}

function renderPostJS(post, esRespuesta) {
    const id      = post.Id_Post;
    const autor   = escHtml(post.Post_Autor || LANG.sin_nombre);
    const inicial = (post.Post_Autor || '?')[0].toUpperCase();
    const texto   = escHtml(post.Post_Texto || '').replace(/\n/g, '<br>');
    const moderar = puedeModerar(post);
    const inicialSesion = USER_LOGGED ? (USER_NOMBRE[0] || '?').toUpperCase() : '?';
    const topReplyHtml  = USER_LOGGED
        ? `<div class="compositor__avatar" id="avatar-reply-${id}">${inicialSesion}</div><span class="compositor__nombre compositor__nombre--sesion">${escHtml(USER_NOMBRE)}</span>`
        : `<div class="compositor__avatar" id="avatar-reply-${id}">?</div><input type="text" class="compositor__nombre" id="nombre-reply-${id}" placeholder="${LANG.tu_nombre}" maxlength="80" oninput="actualizarAvatar('avatar-reply-${id}', this.value)">`;

    if (esRespuesta) {
        return `
        <div class="reply-card" id="post-${id}" data-id="${id}">
            ${renderMediaJS(post, true)}
            <div class="reply-card__body">
                <div class="reply-card__top">
                    <div class="reply-card__avatar">${inicial}</div>
                    <div class="reply-card__meta">
                        <span class="reply-card__autor">${autor}</span>
                        <span class="reply-card__fecha">${LANG.hace_momento}</span>
                    </div>
                    <button class="btn-like btn-like--sm" data-id="${id}" onclick="toggleLike(this)">${SVG_HEART}</button>
                    ${moderar ? `<button class="btn-delete btn-delete--sm" onclick="eliminarPost('${id}', this)">${SVG_DEL}</button>` : ''}
                </div>
                ${texto ? `<p class="reply-card__texto">${texto}</p>` : ''}
            </div>
        </div>`;
    }

    const mediaHtml = renderMediaJS(post);
    return `
    <div class="card post-card" id="post-${id}" data-id="${id}" data-replies="0">
        <div class="post-card__body">
            ${moderar ? `<div class="post-card__header" id="header-${id}">
                <button class="btn-edit" onclick="editarPost('${id}')">${SVG_EDIT}</button>
                <button class="btn-delete" onclick="eliminarPost('${id}', this)">${SVG_DEL}</button>
            </div>` : ''}
            ${mediaHtml ? `<div class="post-card__media">${mediaHtml}</div>` : ''}
            <div class="post-card__content">
                <button class="btn-like" data-id="${id}" onclick="toggleLike(this)">${SVG_HEART}</button>
                ${texto ? `<p class="post-card__texto">${texto}</p>` : ''}
            </div>
            <div class="post-card__actions"><span class="post-card__autor">${autor}</span><button class="post-card__reply-link" onclick="focusReply('${id}')">${LANG.responder}</button></div>
        </div>
        <div class="post-replies" id="replies-${id}">
            <div class="post-replies__lista" id="lista-${id}" data-loaded="0"></div>
            <div class="compositor compositor--reply" id="compositor-reply-${id}">
                <div class="compositor__top">
                    ${topReplyHtml}
                </div>
                <textarea class="compositor__texto" id="texto-reply-${id}" placeholder="${LANG.escribe_respuesta}" rows="2" oninput="autoResize(this)"></textarea>
                <div class="compositor__link-panel" id="linkPanel-reply-${id}" style="display:none">
                    <input type="url" class="compositor__texto compositor__link-input" placeholder="https://" autocomplete="off">
                </div>
                <div class="compositor__previews" id="previews-reply-${id}"></div>
                <div class="compositor__barra">
                    <div class="compositor__media-btns">
                        <button type="button" class="compositor__btn-media" onclick="triggerInput('inputFotos-reply-${id}')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></button>
                        <button type="button" class="compositor__btn-media" onclick="triggerInput('inputVideo-reply-${id}')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></button>
                        <button type="button" class="compositor__btn-media" onclick="triggerInput('inputAudio-reply-${id}')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></button>
                        <button type="button" id="btnLink-reply-${id}" class="compositor__btn-media" onclick="toggleLinkPanel('reply-${id}')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></button>
                    </div>
                    <button type="button" class="compositor__enviar compositor__enviar--sm" onclick="submitPost('${id}', 'reply-${id}')">${LANG.responder}</button>
                </div>
                <input type="file" id="inputFotos-reply-${id}" accept="image/*" multiple style="display:none" onchange="handleFotos(this, 'previews-reply-${id}')">
                <input type="file" id="inputVideo-reply-${id}" accept="video/mp4,video/webm,video/mov" style="display:none" onchange="handleArchivo(this, 'previews-reply-${id}', 'video')">
                <input type="file" id="inputAudio-reply-${id}" accept="audio/*" style="display:none" onchange="handleArchivo(this, 'previews-reply-${id}', 'audio')">
            </div>
        </div>
    </div>`;
}

// ── EDITAR POST ───────────────────────────────────────────────────────────────
function editarPost(idPost) {
    const card   = document.getElementById('post-' + idPost);
    const textoEl = card?.querySelector('.post-card__texto');
    const header  = document.getElementById('header-' + idPost);
    if (!textoEl || !header) return;

    // Texto original (innerText decodifica entidades y convierte <br> en \n)
    const textoOriginal = textoEl.innerText;

    // Reemplazar párrafo con textarea
    const ta = document.createElement('textarea');
    ta.className = 'post-edit__textarea';
    ta.value = textoOriginal;
    ta.id    = 'edit-ta-' + idPost;
    textoEl.replaceWith(ta);
    ta.focus();
    autoResize(ta);
    ta.addEventListener('input', () => autoResize(ta));

    // Guardar HTML original de la cabecera y reemplazar por guardar/cancelar
    header.dataset.originalHtml = header.innerHTML;
    header.innerHTML =
        `<button class="btn-save" onclick="guardarEdicion('${idPost}')" title="<?= addslashes($lang['guardar']) ?>">${SVG_OK}</button>` +
        `<button class="btn-cancel-edit" onclick="cancelarEdicion('${idPost}')" title="<?= addslashes($lang['cancelar']) ?>">${SVG_DEL}</button>`;
}

async function guardarEdicion(idPost) {
    const ta     = document.getElementById('edit-ta-' + idPost);
    const header = document.getElementById('header-' + idPost);
    if (!ta) return;

    const texto = ta.value.trim();
    const fd = new FormData();
    fd.append('action',    'editar_post');
    fd.append('id_huella', ID_HUELLA);
    fd.append('id_post',   idPost);
    fd.append('texto',     texto);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg);

        // Restaurar párrafo con nuevo texto
        const p = document.createElement('p');
        p.className = 'post-card__texto';
        p.innerHTML = escHtml(data.texto).replace(/\n/g, '<br>');
        ta.replaceWith(p);
        _restaurarCabecera(header, idPost);
    } catch(e) {
        alert('Error: ' + e.message);
    }
}

function cancelarEdicion(idPost) {
    const ta     = document.getElementById('edit-ta-' + idPost);
    const header = document.getElementById('header-' + idPost);
    if (!ta) return;

    const p = document.createElement('p');
    p.className = 'post-card__texto';
    p.innerHTML = escHtml(ta.value).replace(/\n/g, '<br>');
    ta.replaceWith(p);
    _restaurarCabecera(header, idPost);
}

function _restaurarCabecera(header, idPost) {
    if (header?.dataset.originalHtml) {
        header.innerHTML = header.dataset.originalHtml;
        delete header.dataset.originalHtml;
    }
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

</script>

</body>
</html>
