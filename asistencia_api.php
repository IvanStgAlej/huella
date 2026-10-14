<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$uActual = usuarioActual();
if (!$uActual) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged']);
    exit;
}

$accion   = $_POST['action']    ?? '';
$idHuella = trim($_POST['id_huella'] ?? '');
$idTarea  = trim($_POST['id_tarea']  ?? '');
$asiste   = isset($_POST['asiste']) ? (int)$_POST['asiste'] : -1;

if ($accion !== 'votar' || !$idHuella || !$idTarea || !in_array($asiste, [0, 1], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

$idUser = (string)$uActual['id'];
$db = getDB();

$check = $db->prepare("SELECT Asiste_SN FROM h_huellaactividadesasistencia WHERE Id_Huella=? AND Id_TareaBase=? AND Id_User=?");
$check->bind_param('sss', $idHuella, $idTarea, $idUser);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

if ($row !== null) {
    $votoActual = (int)$row['Asiste_SN'];
    if ($votoActual === $asiste) {
        $del = $db->prepare("DELETE FROM h_huellaactividadesasistencia WHERE Id_Huella=? AND Id_TareaBase=? AND Id_User=?");
        $del->bind_param('sss', $idHuella, $idTarea, $idUser);
        $del->execute();
        $del->close();
        $nuevoVoto = null;
    } else {
        $upd = $db->prepare("UPDATE h_huellaactividadesasistencia SET Asiste_SN=? WHERE Id_Huella=? AND Id_TareaBase=? AND Id_User=?");
        $upd->bind_param('isss', $asiste, $idHuella, $idTarea, $idUser);
        $upd->execute();
        $upd->close();
        $nuevoVoto = $asiste;
    }
} else {
    $ins = $db->prepare("INSERT INTO h_huellaactividadesasistencia (Id_Huella, Id_TareaBase, Id_User, Asiste_SN) VALUES (?,?,?,?)");
    $ins->bind_param('sssi', $idHuella, $idTarea, $idUser, $asiste);
    $ins->execute();
    $ins->close();
    $nuevoVoto = $asiste;
}

$cnt = $db->prepare("SELECT Asiste_SN, COUNT(*) as n FROM h_huellaactividadesasistencia WHERE Id_Huella=? AND Id_TareaBase=? GROUP BY Asiste_SN");
$cnt->bind_param('ss', $idHuella, $idTarea);
$cnt->execute();
$si = 0; $no = 0;
foreach ($cnt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    if ($r['Asiste_SN']) $si = (int)$r['n'];
    else                 $no = (int)$r['n'];
}
$cnt->close();

echo json_encode(['si' => $si, 'no' => $no, 'voto' => $nuevoVoto]);
