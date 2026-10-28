<?php

/**
 * Reporte detallado de promedios por estudiante y curso.
 */

require_once __DIR__ . '/../../../core/bootstrap.php';

Auth::requerir_permiso('reportes.ver');

$periodo = input('periodo') ?: date('Y') . '-1';
$q       = input('q');
$minimo  = input('minimo');

$verTodo = !Auth::es_profesor();
$idProfesor = $verTodo ? null : db()->valor(
    "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
    [':u' => Auth::id()]
);
$cursosAsignados = $verTodo ? [] : array_map(
    'intval',
    array_column(
        db()->todas(
            "SELECT id_curso FROM curso_docentes WHERE id_profesor = :p",
            [':p' => $idProfesor ?: 0]
        ),
        'id_curso'
    )
);
$listaCursos = $verTodo ? '' : (implode(',', $cursosAsignados) ?: '0');

$periodos = $verTodo
    ? db()->todas("SELECT DISTINCT periodo FROM notas ORDER BY periodo DESC")
    : db()->todas(
        "SELECT DISTINCT n.periodo
           FROM notas n
           JOIN curso_docentes cd ON cd.id_curso = n.id_curso
          WHERE cd.id_profesor = :p
          ORDER BY n.periodo DESC",
        [':p' => $idProfesor ?: 0]
    );
if (!$periodos) {
    $periodos = [['periodo' => $periodo]];
}

$filtros = ['v.periodo' => $periodo];
if (!$verTodo) {
    $filtros['v.id_curso'] = ['in', $cursosAsignados ?: [0]];
}
if ($q !== '') {
    $filtros['v.estudiante'] = ['like', $q];
    $filtros['v.curso']      = ['like', $q];
}
if ($minimo !== '' && is_numeric($minimo)) {
    $filtros['v.promedio'] = ['>=', (float) $minimo];
}

$total = Paginador::contar('v_promedio_por_curso v', $filtros);
$paginador = new Paginador($total, 20, max(1, input_int('pag', 1)));

$registros = Paginador::obtener(
    'v_promedio_por_curso v',
    ['v.id_estudiante', 'v.estudiante', 'v.id_curso', 'v.curso', 'v.promedio',
     'v.evaluaciones', 'v.aprobadas', 'v.reprobadas'],
    $filtros,
    'v.promedio DESC, v.estudiante ASC',
    20,
    $paginador->offset()
)->fetchAll();

/* Totales del periodo */
$resumen = db()->fila(
    "SELECT ROUND(AVG(promedio), 2) AS promedio, MAX(promedio) AS maximo, MIN(promedio) AS minimo,
            COUNT(DISTINCT id_estudiante) AS estudiantes
       FROM v_promedio_por_curso WHERE periodo = :p"
        . ($verTodo ? '' : " AND id_curso IN ({$listaCursos})"),
    [':p' => $periodo]
) ?: ['promedio' => null, 'maximo' => null, 'minimo' => null, 'estudiantes' => 0];

$aprobados = (int) db()->valor(
    "SELECT COUNT(*) FROM v_promedio_por_curso WHERE periodo = :p AND promedio >= 70"
        . ($verTodo ? '' : " AND id_curso IN ({$listaCursos})"),
    [':p' => $periodo],
    0
);

$conNota = $total;
$tasaAprobacion = $conNota > 0 ? round(($aprobados / $conNota) * 100) : 0;

$parametros = array_filter(['periodo' => $periodo, 'q' => $q, 'minimo' => $minimo]);

$titulo    = 'Promedios';
$subtitulo = 'Detalle de rendimiento académico · ' . $periodo;
$seccion   = 'promedios';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="kpi-grid">
    <div class="kpi">
        <span class="kpi-icon" style="background:var(--brand-50);color:var(--brand)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 3v18h18M7 15l4-4 3 3 5-6" />
            </svg>
        </span>
        <div>
            <b><?= $resumen['promedio'] !== null ? e(number_format((float) $resumen['promedio'], 1)) : '—' ?></b>
            <small>Promedio general</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--ok-bg);color:var(--ok)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 15l-4 4v-4h-1a7 7 0 01-7-7V4h4l1 5h6l3-3 3 3-3 3v3a7 7 0 01-7 4z" />
            </svg>
        </span>
        <div>
            <b><?= $tasaAprobacion ?>%</b>
            <small>Cursos aprobados</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--info-bg);color:var(--info)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" />
            </svg>
        </span>
        <div>
            <b><?= (int) $resumen['estudiantes'] ?></b>
            <small>Estudiantes con notas</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--warn-bg);color:var(--warn)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM17 5h2a2 2 0 012 2v1a2 2 0 01-2 2M7 5H5a2 2 0 00-2 2v1a2 2 0 002 2" />
            </svg>
        </span>
        <div>
            <b>
                <?= $resumen['maximo'] !== null ? e(number_format((float) $resumen['maximo'], 0)) : '—' ?>
                /
                <?= $resumen['minimo'] !== null ? e(number_format((float) $resumen['minimo'], 0)) : '—' ?>
            </b>
            <small>Máx / mín del periodo</small>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Detalle por curso</h2>
            <p><?= $total . ' registros' ?></p>
        </div>
        <div class="panel-actions no-print">
            <a class="btn btn-outline btn-sm"
                href="<?= e(base_url('views/reportes/exportar.php?tipo=notas&periodo=' . urlencode($periodo))) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
                </svg>
                Exportar CSV
            </a>
            <button class="btn btn-outline btn-sm" type="button" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <div class="panel-body">
        <form class="toolbar" method="GET" novalidate>
            <select class="input" name="periodo" data-auto-submit>
                <?php foreach ($periodos as $p): ?>
                    <option value="<?= e($p['periodo']) ?>" <?= $periodo === $p['periodo'] ? 'selected' : '' ?>>
                        <?= e($p['periodo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M21 21l-4.35-4.35" />
                </svg>
                <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Estudiante o curso…">
            </div>

            <select class="input" name="minimo" data-auto-submit>
                <option value="">Cualquier promedio</option>
                <option value="70" <?= $minimo === '70' ? 'selected' : '' ?>>Solo aprobados (≥ 70)</option>
                <option value="50" <?= $minimo === '50' ? 'selected' : '' ?>>En riesgo (≥ 50)</option>
            </select>

            <div class="toolbar-right">
                <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
                <?php if ($q !== '' || $minimo !== ''): ?>
                    <a class="btn btn-ghost btn-sm" href="promedios.php?periodo=<?= e(urlencode($periodo)) ?>">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$registros): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 3v18h18M7 15l4-4 3 3 5-6" />
                </svg>
                <h3>Sin promedios para mostrar</h3>
                <p>No hay notas registradas en el periodo seleccionado o no coinciden con los filtros.</p>
                <?php if (Auth::puede('notas.ver')): ?>
                    <a class="btn btn-primary" href="<?= e(base_url('views/notas/listar.php')) ?>">Registrar notas</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Curso</th>
                            <th class="num">Evaluaciones</th>
                            <th class="num">Aprobadas</th>
                            <th class="num">Reprobadas</th>
                            <th class="num">Promedio</th>
                            <th class="num">Desempeño</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $r): ?>
                            <?php
                            $prom = (float) $r['promedio'];
                            $color = $prom >= 70 ? 'green' : ($prom >= 50 ? 'amber' : 'red');
                            $pct = max(2, min(100, round($prom)));
                            ?>
                            <tr>
                                <td><?= e($r['estudiante']) ?></td>
                                <td><?= e($r['curso']) ?></td>
                                <td class="num tabular"><?= (int) $r['evaluaciones'] ?></td>
                                <td class="num tabular" style="color:var(--ok)"><?= (int) $r['aprobadas'] ?></td>
                                <td class="num tabular" style="color:var(--danger)"><?= (int) $r['reprobadas'] ?></td>
                                <td class="num">
                                    <b class="tabular" style="font-size:.95rem"><?= e(number_format($prom, 1)) ?></b>
                                </td>
                                <td class="num" style="min-width:120px">
                                    <div class="bar <?= $color === 'green' ? 'ok' : ($color === 'amber' ? 'warn' : 'danger') ?>"
                                        style="min-width:90px">
                                        <span data-bar="<?= $pct ?>" style="width:0"></span>
                                    </div>
                                </td>
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