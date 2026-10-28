<?php

/**
 * Noticias y avisos institucionales.
 * Esquema: id_noticia, titulo, contenido, imagen, autor_id, publicado, destacado, publicado_en
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('noticias.ver');

$errores = [];
$puedePublicar = Auth::puede('noticias.gestionar');

if (es_post()) {
    exigir_post();
    $accion = input('accion');

    /* Crear publicación */
    if ($accion === 'crear') {
        Auth::requerir_permiso('noticias.gestionar');

        $titulo    = trim(input('titulo'));
        $contenido = trim(input('contenido'));

        if ($titulo === '') {
            $errores[] = 'El título es obligatorio.';
        } elseif (mb_strlen($titulo) > 180) {
            $errores[] = 'El título no debe superar los 180 caracteres.';
        }
        if ($contenido === '') {
            $errores[] = 'El contenido no puede estar vacío.';
        } elseif (mb_strlen($contenido) > 5000) {
            $errores[] = 'El contenido es demasiado largo (máximo 5000 caracteres).';
        }
        /* Casilla sin marcar = borrador; nunca debe considerarse inválido */
        $publicado = input('publicado', '0') === '1' ? 1 : 0;

        if (!$errores) {
            db()->query(
                "INSERT INTO noticias (titulo, contenido, autor_id, publicado, destacado, publicado_en)
                 VALUES (:t, :c, :a, :p, :d, NOW())",
                [
                    ':t' => $titulo,
                    ':c' => $contenido,
                    ':a' => Auth::id(),
                    ':p' => $publicado,
                    ':d' => input('destacado') ? 1 : 0,
                ]
            );
            $id = (int) db()->conectar()->lastInsertId();
            Auth::registrar('crear', 'noticias', $id, "Publicación «{$titulo}»");

            if ($publicado === 1) {
                $usuarios = db()->todas("SELECT id_usuario FROM usuarios WHERE activo = 1 AND id_usuario <> :yo", [':yo' => Auth::id()]);
                foreach ($usuarios as $u) {
                    Notificacion::crear(
                        (int) $u['id_usuario'],
                        $titulo,
                        mb_substr($contenido, 0, 150),
                        'info',
                        'views/noticias/listar.php'
                    );
                }
            }

            flash_set('success', $publicado === 1 ? 'Publicación guardada y notificada.' : 'Borrador guardado.');
            redirigir('listar.php');
        }
    }

    /* Cambiar estado */
    if ($accion === 'estado') {
        Auth::requerir_permiso('noticias.gestionar');
        $id = input_int('id');
        db()->query("UPDATE noticias SET publicado = 1 - publicado WHERE id_noticia = :id", [':id' => $id]);
        Auth::registrar('actualizar', 'noticias', $id, 'Estado de publicación cambiado');
        flash_set('success', 'Estado actualizado.');
        redirigir('listar.php?' . http_build_query(array_filter([
            'q'         => input('f_q'),
            'visibles'  => input('f_visibles'),
            'pag'       => input_int('f_pag') ?: null,
        ])));
    }

    /* Destacar */
    if ($accion === 'destacar') {
        Auth::requerir_permiso('noticias.gestionar');
        $id = input_int('id');
        db()->query("UPDATE noticias SET destacado = 1 - destacado WHERE id_noticia = :id", [':id' => $id]);
        Auth::registrar('actualizar', 'noticias', $id, 'Publicación destacada');
        flash_set('success', 'Destacado actualizado.');
        redirigir('listar.php?' . http_build_query(array_filter([
            'q'        => input('f_q'),
            'visibles' => input('f_visibles'),
            'pag'      => input_int('f_pag') ?: null,
        ])));
    }

    /* Eliminar */
    if ($accion === 'eliminar') {
        Auth::requerir_permiso('noticias.gestionar');
        $id = input_int('id');
        db()->conectar()->prepare("DELETE FROM noticias WHERE id_noticia = :id")->execute([':id' => $id]);
        Auth::registrar('eliminar', 'noticias', $id, 'Publicación eliminada');
        flash_set('success', 'Publicación eliminada.');
        redirigir('listar.php?' . http_build_query(array_filter([
            'q'        => input('f_q'),
            'visibles' => input('f_visibles'),
            'pag'      => input_int('f_pag') ?: null,
        ])));
    }
}

$q        = input('q');
$filtro   = input('visibles');
$pag      = max(1, input_int('pag', 1));
$porPagina = 6;

$filtros = [];
if ($q !== '') {
    $filtros['n.titulo'] = ['like', $q];
}
if ($filtro === 'publicados') {
    $filtros['n.publicado'] = 1;
} elseif ($filtro === 'borradores') {
    $filtros['n.publicado'] = 0;
} elseif ($filtro === 'destacados') {
    $filtros['n.destacado'] = 1;
}

/* Los roles sin permiso de gestión solo ven lo publicado */
if (!$puedePublicar) {
    $filtros['n.publicado'] = 1;
}

$join = 'LEFT JOIN usuarios u ON u.id_usuario = n.autor_id';

$total = Paginador::contar('noticias n', $filtros, $join);
$paginador = new Paginador($total, $porPagina, $pag);

$noticias = Paginador::obtener(
    'noticias n',
    ['n.id_noticia', 'n.titulo', 'n.contenido', 'n.publicado', 'n.destacado', 'n.publicado_en',
     'u.nombres AS autor_n', 'u.apellidos AS autor_a'],
    $filtros,
    'n.destacado DESC, n.publicado_en DESC',
    $porPagina,
    $paginador->offset(),
    $join
)->fetchAll();

$parametros = array_filter(['q' => $q, 'visibles' => $filtro, 'pag' => $pag > 1 ? $pag : null]);
$urlActual = base_url('views/noticias/listar.php') . ($parametros ? '?' . http_build_query($parametros) : '');

$titulo    = 'Noticias';
$subtitulo = $total . ' ' . ($total === 1 ? 'publicación' : 'publicaciones');
$seccion   = 'noticias';

require_once APP_ROOT . '/layout/header.php';
?>

<?php if ($errores): ?>
    <div class="alert alert-error">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
        </svg>
        <div>
            <ul style="margin:0;padding-left:16px">
                <?php foreach ($errores as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($puedePublicar): ?>
    <div class="panel mb-3" id="nueva">
        <div class="panel-head">
            <div>
                <h2>Nueva publicación</h2>
                <p>Se envía notificación a los usuarios activos</p>
            </div>
        </div>
        <div class="panel-body">
            <form method="POST" novalidate>
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="crear">

                <div class="form-grid">
                    <div class="field">
                        <label for="nt">Título *</label>
                        <input class="input" type="text" id="nt" name="titulo" maxlength="180" required
                            value="<?= e(input('titulo')) ?>"
                            placeholder="Inicio de clases del próximo periodo">
                    </div>

                    <div class="field">
                        <label for="nc">Contenido *</label>
                        <textarea class="input" id="nc" name="contenido" rows="4" maxlength="5000" required
                            placeholder="Detalla la información que verá la comunidad…"><?= e(input('contenido')) ?></textarea>
                    </div>

                    <div class="flex gap-md" style="flex-wrap:wrap;align-items:center">
                        <label class="check">
                            <input type="checkbox" name="publicado" value="1" checked>
                            <span>Publicar ahora</span>
                        </label>
                        <label class="check">
                            <input type="checkbox" name="destacado" value="1">
                            <span>Destacar en el inicio</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            Guardar publicación
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<form class="toolbar" method="GET" novalidate>
    <div class="search-wrap">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="11" cy="11" r="7" />
            <path d="M21 21l-4.35-4.35" />
        </svg>
        <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por título…">
    </div>

    <select class="input" name="visibles" data-auto-submit>
        <option value="">Todas</option>
        <option value="publicados" <?= $filtro === 'publicados' ? 'selected' : '' ?>>Publicadas</option>
        <option value="borradores" <?= $filtro === 'borradores' ? 'selected' : '' ?>>Borradores</option>
        <option value="destacados" <?= $filtro === 'destacados' ? 'selected' : '' ?>>Destacadas</option>
    </select>

    <div class="toolbar-right">
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        <?php if ($parametros): ?>
            <a class="btn btn-ghost btn-sm" href="listar.php">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if (!$noticias): ?>
    <div class="panel">
        <div class="empty">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 4h16v16H4zM4 9h16M9 20V9" />
            </svg>
            <h3><?= $parametros ? 'Sin resultados' : 'Aún no hay publicaciones' ?></h3>
            <p><?= $parametros
                ? 'Ninguna publicación coincide con el filtro aplicado.'
                : 'Publica el primer aviso para la comunidad.' ?></p>
            <?php if ($puedePublicar): ?>
                <a class="btn btn-primary" href="#nueva">Crear publicación</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="grid-cards">
        <?php foreach ($noticias as $n): ?>
            <article class="card">
                <div class="card-head">
                    <div class="flex gap-sm" style="flex-wrap:wrap">
                        <?php if ($n['publicado']): ?>
                            <span class="badge badge-green">Publicado</span>
                        <?php else: ?>
                            <span class="badge badge-gray">Borrador</span>
                        <?php endif; ?>
                        <?php if ($n['destacado']): ?>
                            <span class="badge badge-blue no-dot">Destacada</span>
                        <?php endif; ?>
                    </div>
                    <small class="muted nowrap"><?= e(fecha($n['publicado_en'], 'd/m/Y')) ?></small>
                </div>

                <h3><?= e($n['titulo']) ?></h3>
                <p><?= e(mb_substr($n['contenido'], 0, 170)) ?><?= mb_strlen($n['contenido']) > 170 ? '…' : '' ?></p>

                <div class="card-foot">
                    <small class="muted">
                        <?= e(trim($n['autor_n'] . ' ' . $n['autor_a']) ?: 'Sistema') ?>
                    </small>

                    <?php if ($puedePublicar): ?>
                        <div class="flex gap-sm">
                            <form method="POST">
                                <?= csrf_input() ?>
                                <input type="hidden" name="accion" value="estado">
                                <input type="hidden" name="id" value="<?= (int) $n['id_noticia'] ?>">
                                <input type="hidden" name="f_q" value="<?= e($q) ?>">
                                <input type="hidden" name="f_visibles" value="<?= e($filtro) ?>">
                                <input type="hidden" name="f_pag" value="<?= (int) $pag ?>">
                                <button class="btn btn-ghost btn-sm" type="submit"
                                    title="<?= $n['publicado'] ? 'Despublicar' : 'Publicar' ?>">
                                    <?= $n['publicado'] ? 'Ocultar' : 'Publicar' ?>
                                </button>
                            </form>

                            <form method="POST">
                                <?= csrf_input() ?>
                                <input type="hidden" name="accion" value="destacar">
                                <input type="hidden" name="id" value="<?= (int) $n['id_noticia'] ?>">
                                <input type="hidden" name="f_q" value="<?= e($q) ?>">
                                <input type="hidden" name="f_visibles" value="<?= e($filtro) ?>">
                                <input type="hidden" name="f_pag" value="<?= (int) $pag ?>">
                                <button class="btn btn-ghost btn-sm" type="submit" title="Destacar">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 2l2.9 6.3 6.6.8-4.9 4.6 1.3 6.6L12 17l-5.9 3.3 1.3-6.6L2.5 9.1l6.6-.8L12 2z" />
                                    </svg>
                                </button>
                            </form>

                            <form method="POST" data-confirmar="¿Eliminar «<?= e($n['titulo']) ?>»?">
                                <?= csrf_input() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $n['id_noticia'] ?>">
                                <input type="hidden" name="f_q" value="<?= e($q) ?>">
                                <input type="hidden" name="f_visibles" value="<?= e($filtro) ?>">
                                <input type="hidden" name="f_pag" value="<?= (int) $pag ?>">
                                <button class="btn btn-ghost btn-sm" type="submit" title="Eliminar" style="color:var(--danger)">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?= $paginador->render($parametros) ?>
<?php endif; ?>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>