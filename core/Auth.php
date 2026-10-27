<?php

/**
 * Autenticación y autorización por roles.
 */

require_once __DIR__ . '/../config/database.php';

class Auth
{
    public const ROL_ADMIN     = 1;
    public const ROL_DOCENTE    = 2;
    public const ROL_ESTUDIANTE = 3;

    /** Registro de roles: id => [nombre, badge, color] */
    public static function roles(): array
    {
        return [
            self::ROL_ADMIN     => ['nombre' => 'Administrador', 'clase' => 'rol-admin'],
            self::ROL_DOCENTE    => ['nombre' => 'Docente',       'clase' => 'rol-docente'],
            self::ROL_ESTUDIANTE => ['nombre' => 'Estudiante',    'clase' => 'rol-estudiante'],
        ];
    }

    public static function nombre_rol(?int $id): string
    {
        return self::roles()[$id]['nombre'] ?? 'Invitado';
    }

    /**
     * Intenta autenticar. Devuelve el usuario o null.
     */
    public static function intentar(string $login, string $password): ?array
    {
        if ($login === '' || $password === '') {
            return null;
        }

        $db = db();

        $usuario = $db->fila(
            "SELECT id_usuario, nombres, apellidos, email, password_hash, id_rol, activo, avatar
               FROM usuarios
              WHERE (email = :login OR CONCAT(nombres, ' ', apellidos) = :login2)
                AND activo = 1
              LIMIT 1",
            [':login' => $login, ':login2' => $login]
        );

        if (!$usuario) {
            // Comparación en falso para no filtrar por tiempo si el usuario existe
            password_verify($password, '$2y$10$usuarioinexistenteplaceholderhash000000000000000');
            return null;
        }

        // Compatibilidad: hashes antiguos guardados en texto plano
        $hash = (string) $usuario['password_hash'];

        if (!password_verify($password, $hash)) {
            // Migración perezosa: si el hash no es bcrypt pero el campo coincide, se re-hashea
            if (!self::es_bcrypt($hash) && hash_equals($hash, $password)) {
                $nuevo = password_hash($password, PASSWORD_DEFAULT);
                $db->query("UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id", [
                    ':h' => $nuevo,
                    ':id' => $usuario['id_usuario'],
                ]);
            } else {
                return null;
            }
        }

        if (self::es_bcrypt($hash) && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $nuevo = password_hash($password, PASSWORD_DEFAULT);
            $db->query("UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id", [
                ':h' => $nuevo,
                ':id' => $usuario['id_usuario'],
            ]);
        }

        return $usuario;
    }

    private static function es_bcrypt(string $hash): bool
    {
        return (bool) preg_match('/^\$(2[axy])\$(\d{2})\$/', $hash);
    }

    /** Inicia sesión regenerando el ID (anti session fixation). */
    public static function iniciar(array $usuario): void
    {
        self::asegurar_sesion();
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nombres'    => $usuario['nombres'],
            'apellidos'  => $usuario['apellidos'],
            'email'      => $usuario['email'],
            'id_rol'     => isset($usuario['id_rol']) ? (int) $usuario['id_rol'] : self::ROL_ADMIN,
            'avatar'     => $usuario['avatar'] ?? null,
            'nombre'     => $usuario['nombres'] . ' ' . $usuario['apellidos'],
            'rol'        => self::nombre_rol(isset($usuario['id_rol']) ? (int) $usuario['id_rol'] : self::ROL_ADMIN),
            'inicio_sesion' => date('Y-m-d H:i:s'),
        ];

        db()->query(
            "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id",
            [':id' => $usuario['id_usuario']]
        );

        self::registrar('login', 'usuarios', (int) $usuario['id_usuario'], 'Inicio de sesión');
    }

    public static function cerrar(): void
    {
        if (self::esta_logueado()) {
            self::registrar('logout', 'usuarios', (int) self::id(), 'Cierre de sesión');
        }
        self::asegurar_sesion();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function asegurar_sesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(APP_NOMBRE_SESION);
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure'   => !empty($_SERVER['HTTPS']),
            ]);
        }
    }

    /* ---------------- Estado ---------------- */

    public static function esta_logueado(): bool
    {
        return !empty($_SESSION['user']['id_usuario']);
    }

    public static function usuario(): array
    {
        return $_SESSION['user'] ?? [];
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id_usuario'] ?? null;
    }

    public static function rol(): int
    {
        return (int) ($_SESSION['user']['id_rol'] ?? 0);
    }

    public static function nombre(): string
    {
        return (string) ($_SESSION['user']['nombre'] ?? 'Usuario');
    }

    public static function iniciales_usuario(): string
    {
        return iniciales((string) ($_SESSION['user']['nombres'] ?? '') . ' ' . (string) ($_SESSION['user']['apellidos'] ?? ''));
    }

    public static function es_admin(): bool
    {
        return self::rol() === self::ROL_ADMIN;
    }

    /**
     * Verifica si el usuario actual tiene alguno de los roles permitidos.
     */
    public static function tiene_rol(int ...$roles): bool
    {
        return in_array(self::rol(), $roles, true);
    }

    /* ---------------- Guardas ---------------- */

    public static function requerir_login(): void
    {
        if (!self::esta_logueado()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            flash_set('warning', 'Debes iniciar sesión para continuar.');
            redirigir(base_url('login.php'));
        }

        $usuario = db()->fila(
            "SELECT id_usuario, nombres, apellidos, email, id_rol, activo, avatar
               FROM usuarios
              WHERE id_usuario = :id",
            [':id' => self::id()]
        );
        if (!$usuario || !(int) $usuario['activo']) {
            self::cerrar();
            redirigir(base_url('login.php'));
        }

        $_SESSION['user'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nombres' => $usuario['nombres'],
            'apellidos' => $usuario['apellidos'],
            'email' => $usuario['email'],
            'id_rol' => (int) $usuario['id_rol'],
            'avatar' => $usuario['avatar'],
            'nombre' => $usuario['nombres'] . ' ' . $usuario['apellidos'],
            'rol' => self::nombre_rol((int) $usuario['id_rol']),
        ];
    }

    public static function requerir_rol(int ...$roles): void
    {
        self::requerir_login();
        if (!self::tiene_rol(...$roles)) {
            http_response_code(403);
            require_once APP_ROOT . '/public/403.php';
            exit;
        }
    }

    public static function requerir_admin(): void
    {
        self::requerir_rol(self::ROL_ADMIN);
    }

    public static function es_estudiante(): bool
    {
        return self::rol() === self::ROL_ESTUDIANTE;
    }

    /* ---------------- Auditoría ---------------- */

    public static function registrar(string $accion, string $entidad, ?int $id = null, string $detalle = ''): void
    {
        try {
            db()->query(
                "INSERT INTO auditoria (id_usuario, accion, entidad, entidad_id, detalle, ip)
                 VALUES (:u, :a, :e, :i, :d, :ip)",
                [
                    ':u'  => self::id(),
                    ':a'  => $accion,
                    ':e'  => $entidad,
                    ':i'  => $id,
                    ':d'  => mb_substr($detalle, 0, 255),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ]
            );
        } catch (Throwable $e) {
            // La auditoría nunca debe romper la aplicación
        }
    }
}