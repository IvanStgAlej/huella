<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/upload.php';

function json_ok(array $data = []): never  { echo json_encode(['ok' => true,  ...$data]); exit; }
function json_fail(string $msg): never     { echo json_encode(['ok' => false, 'msg' => $msg]); exit; }

$action   = $_REQUEST['action']   ?? '';
$idHuella = $_REQUEST['id_huella'] ?? '';

if (!$idHuella) json_fail('Sin huella');
$huella = obtenerHuella($idHuella);
if (!$huella) json_fail('Huella no encontrada');

match($action) {
    'crear_post'    => handleCrearPost($huella),
    'editar_post'   => handleEditarPost($huella),
    'eliminar_post' => handleEliminarPost($huella),
    'like'          => handleLike($huella),
    'respuestas'    => handleRespuestas($huella),
    default         => json_fail('Acción desconocida'),
};

// ── CREAR POST / RESPUESTA ────────────────────────────────────────────────────
function handleCrearPost(array $huella): never {
    $user = usuarioActual();
    if (!$user) json_fail('Debes iniciar sesión para publicar un mensaje');

    $idHuella = $huella['Id_Huella'];
    $texto    = trim($_POST['texto']     ?? '');
    $parentId = trim($_POST['parent_id'] ?? '') ?: null;
    $autor    = $user['nombre'];
    $userId   = (string)$user['id'];
    $postLink = trim($_POST['post_link'] ?? '') ?: null;

    if (mb_strlen($texto) > 3000) json_fail('Texto demasiado largo');

    $idPost = generarIdPost($idHuella);
    $db     = getDB();

    $flagMal = contienemalsonantes($texto) ? 1 : 0;
    $stmt = $db->prepare("
        INSERT INTO h_huellapost
          (Id_Huella, Id_Post, Post_Id_user, Post_Autor, Post_Parent_Id,
           Post_Texto, Post_Link, Post_Fecha, Post_Vetado, Post_Autorizado, Post_Eliminado, Post_Flag_Malsonante)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 1, 0, ?)
    ");
    $stmt->bind_param('sssssssi', $idHuella, $idPost, $userId, $autor, $parentId, $texto, $postLink, $flagMal);
    if (!$stmt->execute()) json_fail('Error al guardar el mensaje');

    // ── Imágenes (hasta 4) ──
    if (!empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['name'] as $i => $name) {
            if (!$name || $_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $file = [
                'name'     => $_FILES['fotos']['name'][$i],
                'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                'size'     => $_FILES['fotos']['size'][$i],
                'error'    => $_FILES['fotos']['error'][$i],
                'type'     => $_FILES['fotos']['type'][$i],
            ];
            try {
                $m = subirMediaPost($file, $idHuella, $idPost, 'img');
                $s = $db->prepare("INSERT INTO h_huellapost_imagenes (Id_Huella,Id_Post,Img_Ruta_Carpeta,Img_Nombre,Img_Orden,Img_Fecha) VALUES(?,?,?,?,?,NOW())");
                $s->bind_param('ssssi', $idHuella, $idPost, $m['carpeta'], $m['nombre'], $i);
                $s->execute();
            } catch (RuntimeException) {}
        }
    }

    // ── Vídeo ──
    if (!empty($_FILES['video']['name']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        try {
            $m = subirMediaPost($_FILES['video'], $idHuella, $idPost, 'video');
            $s = $db->prepare("INSERT INTO h_huellapost_videos (Id_Huella,Id_Post,Video_Ruta_Carpeta,Video_Nombre,Video_Fecha) VALUES(?,?,?,?,NOW())");
            $s->bind_param('ssss', $idHuella, $idPost, $m['carpeta'], $m['nombre']);
            $s->execute();
        } catch (RuntimeException) {}
    }

    // ── Audio ──
    if (!empty($_FILES['audio']['name']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
        try {
            $m = subirMediaPost($_FILES['audio'], $idHuella, $idPost, 'audio');
            $s = $db->prepare("INSERT INTO h_huellapost_audios (Id_Huella,Id_Post,Audio_Ruta_Carpeta,Audio_Nombre,Audio_Fecha) VALUES(?,?,?,?,NOW())");
            $s->bind_param('ssss', $idHuella, $idPost, $m['carpeta'], $m['nombre']);
            $s->execute();
        } catch (RuntimeException) {}
    }

    // Devolver el post completo para renderizarlo en el cliente
    $post = [
        'Id_Post'          => $idPost,
        'Post_Id_user'     => $userId,
        'Post_Autor'       => $autor,
        'Post_Texto'            => filtrarMalsonantes($texto),
        'Post_Flag_Malsonante'  => $flagMal,
        'Post_Link'        => $postLink,
        'Post_Fecha'       => date('Y-m-d H:i:s'),
        'total_likes'      => 0,
        'total_respuestas' => 0,
        'imagenes'         => [],
        'videos'           => [],
        'audios'           => [],
    ];
    $h = $db->real_escape_string($idHuella);
    $p = $db->real_escape_string($idPost);
    $post['imagenes'] = $db->query("SELECT Img_Ruta_Carpeta, Img_Nombre, Img_Orden FROM h_huellapost_imagenes WHERE Id_Huella='$h' AND Id_Post='$p' ORDER BY Img_Orden")->fetch_all(MYSQLI_ASSOC);
    $post['videos']   = $db->query("SELECT Video_Ruta_Carpeta, Video_Nombre FROM h_huellapost_videos WHERE Id_Huella='$h' AND Id_Post='$p'")->fetch_all(MYSQLI_ASSOC);
    $post['audios']   = $db->query("SELECT Audio_Ruta_Carpeta, Audio_Nombre FROM h_huellapost_audios WHERE Id_Huella='$h' AND Id_Post='$p'")->fetch_all(MYSQLI_ASSOC);

    json_ok(['post' => $post, 'parent_id' => $parentId]);
}

// ── EDITAR POST ───────────────────────────────────────────────────────────────
function handleEditarPost(array $huella): never {
    $user = usuarioActual();
    if (!$user) json_fail('Debes iniciar sesión');

    $idHuella = $huella['Id_Huella'];
    $idPost   = trim($_POST['id_post'] ?? '');
    $texto    = trim($_POST['texto']   ?? '');
    if (!$idPost) json_fail('Sin id_post');
    if (mb_strlen($texto) > 3000) json_fail('Texto demasiado largo');

    $db  = getDB();
    $uid = (string)$user['id'];

    $chk = $db->prepare("SELECT Post_Id_user FROM h_huellapost WHERE Id_Huella=? AND Id_Post=? AND Post_Eliminado=0");
    $chk->bind_param('ss', $idHuella, $idPost);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row) json_fail('Mensaje no encontrado');
    if ($row['Post_Id_user'] !== $uid && !esAdminHuella($idHuella)) json_fail('Sin permiso para editar este mensaje');

    $flagMalEdit = contienemalsonantes($texto) ? 1 : 0;
    $s = $db->prepare("UPDATE h_huellapost SET Post_Texto=?, Post_Flag_Malsonante=? WHERE Id_Huella=? AND Id_Post=? AND Post_Eliminado=0");
    $s->bind_param('siss', $texto, $flagMalEdit, $idHuella, $idPost);
    if (!$s->execute()) json_fail('Error al guardar');
    json_ok(['texto' => $flagMalEdit ? filtrarMalsonantes($texto) : $texto, 'flag_malsonante' => $flagMalEdit]);
}

// ── ELIMINAR POST ─────────────────────────────────────────────────────────────
function handleEliminarPost(array $huella): never {
    $user = usuarioActual();
    if (!$user) json_fail('Debes iniciar sesión');

    $idHuella = $huella['Id_Huella'];
    $idPost   = trim($_POST['id_post'] ?? '');
    if (!$idPost) json_fail('Sin id_post');

    $db  = getDB();
    $uid = (string)$user['id'];

    $chk = $db->prepare("SELECT Post_Id_user FROM h_huellapost WHERE Id_Huella=? AND Id_Post=? AND Post_Eliminado=0");
    $chk->bind_param('ss', $idHuella, $idPost);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row) json_fail('Mensaje no encontrado');
    if ($row['Post_Id_user'] !== $uid && !esAdminHuella($idHuella)) json_fail('Sin permiso para eliminar este mensaje');

    // Eliminar también las respuestas
    foreach (["UPDATE h_huellapost SET Post_Eliminado=1 WHERE Id_Huella=? AND Post_Parent_Id=?",
              "UPDATE h_huellapost SET Post_Eliminado=1 WHERE Id_Huella=? AND Id_Post=?"] as $sql) {
        $s = $db->prepare($sql);
        $s->bind_param('ss', $idHuella, $idPost);
        $s->execute();
    }
    json_ok();
}

// ── LIKE / UNLIKE ─────────────────────────────────────────────────────────────
function handleLike(array $huella): never {
    if (!usuarioActual()) json_fail('Debes iniciar sesión para dar me gusta');
    $idHuella = $huella['Id_Huella'];
    $idPost   = trim($_POST['id_post'] ?? '');
    $token    = trim($_POST['token']   ?? '');

    if (!$idPost || !$token)  json_fail('Datos incompletos');
    if (strlen($token) > 64)  json_fail('Token inválido');

    $db = getDB();
    $st = $db->prepare("SELECT 1 FROM h_huellapost_likes WHERE Id_Huella=? AND Id_Post=? AND Like_Token=?");
    $st->bind_param('sss', $idHuella, $idPost, $token);
    $st->execute();
    $yaLiked = $st->get_result()->num_rows > 0;

    if ($yaLiked) {
        $s = $db->prepare("DELETE FROM h_huellapost_likes WHERE Id_Huella=? AND Id_Post=? AND Like_Token=?");
    } else {
        $s = $db->prepare("INSERT IGNORE INTO h_huellapost_likes (Id_Huella,Id_Post,Like_Token) VALUES(?,?,?)");
    }
    $s->bind_param('sss', $idHuella, $idPost, $token);
    $s->execute();

    $cnt = $db->prepare("SELECT COUNT(*) FROM h_huellapost_likes WHERE Id_Huella=? AND Id_Post=?");
    $cnt->bind_param('ss', $idHuella, $idPost);
    $cnt->execute();
    $count = (int)$cnt->get_result()->fetch_row()[0];

    json_ok(['liked' => !$yaLiked, 'count' => $count]);
}

// ── CARGAR RESPUESTAS ─────────────────────────────────────────────────────────
function handleRespuestas(array $huella): never {
    $idPost = trim($_GET['id_post'] ?? '');
    if (!$idPost) json_fail('Sin id_post');
    json_ok(['replies' => obtenerRespuestas($huella['Id_Huella'], $idPost)]);
}
