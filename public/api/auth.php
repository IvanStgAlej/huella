<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/functions.php';

function jsonOk(array $data = []): never { echo json_encode(['ok' => true,  ...$data]); exit; }
function jsonFail(string $msg): never   { echo json_encode(['ok' => false, 'msg' => $msg]); exit; }

$action = trim($_POST['action'] ?? '');

match($action) {
    'check'    => handleCheck(),
    'login'    => handleLogin(),
    'register' => handleRegister(),
    'idioma'   => handleIdioma(),
    default    => jsonFail('Acción desconocida'),
};

function esEmail(string $id): bool { return str_contains($id, '@'); }

// Comprueba si el identificador existe en una huella concreta
function handleCheck(): never {
    $id       = trim($_POST['id']        ?? '');
    $idHuella = trim($_POST['id_huella'] ?? '');
    if (!$id)       jsonFail('Introduce tu teléfono o correo electrónico');
    if (!$idHuella) jsonFail('Contexto de huella no especificado');

    $db = getDB();
    $st = $db->prepare("SELECT 1 FROM h_huellausersroles WHERE Id_Huella = ? AND User_Identificador = ?");
    $st->bind_param('ss', $idHuella, $id);
    $st->execute();
    jsonOk(['exists' => $st->get_result()->num_rows > 0]);
}

function handleLogin(): never {
    $id       = trim($_POST['id']        ?? '');
    $pass     = $_POST['password']       ?? '';
    $idHuella = trim($_POST['id_huella'] ?? '');

    if (!$id)       jsonFail('Introduce tu teléfono o correo electrónico');
    if (!$pass)     jsonFail('Contraseña requerida');
    if (!$idHuella) jsonFail('Contexto de huella no especificado');

    $db = getDB();
    $st = $db->prepare("
        SELECT Id, User_Nombre_Apellidos, User_Password, User_Idioma, User_Baneado, Id_Rol
        FROM h_huellausersroles
        WHERE Id_Huella = ? AND User_Identificador = ?
        LIMIT 1
    ");
    $st->bind_param('ss', $idHuella, $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row)                                   jsonFail('Usuario no encontrado en esta huella');
    if (!password_verify($pass, $row['User_Password'] ?? '')) jsonFail('Contraseña incorrecta');
    if (!empty($row['User_Baneado']))             jsonFail('Tu cuenta ha sido bloqueada. Contacta con el administrador.');

    $idioma = $row['User_Idioma'] ?: 'CAS';

    $_SESSION['user'] = [
        'id'        => $row['Id'],
        'nombre'    => $row['User_Nombre_Apellidos'],
        'idioma'    => $idioma,
        'id_huella' => $idHuella,
        'rol'       => $row['Id_Rol'],
    ];
    jsonOk(['nombre' => $row['User_Nombre_Apellidos']]);
}

function handleRegister(): never {
    $id        = trim($_POST['id']        ?? '');
    $nombre    = trim($_POST['nombre']    ?? '');
    $pass      = $_POST['password']       ?? '';
    $idHuella  = trim($_POST['id_huella'] ?? '');
    $deviceId  = trim($_POST['device_id'] ?? '');

    if (!$id)                    jsonFail('Introduce tu teléfono o correo electrónico');
    if (!$nombre)                jsonFail('Nombre requerido');
    if (mb_strlen($nombre) > 50) jsonFail('Nombre demasiado largo');
    if (!$pass)                  jsonFail('Contraseña requerida');
    if (mb_strlen($pass) < 6)    jsonFail('La contraseña debe tener al menos 6 caracteres');
    if (!$idHuella)              jsonFail('Contexto de huella no especificado');

    $db = getDB();

    // Comprobar si el dispositivo está baneado
    if ($deviceId) {
        $stBan = $db->prepare("SELECT 1 FROM h_dispositivos_baneados WHERE device_id = ?");
        $stBan->bind_param('s', $deviceId);
        $stBan->execute();
        if ($stBan->get_result()->num_rows > 0) jsonFail('No es posible crear una cuenta desde este dispositivo.');
    }

    // Comprobar que la huella existe
    $stH = $db->prepare("SELECT Id_Idioma FROM h_huella WHERE Id_Huella = ? AND Ocultar = 0 LIMIT 1");
    $stH->bind_param('s', $idHuella);
    $stH->execute();
    $rowH = $stH->get_result()->fetch_assoc();
    if (!$rowH) jsonFail('Huella no encontrada');
    $idioma = $rowH['Id_Idioma'] ?? 'CAS';

    // Comprobar unicidad del identificador en esta huella
    $stCheck = $db->prepare("SELECT 1 FROM h_huellausersroles WHERE Id_Huella = ? AND User_Identificador = ?");
    $stCheck->bind_param('ss', $idHuella, $id);
    $stCheck->execute();
    if ($stCheck->get_result()->num_rows > 0) jsonFail('Este teléfono o correo ya está registrado en esta huella');

    $hash     = password_hash($pass, PASSWORD_BCRYPT);
    $dId      = $deviceId ?: null;
    $rolInicial = 'COL';

    $st = $db->prepare("
        INSERT INTO h_huellausersroles
            (Id_Huella, User_Identificador, User_Nombre_Apellidos, User_Password, Id_Rol, User_Idioma, User_Device_Id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $st->bind_param('sssssss', $idHuella, $id, $nombre, $hash, $rolInicial, $idioma, $dId);
    if (!$st->execute()) jsonFail('Error al crear el usuario');
    $newId = $db->insert_id;

    $_SESSION['user'] = [
        'id'        => $newId,
        'nombre'    => $nombre,
        'idioma'    => $idioma,
        'id_huella' => $idHuella,
        'rol'       => $rolInicial,
    ];
    jsonOk(['nombre' => $nombre]);
}

function handleIdioma(): never {
    if (!sesionActiva()) jsonFail('Sin sesión');

    $idiomas = ['CAS' => 'Castellano', 'CAT' => 'Català', 'ENG' => 'English', 'EUS' => 'Euskera'];
    $codigo  = strtoupper(trim($_POST['idioma'] ?? ''));
    if (!isset($idiomas[$codigo])) jsonFail('Idioma no válido');

    $uid = (int)$_SESSION['user']['id'];
    $db  = getDB();
    $st  = $db->prepare("UPDATE h_huellausersroles SET User_Idioma = ? WHERE Id = ?");
    $st->bind_param('si', $codigo, $uid);
    $st->execute();

    $_SESSION['user']['idioma'] = $codigo;
    jsonOk(['idioma' => $codigo, 'literal' => $idiomas[$codigo]]);
}
