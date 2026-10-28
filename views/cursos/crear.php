<?php

/**
 * Formulario de alta/edición de curso.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('cursos.gestionar');

$id      = input_int('id');
$editando = $id > 0;

$cursos  = db()->fila("SELECT * FROM cursos WHERE id_curso = :id", [':id' => $id]);

if ($editando && !$cursos) {
    http_response_code(404);
    require_once APP_ROOT . '/public/404.php';
    exit;
}

$errores = [];
$datos   = $cursos ?: ['codigo' => '', 'nombre' => '', 'descripcion' => '', 'creditos' => 3, 'area' => '', 'activo' => 1];

$docentesSeleccionados = $editando
    ? array_map(
        'intval',
        array_column(
            db()->todas(
                "SELECT id_profesor FROM curso_docentes WHERE id_curso = :c",
                [':c' => $id]
            ),
            'id_profesor'
        )
    )
    : [];
$docentes = db()->todas(
    "SELECT p.id_profesor, p.nombres, p.apellidos, p.especialidad, p.activo
       FROM profesores p
      WHERE p.activo = 1
         OR EXISTS (
                SELECT 1 FROM curso_docentes cd
                 WHERE cd.id_profesor = p.id_profesor AND cd.id_curso = :c
            )
      ORDER BY p.activo DESC, p.apellidos, p.nombres",
    [':c' => $editando ? $id : 0]
);

$areasSugeridas = ['Talleres', 'Teoría', 'Música', 'Danza', 'Teatro', 'Práctica'];

if (es_post()) {
    exigir_post();

    $codigo      = mb_strtoupper(input('codigo'));
    $nombre      = trim(input('nombre'));
    $descripcion = trim(input('descripcion'));
    $creditos    = input_int('creditos');
    $area        = trim(input('area'));
    $activo      = isset($_POST['activo']) ? 1 : 0;
    $docentesSeleccionados = [];
    foreach (is_array($_POST['docentes'] ?? null) ? $_POST['docentes'] : [] as $valorDocente) {
        if (!is_scalar($valorDocente)) {
            continue;
        }
        $idProfesor = filter_var($valorDocente, FILTER_VALIDATE_INT);
        if ($idProfesor !== false && $idProfesor > 0) {
            $docentesSeleccionados[] = $idProfesor;
        }
    }
    $docentesSeleccionados = array_values(array_unique($docentesSeleccionados));

    if ($nombre === '') {
        $errores[] = 'El nombre del curso es obligatorio.';
    }
    if (mb_strlen($nombre) > 150) {
        $errores[] = 'El nombre no puede superar los 150 caracteres.';
    }
    if (mb_strlen($codigo) > 20) {
        $errores[] = 'El código no puede superar los 20 caracteres.';
    }
    if (mb_strlen($descripcion) > 600) {
        $errores[] = 'La descripción no puede superar los 600 caracteres.';
    }
    if (mb_strlen($area) > 80) {
        $errores[] = 'El área no puede superar los 80 caracteres.';
    }
    if ($creditos < 0 || $creditos > 12) {
        $errores[] = 'Los créditos deben estar entre 0 y 12.';
    }
    if ($codigo !== '' && db()->valor(
        "SELECT id_curso FROM cursos WHERE codigo = :c AND id_curso <> :id",
        [':c' => $codigo, ':id' => $id]
    )) {
        $errores[] = 'Ese código de curso ya está en uso.';
    }

    foreach ($docentesSeleccionados as $idProfesor) {
        $profesorValido = db()->valor(
            "SELECT id_profesor FROM profesores
              WHERE id_profesor = :profesor
                AND (activo = 1 OR :curso_actual > 0 AND EXISTS (
                    SELECT 1 FROM curso_docentes
                     WHERE id_profesor = :profesor_asignado AND id_curso = :curso_asignado
                ))",
            [
                ':profesor' => $idProfesor,
                ':curso_actual' => $editando ? $id : 0,
                ':profesor_asignado' => $idProfesor,
                ':curso_asignado' => $editando ? $id : 0,
            ]
        );
        if (!$profesorValido) {
            $errores[] = 'La selección incluye un profesor inexistente o inactivo.';
            break;
        }
    }

    if (!$errores) {
        $pdo = db()->conectar();
        try {
            $pdo->beginTransaction();

            if ($editando) {
                $pdo->prepare(
                    "UPDATE cursos SET codigo = :c, nombre = :n, descripcion = :d, creditos = :cr, area = :a, activo = :ac
                      WHERE id_curso = :id"
                )->execute([
                        ':c'  => $codigo ?: null,
                        ':n'  => $nombre,
                        ':d'  => $descripcion ?: null,
                        ':cr' => $creditos,
                        ':a'  => $area ?: null,
                        ':ac' => $activo,
                        ':id' => $id,
                    ]);
                $idCurso = $id;
            } else {
                $pdo->prepare(
                    "INSERT INTO cursos (codigo, nombre, descripcion, creditos, area, activo)
                     VALUES (:c, :n, :d, :cr, :a, :ac)"
                )->execute([
                        ':c'  => $codigo ?: null,
                        ':n'  => $nombre,
                        ':d'  => $descripcion ?: null,
                        ':cr' => $creditos,
                        ':a'  => $area ?: null,
                        ':ac' => $activo,
                    ]);
                $idCurso = (int) $pdo->lastInsertId();
            }

            $pdo->prepare("DELETE FROM curso_docentes WHERE id_curso = :c")
                ->execute([':c' => $idCurso]);
            $asignarDocente = $pdo->prepare(
                "INSERT INTO curso_docentes (id_curso, id_profesor) VALUES (:c, :p)"
            );
            foreach ($docentesSeleccionados as $idProfesor) {
                $asignarDocente->execute([':c' => $idCurso, ':p' => $idProfesor]);
            }

            $pdo->commit();
            Auth::registrar(
                $editando ? 'editar' : 'crear',
                'cursos',
                $idCurso,
                "Curso «{$nombre}» " . ($editando ? 'actualizado' : 'creado') .
                    '; docentes asignados: ' . count($docentesSeleccionados)
            );
            flash_set(
                'success',
                $editando ? 'Curso y asignaciones actualizados correctamente.' : 'Curso y asignaciones creados correctamente.'
            );
            redirigir('listar.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }

    $datos = compact('codigo', 'nombre', 'descripcion', 'creditos', 'area', 'activo');
}

$titulo    = $editando ? 'Editar curso' : 'Nuevo curso';
$subtitulo = $editando ? $cursos['nombre'] : 'Agrega un curso al catálogo académico';
$seccion   = 'cursos';
$ancho     = 'content-narrow';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="course-editor-head">
        <div class="course-editor-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" />
            </svg>
        </div>
        <div class="course-editor-copy">
            <span class="eyebrow"><?= $editando ? 'Edición de catálogo' : 'Catálogo académico' ?></span>
            <h2><?= $editando ? 'Edita el curso' : 'Crea un curso nuevo' ?></h2>
            <p>Completa la información y asigna los docentes que podrán gestionar sus evaluaciones y calificaciones.</p>
        </div>
        <a class="btn btn-ghost btn-sm course-back" href="listar.php">Volver a cursos</a>
    </div>

    <div class="panel-body">
        <?php if ($errores): ?>
            <div class="alert alert-error" role="alert" aria-live="assertive">
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

        <form method="POST">
            <?= csrf_input() ?>

            <div class="course-form">
                <section class="course-form-section" aria-labelledby="course-details-title">
                    <div class="course-section-heading">
                        <span class="course-section-number">01</span>
                        <div>
                            <h3 id="course-details-title">Información del curso</h3>
                            <p>Los campos con <span class="required-mark">*</span> son obligatorios.</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-row course-main-fields">
                            <div class="field">
                                <label for="codigo">Código</label>
                                <input class="input" type="text" id="codigo" name="codigo" maxlength="20"
                                    value="<?= e($datos['codigo']) ?>" placeholder="ART-101">
                                <span class="hint">Opcional · hasta 20 caracteres</span>
                            </div>
                            <div class="field">
                                <label for="creditos">Créditos <span class="required-mark">*</span></label>
                                <input class="input" type="number" id="creditos" name="creditos" min="0" max="12" required
                                    value="<?= e((string) $datos['creditos']) ?>">
                                <span class="hint">De 0 a 12 créditos</span>
                            </div>
                        </div>

                        <div class="field">
                            <label for="nombre">Nombre del curso <span class="required-mark">*</span></label>
                            <input class="input" type="text" id="nombre" name="nombre" required maxlength="150"
                                value="<?= e($datos['nombre']) ?>" placeholder="Fundamentos del Dibujo">
                        </div>

                        <div class="field">
                            <label for="descripcion">Descripción</label>
                            <textarea class="input" id="descripcion" name="descripcion" maxlength="600"
                                placeholder="Contenidos, competencias y metodología de la asignatura."><?= e($datos['descripcion']) ?></textarea>
                        </div>

                        <div class="field">
                            <label for="area">Área de formación</label>
                            <input class="input" type="text" id="area" name="area" maxlength="80" list="areas"
                                value="<?= e($datos['area']) ?>" placeholder="Talleres">
                            <datalist id="areas">
                                <?php foreach ($areasSugeridas as $a): ?>
                                    <option value="<?= e($a) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                </section>

                <section class="course-form-section" aria-labelledby="course-teachers-title">
                    <div class="course-section-heading">
                        <span class="course-section-number">02</span>
                        <div>
                            <h3 id="course-teachers-title">Asignación de docentes</h3>
                            <p>Selecciona quién podrá ver este curso y registrar sus notas.</p>
                        </div>
                        <span class="teacher-count" data-docentes-seleccionados aria-live="polite">
                            <?= count($docentesSeleccionados) ?>
                            <?= count($docentesSeleccionados) === 1 ? 'seleccionado' : 'seleccionados' ?>
                        </span>
                    </div>

                    <?php if (!$docentes): ?>
                        <div class="teacher-empty">
                            <strong>Aún no hay docentes disponibles</strong>
                            <span>Registra primero un profesor activo desde el módulo Profesores.</span>
                        </div>
                    <?php else: ?>
                        <label class="teacher-search" for="buscar-docente">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-4-4" />
                            </svg>
                            <input class="input" type="search" id="buscar-docente"
                                placeholder="Buscar por nombre o especialidad"
                                aria-controls="lista-docentes" data-docente-busqueda autocomplete="off">
                        </label>
                        <div class="teacher-options" id="lista-docentes">
                            <?php foreach ($docentes as $docente): ?>
                                <?php $idProfesor = (int) $docente['id_profesor']; ?>
                                <?php $nombreDocente = $docente['nombres'] . ' ' . $docente['apellidos']; ?>
                                <label class="teacher-option"
                                    data-docente-opcion
                                    data-docente-texto="<?= e(mb_strtolower($nombreDocente . ' ' . ($docente['especialidad'] ?? ''))) ?>">
                                    <input type="checkbox" name="docentes[]" value="<?= $idProfesor ?>"
                                        <?= in_array($idProfesor, $docentesSeleccionados, true) ? 'checked' : '' ?>>
                                    <span class="teacher-checkmark" aria-hidden="true"></span>
                                    <span class="teacher-option-copy">
                                        <strong><?= e($nombreDocente) ?></strong>
                                        <small>
                                            <?= $docente['especialidad'] ? e($docente['especialidad']) : 'Sin especialidad indicada' ?>
                                            <?php if (!(int) $docente['activo']): ?>
                                                <span class="teacher-inactive">Inactivo · asignación existente</span>
                                            <?php endif; ?>
                                        </small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="hint teacher-hint">Los docentes inactivos solo se pueden conservar en cursos donde ya estaban asignados.</p>
                        <p class="teacher-no-results" data-docentes-sin-resultados hidden>No hay docentes que coincidan con la búsqueda.</p>
                    <?php endif; ?>
                </section>

                <section class="course-status-section" aria-label="Disponibilidad del curso">
                    <label class="course-status-control">
                        <span class="course-status-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                        </span>
                        <span class="course-status-copy">
                            <strong>Curso disponible para matrícula</strong>
                            <small>Desactívalo para ocultarlo de nuevas inscripciones.</small>
                        </span>
                        <span class="switch">
                            <input type="checkbox" name="activo" value="1" <?= $datos['activo'] ? 'checked' : '' ?>>
                            <span class="track"></span>
                        </span>
                    </label>
                </section>

                <div class="course-form-actions">
                    <a class="btn btn-ghost" href="listar.php">Cancelar</a>
                    <button class="btn btn-primary" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                            <path d="M17 21v-8H7v8M7 3v5h8" />
                        </svg>
                        <?= $editando ? 'Guardar cambios' : 'Crear curso' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const search = document.querySelector('[data-docente-busqueda]');
    const options = Array.from(document.querySelectorAll('[data-docente-opcion]'));
    const count = document.querySelector('[data-docentes-seleccionados]');
    const empty = document.querySelector('[data-docentes-sin-resultados]');

    if (!search || !options.length) {
        return;
    }

    const normalizar = (texto) => texto
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const actualizar = () => {
        const busqueda = normalizar(search.value.trim());
        let visibles = 0;
        let seleccionados = 0;

        options.forEach((opcion) => {
            const coincide = normalizar(opcion.dataset.docenteTexto || '').includes(busqueda);
            opcion.hidden = !coincide;
            visibles += coincide ? 1 : 0;
            seleccionados += opcion.querySelector('input').checked ? 1 : 0;
        });

        if (count) {
            count.textContent = `${seleccionados} ${seleccionados === 1 ? 'seleccionado' : 'seleccionados'}`;
        }
        if (empty) {
            empty.hidden = visibles > 0;
        }
    };

    search.addEventListener('input', actualizar);
    options.forEach((opcion) => opcion.querySelector('input').addEventListener('change', actualizar));
    actualizar();
})();
</script>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>