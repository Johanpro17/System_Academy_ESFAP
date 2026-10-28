<?php

/**
 * Alta de estudiante: registra la ficha académica, sin crear cuenta de acceso.
 *
 * El alumno no es un usuario del sistema. Su cuenta (si existiera) pertenece
 * a la plataforma de alumnos, que se administra por separado.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('estudiantes.crear');

$especialidades = db()->todas("SELECT id_especialidad, nombre FROM especialidades ORDER BY nombre ASC");

$errores = [];
$datos   = [
    'nombres'         => '',
    'apellidos'       => '',
    'email'           => '',
    'telefono'        => '',
    'id_especialidad' => '',
    'matricula'       => '',
    'anio_ingreso'    => date('Y'),
    'dni'             => '',
];

if (es_post()) {
    exigir_post();

    $nombres        = trim(input('nombres'));
    $apellidos      = trim(input('apellidos'));
    $email          = mb_strtolower(trim(input('email')));
    $telefono       = trim(input('telefono'));
    $idEspecialidad = input_int('id_especialidad');
    $matricula      = mb_strtoupper(trim(input('matricula')));
    $dni            = mb_strtoupper(trim(input('dni')));
    $anioIngreso    = input_int('anio_ingreso');

    if ($nombres === '' || $apellidos === '') {
        $errores[] = 'El nombre y los apellidos son obligatorios.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    }
    if (!$idEspecialidad) {
        $errores[] = 'Selecciona una especialidad.';
    }
    if ($anioIngreso < 1950 || $anioIngreso > (int) date('Y') + 1) {
        $errores[] = 'El año de ingreso no es válido.';
    }
    if ($matricula !== '' && db()->valor(
        "SELECT id_estudiante FROM estudiantes WHERE matricula = :m",
        [':m' => $matricula]
    )) {
        $errores[] = 'Ese número de matrícula ya está registrado.';
    }
    if ($dni !== '' && !preg_match('/^[0-9]{7,15}[A-Za-z]?$/', $dni)) {
        $errores[] = 'El DNI debe tener entre 7 y 15 dígitos (con letra opcional al final).';
    }
    if ($dni !== '' && db()->valor(
        "SELECT id_estudiante FROM estudiantes WHERE dni = :d",
        [':d' => $dni]
    )) {
        $errores[] = 'Ese DNI ya está registrado.';
    }

    if (!$errores) {
        try {
            db()->query(
                "INSERT INTO estudiantes
                    (id_usuario, nombres, apellidos, email, telefono, id_especialidad,
                     dni, matricula, anio_ingreso, estado)
                 VALUES
                    (NULL, :n, :a, :e, :t, :esp, :d, :m, :anio, 'activo')",
                [
                    ':n'    => $nombres,
                    ':a'    => $apellidos,
                    ':e'    => $email !== '' ? $email : null,
                    ':t'    => $telefono !== '' ? $telefono : null,
                    ':esp'  => $idEspecialidad,
                    ':d'    => $dni !== '' ? $dni : null,
                    ':m'    => $matricula !== '' ? $matricula : null,
                    ':anio' => $anioIngreso ?: null,
                ]
            );

            $idEstudiante = (int) db()->conectar()->lastInsertId();
            Auth::registrar('crear', 'estudiantes', $idEstudiante, "Ficha de {$nombres} {$apellidos} registrada");

            flash_set('success', 'Estudiante registrado correctamente.');
            redirigir('listar.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errores[] = 'El DNI o la matrícula ya pertenecen a otra ficha.';
            } else {
                error_log('estudiantes/crear: ' . $e->getMessage());
                $errores[] = 'No fue posible guardar la ficha. Inténtalo de nuevo.';
            }
        }
    }

    $datos = [
        'nombres'         => $nombres,
        'apellidos'       => $apellidos,
        'email'           => $email,
        'telefono'        => $telefono,
        'id_especialidad' => $idEspecialidad,
        'matricula'       => $matricula,
        'anio_ingreso'    => $anioIngreso,
        'dni'             => $dni,
    ];
}

$titulo    = 'Nuevo estudiante';
$subtitulo = 'Registra la ficha académica de un alumno';
$seccion   = 'estudiantes';
$ancho     = 'content-narrow';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Registrar estudiante</h2>
            <p>La ficha es un dato académico: no crea una cuenta de acceso al sistema</p>
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

        <form method="POST" novalidate>
            <?= csrf_input() ?>

            <div class="form-grid">
                <h3 class="section-title" style="margin:0">Datos personales</h3>

                <div class="form-row">
                    <div class="field">
                        <label for="nombres">Nombres *</label>
                        <input class="input" type="text" id="nombres" name="nombres" maxlength="100"
                            value="<?= e($datos['nombres']) ?>" placeholder="Ana María" required>
                    </div>
                    <div class="field">
                        <label for="apellidos">Apellidos *</label>
                        <input class="input" type="text" id="apellidos" name="apellidos" maxlength="100"
                            value="<?= e($datos['apellidos']) ?>" placeholder="García López" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="email">Correo de contacto</label>
                        <input class="input" type="email" id="email" name="email" maxlength="150"
                            value="<?= e($datos['email']) ?>" placeholder="alumno@correo.com">
                        <span class="hint">Solo para contacto y reportes. No habilita el acceso al sistema.</span>
                    </div>
                    <div class="field">
                        <label for="telefono">Teléfono</label>
                        <input class="input" type="tel" id="telefono" name="telefono" maxlength="30"
                            value="<?= e($datos['telefono']) ?>" placeholder="+51 900 000 000">
                    </div>
                </div>

                <div class="divider" style="margin:4px 0"></div>
                <h3 class="section-title" style="margin:0">Ficha académica</h3>

                <div class="form-row">
                    <div class="field">
                        <label for="id_especialidad">Especialidad *</label>
                        <select class="input" id="id_especialidad" name="id_especialidad" required>
                            <option value="">Selecciona…</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= (int) $esp['id_especialidad'] ?>"
                                    <?= (int) $datos['id_especialidad'] === (int) $esp['id_especialidad'] ? 'selected' : '' ?>>
                                    <?= e($esp['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="dni">DNI</label>
                        <input class="input" type="text" id="dni" name="dni" maxlength="20"
                            inputmode="numeric" value="<?= e($datos['dni']) ?>" placeholder="12345678"
                            pattern="[0-9]{7,15}[A-Za-z]?">
                        <span class="hint">7 a 15 dígitos. Identifica al alumno en las constancias.</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="matricula">Número de matrícula</label>
                        <input class="input" type="text" id="matricula" name="matricula" maxlength="30"
                            value="<?= e($datos['matricula']) ?>" placeholder="ESFAP-2026-001">
                    </div>
                    <div class="field">
                        <label for="anio_ingreso">Año de ingreso</label>
                        <input class="input" type="number" id="anio_ingreso" name="anio_ingreso"
                            min="1950" max="<?= (int) date('Y') + 1 ?>" value="<?= e((string) $datos['anio_ingreso']) ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                            <path d="M17 21v-8H7v8M7 3v5h8" />
                        </svg>
                        Guardar ficha
                    </button>
                    <a class="btn btn-ghost" href="listar.php">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>
