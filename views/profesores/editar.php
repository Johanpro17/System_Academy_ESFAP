<?php

/**
 * Edición de profesor: redirige al formulario unificado.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('profesores.gestionar');

$id = input_int('id');

header('Location: ' . base_url('views/profesores/crear.php') . ($id ? '?id=' . $id : ''));
exit;