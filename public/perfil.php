<?php

/**
 * Perfil del usuario en sesión: datos, contraseña y actividad.
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerir_login();

$errores = [];

/* La sesión guarda solo los datos de identificación: el hash y los metadatos
   del usuario se leen de la base para no meterlos en la sesión. */
$usuario = array_merge(Auth::usuario(), db()->fila(
    "SELECT telefono, ultimo_acceso, creado_en, password_hash FROM usuarios WHERE id_usuario = :id",
    [':id' => Auth::id()]
) ?: []);

if (es_post()) {
    exigir_post();
    $accion = input('accion');

    /* Actualizar datos personales */
    if ($accion === 'datos') {
        $nombres   = trim(input('nombres'));
        $apellidos = trim(input('apellidos'));
        $telefono  = trim(input('telefono'));

        if ($nombres === '' || $apellidos === '') {
            $errores[] = 'Nombres y apellidos son obligatorios.';
        } elseif (mb_strlen($nombres) > 80 || mb_strlen($apellidos) > 80) {
            $errores[] = 'Los nombres y apellidos son demasiado extensos.';
        }

        if (!$errores) {
            db()->query(
                "UPDATE usuarios SET nombres = :n, apellidos = :a, telefono = :t WHERE id_usuario = :id",
                [':n' => $nombres, ':a' => $apellidos, ':t' => $telefono ?: null, ':id' => Auth::id()]
            );
            Auth::registrar('actualizar', 'usuarios', (int) Auth::id(), 'Datos del perfil actualizados');
            flash_set('success', 'Datos actualizados correctamente.');
            redirigir('perfil.php');
        }
    }

    /* Cambiar contraseña */
    if ($accion === 'password') {
        $actual    = input('actual');
        $nueva     = input('nueva');
        $confirmar = input('confirmar');

        if (!password_verify($actual, $usuario['password_hash'])) {
            $errores[] = 'La contraseña actual es incorrecta.';
        }
        if (strlen($nueva) < 8) {
            $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/\d/', $nueva)) {
            $errores[] = 'La nueva contraseña debe incluir letras y números.';
        }
        if ($nueva !== $confirmar) {
            $errores[] = 'La nueva contraseña y su confirmación no coinciden.';
        }
        if ($nueva === $actual) {
            $errores[] = 'La nueva contraseña debe ser distinta de la actual.';
        }

        if (!$errores) {
            db()->query(
                "UPDATE usuarios SET password_hash = :p WHERE id_usuario = :id",
                [':p' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => Auth::id()]
            );
            Auth::registrar('cambiar_password', 'usuarios', (int) Auth::id(), 'Contraseña modificada por el usuario');
            Notificacion::crear(
                (int) Auth::id(),
                'Contraseña actualizada',
                'Tu contraseña fue cambiada el ' . fecha(date('Y-m-d H:i:s'), 'd/m/Y H:i') . '.',
                'success',
                'perfil.php'
            );
            flash_set('success', 'Contraseña actualizada.');
            redirigir('perfil.php');
        }
    }
}

/* Actividad reciente del usuario */
$actividad = db()->todas(
    "SELECT accion, entidad, detalle, creado_en FROM auditoria
      WHERE id_usuario = :id ORDER BY id_auditoria DESC LIMIT 8",
    [':id' => Auth::id()]
);

/* Notificaciones sin leer */
$sinLeer = (int) db()->valor(
    "SELECT COUNT(*) FROM notificaciones WHERE id_usuario = :id AND leida = 0",
    [':id' => Auth::id()],
    0
);

/* Actividad académica (solo estudiantes) */
$miMatricula = db()->fila("SELECT id_estudiante, matricula FROM estudiantes WHERE id_usuario = :id", [':id' => Auth::id()]);

$titulo    = 'Mi perfil';
$subtitulo = 'Gestiona tu cuenta y seguridad';
$seccion   = 'perfil';
$ancho     = '';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="grid-2">
    <div>
        <div class="panel mb-3">
            <div class="panel-head">
                <div>
                    <h2>Datos personales</h2>
                    <p>Información visible en el sistema</p>
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
                    <input type="hidden" name="accion" value="datos">

                    <div class="form-grid">
                        <div class="form-row">
                            <div class="field">
                                <label for="pn">Nombres *</label>
                                <input class="input" type="text" id="pn" name="nombres" required maxlength="80"
                                    value="<?= e(input('nombres') ?: $usuario['nombres']) ?>">
                            </div>
                            <div class="field">
                                <label for="pa">Apellidos *</label>
                                <input class="input" type="text" id="pa" name="apellidos" required maxlength="80"
                                    value="<?= e(input('apellidos') ?: $usuario['apellidos']) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label for="pe">Correo electrónico</label>
                                <input class="input" type="email" id="pe" value="<?= e($usuario['email']) ?>" disabled>
                                <span class="hint">Solo un administrador puede cambiar el correo.</span>
                            </div>
                            <div class="field">
                                <label for="pt">Teléfono</label>
                                <input class="input" type="tel" id="pt" name="telefono"
                                    value="<?= e(input('telefono') ?: $usuario['telefono']) ?>">
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
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>Seguridad</h2>
                    <p>Cambia tu contraseña de acceso</p>
                </div>
            </div>
            <div class="panel-body">
                <form method="POST" novalidate autocomplete="off">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="password">

                    <div class="form-grid">
                        <div class="field">
                            <label for="pw_act">Contraseña actual *</label>
                            <div class="input-group">
                                <input class="input" type="password" id="pw_act" name="actual" required
                                    autocomplete="current-password">
                                <button class="input-action" type="button" data-toggle-password="pw_new" aria-label="Mostrar contraseña">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label for="pw_new">Nueva contraseña *</label>
                                <div class="input-group">
                                    <input class="input" type="password" id="pw_new" name="nueva" required minlength="8"
                                        autocomplete="new-password">
<button class="input-action" type="button" data-toggle-password="pw_act" aria-label="Mostrar contraseña">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="field">
                                <label for="pw_conf">Confirmar contraseña *</label>
                                <div class="input-group">
                                    <input class="input" type="password" id="pw_conf" name="confirmar" required minlength="8"
                                        autocomplete="new-password">
                                    <button class="input-action" type="button" data-toggle-password="pw_conf" aria-label="Mostrar contraseña">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="hint">Mínimo 8 caracteres con letras y números.</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M21 2l-2 2m-7.6 7.6a5 5 0 11-7.1 7.1 5 5 0 017.1-7.1zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3" />
                                </svg>
                                Actualizar contraseña
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="panel mb-3">
            <div class="panel-body text-center">
                <span class="avatar" style="width:82px;height:82px;font-size:1.7rem;margin:0 auto 14px">
                    <?= e(Auth::iniciales_usuario()) ?>
                </span>
                <h3 style="margin:0 0 2px"><?= e($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></h3>
                <p class="muted small" style="margin:0 0 10px"><?= e($usuario['email']) ?></p>
                <span class="badge badge-purple no-dot"><?= e(Auth::nombre_rol((int) $usuario['id_rol'])) ?></span>

                <?php if ($miMatricula): ?>
                    <div class="divider"></div>
                    <div class="flex-between">
                        <span class="muted small">Matrícula</span>
                        <b class="tabular"><?= e($miMatricula['matricula']) ?></b>
                    </div>
                <?php endif; ?>

                <div class="divider"></div>
                <div class="flex-between">
                    <span class="muted small">Notificaciones sin leer</span>
                    <b class="tabular"><?= (int) $sinLeer ?></b>
                </div>
                <div class="flex-between" style="margin-top:8px">
                    <span class="muted small">Último acceso</span>
                    <b class="small"><?= $usuario['ultimo_acceso'] ? e(fecha($usuario['ultimo_acceso'], 'd/m/Y H:i')) : 'Primer ingreso' ?></b>
                </div>
                <div class="flex-between" style="margin-top:8px">
                    <span class="muted small">Cuenta creada</span>
                    <b class="small"><?= e(fecha($usuario['creado_en'])) ?></b>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>Mi actividad reciente</h2>
                    <p>Últimas acciones registradas</p>
                </div>
            </div>
            <div class="panel-body">
                <?php if (!$actividad): ?>
                    <div class="empty" style="padding:26px 10px">
                        <p>Aún no hay acciones registradas a tu nombre.</p>
                    </div>
                <?php else: ?>
                    <div class="list">
                        <?php foreach ($actividad as $a): ?>
                            <div class="list-item">
                                <span class="li-body">
                                    <b><?= e(str_replace('_', ' ', $a['accion'])) ?></b>
                                    <small>
                                        <?= e($a['detalle'] ?: $a['entidad']) ?>
                                        · <?= e(fecha($a['creado_en'], 'd/m/Y H:i')) ?>
                                    </small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>