<?php

/**
 * Listado de profesores: lectura para administración, secretaría y auditoría.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('profesores.ver');

if (es_post()) {
    exigir_post();
    Auth::requerir_permiso('profesores.gestionar');

    if (input('accion') === 'eliminar') {
        $id = input_int('id');
        try {
            db()->conectar()->prepare("DELETE FROM profesores WHERE id_profesor = :id")->execute([':id' => $id]);
            Auth::registrar('eliminar', 'profesores', $id, 'Profesor eliminado');
            flash_set('success', 'Profesor eliminado.');
        } catch (Throwable $e) {
            flash_set('error', 'No se pudo eliminar: tiene horarios asignados.');
        }
        redirigir('listar.php');
    }

    if (input('accion') === 'alternar') {
        $id = input_int('id');
        db()->query("UPDATE profesores SET activo = 1 - activo WHERE id_profesor = :id", [':id' => $id]);
        Auth::registrar('alternar', 'profesores', $id, 'Estado de profesor cambiado');
        redirigir('listar.php');
    }
}

$q   = input('q');
$pag = max(1, input_int('pag', 1));
$porPagina = 12;

$filtros = [];
if ($q !== '') {
    $filtros["CONCAT(nombres,' ',apellidos)"] = ['like', $q];
    $filtros['especialidad']                  = ['like', $q];
    $filtros['email']                         = ['like', $q];
}

$total = Paginador::contar('profesores', $filtros);
$paginador = new Paginador($total, $porPagina, $pag);

$columnas = ['id_profesor', 'nombres', 'apellidos', 'especialidad', 'email', 'telefono', 'titulo', 'activo'];
$orden    = 'activo DESC, apellidos ASC, nombres ASC';

$registros = Paginador::obtener('profesores', $columnas, $filtros, $orden, $porPagina, $paginador->offset())->fetchAll();

$paramsFiltros = array_filter(['q' => $q]);

$titulo    = 'Profesores';
$subtitulo = $total . ' ' . ($total === 1 ? 'profesor' : 'profesores') . ' en la plantilla';
$seccion   = 'profesores';
$ancho     = 'content-narrow';

$puedeGestionar = Auth::puede('profesores.gestionar');
$puedeExportar  = Auth::puede('reportes.exportar');

$accionesExtra = $puedeGestionar
    ? '<a class="btn btn-primary btn-sm" href="crear.php">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Nuevo profesor</a>'
    : '';

require_once APP_ROOT . '/layout/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Plantilla de profesores</h2>
            <p>Especialidades, contacto y estado de los profesores</p>
        </div>
        <div class="panel-actions no-print">
            <?php if ($puedeExportar): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(base_url('views/reportes/exportar.php?tipo=profesores')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
                </svg>
                Exportar CSV
            </a>
            <?php endif; ?>
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
                    placeholder="Nombre, especialidad o correo…">
            </div>
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
                    <path d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m9-6v5c0 1.7-4 3-9 3s-9-1.3-9-3v-5" />
                </svg>
                <h3><?= $paramsFiltros ? 'Sin resultados' : 'Sin profesores registrados' ?></h3>
                <p>
                    <?= $paramsFiltros
                        ? 'Ningún profesor coincide con la búsqueda.'
                        : 'Registra al primer profesor para poder asignarle cursos y horarios.' ?>
                </p>
                <?php if ($paramsFiltros): ?>
                    <a class="btn btn-outline" href="listar.php">Limpiar búsqueda</a>
                <?php elseif ($puedeGestionar): ?>
                    <a class="btn btn-primary" href="crear.php">Registrar profesor</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Profesor</th>
                            <th>Especialidad</th>
                            <th>Título</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <?php if ($puedeGestionar): ?>
                                <th class="num no-print">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $p): ?>
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <span class="avatar"><?= e(iniciales($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
                                        <div>
                                            <b><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></b>
                                            <small>#<?= (int) $p['id_profesor'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($p['especialidad'] ?: '—') ?></td>
                                <td><span class="muted"><?= e($p['titulo'] ?: '—') ?></span></td>
                                <td>
                                    <small style="display:block"><?= e($p['email'] ?: '—') ?></small>
                                    <?php if ($p['telefono']): ?>
                                        <small class="muted"><?= e($p['telefono']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $p['activo'] ? 'green' : 'gray' ?>">
                                        <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <?php if ($puedeGestionar): ?>
                                <td class="num no-print">
                                    <div class="flex gap-sm" style="justify-content:flex-end">
                                        <a class="btn btn-outline btn-sm"
                                            href="editar.php?id=<?= (int) $p['id_profesor'] ?>" title="Editar">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.5 15.5l-4 1 1-4 9-9z" />
                                            </svg>
                                        </a>
                                        <form method="POST" class="no-print">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="accion" value="alternar">
                                            <input type="hidden" name="id" value="<?= (int) $p['id_profesor'] ?>">
                                            <button class="btn btn-ghost btn-sm" type="submit"
                                                title="<?= $p['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <?php if ($p['activo']): ?>
                                                        <path d="M18 6L6 18M6 6l12 12" />
                                                    <?php else: ?>
                                                        <path d="M20 6L9 17l-5-5" />
                                                    <?php endif; ?>
                                                </svg>
                                            </button>
                                        </form>
                                        <form method="POST" class="no-print"
                                            data-confirmar="¿Eliminar a <?= e($p['nombres'] . ' ' . $p['apellidos']) ?>?">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int) $p['id_profesor'] ?>">
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