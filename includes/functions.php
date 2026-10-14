<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

function sesionActiva(): bool    { return !empty($_SESSION['user']); }
function usuarioActual(): ?array { return $_SESSION['user'] ?? null; }

function renderMenuUsuario(array $u, string $requestUri): string {
    $inicial   = htmlspecialchars(mb_strtoupper(mb_substr($u['nombre'], 0, 1, 'UTF-8'), 'UTF-8'));
    $nombre    = htmlspecialchars($u['nombre']);
    $back      = htmlspecialchars(urlencode($requestUri));
    $idiomas   = ['CAS' => 'Castellano', 'CAT' => 'Català', 'ENG' => 'English', 'EUS' => 'Euskera'];
    $idActual  = $u['idioma'] ?? 'CAS';
    $litActual = htmlspecialchars($idiomas[$idActual] ?? 'Castellano');

    $opts = '';
    foreach ($idiomas as $cod => $lit) {
        $activo = $cod === $idActual ? ' user-dropdown__idioma-op--activo' : '';
        $opts  .= "<button class=\"user-dropdown__idioma-op{$activo}\" data-cod=\"{$cod}\">"
               . htmlspecialchars($lit) . "</button>";
    }

    $linkAdmin = esAdminDeAlgunaHuella()
        ? '<div class="user-dropdown__sep"></div>'
          . '<a href="/huella/admin_usuarios.php" class="user-dropdown__admin">'
          . '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
          . 'Gestión de usuarios</a>'
        : '';

    return <<<HTML
<div class="user-menu">
    <button class="btn-icon btn-icon--usuario" id="btn-usuario" title="Mi cuenta">{$inicial}</button>
    <div class="user-dropdown" id="user-dropdown">
        <span class="user-dropdown__nombre">{$nombre}</span>
        <div class="user-dropdown__sep"></div>
        <button class="user-dropdown__idioma-btn" id="btn-idioma">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <span id="idioma-txt">{$litActual}</span>
            <svg class="user-dropdown__chevron" id="chevron-idioma" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="user-dropdown__idioma-lista" id="idioma-lista">{$opts}</div>
        {$linkAdmin}
        <div class="user-dropdown__sep"></div>
        <a href="/huella/logout.php?back={$back}" class="user-dropdown__cerrar">Cerrar sesión</a>
    </div>
</div>
<script>
!function(){
    var b=document.getElementById('btn-usuario'),
        d=document.getElementById('user-dropdown'),
        bi=document.getElementById('btn-idioma'),
        il=document.getElementById('idioma-lista'),
        ch=document.getElementById('chevron-idioma'),
        it=document.getElementById('idioma-txt');

    b.addEventListener('click',function(e){
        e.stopPropagation();
        d.classList.toggle('open');
        il.classList.remove('open');
        ch.classList.remove('abierto');
    });
    bi.addEventListener('click',function(e){
        e.stopPropagation();
        il.classList.toggle('open');
        ch.classList.toggle('abierto');
    });
    il.querySelectorAll('.user-dropdown__idioma-op').forEach(function(btn){
        btn.addEventListener('click',function(e){
            e.stopPropagation();
            var cod=this.dataset.cod;
            fetch('/huella/auth_api.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=idioma&idioma='+encodeURIComponent(cod)
            }).then(function(r){return r.json();}).then(function(res){
                if(res.ok){
                    it.textContent=res.literal;
                    il.querySelectorAll('.user-dropdown__idioma-op--activo').forEach(function(el){el.classList.remove('user-dropdown__idioma-op--activo')});
                    btn.classList.add('user-dropdown__idioma-op--activo');
                    d.classList.remove('open');
                    location.reload();
                }
            });
        });
    });
    document.addEventListener('click',function(){
        d.classList.remove('open');
        il.classList.remove('open');
        ch.classList.remove('abierto');
    });
}();
</script>
HTML;
}

function esAdminHuella(string $idHuella): bool {
    $user = usuarioActual();
    if (!$user) return false;
    return ($user['id_huella'] ?? '') === $idHuella
        && in_array($user['rol'] ?? '', ['ADM', 'OWN'], true);
}

function esAdminDeAlgunaHuella(): bool {
    $user = usuarioActual();
    if (!$user) return false;
    return in_array($user['rol'] ?? '', ['ADM', 'OWN'], true);
}

function cargarTema(): array {
    $json = file_get_contents(__DIR__ . '/../config/theme.json');
    return json_decode($json, true);
}

function asset(string $path): string {
    static $base = null;
    if ($base === null) {
        $tema = cargarTema();
        $base = rtrim($tema['base_url'] ?? '/huella/', '/') . '/';
    }
    return $base . ltrim($path, '/');
}

function urlHuella(string $link, string $pagina = ''): string {
    static $base = null;
    if ($base === null) {
        $tema = cargarTema();
        $base = rtrim($tema['base_url'] ?? '/huella/', '/');
    }
    $url = $base . '/' . rawurlencode($link);
    return $pagina ? $url . '/' . $pagina : $url;
}

function cargarIdioma(string $codigo = 'CAS'): array {
    $archivo = __DIR__ . '/../config/lang/' . strtoupper($codigo) . '.json';
    if (!file_exists($archivo)) {
        $archivo = __DIR__ . '/../config/lang/CAS.json';
    }
    return json_decode(file_get_contents($archivo), true);
}

function obtenerHuella(string $idHuella): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT h.*, f.Fallecido_Nombre, f.Fallecido_Apellido_1, f.Fallecido_Apellido_2,
               f.Fallecido_Fecha_Nacimiento, `f`.`Fallecido_Fecha_Defunción`,
               f.Fallecido_Titulo, f.Fallecido_Texto, f.Fallecido_Foto
        FROM h_huella h
        LEFT JOIN h_huellafallecido f ON h.Id_Huella = f.Id_Huella
        WHERE h.Id_Huella = ?
          AND h.Ocultar = 0
    ");
    $stmt->bind_param('s', $idHuella);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function obtenerHuellaByLink(string $link): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT h.*, f.Fallecido_Nombre, f.Fallecido_Apellido_1, f.Fallecido_Apellido_2,
               f.Fallecido_Fecha_Nacimiento, `f`.`Fallecido_Fecha_Defunción`,
               f.Fallecido_Titulo, f.Fallecido_Texto, f.Fallecido_Foto
        FROM h_huella h
        LEFT JOIN h_huellafallecido f ON h.Id_Huella = f.Id_Huella
        WHERE h.link = ?
          AND h.Ocultar = 0
    ");
    $stmt->bind_param('s', $link);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function listarHuellas(): array {
    $db = getDB();
    $resultado = $db->query("
        SELECT h.Id_Huella, h.link,
               f.Fallecido_Nombre, f.Fallecido_Apellido_1, f.Fallecido_Apellido_2
        FROM h_huella h
        LEFT JOIN h_huellafallecido f ON h.Id_Huella = f.Id_Huella
        WHERE h.Ocultar = 0
        ORDER BY f.Fallecido_Apellido_1
    ");
    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function obtenerColoresCliente(string $idCliente, array $tema): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT color_primario, color_secundario, color_acento,
               color_fondo, color_fondo_card, color_texto,
               color_texto_suave, color_borde
        FROM h_clientesconfig WHERE Id_Cliente = ?
    ");
    $stmt->bind_param('s', $idCliente);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $colores = $tema['colores'];
    if ($row) {
        foreach ($colores as $clave => $_) {
            if (!empty($row[$clave])) $colores[$clave] = $row[$clave];
        }
    }
    return $colores;
}

function obtenerTemaCliente(string $idCliente, array $tema): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT color_primario, color_secundario, color_acento,
               color_fondo, color_fondo_card, color_texto,
               color_texto_suave, color_borde,
               fuente_titulo, fuente_cuerpo, tamanio_base,
               max_ancho_contenido, radio_tarjeta, sombra_tarjeta,
               nombre_producto, logo_path, logo_alt
        FROM h_clientesconfig WHERE Id_Cliente = ?
    ");
    $stmt->bind_param('s', $idCliente);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) return $tema;

    foreach ($tema['colores'] as $clave => $_) {
        if (!empty($row[$clave])) $tema['colores'][$clave] = $row[$clave];
    }
    $tipoCampos = ['fuente_titulo' => 'tipografia', 'fuente_cuerpo' => 'tipografia', 'tamanio_base' => 'tipografia'];
    $layoutCampos = ['max_ancho_contenido', 'radio_tarjeta', 'sombra_tarjeta'];
    foreach ($tipoCampos as $campo => $seccion) {
        if (!empty($row[$campo])) $tema[$seccion][$campo] = $row[$campo];
    }
    foreach ($layoutCampos as $campo) {
        if (!empty($row[$campo])) $tema['layout'][$campo] = $row[$campo];
    }
    foreach (['nombre_producto', 'logo_path', 'logo_alt'] as $campo) {
        if (!empty($row[$campo])) $tema[$campo] = $row[$campo];
    }
    return $tema;
}

function formatearFecha(?string $fecha, string $idioma = 'CAS'): string {
    if (!$fecha || $fecha === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($fecha);
    $locales = ['CAS' => 'es_ES', 'CAT' => 'ca_ES', 'ENG' => 'en_GB', 'EUS' => 'eu_ES'];
    $meses = [
        'CAS' => ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
        'ENG' => ['January','February','March','April','May','June','July','August','September','October','November','December'],
    ];
    $m = $meses[$idioma] ?? $meses['CAS'];
    return date('j', $ts) . ' de ' . $m[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function obtenerActos(string $idHuella, string $idCliente, string $empresa): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT a.Id_TareaBase, a.Id_Lugar,
               a.Act_FechaHora_Inicio, a.Act_FechaHora_Fin,
               a.Act_Texto, a.Act_Sala, a.Act_Visible,
               t.Literal AS Tipo_Literal,
               l.Lugar_Direccion, l.Lugar_Ubicacion, `l`.`Lugar_Teléfono`, l.Lugar_Foto1
        FROM h_huellaactividades a
        LEFT JOIN hs_tareasbase t ON a.Id_TareaBase = t.Id_TareaBase
        LEFT JOIN h_empresaslugareshabituales l
               ON l.Id_Lugar = a.Id_Lugar
              AND l.Id_Cliente = ?
              AND l.Empresa = a.Empresa
        WHERE a.Id_Huella = ?
          AND a.Act_Visible = 1
        ORDER BY a.Act_FechaHora_Inicio ASC
    ");
    $stmt->bind_param('ss', $idCliente, $idHuella);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function obtenerAsistenciaDetalle(string $idHuella): array {
    $db = getDB();
    $st = $db->prepare("
        SELECT a.Id_TareaBase, a.Asiste_SN, r.User_Nombre_Apellidos AS nombre
        FROM h_huellaactividadesasistencia a
        JOIN h_huellausersroles r ON r.Id = a.Id_User
        WHERE a.Id_Huella = ?
        ORDER BY a.Id_TareaBase, a.Asiste_SN DESC, r.User_Nombre_Apellidos
    ");
    $st->bind_param('s', $idHuella);
    $st->execute();
    $data = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $t = $r['Id_TareaBase'];
        if (!isset($data[$t])) $data[$t] = ['si' => [], 'no' => []];
        if ($r['Asiste_SN']) $data[$t]['si'][] = $r['nombre'];
        else                 $data[$t]['no'][] = $r['nombre'];
    }
    $st->close();
    return $data;
}

function obtenerAsistencia(string $idHuella, ?int $idUser): array {
    $db   = getDB();
    $data = [];

    $cnt = $db->prepare("SELECT Id_TareaBase, Asiste_SN, COUNT(*) as n FROM h_huellaactividadesasistencia WHERE Id_Huella=? GROUP BY Id_TareaBase, Asiste_SN");
    $cnt->bind_param('s', $idHuella);
    $cnt->execute();
    foreach ($cnt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $t = $r['Id_TareaBase'];
        if (!isset($data[$t])) $data[$t] = ['si' => 0, 'no' => 0, 'voto' => null];
        if ($r['Asiste_SN']) $data[$t]['si'] = (int)$r['n'];
        else                 $data[$t]['no'] = (int)$r['n'];
    }
    $cnt->close();

    if ($idUser) {
        $uid = (string)$idUser;
        $v = $db->prepare("SELECT Id_TareaBase, Asiste_SN FROM h_huellaactividadesasistencia WHERE Id_Huella=? AND Id_User=?");
        $v->bind_param('ss', $idHuella, $uid);
        $v->execute();
        foreach ($v->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $t = $r['Id_TareaBase'];
            if (!isset($data[$t])) $data[$t] = ['si' => 0, 'no' => 0, 'voto' => null];
            $data[$t]['voto'] = (int)$r['Asiste_SN'];
        }
        $v->close();
    }

    return $data;
}

function formatearFechaHora(?string $fecha, string $idioma = 'CAS'): string {
    if (!$fecha || $fecha === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($fecha);
    $diasSemana = [
        'CAS' => ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'],
        'ENG' => ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
    ];
    $meses = [
        'CAS' => ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],
        'ENG' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    ];
    $ds = $diasSemana[$idioma] ?? $diasSemana['CAS'];
    $ms = $meses[$idioma] ?? $meses['CAS'];
    $diaSemana = $ds[(int)date('w', $ts)];
    $dia       = date('j', $ts);
    $mes       = $ms[(int)date('n', $ts) - 1];
    $hora      = date('H:i', $ts);
    return ucfirst($diaSemana) . ', ' . $dia . ' ' . $mes . ', ' . $hora;
}

function generarIdPost(string $idHuella): string {
    $db    = getDB();
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $id = '';
        for ($i = 0; $i < 6; $i++) $id .= $chars[random_int(0, 35)];
        $st = $db->prepare("SELECT 1 FROM h_huellapost WHERE Id_Huella=? AND Id_Post=?");
        $st->bind_param('ss', $idHuella, $id);
        $st->execute();
    } while ($st->get_result()->num_rows > 0);
    return $id;
}

function _cargarMediaPosts(string $idHuella, array &$posts): void {
    if (empty($posts)) return;
    $db    = getDB();
    $h     = $db->real_escape_string($idHuella);
    $inIds = implode(',', array_map(fn($p) => "'" . $db->real_escape_string($p['Id_Post']) . "'", $posts));

    $imgs = $db->query("SELECT Id_Post, Img_Ruta_Carpeta, Img_Nombre, Img_Orden FROM h_huellapost_imagenes WHERE Id_Huella='$h' AND Id_Post IN ($inIds) ORDER BY Img_Orden ASC")->fetch_all(MYSQLI_ASSOC);
    $vids = $db->query("SELECT Id_Post, Video_Ruta_Carpeta, Video_Nombre FROM h_huellapost_videos WHERE Id_Huella='$h' AND Id_Post IN ($inIds)")->fetch_all(MYSQLI_ASSOC);
    $auds = $db->query("SELECT Id_Post, Audio_Ruta_Carpeta, Audio_Nombre FROM h_huellapost_audios WHERE Id_Huella='$h' AND Id_Post IN ($inIds)")->fetch_all(MYSQLI_ASSOC);

    $imgMap = []; foreach ($imgs as $i) $imgMap[$i['Id_Post']][] = $i;
    $vidMap = []; foreach ($vids as $v) $vidMap[$v['Id_Post']][] = $v;
    $audMap = []; foreach ($auds as $a) $audMap[$a['Id_Post']][] = $a;

    foreach ($posts as &$p) {
        $p['imagenes'] = $imgMap[$p['Id_Post']] ?? [];
        $p['videos']   = $vidMap[$p['Id_Post']] ?? [];
        $p['audios']   = $audMap[$p['Id_Post']] ?? [];
    }
}

function obtenerPosts(string $idHuella): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.Id_Post, p.Post_Id_user, p.Post_Autor, p.Post_Texto, p.Post_Link, p.Post_Fecha,
               p.Post_Flag_Malsonante,
               COALESCE(lk.cnt, 0) AS total_likes,
               COALESCE(rp.cnt, 0) AS total_respuestas
        FROM h_huellapost p
        LEFT JOIN (SELECT Id_Huella, Id_Post, COUNT(*) AS cnt FROM h_huellapost_likes GROUP BY Id_Huella, Id_Post) lk
               ON lk.Id_Huella = p.Id_Huella AND lk.Id_Post = p.Id_Post
        LEFT JOIN (SELECT Id_Huella, Post_Parent_Id, COUNT(*) AS cnt FROM h_huellapost WHERE Post_Eliminado=0 GROUP BY Id_Huella, Post_Parent_Id) rp
               ON rp.Id_Huella = p.Id_Huella AND rp.Post_Parent_Id = p.Id_Post
        WHERE p.Id_Huella = ?
          AND (p.Post_Vetado IS NULL OR p.Post_Vetado = 0)
          AND (p.Post_Autorizado IS NULL OR p.Post_Autorizado = 1)
          AND p.Post_Eliminado = 0
          AND p.Post_Parent_Id IS NULL
        GROUP BY p.Id_Post
        ORDER BY p.Post_Fecha DESC
    ");
    $stmt->bind_param('s', $idHuella);
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    _cargarMediaPosts($idHuella, $posts);
    return $posts;
}

function obtenerRespuestas(string $idHuella, string $idPost): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.Id_Post, p.Post_Id_user, p.Post_Autor, p.Post_Texto, p.Post_Link, p.Post_Fecha,
               p.Post_Flag_Malsonante,
               COALESCE(lk.cnt, 0) AS total_likes
        FROM h_huellapost p
        LEFT JOIN (SELECT Id_Huella, Id_Post, COUNT(*) AS cnt FROM h_huellapost_likes GROUP BY Id_Huella, Id_Post) lk
               ON lk.Id_Huella = p.Id_Huella AND lk.Id_Post = p.Id_Post
        WHERE p.Id_Huella = ? AND p.Post_Parent_Id = ?
          AND (p.Post_Vetado IS NULL OR p.Post_Vetado = 0)
          AND p.Post_Eliminado = 0
        ORDER BY p.Post_Fecha ASC
    ");
    $stmt->bind_param('ss', $idHuella, $idPost);
    $stmt->execute();
    $replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    _cargarMediaPosts($idHuella, $replies);
    return $replies;
}

function tiempoRelativo(?string $fecha): string {
    if (!$fecha) return '';
    $diff = time() - strtotime($fecha);
    if ($diff < 60)     return 'Hace un momento';
    if ($diff < 3600)   return 'Hace ' . (int)($diff/60) . ' min';
    if ($diff < 86400)  return 'Hace ' . (int)($diff/3600) . ' h';
    if ($diff < 604800) return 'Hace ' . (int)($diff/86400) . ' días';
    return date('d/m/Y', strtotime($fecha));
}

function iconoActo(string $idTarea): string {
    $iconos = [
        'SALA_V' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'EXEQUI' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M10 4h4"/><path d="M5 22V10l7-6 7 6v12H5z"/><rect x="9" y="15" width="6" height="7"/></svg>',
        'INCINE' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/><path d="M12 8v4l3 3"/></svg>',
        'INHUMA' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v10"/><path d="M9 5h6"/><path d="M5 22h14"/><path d="M7 22v-5a5 5 0 0 1 10 0v5"/></svg>',
    ];
    return $iconos[$idTarea] ?? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
}

function generarVariablesCSS(array $colores, array $tema): string {
    $t = $tema['tipografia'];
    $l = $tema['layout'];
    return "
        --color-primario:    {$colores['color_primario']};
        --color-secundario:  {$colores['color_secundario']};
        --color-acento:      {$colores['color_acento']};
        --color-fondo:       {$colores['color_fondo']};
        --color-fondo-card:  {$colores['color_fondo_card']};
        --color-texto:       {$colores['color_texto']};
        --color-texto-suave: {$colores['color_texto_suave']};
        --color-borde:       {$colores['color_borde']};
        --fuente-titulo:     {$t['fuente_titulo']};
        --fuente-cuerpo:     {$t['fuente_cuerpo']};
        --tamanio-base:      {$t['tamanio_base']};
        --max-ancho:         {$l['max_ancho_contenido']};
        --radio-card:        {$l['radio_tarjeta']};
        --sombra-card:       {$l['sombra_tarjeta']};
    ";
}

function _cargarPalabrasMalsonantes(): array {
    static $palabras = null;
    if ($palabras === null) {
        $archivo  = __DIR__ . '/../config/palabras_malsonantes.json';
        $palabras = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];
    }
    return $palabras ?? [];
}

function contienemalsonantes(string $texto): bool {
    foreach (_cargarPalabrasMalsonantes() as $palabra) {
        $patron = '/(?<![a-záéíóúüñ])' . preg_quote($palabra, '/') . '(?![a-záéíóúüñ])/iu';
        if (preg_match($patron, $texto)) return true;
    }
    return false;
}

function filtrarMalsonantes(string $texto): string {
    $palabras = _cargarPalabrasMalsonantes();
    if (empty($palabras)) return $texto;

    foreach ($palabras as $palabra) {
        $reemplazo = str_repeat('*', mb_strlen($palabra));
        $patron = '/(?<![a-záéíóúüñ])' . preg_quote($palabra, '/') . '(?![a-záéíóúüñ])/iu';
        $texto  = preg_replace($patron, $reemplazo, $texto);
    }
    return $texto;
}
