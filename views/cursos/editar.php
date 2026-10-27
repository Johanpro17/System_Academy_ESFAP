<?php

/**
 * Edición de curso: redirige al formulario unificado con el id en la URL.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_admin();

$id = input_int('id');

header('Location: ' . base_url('views/cursos/crear.php') . ($id ? '?id=' . $id : ''));
exit;