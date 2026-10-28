<?php
require_once __DIR__ . '/../core/bootstrap.php';
$mensaje = 'Acceso restringido. No tiene permisos para visualizar este recurso.';
$url = 'javascript:history.back()';
$texto = 'Volver atrás';
if (defined('APP_NOMBRE')) {
    $mensaje = 'Acceso no autorizado.';
    $url = base_url('public/dashboard.php');
    $texto = 'Ir al panel';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Acceso denegado · <?= e(APP_NOMBRE) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <style>
        :root{--bg:#f4f6fb;--card:#fff;--line:#e3e8f0;--text:#0d1526;--muted:#4b5768;--brand:#6d28d9}
        *{box-sizing:border-box;font-family:Inter,sans-serif}
        body{margin:0;background:radial-gradient(1000px 600px at 90% -10%,rgba(200,162,74,.09),transparent 62%),radial-gradient(1000px 640px at -10% 2%,rgba(109,40,217,.09),transparent 62%),var(--bg);color:var(--text);display:grid;place-items:center;min-height:100vh;padding:24px}
        .card{max-width:520px;background:var(--card);border:1px solid var(--line);border-radius:26px;box-shadow:0 24px 60px rgba(13,21,38,.12);padding:44px;text-align:center;position:relative;overflow:hidden}
        .card::after{content:"";position:absolute;inset:0;background:radial-gradient(240px 160px at 92% 12%,rgba(200,162,74,.18),transparent 64%);pointer-events:none}
        .card>*{position:relative;z-index:2}
        .mark{width:72px;height:72px;margin:0 auto 18px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(140deg,#7c3aed,#5b21b6);color:#fff;font-size:1.7rem;font-weight:800;box-shadow:0 12px 30px rgba(91,33,182,.28)}
        h1{margin:0;font-size:1.4rem;letter-spacing:-.01em}
        p{margin:10px 0 0;color:var(--muted);line-height:1.7}
        .btn{display:inline-flex;margin-top:26px;gap:8px;align-items:center;padding:11px 20px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;font-weight:600;text-decoration:none;box-shadow:0 10px 26px rgba(109,40,217,.24)}
    </style>
</head>
<body>
<div class="card">
    <div class="mark">403</div>
    <h1>Acceso denegado</h1>
    <p><?= e($mensaje) ?></p>
    <a class="btn" href="<?= e($url) ?>"><?= e($texto) ?></a>
</div>
</body>
</html>