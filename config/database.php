<?php

/**
 * Capa de conexión a la base de datos (PDO singleton).
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static $instance = null;
    private $conn = null;

    private $host;
    private $db_name;
    private $username;
    private $password;

    public function __construct()
    {
        $cfg = require __DIR__ . '/config.php';
        $this->host     = $cfg['db_host'];
        $this->db_name  = $cfg['db_name'];
        $this->username = $cfg['db_user'];
        $this->password = $cfg['db_pass'];
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function conectar()
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $opciones);
            $this->conn->exec("SET NAMES utf8mb4");
            $this->alinear_zona();
        } catch (PDOException $e) {
            // Intento crear la base si aún no existe
            try {
                $tmp = new PDO(
                    "mysql:host={$this->host};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    $opciones
                );
                $tmp->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                unset($tmp);

                $this->conn = new PDO($dsn, $this->username, $this->password, $opciones);
                $this->conn->exec("SET NAMES utf8mb4");
                $this->alinear_zona();
            } catch (PDOException $inner) {
                throw new RuntimeException(
                    'No se pudo conectar con la base de datos: ' . $inner->getMessage()
                );
            }
        }

        return $this->conn;
    }

    /**
     * Iguala la zona horaria de la sesión MySQL con la de la aplicación, de modo
     * que NOW(), CURDATE() y las funciones de PHP hablen del mismo reloj.
     */
    private function alinear_zona(): void
    {
        try {
            $cfg = require __DIR__ . '/config.php';
            $zona = $cfg['zona_horaria'] ?? null;
            if (!$zona) {
                return;
            }

            $offset = (new DateTime('now', new DateTimeZone($zona)))->format('P');
            $this->conn->exec("SET time_zone = " . $this->conn->quote($offset));
        } catch (Throwable $e) {
            // SinPrivilegios para SET time_zone: la app sigue funcionando
        }
    }

    /**
     * Ejecuta una consulta preparada devolviendo el statement.
     */
    public function query($sql, array $params = [])
    {
        [$sql, $params] = $this::expandir_placeholders($sql, $params);

        $stmt = $this->conectar()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * MySQL no admite un parámetro nombrado repetido dentro de la misma
     * consulta cuando se usan consultas preparadas nativas. Aquí cada
     * repetición se renombra y se le asigna el mismo valor, de modo que
     * escribir dos veces ':id' funcione igual que usar ':id' y ':id2'.
     */
    private static function expandir_placeholders(string $sql, array $params): array
    {
        if (!$params) {
            return [$sql, $params];
        }

        $sqlOriginal = $sql;

        $usados = [];
        $extra  = [];
        $contador = 0;

        /* \x5C evita el caracter de escape al definir la barra invertida */
        $sql = preg_replace_callback(
            '/(?<![\'":\x5C]):([a-zA-Z_][a-zA-Z0-9_]*)/',
            static function (array $m) use (&$usados, &$extra, &$contador, $params) {
                $clave = ':' . $m[1];

                if (!array_key_exists($clave, $params)) {
                    return $m[0];
                }

                if (!isset($usados[$clave])) {
                    $usados[$clave] = true;
                    return $m[0];
                }

                $nuevo = $clave . '__' . (++$contador);
                $extra[$nuevo] = $params[$clave];

                return $nuevo;
            },
            $sql
        );

        if ($sql === null) {
            return [$sqlOriginal, $params];
        }

        return [$sql, $params + $extra];
    }

    /**
     * Devuelve la primera fila o null.
     */
    public function fila($sql, array $params = [])
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Devuelve todas las filas.
     */
    public function todas($sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Devuelve el primer valor de la primera fila.
     */
    public function valor($sql, array $params = [], $default = null)
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? $default : $row[0];
    }
}

/**
 * Acceso rápido: $db->todas(...) sin instanciar.
 */
function db(): Database
{
    return Database::getInstance();
}