<?php

/**
 * Registro y consulta de notas por evaluación.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('notas.ver');

$errores = [];

$puedeEditar = Auth::puede('notas.editar');
$soloProfesor = Auth::es_profesor();
$idProfesor = $soloProfesor ? db()->valor(
    "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
    [':u' => Auth::id()]
) : null;

if (!$soloProfesor) {
    $cursos = db()->todas("SELECT id_curso, codigo, nombre FROM cursos WHERE activo = 1 ORDER BY nombre ASC");
    $evaluaciones = db()->todas("SELECT id_evaluacion, id_curso, nombre, peso FROM evaluaciones ORDER BY nombre ASC");
} elseif ($idProfesor) {
    $cursos = db()->todas(
        "SELECT DISTINCT c.id_curso, c.codigo, c.nombre
           FROM cursos c
           JOIN curso_docentes cd ON cd.id_curso = c.id_curso
          WHERE c.activo = 1 AND cd.id_profesor = :p
          ORDER BY c.nombre ASC",
        [':p' => $idProfesor]
    );
    $evaluaciones = db()->todas(
        "SELECT DISTINCT ev.id_evaluacion, ev.id_curso, ev.nombre, ev.peso
           FROM evaluaciones ev
           JOIN curso_docentes cd ON cd.id_curso = ev.id_curso
          WHERE cd.id_profesor = :p
          ORDER BY ev.nombre ASC",
        [':p' => $idProfesor]
    );
} else {
    $cursos = [];
    $evaluaciones = [];
    $errores[] = 'Tu cuenta todavía no está vinculada a una ficha de profesor activa.';
}

$cursoPermitido = static function (int $curso) use ($soloProfesor, $idProfesor): bool {
    if (!$soloProfesor) {
        return $curso > 0;
    }
    if (!$idProfesor) {
        return false;
    }
    return (bool) db()->valor(
        "SELECT 1 FROM curso_docentes WHERE id_profesor = :p AND id_curso = :c LIMIT 1",
        [':p' => $idProfesor, ':c' => $curso]
    );
};

$periodos   = db()->todas("SELECT DISTINCT periodo FROM matriculas ORDER BY periodo DESC");

if (es_post()) {
    exigir_post();
    Auth::requerir_permiso('notas.editar');
    $accion = input('accion');

    /* Crear evaluación */
    if ($accion === 'crear_evaluacion') {
        $idCurso = input_int('id_curso');
        $nombre   = trim(input('nombre'));
        $peso     = input('peso');
        $fecha    = input('fecha') ?: null;

        if (!$idCurso || !$cursoPermitido($idCurso)) {
            $errores[] = 'Selecciona el curso de la evaluación.';
        }
        if ($nombre === '') {
            $errores[] = 'El nombre de la evaluación es obligatorio.';
        }
        if (!is_numeric($peso) || $peso < 0 || $peso > 100) {
            $errores[] = 'El peso debe estar entre 0 y 100.';
        }

        if (!$errores) {
            db()->query(
                "INSERT INTO evaluaciones (id_curso, nombre, peso, fecha) VALUES (:c, :n, :p, :f)",
                [':c' => $idCurso, ':n' => $nombre, ':p' => (float) $peso, ':f' => $fecha]
            );
            $id = (int) db()->conectar()->lastInsertId();
            Auth::registrar('crear', 'evaluaciones', $id, "Evaluación «{$nombre}» creada");
            flash_set('success', 'Evaluación creada correctamente.');
            redirigir('listar.php');
        }
    }

    /* Eliminar evaluación */
    if ($accion === 'eliminar_evaluacion') {
        $id = input_int('id');
        $cursoEvaluacion = (int) db()->valor(
            "SELECT id_curso FROM evaluaciones WHERE id_evaluacion = :id",
            [':id' => $id],
            0
        );
        if (!$cursoPermitido($cursoEvaluacion)) {
            flash_set('error', 'No puedes eliminar evaluaciones de cursos que no tienes asignados.');
        } else try {
            db()->conectar()->prepare("DELETE FROM evaluaciones WHERE id_evaluacion = :id")->execute([':id' => $id]);
            Auth::registrar('eliminar', 'evaluaciones', $id, 'Evaluación eliminada');
            flash_set('success', 'Evaluación eliminada.');
        } catch (Throwable $e) {
            flash_set('error', 'No se pudo eliminar la evaluación.');
        }
        redirigir('listar.php');
    }

    /* Guardar notas */
    if ($accion === 'guardar_notas') {
        $idEvaluacion = input_int('id_evaluacion');
        $periodo      = input('periodo');
        $valores      = $_POST['nota'] ?? [];

        if (!$idEvaluacion) {
            $errores[] = 'Selecciona una evaluación.';
        } elseif ($periodo === '' || !preg_match('/^\d{4}-[12]$/', $periodo)) {
            $errores[] = 'El periodo no es válido.';
        }

        /* El curso se toma de la evaluación, nunca del formulario */
        $cursoEvaluacion = $idEvaluacion ? db()->valor(
            "SELECT id_curso FROM evaluaciones WHERE id_evaluacion = :id",
            [':id' => $idEvaluacion]
        ) : null;

        if ($idEvaluacion && (!$cursoEvaluacion || !$cursoPermitido((int) $cursoEvaluacion))) {
            $errores[] = 'La evaluación no existe o pertenece a un curso que no tienes asignado.';
        }
        $idCurso = (int) ($cursoEvaluacion ?? 0);

        /* Solo se aceptan estudiantes inscritos en ese curso y periodo */
        $inscritos = [];
        if (!$errores) {
            foreach (db()->todas(
                "SELECT id_estudiante FROM matriculas WHERE id_curso = :c AND periodo = :p",
                [':c' => $idCurso, ':p' => $periodo]
            ) as $m) {
                $inscritos[(int) $m['id_estudiante']] = true;
            }
        }

        $descartadas = 0;
        $guardadas = 0;
        if (!$errores) {
            $pdo = db()->conectar();
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO notas (id_estudiante, id_curso, id_evaluacion, periodo, valor, registrado_por)
                     VALUES (:e, :c, :ev, :p, :v, :u)
                     ON DUPLICATE KEY UPDATE valor = VALUES(valor), registrado_por = VALUES(registrado_por)"
                );

                foreach ($valores as $idEstudiante => $valor) {
                    $idEstudiante = (int) $idEstudiante;

                    if ($valor === '' || $valor === null) {
                        continue;
                    }
                    if (!isset($inscritos[$idEstudiante])) {
                        $descartadas++;
                        continue;
                    }
                    $valor = (float) $valor;
                    if ($valor < 0 || $valor > 100) {
                        $descartadas++;
                        continue;
                    }

                    $stmt->execute([
                        ':e'  => $idEstudiante,
                        ':c'  => $idCurso,
                        ':ev' => $idEvaluacion,
                        ':p'  => $periodo,
                        ':v'  => $valor,
                        ':u'  => Auth::id(),
                    ]);
                    $guardadas++;
                }

                $pdo->commit();
                Auth::registrar('guardar_notas', 'notas', $idEvaluacion, "{$guardadas} notas registradas en {$periodo}");
                if ($descartadas > 0) {
                    $errores[] = "Se omitieron {$descartadas} valor(es): fuera del rango 0-100 o no inscrito en el curso.";
                }
                flash_set('success', "{$guardadas} nota(s) guardada(s) correctamente.");
                redirigir('listar.php?curso=' . $idCurso . '&evaluacion=' . $idEvaluacion . '&periodo=' . urlencode($periodo));
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errores[] = 'Error al guardar las notas: ' . $e->getMessage();
            }
        }
    }
}

/* ---------------- Filtros ---------------- */

$idEvaluacion = input_int('evaluacion');
$periodo      = input('periodo') ?: ($periodos[0]['periodo'] ?? date('Y') . '-1');
$idCurso      = input_int('curso');
if ($idCurso && !$cursoPermitido($idCurso)) {
    $idCurso = 0;
    $errores[] = 'No tienes permiso para consultar ese curso.';
}

/* Estudiantes matriculados en el curso/periodo */
$estudiantes = [];
if ($idCurso) {
    $estudiantes = db()->todas(
        "SELECT e.id_estudiante, CONCAT(e.nombres,' ',e.apellidos) AS nombre
           FROM matriculas m
           JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
          WHERE m.id_curso = :c AND m.periodo = :p
          ORDER BY e.apellidos, e.nombres",
        [':c' => $idCurso, ':p' => $periodo]
    );
}

/* Notas ya registradas: siempre limitadas al curso mostrado */
$notasExistentes = [];
if ($idEvaluacion && $idCurso) {
    foreach (db()->todas(
        "SELECT id_estudiante, valor FROM notas
          WHERE id_curso = :c AND id_evaluacion = :ev AND periodo = :p",
        [':c' => $idCurso, ':ev' => $idEvaluacion, ':p' => $periodo]
    ) as $n) {
        $notasExistentes[(int) $n['id_estudiante']] = $n['valor'];
    }
}

$titulo    = 'Notas y evaluaciones';
$subtitulo = 'Registra y consulta calificaciones';
$seccion   = 'notas';
$ancho     = '';

$accionesExtra = $puedeEditar
    ? '<a class="btn btn-outline btn-sm" href="#form-eval">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Nueva evaluación</a>'
    : '';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $puedeEditar ? 'Cargar notas' : 'Notas del curso' ?></h2>
                <p><?= $puedeEditar ? 'Selecciona curso, evaluación y periodo' : 'Consulta de calificaciones · solo lectura' ?></p>
            </div>
        </div>
        <div class="panel-body">
            <form class="toolbar" method="GET" novalidate>
                <select class="input" name="curso" data-auto-submit required>
                    <option value="">Curso…</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int) $c['id_curso'] ?>" <?= $idCurso === (int) $c['id_curso'] ? 'selected' : '' ?>>
                            <?= e($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="input" name="evaluacion" data-auto-submit>
                    <option value="">Evaluación…</option>
                    <?php foreach ($evaluaciones as $ev): ?>
                        <?php if (!$idCurso || (int) $ev['id_curso'] === $idCurso): ?>
                            <option value="<?= (int) $ev['id_evaluacion'] ?>"
                                <?= $idEvaluacion === (int) $ev['id_evaluacion'] ? 'selected' : '' ?>>
                                <?= e($ev['nombre']) ?> (<?= e(rtrim(rtrim((string) $ev['peso'], '0'), '.')) ?>%)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <select class="input" name="periodo" data-auto-submit>
                    <?php foreach ($periodos as $p): ?>
                        <option value="<?= e($p['periodo']) ?>" <?= $periodo === $p['periodo'] ? 'selected' : '' ?>>
                            <?= e($p['periodo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($errores): ?>
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                    </svg>
                    <div><?= e(implode(' ', $errores)) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!$idCurso): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.5 15.5l-4 1 1-4 9-9z" />
                    </svg>
                    <h3>Selecciona un curso</h3>
                    <p>Elige un curso para ver a sus estudiantes matriculados y cargar sus notas.</p>
                </div>
            <?php elseif (!$estudiantes): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                    </svg>
                    <h3>Sin estudiantes matriculados</h3>
                    <p>No hay nadie inscrito en este curso para el periodo <?= e($periodo) ?>.</p>
                    <?php if (Auth::puede('matriculas.crear')): ?>
                        <a class="btn btn-primary" href="<?= e(base_url('views/matriculas/crear.php')) ?>">Ir a matrículas</a>
                    <?php endif; ?>
                </div>
            <?php elseif (!$idEvaluacion): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6" />
                    </svg>
                    <h3>Selecciona una evaluación</h3>
                    <p>Hay <?= count($estudiantes) ?> estudiante(s) matriculados. Crea una evaluación si aún no existe.</p>
                </div>
            <?php else: ?>
                <?php if (!$puedeEditar): ?>
                    <div class="table-wrap" style="max-height:460px;overflow-y:auto">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th class="num" style="width:150px">Nota (0–100)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $e): ?>
                                    <?php $nota = $notasExistentes[(int) $e['id_estudiante']] ?? null; ?>
                                    <tr>
                                        <td><?= e($e['nombre']) ?></td>
                                        <td class="num tabular">
                                            <?= $nota !== null
                                                ? e(rtrim(rtrim((string) $nota, '0'), '.'))
                                                : '<span class="muted">—</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="muted small mt-2">Consulta de solo lectura. La edición de notas está reservada a profesores y administradores.</p>
                <?php else: ?>
                <form method="POST" novalidate>
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="guardar_notas">
                    <input type="hidden" name="id_evaluacion" value="<?= (int) $idEvaluacion ?>">
                    <input type="hidden" name="periodo" value="<?= e($periodo) ?>">

                    <div class="table-wrap" style="max-height:460px;overflow-y:auto">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th class="num" style="width:150px">Nota (0–100)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $e): ?>
                                    <tr>
                                        <td><?= e($e['nombre']) ?></td>
                                        <td class="num">
                                            <input class="input tabular" type="number" step="0.01" min="0" max="100"
                                                name="nota[<?= (int) $e['id_estudiante'] ?>]"
                                                value="<?= e(isset($notasExistentes[(int) $e['id_estudiante']])
                                                    ? rtrim(rtrim((string) $notasExistentes[(int) $e['id_estudiante']], '0'), '.')
                                                    : '') ?>"
                                                style="text-align:right;padding:7px 11px">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions mt-2">
                        <button class="btn btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                                <path d="M17 21v-8H7v8M7 3v5h8" />
                            </svg>
                            Guardar notas
                        </button>
                        <span class="muted small" style="align-self:center">
                            Las notas vacías no se guardan. Se Actualizan si ya existen.
                        </span>
                    </div>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel" id="form-eval">
        <div class="panel-head">
            <div>
                <h2>Evaluaciones</h2>
                <p>Define los parciales de cada curso</p>
            </div>
        </div>
        <div class="panel-body">
            <?php if ($puedeEditar): ?>
            <form method="POST" novalidate class="form-grid mb-3">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="crear_evaluacion">

                <div class="field">
                    <label for="ev_curso">Curso *</label>
                    <select class="input" id="ev_curso" name="id_curso" required>
                        <option value="">Selecciona…</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int) $c['id_curso'] ?>" <?= $idCurso === (int) $c['id_curso'] ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="ev_nombre">Nombre *</label>
                    <input class="input" type="text" id="ev_nombre" name="nombre" maxlength="120" required
                        placeholder="Primer parcial">
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="ev_peso">Peso (%)</label>
                        <input class="input" type="number" id="ev_peso" name="peso" min="0" max="100" step="0.01"
                            value="25">
                    </div>
                    <div class="field">
                        <label for="ev_fecha">Fecha</label>
                        <input class="input" type="date" id="ev_fecha" name="fecha" value="<?= e(date('Y-m-d')) ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        Crear evaluación
                    </button>
                </div>
            </form>

            <div class="divider"></div>
            <?php endif; ?>

            <?php
            $evsFiltradas = array_filter($evaluaciones, static fn($ev) => !$idCurso || (int) $ev['id_curso'] === $idCurso);
            if (!$evsFiltradas):
                ?>
                <div class="empty" style="padding:30px 16px">
                    <p>No hay evaluaciones creadas<?= $idCurso ? ' para este curso' : '' ?>.</p>
                </div>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($evsFiltradas as $ev): ?>
                        <div class="list-item">
                            <span class="notif-icon" style="background:var(--brand-50);color:var(--brand)">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6" />
                                </svg>
                            </span>
                            <span class="li-body">
                                <b><?= e($ev['nombre']) ?></b>
                                <small>Peso <?= e(rtrim(rtrim((string) $ev['peso'], '0'), '.')) ?>%</small>
                            </span>
                            <?php if ($puedeEditar): ?>
                            <form method="POST" data-confirmar="¿Eliminar la evaluación «<?= e($ev['nombre']) ?>»? Se borrarán sus notas.">
                                <?= csrf_input() ?>
                                <input type="hidden" name="accion" value="eliminar_evaluacion">
                                <input type="hidden" name="id" value="<?= (int) $ev['id_evaluacion'] ?>">
                                <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)"
                                    title="Eliminar">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                    </svg>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>