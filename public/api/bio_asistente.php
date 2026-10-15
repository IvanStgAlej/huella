<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/gemini.php';
require_once __DIR__ . '/../../includes/groq.php';

header('Content-Type: application/json; charset=utf-8');

// ── Autenticación ──────────────────────────────────────────────────────────
if (!sesionActiva()) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$action   = $_POST['action']   ?? '';
$idHuella = trim((string)($_POST['id_huella'] ?? ''));

if ($idHuella === '') {
    echo json_encode(['ok' => false, 'error' => 'Huella no especificada']);
    exit;
}

if (!esAdminHuella($idHuella)) {
    echo json_encode(['ok' => false, 'error' => 'Sin permisos de administrador para esta huella']);
    exit;
}

// ── action=generar ────────────────────────────────────────────────────────
if ($action === 'generar') {
    $idioma = trim($_POST['idioma'] ?? 'CAS');
    $r = [
        trim($_POST['r1'] ?? ''),
        trim($_POST['r2'] ?? ''),
        trim($_POST['r3'] ?? ''),
        trim($_POST['r4'] ?? ''),
        trim($_POST['r5'] ?? ''),
    ];

    // Cargar datos del fallecido
    $db = getDB();
    $st = $db->prepare("
        SELECT f.Fallecido_Nombre, f.Fallecido_Apellido_1, f.Fallecido_Apellido_2,
               f.Fallecido_Fecha_Nacimiento, f.`Fallecido_Fecha_Defunción`
        FROM h_huella h
        LEFT JOIN h_huellafallecido f ON f.Id_Huella = h.Id_Huella
        WHERE h.Id_Huella = ?
    ");
    if (!$st) {
        echo json_encode(['ok' => false, 'error' => 'Error interno']);
        exit;
    }
    $st->bind_param('s', $idHuella);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Huella no encontrada']);
        exit;
    }

    $nombre = trim(
        ($row['Fallecido_Nombre']     ?? '') . ' ' .
        ($row['Fallecido_Apellido_1'] ?? '') . ' ' .
        ($row['Fallecido_Apellido_2'] ?? '')
    ) ?: 'el fallecido';

    $fechaNac = $row['Fallecido_Fecha_Nacimiento']  ?? null;
    $fechaDef = $row['Fallecido_Fecha_Defunción']   ?? null;

    $nombresIdioma = [
        'CAS' => 'castellano',
        'CAT' => 'catalán',
        'ENG' => 'inglés',
        'EUS' => 'euskera',
    ];
    $idiomaTexto = $nombresIdioma[$idioma] ?? 'castellano';

    $labels = [
        'Profesión',
        'Hobbies',
        'Personalidad',
        'Lo más importante para él/ella',
        'Recuerdo especial',
    ];

    $infoLineas = '';
    foreach ($r as $i => $resp) {
        $val = $resp !== '' ? $resp : 'No indicado';
        $infoLineas .= "- {$labels[$i]}: {$val}\n";
    }

    $sistema = "Eres un redactor de biografías para memoriales digitales.\n\n"
             . "REGLAS ESTRICTAS — son inviolables:\n"
             . "1. USA EXCLUSIVAMENTE los datos que te proporciona la familia. NO añadas información que no esté explícitamente en esos datos.\n"
             . "2. PROHIBIDO inventar, suponer, deducir o adornar: nada de profesiones extra, aficiones extra, lugares, fechas, anécdotas, familiares, logros o cualidades que la familia no haya mencionado.\n"
             . "3. PROHIBIDO usar conocimiento previo sobre el nombre del fallecido aunque coincida con personajes históricos, famosos o ficticios. El nombre es solo una etiqueta.\n"
             . "4. Si un campo viene como 'No indicado', NO lo menciones ni te lo inventes: simplemente omítelo.\n"
             . "5. NO uses frases vagas que añadan contenido no aportado, tipo 'siempre dispuesto a ayudar a los demás', 'querido por todos', 'una persona excepcional'. Solo lo que dice la familia.\n"
             . "6. NO especules sobre sentimientos, creencias o pensamientos del fallecido más allá de lo aportado.\n\n"
             . "ESTILO:\n"
             . "- Tono cálido, respetuoso y sobrio. Tercera persona.\n"
             . "- Texto continuo en párrafos. Sin markdown, sin títulos, sin viñetas.\n"
             . "- Longitud proporcional a la información: si la familia da poco, el texto es corto. NO rellenes para alargar.\n"
             . "- Máximo 500 palabras.\n\n"
             . "Si los datos son muy escasos, redacta una biografía breve y sencilla; es preferible un texto corto y veraz a uno largo e inventado.";

    $prompt = "Redacta la biografía de {$nombre}";
    if ($fechaNac) $prompt .= ", nacido el {$fechaNac}";
    if ($fechaDef) $prompt .= " y fallecido el {$fechaDef}";
    $prompt .= ".\nIdioma del texto: {$idiomaTexto}.\n\n";
    $prompt .= "Datos aportados por la familia (úsalos como ÚNICA fuente; no añadas nada que no esté aquí):\n{$infoLineas}\n";
    $prompt .= "Recuerda: si un campo dice 'No indicado', no lo menciones. No inventes nada. No uses conocimiento externo sobre el nombre.";

    $resultado = llamarGroq($prompt, $sistema, [
        'max_tokens'  => 1024,
        'temperature' => 0.2,
    ]);
    echo json_encode($resultado);
    exit;
}

// ── action=guardar ────────────────────────────────────────────────────────
if ($action === 'guardar') {
    $texto = trim($_POST['texto'] ?? '');

    $db = getDB();
    $st = $db->prepare("UPDATE h_huellafallecido SET Fallecido_Texto=? WHERE Id_Huella=?");
    if (!$st) {
        echo json_encode(['ok' => false, 'error' => 'Error interno']);
        exit;
    }
    $st->bind_param('ss', $texto, $idHuella);
    $ok = $st->execute();
    $st->close();

    if ($ok) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Error al guardar en la base de datos']);
    }
    exit;
}

// ── action=transcribir ────────────────────────────────────────────────────
if ($action === 'transcribir') {
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'No se ha recibido el audio']);
        exit;
    }

    $idiomaIso = [
        'CAS' => 'es',
        'CAT' => 'ca',
        'ENG' => 'en',
        'EUS' => 'eu',
    ][trim((string)($_POST['idioma'] ?? 'CAS'))] ?? 'es';

    $resultado = transcribirAudio($_FILES['audio']['tmp_name'], $idiomaIso);
    echo json_encode($resultado);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción desconocida: ' . htmlspecialchars($action)]);
