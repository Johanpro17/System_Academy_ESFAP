# Sistema Académico ESFAP

Sistema de gestión académica para la Escuela de Formación Artística ESFAP: estudiantes, matrículas, docentes, cursos, notas, horarios, reportes, noticias y auditoría.

- PHP 8.x + PDO (sin frameworks)
- MariaDB / MySQL
- JavaScript nativo y CSS con variables, modo claro/oscuro
- Diseño institucional morado + dorado

## Requisitos

- PHP 8.1 o superior con extensiones `pdo_mysql` y `mbstring`
- MariaDB 10.4+ o MySQL 8+
- Apache con `mod_rewrite` (XAMPP funciona)

## Instalación rápida

1. Copia el proyecto en `C:\xampp\htdocs\Johan\sistema-academico\`.
2. Crea la base de datos `escuela_artistica`.
3. Abre `http://localhost/Johan/sistema-academico/public/instalar.php` y completa el asistente.
4. Al finalizar, el instalador se bloquea con `config/.instalado`.
5. Ingresa con `admin@sistema.edu` / `admin123` y cambia la contraseña.

El instalador crea también las cuentas institucionales:

| Rol | Correo | Contraseña inicial |
| --- | --- | --- |
| Secretaría | `secretaria@esfap.edu` | `Secretaria2026` |
| Auditoría | `auditoria@esfap.edu` | `Auditoria2026` |

Cámbialas antes de publicar el sistema.

### Instalaciones existentes

Si la base de datos ya existe y no tiene los roles de secretaría y auditoría:

```bat
C:\xampp\php\php.exe scripts\sembrar_roles_cuentas.php
```

El script es idempotente y no modifica estudiantes, matrículas, cursos ni notas.

## Roles y permisos

| Rol | Alcance |
| --- | --- |
| Administrador | Acceso total; único rol que gestiona usuarios, profesores, cursos y noticias |
| Docente | Panel propio; solo sus cursos, notas, reportes, constancias y horarios |
| Estudiante | Solo su portal de calificaciones y su perfil |
| Secretaría | Gestiona estudiantes, matrículas y constancias; consulta el resto (notas en solo lectura) |
| Auditoría | Consulta toda la información y la bitácora, sin escrituras |

Los permisos se validan por acción con `Auth::puede()` y `Auth::requerir_permiso()`, tanto al mostrar la interfaz como en cada POST.

## Estructura

```
core/       Bootstrap, Auth, Database, errores, helpers, paginador
config/     Configuración y conexión a la base de datos
public/     Punto de entrada (portada, login, dashboard, assets)
views/      Vistas por módulo
layout/     Header y footer compartidos
scripts/    Utilidades (respaldos, siembra de roles, migraciones)
docs/       Documentación detallada
legado/     Archivos antiguos no utilizados (protegido)
```

## Documentación

- [`docs/README_PROFESIONAL.md`](docs/README_PROFESIONAL.md): guía funcional, portales por rol, backups y pruebas.
- [`docs/ESTADO.md`](docs/ESTADO.md): mejoras aplicadas y verificaciones.
- [`docs/TODO.md`](docs/TODO.md): historial de correcciones de seguridad.

## Seguridad

- Sesiones con `httponly`, `SameSite=Lax` y regeneración de ID.
- CSRF en formularios críticos y escape de salida con `e()`.
- `.htaccess` protege `config/`, `core/`, `scripts/`, `logs/`, `backups/` y `legado/`.
- Bloqueo temporal de login tras intentos fallidos y bitácora de auditoría.

## Monitoreo y respaldos

- `public/health.php` devuelve el estado de la aplicación y la base de datos (HTTP 503 si la BD está caída).
- `php scripts/backup_bd.php` genera un respaldo en `backups/`.
