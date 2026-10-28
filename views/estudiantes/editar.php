<?php

/**
 * Edición de la ficha de un estudiante.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('estudiantes.editar');

$id = input_int('id');

$estudiante = db()->fila(
    "SELECT e.id_estudiante, e.id_usuario, e.id_especialidad, e.dni, e.matricula, e.anio_ingreso, e.estado,
            u.nombres, u.apellidos, u.email, u.telefono, u.activo
       FROM estudiantes e
       JOIN usuarios u ON u.id_usuario = e.id_usuario
      WHERE e.id_estudiante = :id",
    [':id' => $id]
);

if (!$estudiante) {
    http_response_code(404);
    require_once APP_ROOT . '/public/404.php';
    exit;
}

$errores = [];
$datos   = $estudiante;
$especialidades = db()->todas("SELECT id_especialidad, nombre FROM especialidades ORDER BY nombre ASC");

if (es_post()) {
    exigir_post();

    $idEspecialidad = input_int('id_especialidad');
    $matricula      = mb_strtoupper(input('matricula'));
    $dni            = mb_substr(input('dni'), 0, 20);
    $anioIngreso    = input_int('anio_ingreso');
    $estado         = input('estado');
    $nombres        = trim(input('nombres'));
    $apellidos      = trim(input('apellidos'));
    $email          = mb_strtolower(input('email'));
    $telefono       = input('telefono');
    $activo         = isset($_POST['activo']) ? 1 : 0;

    if ($nombres === '' || $apellidos === '') {
        $errores[] = 'El nombre y los apellidos son obligatorios.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    }
    if (db()->valor("SELECT id_usuario FROM usuarios WHERE email = :e AND id_usuario <> :id",
        [':e' => $email, ':id' => $estudiante['id_usuario']])) {
        $errores[] = 'Ese correo ya está en uso por otro usuario.';
    }
    if (!$idEspecialidad) {
        $errores[] = 'Selecciona una especialidad.';
    }
    if (!in_array($estado, ['activo', 'egresado', 'retirado'], true)) {
        $estado = 'activo';
    }
    if ($matricula !== '' && db()->valor(
        "SELECT id_estudiante FROM estudiantes WHERE matricula = :m AND id_estudiante <> :id",
        [':m' => $matricula, ':id' => $id]
    )) {
        $errores[] = 'Ese número de matrícula ya está registrado.';
    }
    if ($dni !== '' && !preg_match('/^[0-9]{7,15}[A-Za-z]?$/', $dni)) {
        $errores[] = 'El DNI debe tener entre 7 y 15 dígitos (con letra opcional al final).';
    }
    if ($dni !== '' && db()->valor(
        "SELECT id_estudiante FROM estudiantes WHERE dni = :d AND id_estudiante <> :id",
        [':d' => $dni, ':id' => $id]
    )) {
        $errores[] = 'Ese DNI ya está registrado.';
    }

    if (!$errores) {
        $pdo = db()->conectar();

        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                "UPDATE usuarios SET nombres = :n, apellidos = :a, email = :e, telefono = :t, activo = :ac
                  WHERE id_usuario = :id"
            )->execute([
                ':n'  => $nombres,
                ':a'  => $apellidos,
                ':e'  => $email,
                ':t'  => $telefono ?: null,
                ':ac' => $activo,
                ':id' => $estudiante['id_usuario'],
            ]);

            $pdo->prepare(
                "UPDATE estudiantes
                    SET id_especialidad = :e, dni = :d, matricula = :m, anio_ingreso = :a, estado = :es
                  WHERE id_estudiante = :id"
            )->execute([
                ':e'  => $idEspecialidad,
                ':d'  => $dni ?: null,
                ':m'  => $matricula ?: null,
                ':a'  => $anioIngreso ?: null,
                ':es' => $estado,
                ':id' => $id,
            ]);

            $pdo->commit();

            Auth::registrar('editar', 'estudiantes', $id, "Ficha de {$nombres} {$apellidos} actualizada");

            flash_set('success', 'Estudiante actualizado correctamente.');
            redirigir('listar.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }

    $datos = array_merge($estudiante, [
        'nombres'         => $nombres,
        'apellidos'       => $apellidos,
        'email'           => $email,
        'telefono'        => $telefono,
        'id_especialidad' => $idEspecialidad,
        'matricula'       => $matricula,
        'anio_ingreso'    => $anioIngreso,
        'estado'          => $estado,
        'activo'          => $activo,
    ]);
}

$titulo    = 'Editar estudiante';
$subtitulo = $estudiante['nombres'] . ' ' . $estudiante['apellidos'];
$seccion   = 'estudiantes';
$ancho     = 'content-narrow';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Editar ficha</h2>
            <p>Los cambios afectan al usuario vinculado</p>
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
            <input type="hidden" name="id" value="<?= (int) $id ?>">

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
                        <label for="email">Correo electrónico *</label>
                        <input class="input" type="email" id="email" name="email" required maxlength="150"
                            value="<?= e($datos['email']) ?>">
                    </div>
                    <div class="field">
                        <label for="telefono">Teléfono</label>
                        <input class="input" type="tel" id="telefono" name="telefono" maxlength="30"
                            value="<?= e($datos['telefono']) ?>">
                    </div>
                </div>

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
                            inputmode="numeric" value="<?= e((string) ($datos['dni'] ?? '')) ?>"
                            placeholder="12345678" pattern="[0-9]{7,15}[A-Za-z]?">
                    </div>
                    <div class="field">
                        <label for="matricula">Número de matrícula</label>
                        <input class="input" type="text" id="matricula" name="matricula" maxlength="30"
                            value="<?= e($datos['matricula']) ?>">
                    </div>
                    <div class="field">
                        <label for="anio_ingreso">Año de ingreso</label>
                        <input class="input" type="number" id="anio_ingreso" name="anio_ingreso"
                            min="1950" max="<?= (int) date('Y') + 1 ?>" value="<?= e((string) $datos['anio_ingreso']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="estado">Estado</label>
                        <select class="input" id="estado" name="estado">
                            <option value="activo"   <?= $datos['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="egresado" <?= $datos['estado'] === 'egresado' ? 'selected' : '' ?>>Egresado</option>
                            <option value="retirado" <?= $datos['estado'] === 'retirado' ? 'selected' : '' ?>>Retirado</option>
                        </select>
                    </div>
                    <div class="field" style="justify-content:flex-end">
                        <label class="switch">
                            <input type="checkbox" name="activo" value="1"
                                <?= $datos['activo'] ? 'checked' : '' ?>>
                            <span class="track"></span>
                            <span>Usuario activo (puede iniciar sesión)</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                            <path d="M17 21v-8H7v8M7 3v5h8" />
                        </svg>
                        Guardar cambios
                    </button>
                    <a class="btn btn-ghost" href="listar.php">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>