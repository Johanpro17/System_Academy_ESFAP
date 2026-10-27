<?php

/**
 * Configuración global de la aplicación.
 * Los valores sensibles se pueden sobreescribir con variables de entorno.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

return [
    'nombre'        => 'Sistema Académico',
    'institucion'   => 'Escuela de Formación Artística',
    'siglas'        => 'ESFAP',

    /* Zona horaria de la institución. Se aplica en PHP y en la conexión
       MySQL para que ambas fechas coincidan, sin depender del php.ini. */
    'zona_horaria'  => 'America/Bogota',

    /* Modo depuración. En producción debe quedar en false: los errores se
       registran en logs/app.log y al usuario se le muestra una página amable.
       Se puede forzar con la variable de entorno APP_DEBUG=1. */
    'debug'         => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN),

    // Base de datos
    'db_host'       => getenv('DB_HOST') ?: 'localhost',
    'db_name'      => getenv('DB_NAME') ?: 'escuela_artistica',
    'db_user'      => getenv('DB_USER') ?: 'root',
    'db_pass'      => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'db_charset'   => 'utf8mb4',

    // Seguridad
    'nombre_sesion' => 'sistema_academico',
    'max_intentos'  => 5,          // intentos antes del bloqueo temporal
    'minutos_bloqueo' => 15,

    // Paginación
    'por_pagina'    => 10,
];