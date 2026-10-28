<?php

/**
 * Migración aditiva de roles y cuentas institucionales.
 *
 * Uso: php scripts/sembrar_roles_cuentas.php
 *
 * - Crea/actualiza los roles 4 (Secretaría) y 5 (Auditoría).
 * - Crea las cuentas secretaria@esfap.edu y auditoria@esfap.edu si no existen.
 * - No modifica usuarios, estudiantes, profesores ni matrículas existentes.
 * - Puede ejecutarse varias veces sin duplicar datos.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Solo ejecutable por línea de comandos.');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$roles = [
    [4, 'Secretaría', 'Gestiona estudiantes, matrículas y constancias'],
    [5, 'Auditoría',  'Consulta toda la información y la bitácora del sistema'],
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

    $stmtRol = $db->prepare(
        "INSERT INTO roles (id_rol, nombre, descripcion) VALUES (:id, :nombre, :descripcion)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion)"
    );

    foreach ($roles as [$id, $nombre, $descripcion]) {
        $stmtRol->execute([
            ':id'          => $id,
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion,
        ]);
    }

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

    fwrite(STDOUT, 'Migración completada.' . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo completar la migración: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
