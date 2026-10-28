# Sistema Académico ESFAP

Sistema de gestión académica para la Escuela de Formación Artística ESFAP: fichas de estudiantes, matrículas, profesores, cursos, notas, horarios, reportes, noticias y auditoría.

- PHP 8.1+ con PDO (sin frameworks)
- MariaDB 10.4+ / MySQL 8+
- JavaScript nativo y CSS con variables, modo claro/oscuro
- Diseño institucional morado + dorado

## Modelo de usuarios

El sistema tiene **cuatro roles**:

| Rol | Alcance |
| --- | --- |
| Administrador | Acceso total; gestiona usuarios, profesores, cursos, noticias y configuración |
| Profesor | Panel propio; solo sus cursos, notas, reportes, constancias y horarios |
| Secretaría | Gestiona estudiantes, matrículas y constancias; consulta el resto (notas en solo lectura) |
| Auditoría | Consulta toda la información y la bitácora, sin escrituras |

El **alumno no es un usuario de este sistema**: es una ficha académica (nombres, apellidos, DNI, matrícula, especialidad, contacto y estado). Las cuentas históricas de alumno se conservan desactivadas y sin rol, por lo que no pueden iniciar sesión aquí. Su acceso, si se habilita, pertenecerá a la plataforma de alumnos que se implementará por separado.

## Instalación

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

Para completar los cuatro roles y las cuentas institucionales sin tocar datos académicos:

```bat
C:\xampp\php\php.exe scripts\sembrar_roles_cuentas.php
```

Es idempotente. Desactiva las cuentas históricas de alumno y retira el rol Estudiante del sistema, conservando todas las fichas y sus vínculos.

## Estructura

```
core/       Bootstrap, Auth, Database, errores, helpers, paginador
config/     Configuración y conexión a la base de datos
public/     Punto de entrada (portada, login, dashboard, assets)
views/      Vistas por módulo
layout/     Header y footer compartidos
scripts/    Respaldos y siembra de roles
docs/       Documentación detallada
logs/       Registro de errores
backups/    Respaldos de la base de datos
```

## Base de datos

El esquema está preparado para crecer:

- `estudiantes` guarda los datos personales en la propia ficha; `id_usuario` es opcional y se pone en `NULL` si se elimina la cuenta.
- Índices únicos en `dni` y `matricula`, e índices de apoyo por nombre, estado, especialidad y correo.
- `profesores.id_usuario` es entero, único y con clave foránea a `usuarios`.
- Todas las tablas usan `utf8mb4_unicode_ci`.
- La vista `v_promedio_por_curso` se calcula desde la ficha, sin depender de una cuenta.

## Seguridad

- Sesiones con `httponly`, `SameSite=Lax` y regeneración de ID.
- CSRF en formularios críticos y escape de salida con `e()`.
- `.htaccess` protege `config/`, `core/`, `scripts/`, `logs/` y `backups/`.
- Bloqueo temporal de login tras intentos fallidos y bitácora de auditoría.
- El login exige rol activo; una cuenta sin rol no obtiene sesión.

## Monitoreo y respaldos

- `public/health.php` devuelve el estado de la aplicación y la base de datos (HTTP 503 si la BD está caída).
- `php scripts/respaldo_bd.php` genera un respaldo en `backups/`.
- En Windows: `scripts\respaldo_bd.bat`.

## Documentación

- [`docs/README_PROFESIONAL.md`](docs/README_PROFESIONAL.md): guía funcional, roles, backups y monitoreo.
- [`docs/ESTADO.md`](docs/ESTADO.md): arquitectura actual y verificaciones.
- [`docs/TODO.md`](docs/TODO.md): pendientes y siguientes pasos.
