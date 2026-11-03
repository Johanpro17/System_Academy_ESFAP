<?php

/**
 * Layout superior del panel: <head>, sidebar, topbar.
 *
 * Variables opcionales antes del include:
 *   $titulo, $subtitulo, $seccion, $accionesExtra, $ancho ('content-narrow')
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../core/bootstrap.php';
}

$titulo        = $titulo        ?? 'Panel';
$subtitulo     = $subtitulo     ?? '';
$seccion       = $seccion       ?? '';
$ancho         = $ancho         ?? '';
$accionesExtra = $accionesExtra ?? '';
$paginaActual  = nav_pagina_actual();

$tema = $_SESSION['tema'] ?? ($_COOKIE['tema'] ?? 'claro');
if ($tema !== 'oscuro' && $tema !== 'claro') {
    $tema = 'claro';
}

$noLeidas = class_exists('Notificacion') ? Notificacion::no_leidas() : 0;

/* --- Íconos del menú --- */
$iconos = [
    'dashboard'     => 'M3 12l9-8 9 8M5 10v10a1 1 0 001 1h12a1 1 0 001-1V10',
    'estudiantes'   => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z',
    'profesores'    => 'M12 14l9-5-9-5-9 5 9 5zm0 0v6m9-6v5c0 1.7-4 3-9 3s-9-1.3-9-3v-5',
    'cursos'        => 'M4 19.5A2.5 2.5 0 016.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z',
    'matriculas'    => 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11',
    'horarios'      => 'M12 6v6l4 2M12 22a10 10 0 100-20 10 10 0 000 20z',
    'notas'         => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.5 15.5l-4 1 1-4 9-9z',
    'reportes'      => 'M4 20V10m5 10V4m5 16v-7m5 7V8',
    'noticias'      => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2zM7 10h7M7 14h5',
    'auditoria'     => 'M9 12h6m-6 4h6M9 8h2m9-2v16l-3-2-3 2-3-2-3 2V6a2 2 0 012-2h6l3 2z',
    'usuarios'      => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
    'perfil'        => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
    'salir'         => 'M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4m7 14l5-5-5-5m5 5H9',

    /* Íconos de los grupos plegables del menú */
    'grupo_registros'      => 'M3 7a2 2 0 012-2h3.6l2 2H19a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
    'grupo_gestion'        => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-1 8l2 2 4-4',
    'grupo_comunicacion'   => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2zM7 10h7M7 14h5',
    'grupo_reportes'       => 'M4 20V10m5 10V4m5 16v-7m5 7V8',
    'grupo_administracion' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33h.08a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51h.08a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82v.08a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z',
    'grupo_cuenta'         => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
];

/**
 * Ruta de la página actual en la misma forma que usan los enlaces del menú
 * ('dashboard.php', 'views/estudiantes/listar.php', ...).
 */
function nav_pagina_actual(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    foreach (['/public/', '/views/'] as $marcador) {
        $pos = strpos($script, $marcador);
        if ($pos !== false) {
            $script = substr($script, $pos + 1);
            break;
        }
    }

    if (strpos($script, 'public/') === 0) {
        $script = substr($script, 7);
    }

    return $script !== '' ? $script : basename(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
}

/**
 * ¿Coincide la ruta del menú con la página actual?
 * Marca también las vistas hermanas (crear, editar, constancia...) de esa sección.
 */
function nav_coincide(string $ruta, string $actual): bool
{
    if ($actual === $ruta) {
        return true;
    }

    $directorio = dirname($ruta);

    return $directorio !== '.' && $directorio === dirname($actual);
}

/**
 * Genera un enlace del menú.
 */
function nav_link(string $clave, string $ruta, string $texto, string $actual, array $iconos, ?int $badge = null): void
{
    $href = base_url($ruta);
    $activo = nav_coincide($ruta, $actual);
    echo '<a class="nav-item' . ($activo ? ' active' : '') . '" href="' . e($href) . '"'
        . ($activo ? ' aria-current="page"' : '') . '>';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($iconos[$clave] ?? $iconos['dashboard']) . '"/></svg>';
    echo '<span>' . e($texto) . '</span>';
    if ($badge) {
        echo '<span class="nav-badge">' . (int) $badge . '</span>';
    }
    echo '</a>';
}

/**
 * ¿Es la ruta actual alguna de las rutas dadas?
 */
function nav_activa(array $rutas, string $actual): bool
{
    foreach ($rutas as $ruta) {
        if (nav_coincide($ruta, $actual)) {
            return true;
        }
    }
    return false;
}

/**
 * Renderiza un grupo del menú con su cabecera plegable (acordeón).
 *
 * @param string   $id      Identificador estable del grupo (para recordar el estado)
 * @param string   $texto   Etiqueta visible del grupo
 * @param string   $clave   Icono del grupo en $iconos
 * @param bool     $abierto Si debe mostrarse desplegado
 * @param callable $items   Genera los enlaces del grupo
 */
function nav_grupo(string $id, string $texto, string $clave, array $iconos, bool $abierto, callable $items): void
{
    $gid = 'grupo-' . $id;

    echo '<div class="nav-group' . ($abierto ? ' abierto' : '') . '">';
    echo '<button class="nav-group-toggle" type="button" data-nav-group="' . e($gid) . '"'
        . ' aria-expanded="' . ($abierto ? 'true' : 'false') . '" aria-controls="' . e($gid) . '">';
    echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($iconos[$clave] ?? $iconos['dashboard']) . '"/></svg>';
    echo '<span>' . e($texto) . '</span>';
    echo '<svg class="nav-caret" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
    echo '</button>';
    echo '<div class="nav-group-body" id="' . e($gid) . '">';
    $items();
    echo '</div>';
    echo '</div>';
}

$esAdmin      = Auth::es_admin();
$esProfesor   = Auth::es_profesor();
$esSecretaria = Auth::es_secretaria();
$esAuditoria  = Auth::es_auditoria();
$rol          = Auth::rol();

/* Un grupo del menú solo aparece si el rol tiene al menos un permiso dentro de él. */
$verRegistros      = Auth::puede('estudiantes.ver') || Auth::puede('profesores.ver') || Auth::puede('cursos.ver');
$verGestion        = Auth::puede('matriculas.ver') || Auth::puede('notas.ver') || Auth::puede('horarios.ver');
$verComunicacion   = Auth::puede('noticias.ver');
$verReportes       = Auth::puede('reportes.ver');
$verAdministracion = Auth::puede('usuarios.ver') || Auth::puede('auditoria.ver');

/* El profesor solo ve sus propios registros. */
$etiquetaEstudiantes = $esProfesor ? 'Mis estudiantes' : 'Estudiantes';
$etiquetaCursos      = $esProfesor ? 'Mis cursos' : 'Cursos';
$etiquetaNotas       = $esProfesor ? 'Mis notas' : 'Notas';
$etiquetaHorarios    = $esProfesor ? 'Mi horario' : 'Horarios';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= e($tema) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo) ?> · <?= e(APP_NOMBRE) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/sistema.css')) ?>?v=<?= (int) @filemtime(APP_ROOT . '/public/assets/css/sistema.css') ?>">
</head>

<body>
    <div class="scrim" aria-hidden="true"></div>

    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <span class="brand-mark" aria-hidden="true"><?= e(APP_SIGLAS) ?></span>
                <div>
                    <b><?= e(APP_NOMBRE) ?></b>
                    <small>Panel de control</small>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Navegación del panel">
                    <?php nav_link('dashboard', $esProfesor ? 'profesor.php' : 'dashboard.php', 'Resumen', $paginaActual, $iconos); ?>

                    <?php if ($verRegistros): ?>
                        <?php nav_grupo(
                            'registros',
                            'Registros',
                            'grupo_registros',
                            $iconos,
                            nav_activa(['views/estudiantes/listar.php', 'views/profesores/listar.php', 'views/cursos/listar.php'], $paginaActual),
                            function () use ($paginaActual, $iconos, $etiquetaEstudiantes, $etiquetaCursos): void {
                                if (Auth::puede('estudiantes.ver')) {
                                    nav_link('estudiantes', 'views/estudiantes/listar.php', $etiquetaEstudiantes, $paginaActual, $iconos);
                                }
                                if (Auth::puede('profesores.ver')) {
                                    nav_link('profesores', 'views/profesores/listar.php', 'Profesores', $paginaActual, $iconos);
                                }
                                if (Auth::puede('cursos.ver')) {
                                    nav_link('cursos', 'views/cursos/listar.php', $etiquetaCursos, $paginaActual, $iconos);
                                }
                            }
                        ); ?>
                    <?php endif; ?>

                    <?php if ($verGestion): ?>
                        <?php nav_grupo(
                            'gestion',
                            'Gestión',
                            'grupo_gestion',
                            $iconos,
                            nav_activa(['views/matriculas/listar.php', 'views/notas/listar.php', 'views/horarios/ver.php'], $paginaActual),
                            function () use ($paginaActual, $iconos, $etiquetaNotas, $etiquetaHorarios): void {
                                if (Auth::puede('matriculas.ver')) {
                                    nav_link('matriculas', 'views/matriculas/listar.php', 'Matrículas', $paginaActual, $iconos);
                                }
                                if (Auth::puede('notas.ver')) {
                                    nav_link('notas', 'views/notas/listar.php', $etiquetaNotas, $paginaActual, $iconos);
                                }
                                if (Auth::puede('horarios.ver')) {
                                    nav_link('horarios', 'views/horarios/ver.php', $etiquetaHorarios, $paginaActual, $iconos);
                                }
                            }
                        ); ?>
                    <?php endif; ?>

                    <?php if ($verComunicacion): ?>
                        <?php nav_grupo(
                            'comunicacion',
                            'Comunicación',
                            'grupo_comunicacion',
                            $iconos,
                            nav_activa(['views/noticias/listar.php'], $paginaActual),
                            function () use ($paginaActual, $iconos): void {
                                if (Auth::puede('noticias.ver')) {
                                    nav_link('noticias', 'views/noticias/listar.php', 'Noticias', $paginaActual, $iconos);
                                }
                            }
                        ); ?>
                    <?php endif; ?>

                    <?php if ($verReportes): ?>
                        <?php nav_grupo(
                            'reportes',
                            'Reportes',
                            'grupo_reportes',
                            $iconos,
                            nav_activa(['views/reportes/generar.php', 'views/estudiantes/reportes/promedios.php'], $paginaActual),
                            function () use ($paginaActual, $iconos): void {
                                nav_link('reportes', 'views/reportes/generar.php', 'Generar reportes', $paginaActual, $iconos);
                                nav_link('reportes', 'views/estudiantes/reportes/promedios.php', 'Promedios', $paginaActual, $iconos);
                            }
                        ); ?>
                    <?php endif; ?>

                    <?php if ($verAdministracion): ?>
                        <?php nav_grupo(
                            'administracion',
                            'Administración',
                            'grupo_administracion',
                            $iconos,
                            nav_activa(['views/usuarios/listar.php', 'views/auditoria/listar.php'], $paginaActual),
                            function () use ($paginaActual, $iconos): void {
                                if (Auth::puede('usuarios.ver')) {
                                    nav_link('usuarios', 'views/usuarios/listar.php', 'Usuarios', $paginaActual, $iconos);
                                }
                                if (Auth::puede('auditoria.ver')) {
                                    nav_link('auditoria', 'views/auditoria/listar.php', 'Auditoría', $paginaActual, $iconos);
                                }
                            }
                        ); ?>
                    <?php endif; ?>

                    <?php nav_grupo(
                        'cuenta',
                        'Cuenta',
                        'grupo_cuenta',
                        $iconos,
                        true,
                        function () use ($paginaActual, $iconos): void {
                            nav_link('perfil', 'perfil.php', 'Mi perfil', $paginaActual, $iconos);
                            nav_link('salir', 'logout.php', 'Cerrar sesión', $paginaActual, $iconos);
                        }
                    ); ?>
            </nav>

            <script>
                /* Restaura el estado plegado/desplegado de los grupos antes del primer pintado. */
                (function () {
                    var guardado = {};
                    try {
                        guardado = JSON.parse(localStorage.getItem('sistema.nav.grupos') || '{}');
                    } catch (e) { guardado = {}; }

                    var grupos = document.querySelectorAll('.nav-group');
                    for (var i = 0; i < grupos.length; i++) {
                        var grupo = grupos[i];
                        var btn = grupo.querySelector('[data-nav-group]');
                        if (!btn) continue;

                        var id = btn.getAttribute('data-nav-group');
                        var abierto = (id in guardado)
                            ? guardado[id] === true
                            : grupo.classList.contains('abierto');

                        /* El grupo de la página abierta siempre se muestra. */
                        if (grupo.querySelector('.nav-item.active')) abierto = true;

                        grupo.classList.toggle('abierto', abierto);
                        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                    }
                })();
            </script>

            <div class="sidebar-foot">
                <div class="sidebar-user">
                    <span class="avatar">
                        <?php if (!empty($_SESSION['user']['avatar'])): ?>
                            <img src="<?= e($_SESSION['user']['avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= e(Auth::iniciales_usuario()) ?>
                        <?php endif; ?>
                    </span>
                    <div style="min-width:0">
                        <b><?= e(Auth::nombre()) ?></b>
                        <small><?= e(Auth::nombre_rol($rol)) ?></small>
                    </div>
                </div>
            </div>
        </aside>

        <div class="app-main">
            <header class="topbar">
                <button class="icon-btn sidebar-toggle" data-nav-toggle type="button" aria-label="Abrir menú"
                    aria-controls="sidebar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 12h18M3 6h18M3 18h18" />
                    </svg>
                </button>

                <div class="topbar-title">
                    <h1><?= e($titulo) ?></h1>
                    <?php if ($subtitulo): ?>
                        <p><?= e($subtitulo) ?></p>
                    <?php endif; ?>
                </div>

                <div class="topbar-tools">
                    <?= $accionesExtra ?>

                    <button class="icon-btn" data-tema-toggle type="button" aria-label="Cambiar tema">
                        <svg data-icono-claro viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                        </svg>
                        <svg data-icono-oscuro viewBox="0 0 24 24" aria-hidden="true" style="display:none">
                            <circle cx="12" cy="12" r="4.5" />
                            <path d="M12 2v2m0 16v2M4.2 4.2l1.4 1.4m12.8 12.8l1.4 1.4M2 12h2m16 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" />
                        </svg>
                    </button>

                    <div class="rel">
                        <button class="icon-btn" data-popover="popover-notif" type="button"
                            aria-label="Notificaciones" aria-haspopup="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 8A6 6 0 106 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0" />
                            </svg>
                            <?php if ($noLeidas > 0): ?>
                                <span class="dot-badge"></span>
                            <?php endif; ?>
                        </button>

                        <div class="popover" id="popover-notif">
                            <div class="popover-head">
                                <b>Notificaciones</b>
                                <a href="<?= e(base_url('notificaciones.php?marcar=1')) ?>">Marcar leídas</a>
                            </div>
                            <div class="popover-body">
                                <?php $notis = Notificacion::listar(); ?>
                                <?php if (!$notis): ?>
                                    <div class="empty" style="padding:34px 20px">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M18 8A6 6 0 106 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                        </svg>
                                        <p>No tienes notificaciones.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notis as $n): ?>
                                        <a class="notif <?= $n['leida'] ? '' : 'unread' ?>"
                                            href="<?= e($n['url'] ? base_url($n['url']) : '#') ?>">
                                            <span class="notif-icon <?= e($n['tipo']) ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </span>
                                            <span style="min-width:0;flex:1">
                                                <b><?= e($n['titulo']) ?></b>
                                                <p><?= e($n['mensaje']) ?></p>
                                                <time><?= e(fecha($n['creado_en'], 'd/m/Y H:i')) ?></time>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="rel">
                        <button class="icon-btn" data-popover="popover-user" type="button" aria-label="Menú de usuario"
                            aria-haspopup="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 21a8 8 0 10-12 0M12 13a5 5 0 100-10 5 5 0 000 10z" />
                            </svg>
                        </button>
                        <div class="popover" id="popover-user" style="width:236px">
                            <div class="popover-head" style="gap:12px">
                                <span class="avatar"><?= e(Auth::iniciales_usuario()) ?></span>
                                <div style="min-width:0">
                                    <b style="display:block"><?= e(Auth::nombre()) ?></b>
                                    <small class="muted" style="font-size:.76rem">
                                        <?= e(Auth::nombre_rol($rol)) ?>
                                    </small>
                                </div>
                            </div>
                            <div class="list" style="padding:6px">
                                <a class="list-item" href="<?= e(base_url('perfil.php')) ?>" style="border:0">
                                    <span class="li-body"><b>Mi perfil</b></span>
                                </a>
                                <a class="list-item" href="<?= e(base_url('index.php')) ?>" style="border:0">
                                    <span class="li-body"><b>Ver portada</b></span>
                                </a>
                                <a class="list-item" href="<?= e(base_url('logout.php')) ?>" style="border:0;color:var(--danger)">
                                    <span class="li-body">
                                        <b style="color:var(--danger)">Cerrar sesión</b>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content <?= e($ancho) ?>">
                <?php flash_mostrar(); ?>