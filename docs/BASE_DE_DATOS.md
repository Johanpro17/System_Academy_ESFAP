# Base de datos

## Dónde está la base de datos

La base **no vive dentro del proyecto**. La guarda MariaDB en su propia carpeta de datos:

```text
C:\xampp\mysql\data\escuela_artistica\
```

Allí hay un archivo `.frm` (estructura) y un `.ibd` (datos) por cada tabla. Esa carpeta
**no se debe editar ni mover a mano**: cualquier cambio se hace desde phpMyAdmin o con SQL.

El proyecto solo guarda copias de seguridad portables:

```text
backups/    archivos .sql generados con scripts/respaldo_bd.php
Database/   copia portable para revisión (excluida de Git)
logs/       registro de errores de la aplicación
```

No hay archivos de la base sueltos en el proyecto. Esa separación es intencional.

### Copias dentro de `Database/`

| Archivo | Contenido |
| --- | --- |
| `Database/escuela_artistica.sql` | Copia de la base actual (15 tablas). |
| `Database/escuela_artistica_v3.sql` | Copia de la base nueva propuesta (19 tablas + 2 vistas). |

Son copias de lectura para revisar el esquema y los datos desde VS Code. No son la base
viva y no deben importarse sobre `escuela_artistica` sin querer reemplazarla. La carpeta
se bloquea por HTTP y los `.sql` están excluidos de Git porque contienen datos personales.

## Cómo se conecta el sistema

```text
Navegador
  -> public/dashboard.php  (o cualquier vista)
     -> core/bootstrap.php
        -> config/config.php      datos de conexión
        -> config/database.php    conexión PDO y función db()
           -> db()->fila(...) / db()->todas(...) / db()->query(...)
              -> MariaDB: escuela_artistica
```

### Archivos que intervienen

| Archivo | Función |
| --- | --- |
| `config/config.php` | Define host, nombre de la base, usuario, contraseña y `utf8mb4`. |
| `config/database.php` | Crea la conexión PDO y expone el helper `db()`. |
| `core/bootstrap.php` | Carga ambos archivos en todas las páginas. |
| `public/health.php` | Comprueba si la aplicación y la base responden. |

### Valores actuales de conexión

En `config/config.php`:

| Clave | Valor local |
| --- | --- |
| `db_host` | `localhost` |
| `db_name` | `escuela_artistica` |
| `db_user` | `root` |
| `db_pass` | vacía en XAMPP |
| `db_charset` | `utf8mb4` |

Se pueden sobrescribir con las variables de entorno `DB_HOST`, `DB_NAME`, `DB_USER` y
`DB_PASS`, sin editar el archivo. En producción conviene usar un usuario propio con
contraseña, no `root`.

## Comprobar que todo está bien

1. Abrir en el navegador:

   ```text
   http://localhost/Johan/sistema-academico/public/health.php
   ```

   Debe responder `"db":"up"`.

2. O consultar directamente:

   ```bat
   C:\xampp\mysql\bin\mysql.exe -u root -e "USE escuela_artistica; SHOW TABLES;"
   ```

## Organización de las tablas

| Grupo | Tablas |
| --- | --- |
| Identidad | `roles`, `usuarios`, `profesores`, `estudiantes`, `especialidades` |
| Académico | `cursos`, `curso_docentes`, `matriculas`, `evaluaciones`, `notas`, `horarios` |
| Comunicación | `noticias`, `notificaciones` |
| Control | `auditoria`, `login_intentos` |
| Vista | `v_promedio_por_curso` |

## Base nueva `escuela_artistica_v3`

Existe una segunda base, separada de la actual, con el esquema propuesto y los datos
migrados:

- Se crea con `scripts/esquema_v3.sql`.
- Contiene 19 tablas y 2 vistas (`v_alumnos`, `v_promedio_por_curso`).
- Tiene los 120 alumnos, 6 especialidades, 7 cursos, 6 docentes, matrículas, notas,
  horarios y auditoría.
- No reemplaza a `escuela_artistica`: el sistema sigue conectado a la base actual.

Los detalles, equivalencias de tablas y pasos para migrar el código están en
[`docs/ESQUEMA_V3.md`](ESQUEMA_V3.md).

## Respaldos

```bat
php scripts\respaldo_bd.php
scripts\respaldo_bd.bat
```

Los `.sql` se guardan en `backups/`. Para restaurar, se importa el archivo desde
phpMyAdmin o con MySQL. El respaldo más reciente se conserva junto a los anteriores.
