<?php

/**
 * Bitácora de auditoría del sistema (administración y auditoría).
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('auditoria.ver');

$q        = input('q');
$accion   = input('accion_filtro');
$entidad  = input('entidad');
$usuario  = input_int('usuario');
$desde    = input('desde');
$hasta    = input('hasta');
$pag      = max(1, input_int('pag', 1));
$porPagina = 25;

$filtros = [];
if ($q !== '') {
    $filtros['a.detalle'] = ['like', $q];
}
if ($accion !== '') {
    $filtros['a.accion'] = $accion;
}
if ($entidad !== '') {
    $filtros['a.entidad'] = $entidad;
}
if ($usuario) {
    $filtros['a.id_usuario'] = $usuario;
}
/* Ambas fechas pueden venir informadas: se acumulan sin pisarse */
$rangoFechas = [];
if ($desde !== '') {
    $rangoFechas[] = ['>=', $desde . ' 00:00:00'];
}
if ($hasta !== '') {
    $rangoFechas[] = ['<=', $hasta . ' 23:59:59'];
}
if ($rangoFechas) {
    $filtros['a.creado_en'] = $rangoFechas;
}

$join = 'LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario';

$total = Paginador::contar('auditoria a', $filtros, $join);
$paginador = new Paginador($total, $porPagina, $pag);

$registros = Paginador::obtener(
    'auditoria a',
    ['a.id_auditoria', 'a.accion', 'a.entidad', 'a.entidad_id', 'a.detalle', 'a.ip', 'a.creado_en',
     "CONCAT(u.nombres,' ',u.apellidos) AS usuario", 'u.email'],
    $filtros,
    'a.id_auditoria DESC',
    $porPagina,
    $paginador->offset(),
    $join
)->fetchAll();

/* Catálogos para los filtros */
$acciones = array_column(db()->todas(
    "SELECT accion, COUNT(*) AS total FROM auditoria GROUP BY accion ORDER BY accion"
), 'accion');
$entidades = array_column(db()->todas(
    "SELECT entidad, COUNT(*) AS total FROM auditoria GROUP BY entidad ORDER BY entidad"
), 'entidad');
$usuariosFiltro = db()->todas(
    "SELECT DISTINCT a.id_usuario, CONCAT(u.nombres,' ',u.apellidos) AS nombre
       FROM auditoria a JOIN usuarios u ON u.id_usuario = a.id_usuario
      ORDER BY nombre"
);

/* Resumen de actividad por día (últimos 7 días con registro, en orden cronológico) */
$actividad = db()->todas(
    "SELECT DATE(creado_en) AS dia, COUNT(*) AS total
       FROM auditoria
      WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(creado_en)
      ORDER BY dia ASC"
);
$maxActividad = $actividad ? max(array_column($actividad, 'total')) : 1;

$parametros = array_filter([
    'q'            => $q,
    'accion_filtro' => $accion,
    'entidad'      => $entidad,
    'usuario'      => $usuario ?: null,
    'desde'        => $desde,
    'hasta'        => $hasta,
]);

$titulo    = 'Auditoría';
$subtitulo = $total . ' ' . ($total === 1 ? 'evento registrado' : 'eventos registrados');
$seccion   = 'auditoria';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="kpi-grid">
    <div class="kpi">
        <span class="kpi-icon" style="background:var(--brand-50);color:var(--brand)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
            </svg>
        </span>
        <div>
            <b><?= number_format($total, 0, ',', '.') ?></b>
            <small>Eventos totales</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--ok-bg);color:var(--ok)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" />
            </svg>
        </span>
        <div>
            <b><?= count($usuariosFiltro) ?></b>
            <small>Usuarios con actividad</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--warn-bg);color:var(--warn)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
            </svg>
        </span>
        <div>
            <b><?= count(array_filter($acciones, static fn($a) => in_array($a, ['eliminar', 'restablecer_password'], true))) ?></b>
            <small>Tipos de acción sensible</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--info-bg);color:var(--info)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
            </svg>
        </span>
        <div>
            <b><?= $actividad ? e(fecha(end($actividad)['dia'])) : '—' ?></b>
            <small>Último día con actividad</small>
        </div>
    </div>
</div>

<?php if ($actividad): ?>
    <div class="panel mb-3">
        <div class="panel-head">
            <div>
                <h2>Actividad reciente</h2>
                <p>Eventos registrados por día</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="chart" style="height:150px">
                <?php foreach ($actividad as $a): ?>
                    <?php $altura = max(4, (int) round(((int) $a['total'] / $maxActividad) * 100)); ?>
                    <div class="chart-col" title="<?= e(fecha($a['dia']) . ': ' . $a['total'] . ' eventos') ?>">
                        <div class="chart-bar" style="height:<?= $altura ?>%">
                            <span class="val"><?= (int) $a['total'] ?></span>
                        </div>
                        <span class="chart-label"><?= e(date('d/m', strtotime($a['dia']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Bitácora</h2>
            <p>Trazabilidad de acciones en el sistema</p>
        </div>
        <div class="panel-actions no-print">
            <?php if ($parametros): ?>
                <a class="btn btn-ghost btn-sm" href="listar.php">Limpiar filtros</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel-body">
        <form class="toolbar" method="GET" novalidate>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M21 21l-4.35-4.35" />
                </svg>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar en el detalle…">
            </div>

            <select class="input" name="accion_filtro" data-auto-submit>
                <option value="">Todas las acciones</option>
                <?php foreach ($acciones as $a): ?>
                    <option value="<?= e($a) ?>" <?= $accion === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>

            <select class="input" name="entidad" data-auto-submit>
                <option value="">Todas las entidades</option>
                <?php foreach ($entidades as $en): ?>
                    <option value="<?= e($en) ?>" <?= $entidad === $en ? 'selected' : '' ?>><?= e($en) ?></option>
                <?php endforeach; ?>
            </select>

            <select class="input" name="usuario" data-auto-submit>
                <option value="">Todos los usuarios</option>
                <?php foreach ($usuariosFiltro as $uf): ?>
                    <option value="<?= (int) $uf['id_usuario'] ?>" <?= $usuario === (int) $uf['id_usuario'] ? 'selected' : '' ?>>
                        <?= e($uf['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input class="input" type="date" name="desde" value="<?= e($desde) ?>" title="Desde" data-auto-submit>
            <input class="input" type="date" name="hasta" value="<?= e($hasta) ?>" title="Hasta" data-auto-submit>

            <div class="toolbar-right">
                <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
            </div>
        </form>

        <?php if (!$registros): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 12h6m-6 4h6M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z" />
                </svg>
                <h3>Sin registros</h3>
                <p>No hay eventos que coincidan con los filtros aplicados.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Entidad</th>
                            <th>Detalle</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $colorAccion = [
                            'crear'                  => 'green',
                            'actualizar'             => 'blue',
                            'eliminar'               => 'red',
                            'guardar_notas'          => 'blue',
                            'restablecer_password'   => 'amber',
                            'login_exitoso'          => 'green',
                            'login_fallido'          => 'red',
                            'logout'                 => 'gray',
                        ];
                        ?>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td class="nowrap tabular small"><?= e(fecha($r['creado_en'], 'd/m/Y H:i:s')) ?></td>
                                <td>
                                    <?php if ($r['usuario']): ?>
                                        <div class="cell-user">
                                            <span class="avatar"><?= e(iniciales($r['usuario'])) ?></span>
                                            <div>
                                                <b><?= e($r['usuario']) ?></b>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted small">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $colorAccion[$r['accion']] ?? 'gray' ?> no-dot">
                                        <?= e(str_replace('_', ' ', $r['accion'])) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?= e($r['entidad']) ?>
                                    <?php if ($r['entidad_id']): ?>
                                        <span class="muted">#<?= (int) $r['entidad_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small muted" style="max-width:320px"><?= e($r['detalle']) ?></td>
                                <td class="small tabular muted nowrap"><?= e($r['ip']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= $paginador->render($parametros) ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>