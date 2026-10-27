<?php

/**
 * Alta de estudiante: crea el usuario y su ficha académica.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_admin();

$especialidades = db()->todas("SELECT id_especialidad, nombre FROM especialidades ORDER BY nombre ASC");

$errores = [];
$datos   = ['id_usuario' => '', 'id_especialidad' => '', 'matricula' => '', 'anio_ingreso' => date('Y'), 'dni' => ''];
$idRol   = Auth::ROL_ESTUDIANTE;

/* Usuarios disponibles (activos y sin ficha de estudiante) */
$usuariosDisponibles = db()->todas(
    "SELECT u.id_usuario, u.nombres, u.apellidos, u.email, r.nombre AS rol
       FROM usuarios u
       LEFT JOIN estudiantes e ON e.id_usuario = u.id_usuario
       JOIN roles r ON r.id_rol = u.id_rol
      WHERE u.activo = 1 AND u.id_rol = :rol AND e.id_estudiante IS NULL
      ORDER BY u.apellidos, u.nombres",
    [':rol' => Auth::ROL_ESTUDIANTE]
);

if (es_post()) {
    exigir_post();

    $idUsuario      = input_int('id_usuario');
    $idEspecialidad = input_int('id_especialidad');
    $matricula      = mb_strtoupper(input('matricula'));
    $dni            = mb_substr(input('dni'), 0, 20);
    $anioIngreso    = input_int('anio_ingreso');
    $nombres        = trim(input('nombres'));
    $apellidos      = trim(input('apellidos'));
    $email          = mb_strtolower(input('email'));
    $telefono       = input('telefono');
    $password       = (string) ($_POST['password'] ?? '');
    $idRol          = Auth::ROL_ESTUDIANTE;

    if (!$idUsuario) {
        // Se debe crear un usuario nuevo
        if ($nombres === '' || $apellidos === '') {
            $errores[] = 'El nombre y los apellidos son obligatorios.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres e incluir letras y números.';
        }

        if ($email !== '' && db()->valor("SELECT id_usuario FROM usuarios WHERE email = :e", [':e' => $email])) {
            $errores[] = 'Ya existe un usuario registrado con ese correo.';
        }
    }

    if ($idUsuario) {
        $usuarioValido = db()->valor(
            "SELECT id_usuario FROM usuarios
              WHERE id_usuario = :id AND id_rol = :rol AND activo = 1
                AND NOT EXISTS (SELECT 1 FROM estudiantes WHERE id_usuario = :id2)",
            [':id' => $idUsuario, ':id2' => $idUsuario, ':rol' => Auth::ROL_ESTUDIANTE]
        );
        if (!$usuarioValido) {
            $errores[] = 'Solo puedes vincular una cuenta activa de estudiante que no tenga otra ficha.';
        }
    }

    if (!$idEspecialidad) {
        $errores[] = 'Selecciona una especialidad.';
    }
    if ($anioIngreso < 1950 || $anioIngreso > (int) date('Y') + 1) {
        $errores[] = 'El año de ingreso no es válido.';
    }
    if ($matricula !== '' && db()->valor("SELECT id_estudiante FROM estudiantes WHERE matricula = :m", [':m' => $matricula])) {
        $errores[] = 'Ese número de matrícula ya está registrado.';
    }
    if ($dni !== '' && !preg_match('/^[0-9]{7,15}[A-Za-z]?$/', $dni)) {
        $errores[] = 'El DNI debe tener entre 7 y 15 dígitos (con letra opcional al final).';
    }
    if ($dni !== '' && db()->valor("SELECT id_estudiante FROM estudiantes WHERE dni = :d", [':d' => $dni])) {
        $errores[] = 'Ese DNI ya está registrado.';
    }

    if (!$errores) {
        $pdo = db()->conectar();

        try {
            $pdo->beginTransaction();

            if (!$idUsuario) {
                $nuevoId = null;
                $stmt = $pdo->prepare(
                    "INSERT INTO usuarios (nombres, apellidos, email, password_hash, id_rol, telefono, activo)
                     VALUES (:n, :a, :e, :h, :r, :t, 1)"
                );
                $stmt->execute([
                    ':n' => $nombres,
                    ':a' => $apellidos,
                    ':e' => $email,
                    ':h' => password_hash($password, PASSWORD_DEFAULT),
                    ':r' => $idRol,
                    ':t' => $telefono ?: null,
                ]);
                $nuevoId = (int) $pdo->lastInsertId();
            } else {
                $nuevoId = $idUsuario;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO estudiantes (id_usuario, id_especialidad, dni, matricula, anio_ingreso, estado)
                 VALUES (:u, :e, :d, :m, :a, 'activo')"
            );
            $stmt->execute([
                ':u' => $nuevoId,
                ':e' => $idEspecialidad,
                ':d' => $dni ?: null,
                ':m' => $matricula ?: null,
                ':a' => $anioIngreso ?: null,
            ]);

            $idEstudiante = (int) $pdo->lastInsertId();
            $pdo->commit();

            Auth::registrar('crear', 'estudiantes', $idEstudiante, "Estudiante {$nombres} {$apellidos} registrado");

            Notificacion::crear(
                $nuevoId,
                'Bienvenido al sistema',
                'Tu ficha fue creada. Ya puedes consultar tus calificaciones.',
                'success',
                'views/estudiantes/mis_calificaciones.php'
            );

            flash_set('success', 'Estudiante registrado correctamente.');
            redirigir('listar.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }

    $datos = [
        'id_usuario'      => $idUsuario,
        'id_especialidad' => $idEspecialidad,
        'matricula'       => $matricula,
        'dni'             => $dni,
        'anio_ingreso'    => $anioIngreso,
    ];
}

$titulo    = 'Nuevo estudiante';
$subtitulo = 'Registra un alumno y su ficha académica';
$seccion   = 'estudiantes';
$ancho     = 'content-narrow';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Registrar estudiante</h2>
            <p>Crea una cuenta de estudiante o vincula una cuenta de estudiante existente</p>
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
                <div class="field">
                    <label for="id_usuario">Vincular usuario existente</label>
                    <select class="input" id="id_usuario" name="id_usuario">
                        <option value="0">— Crear usuario nuevo —</option>
                        <?php foreach ($usuariosDisponibles as $u): ?>
                            <option value="<?= (int) $u['id_usuario'] ?>"
                                <?= $datos['id_usuario'] === (int) $u['id_usuario'] ? 'selected' : '' ?>>
                                <?= e($u['nombres'] . ' ' . $u['apellidos']) ?> · <?= e($u['rol']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Solo aparecen cuentas activas con rol de estudiante y sin ficha asociada.</span>
                </div>

                <div class="divider" style="margin:4px 0"></div>
                <h3 class="section-title" style="margin:0">Datos del usuario nuevo</h3>

                <div class="form-row">
                    <div class="field">
                        <label for="nombres">Nombres *</label>
                        <input class="input" type="text" id="nombres" name="nombres" maxlength="100"
                            value="<?= e($nombres ?? '') ?>" placeholder="Ana María">
                    </div>
                    <div class="field">
                        <label for="apellidos">Apellidos *</label>
                        <input class="input" type="text" id="apellidos" name="apellidos" maxlength="100"
                            value="<?= e($apellidos ?? '') ?>" placeholder="García López">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="email">Correo electrónico *</label>
                        <input class="input" type="email" id="email" name="email" maxlength="150"
                            value="<?= e($email ?? '') ?>" placeholder="estudiante@escuela.edu">
                    </div>
                    <div class="field">
                        <label for="telefono">Teléfono</label>
                        <input class="input" type="tel" id="telefono" name="telefono" maxlength="30"
                            value="<?= e($telefono ?? '') ?>" placeholder="+51 900 000 000">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="password">Contraseña *</label>
                        <div class="input-group">
                            <input class="input" type="password" id="password" name="password" minlength="8"
                                autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                            <button class="input-action" type="button" data-toggle-password="password"
                                aria-label="Mostrar contraseña">
                                <svg viewBox="0 0 24 24" aria-hidden="true" style="display:block">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg viewBox="0 0 24 24" aria-hidden="true" style="display:none">
                                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M1 1l22 22" />
                                </svg>
                            </button>
                        </div>
                        <span class="hint">Se ignora si vinculaste un usuario existente.</span>
                    </div>
                    <div class="field">
                        <label>Perfil de acceso</label>
                        <input class="input" value="Estudiante · consulta de calificaciones" disabled>
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
                                    <?= $datos['id_especialidad'] === (int) $esp['id_especialidad'] ? 'selected' : '' ?>>
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
                        Guardar estudiante
                    </button>
                    <a class="btn btn-ghost" href="listar.php">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>