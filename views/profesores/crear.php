<?php

/**
 * Formulario de alta/edición de profesor.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('profesores.gestionar');

$id       = input_int('id');
$editando = $id > 0;

$profesor = db()->fila("SELECT * FROM profesores WHERE id_profesor = :id", [':id' => $id]);

if ($editando && !$profesor) {
    http_response_code(404);
    require_once APP_ROOT . '/public/404.php';
    exit;
}

$cuenta = !empty($profesor['id_usuario'])
    ? db()->fila("SELECT id_usuario, email, id_rol FROM usuarios WHERE id_usuario = :id", [':id' => $profesor['id_usuario']])
    : null;

$errores = [];
$datos   = array_replace($profesor ?: [
    'nombres' => '', 'apellidos' => '', 'especialidad' => '',
    'email' => '', 'telefono' => '', 'titulo' => '', 'activo' => 1,
], $cuenta ? ['email' => $cuenta['email']] : []);

$especialidades = db()->todas("SELECT nombre FROM especialidades ORDER BY nombre ASC");

if (es_post()) {
    exigir_post();

    $nombres      = trim(input('nombres'));
    $apellidos    = trim(input('apellidos'));
    $especialidad = trim(input('especialidad'));
    $email        = mb_strtolower(input('email'));
    $telefono     = input('telefono');
    $titulo       = trim(input('titulo'));
    $activo       = isset($_POST['activo']) ? 1 : 0;
    $password     = (string) ($_POST['password'] ?? '');
    $idUsuarioActual = (int) ($profesor['id_usuario'] ?? 0);

    if ($nombres === '' || $apellidos === '') {
        $errores[] = 'El nombre y los apellidos son obligatorios.';
    }
    if ($cuenta && (int) $cuenta['id_rol'] !== Auth::ROL_PROFESOR) {
        $errores[] = 'La cuenta vinculada ya no tiene el perfil Profesor. Corrige el perfil desde Administración → Usuarios.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Indica un correo electrónico válido para la cuenta del profesor.';
    }
    if (db()->valor(
        "SELECT id_profesor FROM profesores WHERE email = :e AND id_profesor <> :id",
        [':e' => $email, ':id' => $id]
    )) {
        $errores[] = 'Ese correo ya está registrado para otro profesor.';
    }

    $usuarioPorCorreo = $email !== ''
        ? db()->fila("SELECT id_usuario, id_rol FROM usuarios WHERE email = :e", [':e' => $email])
        : null;
    if ($usuarioPorCorreo && (int) $usuarioPorCorreo['id_rol'] !== Auth::ROL_PROFESOR) {
        $errores[] = 'Ese correo ya pertenece a una cuenta con otro perfil.';
    }
    if ($usuarioPorCorreo && (int) $usuarioPorCorreo['id_usuario'] !== $idUsuarioActual) {
        $perfilVinculado = db()->valor(
            "SELECT id_profesor FROM profesores
              WHERE id_usuario = :u AND id_profesor <> :id LIMIT 1",
            [':u' => $usuarioPorCorreo['id_usuario'], ':id' => $id]
        );
        if ($perfilVinculado) {
            $errores[] = 'Esa cuenta ya está vinculada a otro profesor.';
        }
    }
    if (!$cuenta && (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))) {
        $errores[] = 'Para crear o vincular la cuenta, define una contraseña de al menos 8 caracteres con letras y números.';
    } elseif ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres e incluir letras y números.';
    }

    if (!$errores) {
        $pdo = db()->conectar();
        try {
            $pdo->beginTransaction();

            if ($cuenta && (int) $cuenta['id_usuario'] === $idUsuarioActual) {
                $sqlCuenta = "UPDATE usuarios
                                 SET nombres = :n, apellidos = :a, email = :e, telefono = :t, activo = :ac";
                if ($password !== '') {
                    $sqlCuenta .= ", password_hash = :h";
                }
                $sqlCuenta .= " WHERE id_usuario = :id AND id_rol = :rol";
                $paramsCuenta = [
                    ':n' => $nombres,
                    ':a' => $apellidos,
                    ':e' => $email,
                    ':t' => $telefono ?: null,
                    ':ac' => $activo,
                    ':id' => $idUsuarioActual,
                    ':rol' => Auth::ROL_PROFESOR,
                ];
                if ($password !== '') {
                    $paramsCuenta[':h'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $pdo->prepare($sqlCuenta)->execute($paramsCuenta);
                $idUsuario = $idUsuarioActual;
            } elseif ($usuarioPorCorreo) {
                $pdo->prepare(
                    "UPDATE usuarios
                        SET nombres = :n, apellidos = :a, password_hash = :h, telefono = :t, activo = :ac
                      WHERE id_usuario = :id AND id_rol = :rol"
                )->execute([
                    ':n' => $nombres,
                    ':a' => $apellidos,
                    ':h' => password_hash($password, PASSWORD_DEFAULT),
                    ':t' => $telefono ?: null,
                    ':ac' => $activo,
                    ':id' => $usuarioPorCorreo['id_usuario'],
                    ':rol' => Auth::ROL_PROFESOR,
                ]);
                $idUsuario = (int) $usuarioPorCorreo['id_usuario'];
            } else {
                $pdo->prepare(
                    "INSERT INTO usuarios (nombres, apellidos, email, password_hash, id_rol, telefono, activo)
                     VALUES (:n, :a, :e, :h, :r, :t, :ac)"
                )->execute([
                    ':n' => $nombres,
                    ':a' => $apellidos,
                    ':e' => $email,
                    ':h' => password_hash($password, PASSWORD_DEFAULT),
                    ':r' => Auth::ROL_PROFESOR,
                    ':t' => $telefono ?: null,
                    ':ac' => $activo,
                ]);
                $idUsuario = (int) $pdo->lastInsertId();
            }

            if ($editando) {
                $pdo->prepare(
                    "UPDATE profesores
                        SET nombres = :n, apellidos = :a, especialidad = :e, email = :em,
                            telefono = :t, titulo = :ti, activo = :ac, id_usuario = :u
                      WHERE id_profesor = :id",
                )->execute([
                        ':n'  => $nombres,
                        ':a'  => $apellidos,
                        ':e'  => $especialidad ?: null,
                        ':em' => $email ?: null,
                        ':t'  => $telefono ?: null,
                        ':ti' => $titulo ?: null,
                        ':ac' => $activo,
                        ':u'  => $idUsuario,
                        ':id' => $id,
                    ]);
                $idProfesor = $id;
            } else {
                $pdo->prepare(
                    "INSERT INTO profesores (id_usuario, nombres, apellidos, especialidad, email, telefono, titulo, activo)
                     VALUES (:u, :n, :a, :e, :em, :t, :ti, :ac)"
                )->execute([
                        ':u'  => $idUsuario,
                        ':n'  => $nombres,
                        ':a'  => $apellidos,
                        ':e'  => $especialidad ?: null,
                        ':em' => $email ?: null,
                        ':t'  => $telefono ?: null,
                        ':ti' => $titulo ?: null,
                        ':ac' => $activo,
                    ]);
                $idProfesor = (int) $pdo->lastInsertId();
            }

            $pdo->commit();
            Auth::registrar(
                $editando ? 'editar' : 'crear',
                'profesores',
                $idProfesor,
                "Profesor {$nombres} {$apellidos} " . ($editando ? 'actualizado' : 'creado') . ' con cuenta vinculada'
            );
            flash_set('success', $editando
                ? 'Profesor y cuenta de acceso actualizados correctamente.'
                : 'Profesor y cuenta de acceso creados correctamente.');
            redirigir('listar.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }

    $datos = compact('nombres', 'apellidos', 'especialidad', 'email', 'telefono', 'titulo', 'activo');
    $datos['id_usuario'] = $idUsuarioActual;
}

$titulo    = $editando ? 'Editar profesor' : 'Nuevo profesor';
$subtitulo = $editando ? $profesor['nombres'] . ' ' . $profesor['apellidos'] : 'Registra un profesor en la plantilla';
$seccion   = 'profesores';
$ancho     = 'content-narrow';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2><?= $editando ? 'Editar profesor' : 'Registrar profesor' ?></h2>
            <p>Datos del profesor y credenciales personales de acceso</p>
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
                <div class="form-row">
                    <div class="field">
                        <label for="nombres">Nombres *</label>
                        <input class="input" type="text" id="nombres" name="nombres" required maxlength="100"
                            value="<?= e($datos['nombres']) ?>">
                    </div>
                    <div class="field">
                        <label for="apellidos">Apellidos *</label>
                        <input class="input" type="text" id="apellidos" name="apellidos" required maxlength="100"
                            value="<?= e($datos['apellidos']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="especialidad">Especialidad</label>
                        <input class="input" type="text" id="especialidad" name="especialidad" maxlength="100"
                            list="especialidades" value="<?= e($datos['especialidad']) ?>"
                            placeholder="Pintura">
                        <datalist id="especialidades">
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= e($esp['nombre']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="field">
                        <label for="titulo">Título académico</label>
                        <input class="input" type="text" id="titulo" name="titulo" maxlength="120"
                            value="<?= e($datos['titulo']) ?>" placeholder="Licenciado en Bellas Artes">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="email">Correo de acceso *</label>
                        <input class="input" type="email" id="email" name="email" maxlength="150" required
                            value="<?= e($datos['email']) ?>" placeholder="profesor@escuela.edu">
                    </div>
                    <div class="field">
                        <label for="telefono">Teléfono</label>
                        <input class="input" type="tel" id="telefono" name="telefono" maxlength="30"
                            value="<?= e($datos['telefono']) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="password"><?= $cuenta ? 'Nueva contraseña (opcional)' : 'Contraseña de acceso *' ?></label>
                    <input class="input" type="password" id="password" name="password"
                        <?= $cuenta ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                    <span class="hint">
                        <?= $cuenta
                            ? 'Déjala vacía para conservar la actual. Si la cambias, comparte la nueva contraseña con el profesor de forma segura.'
                            : 'La cuenta se crea con rol Profesor. Mínimo 8 caracteres, con letras y números.' ?>
                    </span>
                </div>

                <div class="field">
                    <label class="switch">
                        <input type="checkbox" name="activo" value="1" <?= $datos['activo'] ? 'checked' : '' ?>>
                        <span class="track"></span>
                        <span>Profesor activo (disponible para asignación de cursos)</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                            <path d="M17 21v-8H7v8M7 3v5h8" />
                        </svg>
                        <?= $editando ? 'Guardar cambios' : 'Crear profesor' ?>
                    </button>
                    <a class="btn btn-ghost" href="listar.php">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>