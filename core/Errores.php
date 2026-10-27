<?php

/**
 * Gestión central de errores y excepciones.
 * Registra la actividad en logs/app.log y, fuera de consola, muestra páginas
 * amigables en lugar de volcar detalles técnicos (evita fugas de rutas/SQL).
 */
final class Errores
{
    private static bool $cli = false;

    public static function iniciar(bool $debug = false): void
    {
        self::$cli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

        error_reporting(E_ALL);

        $dir = self::dir_logs();
        if ($dir !== null) {
            ini_set('log_errors', '1');
            ini_set('error_log', $dir . DIRECTORY_SEPARATOR . 'app.log');
        }

        if (!self::$cli) {
            ini_set('display_errors', $debug ? '1' : '0');
            ini_set('display_startup_errors', $debug ? '1' : '0');
        }

        set_error_handler([self::class, 'manejar_error']);
        set_exception_handler([self::class, 'manejar_excepcion']);
        register_shutdown_function([self::class, 'manejar_fatal']);
    }

    public static function dir_logs(): ?string
    {
        if (!defined('APP_ROOT')) {
            return null;
        }
        $dir = APP_ROOT . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return is_dir($dir) && is_writable($dir) ? $dir : null;
    }

    /** Escribe una línea en el log (útil para el resto de la app). */
    public static function log(string $mensaje, string $nivel = 'INFO'): void
    {
        self::registrar(strtoupper($nivel), $mensaje, null, null);
    }

    private static function nivel(int $errno): string
    {
        return match ($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR => 'ERROR',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARN',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default => 'INFO',
        };
    }

    private static function registrar(string $nivel, string $mensaje, ?string $archivo, ?int $linea): void
    {
        $ubi = $archivo !== null ? " en {$archivo}:{$linea}" : '';
        $linea_log = sprintf('[%s] [%s] %s%s', date('Y-m-d H:i:s'), $nivel, $mensaje, $ubi);

        if (self::$cli) {
            fwrite(STDERR, $linea_log . PHP_EOL);
        }

        $dir = self::dir_logs();
        if ($dir !== null) {
            @error_log($linea_log . PHP_EOL, 3, $dir . DIRECTORY_SEPARATOR . 'app.log');
        }
    }

    public static function manejar_error(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        self::registrar(self::nivel($errno), $errstr, $errfile !== '' ? $errfile : null, $errline ?: null);
        return true;
    }

    public static function manejar_excepcion(Throwable $e): void
    {
        self::registrar('EXCEPTION', $e->getMessage(), $e->getFile(), $e->getLine());
        if (self::$cli) {
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo self::pagina(
            'Ha ocurrido un error inesperado',
            'El equipo técnico ha sido notificado. Intenta nuevamente en unos minutos.'
        );
    }

    public static function manejar_fatal(): void
    {
        $e = error_get_last();
        if ($e === null) {
            return;
        }
        $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($e['type'], $fatales, true)) {
            return;
        }
        self::registrar('FATAL', $e['message'], $e['file'], $e['line']);
        if (self::$cli) {
            return;
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo self::pagina(
            'Ha ocurrido un error inesperado',
            'El equipo técnico ha sido notificado. Intenta nuevamente en unos minutos.'
        );
    }

    private static function pagina(string $titulo, string $mensaje): string
    {
        $t = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $m = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
        $url = function_exists('base_url')
            ? base_url('public/index.php')
            : '/';
        $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$t}</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f6fb;color:#0d1526;
font-family:'Segoe UI',Tahoma,sans-serif;padding:24px}
.box{max-width:520px;background:#fff;border:1px solid #e3e8f0;border-radius:24px;padding:40px;
box-shadow:0 20px 50px rgba(13,21,38,.12);text-align:center}
.badge{width:64px;height:64px;margin:0 auto 18px;border-radius:18px;display:grid;place-items:center;
background:linear-gradient(140deg,#2563eb,#1733a5);color:#fff;font-size:1.6rem;font-weight:800}
h1{font-size:1.3rem;margin:0 0 8px}
p{margin:0;color:#4b5768;line-height:1.6}
a{display:inline-block;margin-top:22px;color:#1d4ed8;font-weight:600;text-decoration:none}
</style>
</head>
<body>
<div class="box">
<div class="badge">!</div>
<h1>{$t}</h1>
<p>{$m}</p>
<a href="{$u}">Volver al inicio</a>
</div>
</body>
</html>
HTML;
    }
}