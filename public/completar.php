<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';

$tema    = cargarTema();
$huellas = listarHuellas();

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

if (!$huella || !esAdminHuella($huella['Id_Huella'])) {
    if (!sesionActiva()) {
        header('Location: /huella/login.php?back=' . urlencode($_SERVER['REQUEST_URI']));
    } else {
        http_response_code(403);
        header('Location: /huella/' . ($linkActual ?? ''));
    }
    exit;
}

$nombreProducto = $tema['nombre_producto'];
$logoPath       = asset($tema['logo_path']);
$logoAlt        = $tema['logo_alt'];

$aviso      = null;  // 'ok' | 'error'
$avisoMsg   = '';
$avisoPanel = null;  // 'ok' | 'error' — para el panel de aviso
$toastMsg   = '';    // mensaje de confirmación tipo toast
if (isset($_GET['bio_ok'])) {
    $toastMsg = 'Biografía generada y guardada correctamente.';
}

// ── PROCESAR ACTOS ────────────────────────────────────────────────────────
$avisoActo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $huella && isset($_POST['accion_acto'])) {
    $idHuella = $huella['Id_Huella'];
    $idTarea  = trim($_POST['id_tarea']  ?? '');
    $empresa  = trim($_POST['empresa']   ?? '');
    $idLugar  = trim($_POST['id_lugar']  ?? '');
    $db       = getDB();

    if ($_POST['accion_acto'] === 'toggle_visible') {
        $nuevoVisible = ((int)($_POST['visible'] ?? 1)) ? 0 : 1;
        $st = $db->prepare("UPDATE h_huellaactividades SET Act_Visible=? WHERE Id_Huella=? AND Id_TareaBase=? AND Empresa=? AND Id_Lugar=?");
        $st->bind_param('issss', $nuevoVisible, $idHuella, $idTarea, $empresa, $idLugar);
        $avisoActo = $st->execute() ? 'ok' : 'error';
        if ($avisoActo === 'ok') $toastMsg = $nuevoVisible ? ($lang['toast_evento_visible'] ?? '') : ($lang['toast_evento_oculto'] ?? '');
        $st->close();
    } elseif ($_POST['accion_acto'] === 'guardar_texto') {
        $texto = trim($_POST['act_texto'] ?? '');
        $st = $db->prepare("UPDATE h_huellaactividades SET Act_Texto=? WHERE Id_Huella=? AND Id_TareaBase=? AND Empresa=? AND Id_Lugar=?");
        $st->bind_param('sssss', $texto, $idHuella, $idTarea, $empresa, $idLugar);
        $avisoActo = $st->execute() ? 'ok' : 'error';
        if ($avisoActo === 'ok') $toastMsg = $lang['toast_obs_guardadas'] ?? '';
        $st->close();
    }
}

// ── PROCESAR MENSAJES ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $huella && isset($_POST['accion_msg'])) {
    $idHuella = $huella['Id_Huella'];
    $db       = getDB();

    if ($_POST['accion_msg'] === 'agregar') {
        $st = $db->prepare("INSERT INTO h_huella_mensajes (Id_Huella, Msg_Titulo, Msg_Texto, Msg_Orden) SELECT ?, '', '', COALESCE(MAX(Msg_Orden),0)+1 FROM h_huella_mensajes WHERE Id_Huella=?");
        $st->bind_param('ss', $idHuella, $idHuella);
        $st->execute();
        $st->close();
        $toastMsg = $lang['toast_comunicado_anadido'] ?? '';
    } elseif ($_POST['accion_msg'] === 'guardar') {
        $id    = (int)($_POST['id_mensaje'] ?? 0);
        $titulo = trim($_POST['msg_titulo'] ?? '');
        $texto  = trim($_POST['msg_texto']  ?? '');
        $st = $db->prepare("UPDATE h_huella_mensajes SET Msg_Titulo=?, Msg_Texto=? WHERE Id_Mensaje=? AND Id_Huella=?");
        $st->bind_param('ssis', $titulo, $texto, $id, $idHuella);
        if ($st->execute()) $toastMsg = $lang['toast_comunicado_guardado'] ?? '';
        $st->close();
    } elseif ($_POST['accion_msg'] === 'eliminar') {
        $id = (int)($_POST['id_mensaje'] ?? 0);
        $st = $db->prepare("DELETE FROM h_huella_mensajes WHERE Id_Mensaje=? AND Id_Huella=?");
        $st->bind_param('is', $id, $idHuella);
        if ($st->execute()) $toastMsg = $lang['toast_comunicado_eliminado'] ?? '';
        $st->close();
    } elseif ($_POST['accion_msg'] === 'generar_agradecimiento') {
        require_once __DIR__ . '/../includes/groq.php';

        // Recoger todos los mensajes del muro (no eliminados ni vetados)
        $st = $db->prepare("
            SELECT Post_Autor, Post_Texto
            FROM h_huellapost
            WHERE Id_Huella = ?
              AND Post_Eliminado = 0
              AND (Post_Vetado IS NULL OR Post_Vetado = 0)
              AND Post_Texto IS NOT NULL
              AND Post_Texto <> ''
            ORDER BY Post_Fecha ASC
        ");
        $st->bind_param('s', $idHuella);
        $st->execute();
        $postsMuro = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        if (empty($postsMuro)) {
            $toastMsg = $lang['toast_agradecimiento_sin_msg'] ?? 'No hay mensajes en el muro para generar agradecimiento.';
        } else {
            // Construir contexto con autor + texto de cada mensaje
            $contexto = '';
            foreach ($postsMuro as $pm) {
                $autor = trim($pm['Post_Autor'] ?? '') ?: 'Anónimo';
                $contexto .= "- {$autor}: \"" . trim($pm['Post_Texto']) . "\"\n";
            }

            $nombreFall = trim(
                ($huella['Fallecido_Nombre']     ?? '') . ' ' .
                ($huella['Fallecido_Apellido_1'] ?? '') . ' ' .
                ($huella['Fallecido_Apellido_2'] ?? '')
            ) ?: 'el fallecido';

            $sistema = "Eres un redactor de mensajes de agradecimiento para memoriales digitales.\n\n"
                     . "REGLAS ESTRICTAS:\n"
                     . "1. El mensaje lo firma LA FAMILIA. Escribe en primera persona del plural (nosotros, la familia).\n"
                     . "2. NO menciones a ninguna persona por su nombre individualmente, ni siquiera si los mensajes lo facilitan.\n"
                     . "3. NO inventes datos sobre el fallecido (profesión, aficiones, anécdotas, lugares, fechas) ni uses conocimiento previo sobre su nombre.\n"
                     . "4. El agradecimiento es GENERAL: por las palabras de apoyo, el cariño y el acompañamiento. Apóyate en el TONO de los mensajes recibidos, no en hechos concretos que no aparezcan ahí.\n"
                     . "5. PROHIBIDO incluir título, saludo inicial, comillas, markdown o despedida tipo 'Atentamente'.\n\n"
                     . "ESTILO:\n"
                     . "- Tono cálido, respetuoso, sincero y sobrio. Nada de frases grandilocuentes ni cursilería.\n"
                     . "- BREVE: máximo 4 frases, ~80-120 palabras.\n"
                     . "- Texto continuo, un solo párrafo.";

            $prompt = "Mensajes recibidos en el muro del memorial de {$nombreFall}:\n\n"
                    . $contexto
                    . "\nRedacta un único agradecimiento breve y emotivo dirigido en general a todas las personas que han dejado palabras de apoyo. "
                    . "Recuerda: sin nombres propios, sin inventar nada sobre el fallecido, sin saludo ni firma, máximo 4 frases.";

            $r = llamarGroq($prompt, $sistema, [
                'max_tokens'  => 600,
                'temperature' => 0.3,
            ]);

            if (!$r['ok']) {
                $toastMsg = ($lang['toast_agradecimiento_error'] ?? 'Error al generar agradecimiento: ') . $r['error'];
            } else {
                $titulo = 'Gracias';
                $texto  = $r['texto'];
                $st = $db->prepare("
                    INSERT INTO h_huella_mensajes (Id_Huella, Msg_Titulo, Msg_Texto, Msg_Orden)
                    SELECT ?, ?, ?, COALESCE(MAX(Msg_Orden),0)+1
                    FROM h_huella_mensajes WHERE Id_Huella=?
                ");
                $st->bind_param('ssss', $idHuella, $titulo, $texto, $idHuella);
                if ($st->execute()) {
                    $toastMsg = $lang['toast_agradecimiento_ok'] ?? 'Agradecimiento generado y publicado.';
                } else {
                    $toastMsg = $lang['toast_agradecimiento_save_err'] ?? 'Error al guardar el comunicado.';
                }
                $st->close();
            }
        }
    }
}

// ── PROCESAR AVISO DESTACADO ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $huella && isset($_POST['accion_muro'])) {
    $db       = getDB();
    $idHuella = $huella['Id_Huella'];
    $nuevoVal = $_POST['accion_muro'] === 'activar' ? 1 : 0;
    $stmt = $db->prepare("UPDATE h_huella SET AutorizarMuro_SN=? WHERE Id_Huella=?");
    $stmt->bind_param('is', $nuevoVal, $idHuella);
    $stmt->execute();
    $toastMsg = $nuevoVal ? ($lang['toast_muro_activado'] ?? '') : ($lang['toast_muro_desactivado'] ?? '');
    $huella   = obtenerHuellaByLink($linkActual);
    $tablonAbierto = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $huella && isset($_POST['accion_aviso'])) {
    $db       = getDB();
    $idHuella = $huella['Id_Huella'];
    if ($_POST['accion_aviso'] === 'publicar') {
        $textoAviso = trim($_POST['aviso_texto'] ?? '');
        $stmt = $db->prepare("UPDATE h_huella SET Aviso_Texto=? WHERE Id_Huella=?");
        $stmt->bind_param('ss', $textoAviso, $idHuella);
        $avisoPanel = $stmt->execute() ? 'ok_pub' : 'error';
        if ($avisoPanel === 'ok_pub') $toastMsg = $lang['toast_aviso_publicado'] ?? '';
    } elseif ($_POST['accion_aviso'] === 'retirar') {
        $stmt = $db->prepare("UPDATE h_huella SET Aviso_Texto=NULL WHERE Id_Huella=?");
        $stmt->bind_param('s', $idHuella);
        $avisoPanel = $stmt->execute() ? 'ok_ret' : 'error';
        if ($avisoPanel === 'ok_ret') $toastMsg = $lang['toast_aviso_retirado'] ?? '';
    }
    $huella = obtenerHuellaByLink($linkActual);
}

// ── PROCESAR FORMULARIO ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $huella && !isset($_POST['accion_aviso'])) {
    $idHuella = $huella['Id_Huella'];
    $db       = getDB();

    $titulo = trim($_POST['titulo'] ?? '');
    $texto  = trim($_POST['texto']  ?? '');
    $foto   = $huella['Fallecido_Foto'] ?? null;

    // Subir foto si se envió una
    if (!empty($_FILES['foto']['name'])) {
        try {
            // La foto del fallecido va en uploads/fallecidos/{Id_Huella}/
            $ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $permitidos, true)) {
                throw new RuntimeException('Formato no permitido');
            }
            if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('La imagen supera los 5 MB');
            }

            $carpeta = __DIR__ . '/uploads/fallecidos/' . $idHuella . '/';
            if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

            // Borrar foto anterior si existe
            if ($foto && file_exists(__DIR__ . '/' . $foto)) {
                unlink(__DIR__ . '/' . $foto);
            }

            $nombreArchivo = 'foto.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $carpeta . $nombreArchivo);
            $foto = 'uploads/fallecidos/' . $idHuella . '/' . $nombreArchivo;

        } catch (RuntimeException $e) {
            $aviso    = 'error';
            $avisoMsg = $e->getMessage();
        }
    }

    if ($aviso !== 'error') {
        $colDefun = 'Fallecido_Fecha_Defunción';
        $stmt = $db->prepare("
            UPDATE h_huellafallecido
            SET Fallecido_Titulo = ?,
                Fallecido_Texto  = ?,
                Fallecido_Foto   = ?
            WHERE Id_Huella = ?
        ");
        $stmt->bind_param('ssss', $titulo, $texto, $foto, $idHuella);

        if ($stmt->execute()) {
            $aviso    = 'ok';
            $avisoMsg = $lang['completar_ok'];
            $toastMsg = $lang['toast_info_guardada'] ?? '';
            // Recargar datos del fallecido con los nuevos valores
            $huella = obtenerHuellaByLink($linkActual);
        } else {
            $aviso    = 'error';
            $avisoMsg = $lang['completar_error'];
        }
    }
}

// Todos los actos (incluyendo ocultos) para el panel de admin
$todosActos = [];
if ($huella) {
    $db2 = getDB();
    $st2 = $db2->prepare("
        SELECT a.Id_TareaBase, a.Empresa, a.Id_Lugar,
               a.Act_FechaHora_Inicio, a.Act_FechaHora_Fin,
               a.Act_Texto, a.Act_Sala, a.Act_Visible,
               t.Literal AS Tipo_Literal,
               l.Lugar_Direccion, l.Lugar_Ubicacion, `l`.`Lugar_Teléfono`
        FROM h_huellaactividades a
        LEFT JOIN hs_tareasbase t ON a.Id_TareaBase = t.Id_TareaBase
        LEFT JOIN h_empresaslugareshabituales l
               ON l.Id_Lugar = a.Id_Lugar
              AND l.Id_Cliente = ?
              AND l.Empresa = a.Empresa
        WHERE a.Id_Huella = ?
        ORDER BY a.Act_FechaHora_Inicio ASC
    ");
    $st2->bind_param('ss', $huella['Id_Cliente'], $huella['Id_Huella']);
    $st2->execute();
    $todosActos = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
    $st2->close();
}

$asistenciaDetalle = $huella ? obtenerAsistenciaDetalle($huella['Id_Huella']) : [];

// Mensajes del tablón
$mensajesTablon = [];
if ($huella) {
    $dbM = getDB();
    $stM = $dbM->prepare("SELECT Id_Mensaje, Msg_Titulo, Msg_Texto FROM h_huella_mensajes WHERE Id_Huella=? ORDER BY Msg_Orden ASC, Id_Mensaje ASC");
    $stM->bind_param('s', $huella['Id_Huella']);
    $stM->execute();
    $mensajesTablon = $stM->get_result()->fetch_all(MYSQLI_ASSOC);
    $stM->close();
}
$tablonAbierto = isset($_GET['tablon']) || isset($_POST['accion_msg']) || isset($_POST['accion_aviso']) || isset($_POST['accion_muro']);

// ── USUARIOS DE ESTA HUELLA ───────────────────────────────────────────────
$uCompletarActual = usuarioActual();
$idUserActual     = (int)($uCompletarActual['id'] ?? 0);
$rolesLabel = [
    'OWN' => 'Propietario', 'ADM' => 'Administrador', 'COL' => 'Colaborador',
    'GFC' => 'Cliente',     'INV' => 'Invitado',       'SOL' => 'Solicitante',
];
$usuariosHuella = [];
if ($huella) {
    $dbU = getDB();
    $stU = $dbU->prepare("
        SELECT Id, User_Identificador, User_Nombre_Apellidos, Id_Rol, User_Baneado
        FROM h_huellausersroles
        WHERE Id_Huella = ?
        ORDER BY FIELD(Id_Rol,'OWN','ADM','COL','GFC','INV','SOL'), User_Nombre_Apellidos
    ");
    $stU->bind_param('s', $huella['Id_Huella']);
    $stU->execute();
    $usuariosHuella = $stU->get_result()->fetch_all(MYSQLI_ASSOC);
    $stU->close();
}

// Valores actuales para rellenar el formulario
$avisoActual = $huella['Aviso_Texto'] ?? '';
$fotoActual  = $huella['Fallecido_Foto']   ?? '';
$tituloActual= htmlspecialchars($huella['Fallecido_Titulo'] ?? '');
$textoActual = htmlspecialchars($huella['Fallecido_Texto']  ?? '');
$nombreFallecido = trim(
    ($huella['Fallecido_Nombre']      ?? '') . ' ' .
    ($huella['Fallecido_Apellido_1']  ?? '') . ' ' .
    ($huella['Fallecido_Apellido_2']  ?? '')
);
?>
<!DOCTYPE html>
<html lang="<?= strtolower($idIdioma) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['completar_titulo_pag'] ?> — <?= htmlspecialchars($nombreFallecido ?: $nombreProducto) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/completar.css') ?>">
    <?php require __DIR__ . '/../includes/session_guard.php'; ?>
    <style>:root { <?= $cssVars ?> }
    /* ── Hero de cabecera (estilo lanzador) ── */
    .completar-hero {
        background: var(--color-fondo-card);
        border-bottom: 1px solid var(--color-borde);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 1.75rem 1rem 1.4rem;
        margin-bottom: 0.75rem;
        border-radius: 0 0 16px 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,.07);
    }
    .completar-hero__foto {
        width: 90px; height: 90px;
        border-radius: 50%;
        border: 3px solid var(--color-acento, #c4a882);
        overflow: hidden;
        display: flex; align-items: center; justify-content: center;
        background: var(--color-fondo);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
    }
    .completar-hero__foto img {
        width: 100%; height: 100%;
        object-fit: cover;
    }
    .completar-hero__label {
        font-size: 0.68rem;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--color-primario);
        margin-bottom: 0.3rem;
        font-weight: 600;
    }
    .completar-hero__titulo {
        font-family: var(--fuente-titulo);
        font-size: 1.45rem;
        font-weight: 700;
        color: var(--color-texto);
        margin-bottom: 0.2rem;
    }
    .completar-hero__sub {
        font-size: 0.82rem;
        color: var(--color-texto-suave);
        letter-spacing: 0.04em;
        margin-bottom: 0.5rem;
    }
    .completar-hero__desc {
        font-size: 0.8rem;
        color: var(--color-texto-suave);
        max-width: 320px;
        line-height: 1.55;
        margin-top: 0.4rem;
    }

    .completar-header__ojo {
        -webkit-appearance: none;
        appearance: none;
        flex-shrink: 0;
        width: 38px; height: 38px;
        display: flex; align-items: center; justify-content: center;
        background-color: var(--color-fondo-card);
        border: 1px solid var(--color-borde);
        border-radius: 10px;
        color: var(--color-texto-suave);
        cursor: pointer;
        padding: 0; margin: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
        transition: color .18s, border-color .18s, background-color .18s;
        outline: none;
        font-size: 0; line-height: 0;
    }
    .completar-header__ojo:hover {
        background-color: var(--color-fondo);
        border-color: var(--color-texto-suave);
        color: var(--color-texto);
    }
    .completar-header__ojo svg { width:17px; height:17px; display:block; flex-shrink:0; }

    /* ── Gestión de usuarios ── */
    .bloque { margin: 0 0 1rem; }
    .bloque__label { font-size:.68rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--color-texto-suave); margin:0 0 .45rem .1rem; }
    .usuario-card { background:var(--color-fondo-card); border:1px solid var(--color-borde); border-radius:14px; padding:.8rem 1rem; display:flex; align-items:center; gap:.75rem; margin-bottom:.45rem; }
    .usuario-card__avatar { width:40px; height:40px; border-radius:50%; background:var(--color-primario); color:#fff; font-weight:700; font-size:1rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-family:var(--fuente-titulo); }
    .usuario-card__info { flex:1; min-width:0; }
    .usuario-card__nombre { font-size:.88rem; font-weight:600; color:var(--color-texto); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .usuario-card__contacto { font-size:.74rem; color:var(--color-texto-suave); margin-top:.1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .usuario-card__acciones { display:flex; align-items:center; gap:.4rem; flex-shrink:0; }
    .rol-chip { font-size:.67rem; font-weight:700; letter-spacing:.04em; padding:.2rem .5rem; border-radius:20px; border:1.5px solid transparent; white-space:nowrap; }
    .rol-chip--ADM,.rol-chip--OWN { background:#ede9fe; border-color:#c4b5fd; color:#6d28d9; }
    .rol-chip--COL { background:#ecfdf5; border-color:#6ee7b7; color:#065f46; }
    .rol-chip--GFC { background:#eff6ff; border-color:#93c5fd; color:#1e40af; }
    .rol-chip--INV { background:#fdf4ff; border-color:#e879f9; color:#86198f; }
    .rol-chip--SOL { background:#fff7ed; border-color:#fdba74; color:#c2410c; }
    .btn-rol { border:none; cursor:pointer; border-radius:8px; padding:.32rem .6rem; font-size:.73rem; font-weight:600; white-space:nowrap; }
    .btn-rol--promover { background:#ede9fe; color:#6d28d9; border:1.5px solid #c4b5fd; }
    .btn-rol--degradar { background:#fce7f3; color:#be185d; border:1.5px solid #f9a8d4; }
    .btn-eliminar-u { border:none; cursor:pointer; background:none; padding:.3rem; border-radius:8px; color:var(--color-texto-suave); display:flex; align-items:center; transition:background .15s,color .15s; }
    .btn-eliminar-u:hover { background:#fee2e2; color:#dc2626; }
    .btn-eliminar-u svg { width:15px; height:15px; }
    .btn-banear-u { border:none; cursor:pointer; background:none; padding:.3rem; border-radius:8px; color:var(--color-texto-suave); display:flex; align-items:center; transition:background .15s,color .15s; }
    .btn-banear-u:hover { background:#fee2e2; color:#dc2626; }
    .btn-desbanear-u { border:none; cursor:pointer; background:none; padding:.3rem; border-radius:8px; color:#16a34a; display:flex; align-items:center; transition:background .15s,color .15s; }
    .btn-desbanear-u:hover { background:#dcfce7; color:#15803d; }
    .usuario-card--baneado { opacity:.55; border-color:#fca5a5; }
    .usuario-card--baneado .usuario-card__avatar { background:#ef4444; }
    .buscar-wrap-u { display:flex; gap:.5rem; margin-bottom:.6rem; }
    .buscar-wrap-u__campo { flex:1; position:relative; display:flex; align-items:center; }
    .buscar-wrap-u input { width:100%; padding:.65rem 2.2rem .65rem .9rem; border-radius:10px; border:1.5px solid var(--color-borde); background:var(--color-fondo-card); color:var(--color-texto); font-size:.84rem; outline:none; box-sizing:border-box; transition:border-color .15s; }
    .buscar-wrap-u input:focus { border-color:var(--color-primario); }
    .buscar-wrap-u > button { padding:.65rem 1rem; border-radius:10px; border:none; background:var(--color-primario); color:#fff; font-size:.84rem; font-weight:600; cursor:pointer; flex-shrink:0; white-space:nowrap; }
    .buscar-clear-u { position:absolute; right:.6rem; background:none; border:none; cursor:pointer; color:var(--color-texto-suave); padding:.15rem; display:none; line-height:1; border-radius:4px; }
    .buscar-clear-u:hover { color:var(--color-texto); background:var(--color-borde); }
    .buscar-clear-u svg { width:14px; height:14px; display:block; }
    .sin-resultados-filtro-u { text-align:center; padding:1.5rem 1rem; color:var(--color-texto-suave); font-size:.84rem; display:none; }
    .lista-vacia-u { text-align:center; padding:2rem 1rem; color:var(--color-texto-suave); font-size:.85rem; }
    .lista-vacia-u svg { width:36px; height:36px; margin-bottom:.6rem; opacity:.4; display:block; margin-inline:auto; }
    .toast-comp { position:fixed; bottom:5.5rem; left:50%; transform:translateX(-50%) translateY(6px); background:#166534; color:#fff; padding:.5rem 1rem .5rem .75rem; border-radius:20px; font-size:.82rem; font-weight:600; white-space:nowrap; opacity:0; pointer-events:none; transition:opacity .22s, transform .22s; z-index:9999; box-shadow:0 4px 16px rgba(0,0,0,.18); display:flex; align-items:center; gap:.4rem; }
    .toast-comp.show { opacity:1; transform:translateX(-50%) translateY(0); }

    /* ── Botones del pie del tablón (Añadir + Generar IA) ── */
    .tablon-msg-card__acciones-pie {
        display: flex;
        gap: .5rem;
        margin: 0 .9rem .75rem;
        flex-wrap: wrap;
    }
    .tablon-msg-card__acciones-pie > form { margin: 0; }
    .tablon-msg-card__acciones-pie .tablon-msg-card__btn-agregar {
        width: 100% !important;
        margin: 0 !important;
    }
    .tablon-msg-card__btn-ia {
        -webkit-appearance: none;
        appearance: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        width: 100%;
        padding: .75rem 1rem;
        border-radius: 12px;
        border: none;
        background: linear-gradient(135deg, #7c3aed 0%, #c026d3 100%);
        color: #fff;
        font-size: .85rem;
        font-weight: 600;
        font-family: var(--fuente-cuerpo);
        line-height: 1.2;
        cursor: pointer;
        transition: filter .15s, transform .15s, box-shadow .15s;
        box-shadow: 0 2px 8px rgba(124, 58, 237, .25);
    }
    .tablon-msg-card__btn-ia:hover {
        filter: brightness(1.08);
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(124, 58, 237, .35);
    }
    .tablon-msg-card__btn-ia:active {
        transform: translateY(0);
    }
    .tablon-msg-card__btn-ia:disabled {
        opacity: .65;
        cursor: wait;
        transform: none;
        filter: none;
    }
    .tablon-msg-card__btn-ia svg { flex-shrink: 0; }
    </style>
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
        <?php $uActual = usuarioActual(); if ($uActual): ?>
        <?= renderMenuUsuario($uActual, $_SERVER['REQUEST_URI']) ?>
        <?php else: ?>
        <a href="/huella/login.php?back=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
           class="btn-icon" title="Iniciar sesión">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
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
        <div class="card card-bio"><p><?= $lang['error_huella'] ?></p></div>
    <?php else: ?>

    <!-- HERO DE CABECERA -->
    <div class="completar-hero">
        <div class="completar-hero__foto">
            <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($logoAlt) ?>">
        </div>
        <h1 class="completar-hero__titulo">Administrar</h1>
        <?php if ($nombreFallecido): ?>
            <p class="completar-hero__sub"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
        <?php endif; ?>
        <p class="completar-hero__desc">Completa la historia del fallecido, gestiona los eventos, modera los mensajes del tablón y administra los usuarios vinculados a este memorial.</p>
    </div>

    <?php if ($aviso === 'error'): ?>
    <div class="aviso aviso--error">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($avisoMsg) ?>
    </div>
    <?php endif; ?>

    <!-- CABECERA -->
    <div class="completar-header">
        <div class="completar-header__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div style="flex:1">
            <h1 class="completar-header__titulo"><?= $lang['completar_titulo_pag'] ?></h1>
            <?php if ($nombreFallecido): ?>
                <p class="completar-header__subtitulo"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
            <?php endif; ?>
        </div>
        <button type="button" id="btnToggleForm" class="completar-header__ojo" onclick="toggleFormulario()" title="Mostrar / ocultar">
            <svg id="iconoOjo" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="iconoOjoTachado" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        </button>
    </div>

    <!-- FORMULARIO -->
    <div id="formHistoriaWrap" style="display:none">
    <form method="POST"
          action="<?= urlHuella($linkActual, 'completar') ?>"
          enctype="multipart/form-data"
          id="formCompletar">

        <!-- ── FOTO ── -->
        <div class="card">
            <div class="bloque-foto">
                <div class="foto-preview" id="fotoPreview">
                    <?php if ($fotoActual): ?>
                        <img src="<?= htmlspecialchars(asset($fotoActual)) ?>" alt="Foto del fallecido" id="fotoImg">
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" id="fotoIcono"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <img src="" alt="" id="fotoImg" style="display:none">
                    <?php endif; ?>
                </div>
                <p class="foto-label"><?= $lang['completar_foto_ayuda'] ?></p>
                <input type="file" name="foto" id="inputFoto" accept=".jpg,.jpeg,.png,.webp">
                <button type="button" class="btn-foto" onclick="document.getElementById('inputFoto').click()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <?= $fotoActual ? $lang['completar_cambiar_foto'] : $lang['completar_subir_foto'] ?>
                </button>
            </div>
        </div>

        <!-- ── TÍTULO / FRASE ── -->
        <div class="card">
            <div class="campo-grupo">
                <label class="campo-label" for="inputTitulo"><?= $lang['completar_titulo_bio'] ?></label>
                <input type="text"
                       id="inputTitulo"
                       name="titulo"
                       class="campo-input"
                       placeholder="<?= $lang['completar_titulo_bio_ph'] ?>"
                       maxlength="150"
                       value="<?= $tituloActual ?>"
                       oninput="actualizarContador(this, 'cntTitulo', 150)">
                <div class="campo-contador"><span id="cntTitulo"><?= mb_strlen(html_entity_decode($tituloActual)) ?></span> / 150</div>
            </div>
        </div>

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

        <!-- ── GUARDAR + ASISTENTE ── -->
        <div class="tablon-msg-card__acciones-pie">
            <div style="flex:1">
                <button type="submit" class="tablon-msg-card__btn-agregar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <?= $lang['completar_guardar'] ?>
                </button>
            </div>
            <div style="flex:1">
                <button type="button" class="tablon-msg-card__btn-ia" onclick="window.location.href='<?= htmlspecialchars(urlHuella($linkActual, 'bio-asistente')) ?>'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8 5.8 21.3l2.4-7.4L2 9.4h7.6z"/></svg>
                    Generar biografía con asistente
                </button>
            </div>
        </div>

    </form>
    </div><!-- /#formHistoriaWrap -->

    <!-- ══ DETALLES DE EVENTOS ══ -->
    <div class="completar-header" style="margin-top:1.25rem">
        <div class="completar-header__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div style="flex:1">
            <h2 class="completar-header__titulo"><?= $lang['completar_detalles_eventos'] ?></h2>
            <?php if ($nombreFallecido): ?>
                <p class="completar-header__subtitulo"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
            <?php endif; ?>
        </div>
        <button type="button" class="completar-header__ojo" onclick="toggleActos()" title="Mostrar / ocultar">
            <svg id="iconoOjoActos" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= $avisoActo ? '' : 'style="display:none"' ?>>
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="iconoOjoTachadoActos" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= $avisoActo ? 'style="display:none"' : '' ?>>
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        </button>
    </div>

    <?php if ($avisoActo === 'error'): ?>
    <div class="aviso aviso--error">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Error al actualizar el evento
    </div>
    <?php endif; ?>

    <div id="actosWrap" <?= $avisoActo ? '' : 'style="display:none"' ?>>
    <?php if (empty($todosActos)): ?>
        <div class="card card-bio"><p>No hay eventos configurados.</p></div>
    <?php else: ?>
        <?php $actoIdx = 0; foreach ($todosActos as $acto): ?>
        <?php
            $lugarParts = preg_split('/\s*\x{2014}\s*/u', $acto['Lugar_Direccion'] ?? '', 2);
            $lugarNombre = $lugarParts[0] ?? '';
        ?>
        <div class="card acto-admin <?= $acto['Act_Visible'] ? '' : 'acto-admin--oculto' ?>">

            <div class="acto-admin__header">
                <div class="acto-admin__tipo">
                    <?= iconoActo($acto['Id_TareaBase']) ?>
                    <span><?= htmlspecialchars($lang['acto_' . $acto['Id_TareaBase']] ?? $acto['Tipo_Literal'] ?? $acto['Id_TareaBase']) ?></span>
                </div>
                <div class="acto-admin__meta">
                    <?php if ($lugarNombre): ?>
                    <span class="acto-admin__lugar"><?= htmlspecialchars($lugarNombre) ?></span>
                    <?php endif; ?>
                    <span class="acto-admin__fecha"><?php
                        echo formatearFechaHora($acto['Act_FechaHora_Inicio'], $idIdioma);
                        if (!empty($acto['Act_FechaHora_Fin']) && $acto['Act_FechaHora_Fin'] !== '0000-00-00 00:00:00') {
                            $tsFin = strtotime($acto['Act_FechaHora_Fin']);
                            $tsIni = strtotime($acto['Act_FechaHora_Inicio']);
                            if (date('Y-m-d', $tsFin) !== date('Y-m-d', $tsIni)) {
                                $meses = ['CAS'=>['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],'ENG'=>['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']];
                                $ms = $meses[$idIdioma] ?? $meses['CAS'];
                                echo ' - ' . date('H:i', $tsFin) . ', ' . date('j', $tsFin) . ' ' . $ms[(int)date('n', $tsFin) - 1];
                            } else {
                                echo ' - ' . date('H:i', $tsFin);
                            }
                        }
                    ?></span>
                </div>
                <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>" class="acto-admin__form-vis">
                    <input type="hidden" name="accion_acto" value="toggle_visible">
                    <input type="hidden" name="id_tarea" value="<?= htmlspecialchars($acto['Id_TareaBase']) ?>">
                    <input type="hidden" name="empresa"  value="<?= htmlspecialchars($acto['Empresa']) ?>">
                    <input type="hidden" name="id_lugar" value="<?= htmlspecialchars($acto['Id_Lugar']) ?>">
                    <input type="hidden" name="visible"  value="<?= (int)$acto['Act_Visible'] ?>">
                    <button type="submit" class="acto-admin__btn-vis <?= $acto['Act_Visible'] ? 'acto-admin__btn-vis--visible' : 'acto-admin__btn-vis--oculto' ?>">
                        <?php if ($acto['Act_Visible']): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <?= $lang['completar_visible'] ?>
                        <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        <?= $lang['completar_oculto'] ?>
                        <?php endif; ?>
                    </button>
                </form>
            </div>

            <div class="acto-admin__obs-toggle" onclick="toggleObs(this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span><?= $lang['completar_observaciones'] ?></span>
                <?php if (!empty($acto['Act_Texto'])): ?>
                <span class="acto-admin__obs-dot"></span>
                <?php endif; ?>
                <svg class="acto-admin__obs-chevron" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="acto-admin__obs-body" style="display:none">
                <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>">
                    <input type="hidden" name="accion_acto" value="guardar_texto">
                    <input type="hidden" name="id_tarea" value="<?= htmlspecialchars($acto['Id_TareaBase']) ?>">
                    <input type="hidden" name="empresa"  value="<?= htmlspecialchars($acto['Empresa']) ?>">
                    <input type="hidden" name="id_lugar" value="<?= htmlspecialchars($acto['Id_Lugar']) ?>">
                    <div class="campo-grupo">
                        <textarea name="act_texto" class="campo-textarea acto-admin__textarea"
                                  placeholder="Ej: Ceremonia de despedida. Se ruega puntualidad."><?= htmlspecialchars($acto['Act_Texto'] ?? '') ?></textarea>
                    </div>
                    <div class="acto-admin__footer">
                        <button type="submit" class="acto-admin__btn-guardar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <?= $lang['guardar'] ?>
                        </button>
                        <?php if (!empty($acto['Act_Texto'])): ?>
                        <button type="button" class="acto-admin__btn-guardar acto-admin__btn-borrar-obs"
                                onclick="this.closest('form').querySelector('textarea').value='';this.closest('form').submit();">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            <?= $lang['eliminar'] ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php
            $idTarea  = $acto['Id_TareaBase'];
            $detalle  = $asistenciaDetalle[$idTarea] ?? ['si' => [], 'no' => []];
            $cntSi    = count($detalle['si']);
            $cntNo    = count($detalle['no']);
            $uid      = 'asi-' . $actoIdx++;
            ?>
            <div class="acto-asistentes">
                <div class="acto-asistentes__btns">
                    <button type="button"
                            class="acto-asist-btn acto-asist-btn--si"
                            onclick="toggleAsist('<?= $uid ?>-si')"
                            <?= $cntSi === 0 ? 'disabled' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= $cntSi ?>
                    </button>
                    <button type="button"
                            class="acto-asist-btn acto-asist-btn--no"
                            onclick="toggleAsist('<?= $uid ?>-no')"
                            <?= $cntNo === 0 ? 'disabled' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        <?= $cntNo ?>
                    </button>
                </div>
                <?php if ($cntSi > 0): ?>
                <div id="<?= $uid ?>-si" class="acto-asist-lista" style="display:none">
                    <?php foreach ($detalle['si'] as $nombre): ?>
                    <span class="acto-asist-nombre"><?= htmlspecialchars($nombre) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($cntNo > 0): ?>
                <div id="<?= $uid ?>-no" class="acto-asist-lista" style="display:none">
                    <?php foreach ($detalle['no'] as $nombre): ?>
                    <span class="acto-asist-nombre"><?= htmlspecialchars($nombre) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div><!-- /#actosWrap -->

    <!-- ══ TABLÓN DE MENSAJES ══ -->
    <div class="completar-header" style="margin-top:1.25rem">
        <div class="completar-header__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div style="flex:1">
            <h2 class="completar-header__titulo"><?= $lang['completar_tablon_comunicados'] ?></h2>
            <?php if ($nombreFallecido): ?>
                <p class="completar-header__subtitulo"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
            <?php endif; ?>
        </div>
        <button type="button" class="completar-header__ojo" onclick="toggleTablon()" title="Mostrar / ocultar">
            <svg id="iconoOjoTablon" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= $tablonAbierto ? '' : 'style="display:none"' ?>>
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="iconoOjoTachadoTablon" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= $tablonAbierto ? 'style="display:none"' : '' ?>>
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        </button>
    </div>

    <div id="tablonWrap" <?= $tablonAbierto ? '' : 'style="display:none"' ?>>

        <?php $muroActivo = (bool)($huella['AutorizarMuro_SN'] ?? false); ?>
        <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>" style="padding:0 0.9rem 0.75rem">
            <input type="hidden" name="accion_muro" value="<?= $muroActivo ? 'desactivar' : 'activar' ?>">
            <button type="submit" class="muro-toggle-btn <?= $muroActivo ? 'muro-toggle-btn--on' : 'muro-toggle-btn--off' ?>">
                <?php if ($muroActivo): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <?= $lang['muro_desactivar'] ?>
                <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                <?= $lang['muro_activar'] ?>
                <?php endif; ?>
                <span class="muro-toggle-btn__badge"><?= $muroActivo ? 'ON' : 'OFF' ?></span>
            </button>
        </form>

        <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>">
            <input type="hidden" name="accion_aviso" value="publicar">
            <div class="card aviso-panel">
                <div class="aviso-panel__header">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><?= $lang['aviso_titulo'] ?></span>
                    <?php if ($avisoActual): ?>
                    <span class="aviso-panel__badge"><?= $lang['aviso_badge_publicado'] ?></span>
                    <?php endif; ?>
                </div>
                <textarea name="aviso_texto" class="aviso-panel__textarea"
                          placeholder="<?= $lang['aviso_placeholder'] ?>"><?= htmlspecialchars($avisoActual) ?></textarea>
                <div class="aviso-panel__botones">
                    <button type="submit" class="aviso-panel__btn aviso-panel__btn--publicar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= $lang['aviso_publicar'] ?>
                    </button>
                    <?php if ($avisoActual): ?>
                    <button type="submit" form="formRetirarAviso" class="aviso-panel__btn aviso-panel__btn--retirar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        <?= $lang['aviso_retirar'] ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>" id="formRetirarAviso">
            <input type="hidden" name="accion_aviso" value="retirar">
        </form>

        <?php foreach ($mensajesTablon as $msg): ?>
        <div class="card tablon-msg-card">
            <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>">
                <input type="hidden" name="accion_msg"  value="guardar">
                <input type="hidden" name="id_mensaje"  value="<?= (int)$msg['Id_Mensaje'] ?>">
                <div class="campo-grupo">
                    <label class="campo-label"><?= $lang['completar_msg_titulo'] ?></label>
                    <input type="text" name="msg_titulo" class="campo-input"
                           placeholder="<?= htmlspecialchars($lang['completar_msg_titulo_ph']) ?>"
                           maxlength="150"
                           value="<?= htmlspecialchars($msg['Msg_Titulo']) ?>">
                </div>
                <div class="campo-grupo">
                    <label class="campo-label"><?= $lang['completar_msg_texto'] ?></label>
                    <textarea name="msg_texto" class="campo-textarea tablon-msg-card__textarea"
                              placeholder="<?= htmlspecialchars($lang['completar_msg_texto_ph']) ?>"><?= htmlspecialchars($msg['Msg_Texto']) ?></textarea>
                </div>
                <div class="tablon-msg-card__footer">
                    <button type="button" class="tablon-msg-card__btn-eliminar"
                            onclick="abrirModalEliminar(<?= (int)$msg['Id_Mensaje'] ?>)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        <?= $lang['eliminar'] ?>
                    </button>
                    <button type="submit" class="acto-admin__btn-guardar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= $lang['guardar'] ?>
                    </button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>

        <!-- Botones añadir / generar con IA -->
        <div class="tablon-msg-card__acciones-pie">
            <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>" style="flex:1">
                <input type="hidden" name="accion_msg" value="agregar">
                <button type="submit" class="tablon-msg-card__btn-agregar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?= $lang['completar_agregar_comunicado'] ?>
                </button>
            </form>
            <form method="POST" action="<?= urlHuella($linkActual, 'completar') ?>" style="flex:1"
                  onsubmit="return iniciarGenerarAgradecimiento(this)">
                <input type="hidden" name="accion_msg" value="generar_agradecimiento">
                <button type="submit" class="tablon-msg-card__btn-ia">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8 5.8 21.3l2.4-7.4L2 9.4h7.6z"/></svg>
                    <span class="btn-ia-texto"><?= $lang['completar_generar_agradecimiento'] ?></span>
                </button>
            </form>
        </div>

    </div><!-- /#tablonWrap -->

    <!-- ══ GESTIÓN DE USUARIOS ══ -->
    <div class="completar-header" style="margin-top:1.25rem">
        <div class="completar-header__icono">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div style="flex:1">
            <h2 class="completar-header__titulo"><?= $lang['completar_gestion_usuarios'] ?></h2>
            <p class="completar-header__subtitulo"><?= htmlspecialchars(mb_strtoupper($nombreFallecido, 'UTF-8')) ?></p>
        </div>
        <button type="button" class="completar-header__ojo" onclick="toggleUsuarios()" title="Mostrar / ocultar">
            <svg id="iconoOjoUsuarios" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="iconoOjoTachadoUsuarios" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        </button>
    </div>

    <div id="usuariosWrap" style="display:none">

        <!-- Buscador / filtro -->
        <div class="bloque" style="margin-top:.75rem">
            <div class="buscar-wrap-u">
                <div class="buscar-wrap-u__campo">
                    <input type="text" id="js-buscar-input-u" placeholder="<?= htmlspecialchars($lang['gu_filtrar_placeholder']) ?>">
                    <button id="js-buscar-clear-u" class="buscar-clear-u" title="<?= htmlspecialchars($lang['gu_limpiar_filtro']) ?>" tabindex="-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <button id="js-buscar-btn-u"><?= $lang['gu_buscar'] ?></button>
            </div>
        </div>

        <!-- Lista de usuarios -->
        <div class="bloque">
            <p class="bloque__label" id="js-contador-label-u"><?= $lang['gu_usuarios_en_huella'] ?> (<?= count($usuariosHuella) ?>)</p>
            <div id="js-usuarios-lista-u">
            <?php if (empty($usuariosHuella)): ?>
            <div class="lista-vacia-u">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                No hay usuarios vinculados a esta huella.
            </div>
            <?php else: ?>
            <?php foreach ($usuariosHuella as $u): ?>
            <?php
                $inicial  = mb_strtoupper(mb_substr($u['User_Nombre_Apellidos'] ?? '?', 0, 1, 'UTF-8'), 'UTF-8');
                $esYo     = ((int)$u['Id'] === $idUserActual);
                $rolActual = $u['Id_Rol'];
                $esAdm    = in_array($rolActual, ['ADM','OWN'], true);
                $baneado  = !empty($u['User_Baneado']);
                $label    = $rolesLabel[$rolActual] ?? $rolActual;
                $contacto = $u['User_Identificador'] ?? '';
            ?>
            <div class="usuario-card <?= $baneado ? 'usuario-card--baneado' : '' ?>" data-user="<?= (int)$u['Id'] ?>" data-rol="<?= htmlspecialchars($rolActual) ?>" data-baneado="<?= $baneado ? '1' : '0' ?>" data-buscar="<?= htmlspecialchars(mb_strtolower(($u['User_Nombre_Apellidos'] ?? '') . ' ' . $contacto, 'UTF-8')) ?>">
                <div class="usuario-card__avatar"><?= htmlspecialchars($inicial) ?></div>
                <div class="usuario-card__info">
                    <div class="usuario-card__nombre">
                        <?= htmlspecialchars($u['User_Nombre_Apellidos'] ?? 'Sin nombre') ?>
                        <?php if ($esYo): ?><span style="font-size:.7rem;font-weight:400;color:var(--color-texto-suave)"> (tú)</span><?php endif; ?>
                    </div>
                    <?php if ($contacto): ?>
                    <div class="usuario-card__contacto"><?= htmlspecialchars($contacto) ?></div>
                    <?php endif; ?>
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
                    <button class="btn-banear-u js-btn-banear-u" title="Banear usuario">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                    </button>
                    <?php else: ?>
                    <button class="btn-desbanear-u js-btn-desbanear-u" title="Desbanear usuario">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                    </button>
                    <?php endif; ?>
                    <button class="btn-eliminar-u js-btn-eliminar-u" title="Eliminar de la huella">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <p class="sin-resultados-filtro-u" id="js-sin-resultados-u">Sin resultados para este filtro.</p>
            </div><!-- #js-usuarios-lista-u -->
        </div>

    </div><!-- #usuariosWrap -->

    <div class="toast-comp" id="toast-comp"></div>

    <?php endif; ?>
    <div style="height: 5rem;"></div>
</main>

<!-- NAVEGACIÓN INFERIOR STICKY -->
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
    <a href="<?= urlHuella($linkActual ?? '', 'muro') ?>" class="nav-bottom__item nav-bottom__item--mensajes">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
        <?= $lang['muro'] ?>
    </a>
    <?php endif; ?>
    <a href="<?= urlHuella($linkActual ?? '', 'completar') ?>" class="nav-bottom__item active">
        <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
        <?= $lang['completar'] ?>
    </a>
    <?php // Solo llega aquí si es admin — la guarda del acceso está al inicio del archivo ?>
</nav>

<!-- ── Modal: confirmación genérica (ban / eliminar usuario / etc.) ── -->
<div id="modalConfirm" style="display:none;position:fixed;inset:0;z-index:950;align-items:center;justify-content:center;padding:1.25rem;opacity:0;transition:opacity .18s">
    <div id="modalConfirmOverlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)"></div>
    <div style="position:relative;z-index:1;background:var(--color-fondo-card);border:1px solid var(--color-borde);border-radius:18px;padding:1.5rem 1.5rem 1.25rem;max-width:320px;width:100%;box-shadow:0 24px 48px rgba(0,0,0,0.22)">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.85rem">
            <div id="modalConfirmIconWrap" style="width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0"></div>
            <h3 id="modalConfirmTitulo" style="font-family:var(--fuente-titulo);font-size:1rem;font-weight:700;color:var(--color-texto);margin:0;line-height:1.3"></h3>
        </div>
        <p id="modalConfirmDesc" style="font-size:0.85rem;color:var(--color-texto-suave);margin:0 0 1.3rem;line-height:1.6"></p>
        <div style="display:flex;gap:0.6rem">
            <button id="modalConfirmCancelar" style="-webkit-appearance:none;appearance:none;flex:1;padding:0.65rem;border-radius:10px;border:1px solid var(--color-borde);background:var(--color-fondo);color:var(--color-texto-suave);font-size:0.88rem;font-weight:600;font-family:var(--fuente-cuerpo);cursor:pointer;transition:background 0.15s">Cancelar</button>
            <button id="modalConfirmOk" style="-webkit-appearance:none;appearance:none;flex:1;padding:0.65rem;border-radius:10px;border:none;font-size:0.88rem;font-weight:600;font-family:var(--fuente-cuerpo);cursor:pointer;transition:opacity 0.15s"></button>
        </div>
    </div>
</div>

<!-- ── Modal: confirmar eliminación de mensaje ── -->
<div id="modalEliminar" style="display:none;position:fixed;inset:0;z-index:900;align-items:center;justify-content:center;padding:1.25rem">
    <div id="modalEliminarOverlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)"></div>
    <div style="position:relative;z-index:1;background:var(--color-fondo-card);border:1px solid var(--color-borde);border-radius:18px;padding:1.5rem 1.5rem 1.25rem;max-width:310px;width:100%;box-shadow:0 24px 48px rgba(0,0,0,0.18)">
        <div style="display:flex;align-items:center;gap:0.7rem;margin-bottom:0.9rem">
            <div style="width:38px;height:38px;border-radius:11px;background:#fee2e2;border:1px solid #fca5a5;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#dc2626">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </div>
            <h3 style="font-family:var(--fuente-titulo);font-size:1rem;font-weight:700;color:var(--color-texto);margin:0;line-height:1.3"><?= $lang['completar_eliminar_comunicado'] ?></h3>
        </div>
        <p style="font-size:0.86rem;color:var(--color-texto-suave);margin:0 0 1.25rem;line-height:1.55"><?= htmlspecialchars($lang['completar_eliminar_comunicado_confirm']) ?> <?= htmlspecialchars($lang['confirmar_eliminar_desc']) ?></p>
        <div style="display:flex;gap:0.6rem">
            <button id="modalEliminarCancelar" style="-webkit-appearance:none;appearance:none;flex:1;padding:0.65rem;border-radius:10px;border:1px solid var(--color-borde);background:var(--color-fondo);color:var(--color-texto-suave);font-size:0.88rem;font-weight:600;font-family:var(--fuente-cuerpo);cursor:pointer;transition:background 0.15s"><?= $lang['cancelar'] ?></button>
            <button id="modalEliminarConfirmar" style="-webkit-appearance:none;appearance:none;flex:1;padding:0.65rem;border-radius:10px;border:none;background:#dc2626;color:white;font-size:0.88rem;font-weight:600;font-family:var(--fuente-cuerpo);cursor:pointer;transition:opacity 0.15s"><?= $lang['eliminar'] ?></button>
        </div>
    </div>
</div>

<script>
var _formVisible  = false;
var _actosVisible = <?= $avisoActo ? 'true' : 'false' ?>;

var LANG_IA = {
    confirm:   <?= json_encode($lang['completar_generar_confirm'] ?? '¿Continuar?') ?>,
    generando: <?= json_encode($lang['completar_generando']      ?? 'Generando…') ?>,
    titulo:    <?= json_encode($lang['completar_generar_titulo']  ?? 'Generar agradecimiento con IA') ?>,
    ok:        <?= json_encode($lang['completar_generar_ok']      ?? '✨ Generar') ?>,
    cancelar:  <?= json_encode($lang['cancelar']                  ?? 'Cancelar') ?>
};
var LANG_GU = {
    buscar:  <?= json_encode($lang['gu_buscar']  ?? 'Buscar') ?>,
    limpiar: <?= json_encode($lang['gu_limpiar'] ?? 'Limpiar') ?>
};

// ── Modal de confirmación genérico ────────────────────────────────────────
(function () {
    var modal    = document.getElementById('modalConfirm');
    var overlay  = document.getElementById('modalConfirmOverlay');
    var iconWrap = document.getElementById('modalConfirmIconWrap');
    var titulo   = document.getElementById('modalConfirmTitulo');
    var desc     = document.getElementById('modalConfirmDesc');
    var btnOk    = document.getElementById('modalConfirmOk');
    var btnCan   = document.getElementById('modalConfirmCancelar');
    var _cb      = null;

    window.abrirConfirm = function (opts) {
        // opts: { titulo, desc, labelOk, tipo ('danger'|'warning'|'success'), cb }
        var tipo = opts.tipo || 'danger';
        var paletas = {
            danger:  { bg:'#fee2e2', border:'#fca5a5', icon:'#dc2626', btn:'#dc2626' },
            warning: { bg:'#fff7ed', border:'#fdba74', icon:'#ea580c', btn:'#ea580c' },
            success: { bg:'#dcfce7', border:'#86efac', icon:'#16a34a', btn:'#16a34a' },
            ia:      { bg:'#f5f0ff', border:'#d8b4fe', icon:'#7c3aed', btn:'linear-gradient(135deg,#7c3aed 0%,#c026d3 100%)' },
        };
        var colores = paletas[tipo] || paletas.danger;

        var iconSvg = {
            danger:  '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
            warning: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            success: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>',
            ia:      '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.7L18.5 9.6l-4.6 1.9L12 16l-1.9-4.5L5.5 9.6l4.6-1.9z"/><path d="M19 14l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/><path d="M5 16l.6 1.4 1.4.6-1.4.6L5 20l-.6-1.4L3 18l1.4-.6z"/></svg>',
        }[tipo];

        iconWrap.style.background   = colores.bg;
        iconWrap.style.border       = '1px solid ' + colores.border;
        iconWrap.style.color        = colores.icon;
        iconWrap.innerHTML          = iconSvg;
        titulo.textContent          = opts.titulo  || '';
        desc.textContent            = opts.desc    || '';
        btnOk.textContent           = opts.labelOk || 'Confirmar';
        btnOk.style.background      = colores.btn;
        btnOk.style.color           = '#fff';
        btnOk.style.boxShadow       = tipo === 'ia' ? '0 4px 14px rgba(124,58,237,.35)' : '';
        _cb = opts.cb || null;

        modal.style.display = 'flex';
        requestAnimationFrame(function () { modal.style.opacity = '1'; });
    };

    function cerrar() {
        modal.style.opacity = '0';
        setTimeout(function () { modal.style.display = 'none'; }, 180);
        _cb = null;
    }

    btnOk.addEventListener('click', function () {
        var cb = _cb;
        cerrar();
        if (cb) cb();
    });
    btnCan.addEventListener('click', cerrar);
    overlay.addEventListener('click', cerrar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') cerrar();
    });
})();

function toggleObs(header) {
    var body = header.nextElementSibling;
    var chevron = header.querySelector('.acto-admin__obs-chevron');
    var open = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    chevron.style.transform = open ? '' : 'rotate(180deg)';
}

function toggleAsist(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var opening = el.style.display === 'none';
    // Cierra el hermano opuesto (si/no) del mismo evento
    var sibling = id.endsWith('-si')
        ? document.getElementById(id.replace(/-si$/, '-no'))
        : document.getElementById(id.replace(/-no$/, '-si'));
    if (sibling) sibling.style.display = 'none';
    el.style.display = opening ? '' : 'none';
}

function showToast(msg) {
    var t = document.getElementById('toast-comp');
    t.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' + msg;
    t.classList.add('show');
    setTimeout(function () { t.classList.remove('show'); }, 2600);
}
<?php if ($toastMsg): ?>
document.addEventListener('DOMContentLoaded', function () { showToast(<?= json_encode($toastMsg) ?>); });
<?php endif; ?>

function toggleFormulario() {
    _formVisible = !_formVisible;
    document.getElementById('formHistoriaWrap').style.display = _formVisible ? '' : 'none';
    document.getElementById('iconoOjo').style.display        = _formVisible ? '' : 'none';
    document.getElementById('iconoOjoTachado').style.display = _formVisible ? 'none' : '';
}

function toggleTablon() {
    var wrap    = document.getElementById('tablonWrap');
    var ojoAb   = document.getElementById('iconoOjoTablon');
    var ojoCer  = document.getElementById('iconoOjoTachadoTablon');
    var visible = wrap.style.display !== 'none';
    wrap.style.display = visible ? 'none' : '';
    ojoAb.style.display  = visible ? 'none' : '';
    ojoCer.style.display = visible ? '' : 'none';
}

function iniciarGenerarAgradecimiento(form) {
    abrirConfirm({
        tipo:    'ia',
        titulo:  LANG_IA.titulo,
        desc:    LANG_IA.confirm,
        labelOk: LANG_IA.ok,
        cb: function () {
            var btn = form.querySelector('button[type=submit]');
            if (btn) {
                btn.disabled = true;
                var txt = btn.querySelector('.btn-ia-texto');
                if (txt) txt.textContent = LANG_IA.generando;
            }
            form.submit();
        }
    });
    return false; // siempre evitamos el submit nativo; lo dispara el cb
}

function toggleActos() {
    _actosVisible = !_actosVisible;
    document.getElementById('actosWrap').style.display              = _actosVisible ? '' : 'none';
    document.getElementById('iconoOjoActos').style.display          = _actosVisible ? '' : 'none';
    document.getElementById('iconoOjoTachadoActos').style.display   = _actosVisible ? 'none' : '';
}

// Vista previa de foto antes de subir
document.getElementById('inputFoto').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    const img    = document.getElementById('fotoImg');
    const icono  = document.getElementById('fotoIcono');
    img.src = url;
    img.style.display = 'block';
    if (icono) icono.style.display = 'none';
});

// Contador de caracteres
function actualizarContador(el, spanId, max) {
    const len = el.value.length;
    document.getElementById(spanId).textContent = len;
    if (len > max) el.value = el.value.substring(0, max);
}

// ── Gestión de usuarios ──────────────────────────────────────────────────────
var _usuariosVisible = false;
function toggleUsuarios() {
    _usuariosVisible = !_usuariosVisible;
    document.getElementById('usuariosWrap').style.display            = _usuariosVisible ? '' : 'none';
    document.getElementById('iconoOjoUsuarios').style.display        = _usuariosVisible ? '' : 'none';
    document.getElementById('iconoOjoTachadoUsuarios').style.display = _usuariosVisible ? 'none' : '';
}

(function () {
    var rolesLabel = { OWN:'Propietario', ADM:'Administrador', COL:'Colaborador', GFC:'Cliente', INV:'Invitado', SOL:'Solicitante' };

    function toastU(msg, err) {
        var t = document.getElementById('toast-comp');
        t.style.background = err ? '#991b1b' : '#166534';
        t.innerHTML = (err ? '' : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>') + msg;
        t.classList.add('show');
        setTimeout(function(){ t.classList.remove('show'); }, 2600);
    }

    function apiU(data, cb) {
        fetch('/huella/api/admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: Object.entries(data).map(function(p){ return encodeURIComponent(p[0])+'='+encodeURIComponent(p[1]); }).join('&')
        }).then(function(r){ return r.json(); }).then(cb).catch(function(){ toastU('Error de red', true); });
    }

    function escU(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    var SVG_DEL_U  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>';
    var SVG_BAN_U  = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';
    var SVG_UNBAN_U = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>';

    // Acciones sobre cards de usuario (delegación de eventos)
    document.getElementById('js-usuarios-lista-u').addEventListener('click', function(e) {
        var card = e.target.closest('.usuario-card');
        if (!card) return;
        var uid = card.dataset.user;

        if (e.target.closest('.js-btn-promover')) {
            apiU({ action:'set_rol', id_user:uid, rol:'ADM' }, function(r) {
                if (r.ok) { actualizarCardU(card, 'ADM'); toastU('Ascendido a Administrador'); }
                else toastU(r.error||'Error', true);
            });
        }
        if (e.target.closest('.js-btn-degradar')) {
            apiU({ action:'set_rol', id_user:uid, rol:'COL' }, function(r) {
                if (r.ok) { actualizarCardU(card, 'COL'); toastU('Rol cambiado a Colaborador'); }
                else toastU(r.error||'Error', true);
            });
        }
        if (e.target.closest('.js-btn-eliminar-u')) {
            var nombreU = card.querySelector('.usuario-card__nombre') ? card.querySelector('.usuario-card__nombre').textContent.trim() : 'este usuario';
            abrirConfirm({
                tipo:    'warning',
                titulo:  'Eliminar de la huella',
                desc:    '¿Eliminar a ' + nombreU + ' de esta huella? Perderá el acceso pero su cuenta no se borrará.',
                labelOk: 'Eliminar',
                cb: function () {
                    apiU({ action:'eliminar', id_user:uid }, function(r) {
                        if (r.ok) {
                            card.style.transition = 'opacity .3s';
                            card.style.opacity = '0';
                            setTimeout(function(){ card.remove(); actualizarContadorU(-1); }, 300);
                            toastU('Usuario eliminado');
                        } else toastU(r.error||'Error', true);
                    });
                }
            });
        }
        if (e.target.closest('.js-btn-banear-u')) {
            var nombreU = card.querySelector('.usuario-card__nombre') ? card.querySelector('.usuario-card__nombre').textContent.trim() : 'este usuario';
            abrirConfirm({
                tipo:    'danger',
                titulo:  'Banear usuario',
                desc:    '¿Banear a ' + nombreU + '? No podrá iniciar sesión ni crear una cuenta nueva desde su dispositivo.',
                labelOk: 'Banear',
                cb: function () {
                    apiU({ action:'banear', id_user:uid }, function(r) {
                        if (r.ok) { actualizarBanU(card, true); toastU('Usuario baneado'); }
                        else toastU(r.error||'Error', true);
                    });
                }
            });
        }
        if (e.target.closest('.js-btn-desbanear-u')) {
            var nombreU = card.querySelector('.usuario-card__nombre') ? card.querySelector('.usuario-card__nombre').textContent.trim() : 'este usuario';
            abrirConfirm({
                tipo:    'success',
                titulo:  'Desbanear usuario',
                desc:    '¿Levantar el ban de ' + nombreU + '? Podrá volver a iniciar sesión y registrarse desde su dispositivo.',
                labelOk: 'Desbanear',
                cb: function () {
                    apiU({ action:'desbanear', id_user:uid }, function(r) {
                        if (r.ok) { actualizarBanU(card, false); toastU('Usuario desbaneado'); }
                        else toastU(r.error||'Error', true);
                    });
                }
            });
        }
    });

    function actualizarCardU(card, nuevoRol) {
        card.dataset.rol = nuevoRol;
        var chip = card.querySelector('.js-rol-chip');
        chip.textContent = rolesLabel[nuevoRol] || nuevoRol;
        chip.className = 'rol-chip rol-chip--' + nuevoRol + ' js-rol-chip';
        var btnP = card.querySelector('.js-btn-promover');
        var btnD = card.querySelector('.js-btn-degradar');
        var ref  = card.querySelector('.js-btn-eliminar-u');
        var acc  = card.querySelector('.usuario-card__acciones');
        if (btnP) btnP.remove();
        if (btnD) btnD.remove();
        var nb = document.createElement('button');
        var esAdm = (nuevoRol === 'ADM' || nuevoRol === 'OWN');
        nb.className = esAdm ? 'btn-rol btn-rol--degradar js-btn-degradar' : 'btn-rol btn-rol--promover js-btn-promover';
        nb.textContent = esAdm ? 'Quitar ADM' : 'Hacer ADM';
        acc.insertBefore(nb, ref);
    }

    function actualizarBanU(card, baneado) {
        card.dataset.baneado = baneado ? '1' : '0';
        card.classList.toggle('usuario-card--baneado', baneado);
        var btnBan   = card.querySelector('.js-btn-banear-u');
        var btnUnban = card.querySelector('.js-btn-desbanear-u');
        if (baneado) {
            if (btnBan) {
                btnBan.className = 'btn-desbanear-u js-btn-desbanear-u';
                btnBan.title = 'Desbanear usuario';
                btnBan.innerHTML = SVG_UNBAN_U;
            }
        } else {
            if (btnUnban) {
                btnUnban.className = 'btn-banear-u js-btn-banear-u';
                btnUnban.title = 'Banear usuario';
                btnUnban.innerHTML = SVG_BAN_U;
            }
        }
    }

    function actualizarContadorU(delta) {
        var lbl = document.getElementById('js-contador-label-u');
        if (!lbl) return;
        var m = lbl.textContent.match(/\((\d+)\)/);
        if (m) lbl.textContent = lbl.textContent.replace(/\(\d+\)/, '(' + (parseInt(m[1]) + delta) + ')');
    }

    // Filtro de la lista de usuarios
    var inputBU   = document.getElementById('js-buscar-input-u');
    var btnBU     = document.getElementById('js-buscar-btn-u');
    var btnClearU = document.getElementById('js-buscar-clear-u');
    var sinResU   = document.getElementById('js-sin-resultados-u');

    function filtrarU() {
        var q = inputBU.value.trim().toLowerCase();
        var cards = document.querySelectorAll('#js-usuarios-lista-u .usuario-card');
        var visible = 0;
        cards.forEach(function(card) {
            var texto = (card.dataset.buscar || card.textContent).toLowerCase();
            var mostrar = !q || texto.indexOf(q) !== -1;
            card.style.display = mostrar ? '' : 'none';
            if (mostrar) visible++;
        });
        btnClearU.style.display = q ? 'block' : 'none';
        sinResU.style.display = (q && visible === 0) ? 'block' : 'none';
        btnBU.textContent = q ? LANG_GU.limpiar : LANG_GU.buscar;
    }

    function limpiarU() {
        inputBU.value = '';
        filtrarU();
        inputBU.focus();
    }

    btnBU.addEventListener('click', function() {
        if (inputBU.value.trim()) limpiarU(); else filtrarU();
    });
    btnClearU.addEventListener('click', limpiarU);
    inputBU.addEventListener('input', filtrarU);
    inputBU.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') filtrarU();
        if (e.key === 'Escape') limpiarU();
    });

    function insertarCardU(u) {
        var lista = document.getElementById('js-usuarios-lista-u');
        var vacia = lista.querySelector('.lista-vacia-u');
        if (vacia) vacia.remove();
        var inicial = (u.nombre||'?').charAt(0).toUpperCase();
        var d = document.createElement('div');
        d.className = 'usuario-card';
        d.dataset.user = u.id;
        d.dataset.rol    = 'COL';
        d.dataset.buscar = ((u.nombre || '') + ' ' + (u.contacto || '')).toLowerCase();
        d.innerHTML =
            '<div class="usuario-card__avatar">' + escU(inicial) + '</div>' +
            '<div class="usuario-card__info">' +
                '<div class="usuario-card__nombre">' + escU(u.nombre) + '</div>' +
                '<div class="usuario-card__contacto">' + escU(u.contacto) + '</div>' +
            '</div>' +
            '<div class="usuario-card__acciones">' +
                '<span class="rol-chip rol-chip--COL js-rol-chip">Colaborador</span>' +
                '<button class="btn-rol btn-rol--promover js-btn-promover">Hacer ADM</button>' +
                '<button class="btn-banear-u js-btn-banear-u" title="Banear usuario">' + SVG_BAN_U + '</button>' +
                '<button class="btn-eliminar-u js-btn-eliminar-u" title="Eliminar de la huella">' + SVG_DEL_U + '</button>' +
            '</div>';
        lista.appendChild(d);
    }
})();

// Auto-resize del textarea
const ta = document.getElementById('inputTexto');
if (ta) {
    ta.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
}

// ── Modal eliminar comunicado ─────────────────────────────────────────────────
var _idEliminar    = null;
var COMPLETAR_URL  = <?= json_encode(urlHuella($linkActual, 'completar')) ?>;

function abrirModalEliminar(idMensaje) {
    _idEliminar = idMensaje;
    document.getElementById('modalEliminar').style.display = 'flex';
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').style.display = 'none';
    _idEliminar = null;
}

document.getElementById('modalEliminarCancelar').addEventListener('click', cerrarModalEliminar);
document.getElementById('modalEliminarOverlay').addEventListener('click', cerrarModalEliminar);
document.getElementById('modalEliminarConfirmar').addEventListener('click', function () {
    if (_idEliminar === null) return;
    var f  = document.createElement('form');
    f.method = 'POST';
    f.action = COMPLETAR_URL;
    [['accion_msg','eliminar'],['id_mensaje', _idEliminar]].forEach(function(p) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = p[0]; i.value = p[1];
        f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') cerrarModalEliminar();
});
</script>


</body>
</html>
