<?php

/**
 * Notificaciones del usuario actual.
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerir_login();

if (isset($_GET['marcar'])) {
    Notificacion::marcar_todas_leidas();
    flash_set('success', 'Notificaciones marcadas como leídas.');
    redirigir('notificaciones.php');
}

$titulo    = 'Notificaciones';
$subtitulo = 'Alertas y novedades de tu cuenta';
$seccion   = 'notificaciones';
$ancho     = 'content-narrow';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Tus notificaciones</h2>
            <p><?= (int) Notificacion::no_leidas() ?> sin leer</p>
        </div>
        <?php if (Notificacion::no_leidas() > 0): ?>
            <div class="panel-actions">
                <a class="btn btn-outline btn-sm" href="notificaciones.php?marcar=1">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                    Marcar todas como leídas
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php $notis = Notificacion::listar(null, 50); ?>

    <?php if (!$notis): ?>
        <div class="empty">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 8A6 6 0 106 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0" />
            </svg>
            <h3>Todo limpio por aquí</h3>
            <p>No tienes notificaciones. Aquí aparecerán los avisos de matrículas, notas y publicaciones.</p>
            <a class="btn btn-outline" href="<?= e(base_url('dashboard.php')) ?>">Volver al panel</a>
        </div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($notis as $n): ?>
                <div class="notif <?= $n['leida'] ? '' : 'unread' ?>">
                    <span class="notif-icon <?= e($n['tipo']) ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <?php if ($n['tipo'] === 'success'): ?>
                                <path d="M20 6L9 17l-5-5" />
                            <?php elseif ($n['tipo'] === 'warning'): ?>
                                <path d="M12 9v4m0 4h.01M12 3l9 16H3l9-16z" />
                            <?php elseif ($n['tipo'] === 'error'): ?>
                                <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                            <?php else: ?>
                                <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <?php endif; ?>
                        </svg>
                    </span>
                    <div style="min-width:0;flex:1">
                        <b><?= e($n['titulo']) ?></b>
                        <p><?= e($n['mensaje']) ?></p>
                        <time datetime="<?= e($n['creado_en']) ?>"><?= e(fecha($n['creado_en'], 'd/m/Y H:i')) ?></time>
                    </div>
                    <?php if ($n['url']): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(base_url($n['url'])) ?>">Ver</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>