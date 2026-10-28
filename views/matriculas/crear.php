<?php

/**
 * Alta de matrícula: selección por especialidad, buscador, resumen previo
 * y redirección a la constancia imprimible con folio.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('matriculas.crear');

$errores = [];

/* Matrícula del estudiante, con DNI y especialidad, agrupadas por especialidad. */
$estudiantes = db()->todas(
    "SELECT e.id_estudiante, e.matricula, e.dni,
            CONCAT(e.nombres,' ',e.apellidos) AS nombre,
            esp.id_especialidad, esp.nombre AS especialidad
       FROM estudiantes e
       JOIN especialidades esp ON esp.id_especialidad = e.id_especialidad
      WHERE e.estado = 'activo'
      ORDER BY esp.nombre ASC, e.apellidos ASC, e.nombres ASC"
);

$porEspecialidad = [];
foreach ($estudiantes as $e) {
    $porEspecialidad[$e['especialidad']][] = $e;
}

$cursos = db()->todas(
    "SELECT id_curso, codigo, nombre, creditos, area
       FROM cursos WHERE activo = 1 ORDER BY nombre ASC"
);

$periodoSugerido = date('Y') . '-1';

$datos = [
    'id_estudiante' => input_int('id_estudiante'),
    'id_curso'      => input_int('id_curso'),
    'periodo'       => input('periodo') ?: $periodoSugerido,
];

if (es_post()) {
    exigir_post();

    $idEstudiante = input_int('id_estudiante');
    $idCurso      = input_int('id_curso');
    $periodo      = mb_strtoupper(input('periodo'));

    if (!$idEstudiante) {
        $errores[] = 'Selecciona un estudiante.';
    }
    if (!$idCurso) {
        $errores[] = 'Selecciona un curso.';
    }
    if (!preg_match('/^\d{4}-[12]$/', $periodo)) {
        $errores[] = 'El periodo debe tener el formato AAAA-1 o AAAA-2.';
    }

    if ($idEstudiante && $idCurso && $periodo && db()->valor(
        "SELECT id_matricula FROM matriculas
          WHERE id_estudiante = :e AND id_curso = :c AND periodo = :p",
        [':e' => $idEstudiante, ':c' => $idCurso, ':p' => $periodo]
    )) {
        $errores[] = 'Este estudiante ya está matriculado en ese curso para el periodo indicado.';
    }

    if (!$errores) {
        try {
            db()->query(
                "INSERT INTO matriculas (id_estudiante, id_curso, periodo, estado, fecha_matricula)
                 VALUES (:e, :c, :p, 'matriculado', NOW())",
                [':e' => $idEstudiante, ':c' => $idCurso, ':p' => $periodo]
            );

            $idMatricula = (int) db()->conectar()->lastInsertId();
            Auth::registrar('crear', 'matriculas', $idMatricula, "Matrícula {$periodo} creada");

            flash_set('success', 'Matrícula registrada correctamente.');
            redirigir('constancia.php?id=' . $idMatricula);
        } catch (PDOException $e) {
            /* La clave uk_matricula impide la inscripción duplicada aunque
               dos envíos simultáneos superen la comprobación previa */
            if ($e->getCode() === '23000') {
                $errores[] = 'Este estudiante ya está matriculado en ese curso para el periodo indicado.';
            } else {
                error_log('matriculas/crear: ' . $e->getMessage());
                $errores[] = 'No fue posible registrar la matrícula. Inténtalo de nuevo.';
            }
        } catch (Throwable $e) {
            error_log('matriculas/crear: ' . $e->getMessage());
            $errores[] = 'No fue posible registrar la matrícula. Inténtalo de nuevo.';
        }
    }

    $datos = ['id_estudiante' => $idEstudiante, 'id_curso' => $idCurso, 'periodo' => $periodo];
}

$titulo    = 'Nueva matrícula';
$subtitulo = 'Inscribe un estudiante en un curso del periodo';
$seccion   = 'matriculas';
$ancho     = 'content-narrow';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Registrar inscripción</h2>
            <p>Busca al alumno por código o DNI, revisa el resumen y confirma</p>
        </div>
        <div class="panel-actions">
            <a class="btn btn-ghost btn-sm" href="listar.php">Volver al listado</a>
        </div>
    </div>

    <div class="panel-body">
        <?php if ($errores): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                </svg>
                <div>
                    <ul style="margin:0;padding-left:16px">
                        <?php foreach ($errores as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$estudiantes || !$cursos): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                </svg>
                <h3>Faltan datos previos</h3>
                <p>
                    <?= !$estudiantes
                        ? 'No hay estudiantes activos registrados. Crea uno primero.'
                        : 'No hay cursos activos. Crea uno primero.' ?>
                </p>
                <a class="btn btn-primary"
                    href="<?= e(base_url($estudiantes ? 'views/cursos/crear.php' : 'views/estudiantes/crear.php')) ?>">
                    Ir a <?= $estudiantes ? 'crear curso' : 'crear estudiante' ?>
                </a>
            </div>
        <?php else: ?>
            <form method="POST" novalidate data-form-matricula>
                <?= csrf_input() ?>

                <div class="form-grid">
                    <div class="field">
                        <label for="buscar-estudiante">Buscar estudiante</label>
                        <div class="search-wrap">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" />
                                <path d="M21 21l-4.35-4.35" />
                            </svg>
                            <input class="input" type="search" id="buscar-estudiante"
                                data-busqueda-estudiante autocomplete="off"
                                placeholder="Código, nombre, DNI o especialidad…">
                        </div>
                        <span class="hint" data-sin-coincidencias hidden>
                            Ningún alumno coincide con esa búsqueda.
                        </span>
                    </div>

                    <div class="field">
                        <label for="id_estudiante">Estudiante *</label>
                        <select class="input" id="id_estudiante" name="id_estudiante" required>
                            <option value="">Selecciona un estudiante…</option>
                            <?php foreach ($porEspecialidad as $nombreEsp => $alumnos): ?>
                                <optgroup label="<?= e($nombreEsp) ?>">
                                    <?php foreach ($alumnos as $e):
                                        $codigo = $e['matricula'] !== '' && $e['matricula'] !== null
                                            ? $e['matricula'] : 'S/CÓDIGO';
                                        $dni    = $e['dni'] !== '' && $e['dni'] !== null
                                            ? $e['dni'] : 'sin DNI';
                                        $texto  = $codigo . ' · ' . $e['nombre'] . ' · DNI ' . $dni . ' · ' . $e['especialidad'];
                                        $buscar = mb_strtolower($codigo . ' ' . $e['nombre'] . ' ' . $dni . ' ' . $e['especialidad']);
                                    ?>
                                        <option value="<?= (int) $e['id_estudiante'] ?>"
                                            data-buscar="<?= e($buscar) ?>"
                                            data-codigo="<?= e($codigo) ?>"
                                            data-nombre="<?= e($e['nombre']) ?>"
                                            data-dni="<?= e($dni) ?>"
                                            data-especialidad="<?= e($e['especialidad']) ?>"
                                            <?= $datos['id_estudiante'] === (int) $e['id_estudiante'] ? 'selected' : '' ?>>
                                            <?= e($texto) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="id_curso">Curso *</label>
                            <select class="input" id="id_curso" name="id_curso" required>
                                <option value="">Selecciona un curso…</option>
                                <?php foreach ($cursos as $c): ?>
                                    <option value="<?= (int) $c['id_curso'] ?>"
                                        data-nombre="<?= e(($c['codigo'] ? $c['codigo'] . ' · ' : '') . $c['nombre']) ?>"
                                        data-creditos="<?= (int) $c['creditos'] ?>"
                                        <?= $datos['id_curso'] === (int) $c['id_curso'] ? 'selected' : '' ?>>
                                        <?= e(($c['codigo'] ? $c['codigo'] . ' · ' : '') . $c['nombre']) ?>
                                        (<?= (int) $c['creditos'] ?> cr)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="periodo">Periodo *</label>
                            <select class="input" id="periodo" name="periodo" required>
                                <?php
                                $anios = range((int) date('Y') - 2, (int) date('Y') + 1);
                                rsort($anios);
                                $opciones = [];
                                foreach ($anios as $a) {
                                    $opciones[] = "{$a}-1";
                                    $opciones[] = "{$a}-2";
                                }
                                ?>
                                <?php foreach ($opciones as $op): ?>
                                    <option value="<?= e($op) ?>" <?= $datos['periodo'] === $op ? 'selected' : '' ?>>
                                        <?= e(str_replace('-', ' · Semestre ', $op)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hint">Formato AAAA-1 (primer semestre) o AAAA-2 (segundo semestre).</span>
                        </div>
                    </div>

                    <section class="resumen" data-resumen-matricula aria-live="polite">
                        <header class="resumen-head">
                            <h3>Resumen de la matrícula</h3>
                            <span class="badge badge-amber" data-res="estado">Incompleta</span>
                        </header>
                        <div class="resumen-grid">
                            <div class="resumen-item">
                                <span class="resumen-label">Código</span>
                                <b data-res="codigo">—</b>
                            </div>
                            <div class="resumen-item">
                                <span class="resumen-label">DNI</span>
                                <b data-res="dni">—</b>
                            </div>
                            <div class="resumen-item resumen-ancho">
                                <span class="resumen-label">Estudiante</span>
                                <b data-res="estudiante">—</b>
                            </div>
                            <div class="resumen-item resumen-ancho">
                                <span class="resumen-label">Especialidad</span>
                                <b data-res="especialidad">—</b>
                            </div>
                            <div class="resumen-item resumen-ancho">
                                <span class="resumen-label">Curso</span>
                                <b data-res="curso">—</b>
                            </div>
                            <div class="resumen-item">
                                <span class="resumen-label">Créditos</span>
                                <b data-res="creditos">—</b>
                            </div>
                            <div class="resumen-item">
                                <span class="resumen-label">Periodo</span>
                                <b data-res="periodo">—</b>
                            </div>
                        </div>
                        <p class="hint" data-res="aviso">Selecciona estudiante, curso y periodo para poder confirmar.</p>
                    </section>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" data-enviar disabled>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                            </svg>
                            Confirmar matrícula
                        </button>
                        <a class="btn btn-ghost" href="listar.php">Cancelar</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>
