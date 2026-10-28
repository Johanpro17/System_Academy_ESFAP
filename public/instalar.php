<?php

/**
 * INSTALADOR / MIGRADOR — Sistema Académico
 *
 * Crea el esquema completo y actualiza el existente de forma idempotente.
 * Puede ejecutarse tantas veces como sea necesario.
 *
 * Esquema:
 *   roles, usuarios, especialidades, cursos, profesores, estudiantes,
 *   matriculas, horarios, evaluaciones, notas, noticias, notificaciones,
 *   auditoria, vista v_promedio_por_curso
 *
 * SEGURIDAD: el instalador queda bloqueado automáticamente cuando la base
 * de datos está disponible. Para volver a ejecutarlo hay que borrar el
 * archivo config/.instalado. En producción también conviene eliminar este
 * archivo y toda la carpeta _sql.
 */

require_once __DIR__ . '/../core/bootstrap.php';

/* ------------------------------------------------------------------ *
 * Bloqueo: solo se permite instalar si el archivo candado no existe  *
 * ------------------------------------------------------------------ */

$candado = APP_ROOT . '/config/.instalado';

if (file_exists($candado)) {
    http_response_code(403);
    $yaInstalado = true;
} else {
    $yaInstalado = false;
}

$db        = null;
$conexionOk = false;
$instalado = false;
$mensajes  = [];
$errores   = [];

if (!$yaInstalado) {
    try {
        $db = db()->conectar();
        $conexionOk = true;
    } catch (Throwable $e) {
        $conexionOk = false;
        $errores[] = 'No se pudo conectar con la base de datos: ' . $e->getMessage();
    }
}

/**
 * Ejecuta SQLHelper que no debe fallar.
 */
function ejecutar(string $titulo, string $sql, array &$mensajes, array &$errores): void
{
    global $db;
    try {
        $db->exec($sql);
        $mensajes[] = ['msg' => $titulo, 'type' => 'success'];
    } catch (PDOException $e) {
        $errores[] = $titulo . ': ' . $e->getMessage();
    }
}

/**
 * Añade una columna solo si no existe.
 */
function agregar_columna(string $tabla, string $columna, string $definicion, array &$mensajes, array &$errores): void
{
    try {
        $existe = db()->valor(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
            [':t' => $tabla, ':c' => $columna],
            0
        );
        if (!$existe) {
            db()->conectar()->exec("ALTER TABLE `{$tabla}` ADD COLUMN `{$columna}` {$definicion}");
            $mensajes[] = ['msg' => "Columna {$tabla}.{$columna} agregada.", 'type' => 'info'];
        }
    } catch (PDOException $e) {
        $errores[] = "{$tabla}.{$columna}: " . $e->getMessage();
    }
}

/**
 * Crea un índice solo si no existe.
 */
function agregar_indice(string $tabla, string $indice, string $sql, array &$mensajes, array &$errores): void
{
    try {
        $existe = db()->valor(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i",
            [':t' => $tabla, ':i' => $indice],
            0
        );
        if (!$existe) {
            db()->conectar()->exec("ALTER TABLE `{$tabla}` ADD {$sql}");
            $mensajes[] = ['msg' => "Índice {$indice} creado en {$tabla}.", 'type' => 'info'];
        }
    } catch (PDOException $e) {
        $errores[] = "índice {$indice}: " . $e->getMessage();
    }
}

/* ==================================================================
 * Ejecución del instalador
 * ================================================================== */

if ($yaInstalado) {
    /* Nada que ejecutar: se muestra el aviso de bloqueo más abajo */
} elseif (!$conexionOk) {
    /* Los errores de conexión ya están registrados */
} else {

/* ==================================================================
 * 1. Tabla roles
 * ================================================================== */
ejecutar(
    'Tabla `roles`',
    "CREATE TABLE IF NOT EXISTS roles (
        id_rol TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL UNIQUE,
        descripcion VARCHAR(150) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

$roles = [
    [1, 'Administrador', 'Acceso total al sistema'],
    [2, 'Docente',        'Gestiona notas y consulta sus cursos asignados'],
    [3, 'Estudiante',     'Consulta sus notas, matrículas y horarios'],
    [4, 'Secretaría',     'Gestiona estudiantes, matrículas y constancias'],
    [5, 'Auditoría',      'Consulta toda la información y la bitácora del sistema'],
];

foreach ($roles as $r) {
    try {
        $db->prepare("INSERT INTO roles (id_rol, nombre, descripcion) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)")
            ->execute($r);
    } catch (PDOException $e) {
        $errores[] = "Rol {$r[1]}: " . $e->getMessage();
    }
}
$mensajes[] = ['msg' => 'Roles verificados (5 roles).', 'type' => 'success'];

/* ==================================================================
 * 2. Tabla usuarios (+ roles, avatar, teléfono, acceso)
 * ================================================================== */
ejecutar(
    'Tabla `usuarios`',
    "CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario INT AUTO_INCREMENT PRIMARY KEY,
        nombres VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        id_rol TINYINT UNSIGNED DEFAULT 1,
        telefono VARCHAR(30) DEFAULT NULL,
        avatar VARCHAR(255) DEFAULT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        ultimo_acceso DATETIME DEFAULT NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

agregar_columna('usuarios', 'id_rol',      "TINYINT UNSIGNED DEFAULT 1", $mensajes, $errores);
agregar_columna('usuarios', 'telefono',    "VARCHAR(30) DEFAULT NULL", $mensajes, $errores);
agregar_columna('usuarios', 'avatar',      "VARCHAR(255) DEFAULT NULL", $mensajes, $errores);
agregar_columna('usuarios', 'ultimo_acceso', "DATETIME DEFAULT NULL", $mensajes, $errores);
agregar_columna('usuarios', 'creado_en',   "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP", $mensajes, $errores);
agregar_indice('usuarios', 'idx_usuarios_rol', "INDEX idx_usuarios_rol (id_rol)", $mensajes, $errores);

/* ==================================================================
 * 3. Tabla especialidades
 * ================================================================== */
ejecutar(
    'Tabla `especialidades`',
    "CREATE TABLE IF NOT EXISTS especialidades (
        id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

foreach (['Pintura', 'Escultura', 'Música', 'Danza', 'Teatro', 'Teoría Musical'] as $esp) {
    try {
        $db->prepare("INSERT IGNORE INTO especialidades (nombre) VALUES (?)")->execute([$esp]);
    } catch (PDOException $e) {
        $errores[] = "Especialidad {$esp}: " . $e->getMessage();
    }
}

/* La tabla puede venir de una importación anterior sin restricción UNIQUE,
   lo que genera filas repetidas en cada ejecución. Se unifica por nombre
   (sin distinguir mayúsculas ni acentos) y se remuegan las referencias. */
try {
    $grupos = $db->query(
        "SELECT LOWER(TRIM(nombre)) AS clave, MIN(id_especialidad) AS conservar, COUNT(*) AS total
           FROM especialidades GROUP BY clave HAVING total > 1"
    )->fetchAll();

    if ($grupos) {
        foreach ($grupos as $g) {
            $duplicados = $db->prepare(
                "SELECT id_especialidad FROM especialidades WHERE LOWER(TRIM(nombre)) = :c AND id_especialidad <> :k"
            );
            $duplicados->execute([':c' => $g['clave'], ':k' => $g['conservar']]);

            $mover = $db->prepare("UPDATE estudiantes SET id_especialidad = :nuevo WHERE id_especialidad = :viejo");
            $borrar = $db->prepare("DELETE FROM especialidades WHERE id_especialidad = :id");

            foreach ($duplicados->fetchAll() as $dup) {
                $mover->execute([':nuevo' => $g['conservar'], ':viejo' => $dup['id_especialidad']]);
                $borrar->execute([':id' => $dup['id_especialidad']]);
            }
        }
        $mensajes[] = ['msg' => 'Especialidades duplicadas consolidadas.', 'type' => 'info'];
    }
} catch (PDOException $e) {
    $errores[] = 'Consolidación de especialidades: ' . $e->getMessage();
}

agregar_indice('especialidades', 'uniq_especialidad', "UNIQUE KEY uniq_especialidad (nombre)", $mensajes, $errores);

$mensajes[] = ['msg' => 'Especialidades verificadas.', 'type' => 'success'];

/* ==================================================================
 * 4. Tabla cursos
 * ================================================================== */
ejecutar(
    'Tabla `cursos`',
    "CREATE TABLE IF NOT EXISTS cursos (
        id_curso INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(20) DEFAULT NULL,
        nombre VARCHAR(150) NOT NULL,
        descripcion TEXT DEFAULT NULL,
        creditos TINYINT UNSIGNED NOT NULL DEFAULT 0,
        area VARCHAR(80) DEFAULT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

agregar_columna('cursos', 'codigo', "VARCHAR(20) DEFAULT NULL", $mensajes, $errores);
agregar_columna('cursos', 'area',   "VARCHAR(80) DEFAULT NULL", $mensajes, $errores);
agregar_columna('cursos', 'activo', "TINYINT(1) NOT NULL DEFAULT 1", $mensajes, $errores);
agregar_columna('cursos', 'creado_en', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP", $mensajes, $errores);

/* ==================================================================
 * 5. Tabla profesores
 * ================================================================== */
ejecutar(
    'Tabla `profesores`',
    "CREATE TABLE IF NOT EXISTS profesores (
        id_profesor INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT DEFAULT NULL,
        nombres VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        especialidad VARCHAR(100) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        telefono VARCHAR(30) DEFAULT NULL,
        titulo VARCHAR(120) DEFAULT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

foreach (['telefono', 'titulo', 'activo', 'creado_en', 'id_usuario'] as $c) {
    agregar_columna('profesores', $c, in_array($c, ['activo'], true) ? 'TINYINT(1) NOT NULL DEFAULT 1'
        : ($c === 'creado_en' ? 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' : ($c === 'id_usuario' ? 'INT DEFAULT NULL' : 'VARCHAR(100) DEFAULT NULL')), $mensajes, $errores);
}
agregar_indice('profesores', 'idx_prof_usuario', "INDEX idx_prof_usuario (id_usuario)", $mensajes, $errores);

/* ==================================================================
 * 6. Tabla estudiantes
 * ================================================================== */
ejecutar(
    'Tabla `estudiantes`',
    "CREATE TABLE IF NOT EXISTS estudiantes (
        id_estudiante INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_especialidad INT NOT NULL,
        dni VARCHAR(20) DEFAULT NULL,
        matricula VARCHAR(30) DEFAULT NULL,
        anio_ingreso YEAR DEFAULT NULL,
        estado ENUM('activo','egresado','retirado') DEFAULT 'activo',
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_est_usuario (id_usuario),
        INDEX idx_est_esp (id_especialidad),
        CONSTRAINT fk_est_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
        CONSTRAINT fk_est_esp FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

foreach (['dni' => 'VARCHAR(20) DEFAULT NULL', 'matricula' => 'VARCHAR(30) DEFAULT NULL', 'anio_ingreso' => 'YEAR DEFAULT NULL', 'estado' => "ENUM('activo','egresado','retirado') DEFAULT 'activo'"] as $c => $d) {
    agregar_columna('estudiantes', $c, $d, $mensajes, $errores);
}

/* ==================================================================
 * 7. Tabla matriculas
 * ================================================================== */
ejecutar(
    'Tabla `matriculas`',
    "CREATE TABLE IF NOT EXISTS matriculas (
        id_matricula INT AUTO_INCREMENT PRIMARY KEY,
        id_estudiante INT NOT NULL,
        id_curso INT NOT NULL,
        periodo VARCHAR(20) NOT NULL,
        estado ENUM('matriculado','aprobado','reprobado','retirado') DEFAULT 'matriculado',
        fecha_matricula DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_matricula (id_estudiante, id_curso, periodo),
        INDEX idx_mat_curso (id_curso),
        CONSTRAINT fk_mat_est FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE CASCADE,
        CONSTRAINT fk_mat_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

agregar_columna('matriculas', 'estado', "ENUM('matriculado','aprobado','reprobado','retirado') DEFAULT 'matriculado'", $mensajes, $errores);
agregar_indice('matriculas', 'idx_mat_curso', "INDEX idx_mat_curso (id_curso)", $mensajes, $errores);

/* Una inscripción por estudiante/curso/periodo. Las importaciones antiguas
   pudieron repetir filas: se conserva la más antigua y su estado más
   avanzado, y solo después se crea la restricción UNIQUE. */
try {
    $duplicadas = $db->query(
        "SELECT id_estudiante, id_curso, periodo, MIN(id_matricula) AS conservar
           FROM matriculas
          GROUP BY id_estudiante, id_curso, periodo
         HAVING COUNT(*) > 1"
    )->fetchAll();

    if ($duplicadas) {
        $estado = $db->prepare(
            "UPDATE matriculas SET estado = :e WHERE id_matricula = :id"
        );

        foreach ($duplicadas as $d) {
            /* Precedencia: aprobado > matriculado > reprobado > retirado */
            $mejor = $db->query(
                "SELECT estado FROM matriculas
                  WHERE id_estudiante = {$d['id_estudiante']}
                    AND id_curso = {$d['id_curso']}
                    AND periodo = " . $db->quote($d['periodo']) . "
                  ORDER BY FIELD(estado, 'aprobado', 'matriculado', 'reprobado', 'retirado')
                  LIMIT 1"
            )->fetchColumn();

            if ($mejor) {
                $estado->execute([':e' => $mejor, ':id' => $d['conservar']]);
            }

            $repetidas = $db->prepare(
                "DELETE FROM matriculas
                  WHERE id_estudiante = :e AND id_curso = :c AND periodo = :p AND id_matricula <> :k"
            );
            $repetidas->execute([
                ':e' => $d['id_estudiante'],
                ':c' => $d['id_curso'],
                ':p' => $d['periodo'],
                ':k' => $d['conservar'],
            ]);
        }

        $mensajes[] = ['msg' => 'Matrículas duplicadas consolidadas.', 'type' => 'info'];
    }
} catch (PDOException $e) {
    $errores[] = 'Consolidación de matrículas: ' . $e->getMessage();
}

agregar_indice(
    'matriculas',
    'uk_matricula',
    'UNIQUE KEY uk_matricula (id_estudiante, id_curso, periodo)',
    $mensajes,
    $errores
);

/* ==================================================================
 * 8. Tabla horarios
 * ================================================================== */
ejecutar(
    'Tabla `horarios`',
    "CREATE TABLE IF NOT EXISTS horarios (
        id_horario INT AUTO_INCREMENT PRIMARY KEY,
        id_curso INT NOT NULL,
        id_profesor INT NOT NULL,
        id_salon VARCHAR(50) DEFAULT NULL,
        dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo') NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL,
        periodo VARCHAR(20) DEFAULT NULL,
        INDEX idx_hor_curso (id_curso),
        INDEX idx_hor_prof (id_profesor),
        CONSTRAINT fk_hor_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso),
        CONSTRAINT fk_hor_prof FOREIGN KEY (id_profesor) REFERENCES profesores(id_profesor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

agregar_columna('horarios', 'id_salon', 'VARCHAR(50) DEFAULT NULL', $mensajes, $errores);
agregar_columna('horarios', 'periodo', 'VARCHAR(20) DEFAULT NULL', $mensajes, $errores);
agregar_indice('horarios', 'idx_hor_curso', "INDEX idx_hor_curso (id_curso)", $mensajes, $errores);

/* Asignación de docentes a cursos, independiente de los bloques de horario. */
ejecutar(
    'Tabla `curso_docentes`',
    "CREATE TABLE IF NOT EXISTS curso_docentes (
        id_curso INT NOT NULL,
        id_profesor INT NOT NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_curso, id_profesor),
        INDEX idx_curso_docentes_profesor (id_profesor, id_curso),
        CONSTRAINT fk_cd_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
        CONSTRAINT fk_cd_profesor FOREIGN KEY (id_profesor) REFERENCES profesores(id_profesor) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

try {
    $asignacionesExistentes = $db->exec(
        "INSERT IGNORE INTO curso_docentes (id_curso, id_profesor)
         SELECT DISTINCT id_curso, id_profesor FROM horarios"
    );
    if ($asignacionesExistentes > 0) {
        $mensajes[] = ['msg' => "{$asignacionesExistentes} asignación(es) docente(s) migrada(s) desde horarios.", 'type' => 'info'];
    }
} catch (PDOException $e) {
    $errores[] = 'Migración de asignaciones docentes: ' . $e->getMessage();
}

/* ==================================================================
 * 9. Tablas de evaluación y notas
 * ================================================================== */
ejecutar(
    'Tabla `evaluaciones`',
    "CREATE TABLE IF NOT EXISTS evaluaciones (
        id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
        id_curso INT NOT NULL,
        nombre VARCHAR(120) NOT NULL,
        peso DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        fecha DATE DEFAULT NULL,
        INDEX idx_ev_curso (id_curso),
        CONSTRAINT fk_ev_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

ejecutar(
    'Tabla `notas`',
    "CREATE TABLE IF NOT EXISTS notas (
        id_nota INT AUTO_INCREMENT PRIMARY KEY,
        id_estudiante INT NOT NULL,
        id_curso INT NOT NULL,
        id_evaluacion INT DEFAULT NULL,
        periodo VARCHAR(20) NOT NULL,
        valor DECIMAL(5,2) NOT NULL,
        registrado_por INT DEFAULT NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_nota (id_estudiante, id_evaluacion, periodo),
        INDEX idx_notas_est (id_estudiante),
        INDEX idx_notas_curso (id_curso),
        CONSTRAINT fk_nota_est FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE CASCADE,
        CONSTRAINT fk_nota_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
        CONSTRAINT fk_nota_ev FOREIGN KEY (id_evaluacion) REFERENCES evaluaciones(id_evaluacion) ON DELETE SET NULL,
        CONSTRAINT fk_nota_usr FOREIGN KEY (registrado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

agregar_indice('notas', 'idx_notas_est', "INDEX idx_notas_est (id_estudiante)", $mensajes, $errores);
agregar_indice('notas', 'idx_notas_curso', "INDEX idx_notas_curso (id_curso)", $mensajes, $errores);

/* ==================================================================
 * 10. Noticias
 * ================================================================== */
ejecutar(
    'Tabla `noticias`',
    "CREATE TABLE IF NOT EXISTS noticias (
        id_noticia INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(180) NOT NULL,
        contenido TEXT NOT NULL,
        imagen VARCHAR(255) DEFAULT NULL,
        autor_id INT DEFAULT NULL,
        publicado TINYINT(1) NOT NULL DEFAULT 0,
        destacado TINYINT(1) NOT NULL DEFAULT 0,
        publicado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_not_pub (publicado, publicado_en),
        CONSTRAINT fk_not_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

/* ==================================================================
 * 11. Notificaciones
 * ================================================================== */
ejecutar(
    'Tabla `notificaciones`',
    "CREATE TABLE IF NOT EXISTS notificaciones (
        id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        titulo VARCHAR(120) NOT NULL,
        mensaje VARCHAR(255) DEFAULT NULL,
        tipo ENUM('info','success','warning','error') DEFAULT 'info',
        url VARCHAR(255) DEFAULT NULL,
        leida TINYINT(1) NOT NULL DEFAULT 0,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notif_usr (id_usuario, leida),
        CONSTRAINT fk_notif_usr FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

/* ==================================================================
 * 12. Auditoría
 * ================================================================== */
ejecutar(
    'Tabla `auditoria`',
    "CREATE TABLE IF NOT EXISTS auditoria (
        id_auditoria BIGINT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT DEFAULT NULL,
        accion VARCHAR(40) NOT NULL,
        entidad VARCHAR(40) NOT NULL,
        entidad_id INT DEFAULT NULL,
        detalle VARCHAR(255) DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_aud_usuario (id_usuario),
        INDEX idx_aud_fecha (creado_en),
        CONSTRAINT fk_aud_usr FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

/* ==================================================================
 * 13. Intentos de login (rate limiting persistente)
 * ================================================================== */
ejecutar(
    'Tabla `login_intentos`',
    "CREATE TABLE IF NOT EXISTS login_intentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identificador VARCHAR(150) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        exito TINYINT(1) NOT NULL DEFAULT 0,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_li_id (identificador, ip, creado_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    $mensajes,
    $errores
);

/* ==================================================================
 * 14. Vista de promedios
 * ================================================================== */
try {
    $db->exec("DROP VIEW IF EXISTS v_promedio_por_curso");
    $db->exec("
        CREATE OR REPLACE VIEW v_promedio_por_curso AS
        SELECT
            e.id_estudiante,
            CONCAT(u.nombres, ' ', u.apellidos) AS estudiante,
            c.id_curso,
            c.nombre AS curso,
            n.periodo,
            ROUND(AVG(n.valor), 2) AS promedio,
            COUNT(DISTINCT n.id_evaluacion) AS evaluaciones,
            SUM(CASE WHEN n.valor >= 7 THEN 1 ELSE 0 END) AS aprobadas,
            SUM(CASE WHEN n.valor <  7 THEN 1 ELSE 0 END) AS reprobadas
        FROM notas n
        INNER JOIN estudiantes e ON e.id_estudiante = n.id_estudiante
        INNER JOIN usuarios u      ON u.id_usuario  = e.id_usuario
        INNER JOIN cursos c        ON c.id_curso     = n.id_curso
        GROUP BY e.id_estudiante, u.nombres, u.apellidos, c.id_curso, c.nombre, n.periodo
    ");
    $mensajes[] = ['msg' => 'Vista `v_promedio_por_curso` creada.', 'type' => 'success'];
} catch (PDOException $e) {
    $errores[] = 'Vista v_promedio_por_curso: ' . $e->getMessage();
}

/* ==================================================================
 * 15. Usuario administrador
 * ================================================================== */
try {
    $existeAdmin = (int) db()->valor("SELECT COUNT(*) FROM usuarios WHERE id_rol = 1", [], 0);

    if ($existeAdmin === 0) {
        $stmt = $db->prepare(
            "INSERT INTO usuarios (nombres, apellidos, email, password_hash, id_rol, activo)
             VALUES (:n, :a, :e, :h, 1, 1)"
        );
        $stmt->execute([
            ':n' => 'Administrador',
            ':a' => 'Sistema',
            ':e' => 'admin@sistema.edu',
            ':h' => password_hash('admin123', PASSWORD_DEFAULT),
        ]);
        $mensajes[] = ['msg' => 'Usuario administrador creado.', 'type' => 'success'];
    } else {
        $mensajes[] = ['msg' => 'Ya existe al menos un usuario administrador.', 'type' => 'info'];
    }
} catch (PDOException $e) {
    $errores[] = 'Usuario administrador: ' . $e->getMessage();
}

/* ==================================================================
 * 15b. Cuentas institucionales de secretaría y auditoría
 * ================================================================== */
$cuentasInstitucionales = [
    [
        'nombres'   => 'Secretaría',
        'apellidos' => 'Académica',
        'email'     => 'secretaria@esfap.edu',
        'password'  => 'Secretaria2026',
        'rol'       => 4,
    ],
    [
        'nombres'   => 'Auditoría',
        'apellidos' => 'Institucional',
        'email'     => 'auditoria@esfap.edu',
        'password'  => 'Auditoria2026',
        'rol'       => 5,
    ],
];

foreach ($cuentasInstitucionales as $cuenta) {
    try {
        $existe = db()->valor(
            "SELECT id_usuario FROM usuarios WHERE email = :e",
            [':e' => $cuenta['email']]
        );

        if (!$existe) {
            $db->prepare(
                "INSERT INTO usuarios (nombres, apellidos, email, password_hash, id_rol, activo)
                 VALUES (:n, :a, :e, :h, :r, 1)"
            )->execute([
                ':n' => $cuenta['nombres'],
                ':a' => $cuenta['apellidos'],
                ':e' => $cuenta['email'],
                ':h' => password_hash($cuenta['password'], PASSWORD_DEFAULT),
                ':r' => $cuenta['rol'],
            ]);
            $mensajes[] = ['msg' => "Cuenta {$cuenta['email']} creada.", 'type' => 'success'];
        } else {
            $mensajes[] = ['msg' => "La cuenta {$cuenta['email']} ya existe.", 'type' => 'info'];
        }
    } catch (PDOException $e) {
        $errores[] = "Cuenta {$cuenta['email']}: " . $e->getMessage();
    }
}

/* ==================================================================
 * 16. Migrar contraseñas en texto plano
 * ================================================================== */
try {
    $planas = db()->todas(
        "SELECT id_usuario, password_hash FROM usuarios
          WHERE password_hash NOT LIKE '\$2%' AND password_hash NOT LIKE '\$argon%'
            AND password_hash NOT LIKE '\$scrypt%'"
    );

    foreach ($planas as $u) {
        // Solo se migra si parece una contraseña corta/legible, no un hash truncado
        if (strlen((string) $u['password_hash']) > 100) {
            continue;
        }
        db()->query(
            "UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id",
            [
                ':h'  => password_hash((string) $u['password_hash'], PASSWORD_DEFAULT),
                ':id' => $u['id_usuario'],
            ]
        );
        $mensajes[] = [
            'msg'  => "Contraseña del usuario #{$u['id_usuario']} migrada a hash seguro. IMPORTANTE: cámbiala al primer inicio de sesión.",
            'type' => 'warning',
        ];
    }
} catch (PDOException $e) {
    $errores[] = 'Migración de contraseñas: ' . $e->getMessage();
}

/* ==================================================================
 * 17. Datos de demostración
 * ================================================================== */
try {
    if ((int) db()->valor("SELECT COUNT(*) FROM cursos", [], 0) === 0) {
        $cursos = [
            ['ART-101', 'Fundamentos del Dibujo', 'Técnicas básicas de línea, forma y volumen.', 4, 'Talleres'],
            ['ART-115', 'Historia del Arte', 'Recorrido por los movimientos artísticos nacionales.', 3, 'Teoría'],
            ['MUS-120', 'Audiación y Solfeo', 'Lectura de partituras y entrenamiento del oído.', 4, 'Música'],
            ['DAN-140', 'Fundamentos de la Danza', 'Postura, respiración y expresión corporal.', 3, 'Danza'],
            ['TEO-130', 'Historia de la Música', 'Evolución de la música occidental y latinoamericana.', 3, 'Teoría'],
            ['ESC-150', 'Técnicas de Escultura', 'Modelado y construcción de volúmenes.', 4, 'Talleres'],
            ['TEA-160', 'Interpretación Dramática', 'Técnicas de actuación y puesta en escena.', 3, 'Teatro'],
        ];

        $stmt = $db->prepare(
            "INSERT INTO cursos (codigo, nombre, descripcion, creditos, area, activo)
             VALUES (?,?,?,?,?,1)"
        );
        foreach ($cursos as $c) {
            $stmt->execute($c);
        }
        $mensajes[] = ['msg' => 'Cursos de demostración cargados.', 'type' => 'success'];
    }
} catch (PDOException $e) {
    $errores[] = 'Cursos demo: ' . $e->getMessage();
}

$instalado = !$yaInstalado && empty($errores);

/* Bloqueo permanente tras una instalación correcta */
if ($instalado) {
    @file_put_contents($candado, 'Instalado el ' . date('Y-m-d H:i:s') . PHP_EOL);
}
} /* fin de la ejecución del instalador */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - <?= e(APP_NOMBRE) ?></title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/sistema.css">
</head>

<body class="page-auth">
    <div class="auth-wrap">
        <div class="panel">
            <div class="panel-head">
                <span class="brand-mark" aria-hidden="true">SA</span>
                <div>
                    <h1>Instalador del Sistema</h1>
                    <p><?= e(APP_INSTITUCION) ?></p>
                </div>
            </div>

            <div class="panel-body">
                <?php if ($yaInstalado): ?>
                    <div class="alert alert-success">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        <div>
                            <strong>El instalador está bloqueado.</strong><br>
                            El sistema ya fue instalado. Por seguridad este archivo no vuelve a ejecutar
                            ningún cambio en la base de datos.
                        </div>
                    </div>

                    <div class="credentials">
                        <h3>Para volver a instalarlo</h3>
                        <ul style="margin:0;padding-left:18px;font-size:.9rem;line-height:1.7">
                            <li>Elimina el archivo <code>config/.instalado</code></li>
                            <li>Vuelve a abrir <code>instalar.php</code></li>
                        </ul>
                        <p class="cred-note">En producción lo recomendable es borrar este archivo y la carpeta <code>_sql</code>.</p>
                    </div>
                <?php elseif ($instalado): ?>
                    <div class="alert alert-success">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        <div>Instalación completada correctamente.</div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                        </svg>
                        <div><?= $conexionOk ? 'Hubo errores. Revisa el detalle abajo.' : 'No se pudo conectar con la base de datos.' ?></div>
                    </div>
                <?php endif; ?>

                <div class="status-list">
                    <?php foreach ($mensajes as $m): ?>
                        <div class="status-item <?= e($m['type']) ?>"><?= e($m['msg']) ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($errores as $err): ?>
                        <div class="status-item error"><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>

                <?php if ($instalado): ?>
                    <div class="credentials">
                        <h3>Credenciales iniciales</h3>
                        <div class="cred-row">
                            <span class="badge">Email</span>
                            <code>admin@sistema.edu</code>
                        </div>
                        <div class="cred-row">
                            <span class="badge">Password</span>
                            <code>admin123</code>
                        </div>
                        <p class="cred-note">Cambia esta contraseña desde tu perfil después de ingresar.</p>
                    </div>
                <?php endif; ?>

                <div class="actions">
                    <?php if ($yaInstalado || $instalado): ?>
                        <a class="btn btn-primary" href="<?= e(base_url('login.php')) ?>">Ir al inicio de sesión</a>
                        <a class="btn btn-outline" href="<?= e(base_url('dashboard.php')) ?>">Ir al panel</a>
                    <?php else: ?>
                        <a class="btn btn-primary" href="instalar.php">Reintentar instalación</a>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="<?= e(base_url('index.php')) ?>">Ver portada</a>
                </div>

                <p class="warn-note">
                    <strong>Seguridad:</strong> elimina <code>public/instalar.php</code> cuando termines.
                    Ese archivo puede recrear usuarios administradores.
                </p>
            </div>
        </div>
    </div>
</body>

</html>
