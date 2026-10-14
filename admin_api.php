<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function ok(array $extra = []): never  { echo json_encode(['ok' => true] + $extra);  exit; }
function fail(string $msg): never      { echo json_encode(['ok' => false, 'error' => $msg]); exit; }

if (!sesionActiva()) fail('No autenticado');

$uActual   = usuarioActual();
$idYo      = (int)$uActual['id'];
$idHuella  = $uActual['id_huella'] ?? '';   // la huella de la sesión actual
$action    = $_POST['action'] ?? '';
$db        = getDB();

// El usuario es admin si su sesión dice ADM u OWN en su huella
function esAdmin(array $u): bool {
    return in_array($u['rol'] ?? '', ['ADM', 'OWN'], true);
}

// Verifica que el usuario objetivo pertenece a la huella de sesión y no es OWN
function obtenerTarget(mysqli $db, int $idTarget, string $idHuella): array {
    $st = $db->prepare("SELECT Id, Id_Rol, User_Baneado, User_Device_Id FROM h_huellausersroles WHERE Id = ? AND Id_Huella = ?");
    $st->bind_param('is', $idTarget, $idHuella);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?? [];
}

// ── SET_ROL ────────────────────────────────────────────────────────────────
if ($action === 'set_rol') {
    $idTarget = (int)($_POST['id_user'] ?? 0);
    $rol      = trim($_POST['rol'] ?? '');

    if (!$idTarget || !$rol)          fail('Parámetros incompletos');
    if (!esAdmin($uActual))           fail('Sin permisos');
    if ($idTarget === $idYo)          fail('No puedes cambiarte el rol a ti mismo');

    $rolesPermitidos = ['ADM', 'COL', 'GFC', 'INV', 'SOL'];
    if (!in_array($rol, $rolesPermitidos, true)) fail('Rol no permitido');

    $target = obtenerTarget($db, $idTarget, $idHuella);
    if (!$target)                     fail('Usuario no encontrado en esta huella');
    if ($target['Id_Rol'] === 'OWN')  fail('No se puede modificar el rol del propietario');

    $st = $db->prepare("UPDATE h_huellausersroles SET Id_Rol = ? WHERE Id = ?");
    $st->bind_param('si', $rol, $idTarget);
    $st->execute();
    ok();
}

// ── ELIMINAR ───────────────────────────────────────────────────────────────
if ($action === 'eliminar') {
    $idTarget = (int)($_POST['id_user'] ?? 0);

    if (!$idTarget)          fail('Parámetros incompletos');
    if (!esAdmin($uActual))  fail('Sin permisos');
    if ($idTarget === $idYo) fail('No puedes eliminarte a ti mismo');

    $target = obtenerTarget($db, $idTarget, $idHuella);
    if (!$target)                    fail('Usuario no encontrado');
    if ($target['Id_Rol'] === 'OWN') fail('No se puede eliminar al propietario');

    $st = $db->prepare("DELETE FROM h_huellausersroles WHERE Id = ?");
    $st->bind_param('i', $idTarget);
    $st->execute();
    ok();
}

// ── BANEAR ─────────────────────────────────────────────────────────────────
if ($action === 'banear') {
    $idTarget = (int)($_POST['id_user'] ?? 0);

    if (!$idTarget)          fail('Parámetros incompletos');
    if (!esAdmin($uActual))  fail('Sin permisos');
    if ($idTarget === $idYo) fail('No puedes banearte a ti mismo');

    $target = obtenerTarget($db, $idTarget, $idHuella);
    if (!$target)                    fail('Usuario no encontrado');
    if ($target['Id_Rol'] === 'OWN') fail('No se puede banear al propietario');

    $st = $db->prepare("UPDATE h_huellausersroles SET User_Baneado = 1 WHERE Id = ?");
    $st->bind_param('i', $idTarget);
    $st->execute();

    if (!empty($target['User_Device_Id'])) {
        $st = $db->prepare("INSERT IGNORE INTO h_dispositivos_baneados (device_id) VALUES (?)");
        $st->bind_param('s', $target['User_Device_Id']);
        $st->execute();
    }
    ok();
}

// ── DESBANEAR ──────────────────────────────────────────────────────────────
if ($action === 'desbanear') {
    $idTarget = (int)($_POST['id_user'] ?? 0);

    if (!$idTarget)         fail('Parámetros incompletos');
    if (!esAdmin($uActual)) fail('Sin permisos');

    $target = obtenerTarget($db, $idTarget, $idHuella);
    if (!$target) fail('Usuario no encontrado');

    $st = $db->prepare("UPDATE h_huellausersroles SET User_Baneado = 0 WHERE Id = ?");
    $st->bind_param('i', $idTarget);
    $st->execute();

    if (!empty($target['User_Device_Id'])) {
        $st = $db->prepare("DELETE FROM h_dispositivos_baneados WHERE device_id = ?");
        $st->bind_param('s', $target['User_Device_Id']);
        $st->execute();
    }
    ok();
}

// ── ELIMINAR_POST ──────────────────────────────────────────────────────────
if ($action === 'eliminar_post') {
    $idPost = trim($_POST['id_post'] ?? '');
    if (!$idPost)           fail('Parámetros incompletos');
    if (!esAdmin($uActual)) fail('Sin permisos');

    $st = $db->prepare("UPDATE h_huellapost SET Post_Eliminado=1 WHERE Id_Huella=? AND Id_Post=?");
    $st->bind_param('ss', $idHuella, $idPost);
    $st->execute();
    ok();
}

// ── DESCENSURAR_POST ───────────────────────────────────────────────────────
if ($action === 'descensurar_post') {
    $idPost = trim($_POST['id_post'] ?? '');
    if (!$idPost)           fail('Parámetros incompletos');
    if (!esAdmin($uActual)) fail('Sin permisos');

    $st = $db->prepare("UPDATE h_huellapost SET Post_Flag_Malsonante=0 WHERE Id_Huella=? AND Id_Post=?");
    $st->bind_param('ss', $idHuella, $idPost);
    $st->execute();
    ok();
}

fail('Acción desconocida');
