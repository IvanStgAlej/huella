<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$tema           = cargarTema();
$cssVars        = generarVariablesCSS($tema['colores'], $tema);
$nombreProducto = $tema['nombre_producto'];
$logoPath       = asset($tema['logo_path']);
$logoAlt        = $tema['logo_alt'];
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas de privacidad — <?= htmlspecialchars($nombreProducto) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <style>:root { <?= $cssVars ?> }</style>
    <style>
        body { background: var(--color-fondo, #f5f5f5); color: var(--color-texto, #1a1a1a); }

        .politicas-wrap {
            max-width: 680px;
            margin: 0 auto;
            padding: 32px 20px 60px;
        }

        .politicas-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: var(--color-primario, #555);
            text-decoration: none;
            margin-bottom: 28px;
            opacity: 0.8;
        }
        .politicas-back:hover { opacity: 1; }
        .politicas-back svg { flex-shrink: 0; }

        .politicas-header {
            margin-bottom: 32px;
        }
        .politicas-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            margin-bottom: 6px;
        }
        .politicas-header p {
            font-size: 0.82rem;
            color: var(--color-texto-suave, #888);
        }

        .politicas-body {
            background: var(--color-fondo-card, #fff);
            border-radius: var(--radio-tarjeta, 12px);
            box-shadow: var(--sombra-tarjeta, 0 1px 6px rgba(0,0,0,.07));
            padding: 32px 28px;
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--color-texto-suave, #555);
            font-style: italic;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="politicas-wrap">

    <a href="javascript:history.back()" class="politicas-back">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Volver
    </a>

    <div class="politicas-header">
        <h1>Políticas de privacidad</h1>
        <p><?= htmlspecialchars($nombreProducto) ?></p>
    </div>

    <div class="politicas-body">
        Texto por definir
    </div>

</div>

</body>
</html>
