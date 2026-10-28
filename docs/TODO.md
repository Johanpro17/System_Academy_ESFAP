# Pendientes y siguientes pasos

## Plataforma de alumnos (fuera de este sistema)

- Crear el panel independiente para alumnos con correo y contraseña propios.
- Definir cómo se vinculará cada cuenta nueva con su ficha en `estudiantes` (por DNI o matrícula).
- Las cuentas históricas de alumno ya están desactivadas y sin rol; no es necesario borrarlas.

## Operación académica

- Importación masiva de fichas de alumno (CSV o base externa) sin crear cuentas de acceso.
- Validar el formato de DNI por país antes de endurecer la regla actual.
- Programar `scripts/respaldo_bd.php` con el Programador de tareas de Windows y copiar los respaldos fuera del servidor.
- Revisar periódicamente el crecimiento de `auditoria`, `login_intentos` y `notificaciones`.

## Infraestructura

- Publicar bajo HTTPS y ajustar `config/config.php` para producción (`debug = false`).
- Definir política de contraseñas y rotación de las cuentas institucionales.
- Documentar el procedimiento de restauración de un respaldo.

## Completado

- Cuatro roles de sistema y retiro del rol Estudiante.
- Ficha de alumno independiente de la cuenta de usuario.
- Esquema normalizado en `utf8mb4`, con índices y claves foráneas.
- Limpieza del árbol: sin `legado/`, sin scripts de una sola vez y con las imágenes en `public/assets/img/`.
