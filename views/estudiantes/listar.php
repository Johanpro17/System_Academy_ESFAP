<?php

/**
 * Listado de estudiantes con búsqueda, filtro y paginación.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('estudiantes.ver');

/* ---------------- Procesamiento ---------------- */

if (es_post()) {
    exigir_post();
    Auth::requerir_permiso('estudiantes.eliminar');

    $accion = input('accion');

    if ($accion === 'eliminar') {
        $id = input_int('id');
        try {
            db()->conectar()->prepare("DELETE FROM estudiantes WHERE id_estudiante = :id")->execute([':id' => $id]);
            Auth::registrar('eliminar', 'estudiantes', $id, 'Estudiante eliminado');
            flash_set('success', 'Estudiante eliminado correctamente.');
        } catch (Throwable $e) {
            flash_set('error', 'No se pudo eliminar: ' . $e->getMessage());
        }
        redirigir('listar.php?' . http_build_query($_GET));
    }
}

/* ---------------- Filtros ---------------- */

$q            = input('q');
$especialidad = input_int('especialidad');
$estado       = input('estado');
$pag          = max(1, input_int('pag', 1));
$porPagina    = 12;

$filtros = [];
$join    = '';

if ($q !== '') {
    $filtros["CONCAT(e.nombres,' ',e.apellidos)"] = ['like', $q];
    $filtros['e.email']                           = ['like', $q];
    $filtros['e.matricula']                       = ['like', $q];
    $filtros['e.dni']                             = ['like', $q];
}
if ($especialidad > 0) {
    $filtros['e.id_especialidad'] = $especialidad;
}
if ($estado !== '') {
    $filtros['e.estado'] = $estado;
}

$join = 'INNER JOIN especialidades esp ON esp.id_especialidad = e.id_especialidad';

$soloProfesor = Auth::es_profesor();
if ($soloProfesor) {
    $idProfesor = db()->valor(
        "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
        [':u' => Auth::id()]
    );
    $join .= ' INNER JOIN (
        SELECT DISTINCT m.id_estudiante
          FROM matriculas m
          JOIN curso_docentes cd ON cd.id_curso = m.id_curso
         WHERE cd.id_profesor = ' . (int) ($idProfesor ?: 0) . '
    ) cursos_asignados ON cursos_asignados.id_estudiante = e.id_estudiante';
}

$total = Paginador::contar('estudiantes e', $filtros, $join);
$paginador = new Paginador($total, $porPagina, $pag);

$columnas = [
    'e.id_estudiante',
    'CONCAT(e.nombres," ",e.apellidos) AS nombre',
    'e.email',
    'e.dni',
    'e.matricula',
    'esp.nombre AS especialidad',
    'e.anio_ingreso',
    'e.estado',
    'e.id_usuario',
];

$orden = 'e.estado ASC, e.apellidos ASC, e.nombres ASC';

$registros = Paginador::obtener('estudiantes e', $columnas, $filtros, $orden, $porPagina, $paginador->offset(), $join)->fetchAll();

$especialidades = db()->todas("SELECT id_especialidad, nombre FROM especialidades ORDER BY nombre ASC");

$paramsFiltros = array_filter(['q' => $q, 'especialidad' => $especialidad ?: '', 'estado' => $estado]);

/* ---------------- Vista ---------------- */

$titulo    = 'Estudiantes';
$subtitulo = $total . ' ' . ($total === 1 ? 'registro' : 'registros') . ' encontrado' . ($total === 1 ? '' : 's');
$seccion   = 'estudiantes';
$ancho     = 'content-narrow';

$puedeCrear    = Auth::puede('estudiantes.crear');
$puedeEditar   = Auth::puede('estudiantes.editar');
$puedeEliminar = Auth::puede('estudiantes.eliminar');
$puedeExportar = Auth::puede('reportes.exportar');
$puedeGestionar = $puedeEditar || $puedeEliminar;

$accionesExtra = $puedeCrear
    ? '<a class="btn btn-primary btn-sm" href="crear.php">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Nuevo estudiante</a>'
    : '';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Padrón de estudiantes</h2>
            <p><?= $soloProfesor
                ? 'Estudiantes inscritos en tus cursos asignados'
                : 'Busca por nombre, correo, matrícula o DNI' ?></p>
        </div>
        <?php if ($puedeExportar): ?>
        <div class="panel-actions no-print">
            <a class="btn btn-outline btn-sm"
                href="<?= e(base_url('views/reportes/exportar.php?tipo=estudiantes' . ($paramsFiltros ? '&' . http_build_query($paramsFiltros) : ''))) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
                </svg>
                Exportar CSV
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel-body">
        <form class="toolbar" method="GET" novalidate>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M21 21l-4.35-4.35" />
                </svg>
                <input class="input" type="search" name="q" value="<?= e($q) ?>"
                    placeholder="Nombre, correo, matrícula o DNI…">
            </div>

            <select class="input" name="especialidad" data-auto-submit>
                <option value="">Todas las especialidades</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= (int) $esp['id_especialidad'] ?>"
                        <?= $especialidad === (int) $esp['id_especialidad'] ? 'selected' : '' ?>>
                        <?= e($esp['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="input" name="estado" data-auto-submit>
                <option value="">Todos los estados</option>
                <option value="activo"   <?= $estado === 'activo' ? 'selected' : '' ?>>Activos</option>
                <option value="egresado" <?= $estado === 'egresado' ? 'selected' : '' ?>>Egresados</option>
                <option value="retirado" <?= $estado === 'retirado' ? 'selected' : '' ?>>Retirados</option>
            </select>

            <div class="toolbar-right">
                <button class="btn btn-primary btn-sm" type="submit">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M21 21l-4.35-4.35" />
                        <circle cx="11" cy="11" r="7" />
                    </svg>
                    Filtrar
                </button>
                <?php if ($paramsFiltros): ?>
                    <a class="btn btn-ghost btn-sm" href="listar.php">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$registros): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 00-4-4H6a4 2 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" />
                </svg>
                <h3><?= $paramsFiltros ? 'Sin resultados' : 'Aún no hay estudiantes' ?></h3>
                <p>
                    <?= $paramsFiltros
                        ? 'Ningún estudiante coincide con los filtros aplicados. Prueba con otros términos.'
                        : 'Registra el primer estudiante para comenzar a construir el padrón.' ?>
                </p>
                <?php if ($paramsFiltros): ?>
                    <a class="btn btn-outline" href="listar.php">Limpiar filtros</a>
                <?php elseif ($puedeCrear): ?>
                    <a class="btn btn-primary" href="crear.php">Registrar estudiante</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>DNI</th>
                            <th>Matrícula</th>
                            <th>Especialidad</th>
                            <th>Ingreso</th>
                            <th>Estado</th>
                            <?php if ($puedeGestionar): ?>
                                <th class="num no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $row):
                            $badges = ['activo' => 'green', 'egresado' => 'blue', 'retirado' => 'gray'];
                        ?>
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <span class="avatar"><?= e(iniciales($row['nombre'])) ?></span>
                                        <div>
                                            <b><?= e($row['nombre']) ?></b>
                                            <small><?= e($row['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="tabular">
                                    <?= $row['dni'] ? e($row['dni']) : '<span class="muted">—</span>' ?>
                                </td>
                                <td class="tabular">
                                    <?= $row['matricula'] ? e($row['matricula']) : '<span class="muted">—</span>' ?>
                                </td>
                                <td><?= e($row['especialidad']) ?></td>
                                <td class="tabular"><?= $row['anio_ingreso'] ? (int) $row['anio_ingreso'] : '—' ?></td>
                                <td>
                                    <span class="badge badge-<?= $badges[$row['estado']] ?? 'gray' ?>">
                                        <?= e(ucfirst($row['estado'] ?? 'activo')) ?>
                                    </span>
                                </td>
                                <?php if ($puedeGestionar): ?>
                                    <td class="num no-print">
                                        <div class="flex gap-sm" style="justify-content:flex-end">
                                            <?php if ($puedeEditar): ?>
                                                <a class="btn btn-outline btn-sm"
                                                    href="editar.php?id=<?= (int) $row['id_estudiante'] ?>"
                                                    title="Editar">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.5 15.5l-4 1 1-4 9-9z" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($puedeEliminar): ?>
                                                <form method="POST" class="no-print"
                                                    data-confirmar="¿Eliminar a <?= e($row['nombre']) ?>? Esta acción no se puede deshacer.">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id_estudiante'] ?>">
                                                    <button class="btn btn-ghost btn-sm" type="submit" title="Eliminar"
                                                        style="color:var(--danger)">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= $paginador->render($paramsFiltros) ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>