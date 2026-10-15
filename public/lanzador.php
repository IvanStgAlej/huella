<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$resultado = $db->query("
    SELECT h.Id_Huella, h.link, h.Id_Idioma, h.Id_Cliente,
           h.Empresa, h.Delegacion, h.AutorizarMuro_SN,
           f.Fallecido_Nombre, f.Fallecido_Apellido_1, f.Fallecido_Apellido_2,
           f.Fallecido_Fecha_Nacimiento, f.Fallecido_Fecha_Defunción,
           c.nombre_producto, c.color_primario
    FROM h_huella h
    LEFT JOIN h_huellafallecido f ON h.Id_Huella = f.Id_Huella
    LEFT JOIN h_clientesconfig c  ON h.Id_Cliente = c.Id_Cliente
    WHERE h.Ocultar = 0
    ORDER BY f.Fallecido_Apellido_1, f.Fallecido_Nombre
");
$huellas = $resultado->fetch_all(MYSQLI_ASSOC);

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST'] . '/huella/';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lanzador de Huellas</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f0f2f5;
    color: #1a1a1a;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40px 16px 60px;
  }

  header {
    text-align: center;
    margin-bottom: 36px;
  }
  header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111;
    letter-spacing: -0.5px;
  }
  header p {
    margin-top: 6px;
    color: #666;
    font-size: 0.95rem;
  }

  .card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    padding: 32px;
    width: 100%;
    max-width: 680px;
  }

  label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #555;
    margin-bottom: 8px;
  }

  .huella-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 28px;
  }

  .huella-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border: 2px solid #e8e8e8;
    border-radius: 10px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    user-select: none;
  }
  .huella-item:hover { border-color: #aaa; background: #fafafa; }
  .huella-item.selected { border-color: #2563eb; background: #eff6ff; }

  .huella-item input[type=radio] { display: none; }

  .huella-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    background: #94a3b8;
  }

  .huella-info { flex: 1; min-width: 0; }
  .huella-nombre {
    font-weight: 600;
    font-size: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .huella-meta {
    font-size: 0.78rem;
    color: #888;
    margin-top: 2px;
  }

  .badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    background: #e2e8f0;
    color: #475569;
    white-space: nowrap;
  }
  .badge.muro { background: #dcfce7; color: #166534; }

  .btn-generar {
    width: 100%;
    padding: 14px;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    letter-spacing: .2px;
  }
  .btn-generar:hover { background: #1d4ed8; }
  .btn-generar:disabled { background: #93c5fd; cursor: not-allowed; }

  .resultado {
    margin-top: 24px;
    display: none;
  }
  .resultado.visible { display: block; }

  .resultado-titulo {
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #555;
    margin-bottom: 10px;
  }

  .enlaces-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .enlace-box {
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    padding: 12px 14px;
    background: #f8fafc;
  }
  .enlace-box.principal {
    grid-column: 1 / -1;
    background: #eff6ff;
    border-color: #bfdbfe;
  }
  .enlace-box.tv {
    grid-column: 1 / -1;
    background: #0f0f0f;
    border-color: #333;
  }
  .enlace-box.tv .enlace-label { color: #666; }
  .enlace-box.tv .enlace-url   { color: #a78bfa; }
  .enlace-box.tv .btn-abrir    { background: #6d28d9; }
  .enlace-box.tv .btn-abrir:hover { background: #5b21b6; }
  .enlace-box.tv .btn-copiar   { background: #1f1f1f; color: #aaa; border: 1px solid #333; }
  .enlace-box.tv .btn-copiar:hover { background: #2a2a2a; }
  .enlace-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #64748b;
    margin-bottom: 6px;
  }
  .enlace-url {
    font-size: 0.88rem;
    color: #1e40af;
    word-break: break-all;
    font-family: 'Courier New', monospace;
    margin-bottom: 8px;
  }
  .enlace-acciones {
    display: flex;
    gap: 8px;
  }
  .btn-copiar, .btn-abrir {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    transition: background .12s;
  }
  .btn-copiar {
    background: #e2e8f0;
    color: #334155;
  }
  .btn-copiar:hover { background: #cbd5e1; }
  .btn-copiar.copiado { background: #bbf7d0; color: #15803d; }
  .btn-abrir {
    background: #2563eb;
    color: #fff;
  }
  .btn-abrir:hover { background: #1d4ed8; }

  .empty {
    text-align: center;
    color: #999;
    padding: 32px;
    font-size: 0.95rem;
  }

  .hero {
    background: #fff;
    border-bottom: 1px solid #e8e0d8;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 2rem 1rem 1.5rem;
    margin-bottom: 0.75rem;
    width: 100%;
    max-width: 680px;
    border-radius: 0 0 16px 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
  }

  .hero__foto-wrap { margin-bottom: 1.1rem; }

  .hero__foto {
    width: 110px; height: 110px;
    border-radius: 50%;
    border: 3px solid #c4a882;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    background: #f7f4f1;
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
  }

  .hero__foto img {
    width: 100%; height: 100%;
    object-fit: cover;
  }

  .hero__label {
    font-size: 0.68rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: #6b6b6b;
    margin-bottom: 0.4rem;
    font-family: 'Segoe UI', Arial, sans-serif;
  }

  .hero__titulo {
    font-family: 'Georgia', 'Times New Roman', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c2c2c;
    letter-spacing: 0.03em;
    margin-bottom: 0.25rem;
  }

  .hero__sub {
    font-size: 0.85rem;
    color: #6b6b6b;
    margin-top: 0.2rem;
  }
</style>
</head>
<body>

<div class="hero">
  <div class="hero__foto-wrap">
    <div class="hero__foto">
      <img src="/huella/assets/img/HuellasLogo.jpeg" alt="Huellas Logo">
    </div>
  </div>
  <div class="hero__label">Bienvenido</div>
  <h1 class="hero__titulo">Lanzador de Huellas</h1>
  <p class="hero__sub">Selecciona un memorial y genera su enlace directo</p>
</div>

<div class="card">

  <?php if (empty($huellas)): ?>
    <div class="empty">No hay huellas disponibles.</div>
  <?php else: ?>

    <label>Selecciona un memorial</label>
    <div class="huella-list" id="lista">
      <?php foreach ($huellas as $i => $h):
        $nombre = trim(($h['Fallecido_Nombre'] ?? '') . ' ' . ($h['Fallecido_Apellido_1'] ?? '') . ' ' . ($h['Fallecido_Apellido_2'] ?? ''));
        $nombre = $nombre ?: '(Sin nombre)';
        $inicial = mb_strtoupper(mb_substr($h['Fallecido_Nombre'] ?? '?', 0, 1));
        $cliente = htmlspecialchars($h['nombre_producto'] ?? $h['Id_Cliente']);
        $idioma  = htmlspecialchars($h['Id_Idioma'] ?? '');
        $tieneMuro = (bool)$h['AutorizarMuro_SN'];
        $color = $h['color_primario'] ?: '#94a3b8';
        $link  = htmlspecialchars($h['link'] ?? '');

        $nacimiento = $h['Fallecido_Fecha_Nacimiento'] ?? null;
        $defuncion  = $h['Fallecido_Fecha_Defunción']  ?? null;
        $fechas = '';
        if ($nacimiento && $nacimiento !== '0000-00-00 00:00:00') {
            $fechas .= date('d/m/Y', strtotime($nacimiento));
        }
        if ($defuncion && $defuncion !== '0000-00-00 00:00:00') {
            $fechas .= ($fechas ? ' — ' : '') . date('d/m/Y', strtotime($defuncion));
        }
      ?>
      <label class="huella-item<?= $i === 0 ? ' selected' : '' ?>"
             data-link="<?= $link ?>"
             data-nombre="<?= htmlspecialchars($nombre) ?>">
        <input type="radio" name="huella" value="<?= $link ?>" <?= $i === 0 ? 'checked' : '' ?>>
        <div class="huella-avatar" style="background:<?= htmlspecialchars($color) ?>">
          <?= htmlspecialchars($inicial) ?>
        </div>
        <div class="huella-info">
          <div class="huella-nombre"><?= htmlspecialchars($nombre) ?></div>
          <div class="huella-meta">
            <?= $cliente ?> &middot; <?= $idioma ?>
            <?= $fechas ? ' &middot; ' . $fechas : '' ?>
          </div>
        </div>
        <?php if ($tieneMuro): ?>
          <span class="badge muro">Muro</span>
        <?php else: ?>
          <span class="badge">Solo info</span>
        <?php endif; ?>
      </label>
      <?php endforeach; ?>
    </div>

    <button class="btn-generar" id="btnGenerar">Generar enlace</button>

    <div class="resultado" id="resultado">
      <div class="resultado-titulo">Enlaces generados</div>
      <div class="enlaces-grid" id="enlacesGrid"></div>
    </div>

  <?php endif; ?>
</div>

<script>
const BASE = <?= json_encode($base) ?>;
const tieneMuro = {};
<?php foreach ($huellas as $h): ?>
tieneMuro[<?= json_encode($h['link'] ?? '') ?>] = <?= $h['AutorizarMuro_SN'] ? 'true' : 'false' ?>;
<?php endforeach; ?>

// Selección visual
document.querySelectorAll('.huella-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.huella-item').forEach(i => i.classList.remove('selected'));
    item.classList.add('selected');
    item.querySelector('input[type=radio]').checked = true;
    ocultarResultado();
  });
});

function ocultarResultado() {
  document.getElementById('resultado').classList.remove('visible');
}

document.getElementById('btnGenerar').addEventListener('click', () => {
  const radio = document.querySelector('input[name=huella]:checked');
  if (!radio) return;
  const link = radio.value;
  const nombre = radio.closest('.huella-item').dataset.nombre;
  mostrarEnlaces(link, nombre);
});

function mostrarEnlaces(link, nombre) {
  const urlTv    = BASE + 'tv.php?link=' + encodeURIComponent(link);
  const backMain = '/huella/' + link;
  const backTv   = '/huella/tv.php?link=' + encodeURIComponent(link);
  const urls = [
    { label: 'Página principal', url: BASE + link, back: backMain, principal: true },
    { label: 'Pantalla TV',      url: urlTv,       back: backTv,   principal: false, tv: true },
  ];

  const grid = document.getElementById('enlacesGrid');
  grid.innerHTML = urls.map(u => `
    <div class="enlace-box${u.principal ? ' principal' : ''}${u.tv ? ' tv' : ''}">
      <div class="enlace-label">${u.label}</div>
      <div class="enlace-url">${u.url}</div>
      <div class="enlace-acciones">
        <button class="btn-copiar" onclick="copiar(this, '${u.url}')">Copiar</button>
        <button class="btn-abrir" onclick="window.open('/huella/logout.php?back='+encodeURIComponent('${u.back}'),'_blank')">Abrir</button>
      </div>
    </div>
  `).join('');

  document.getElementById('resultado').classList.add('visible');
}

function copiar(btn, url) {
  navigator.clipboard.writeText(url).then(() => {
    btn.textContent = '¡Copiado!';
    btn.classList.add('copiado');
    setTimeout(() => {
      btn.textContent = 'Copiar';
      btn.classList.remove('copiado');
    }, 1800);
  });
}

// Generar automáticamente para la primera huella al cargar
window.addEventListener('DOMContentLoaded', () => {
  const first = document.querySelector('input[name=huella]:checked');
  if (first) {
    const link = first.value;
    const nombre = first.closest('.huella-item').dataset.nombre;
    mostrarEnlaces(link, nombre);
  }
});
</script>
</body>
</html>
