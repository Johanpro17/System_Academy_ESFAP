<?php

/**
 * Exportación de datos a CSV (Excel compatible con UTF-8 BOM).
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('reportes.exportar');

$tipo = input('tipo');
$q    = input('q');

$titulos = [
    'estudiantes' => 'Estudiantes',
    'profesores'  => 'Profesores',
    'cursos'      => 'Cursos',
    'matriculas'  => 'Matrículas',
    'notas'       => 'Notas',
];

if (!isset($titulos[$tipo])) {
    http_response_code(400);
    exit('Tipo de exportación no válido.');
}

$verTodo = !Auth::es_docente();
$idProfesor = $verTodo ? null : db()->valor(
    "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
    [':u' => Auth::id()]
);
if (Auth::es_docente() && $tipo === 'profesores') {
    http_response_code(403);
    exit('No tienes permiso para exportar el padrón de profesores.');
}

switch ($tipo) {
    case 'estudiantes':
        $filtros = [];
        if ($q !== '') {
            $filtros["CONCAT(u.nombres,' ',u.apellidos)"] = ['like', $q];
            $filtros['e.matricula']                       = ['like', $q];
            $filtros['e.dni']                             = ['like', $q];
        }
        $columnas = ['e.dni', 'e.matricula', 'e.anio_ingreso', 'e.estado',
                     "CONCAT(u.nombres,' ',u.apellidos) AS estudiante", 'u.email', 'u.telefono',
                     'esp.nombre AS especialidad'];
        $join = 'JOIN usuarios u ON u.id_usuario = e.id_usuario
                 JOIN especialidades esp ON esp.id_especialidad = e.id_especialidad';
        if (!$verTodo) {
            $join .= ' JOIN (
                SELECT DISTINCT m.id_estudiante
                  FROM matriculas m
                  JOIN curso_docentes cd ON cd.id_curso = m.id_curso
                 WHERE cd.id_profesor = ' . (int) ($idProfesor ?: 0) . '
            ) cursos_asignados ON cursos_asignados.id_estudiante = e.id_estudiante';
        }
        $filas = Paginador::obtener(
            'estudiantes e',
            $columnas,
            $filtros,
            'u.apellidos ASC',
            5000,
            0,
            $join
        )->fetchAll();
        $encabezados = ['DNI', 'Matrícula', 'Año de ingreso', 'Estado', 'Estudiante', 'Correo', 'Teléfono', 'Especialidad'];
        break;

    case 'profesores':
        /* Columnas explícitas: deben coincidir con el orden de los encabezados */
        $filas = db()->todas(
            'SELECT p.nombres, p.apellidos, p.especialidad, p.titulo, p.email, p.telefono, p.activo, p.creado_en
               FROM profesores p
              ORDER BY p.apellidos, p.nombres'
        );
        $encabezados = ['Nombres', 'Apellidos', 'Especialidad', 'Título académico', 'Correo', 'Teléfono', 'Activo', 'Registrado'];
        break;

    case 'cursos':
        if ($verTodo) {
            $filas = db()->todas("SELECT codigo, nombre, area, creditos, descripcion, activo, creado_en FROM cursos ORDER BY nombre");
        } else {
            $filas = db()->todas(
                "SELECT c.codigo, c.nombre, c.area, c.creditos, c.descripcion, c.activo, c.creado_en
                   FROM cursos c
                   JOIN curso_docentes cd ON cd.id_curso = c.id_curso
                  WHERE cd.id_profesor = :p
                  ORDER BY c.nombre",
                [':p' => $idProfesor ?: 0]
            );
        }
        $encabezados = ['Código', 'Curso', 'Área', 'Créditos', 'Descripción', 'Activo', 'Creado'];
        break;

    case 'matriculas':
        $periodo = input('periodo') ?: '';
        $filtros = [];
        if ($q !== '') {
            $filtros["CONCAT(u.nombres,' ',u.apellidos)"] = ['like', $q];
        }
        if ($periodo !== '') {
            $filtros['m.periodo'] = $periodo;
        }
        $join = 'JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
                 JOIN usuarios u ON u.id_usuario = e.id_usuario
                 JOIN cursos c ON c.id_curso = m.id_curso';
        if (!$verTodo) {
            $join .= ' JOIN curso_docentes cd ON cd.id_curso = m.id_curso';
            $filtros['cd.id_profesor'] = $idProfesor ?: 0;
        }
        $filas = Paginador::obtener(
            'matriculas m',
            ["CONCAT(u.nombres,' ',u.apellidos) AS estudiante", 'e.matricula', 'c.nombre AS curso',
             'm.periodo', 'm.estado', 'm.fecha_matricula'],
            $filtros,
            'm.fecha_matricula DESC',
            5000,
            0,
            $join
        )->fetchAll();
        $encabezados = ['Estudiante', 'Matrícula', 'Curso', 'Periodo', 'Estado', 'Fecha de matrícula'];
        break;

    case 'notas':
        $periodo = input('periodo') ?: '';
        $filtros = [];
        if ($periodo !== '') {
            $filtros['n.periodo'] = $periodo;
        }
        $join = 'JOIN estudiantes e ON e.id_estudiante = n.id_estudiante
                 JOIN usuarios u ON u.id_usuario = e.id_usuario
                 JOIN cursos c ON c.id_curso = n.id_curso
                 JOIN evaluaciones ev ON ev.id_evaluacion = n.id_evaluacion';
        if (!$verTodo) {
            $join .= ' JOIN curso_docentes cd ON cd.id_curso = n.id_curso';
            $filtros['cd.id_profesor'] = $idProfesor ?: 0;
        }
        $filas = Paginador::obtener(
            'notas n',
            ["CONCAT(u.nombres,' ',u.apellidos) AS estudiante", 'e.matricula', 'c.nombre AS curso',
             'ev.nombre AS evaluacion', 'ev.peso', 'n.periodo', 'n.valor'],
            $filtros,
            'u.apellidos ASC, c.nombre ASC',
            10000,
            0,
            $join
        )->fetchAll();
        $encabezados = ['Estudiante', 'Matrícula', 'Curso', 'Evaluación', 'Peso', 'Periodo', 'Nota'];
        break;
}

Auth::registrar('exportar', $tipo, null, "Exportación CSV: " . count($filas) . ' registros');

$nombreArchivo = $tipo . '-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

$salida = fopen('php://output', 'wb');

/* BOM para que Excel respete UTF-8 */
fwrite($salida, "\xEF\xBB\xBF");

fputcsv($salida, $encabezados, ';');

foreach ($filas as $fila) {
    $valores = [];
    foreach (array_keys($fila) as $clave) {
        $valores[] = is_scalar($fila[$clave]) ? (string) $fila[$clave] : '';
    }
    fputcsv($salida, $valores, ';');
}

fclose($salida);
exit;