<?php

/**
 * Inicio de sesión.
 * - CSRF token
 * - Rate limiting persistente (bloqueo temporal)
 * - Detección de credenciales en texto plano (migración segura)
 */

require_once __DIR__ . '/../core/bootstrap.php';

if (Auth::esta_logueado()) {
    redirigir(base_url('dashboard.php'));
}

$error   = '';
$usuario = '';

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/* ---------- Rate limiting ---------- */

/**
 * Registra un intento y determina si ya se debe bloquear.
 */
function registrar_intento(string $identificador, string $ip, bool $exito): void
{
    try {
        db()->query(
            "INSERT INTO login_intentos (identificador, ip, exito) VALUES (:i, :ip, :e)",
            [':i' => mb_substr($identificador, 0, 150), ':ip' => $ip, ':e' => $exito ? 1 : 0]
        );

        /* Poda: la tabla solo interesa dentro de la ventana de bloqueo */
        db()->query("DELETE FROM login_intentos WHERE creado_en < DATE_SUB(NOW(), INTERVAL 1 DAY)");

        if ($exito) {
            db()->query("DELETE FROM login_intentos WHERE exito = 1");
        }
    } catch (Throwable $e) {
        // Si la tabla no existe, el rate limiting no se aplica pero el login funciona
    }
}

/**
 * Calcula, en SQL, cuántos intentos fallidos hay en la ventana y cuánto queda
 * de bloqueo. Se evita usar time() de PHP: su reloj puede no coincidir con el
 * del servidor MySQL y desactivaría el bloqueo en silencio.
 */
function evaluar_bloqueo(array $params): int
{
    try {
        $fila = db()->fila(
            "SELECT COUNT(*) AS intentos,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), MAX(creado_en) + INTERVAL :min MINUTE)) AS restante
               FROM login_intentos
              WHERE exito = 0
                AND creado_en > DATE_SUB(NOW(), INTERVAL :ventana MINUTE)
                AND " . $params['donde'],
            [':min' => APP_MINUTOS_BLOQUEO, ':ventana' => APP_MINUTOS_BLOQUEO] + $params['valores']
        );

        if (!$fila || (int) $fila['intentos'] < APP_MAX_INTENTOS) {
            return 0;
        }

        return (int) $fila['restante'];
    } catch (Throwable $e) {
        error_log('login: no se pudo evaluar el bloqueo - ' . $e->getMessage());
        return 0;
    }
}

/**
 * Segundos restantes de bloqueo de una cuenta desde una IP, o 0.
 */
function segundos_bloqueo(string $identificador, string $ip): int
{
    return evaluar_bloqueo([
        'donde'   => 'identificador = :i AND ip = :ip',
        'valores' => [':i' => $identificador, ':ip' => $ip],
    ]);
}

/**
 * Bloqueo por dirección IP: se evalúa antes de conocer el usuario, de modo que
 * el formulario pueda avisar y deshabilitar el envío. Evita que el bloqueo por
 * cuenta se esquive cambiando de identificador.
 */
function segundos_bloqueo_ip(string $ip): int
{
    return evaluar_bloqueo([
        'donde'   => 'ip = :ip',
        'valores' => [':ip' => $ip],
    ]);
}

$bloqueo = segundos_bloqueo_ip($ip);

/* ---------- Procesamiento ---------- */

if (es_post()) {
    $usuario  = input('username');
    $password = (string) ($_POST['password'] ?? '');
    $token    = $_POST['_token'] ?? null;

    if (!csrf_valido($token)) {
        $error = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } elseif ($usuario === '' || $password === '') {
        $error = 'Escribe tu usuario y contraseña.';
    } else {
        $espera = max(segundos_bloqueo($usuario, $ip), $bloqueo);

        if ($espera > 0) {
            $minutos = (int) ceil($espera / 60);
            $error   = "Demasiados intentos fallidos. Cuenta bloqueada por {$minutos} minuto(s).";
        } else {
            $u = Auth::intentar($usuario, $password);

            if ($u) {
                registrar_intento($usuario, $ip, true);

                Auth::iniciar($u);

                $destino = $_SESSION['redirect_after_login'] ?? base_url('dashboard.php');
                unset($_SESSION['redirect_after_login']);
                redirigir($destino);
            }

            registrar_intento($usuario, $ip, false);

            $restantes = APP_MAX_INTENTOS - (int) db()->valor(
                "SELECT COUNT(*) FROM login_intentos
                  WHERE identificador = :i AND ip = :ip AND exito = 0
                    AND creado_en > DATE_SUB(NOW(), INTERVAL :min MINUTE)",
                [':i' => $usuario, ':ip' => $ip, ':min' => APP_MINUTOS_BLOQUEO],
                0
            );

            $error = 'Credenciales incorrectas o usuario inactivo.';
            if ($restantes > 0 && $restantes <= 2) {
                $error .= " Te quedan {$restantes} intento(s).";
            }
        }
    }
}

$tema = $_SESSION['tema'] ?? ($_COOKIE['tema'] ?? 'claro');
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= e($tema) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Iniciar sesión · <?= e(APP_NOMBRE) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/sistema.css')) ?>">
</head>

<body class="page-auth">
    <div class="auth-wrap">
        <div class="auth-grid">
            <aside class="auth-side">
                <span class="brand-mark" aria-hidden="true" style="background:rgba(255,255,255,.16);box-shadow:none">
                    <?= e(APP_SIGLAS) ?>
                </span>

                <h2>Gestión académica en un solo lugar</h2>
                <p>Matrículas, cursos, docentes, horarios y reportes conectados para toda la institución.</p>

                <ul>
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        Acceso restringido por rol y perfil
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        Sesiones protegidas contra robo de sesión
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        Registro de auditoría de cada acción
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        Bloqueo automático tras intentos fallidos
                    </li>
                </ul>
            </aside>

            <div class="auth-main">
                <div class="auth-logo">
                    <img src="<?= e(base_url('../imagenes/logoInstitucion.jpg')) ?>" alt="Logo de la institución">
                </div>

                <h1>Iniciar sesión</h1>
                <p class="sub">Ingresa con las credenciales asignadas por coordinación.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 8v5m0 3h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                        </svg>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($bloqueo > 0): ?>
                    <div class="alert alert-warning" role="alert">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 9v4m0 4h.01M12 3l9 16H3l9-16z" />
                        </svg>
                        <div>Cuenta bloqueada temporalmente por seguridad.</div>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="on" novalidate>
                    <?= csrf_input() ?>

                    <div class="form-grid">
                        <div class="field">
                            <label for="username">Usuario o correo electrónico</label>
                            <input class="input" type="text" id="username" name="username"
                                value="<?= e($usuario) ?>" required autofocus
                                autocomplete="username" maxlength="150"
                                placeholder="admin@sistema.edu">
                        </div>

                        <div class="field">
                            <label for="password">Contraseña</label>
                            <div class="input-group">
                                <input class="input" type="password" id="password" name="password" required
                                    autocomplete="current-password" maxlength="200" placeholder="••••••••">
                                <button class="input-action" type="button" data-toggle-password="password"
                                    aria-label="Mostrar contraseña">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" style="display:block">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg viewBox="0 0 24 24" aria-hidden="true" style="display:none">
                                        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 10-4.24-4.24M1 1l22 22" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button class="btn btn-primary btn-lg w-full" type="submit"
                            <?= $bloqueo > 0 ? 'disabled' : '' ?>>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3" />
                            </svg>
                            Ingresar al sistema
                        </button>
                    </div>
                </form>

                <span class="lock-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    Conexión protegida · bloquea tras <?= (int) APP_MAX_INTENTOS ?> intentos
                </span>

                <div class="auth-foot">
                    ¿Necesitas ayuda? <a href="<?= e(base_url('index.php')) ?>">Volver a la portada</a>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= e(base_url('assets/js/sistema.js')) ?>"></script>
</body>

</html>