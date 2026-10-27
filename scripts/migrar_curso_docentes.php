<?php

/**
 * Migración aditiva para asignar docentes a cursos sin depender de horarios.
 *
 * Uso: php scripts/migrar_curso_docentes.php
 * Copia las relaciones existentes de horarios y puede repetirse sin duplicar.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Solo ejecutable por línea de comandos.');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

try {
    db()->conectar()->exec(
        "CREATE TABLE IF NOT EXISTS curso_docentes (
            id_curso INT NOT NULL,
            id_profesor INT NOT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_curso, id_profesor),
            INDEX idx_curso_docentes_profesor (id_profesor, id_curso),
            CONSTRAINT fk_cd_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso) ON DELETE CASCADE,
            CONSTRAINT fk_cd_profesor FOREIGN KEY (id_profesor) REFERENCES profesores(id_profesor) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $insertadas = db()->conectar()->exec(
        "INSERT IGNORE INTO curso_docentes (id_curso, id_profesor)
         SELECT DISTINCT id_curso, id_profesor FROM horarios"
    );

    fwrite(STDOUT, "Migración completada. Asignaciones recuperadas de horarios: {$insertadas}." . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo completar la migración: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
