<?php

/**
 * Siembra de roles y cuentas institucionales.
 *
 * Uso: php scripts/sembrar_roles_cuentas.php
 *
 * - Deja los 4 roles del sistema: Administrador, Profesor, Secretaría y Auditoría.
 * - Crea las cuentas secretaria@esfap.edu y auditoria@esfap.edu si no existen.
 * - Desactiva las cuentas históricas de alumno sin borrar sus datos.
 * - Puede ejecutarse varias veces sin duplicar datos.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Solo ejecutable por línea de comandos.');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$roles = [
    [1, 'Administrador', 'Acceso total al sistema'],
    [2, 'Profesor',      'Gestiona notas y consulta sus cursos asignados'],
    [4, 'Secretaría',    'Gestiona estudiantes, matrículas y constancias'],
    [5, 'Auditoría',     'Consulta toda la información y la bitácora del sistema'],
];

$cuentas = [
    [
        'nombres'   => 'Secretaría',
        'apellidos' => 'Académica',
        'email'     => 'secretaria@esfap.edu',
        'password'  => 'Secretaria2026',
        'id_rol'    => 4,
    ],
    [
        'nombres'   => 'Auditoría',
        'apellidos' => 'Institucional',
        'email'     => 'auditoria@esfap.edu',
        'password'  => 'Auditoria2026',
        'id_rol'    => 5,
    ],
];

try {
    $db = db()->conectar();

    /* Instalaciones antiguas pueden no tener roles.activo. */
    $tieneActivo = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'activo'"
    )->fetchColumn();

    if (!$tieneActivo) {
        $db->exec('ALTER TABLE roles ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion');
        fwrite(STDOUT, 'Columna roles.activo agregada.' . PHP_EOL);
    }

    $stmtRol = $db->prepare(
        'INSERT INTO roles (id_rol, nombre, descripcion, activo)
         VALUES (:id, :nombre, :descripcion, 1)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre),
                                 descripcion = VALUES(descripcion),
                                 activo = 1'
    );

    foreach ($roles as [$id, $nombre, $descripcion]) {
        $stmtRol->execute([
            ':id'          => $id,
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion,
        ]);
    }

    /* El alumno ya no es un usuario del sistema: se conserva la cuenta, no el acceso. */
    $cuentasAlumno = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE id_rol = 3')->fetchColumn();
    if ($cuentasAlumno > 0) {
        $db->exec('UPDATE usuarios SET activo = 0, id_rol = NULL WHERE id_rol = 3');
        fwrite(STDOUT, "{$cuentasAlumno} cuenta(s) de alumno desactivadas y sin rol de sistema." . PHP_EOL);
    }
    $db->exec('DELETE FROM roles WHERE id_rol = 3');

    $totalRoles = (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn();
    fwrite(STDOUT, "Roles verificados: {$totalRoles}" . PHP_EOL);

    $buscar = $db->prepare('SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1');
    $crear  = $db->prepare(
        'INSERT INTO usuarios (nombres, apellidos, email, password_hash, id_rol, activo)
         VALUES (:nombres, :apellidos, :email, :hash, :rol, 1)'
    );

    foreach ($cuentas as $cuenta) {
        $buscar->execute([':email' => $cuenta['email']]);
        if ($buscar->fetchColumn()) {
            fwrite(STDOUT, "Cuenta existente: {$cuenta['email']} (sin cambios)" . PHP_EOL);
            continue;
        }

        $crear->execute([
            ':nombres'   => $cuenta['nombres'],
            ':apellidos' => $cuenta['apellidos'],
            ':email'     => $cuenta['email'],
            ':hash'      => password_hash($cuenta['password'], PASSWORD_DEFAULT),
            ':rol'       => $cuenta['id_rol'],
        ]);

        fwrite(STDOUT, "Cuenta creada: {$cuenta['email']} · contraseña: {$cuenta['password']}" . PHP_EOL);
    }

    fwrite(STDOUT, 'Siembra completada.' . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo completar la siembra: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
