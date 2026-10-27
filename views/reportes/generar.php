<?php

/**
 * Centro de reportes: previsualización y exportación.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_rol(1, 2);

$esAdmin = Auth::es_admin();
$idProfesor = $esAdmin ? null : db()->valor(
    "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
    [':u' => Auth::id()]
);
$cursosAsignados = $esAdmin ? [] : array_map(
    'intval',
    array_column(
        db()->todas(
            "SELECT id_curso FROM curso_docentes WHERE id_profesor = :p",
            [':p' => $idProfesor ?: 0]
        ),
        'id_curso'
    )
);
$listaCursos = $esAdmin ? '' : (implode(',', $cursosAsignados) ?: '0');

$periodo = input('periodo') ?: date('Y') . '-1';

$periodos = $esAdmin
    ? db()->todas("SELECT periodo, COUNT(*) AS total FROM matriculas GROUP BY periodo ORDER BY periodo DESC LIMIT 12")
    : db()->todas(
        "SELECT m.periodo, COUNT(*) AS total
           FROM matriculas m
           JOIN curso_docentes cd ON cd.id_curso = m.id_curso
          WHERE cd.id_profesor = :p
          GROUP BY m.periodo
          ORDER BY m.periodo DESC
          LIMIT 12",
        [':p' => $idProfesor ?: 0]
    );

if (!$periodos) {
    $periodos = [['periodo' => date('Y') . '-1', 'total' => 0]];
}

$cursos = $esAdmin
    ? db()->todas("SELECT id_curso, nombre FROM cursos WHERE activo = 1 ORDER BY nombre")
    : db()->todas(
        "SELECT c.id_curso, c.nombre
           FROM cursos c
           JOIN curso_docentes cd ON cd.id_curso = c.id_curso
          WHERE c.activo = 1 AND cd.id_profesor = :p
          ORDER BY c.nombre",
        [':p' => $idProfesor ?: 0]
    );

/* ---------------- Indicadores del periodo ---------------- */

$kpis = ($esAdmin
    ? db()->fila(
        "SELECT
            (SELECT COUNT(DISTINCT m.id_estudiante) FROM matriculas m WHERE m.periodo = :p1) AS estudiantes,
            (SELECT COUNT(*) FROM matriculas m WHERE m.periodo = :p2) AS matriculas,
            (SELECT COUNT(*) FROM cursos WHERE activo = 1) AS cursos,
            (SELECT ROUND(AVG(n.valor), 2) FROM notas n WHERE n.periodo = :p3) AS promedio",
        [':p1' => $periodo, ':p2' => $periodo, ':p3' => $periodo]
    )
    : db()->fila(
        "SELECT
            (SELECT COUNT(DISTINCT m.id_estudiante) FROM matriculas m
              WHERE m.periodo = :p1 AND m.id_curso IN ({$listaCursos})) AS estudiantes,
            (SELECT COUNT(*) FROM matriculas m
              WHERE m.periodo = :p2 AND m.id_curso IN ({$listaCursos})) AS matriculas,
            (SELECT COUNT(*) FROM cursos c
              WHERE c.activo = 1 AND c.id_curso IN ({$listaCursos})) AS cursos,
            (SELECT ROUND(AVG(n.valor), 2) FROM notas n
              WHERE n.periodo = :p3 AND n.id_curso IN ({$listaCursos})) AS promedio",
        [':p1' => $periodo, ':p2' => $periodo, ':p3' => $periodo]
    )) ?: ['estudiantes' => 0, 'matriculas' => 0, 'cursos' => 0, 'promedio' => null];

$aprobacion = (int) db()->valor(
    "SELECT COALESCE(SUM(m.estado = 'aprobado'), 0) FROM matriculas m WHERE m.periodo = :p"
        . ($esAdmin ? '' : " AND m.id_curso IN ({$listaCursos})"),
    [':p' => $periodo],
    0
);

$porCurso = db()->todas(
    "SELECT c.nombre AS curso, c.creditos, COUNT(m.id_matricula) AS inscritos,
            ROUND(AVG(n.promedio), 2) AS promedio
       FROM cursos c
       " . ($esAdmin ? '' : "JOIN curso_docentes cd ON cd.id_curso = c.id_curso
       ") . "
       LEFT JOIN matriculas m ON m.id_curso = c.id_curso AND m.periodo = :p1
       LEFT JOIN (
            SELECT id_curso, AVG(valor) AS promedio FROM notas
             WHERE periodo = :p2" . ($esAdmin ? '' : " AND id_curso IN ({$listaCursos})") . "
             GROUP BY id_curso
       ) n ON n.id_curso = c.id_curso
      WHERE c.activo = 1" . ($esAdmin ? '' : " AND cd.id_profesor = " . (int) ($idProfesor ?: 0)) . "
      GROUP BY c.id_curso
      ORDER BY inscritos DESC, c.nombre",
    [':p1' => $periodo, ':p2' => $periodo]
);

$topEstudiantes = db()->todas(
    "SELECT v.estudiante, e.matricula, COUNT(DISTINCT v.id_curso) AS cursos, ROUND(AVG(v.promedio), 2) AS promedio
       FROM v_promedio_por_curso v
       JOIN estudiantes e ON e.id_estudiante = v.id_estudiante
      WHERE v.periodo = :p
      " . ($esAdmin ? '' : "AND v.id_curso IN ({$listaCursos})") . "
      GROUP BY v.id_estudiante
      ORDER BY promedio DESC
      LIMIT 8",
    [':p' => $periodo]
);

$maxInscritos = $porCurso ? max(array_map(static fn($c) => (int) $c['inscritos'], $porCurso)) : 1;

$titulo    = 'Reportes';
$subtitulo = 'Indicadores y exportaciones del periodo ' . $periodo;
$seccion   = 'reportes';
$ancho     = '';

require_once APP_ROOT . '/layout/header.php';
?>

<form class="toolbar" method="GET" novalidate>
    <select class="input" name="periodo" data-auto-submit>
        <?php foreach ($periodos as $p): ?>
            <option value="<?= e($p['periodo']) ?>" <?= $periodo === $p['periodo'] ? 'selected' : '' ?>>
                <?= e($p['periodo']) ?> (<?= (int) $p['total'] ?> matrículas)
            </option>
        <?php endforeach; ?>
    </select>
    <span class="muted small nowrap">Datos calculados sobre matrículas y notas registradas</span>
</form>

<div class="kpi-grid">
    <div class="kpi">
        <span class="kpi-icon" style="background:var(--brand-50);color:var(--brand)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
            </svg>
        </span>
        <div>
            <b><?= (int) $kpis['estudiantes'] ?></b>
            <small>Estudiantes inscritos</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--ok-bg);color:var(--ok)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
            </svg>
        </span>
        <div>
            <b><?= (int) $kpis['matriculas'] ?></b>
            <small>Matrículas del periodo</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--warn-bg);color:var(--warn)">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 6.25V12m0 3h.01M3 4h18l-7 16-7-16z" />
            </svg>
        </span>
        <div>
            <b><?= $kpis['promedio'] !== null ? e(number_format((float) $kpis['promedio'], 1)) : '—' ?></b>
            <small>Promedio general</small>
        </div>
    </div>

    <div class="kpi">
        <span class="kpi-icon" style="background:var(--info-bg);color:var(--info)">
            <path d=""></path>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
            </svg>
        </span>
        <div>
            <b><?= $kpis['matriculas'] > 0 ? round(($aprobacion / (int) $kpis['matriculas']) * 100) : 0 ?>%</b>
            <small>Tasa de aprobación</small>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Inscritos y rendimiento por curso</h2>
                <p>Periodo <?= e($periodo) ?></p>
            </div>
        </div>
        <div class="panel-body">
            <?php if (!$porCurso): ?>
                <div class="empty">
                    <p>No hay cursos activos para mostrar.</p>
                </div>
            <?php else: ?>
                <div class="form-grid" style="gap:15px">
                    <?php foreach ($porCurso as $c): ?>
                        <?php
                        $pct = $maxInscritos > 0 ? round(((int) $c['inscritos'] / $maxInscritos) * 100) : 0;
                        $prom = $c['promedio'] !== null ? (float) $c['promedio'] : null;
                        ?>
                        <div>
                            <div class="flex-between" style="margin-bottom:6px">
                                <span style="font-size:.88rem;font-weight:600">
                                    <?= e($c['curso']) ?>
                                    <small class="muted">(<?= (int) $c['creditos'] ?> cr)</small>
                                </span>
                                <span class="flex gap-sm nowrap" style="font-size:.82rem">
                                    <b class="tabular"><?= (int) $c['inscritos'] ?> inscritos</b>
                                    <?php if ($prom !== null): ?>
                                        <span class="badge badge-<?= $prom >= 70 ? 'green' : ($prom >= 50 ? 'amber' : 'red') ?> no-dot">
                                            <?= e(number_format($prom, 1)) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="muted">sin notas</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="bar <?= $prom !== null && $prom < 50 ? 'danger' : '' ?>">
                                <span data-bar="<?= $pct ?>" style="width:0"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Mejor promedio</h2>
                <p>Estudiantes destacados del periodo</p>
            </div>
        </div>
        <div class="panel-body">
            <?php if (!$topEstudiantes): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 15l-4 4v-4h-1a7 7 0 01-7-7V4h4l1 5h6l3-3 3 3-3 3v3a7 7 0 01-7 4z" />
                    </svg>
                    <h3>Sin datos de rendimiento</h3>
                    <p>Registra notas en el periodo para ver este ranking.</p>
                    <a class="btn btn-primary" href="<?= e(base_url('views/notas/listar.php')) ?>">Ir a notas</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Estudiante</th>
                                <th class="num">Cursos</th>
                                <th class="num">Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topEstudiantes as $i => $t): ?>
                                <tr>
                                    <td class="muted tabular"><?= $i + 1 ?></td>
                                    <td>
                                        <div class="cell-user">
                                            <span class="avatar"><?= e(iniciales($t['estudiante'])) ?></span>
                                            <div>
                                                <b><?= e($t['estudiante']) ?></b>
                                                <small class="muted tabular"><?= e($t['matricula']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="num tabular"><?= (int) $t['cursos'] ?></td>
                                    <td class="num">
                                        <b class="tabular" style="color:<?= (float) $t['promedio'] >= 70 ? 'var(--ok)' : 'var(--warn)' ?>">
                                            <?= e(number_format((float) $t['promedio'], 1)) ?>
                                        </b>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="panel mt-2">
    <div class="panel-head">
        <div>
            <h2>Exportar información</h2>
            <p>Archivos CSV compatibles con Excel</p>
        </div>
    </div>
    <div class="panel-body">
        <div class="grid-3">
            <?php
            $exportables = [
                'estudiantes' => ['Estudiantes', 'Listado con especialidad y estado', 'M17 21l4.4-4.4M9 11l3 3L22 4'],
                'profesores'  => ['Docentes', 'Datos de contacto y titulación', 'M4 19V5a2 2 0 012-2h9l5 5v11a2 2 0 01-2 2H6a2 2 0 01-2-2z'],
                'cursos'      => ['Cursos', 'Plan de estudios activo', 'M4 19V5a2 2 0 012-2h9l5 5v11a2 2 0 01-2 2H6a2 2 0 01-2-2zM9 9h6M9 13h6'],
                'matriculas'  => ['Matrículas', 'Inscripciones por periodo ' . $periodo, 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11'],
                'notas'       => ['Notas', 'Calificaciones del periodo ' . $periodo, 'M9 19V5l12-2v14l-12 2zM9 19a2 2 0 11-4 0 2 2 0 014 0zm12-2a2 2 0 11-4 0 2 2 0 014 0z'],
            ];
            ?>
            <?php foreach ($exportables as $clave => [$nombre, $desc, $icono]): ?>
                <a class="mod-card" style="text-decoration:none"
                    href="<?= e(base_url('views/reportes/exportar.php?tipo=' . $clave)) ?>">
                    <span class="mod-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($icono) ?>" /></svg>
                    </span>
                    <span class="mod-meta">
                        <h3><?= e($nombre) ?></h3>
                        <p><?= e($desc) ?></p>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="divider"></div>

        <div class="flex gap-sm" style="flex-wrap:wrap">
            <a class="btn btn-outline btn-sm" href="<?= e(base_url('views/estudiantes/reportes/promedios.php?periodo=' . urlencode($periodo))) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 3v18h18M7 15l4-4 3 3 5-6" />
                </svg>
                Ver promedios detallados
            </a>
            <button class="btn btn-outline btn-sm" type="button" onclick="window.print()">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z" />
                </svg>
                Imprimir / guardar PDF
            </button>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>