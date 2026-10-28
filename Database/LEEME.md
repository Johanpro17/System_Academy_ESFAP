# Carpeta Database

Esta carpeta contiene **copias portables de la base de datos** para revisarlas o
importarlas. No es la base viva del sistema.

## Archivos

```text
Database/escuela_artistica.sql       base actual (15 tablas)
Database/escuela_artistica_v3.sql    base nueva propuesta (19 tablas + 2 vistas)
```

### `escuela_artistica.sql`

- Estructura de las 15 tablas actuales.
- Datos actuales: 120 fichas de alumno, usuarios, cursos, matrículas, notas, etc.
- La vista `v_promedio_por_curso`.
- Codificación `utf8mb4`.

### `escuela_artistica_v3.sql`

- Esquema propuesto, adaptado para conservar los datos.
- 120 alumnos, 6 especialidades, 7 cursos, 6 docentes y el resto de datos actuales.
- Tablas nuevas: `programas`, `periodos_academicos`, `matricula_cursos`,
  `asignacion_docentes`, `informacion_institucional`.
- Tablas del sistema que se conservan: `auditoria`, `login_intentos`,
  `notificaciones`, `horarios`.
- Vistas `v_alumnos` y `v_promedio_por_curso`.

El script que genera esta base está en `scripts/esquema_v3.sql` y sí se versiona en
Git. La explicación completa está en `docs/ESQUEMA_V3.md`.

## Importante

La base real sigue viviendo en MariaDB:

```text
C:\xampp\mysql\data\escuela_artistica\
```

No muevas ni edites esa carpeta con MariaDB encendido: se puede dañar. Estos `.sql`
son la forma segura de tener copias dentro del proyecto.

## Cómo revisarlas

Abre cualquiera de los dos archivos en VS Code. Verás:

1. La estructura de cada tabla (`CREATE TABLE`).
2. Los índices y claves foráneas.
3. Los datos (`INSERT INTO`).
4. Las vistas al final.

## Cómo importarlas (solo en una base de prueba)

1. Abre phpMyAdmin.
2. Crea una base llamada, por ejemplo, `escuela_artistica_prueba`.
3. Selecciónala y entra en **Importar**.
4. Elige el archivo.
5. Ejecuta la importación.

`escuela_artistica.sql` contiene `DROP TABLE`, y `escuela_artistica_v3.sql` contiene
`DROP DATABASE IF EXISTS escuela_artistica_v3`. **No los importes sobre la base real**
si no quieres reemplazar sus datos.

## Privacidad

Estos `.sql` contienen datos personales y contraseñas cifradas. Por eso están excluidos
de Git con `.gitignore` y no se suben a GitHub. La carpeta también bloquea el acceso por
HTTP con su propio `.htaccess`.
