# Sistema Académico - ESFAP

Sistema de gestión académica para la Escuela de Formación Artística ESFAP: fichas de estudiantes, matrículas, profesores, cursos, notas, notificaciones, reportes y auditoría.

## Tecnologías

- PHP 8.1+ con PDO (sin frameworks)
- MariaDB 10.4+ / MySQL 8+
- JavaScript nativo
- CSS con variables y modo oscuro

## Estructura

- `core/` - Bootstrap, Auth, Database, Errores, Helpers, Paginador, Notificacion
- `config/` - Configuración y conexión a la base de datos
- `public/` - Punto de entrada (`index`, `login`, `dashboard`, `profesor`, assets)
- `views/` - Vistas organizadas por módulo
- `layout/` - Header y footer compartidos
- `scripts/` - `respaldo_bd.php`, `respaldo_bd.bat`, `sembrar_roles_cuentas.php`
- `logs/` - Registro de errores (`app.log`, protegido por `.htaccess`)
- `backups/` - Respaldos de la base de datos (`.sql`, protegidos por `.htaccess`)
- `docs/` - Documentación

## Roles del sistema

| Rol | Alcance |
| --- | --- |
| Administrador | Acceso total; gestiona usuarios, profesores, cursos, noticias y configuración |
| Profesor | Panel propio; solo sus cursos, notas, reportes, constancias y horarios |
| Secretaría | Gestiona estudiantes, matrículas y constancias; consulta el resto (notas en solo lectura) |
| Auditoría | Consulta toda la información y la bitácora, sin escrituras |

El alumno **no tiene cuenta en este sistema**. Su ficha vive en `estudiantes` (nombres, apellidos, DNI, matrícula, especialidad, contacto, estado). Las cuentas históricas de alumno quedan desactivadas, sin rol y sin acceso; sus datos se conservan. El portal del alumno será una plataforma aparte.

## Seguridad

- Control de sesiones (`httponly`, `SameSite=Lax`, regeneración de ID)
- Cabeceras de seguridad (CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`)
- `.htaccess` protege `config/`, `core/`, `scripts/`, `logs/` y `backups/`
- Bloqueo del instalador (`config/.instalado`) una vez ejecutado
- Validación y escape (`e()`), placeholders PDO y CSRF en formularios críticos
- Manejador centralizado de errores (sin exponer rutas ni SQL)
- Permisos por acción (`Auth::puede()` / `Auth::requerir_permiso()`); cada escritura valida su permiso también en el POST
- El login exige un rol activo: una cuenta sin rol no obtiene sesión
- Las cuentas de profesor se crean o vinculan desde la ficha de profesor; el panel, listados, reportes, exportaciones, constancias y notas se limitan a sus cursos asignados

## Instalación rápida

1. Copiar el proyecto en `C:\xampp\htdocs\Johan\sistema-academico\`
2. Crear la base de datos `escuela_artistica` (MariaDB)
3. Acceder a `http://localhost/Johan/sistema-academico/public/instalar.php` y completar el asistente
4. Al finalizar queda bloqueado (`config/.instalado`)
5. Login inicial: `admin@sistema.edu` / `admin123`. Cambiar la contraseña antes de publicar.
6. El instalador crea además `secretaria@esfap.edu` / `Secretaria2026` y `auditoria@esfap.edu` / `Auditoria2026`. Cambiarlas antes de publicar.
7. En **Profesores** se crea cada perfil con su correo y contraseña de acceso.
8. En **Estudiantes** se registra solo la ficha académica; no se crea cuenta de acceso.

### Instalaciones existentes

Si la base ya existe y necesita los cuatro roles y las cuentas institucionales:

```bat
C:\xampp\php\php.exe scripts\sembrar_roles_cuentas.php
```

Es idempotente y no altera estudiantes, matrículas, cursos ni notas.

## Backups de la base de datos

- `php scripts/respaldo_bd.php` genera un respaldo en `backups/`.
- `php scripts/respaldo_bd.php --conservar=20` conserva las últimas N copias.
- En Windows: `scripts\respaldo_bd.bat`.

## Monitoreo

- `public/health.php` devuelve `{status, app, time, db}` en JSON. Responde HTTP 503 si la base de datos está caída.

## Notas

- Zona horaria `America/Bogota` (UTC-5).
- Modo oscuro disponible desde la barra superior.
- Diseño institucional morado + dorado, con modo claro/oscuro y componentes adaptables.
