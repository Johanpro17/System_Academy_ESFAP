<?php

/**
 * Panel del profesor: información limitada a sus cursos asignados.
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerir_rol(2);
refrescar_usuario();

$idProfesor = db()->valor(
    "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
    [':u' => Auth::id()]
);

$cursos = $idProfesor
    ? db()->todas(
        "SELECT c.id_curso, c.codigo, c.nombre, c.creditos,
                COUNT(DISTINCT m.id_estudiante) AS estudiantes,
                COUNT(DISTINCT ev.id_evaluacion) AS evaluaciones
           FROM curso_docentes cd
           JOIN cursos c ON c.id_curso = cd.id_curso
           LEFT JOIN matriculas m ON m.id_curso = c.id_curso
           LEFT JOIN evaluaciones ev ON ev.id_curso = c.id_curso
          WHERE cd.id_profesor = :p
          GROUP BY c.id_curso, c.codigo, c.nombre, c.creditos
          ORDER BY c.nombre",
        [':p' => $idProfesor]
    )
    : [];

$totalEstudiantes = $idProfesor
    ? (int) db()->valor(
        "SELECT COUNT(DISTINCT m.id_estudiante)
           FROM matriculas m
           JOIN curso_docentes cd ON cd.id_curso = m.id_curso
          WHERE cd.id_profesor = :p",
        [':p' => $idProfesor],
        0
    )
    : 0;
$totalEvaluaciones = array_sum(array_map(
    static fn(array $curso): int => (int) $curso['evaluaciones'],
    $cursos
));

$titulo = 'Panel del profesor';
$subtitulo = 'Resumen de tus cursos y grupos asignados';
$seccion = 'dashboard';

require_once APP_ROOT . '/layout/header.php';
?>

<section class="stats-grid" aria-label="Resumen de actividad del profesor">
    <article class="stat-card">
        <span class="stat-label">Cursos asignados</span>
        <strong class="stat-value"><?= count($cursos) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Estudiantes inscritos</span>
        <strong class="stat-value"><?= $totalEstudiantes ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Evaluaciones</span>
        <strong class="stat-value"><?= $totalEvaluaciones ?></strong>
    </article>
</section>

<section class="panel">
    <header class="panel-header">
        <div>
            <h2>Mis cursos</h2>
            <p>Solo se muestran los cursos que te fueron asignados.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('views/notas/listar.php')) ?>">
            Revisar calificaciones
        </a>
    </header>

    <?php if (!$cursos): ?>
        <div class="empty-state">
            <h3>No tienes cursos asignados</h3>
            <p>Solicita al administrador que vincule tus cursos a tu cuenta de profesor.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Curso</th>
                        <th>Créditos</th>
                        <th>Estudiantes</th>
                        <th>Evaluaciones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $curso): ?>
                        <tr>
                            <td><?= e($curso['codigo'] ?: '—') ?></td>
                            <td><?= e($curso['nombre']) ?></td>
                            <td><?= (int) $curso['creditos'] ?></td>
                            <td><?= (int) $curso['estudiantes'] ?></td>
                            <td><?= (int) $curso['evaluaciones'] ?></td>
                            <td>
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
    <?php endif; ?>
</section>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>
