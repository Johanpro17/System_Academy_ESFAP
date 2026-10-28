<?php

/**
 * Panel principal: indicadores, gráficas y actividad reciente.
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerir_login();
refrescar_usuario();

if (Auth::es_profesor()) {
    redirigir(base_url('profesor.php'));
}

if (!Auth::puede('dashboard.ver')) {
    redirigir(Auth::url_inicio());
}

$esAdmin      = Auth::es_admin();
$esSecretaria = Auth::es_secretaria();
$esAuditoria  = Auth::es_auditoria();

$puedeAuditoria   = Auth::puede('auditoria.ver');
$puedeEstudiantes = Auth::puede('estudiantes.ver');
$puedeCursos      = Auth::puede('cursos.ver');
$puedeProfesores  = Auth::puede('profesores.ver');
$puedeMatriculas  = Auth::puede('matriculas.ver');
$puedeNoticias    = Auth::puede('noticias.ver');
$puedeCrearMatricula = Auth::puede('matriculas.crear');

/* ------------------------------------------------------------------
 * Indicadores
 * ---------------------------------------------------------------- */

$totales = [
    'estudiantes' => (int) db()->valor("SELECT COUNT(*) FROM estudiantes", [], 0),
    'cursos'      => (int) db()->valor("SELECT COUNT(*) FROM cursos WHERE activo = 1", [], 0),
    'profesores'  => (int) db()->valor("SELECT COUNT(*) FROM profesores WHERE activo = 1", [], 0),
    'matriculas'  => (int) db()->valor("SELECT COUNT(*) FROM matriculas", [], 0),
    'evaluaciones'=> (int) db()->valor("SELECT COUNT(*) FROM evaluaciones", [], 0),
    'usuarios'    => (int) db()->valor("SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND id_rol IS NOT NULL", [], 0),
];

$promedioGeneral = (float) db()->valor(
    "SELECT COALESCE(AVG(promedio), 0) FROM v_promedio_por_curso",
    [],
    0
);

/* Matrículas del periodo actual vs anterior */
$periodoActual = input('periodo') ?: date('Y') . '-1';
$matriculasPeriodo = (int) db()->valor(
    "SELECT COUNT(*) FROM matriculas WHERE periodo = :p",
    [':p' => $periodoActual],
    0
);

$aprobacion = ['aprobadas' => 0, 'total' => 0];
try {
    $r = db()->fila(
        "SELECT
            SUM(CASE WHEN promedio >= 7 THEN 1 ELSE 0 END) AS aprobadas,
            COUNT(*) AS total
         FROM v_promedio_por_curso"
    );
    if ($r) {
        $aprobacion = [
            'aprobadas' => (int) $r['aprobadas'],
            'total'     => (int) $r['total'],
        ];
    }
} catch (Throwable $e) {
    // vista no disponible
}
$porcentajeAprobacion = $aprobacion['total'] > 0
    ? round(($aprobacion['aprobadas'] / $aprobacion['total']) * 100)
    : 0;

/* ------------------------------------------------------------------
 * Distribución de estudiantes por especialidad
 * ---------------------------------------------------------------- */

$porEspecialidad = [];
try {
    $porEspecialidad = db()->todas(
        "SELECT esp.nombre, COUNT(e.id_estudiante) AS total
           FROM especialidades esp
           LEFT JOIN estudiantes e ON e.id_especialidad = esp.id_especialidad
          GROUP BY esp.id_especialidad, esp.nombre
          ORDER BY total DESC, esp.nombre ASC"
    );
} catch (Throwable $e) {
    $porEspecialidad = [];
}
$maxEspecialidad = max(1, ...array_map(static fn($f) => (int) $f['total'], $porEspecialidad ?: [['total' => 1]]));

/* ------------------------------------------------------------------
 * Matrículas por periodo
 * ---------------------------------------------------------------- */

$porPeriodo = [];
try {
    $porPeriodo = db()->todas(
        "SELECT periodo, COUNT(*) AS total
           FROM matriculas
          GROUP BY periodo
          ORDER BY periodo ASC
          LIMIT 8"
    );
} catch (Throwable $e) {
    $porPeriodo = [];
}
$maxPeriodo = max(1, ...array_map(static fn($f) => (int) $f['total'], $porPeriodo ?: [['total' => 1]]));

/* ------------------------------------------------------------------
 * Datos recientes
 * ---------------------------------------------------------------- */

$ultimasMatriculas = [];
try {
    $ultimasMatriculas = db()->todas(
        "SELECT CONCAT(e.nombres,' ',e.apellidos) AS estudiante,
                c.nombre AS curso, m.periodo, m.fecha_matricula
           FROM matriculas m
           JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
           JOIN cursos c ON c.id_curso = m.id_curso
          ORDER BY m.fecha_matricula DESC
          LIMIT 6"
    );
} catch (Throwable $e) {
    $ultimasMatriculas = [];
}

$noticias = [];
if ($puedeNoticias) {
    try {
        $noticias = db()->todas(
            "SELECT n.titulo, n.contenido, n.publicado_en, u.nombres AS autor
               FROM noticias n
               LEFT JOIN usuarios u ON u.id_usuario = n.autor_id
              WHERE n.publicado = 1
              ORDER BY n.destacado DESC, n.publicado_en DESC
              LIMIT 3"
        );
    } catch (Throwable $e) {
        $noticias = [];
    }
}

$actividad = [];
if ($puedeAuditoria) {
    try {
        $actividad = db()->todas(
            "SELECT a.accion, a.entidad, a.detalle, a.creado_en,
                    CONCAT(u.nombres,' ',u.apellidos) AS usuario
               FROM auditoria a
               LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
              ORDER BY a.creado_en DESC
              LIMIT 8"
        );
    } catch (Throwable $e) {
        $actividad = [];
    }
}

$titulo = $esSecretaria
    ? 'Panel de secretaría'
    : ($esAuditoria ? 'Panel de auditoría' : 'Resumen general');

$subtitulo = 'Panel de control · ' . APP_INSTITUCION;
$seccion   = 'dashboard';

if (Auth::puede('matriculas.crear')) {
    $accionesExtra = '<a class="btn btn-primary btn-sm" href="' . e(base_url('views/matriculas/crear.php')) . '">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Nueva matrícula</a>';
} elseif ($puedeAuditoria) {
    $accionesExtra = '<a class="btn btn-outline btn-sm" href="' . e(base_url('views/auditoria/listar.php')) . '">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12h6m-6 4h6M9 8h2m9-2v16l-3-2-3 2-3-2-3 2V6a2 2 0 012-2h6l3 2z"/></svg> Ver auditoría</a>';
} else {
    $accionesExtra = '';
}

$eyebrow = $esAdmin
    ? 'Panel de administración'
    : ($esSecretaria ? 'Secretaría académica' : 'Control y transparencia');

require_once APP_ROOT . '/layout/header.php';
?>

<!-- ================= KPIs ================= -->

<section class="dashboard-welcome" aria-labelledby="dashboard-welcome-title">
    <div class="dashboard-welcome-copy">
        <span class="eyebrow"><?= e($eyebrow) ?></span>
        <h2 id="dashboard-welcome-title">Hola, <?= e(Auth::nombre()) ?></h2>
        <p>Este es el resumen de la actividad académica de <?= e(APP_INSTITUCION) ?>.</p>
    </div>
    <div class="dashboard-quick-actions" aria-label="Accesos rápidos">
        <?php if (Auth::puede('estudiantes.crear')): ?>
            <a class="btn btn-primary" href="<?= e(base_url('views/estudiantes/crear.php')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM19 8v6m-3-3h6" />
                </svg>
                Nuevo estudiante
            </a>
        <?php endif; ?>
        <?php if (Auth::puede('matriculas.crear')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('views/matriculas/crear.php')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                </svg>
                Nueva matrícula
            </a>
        <?php endif; ?>
        <?php if (Auth::puede('cursos.gestionar')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('views/cursos/crear.php')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Nuevo curso
            </a>
        <?php endif; ?>
        <?php if ($puedeAuditoria && !Auth::puede('matriculas.crear')): ?>
            <a class="btn btn-primary" href="<?= e(base_url('views/auditoria/listar.php')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 12h6m-6 4h6M9 8h2m9-2v16l-3-2-3 2-3-2-3 2V6a2 2 0 012-2h6l3 2z" />
                </svg>
                Revisar bitácora
            </a>
        <?php endif; ?>
    </div>
</section>

<div class="kpi-grid">
    <?php if ($puedeEstudiantes): ?>
    <div class="kpi" data-animar>
        <span class="kpi-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" />
            </svg>
        </span>
        <div>
            <div class="kpi-value"><?= number_format($totales['estudiantes'], 0, ',', '.') ?></div>
            <div class="kpi-label">Estudiantes</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puedeCursos): ?>
    <div class="kpi" data-animar data-animar-delay="1">
        <span class="kpi-icon ok">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" />
            </svg>
        </span>
        <div>
            <div class="kpi-value"><?= number_format($totales['cursos'], 0, ',', '.') ?></div>
            <div class="kpi-label">Cursos activos</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puedeProfesores): ?>
    <div class="kpi" data-animar data-animar-delay="2">
        <span class="kpi-icon gold">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m9-6v5c0 1.7-4 3-9 3s-9-1.3-9-3v-5" />
            </svg>
        </span>
        <div>
            <div class="kpi-value"><?= number_format($totales['profesores'], 0, ',', '.') ?></div>
            <div class="kpi-label">Profesores</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puedeMatriculas): ?>
    <div class="kpi" data-animar data-animar-delay="3">
        <span class="kpi-icon warn">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
            </svg>
        </span>
        <div>
            <div class="kpi-value"><?= number_format($matriculasPeriodo, 0, ',', '.') ?></div>
            <div class="kpi-label">Matrículas <?= e($periodoActual) ?></div>
            <div class="kpi-delta up"><?= number_format($totales['matriculas'], 0, ',', '.') ?> en total</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ================= Gráficas ================= -->

<div class="grid-2 mb-3">
    <div class="panel" data-animar>
        <div class="panel-head">
            <div>
                <h2>Estudiantes por especialidad</h2>
                <p>Distribución de la matrícula activa</p>
            </div>
        </div>
        <div class="panel-body">
            <?php if (!$porEspecialidad): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 20V10m5 10V4m5 16v-7m5 7V8" />
                    </svg>
                    <p>Todavía no hay especialidades con estudiantes.</p>
                </div>
            <?php else: ?>
                <div class="form-grid" style="gap:14px">
                    <?php foreach ($porEspecialidad as $esp): ?>
                        <?php $pct = round(((int) $esp['total'] / $maxEspecialidad) * 100); ?>
                        <div>
                            <div class="flex-between" style="margin-bottom:6px">
                                <span style="font-size:.87rem;font-weight:600"><?= e($esp['nombre']) ?></span>
                                <span class="tabular" style="font-size:.85rem;font-weight:700;color:var(--brand)">
                                    <?= (int) $esp['total'] ?>
                                </span>
                            </div>
                            <div class="bar <?= (int) $esp['total'] === 0 ? 'warn' : '' ?>">
                                <span data-bar="<?= $pct ?>" style="width:0"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel" data-animar data-animar-delay="1">
        <div class="panel-head">
            <div>
                <h2>Rendimiento académico</h2>
                <p>Promedios y aprobación del conjunto</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="flex" style="gap:26px;align-items:center;flex-wrap:wrap">
                <div class="donut" style="--value:<?= $porcentajeAprobacion ?>;--fill:var(--ok)">
                    <b><?= $porcentajeAprobacion ?><small>%</small></b>
                </div>

                <div style="flex:1;min-width:180px">
                    <div class="list" style="gap:2px">
                        <div class="list-item" style="padding:9px 0;border:0">
                            <span class="li-body">
                                <b>Promedio general</b>
                                <small>Ponderado de todos los registros</small>
                            </span>
                            <span class="li-meta">
                                <b style="color:var(--brand)">
                                    <?= $promedioGeneral > 0 ? number_format($promedioGeneral, 2, ',', '.') : '—' ?>
                                </b>
                            </span>
                        </div>
                        <div class="list-item" style="padding:9px 0;border:0">
                            <span class="li-body">
                                <b>Aprobados</b>
                                <small>Promedio ≥ 7.00</small>
                            </span>
                            <span class="li-meta">
                                <b style="color:var(--ok)"><?= number_format($aprobacion['aprobadas'], 0, ',', '.') ?></b>
                            </span>
                        </div>
                        <div class="list-item" style="padding:9px 0;border:0">
                            <span class="li-body">
                                <b>En riesgo</b>
                                <small>Promedio &lt; 7.00</small>
                            </span>
                            <span class="li-meta">
                                <b style="color:var(--danger)">
                                    <?= number_format(max(0, $aprobacion['total'] - $aprobacion['aprobadas']), 0, ',', '.') ?>
                                </b>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($porPeriodo): ?>
                <div class="divider"></div>
                <h3 class="section-title" style="margin-top:0">Matrículas por periodo</h3>
                <div class="chart" style="height:150px">
                    <?php foreach ($porPeriodo as $p): ?>
                        <?php $altura = max(4, (int) round(((int) $p['total'] / $maxPeriodo) * 100)); ?>
                        <div class="chart-col">
                            <div class="chart-bar" style="height:<?= $altura ?>%">
                                <span class="val"><?= (int) $p['total'] ?></span>
                            </div>
                            <span class="chart-label"><?= e($p['periodo']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ================= Módulos ================= -->

<h2 class="section-title" data-animar>Módulos del sistema</h2>

<div class="grid-3 mod-grid mb-3">
    <?php
    $modulos = [
        Auth::puede('estudiantes.ver')
            ? ['estudiantes', 'views/estudiantes/listar.php', 'Estudiantes', 'Consulta el padrón de alumnos y filtra por especialidad.', $totales['estudiantes'] . ' registros', '']
            : null,
        Auth::puede('cursos.ver')
            ? ['cursos', 'views/cursos/listar.php', 'Cursos', 'Consulta la oferta académica, créditos y áreas de formación.', $totales['cursos'] . ' activos', 'is-ok']
            : null,
        Auth::puede('matriculas.ver')
            ? ['matriculas', 'views/matriculas/listar.php', 'Matrículas', 'Inscripciones por curso, periodo y estado académico.', $totales['matriculas'] . ' históricas', 'is-warn']
            : null,
        Auth::puede('notas.ver')
            ? ['notas', 'views/notas/listar.php', 'Notas', 'Evaluaciones y calificaciones por estudiante.', $totales['evaluaciones'] . ' evaluaciones', 'is-gold']
            : null,
        Auth::puede('horarios.ver')
            ? ['horarios', 'views/horarios/ver.php', 'Horarios', 'Programación semanal de clases y aulas.', 'Semanal', '']
            : null,
        Auth::puede('reportes.ver')
            ? ['reportes', 'views/reportes/generar.php', 'Reportes', 'Genera y exporta documentos en CSV y PDF.', 'Exportable', 'is-ok']
            : null,
        Auth::puede('noticias.ver')
            ? ['noticias', 'views/noticias/listar.php', 'Noticias', 'Avisos, eventos y comunicados de la institución.', 'Institucional', '']
            : null,
        Auth::puede('usuarios.ver')
            ? ['usuarios', 'views/usuarios/listar.php', 'Usuarios', 'Cuentas, roles y estado de acceso al sistema.', 'Seguridad', 'is-warn']
            : null,
        Auth::puede('auditoria.ver')
            ? ['auditoria', 'views/auditoria/listar.php', 'Auditoría', 'Trazabilidad de las acciones realizadas en el sistema.', 'Bitácora', 'is-gold']
            : null,
        Auth::puede('perfil.ver')
            ? ['perfil', 'perfil.php', 'Mi perfil', 'Actualiza tus datos y la contraseña de acceso.', 'Personal', 'is-ok']
            : null,
    ];
    $modulos = array_values(array_filter($modulos));

    foreach ($modulos as $m):
        [$icono, $ruta, $nombre, $desc, $meta, $clase] = $m;
    ?>
        <a class="mod-card <?= $clase ?>" href="<?= e(base_url($ruta)) ?>" data-animar>
            <span class="mod-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <?php
                    $paths = [
                        'estudiantes' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z',
                        'cursos'      => 'M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z',
                        'matriculas'  => 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11',
                        'notas'       => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.5 15.5l-4 1 1-4 9-9z',
                        'horarios'    => 'M12 6v6l4 2M12 22a10 10 0 100-20 10 10 0 000 20z',
                        'reportes'    => 'M4 20V10m5 10V4m5 16v-7m5 7V8',
                        'noticias'    => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2zM7 8h7M7 12h10M7 16h6',
                        'usuarios'    => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
                        'auditoria'   => 'M9 12h6m-6 4h6M9 8h2m9-2v16l-3-2-3 2-3-2-3 2V6a2 2 0 012-2h6l3 2z',
                        'perfil'      => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
                    ];
                    ?>
                    <path d="<?= e($paths[$icono] ?? '') ?>" />
                </svg>
            </span>
            <div style="min-width:0">
                <h3><?= e($nombre) ?></h3>
                <p><?= e($desc) ?></p>
                <span class="mod-meta"><?= e($meta) ?></span>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<!-- ================= Actividad ================= -->

<div class="grid-2">
    <?php if ($puedeMatriculas): ?>
    <div class="panel" data-animar>
        <div class="panel-head">
            <div>
                <h2>Matrículas recientes</h2>
                <p>Últimos registros inscritos</p>
            </div>
            <div class="panel-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('views/matriculas/listar.php')) ?>">Ver todas</a>
            </div>
        </div>

        <?php if (!$ultimasMatriculas): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                </svg>
                <h3>Sin matrículas todavía</h3>
                <p>Crea la primera matrícula para empezar a tener datos en el panel.</p>
                <?php if ($puedeCrearMatricula): ?>
                    <a class="btn btn-primary" href="<?= e(base_url('views/matriculas/crear.php')) ?>">Crear matrícula</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($ultimasMatriculas as $m): ?>
                    <div class="list-item">
                        <span class="avatar" style="width:34px;height:34px;flex-basis:34px;font-size:.72rem">
                            <?= e(iniciales($m['estudiante'])) ?>
                        </span>
                        <span class="li-body">
                            <b><?= e($m['estudiante']) ?></b>
                            <small><?= e($m['curso']) ?> · <?= e($m['periodo']) ?></small>
                        </span>
                        <span class="li-meta">
                            <b style="font-weight:600"><?= e(fecha($m['fecha_matricula'])) ?></b>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($puedeAuditoria || $puedeNoticias): ?>
    <div class="panel" data-animar data-animar-delay="1">
        <div class="panel-head">
            <div>
                <h2><?= $puedeAuditoria ? 'Auditoría reciente' : 'Noticias institucionales' ?></h2>
                <p><?= $puedeAuditoria ? 'Últimas acciones registradas en el sistema' : 'Comunicados de la institución' ?></p>
            </div>
            <div class="panel-actions">
                <a class="btn btn-ghost btn-sm"
                    href="<?= e(base_url($puedeAuditoria ? 'views/auditoria/listar.php' : 'views/noticias/listar.php')) ?>">
                    Ver todo
                </a>
            </div>
        </div>

        <?php if ($puedeAuditoria && !$actividad): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 12h6m-6 4h6M9 8h2m9-2v16l-3-2-3 2-3-2-3 2V6a2 2 0 012-2h6l3 2z" />
                </svg>
                <h3>Sin actividad registrada</h3>
                <p>Las acciones que lleves a cabo quedarán registradas aquí automáticamente.</p>
            </div>
        <?php elseif ($puedeAuditoria): ?>
            <div class="panel-body">
                <div class="timeline">
                    <?php foreach ($actividad as $a): ?>
                        <div class="tl-item">
                            <b><?= e(ucfirst(str_replace('_', ' ', $a['accion']))) ?>
                                <span class="muted small">· <?= e($a['entidad']) ?></span>
                            </b>
                            <?php if ($a['detalle']): ?>
                                <p><?= e($a['detalle']) ?></p>
                            <?php endif; ?>
                            <time><?= e(($a['usuario'] ?: 'Sistema')) ?> ·
                                <?= e(fecha($a['creado_en'], 'd/m/Y H:i')) ?></time>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif (!$noticias): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2z" />
                </svg>
                <h3>Sin noticias publicadas</h3>
                <p>Aquí aparecerán los comunicados de la institución.</p>
            </div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($noticias as $n): ?>
                    <div class="list-item" style="align-items:flex-start">
                        <span class="li-body">
                            <b><?= e($n['titulo']) ?></b>
                            <small><?= e(mb_strimwidth(strip_tags($n['contenido']), 0, 90, '…')) ?></small>
                            <time class="muted" style="font-size:.72rem">
                                <?= e(fecha($n['publicado_en'])) ?>
                            </time>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>
