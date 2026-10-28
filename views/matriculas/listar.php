<?php

/**
 * Listado de matrículas.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('matriculas.ver');

if (es_post()) {
    exigir_post();

    if (input('accion') === 'eliminar') {
        Auth::requerir_permiso('matriculas.eliminar');
        $id = input_int('id');
        try {
            db()->conectar()->prepare("DELETE FROM matriculas WHERE id_matricula = :id")->execute([':id' => $id]);
            Auth::registrar('eliminar', 'matriculas', $id, 'Matrícula eliminada');
            flash_set('success', 'Matrícula eliminada.');
        } catch (Throwable $e) {
            flash_set('error', 'No se pudo eliminar la matrícula.');
        }
        redirigir('listar.php?' . http_build_query([
            'q' => input('f_q'), 'periodo' => input('f_periodo'),
        ]));
    }

    if (input('accion') === 'estado') {
        Auth::requerir_permiso('matriculas.editar');
        $id    = input_int('id');
        $estado = input('estado');
        if (in_array($estado, ['matriculado', 'aprobado', 'reprobado', 'retirado'], true)) {
            db()->query("UPDATE matriculas SET estado = :e WHERE id_matricula = :id", [':e' => $estado, ':id' => $id]);
            Auth::registrar('actualizar', 'matriculas', $id, "Estado cambiado a {$estado}");
            flash_set('success', "Matrícula marcada como {$estado}.");
        }
        redirigir('listar.php?' . http_build_query([
            'q' => input('f_q'), 'periodo' => input('f_periodo'),
        ]));
    }
}

$q       = input('q');
$periodo = input('periodo');
$estado  = input('estado');
$pag     = max(1, input_int('pag', 1));
$porPagina = 12;

$filtros = [];
if ($q !== '') {
    $filtros["CONCAT(u.nombres,' ',u.apellidos)"] = ['like', $q];
    $filtros['c.nombre']                          = ['like', $q];
}
if ($periodo !== '') {
    $filtros['m.periodo'] = $periodo;
}
if ($estado !== '') {
    $filtros['m.estado'] = $estado;
}

$join = 'JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
         JOIN usuarios u ON u.id_usuario = e.id_usuario
         JOIN cursos c ON c.id_curso = m.id_curso';

$soloDocente = Auth::es_docente();
if ($soloDocente) {
    $idProfesor = db()->valor(
        "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
        [':u' => Auth::id()]
    );
    $join .= ' JOIN curso_docentes cd ON cd.id_curso = m.id_curso';
    $filtros['cd.id_profesor'] = $idProfesor ?: 0;
}

$total = Paginador::contar('matriculas m', $filtros, $join);
$paginador = new Paginador($total, $porPagina, $pag);

$columnas = [
    'm.id_matricula',
    'CONCAT(u.nombres," ",u.apellidos) AS estudiante',
    'c.nombre AS curso',
    'm.periodo',
    'm.estado',
    'm.fecha_matricula',
    'e.id_usuario',
];

$orden    = 'm.fecha_matricula DESC';

$registros = Paginador::obtener('matriculas m', $columnas, $filtros, $orden, $porPagina, $paginador->offset(), $join)->fetchAll();

$periodos = !$soloDocente
    ? db()->todas("SELECT DISTINCT periodo FROM matriculas ORDER BY periodo DESC")
    : db()->todas(
        "SELECT DISTINCT m.periodo
           FROM matriculas m
           JOIN curso_docentes cd ON cd.id_curso = m.id_curso
          WHERE cd.id_profesor = :p
          ORDER BY m.periodo DESC",
        [':p' => $idProfesor ?: 0]
    );

$paramsFiltros = array_filter(['q' => $q, 'periodo' => $periodo, 'estado' => $estado]);

$titulo    = 'Matrículas';
$subtitulo = $total . ' ' . ($total === 1 ? 'inscripción' : 'inscripciones') . ' registrada' . ($total === 1 ? '' : 's');
$seccion   = 'matriculas';
$ancho     = 'content-narrow';

$puedeCrear    = Auth::puede('matriculas.crear');
$puedeEditar   = Auth::puede('matriculas.editar');
$puedeEliminar = Auth::puede('matriculas.eliminar');
$puedeExportar = Auth::puede('reportes.exportar');
$puedeGestionar = $puedeEditar || $puedeEliminar;

$accionesExtra = $puedeCrear
    ? '<a class="btn btn-primary btn-sm" href="crear.php">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Nueva matrícula</a>'
    : '';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Inscripciones</h2>
            <p><?= $soloDocente
                ? 'Inscripciones de tus cursos asignados · solo lectura'
                : 'Estudiantes inscritos por curso y periodo' ?></p>
        </div>
        <?php if ($puedeExportar): ?>
        <div class="panel-actions no-print">
            <a class="btn btn-outline btn-sm"
                href="<?= e(base_url('views/reportes/exportar.php?tipo=matriculas' . ($paramsFiltros ? '&' . http_build_query($paramsFiltros) : ''))) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
                </svg>
                Exportar CSV
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel-body">
        <form class="toolbar" method="GET" novalidate>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M21 21l-4.35-4.35" />
                </svg>
                <input class="input" type="search" name="q" value="<?= e($q) ?>"
                    placeholder="Estudiante o curso…">
            </div>

            <select class="input" name="periodo" data-auto-submit>
                <option value="">Todos los periodos</option>
                <?php foreach ($periodos as $p): ?>
                    <option value="<?= e($p['periodo']) ?>" <?= $periodo === $p['periodo'] ? 'selected' : '' ?>>
                        <?= e($p['periodo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="input" name="estado" data-auto-submit>
                <option value="">Todos los estados</option>
                <option value="matriculado" <?= $estado === 'matriculado' ? 'selected' : '' ?>>Matriculado</option>
                <option value="aprobado" <?= $estado === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                <option value="reprobado" <?= $estado === 'reprobado' ? 'selected' : '' ?>>Reprobado</option>
                <option value="retirado" <?= $estado === 'retirado' ? 'selected' : '' ?>>Retirado</option>
            </select>

            <div class="toolbar-right">
                <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
                <?php if ($paramsFiltros): ?>
                    <a class="btn btn-ghost btn-sm" href="listar.php">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$registros): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                </svg>
                <h3><?= $paramsFiltros ? 'Sin resultados' : 'Sin matrículas' ?></h3>
                <p>
                    <?= $paramsFiltros
                        ? 'Ninguna inscripción coincide con los filtros aplicados.'
                        : 'Inscribe al primer estudiante en un curso para empezar.' ?>
                </p>
                <?php if ($paramsFiltros): ?>
                    <a class="btn btn-outline" href="listar.php">Limpiar filtros</a>
                <?php elseif ($puedeCrear): ?>
                    <a class="btn btn-primary" href="crear.php">Crear matrícula</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Curso</th>
                            <th>Periodo</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th class="no-print" style="width:1%">Constancia</th>
                            <?php if ($puedeGestionar): ?>
                                <th class="num no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $badges = [
                            'matriculado' => 'blue', 'aprobado' => 'green',
                            'reprobado' => 'red', 'retirado' => 'gray',
                        ];
                        ?>
                        <?php foreach ($registros as $m): ?>
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <span class="avatar"><?= e(iniciales($m['estudiante'])) ?></span>
                                        <div>
                                            <b><?= e($m['estudiante']) ?></b>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($m['curso']) ?></td>
                                <td><span class="badge badge-gray no-dot"><?= e($m['periodo']) ?></span></td>
                                <td class="tabular muted"><?= e(fecha($m['fecha_matricula'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $badges[$m['estado']] ?? 'gray' ?>">
                                        <?= e(ucfirst($m['estado'])) ?>
                                    </span>
                                </td>
                                <td class="no-print">
                                    <a class="btn btn-outline btn-sm" style="padding:6px 9px"
                                        href="constancia.php?id=<?= (int) $m['id_matricula'] ?>"
                                        title="Ver constancia">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                            <path d="M14 2v6h6M16 13H8m0 4h8" />
                                        </svg>
                                    </a>
                                </td>
                                <?php if ($puedeGestionar): ?>
                                    <td class="num no-print">
                                        <div class="flex gap-sm" style="justify-content:flex-end;align-items:center">
                                            <?php if ($puedeEditar): ?>
                                            <form method="POST" class="no-print flex gap-sm">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="accion" value="estado">
                                                <input type="hidden" name="id" value="<?= (int) $m['id_matricula'] ?>">
                                                <input type="hidden" name="f_q" value="<?= e($q) ?>">
                                                <input type="hidden" name="f_periodo" value="<?= e($periodo) ?>">
                                                <select name="estado" class="input" style="padding:5px 28px 5px 9px;min-width:112px;font-size:.78rem">
                                                    <?php foreach (['matriculado', 'aprobado', 'reprobado', 'retirado'] as $op): ?>
                                                        <option value="<?= $op ?>" <?= $m['estado'] === $op ? 'selected' : '' ?>>
                                                            <?= e(ucfirst($op)) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-outline btn-sm" type="submit">OK</button>
                                            </form>
                                            <?php endif; ?>

                                            <?php if ($puedeEliminar): ?>
                                            <form method="POST" class="no-print"
                                                data-confirmar="¿Eliminar la matrícula de <?= e($m['estudiante']) ?> en «<?= e($m['curso']) ?>»?">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= (int) $m['id_matricula'] ?>">
                                                <input type="hidden" name="f_q" value="<?= e($q) ?>">
                                                <input type="hidden" name="f_periodo" value="<?= e($periodo) ?>">
                                                <button class="btn btn-ghost btn-sm" type="submit" title="Eliminar"
                                                    style="color:var(--danger)">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                                    </svg>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= $paginador->render($paramsFiltros) ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>