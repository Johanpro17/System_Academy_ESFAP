<?php

/**
 * Panel del profesor: resumen de sus cursos, horario y evaluaciones.
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerir_rol(2);
refrescar_usuario();

$idProfesor = db()->valor(
    "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
    [':u' => Auth::id()]
);

/* Periodo de trabajo: el más reciente entre horarios y matrículas del profesor. */
$periodoActual = $idProfesor
    ? (string) db()->valor(
        "SELECT periodo FROM (
            SELECT periodo FROM horarios
             WHERE id_profesor = :p AND periodo IS NOT NULL
            UNION
            SELECT m.periodo
              FROM matriculas m
              JOIN curso_docentes cd ON cd.id_curso = m.id_curso
             WHERE cd.id_profesor = :p
         ) t
         ORDER BY periodo DESC
         LIMIT 1",
        [':p' => $idProfesor],
        ''
    )
    : '';

if ($periodoActual === '') {
    $periodoActual = date('Y') . '-1';
}

$cursos            = [];
$horarios          = [];
$evaluaciones      = [];
$promedioGeneral   = null;
$totalEstudiantes  = 0;
$totalEvaluaciones = 0;

if ($idProfesor) {
    $cursos = db()->todas(
        "SELECT c.id_curso, c.codigo, c.nombre, c.creditos,
                COUNT(DISTINCT m.id_estudiante) AS estudiantes,
                COUNT(DISTINCT ev.id_evaluacion) AS evaluaciones,
                n.promedio, n.notas
           FROM curso_docentes cd
           JOIN cursos c ON c.id_curso = cd.id_curso
           LEFT JOIN matriculas m ON m.id_curso = c.id_curso AND m.periodo = :per
           LEFT JOIN evaluaciones ev ON ev.id_curso = c.id_curso
           LEFT JOIN (
                SELECT id_curso, ROUND(AVG(valor), 1) AS promedio, COUNT(*) AS notas
                  FROM notas
                 WHERE periodo = :per
                 GROUP BY id_curso
           ) n ON n.id_curso = c.id_curso
          WHERE cd.id_profesor = :p
          GROUP BY c.id_curso, c.codigo, c.nombre, c.creditos, n.promedio, n.notas
          ORDER BY c.nombre",
        [':per' => $periodoActual, ':p' => $idProfesor]
    );

    $totalEstudiantes = (int) db()->valor(
        "SELECT COUNT(DISTINCT m.id_estudiante)
           FROM matriculas m
           JOIN curso_docentes cd ON cd.id_curso = m.id_curso
          WHERE cd.id_profesor = :p AND m.periodo = :per",
        [':p' => $idProfesor, ':per' => $periodoActual],
        0
    );

    $promedioGeneral = db()->valor(
        "SELECT ROUND(AVG(n.valor), 1)
           FROM notas n
           JOIN curso_docentes cd ON cd.id_curso = n.id_curso
          WHERE cd.id_profesor = :p AND n.periodo = :per",
        [':p' => $idProfesor, ':per' => $periodoActual]
    );

    $horarios = db()->todas(
        "SELECT h.dia_semana, h.hora_inicio, h.hora_fin, h.aula,
                c.codigo, c.nombre
           FROM horarios h
           JOIN cursos c ON c.id_curso = h.id_curso
          WHERE h.id_profesor = :p
            AND (h.periodo = :per OR h.periodo IS NULL)",
        [':p' => $idProfesor, ':per' => $periodoActual]
    );

    $evaluaciones = db()->todas(
        "SELECT ev.nombre, ev.fecha, ev.peso, c.nombre AS curso, c.codigo
           FROM evaluaciones ev
           JOIN cursos c ON c.id_curso = ev.id_curso
           JOIN curso_docentes cd ON cd.id_curso = ev.id_curso
          WHERE cd.id_profesor = :p
          ORDER BY CASE WHEN ev.fecha >= CURDATE() THEN 0 ELSE 1 END,
                   CASE WHEN ev.fecha >= CURDATE() THEN ev.fecha END ASC,
                   ev.fecha DESC
          LIMIT 5",
        [':p' => $idProfesor]
    );
}

$totalEvaluaciones = array_sum(array_map(
    static fn(array $curso): int => (int) $curso['evaluaciones'],
    $cursos
));

/* Orden y agrupación del horario: la base puede guardar los días sin tilde. */
$ordenDias = [
    'Lunes'     => 1,
    'Martes'    => 2,
    'Miercoles' => 3,
    'Miércoles' => 3,
    'Jueves'    => 4,
    'Viernes'   => 5,
    'Sabado'    => 6,
    'Sábado'    => 6,
    'Domingo'   => 7,
];

$normalizarDia = static fn(string $dia): string => match ($dia) {
    'Miercoles' => 'Miércoles',
    'Sabado'    => 'Sábado',
    default     => $dia,
};

usort($horarios, static function (array $a, array $b) use ($ordenDias): int {
    $ordenA = $ordenDias[$a['dia_semana']] ?? 9;
    $ordenB = $ordenDias[$b['dia_semana']] ?? 9;

    return $ordenA <=> $ordenB
        ?: strcmp((string) $a['hora_inicio'], (string) $b['hora_inicio']);
});

$horariosPorDia = [];
foreach ($horarios as $bloque) {
    $horariosPorDia[$normalizarDia((string) $bloque['dia_semana'])][] = $bloque;
}

$diasEnEspanol = [
    'Sunday'    => 'Domingo',
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Sábado',
];
$hoy = $diasEnEspanol[date('l')] ?? '';

$mesesCortos = [
    1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
    7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
];

$maxEstudiantes = max(
    1,
    ...array_map(
        static fn(array $curso): int => (int) $curso['estudiantes'],
        $cursos ?: [['estudiantes' => 1]]
    )
);

$hoyISO = date('Y-m-d');

$titulo    = 'Panel del profesor';
$subtitulo = 'Resumen de tus cursos y grupos asignados';
$seccion   = 'dashboard';

require_once APP_ROOT . '/layout/header.php';
?>

<section class="dashboard-welcome profe-welcome" aria-labelledby="profe-bienvenida">
    <div class="dashboard-welcome-copy">
        <span class="eyebrow">Resumen docente</span>
        <h2 id="profe-bienvenida">Hola, <?= e(Auth::nombre()) ?></h2>
        <p>
            Este es el resumen de tus cursos y tu actividad docente en el periodo
            <b><?= e($periodoActual) ?></b>.
        </p>
    </div>
    <div class="dashboard-quick-actions" aria-label="Accesos rápidos">
        <a class="btn btn-primary" href="<?= e(base_url('views/notas/listar.php')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.5 15.5l-4 1 1-4 9-9z" />
            </svg>
            Registrar notas
        </a>
        <a class="btn btn-outline" href="<?= e(base_url('views/horarios/ver.php')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 6v6l4 2M12 22a10 10 0 100-20 10 10 0 000 20z" />
            </svg>
            Mi horario
        </a>
        <a class="btn btn-outline" href="<?= e(base_url('views/reportes/generar.php')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 20V10m5 10V4m5 16v-7m5 7V8" />
            </svg>
            Reportes
        </a>
    </div>
</section>

<div class="kpi-grid">
    <div class="kpi" data-animar>
        <span class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" />
            </svg>
        </span>
        <div>
            <div class="kpi-value"><?= count($cursos) ?></div>
            <div class="kpi-label">Cursos asignados</div>
            <div class="kpi-delta" style="color:var(--text-mute)">Periodo <?= e($periodoActual) ?></div>
        </div>
    </div>

    <div class="kpi" data-animar data-animar-delay="1">
        <span class="kpi-icon ok">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
            </svg>
        </span>
        <div>
            <div class="kpi-value"><?= number_format($totalEstudiantes, 0, ',', '.') ?></div>
            <div class="kpi-label">Estudiantes inscritos</div>
            <div class="kpi-delta" style="color:var(--text-mute)">En tus cursos</div>
        </div>
    </div>

    <div class="kpi" data-animar data-animar-delay="2">
        <span class="kpi-icon gold">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-1 8l2 2 4-4" />
            </svg>
        </span>
        <div>
            <div class="kpi-value"><?= $totalEvaluaciones ?></div>
            <div class="kpi-label">Evaluaciones</div>
            <div class="kpi-delta" style="color:var(--text-mute)">Registradas en tus cursos</div>
        </div>
    </div>

    <div class="kpi" data-animar data-animar-delay="3">
        <span class="kpi-icon warn">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 20V10m5 10V4m5 16v-7m5 7V8" />
            </svg>
        </span>
        <div>
            <div class="kpi-value">
                <?= $promedioGeneral !== null
                    ? number_format((float) $promedioGeneral, 1, ',', '.')
                    : '—' ?>
            </div>
            <div class="kpi-label">Promedio general</div>
            <div class="kpi-delta" style="color:var(--text-mute)">
                <?= $promedioGeneral !== null ? 'Sobre 100 puntos' : 'Sin notas registradas' ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$cursos): ?>
    <div class="panel">
        <div class="empty">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" />
            </svg>
            <h3>No tienes cursos asignados</h3>
            <p>Solicita al administrador que vincule tus cursos a tu cuenta de profesor.</p>
        </div>
    </div>
<?php else: ?>

<div class="profe-top">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Mis cursos</h2>
                <p><?= count($cursos) ?> curso(s) asignado(s) en el periodo <?= e($periodoActual) ?></p>
            </div>
            <div class="panel-actions">
                <a class="btn btn-outline btn-sm" href="<?= e(base_url('views/notas/listar.php')) ?>">
                    Ver todas las notas
                </a>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th class="num">Créditos</th>
                        <th class="num">Estudiantes</th>
                        <th class="num">Evaluaciones</th>
                        <th class="num">Promedio</th>
                        <th class="num no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $curso): ?>
                        <tr>
                            <td>
                                <div class="profe-curso">
                                    <b><?= e($curso['nombre']) ?></b>
                                    <small><?= e($curso['codigo'] ?: 'Sin código') ?></small>
                                </div>
                            </td>
                            <td class="num tabular"><?= (int) $curso['creditos'] ?></td>
                            <td class="num tabular"><?= (int) $curso['estudiantes'] ?></td>
                            <td class="num tabular"><?= (int) $curso['evaluaciones'] ?></td>
                            <td class="num">
                                <?php if ($curso['promedio'] === null): ?>
                                    <span class="muted">—</span>
                                <?php else: ?>
                                    <span class="profe-promedio">
                                        <?= number_format((float) $curso['promedio'], 1, ',', '.') ?><small>/100</small>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="num no-print">
                                <a class="btn btn-ghost btn-sm"
                                    href="<?= e(base_url('views/notas/listar.php?curso=' . (int) $curso['id_curso'])) ?>">
                                    Ver notas
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Mi horario</h2>
                <p>Bloques de clase asignados</p>
            </div>
            <div class="panel-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('views/horarios/ver.php')) ?>">
                    Ver completo
                </a>
            </div>
        </div>

        <div class="panel-body">
            <?php if (!$horariosPorDia): ?>
                <div class="empty" style="padding:26px 8px">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 6v6l4 2M12 22a10 10 0 100-20 10 10 0 000 20z" />
                    </svg>
                    <h3>Sin clases programadas</h3>
                    <p>Tu horario aparecerá aquí cuando coordinación registre tus bloques.</p>
                </div>
            <?php else: ?>
                <div class="profe-agenda">
                    <?php foreach ($horariosPorDia as $dia => $bloques): ?>
                        <div class="profe-agenda-grupo">
                            <div class="profe-agenda-dia">
                                <b><?= e($dia) ?></b>
                                <?php if ($dia === $hoy): ?>
                                    <span class="badge badge-purple no-dot">Hoy</span>
                                <?php endif; ?>
                            </div>

                            <?php foreach ($bloques as $bloque): ?>
                                <div class="profe-clase <?= $dia === $hoy ? 'is-hoy' : '' ?>">
                                    <span class="profe-clase-hora">
                                        <b><?= e(substr((string) $bloque['hora_inicio'], 0, 5)) ?></b>
                                        <small><?= e(substr((string) $bloque['hora_fin'], 0, 5)) ?></small>
                                    </span>
                                    <span class="profe-clase-body">
                                        <b><?= e($bloque['nombre']) ?></b>
                                        <small>
                                            <?= e($bloque['codigo'] ?: 'Sin código') ?>
                                            <?= $bloque['aula'] ? ' · ' . e($bloque['aula']) : '' ?>
                                        </small>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2 mt-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Evaluaciones</h2>
                <p>Próximas y recientes en tus cursos</p>
            </div>
            <div class="panel-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('views/notas/listar.php')) ?>">
                    Gestionar
                </a>
            </div>
        </div>

        <div class="panel-body">
            <?php if (!$evaluaciones): ?>
                <div class="empty" style="padding:26px 8px">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6" />
                    </svg>
                    <h3>Sin evaluaciones registradas</h3>
                    <p>Crea una evaluación desde el módulo de notas para verla aquí.</p>
                </div>
            <?php else: ?>
                <div class="profe-evals">
                    <?php foreach ($evaluaciones as $evaluacion): ?>
                        <?php
                        $timestamp   = $evaluacion['fecha'] ? strtotime((string) $evaluacion['fecha']) : false;
                        $esProxima   = $evaluacion['fecha'] !== null && (string) $evaluacion['fecha'] >= $hoyISO;
                        $peso        = rtrim(rtrim((string) $evaluacion['peso'], '0'), '.');
                        ?>
                        <div class="profe-eval">
                            <span class="profe-eval-fecha <?= $esProxima ? 'es-proxima' : '' ?>">
                                <b><?= $timestamp ? date('d', $timestamp) : '—' ?></b>
                                <small><?= $timestamp ? $mesesCortos[(int) date('n', $timestamp)] : '' ?></small>
                            </span>
                            <span class="profe-eval-body">
                                <b><?= e($evaluacion['nombre']) ?></b>
                                <small>
                                    <?= e($evaluacion['curso']) ?> ·
                                    <?= e($peso) ?>% del curso
                                </small>
                            </span>
                            <span class="li-meta">
                                <span class="badge badge-<?= $esProxima ? 'purple' : 'gray' ?> no-dot">
                                    <?= $esProxima ? 'Próxima' : 'Anterior' ?>
                                </span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Estudiantes por curso</h2>
                <p>Inscritos en el periodo <?= e($periodoActual) ?></p>
            </div>
        </div>

        <div class="panel-body">
            <?php if ($totalEstudiantes === 0): ?>
                <div class="empty" style="padding:26px 8px">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                    <h3>Aún no hay estudiantes inscritos</h3>
                    <p>Cuando secretaría registre matrículas en tus cursos, verás la distribución aquí.</p>
                </div>
            <?php else: ?>
                <div class="profe-carga">
                    <?php foreach ($cursos as $curso): ?>
                        <?php
                        $inscritos = (int) $curso['estudiantes'];
                        $porcentaje = (int) round(($inscritos / $maxEstudiantes) * 100);
                        ?>
                        <div class="profe-carga-fila">
                            <div class="flex-between">
                                <span class="profe-carga-nombre"><?= e($curso['nombre']) ?></span>
                                <span class="profe-carga-meta">
                                    <b><?= $inscritos ?></b> estudiante<?= $inscritos === 1 ? '' : 's' ?>
                                </span>
                            </div>
                            <div class="bar <?= $inscritos === 0 ? 'warn' : '' ?>">
                                <span data-bar="<?= $porcentaje ?>" style="width:0"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>
