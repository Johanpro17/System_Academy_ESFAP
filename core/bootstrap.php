<?php

/**
 * Arranque de la aplicación: constantes, sesión, cabeceras de seguridad.
 * Debe incluirse al inicio de TODAS las páginas.
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/core/helpers.php';
require_once APP_ROOT . '/core/Auth.php';
require_once APP_ROOT . '/core/Paginador.php';
require_once APP_ROOT . '/core/Notificacion.php';
require_once APP_ROOT . '/core/Errores.php';

$GLOBALS['APP_CONFIG'] = require APP_ROOT . '/config/config.php';

/* La zona horaria se fija en la aplicación: un php.ini con otro valor
   desalinearía las fechas de PHP con NOW() / CURDATE() de MySQL. */
date_default_timezone_set($GLOBALS['APP_CONFIG']['zona_horaria'] ?? 'UTC');

define('APP_DEBUG',     (bool) ($GLOBALS['APP_CONFIG']['debug'] ?? false));

define('APP_NOMBRE',        $GLOBALS['APP_CONFIG']['nombre']);
define('APP_INSTITUCION',   $GLOBALS['APP_CONFIG']['institucion']);
define('APP_SIGLAS',        $GLOBALS['APP_CONFIG']['siglas']);
define('APP_NOMBRE_SESION', $GLOBALS['APP_CONFIG']['nombre_sesion']);
define('APP_MAX_INTENTOS',  $GLOBALS['APP_CONFIG']['max_intentos']);
define('APP_MINUTOS_BLOQUEO', $GLOBALS['APP_CONFIG']['minutos_bloqueo']);
define('APP_POR_PAGINA',    $GLOBALS['APP_CONFIG']['por_pagina']);

/* Cabeceras de seguridad */
Errores::iniciar(APP_DEBUG);
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-XSS-Protection: 0');
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "img-src 'self' data:; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com data:; " .
        "script-src 'self' 'unsafe-inline'; " .
        "form-action 'self'; frame-ancestors 'self'"
    );
}

/* Sesión */
Auth::asegurar_sesion();

/* Content-Security: aplicar cookie de tema antes de renderizar */
if (isset($_COOKIE['tema'])) {
    $_SESSION['tema'] = $_COOKIE['tema'] === 'oscuro' ? 'oscuro' : 'claro';
}

/**
 * Recarga los datos del usuario logged desde la BD (rol, avatar, etc).
 */
function refrescar_usuario(): void
{
    if (!Auth::esta_logueado()) {
        return;
    }
    try {
        $u = db()->fila(
            "SELECT id_usuario, nombres, apellidos, email, id_rol, activo, avatar
               FROM usuarios WHERE id_usuario = :id",
            [':id' => Auth::id()]
        );
        if ($u) {
            $_SESSION['user'] = [
                'id_usuario' => (int) $u['id_usuario'],
                'nombres'    => $u['nombres'],
                'apellidos'  => $u['apellidos'],
                'email'      => $u['email'],
                'id_rol'     => (int) $u['id_rol'],
                'avatar'     => $u['avatar'],
                'nombre'     => $u['nombres'] . ' ' . $u['apellidos'],
                'rol'        => Auth::nombre_rol((int) $u['id_rol']),
            ];
        }
    } catch (Throwable $e) {
        // sin cambios
    }
}