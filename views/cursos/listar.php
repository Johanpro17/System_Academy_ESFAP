<?php

/**
 * Listado de cursos.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_rol(1, 2);

if (es_post()) {
    exigir_post();
    Auth::requerir_admin();

    if (input('accion') === 'eliminar') {
        $id = input_int('id');
        try {
            db()->conectar()->prepare("DELETE FROM cursos WHERE id_curso = :id")->execute([':id' => $id]);
            Auth::registrar('eliminar', 'cursos', $id, 'Curso eliminado');
            flash_set('success', 'Curso eliminado.');
        } catch (Throwable $e) {
            flash_set('error', 'No se pudo eliminar: puede tener matrículas o notas asociadas.');
        }
        redirigir('listar.php');
    }

    if (input('accion') === 'alternar') {
        $id = input_int('id');
        db()->query("UPDATE cursos SET activo = 1 - activo WHERE id_curso = :id", [':id' => $id]);
        Auth::registrar('alternar', 'cursos', $id, 'Estado de curso cambiado');
        redirigir('listar.php');
    }
}

$q     = input('q');
$area  = input('area');
$pag   = max(1, input_int('pag', 1));
$porPagina = 12;

$tabla = 'cursos c';
$join = '';
$filtros = [];
$idProfesor = null;
if (!Auth::es_admin()) {
    $idProfesor = db()->valor(
        "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
        [':u' => Auth::id()]
    );
    $join = 'JOIN curso_docentes cd ON cd.id_curso = c.id_curso';
    $filtros['cd.id_profesor'] = $idProfesor ?: 0;
}
if ($q !== '') {
    $filtros['c.nombre']      = ['like', $q];
    $filtros['c.descripcion'] = ['like', $q];
    $filtros['c.codigo']      = ['like', $q];
}
if ($area !== '') {
    $filtros['c.area'] = $area;
}

$total = Paginador::contar($tabla, $filtros, $join);
$paginador = new Paginador($total, $porPagina, $pag);

$columnas = ['c.id_curso', 'c.codigo', 'c.nombre', 'c.descripcion', 'c.creditos', 'c.area', 'c.activo'];
$orden    = 'c.activo DESC, c.nombre ASC';

$registros = Paginador::obtener($tabla, $columnas, $filtros, $orden, $porPagina, $paginador->offset(), $join)->fetchAll();

$areas = db()->todas("SELECT DISTINCT area FROM cursos WHERE area IS NOT NULL AND area <> '' ORDER BY area ASC");

$paramsFiltros = array_filter(['q' => $q, 'area' => $area]);

$titulo    = 'Cursos';
$subtitulo = $total . ' ' . ($total === 1 ? 'curso' : 'cursos') . ' en el catálogo';
$seccion   = 'cursos';
$ancho     = 'content-narrow';

$accionesExtra = Auth::es_admin()
    ? '<a class="btn btn-primary btn-sm" href="crear.php">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Nuevo curso</a>'
    : '';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Catálogo de cursos</h2>
            <p>Oferta académica, créditos y áreas de formación</p>
        </div>
        <div class="panel-actions no-print">
            <a class="btn btn-outline btn-sm"
                href="<?= e(base_url('views/reportes/exportar.php?tipo=cursos')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
                </svg>
                Exportar CSV
            </a>
        </div>
    </div>

    <div class="panel-body">
        <form class="toolbar" method="GET" novalidate>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M21 21l-4.35-4.35" />
                </svg>
                <input class="input" type="search" name="q" value="<?= e($q) ?>"
                    placeholder="Nombre, código o descripción…">
            </div>

            <?php if ($areas): ?>
                <select class="input" name="area" data-auto-submit>
                    <option value="">Todas las áreas</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= e($a['area']) ?>" <?= $area === $a['area'] ? 'selected' : '' ?>>
                            <?= e($a['area']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <div class="toolbar-right">
                <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
                <?php if ($paramsFiltros): ?>
                    <a class="btn btn-ghost btn-sm" href="listar.php">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$registros): ?>
            <div class="empty">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" />
                </svg>
                <h3><?= $paramsFiltros ? 'Sin resultados' : 'Catálogo vacío' ?></h3>
                <p>
                    <?= $paramsFiltros
                        ? 'Ningún curso coincide con los filtros aplicados.'
                        : 'Crea el primer curso para empezar a construir la oferta académica.' ?>
                </p>
                <?php if ($paramsFiltros): ?>
                    <a class="btn btn-outline" href="listar.php">Limpiar filtros</a>
                <?php elseif (Auth::es_admin()): ?>
                    <a class="btn btn-primary" href="crear.php">Crear curso</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Curso</th>
                            <th>Área</th>
                            <th class="num">Créditos</th>
                            <th>Estado</th>
                            <?php if (Auth::es_admin()): ?>
                                <th class="num no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $c): ?>
                            <tr>
                                <td class="tabular muted"><?= $c['codigo'] ? e($c['codigo']) : '—' ?></td>
                                <td>
                                    <b style="font-weight:600"><?= e($c['nombre']) ?></b>
                                    <?php if ($c['descripcion']): ?>
                                        <small class="muted" style="display:block;font-size:.78rem">
                                            <?= e(mb_strimwidth($c['descripcion'], 0, 72, '…')) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['area']): ?>
                                        <span class="badge badge-blue no-dot"><?= e($c['area']) ?></span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num tabular" style="font-weight:700"><?= (int) $c['creditos'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $c['activo'] ? 'green' : 'gray' ?>">
                                        <?= $c['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <?php if (Auth::es_admin()): ?>
                                    <td class="num no-print">
                                        <div class="flex gap-sm" style="justify-content:flex-end">
                                            <a class="btn btn-outline btn-sm" href="editar.php?id=<?= (int) $c['id_curso'] ?>"
                                                title="Editar">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.5 15.5l-4 1 1-4 9-9z" />
                                                </svg>
                                            </a>
                                            <form method="POST" class="no-print">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="accion" value="alternar">
                                                <input type="hidden" name="id" value="<?= (int) $c['id_curso'] ?>">
                                                <button class="btn btn-ghost btn-sm" type="submit"
                                                    title="<?= $c['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <?php if ($c['activo']): ?>
                                                            <path d="M18 6L6 18M6 6l12 12" />
                                                        <?php else: ?>
                                                            <path d="M20 6L9 17l-5-5" />
                                                        <?php endif; ?>
                                                    </svg>
                                                </button>
                                            </form>
                                            <form method="POST" class="no-print"
                                                data-confirmar="¿Eliminar el curso «<?= e($c['nombre']) ?>»?">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= (int) $c['id_curso'] ?>">
                                                <button class="btn btn-ghost btn-sm" type="submit" title="Eliminar"
                                                    style="color:var(--danger)">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                                    </svg>
                                                </button>
                                            </form>
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