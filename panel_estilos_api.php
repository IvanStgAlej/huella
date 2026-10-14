<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/functions.php';

function ok(string $msg = ''): never  { echo json_encode(['ok' => true,  'msg' => $msg]); exit; }
function fail(string $msg): never     { echo json_encode(['ok' => false, 'msg' => $msg]); exit; }

if (!sesionActiva()) {
    http_response_code(401);
    fail('No autenticado');
}
if (!esAdminDeAlgunaHuella()) {
    http_response_code(403);
    fail('Sin permisos');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método no permitido');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) fail('Payload inválido');

$id = trim($body['id_cliente'] ?? '');
if (!$id) fail('ID de cliente obligatorio');
if (strlen($id) > 10) fail('ID demasiado largo (máx. 10 caracteres)');

$campos = [
    'Literal'             => trim($body['literal']             ?? ''),
    'nombre_producto'     => trim($body['nombre_producto']     ?? ''),
    'logo_path'           => trim($body['logo_path']           ?? ''),
    'logo_alt'            => trim($body['logo_alt']            ?? ''),
    'color_primario'      => trim($body['color_primario']      ?? ''),
    'color_secundario'    => trim($body['color_secundario']    ?? ''),
    'color_acento'        => trim($body['color_acento']        ?? ''),
    'color_fondo'         => trim($body['color_fondo']         ?? ''),
    'color_fondo_card'    => trim($body['color_fondo_card']    ?? ''),
    'color_texto'         => trim($body['color_texto']         ?? ''),
    'color_texto_suave'   => trim($body['color_texto_suave']   ?? ''),
    'color_borde'         => trim($body['color_borde']         ?? ''),
    'fuente_titulo'       => trim($body['fuente_titulo']       ?? ''),
    'fuente_cuerpo'       => trim($body['fuente_cuerpo']       ?? ''),
    'tamanio_base'        => trim($body['tamanio_base']        ?? ''),
    'max_ancho_contenido' => trim($body['max_ancho_contenido'] ?? ''),
    'radio_tarjeta'       => trim($body['radio_tarjeta']       ?? ''),
    'sombra_tarjeta'      => trim($body['sombra_tarjeta']      ?? ''),
];

// Construir INSERT ... ON DUPLICATE KEY UPDATE
$cols       = 'Id_Cliente, ' . implode(', ', array_keys($campos));
$placeholders = '?, ' . implode(', ', array_fill(0, count($campos), '?'));
$updates    = implode(', ', array_map(fn($k) => "$k = VALUES($k)", array_keys($campos)));

$sql = "INSERT INTO h_clientesconfig ($cols) VALUES ($placeholders)
        ON DUPLICATE KEY UPDATE $updates";

$db   = getDB();
$stmt = $db->prepare($sql);
if (!$stmt) fail('Error preparando la consulta: ' . $db->error);

$tipos  = 's' . str_repeat('s', count($campos));
$values = [$id, ...array_values($campos)];
$stmt->bind_param($tipos, ...$values);

if (!$stmt->execute()) fail('Error al guardar: ' . $stmt->error);

$accion = $stmt->affected_rows === 1 ? 'creado' : 'actualizado';
ok("Estilo $accion correctamente.");
