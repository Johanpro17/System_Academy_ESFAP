<?php

/**
 * Portal de consulta personal de calificaciones para estudiantes.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_rol(Auth::ROL_ESTUDIANTE);

$estudiante = db()->fila(
    "SELECT id_estudiante, matricula
       FROM estudiantes
      WHERE id_usuario = :u
      ORDER BY id_estudiante ASC
      LIMIT 1",
    [':u' => Auth::id()]
);

$resumen = [];
$calificaciones = [];

if ($estudiante) {
    $resumen = db()->todas(
        "SELECT curso, periodo, promedio, evaluaciones
           FROM v_promedio_por_curso
          WHERE id_estudiante = :e
          ORDER BY periodo DESC, curso ASC",
        [':e' => $estudiante['id_estudiante']]
    );

    $calificaciones = db()->todas(
        "SELECT c.nombre AS curso, n.periodo, ev.nombre AS evaluacion, ev.fecha, n.valor
           FROM notas n
           JOIN cursos c ON c.id_curso = n.id_curso
           LEFT JOIN evaluaciones ev ON ev.id_evaluacion = n.id_evaluacion
          WHERE n.id_estudiante = :e
          ORDER BY n.periodo DESC, c.nombre ASC, ev.fecha ASC, ev.nombre ASC, n.id_nota ASC",
        [':e' => $estudiante['id_estudiante']]
    );
}

$titulo = 'Mis calificaciones';
$subtitulo = $estudiante
    ? 'Consulta privada de tus resultados académicos'
    : 'Tu cuenta todavía no tiene una ficha académica activa';
$seccion = 'notas';
$ancho = '';
$accionesExtra = '';

require_once APP_ROOT . '/layout/header.php';
?>

<?php if (!$estudiante): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty">
                <h3>Ficha de estudiante pendiente</h3>
                <p>Contacta con coordinación para vincular tu cuenta con tu ficha académica.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel mb-3">
        <div class="panel-head">
            <div>
                <h2>Resumen por curso y periodo</h2>
                <p>La información mostrada corresponde únicamente a tu cuenta.</p>
            </div>
            <?php if (!empty($estudiante['matricula'])): ?>
                <span class="badge">Matrícula <?= e($estudiante['matricula']) ?></span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if (!$resumen): ?>
                <div class="empty">
                    <h3>Aún no hay calificaciones</h3>
                    <p>Cuando tus docentes registren notas, aparecerán aquí.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Periodo</th>
                                <th class="num">Promedio</th>
                                <th class="num">Evaluaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumen as $fila): ?>
                                <tr>
                                    <td><strong><?= e($fila['curso']) ?></strong></td>
                                    <td><?= e($fila['periodo']) ?></td>
                                    <td class="num tabular"><?= number_format((float) $fila['promedio'], 2, ',', '.') ?></td>
                                    <td class="num tabular"><?= (int) $fila['evaluaciones'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Detalle de calificaciones</h2>
                <p>Solo lectura · Las notas son registradas por tus docentes.</p>
            </div>
        </div>
        <div class="panel-body">
            <?php if (!$calificaciones): ?>
                <div class="empty">
                    <p>No hay calificaciones para mostrar.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Periodo</th>
                                <th>Evaluación</th>
                                <th>Fecha</th>
                                <th class="num">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calificaciones as $fila): ?>
                                <tr>
                                    <td><?= e($fila['curso']) ?></td>
                                    <td><?= e($fila['periodo']) ?></td>
                                    <td><?= e($fila['evaluacion'] ?: 'Calificación') ?></td>
                                    <td><?= e(fecha($fila['fecha'])) ?></td>
                                    <td class="num tabular"><strong><?= number_format((float) $fila['valor'], 2, ',', '.') ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>
