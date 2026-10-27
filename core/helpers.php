<?php

/**
 * Funciones de ayuda de uso general.
 */

/** Escapa salida HTML (prevención de XSS). */
function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Raíz web del proyecto (carpeta que contiene /public y /views).
 * Se deduce de la URL del script para no depender de la instalación.
 */
function url_raiz(): string
{
    static $raiz = null;
    if ($raiz !== null) {
        return $raiz;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    foreach (['/public/', '/views/'] as $marcador) {
        $pos = strpos($script, $marcador);
        if ($pos !== false) {
            return $raiz = substr($script, 0, $pos);
        }
    }

    return $raiz = '/Johan/sistema-academico';
}

/**
 * URL absoluta dentro del sistema.
 *
 * - 'views/...', 'layout/...' -> desde la raíz del proyecto
 * - 'assets/...', 'login.php' -> dentro de /public
 */
function base_url(string $ruta = ''): string
{
    $raiz = url_raiz();
    $ruta = ltrim(str_replace('\\', '/', $ruta), '/');

    if ($ruta === '') {
        return $raiz . '/public';
    }

    if (strpos($ruta, 'views/') === 0 || strpos($ruta, 'layout/') === 0) {
        return $raiz . '/' . $ruta;
    }

    return $raiz . '/public/' . $ruta;
}

/** Ruta absoluta desde la carpeta public. */
function url(string $ruta = ''): string
{
    return base_url($ruta === '' ? '' : $ruta);
}

/**
 * Redirección y fin de ejecución.
 * Las rutas relativas se resuelven contra la URL actual (misma carpeta).
 */
function redirigir(string $ruta): void
{
    if (preg_match('#^https?://#i', $ruta)) {
        header('Location: ' . $ruta);
        exit;
    }

    if ($ruta === '') {
        header('Location: ' . base_url());
        exit;
    }

    $limpio = str_replace('\\', '/', $ruta);

    /* Ya es una ruta absoluta respecto al dominio (base_url() la devuelve así):
       se envía tal cual para no duplicar el prefijo del proyecto. */
    if (strpos($limpio, '/') === 0) {
        header('Location: ' . $limpio);
        exit;
    }

    /* Ruta de proyecto: se convierte en absoluta */
    if (strpos($limpio, 'views/') === 0 || strpos($limpio, 'public/') === 0 || strpos($limpio, 'layout/') === 0) {
        header('Location: ' . base_url($ruta));
        exit;
    }

    /* Ruta local: se resuelve contra la carpeta del script actual */
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    header('Location: ' . $dir . '/' . $limpio);
    exit;
}

/** Indica si la petición actual es POST. */
function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Valor limpio de un input. */
function input(string $clave, string $defecto = ''): string
{
    return trim((string) ($_POST[$clave] ?? $_GET[$clave] ?? $defecto));
}

/** Valor entero de un input. */
function input_int(string $clave, int $defecto = 0): int
{
    $v = $_POST[$clave] ?? $_GET[$clave] ?? null;
    return $v === null || $v === '' ? $defecto : (int) $v;
}

/* ------------------------------------------------------------------
 *  Mensajes flash (notificaciones de una sola lectura)
 * ---------------------------------------------------------------- */

function flash_set(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_todos(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

function flash_mostrar(): void
{
    $iconos = [
        'success' => 'M20 6L9 17l-5-5',
        'error'   => 'M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        'warning' => 'M12 9v4m0 4h.01M12 3l9 16H3l9-16z',
        'info'    => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ];

    foreach (flash_todos() as $m) {
        $icono = $iconos[$m['tipo']] ?? $iconos['info'];
        echo '<div class="alert alert-' . e($m['tipo']) . '" role="alert">'
            . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $icono . '"/></svg>'
            . '<div>' . e($m['mensaje']) . '</div>'
            . '<button class="alert-close" type="button" aria-label="Cerrar">&times;</button>'
            . '</div>';
    }
}

/* ------------------------------------------------------------------
 *  CSRF
 * ---------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_valido(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Exige POST + token válido. */
function exigir_post(): void
{
    if (!es_post() || !csrf_valido($_POST['_token'] ?? null)) {
        flash_set('error', 'La sesión del formulario expiró. Inténtalo nuevamente.');
        redirigir($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

/* ------------------------------------------------------------------
 *  Formato y presentación
 * ---------------------------------------------------------------- */

function fecha(?string $valor, string $formato = 'd/m/Y'): string
{
    if (empty($valor)) {
        return '—';
    }
    $ts = strtotime($valor);
    return $ts ? date($formato, $ts) : '—';
}

function moneda(float $valor): string
{
    return number_format($valor, 2, ',', '.');
}

/** Iniciales para el avatar. */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre)) ?: [];
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
}

function slug(string $texto): string
{
    $texto = preg_replace('/\s+/', '-', trim($texto));
    $texto = preg_replace('/[^\p{L}\p{N}\-]/u', '', $texto);
    return mb_strtolower($texto);
}

/** Construye una URL conservando los filtros actuales y cambiando algunos. */
function url_con(array $cambios, array $base = []): string
{
    $params = array_merge($base, $cambios);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}