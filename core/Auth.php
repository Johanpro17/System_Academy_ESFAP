<?php

/**
 * Autenticación y autorización por roles.
 */

require_once __DIR__ . '/../config/database.php';

class Auth
{
    public const ROL_ADMIN      = 1;
    public const ROL_PROFESOR   = 2;
    public const ROL_SECRETARIA = 4;
    public const ROL_AUDITORIA  = 5;

    /** Registro de roles del sistema: id => [nombre, clase, descripción] */
    public static function roles(): array
    {
        return [
            self::ROL_ADMIN      => [
                'nombre'      => 'Administrador',
                'clase'       => 'rol-admin',
                'descripcion' => 'Acceso total al sistema',
            ],
            self::ROL_PROFESOR   => [
                'nombre'      => 'Profesor',
                'clase'       => 'rol-profesor',
                'descripcion' => 'Gestiona notas y consulta sus cursos asignados',
            ],
            self::ROL_SECRETARIA => [
                'nombre'      => 'Secretaría',
                'clase'       => 'rol-secretaria',
                'descripcion' => 'Gestiona estudiantes, matrículas y constancias',
            ],
            self::ROL_AUDITORIA  => [
                'nombre'      => 'Auditoría',
                'clase'       => 'rol-auditoria',
                'descripcion' => 'Consulta toda la información y la bitácora del sistema',
            ],
        ];
    }

    public static function nombre_rol(?int $id): string
    {
        return self::roles()[$id]['nombre'] ?? 'Invitado';
    }

    /**
     * Matriz de permisos por rol. El administrador recibe comodín total.
     *
     * @return array<int, string[]>
     */
    public static function permisos_por_rol(): array
    {
        return [
            self::ROL_ADMIN => ['*'],

            self::ROL_PROFESOR => [
                'dashboard.ver',
                'estudiantes.ver',
                'cursos.ver',
                'matriculas.ver',
                'notas.ver',
                'notas.editar',
                'horarios.ver',
                'reportes.ver',
                'reportes.exportar',
                'noticias.ver',
                'perfil.ver',
            ],

            self::ROL_SECRETARIA => [
                'dashboard.ver',
                'estudiantes.ver',
                'estudiantes.crear',
                'estudiantes.editar',
                'estudiantes.eliminar',
                'profesores.ver',
                'cursos.ver',
                'matriculas.ver',
                'matriculas.crear',
                'matriculas.editar',
                'matriculas.eliminar',
                'notas.ver',
                'horarios.ver',
                'reportes.ver',
                'reportes.exportar',
                'noticias.ver',
                'usuarios.ver',
                'perfil.ver',
            ],

            self::ROL_AUDITORIA => [
                'dashboard.ver',
                'estudiantes.ver',
                'profesores.ver',
                'cursos.ver',
                'matriculas.ver',
                'notas.ver',
                'horarios.ver',
                'reportes.ver',
                'reportes.exportar',
                'noticias.ver',
                'usuarios.ver',
                'auditoria.ver',
                'perfil.ver',
            ],
        ];
    }

    /** Comprueba un permiso concreto del rol actual. */
    public static function puede(string $permiso): bool
    {
        $permisos = self::permisos_por_rol()[self::rol()] ?? [];

        return in_array('*', $permisos, true) || in_array($permiso, $permisos, true);
    }

    /** Ruta de inicio según el rol. */
    public static function url_inicio(): string
    {
        return match (self::rol()) {
            self::ROL_PROFESOR => base_url('profesor.php'),
            default            => base_url('dashboard.php'),
        };
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
            "SELECT u.id_usuario, u.nombres, u.apellidos, u.email, u.password_hash, u.id_rol, u.activo, u.avatar
               FROM usuarios u
               INNER JOIN roles r ON r.id_rol = u.id_rol AND r.activo = 1
              WHERE (u.email = :login OR CONCAT(u.nombres, ' ', u.apellidos) = :login2)
                AND u.activo = 1
                AND u.id_rol IS NOT NULL
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
        $idRol = isset($usuario['id_rol']) && $usuario['id_rol'] !== null
            ? (int) $usuario['id_rol']
            : 0;

        /* Sin rol de sistema no hay acceso: la cuenta no pertenece a este panel. */
        if ($idRol <= 0 || !isset(self::roles()[$idRol])) {
            return;
        }

        self::asegurar_sesion();
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nombres'    => $usuario['nombres'],
            'apellidos'  => $usuario['apellidos'],
            'email'      => $usuario['email'],
            'id_rol'     => $idRol,
            'avatar'     => $usuario['avatar'] ?? null,
            'nombre'     => $usuario['nombres'] . ' ' . $usuario['apellidos'],
            'rol'        => self::nombre_rol($idRol),
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

    public static function es_profesor(): bool
    {
        return self::rol() === self::ROL_PROFESOR;
    }

    public static function es_secretaria(): bool
    {
        return self::rol() === self::ROL_SECRETARIA;
    }

    public static function es_auditoria(): bool
    {
        return self::rol() === self::ROL_AUDITORIA;
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
            "SELECT u.id_usuario, u.nombres, u.apellidos, u.email,
                    u.id_rol, u.activo, u.avatar, r.activo AS rol_activo
               FROM usuarios u
               LEFT JOIN roles r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = :id",
            [':id' => self::id()]
        );
        if (!$usuario
            || !(int) $usuario['activo']
            || $usuario['id_rol'] === null
            || !(int) $usuario['rol_activo']
            || !isset(self::roles()[(int) $usuario['id_rol']])
        ) {
            self::cerrar();
            flash_set('warning', 'Tu cuenta no tiene acceso a este sistema.');
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

    /** Guarda por permiso (preferida frente a comprobar roles sueltos). */
    public static function requerir_permiso(string $permiso): void
    {
        self::requerir_login();

        if (!self::puede($permiso)) {
            http_response_code(403);
            require_once APP_ROOT . '/public/403.php';
            exit;
        }
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