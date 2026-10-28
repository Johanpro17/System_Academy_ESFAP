<?php

/**
 * Gestión de usuarios y roles (solo administradores).
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('usuarios.ver');

$puedeGestionar = Auth::puede('usuarios.gestionar');
$errores = [];
$roles   = db()->todas("SELECT id_rol, nombre, descripcion FROM roles ORDER BY id_rol");
$miId    = (int) Auth::id();

if (es_post()) {
    exigir_post();
    Auth::requerir_permiso('usuarios.gestionar');
    $accion = input('accion');

    /* Crear usuario */
    if ($accion === 'crear') {
        $nombres   = trim(input('nombres'));
        $apellidos = trim(input('apellidos'));
        $email     = mb_strtolower(trim(input('email')));
        $telefono  = trim(input('telefono'));
        $idRol     = input_int('id_rol');
        $password  = input('password');

        if ($nombres === '') {
            $errores[] = 'Los nombres son obligatorios.';
        }
        if ($apellidos === '') {
            $errores[] = 'Los apellidos son obligatorios.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        } elseif (db()->valor("SELECT id_usuario FROM usuarios WHERE email = :e", [':e' => $email])) {
            $errores[] = 'Ya existe un usuario registrado con ese correo.';
        }
        if (!in_array($idRol, array_map(static fn($r) => (int) $r['id_rol'], $roles), true)) {
            $errores[] = 'Selecciona un rol válido.';
        }
        if (strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errores[] = 'La contraseña debe incluir letras y números.';
        }

        if (!$errores) {
            db()->query(
                "INSERT INTO usuarios (nombres, apellidos, email, password_hash, telefono, id_rol, activo)
                 VALUES (:n, :a, :e, :p, :t, :r, 1)",
                [
                    ':n' => $nombres,
                    ':a' => $apellidos,
                    ':e' => $email,
                    ':p' => password_hash($password, PASSWORD_DEFAULT),
                    ':t' => $telefono ?: null,
                    ':r' => $idRol,
                ]
            );
            $id = (int) db()->conectar()->lastInsertId();
            Auth::registrar('crear', 'usuarios', $id, "Usuario {$email} creado con rol {$idRol}");

            Notificacion::crear($id, 'Bienvenido al sistema', 'Tu cuenta fue creada. Ya puedes iniciar sesión.', 'success', 'perfil.php');

            flash_set('success', 'Usuario creado correctamente.');
            redirigir('listar.php');
        }
    }

    /* Editar usuario */
    if ($accion === 'editar') {
        $id        = input_int('id');
        $nombres   = trim(input('nombres'));
        $apellidos = trim(input('apellidos'));
        $email     = mb_strtolower(trim(input('email')));
        $telefono  = trim(input('telefono'));
        $idRol     = input_int('id_rol');
        $activo    = input('activo') ? 1 : 0;

        if ($nombres === '' || $apellidos === '') {
            $errores[] = 'Nombres y apellidos son obligatorios.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        } else {
            $otro = db()->valor(
                "SELECT id_usuario FROM usuarios WHERE email = :e AND id_usuario <> :id",
                [':e' => $email, ':id' => $id]
            );
            if ($otro) {
                $errores[] = 'Ese correo ya pertenece a otro usuario.';
            }
        }
        if (!in_array($idRol, array_map(static fn($r) => (int) $r['id_rol'], $roles), true)) {
            $errores[] = 'Selecciona un rol válido.';
        }

        /* Protección: no permitir degradar o desactivar el último administrador */
        $adminActual = db()->fila("SELECT id_rol, activo FROM usuarios WHERE id_usuario = :id", [':id' => $id]);
        $dejaDeSerAdmin = $adminActual && ((int) $adminActual['id_rol'] === 1) && ($idRol !== 1 || $activo === 0);

        if ($dejaDeSerAdmin) {
            $otrosAdmins = (int) db()->valor(
                "SELECT COUNT(*) FROM usuarios WHERE id_rol = 1 AND activo = 1 AND id_usuario <> :id",
                [':id' => $id],
                0
            );
            if ($otrosAdmins === 0) {
                $errores[] = 'Debe existir al menos un administrador activo en el sistema.';
            }
        }

        if (!$errores) {
            db()->query(
                "UPDATE usuarios
                    SET nombres = :n, apellidos = :a, email = :e, telefono = :t, id_rol = :r, activo = :ac
                  WHERE id_usuario = :id",
                [
                    ':n'  => $nombres,
                    ':a'  => $apellidos,
                    ':e'  => $email,
                    ':t'  => $telefono ?: null,
                    ':r'  => $idRol,
                    ':ac' => $activo,
                    ':id' => $id,
                ]
            );
            Auth::registrar('actualizar', 'usuarios', $id, "Usuario {$email} actualizado");

            flash_set('success', 'Usuario actualizado.');
            redirigir('listar.php');
        }
    }
    if ($accion === 'alternar') {
        $id = input_int('id');
        if ($id === $miId) {
            flash_set('error', 'No puedes desactivar tu propia cuenta.');
        } else {
            $u = db()->fila("SELECT id_rol, activo FROM usuarios WHERE id_usuario = :id", [':id' => $id]);
            $nuevo = $u && $u['activo'] ? 0 : 1;

            if ($u && (int) $u['id_rol'] === 1 && $nuevo === 0) {
                $otros = (int) db()->valor(
                    "SELECT COUNT(*) FROM usuarios WHERE id_rol = 1 AND activo = 1 AND id_usuario <> :id",
                    [':id' => $id],
                    0
                );
                if ($otros === 0) {
                    flash_set('error', 'Debe quedar al menos un administrador activo.');
                    redirigir('listar.php');
                }
            }

            db()->query("UPDATE usuarios SET activo = :a WHERE id_usuario = :id", [':a' => $nuevo, ':id' => $id]);
            Auth::registrar('actualizar', 'usuarios', $id, $nuevo ? 'Usuario activado' : 'Usuario desactivado');
            flash_set('success', $nuevo ? 'Usuario activado.' : 'Usuario desactivado.');
        }
        redirigir('listar.php');
    }

    /* Restablecer contraseña (opcional dentro de la edición) */
    if ($accion === 'resetear') {
        $id       = input_int('id');
        $password = input('password');
        $u        = db()->fila("SELECT email FROM usuarios WHERE id_usuario = :id", [':id' => $id]);

        if (!$u) {
            flash_set('error', 'El usuario no existe.');
        } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            flash_set('error', 'La nueva contraseña debe tener mínimo 8 caracteres con letras y números.');
        } else {
            db()->query(
                "UPDATE usuarios SET password_hash = :p, ultimo_acceso = NULL WHERE id_usuario = :id",
                [':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]
            );
            Auth::registrar('restablecer_password', 'usuarios', $id, 'Contraseña restablecida por administrador');
            Notificacion::crear(
                (int) $id,
                'Contraseña restablecida',
                'Un administrador definió una nueva contraseña para tu cuenta.',
                'warning',
                'perfil.php'
            );
            flash_set('success', "Contraseña de {$u['email']} restablecida.");
        }
        redirigir('listar.php' . ($id ? '?editar=' . $id : ''));
    }
}

/* ---------------- Listado ---------------- */

$q        = input('q');
$idRol    = input_int('rol');
$estado   = input('estado');
$pag      = max(1, input_int('pag', 1));
$porPagina = 15;

$filtros = [];
if ($q !== '') {
    $filtros["CONCAT(u.nombres,' ',u.apellidos)"] = ['like', $q];
    $filtros['u.email']                          = ['like', $q];
}
if ($idRol) {
    $filtros['u.id_rol'] = $idRol;
}
if ($estado === 'activos') {
    $filtros['u.activo'] = 1;
} elseif ($estado === 'inactivos') {
    $filtros['u.activo'] = 0;
}

$join = 'JOIN roles r ON r.id_rol = u.id_rol';

$total = Paginador::contar('usuarios u', $filtros, $join);
$paginador = new Paginador($total, $porPagina, $pag);

$usuarios = Paginador::obtener(
    'usuarios u',
    ['u.id_usuario', 'u.nombres', 'u.apellidos', 'u.email', 'u.telefono', 'u.activo',
     'u.ultimo_acceso', 'u.creado_en', 'r.nombre AS rol'],
    $filtros,
    'u.activo DESC, u.nombres ASC',
    $porPagina,
    $paginador->offset(),
    $join
)->fetchAll();

/* Usuario en edición */
$editando = null;
if ($puedeGestionar && input_int('editar')) {
    $editando = db()->fila("SELECT * FROM usuarios WHERE id_usuario = :id", [':id' => input_int('editar')]);
}

$parametros = array_filter(['q' => $q, 'rol' => $idRol ?: null, 'estado' => $estado]);

$titulo    = 'Usuarios';
$subtitulo = $total . ' ' . ($total === 1 ? 'cuenta' : 'cuentas') . ' registrada' . ($total === 1 ? '' : 's');
$seccion   = 'usuarios';
$ancho     = '';

require_once APP_ROOT . '/layout/header.php';
?>

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

<div class="<?= $puedeGestionar ? 'grid-2' : '' ?>">
    <?php if ($puedeGestionar): ?>
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $editando ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
                <p><?= $editando ? 'Modifica los datos de la cuenta' : 'Crea una cuenta con acceso al sistema' ?></p>
            </div>
            <?php if ($editando): ?>
                <div class="panel-actions">
                    <a class="btn btn-ghost btn-sm" href="listar.php">Cancelar</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-body">
            <form method="POST" novalidate>
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="<?= $editando ? 'editar' : 'crear' ?>">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?= (int) $editando['id_usuario'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-row">
                        <div class="field">
                            <label for="un">Nombres *</label>
                            <input class="input" type="text" id="un" name="nombres" required maxlength="80"
                                value="<?= e($editando['nombres'] ?? input('nombres')) ?>">
                        </div>
                        <div class="field">
                            <label for="ua">Apellidos *</label>
                            <input class="input" type="text" id="ua" name="apellidos" required maxlength="80"
                                value="<?= e($editando['apellidos'] ?? input('apellidos')) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="ue">Correo electrónico *</label>
                            <input class="input" type="email" id="ue" name="email" required
                                value="<?= e($editando['email'] ?? input('email')) ?>">
                        </div>
                        <div class="field">
                            <label for="ut">Teléfono</label>
                            <input class="input" type="tel" id="ut" name="telefono"
                                value="<?= e($editando['telefono'] ?? input('telefono')) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="ur">Rol *</label>
                            <select class="input" id="ur" name="id_rol" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int) $r['id_rol'] ?>"
                                        <?= (int) ($editando['id_rol'] ?? input_int('id_rol')) === (int) $r['id_rol'] ? 'selected' : '' ?>>
                                        <?= e($r['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="hint"><?= e($roles[array_key_first($roles)]['descripcion'] ?? '') ?></span>
                        </div>

                        <?php if (!$editando): ?>
                            <div class="field">
                                <label for="up">Contraseña *</label>
                                <div class="input-group">
                                    <input class="input" type="password" id="up" name="password" required minlength="8"
                                        autocomplete="new-password">
                                    <button class="input-action" type="button" data-toggle-password="up" aria-label="Mostrar contraseña">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="hint">Mínimo 8 caracteres, con letras y números.</span>
                            </div>
                        <?php else: ?>
                            <div class="field">
                                <label>Estado de la cuenta</label>
                                <label class="check" style="padding:9px 0">
                                    <input type="checkbox" name="activo" value="1"
                                        <?= !empty($editando['activo']) ? 'checked' : '' ?>>
                                    <span>Cuenta activa</span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($editando): ?>
                        <div class="divider"></div>

                        <div class="form-row">
                            <div class="field">
                                <label for="np">Nueva contraseña</label>
                                <div class="input-group">
                                    <input class="input" type="password" id="np" name="password" minlength="8"
                                        autocomplete="new-password"
                                        placeholder="Usar solo con «Restablecer contraseña»">
                                    <button class="input-action" type="button" data-toggle-password="np" aria-label="Mostrar contraseña">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                </div>
                                <span class="hint">Guardar datos no altera la contraseña: usa «Restablecer contraseña» (mínimo 8 caracteres con letras y números).</span>
                            </div>

                            <div class="field">
                                <label>&nbsp;</label>
                                <button class="btn btn-outline" type="submit" name="accion" value="resetear"
                                    formnovalidate>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M21 2l-2 2m-7.6 7.6a5 5 0 11-7.1 7.1 5 5 0 017.1-7.1zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3" />
                                    </svg>
                                    Restablecer contraseña
                                </button>
                                <span class="hint">Aplica solo el campo de contraseña, sin tocar los demás datos.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                                <path d="M17 21v-8H7v8M7 3v5h8" />
                            </svg>
                            <?= $editando ? 'Guardar cambios' : 'Crear usuario' ?>
                        </button>
                        <?php if ($editando): ?>
                            <a class="btn btn-ghost" href="listar.php">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Usuarios del sistema</h2>
                <p>Roles, estado y último acceso</p>
            </div>
        </div>

        <div class="panel-body">
            <form class="toolbar" method="GET" novalidate>
                <div class="search-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="M21 21l-4.35-4.35" />
                    </svg>
                    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre o correo…">
                </div>

                <select class="input" name="rol" data-auto-submit>
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id_rol'] ?>" <?= $idRol === (int) $r['id_rol'] ? 'selected' : '' ?>>
                            <?= e($r['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="input" name="estado" data-auto-submit>
                    <option value="">Todos</option>
                    <option value="activos" <?= $estado === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivos" <?= $estado === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </form>

            <?php if (!$usuarios): ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM19 8v6M22 11h-6" />
                    </svg>
                    <h3>Sin usuarios</h3>
                    <p>No hay cuentas que coincidan con los filtros.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Último acceso</th>
                                <?php if ($puedeGestionar): ?>
                                    <th class="num">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $colorRol = [
                                'Administrador' => 'blue',
                                'Profesor'      => 'green',
                                'Estudiante'    => 'gray',
                                'Secretaría'    => 'gold',
                                'Auditoría'     => 'blue',
                            ];
                            ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td>
                                        <div class="cell-user">
                                            <span class="avatar"><?= e(iniciales($u['nombres'] . ' ' . $u['apellidos'])) ?></span>
                                            <div>
                                                <b><?= e($u['nombres'] . ' ' . $u['apellidos']) ?></b>
                                                <small class="muted"><?= e($u['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $colorRol[$u['rol']] ?? 'gray' ?> no-dot">
                                            <?= e($u['rol']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['activo']): ?>
                                            <span class="badge badge-green">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-gray">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="muted small nowrap">
                                        <?= $u['ultimo_acceso'] ? e(fecha($u['ultimo_acceso'], 'd/m/Y H:i')) : 'Nunca' ?>
                                    </td>
                                    <?php if ($puedeGestionar): ?>
                                    <td class="num">
                                        <div class="flex gap-sm" style="justify-content:flex-end">
                                            <a class="btn btn-ghost btn-sm" title="Editar"
                                                href="listar.php?<?= e(http_build_query($parametros + ['editar' => (int) $u['id_usuario']])) ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
                                                    <path d="M18.5 2.5a2.1 2.1 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </a>

                                            <form method="POST"
                                                data-confirmar="¿<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> la cuenta de <?= e($u['email']) ?>?">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="accion" value="alternar">
                                                <input type="hidden" name="id" value="<?= (int) $u['id_usuario'] ?>">
                                                <button class="btn btn-ghost btn-sm" type="submit"
                                                    title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>"
                                                    style="color:<?= $u['activo'] ? 'var(--danger)' : 'var(--ok)' ?>">
                                                    <?php if ($u['activo']): ?>
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <path d="M18 6L6 18M6 6l12 12" />
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                                                        </svg>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?= $paginador->render($parametros) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>