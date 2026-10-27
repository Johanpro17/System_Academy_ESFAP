<?php
require_once __DIR__ . '/../core/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · Página no encontrada · <?= e(APP_NOMBRE) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <style>
        :root{--bg:#f4f6fb;--card:#fff;--line:#e3e8f0;--text:#0d1526;--muted:#4b5768;--brand:#1d4ed8;--gold:#c8a24a}
        *{box-sizing:border-box;font-family:Inter,sans-serif}
        body{margin:0;background:radial-gradient(1000px 600px at 90% -10%,rgba(200,162,74,.09),transparent 62%),radial-gradient(1000px 640px at -10% 2%,rgba(29,78,216,.09),transparent 62%),var(--bg);color:var(--text);display:grid;place-items:center;min-height:100vh;padding:24px}
        .card{max-width:520px;background:var(--card);border:1px solid var(--line);border-radius:26px;box-shadow:0 24px 60px rgba(13,21,38,.12);padding:44px;text-align:center;position:relative;overflow:hidden}
        .card::after{content:"";position:absolute;inset:0;background:radial-gradient(240px 160px at 92% 12%,rgba(200,162,74,.14),transparent 64%);pointer-events:none}
        .card>*{position:relative;z-index:2}
        .mark{width:72px;height:72px;margin:0 auto 18px;border-radius:18px;display:grid;place-items:center;background:linear-gradient(140deg,#2563eb,#1733a5);color:#fff;font-size:1.7rem;font-weight:800;box-shadow:0 12px 30px rgba(23,51,165,.28)}
        h1{margin:0;font-size:1.4rem;letter-spacing:-.01em}
        p{margin:10px 0 0;color:var(--muted);line-height:1.7}
        .btn{display:inline-flex;margin-top:26px;gap:8px;align-items:center;padding:11px 20px;border-radius:12px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-weight:600;text-decoration:none;box-shadow:0 10px 26px rgba(29,78,216,.24)}
        small{display:block;margin-top:12px;color:var(--muted);font-size:.82rem}
    </style>
</head>
<body>
<div class="card">
    <div class="mark">404</div>
    <h1>Página no encontrada</h1>
    <p>El enlace puede haber sido modificado, eliminado o la dirección es incorrecta.</p>
    <a class="btn" href="<?= e(base_url('public/index.php')) ?>">Volver a la portada</a>
    <small>Si crees que esto es un error, informa al administrador.</small>
</div>
</body>
</html>