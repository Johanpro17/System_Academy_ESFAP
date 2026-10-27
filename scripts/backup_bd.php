<?php

/**
 * Copia de seguridad de la base de datos.
 *
 * Uso (línea de comandos):
 *   php scripts/backup_bd.php                → crear respaldo
 *   php scripts/backup_bd.php --conservar=20 → conservar las últimas N copias
 *
 * Genera un archivo .sql íntegro (estructura + datos) en backups/.
 * No requiere mysqldump: exporta con PDO sobre la propia conexión.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Solo ejecutable por línea de comandos.');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$cfg = $GLOBALS['APP_CONFIG'];
$conservar = 15;
$solo = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--conservar=')) {
        $conservar = max(1, (int) substr($arg, 12));
    }
    if (str_starts_with($arg, '--tabla=')) {
        $solo = substr($arg, 8);
    }
}

$dir = APP_ROOT . '/backups';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
if (!is_dir($dir) || !is_writable($dir)) {
    fwrite(STDERR, 'No se puede escribir en backups/' . PHP_EOL);
    exit(1);
}

try {
    $dsn = 'mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=' . $cfg['db_charset'];
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 60,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Conexión fallida: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($solo !== null) {
    $tablas = array_values(array_filter($tablas, fn($t) => $t === $solo));
}
if ($tablas === []) {
    fwrite(STDERR, 'No hay tablas que exportar.' . PHP_EOL);
    exit(1);
}

$archivo = sprintf('%s_%s.sql', $cfg['db_name'], date('Ymd_His'));
$ruta    = $dir . DIRECTORY_SEPARATOR . $archivo;
$salida  = fopen($ruta, 'wb');
if ($salida === false) {
    fwrite(STDERR, 'No se pudo crear el archivo de respaldo.' . PHP_EOL);
    exit(1);
}

$salto = fn() => fwrite($salida, PHP_EOL);

fwrite($salida, "-- Sistema Académico — respaldo de base de datos" . PHP_EOL);
fwrite($salida, "-- Base: " . $cfg['db_name'] . "  Fecha: " . date('Y-m-d H:i:s') . PHP_EOL);
fwrite($salida, "-- Generado por scripts/backup_bd.php" . PHP_EOL);
$salto();
fwrite($salida, "SET NAMES utf8mb4;" . PHP_EOL);
fwrite($salida, "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL);
$salto();

foreach ($tablas as $tabla) {
    $create = $pdo->query('SHOW CREATE TABLE `' . $tabla . '`')->fetch();
    $esVista = !empty($create['Create View']);

    fwrite($salida, '-- -------------------------------------------------' . PHP_EOL);
    fwrite($salida, '-- ' . ($esVista ? 'Vista' : 'Estructura de la tabla') . ' `' . $tabla . '`' . PHP_EOL);

    if ($esVista) {
        fwrite($salida, 'DROP VIEW IF EXISTS `' . $tabla . '`;' . PHP_EOL);
        fwrite($salida, $create['Create View'] . ';' . PHP_EOL);
        if (!empty($create['character_set_client'])) {
            fwrite($salida, '/*!50003 SET character_set_client = ' . $create['character_set_client'] . ' */;' . PHP_EOL);
        }
        if (!empty($create['collation_connection'])) {
            fwrite($salida, '/*!50003 SET collation_connection   = ' . $create['collation_connection'] . ' */;' . PHP_EOL);
        }
        $salto();
        continue;
    }

    fwrite($salida, 'DROP TABLE IF EXISTS `' . $tabla . '`;' . PHP_EOL);
    fwrite($salida, $create['Create Table'] . ';' . PHP_EOL);
    $salto();

    $filas = $pdo->query('SELECT * FROM `' . $tabla . '`')->fetchAll();
    $total = count($filas);
    if ($total === 0) {
        continue;
    }

    $cols = array_keys($filas[0]);
    $cast = fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v, PDO::PARAM_STR);

    $valores = array_map(
        fn($f) => '(' . implode(',', array_map($cast, array_values($f))) . ')',
        $filas
    );

    foreach (array_chunk($valores, 250) as $lote) {
        fwrite($salida, 'INSERT INTO `' . $tabla . '` (`' . implode('`,`', $cols) . '`) VALUES' . PHP_EOL);
        fwrite($salida, implode(',' . PHP_EOL, $lote) . ';' . PHP_EOL);
    }
    $salto();
    echo "  - {$tabla}: {$total} filas" . PHP_EOL;
}

fwrite($salida, "SET FOREIGN_KEY_CHECKS=1;" . PHP_EOL);
fwrite($salida, "-- Fin del respaldo" . PHP_EOL);
fclose($salida);

/* Retención: conservar solo los N respaldos más recientes */
if ($conservar > 0) {
    $existentes = glob($dir . DIRECTORY_SEPARATOR . $cfg['db_name'] . '_*.sql');
    usort($existentes, fn($a, $b) => strcmp($b, $a));
    $sobrantes = array_slice($existentes, $conservar);
    foreach ($sobrantes as $viejo) {
        @unlink($viejo);
        echo "  (eliminado antiguo: " . basename($viejo) . ")" . PHP_EOL;
    }
}

echo PHP_EOL . "Respaldo creado: " . str_replace(APP_ROOT, '.', $ruta) . PHP_EOL;
echo "Tamaño: " . number_format(filesize($ruta)) . ' bytes' . PHP_EOL;
exit(0);