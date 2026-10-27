<?php

/**
 * Cierre de sesión seguro.
 */

require_once __DIR__ . '/../core/bootstrap.php';

Auth::cerrar();

/* Sesión nueva para poder mostrar el mensaje de confirmación */
Auth::asegurar_sesion();
flash_set('success', 'Cerraste sesión correctamente.');

header('Location: ' . base_url('login.php'));
exit;