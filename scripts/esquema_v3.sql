-- ============================================================
-- ESQUEMA V3 - Escuela de Formacion Artistica
-- Base nueva: escuela_artistica_v3
--
-- Basado en el esquema propuesto por el usuario, adaptado para:
--   1. No tocar la base actual (escuela_artistica).
--   2. Conservar los 120 alumnos y todos los datos existentes.
--   3. Permitir alumnos sin DNI (2 casos reales).
--   4. Mantener las tablas que el sistema ya usa:
--      auditoria, login_intentos, notificaciones y horarios.
--
-- Este script SI se puede ejecutar: solo borra escuela_artistica_v3.
-- ============================================================

DROP DATABASE IF EXISTS escuela_artistica_v3;

CREATE DATABASE escuela_artistica_v3
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE escuela_artistica_v3;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================
-- 1. ROLES
-- =========================
CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los IDs coinciden con los roles de la base actual:
-- 1 Administrador, 2 Profesor/Docente, 4 Secretaria, 5 Auditoria.
-- El rol 3 (estudiante) solo lo usan las cuentas historicas inactivas.
INSERT INTO roles (id_rol, nombre, descripcion, activo) VALUES
(1, 'administrador', 'Acceso total', 1),
(2, 'docente',       'Registro de notas', 1),
(3, 'estudiante',    'Consulta (cuentas historicas inactivas)', 1),
(4, 'secretaria',    'Matriculas', 1),
(5, 'auditoria',     'Revision de bitacora y auditoria', 1);

-- =========================
-- 2. USUARIOS
-- =========================
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_rol INT NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME DEFAULT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuarios_rol (id_rol),
    KEY idx_usuarios_rol_activo (id_rol, activo),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 3. ESPECIALIDADES
-- =========================
CREATE TABLE especialidades (
    id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 4. ESTUDIANTES
-- =========================
-- id_usuario es obligatorio porque los 120 alumnos ya tienen cuenta.
-- dni es NULL-able: 2 alumnos no tienen DNI y no se deben inventar.
-- codigo guarda la matricula actual (ej. EST-2026-001).
CREATE TABLE estudiantes (
    id_estudiante INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    id_especialidad INT NOT NULL,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    dni VARCHAR(20) DEFAULT NULL UNIQUE,
    fecha_nacimiento DATE DEFAULT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    foto_url VARCHAR(255) DEFAULT NULL,
    anio_ingreso YEAR(4) DEFAULT NULL,
    estado ENUM('activo','egresado','retirado') NOT NULL DEFAULT 'activo',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_est_especialidad (id_especialidad),
    KEY idx_est_estado (estado),
    CONSTRAINT fk_est_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_est_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 5. DOCENTES
-- =========================
-- dni queda NULL-able porque la base actual no lo tiene.
CREATE TABLE docentes (
    id_docente INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    id_especialidad INT NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    dni VARCHAR(20) DEFAULT NULL UNIQUE,
    telefono VARCHAR(30) DEFAULT NULL,
    grado_academico VARCHAR(100) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc_especialidad (id_especialidad),
    CONSTRAINT fk_doc_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_doc_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 6. PROGRAMAS
-- =========================
CREATE TABLE programas (
    id_programa INT AUTO_INCREMENT PRIMARY KEY,
    id_especialidad INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    duracion_anios TINYINT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE (id_especialidad, nombre),
    CONSTRAINT fk_prog_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 7. CURSOS
-- =========================
-- Se conservan codigo, area y descripcion de la base actual.
CREATE TABLE cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    id_programa INT NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    area VARCHAR(50) DEFAULT NULL,
    tipo ENUM('teorico','practico') NOT NULL DEFAULT 'teorico',
    creditos TINYINT DEFAULT NULL,
    horas_semana TINYINT DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cursos_programa (id_programa),
    CONSTRAINT fk_cursos_programa FOREIGN KEY (id_programa) REFERENCES programas(id_programa)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 8. PERIODOS ACADEMICOS
-- =========================
CREATE TABLE periodos_academicos (
    id_periodo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 9. MATRICULAS
-- =========================
-- periodo conserva la etiqueta original (ej. 2026-1).
-- El curso concreto se guarda en matricula_cursos.
CREATE TABLE matriculas (
    id_matricula INT AUTO_INCREMENT PRIMARY KEY,
    id_estudiante INT NOT NULL,
    id_periodo INT NOT NULL,
    id_programa INT NOT NULL,
    periodo VARCHAR(20) DEFAULT NULL,
    estado ENUM('activo','retirado','reservado') NOT NULL DEFAULT 'activo',
    fecha_matricula DATETIME DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT DEFAULT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_estudiante, id_periodo),
    KEY idx_mat_periodo (id_periodo),
    KEY idx_mat_programa (id_programa),
    CONSTRAINT fk_mat_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mat_periodo FOREIGN KEY (id_periodo) REFERENCES periodos_academicos(id_periodo)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mat_programa FOREIGN KEY (id_programa) REFERENCES programas(id_programa)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 10. MATRICULA CURSOS
-- =========================
CREATE TABLE matricula_cursos (
    id_matricula_curso INT AUTO_INCREMENT PRIMARY KEY,
    id_matricula INT NOT NULL,
    id_curso INT NOT NULL,
    UNIQUE (id_matricula, id_curso),
    CONSTRAINT fk_mc_matricula FOREIGN KEY (id_matricula) REFERENCES matriculas(id_matricula)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_mc_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 11. ASIGNACION DE DOCENTES
-- =========================
-- Reemplaza a curso_docentes de la base actual.
CREATE TABLE asignacion_docentes (
    id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    id_curso INT NOT NULL,
    id_periodo INT NOT NULL,
    UNIQUE (id_docente, id_curso, id_periodo),
    CONSTRAINT fk_ad_docente FOREIGN KEY (id_docente) REFERENCES docentes(id_docente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ad_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ad_periodo FOREIGN KEY (id_periodo) REFERENCES periodos_academicos(id_periodo)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 12. EVALUACIONES
-- =========================
CREATE TABLE evaluaciones (
    id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    id_periodo INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('practica','teorica','desempeno_artistico') NOT NULL DEFAULT 'teorica',
    peso_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 100,
    fecha DATE DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    KEY idx_eval_curso (id_curso),
    KEY idx_eval_periodo (id_periodo),
    CONSTRAINT fk_eval_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_eval_periodo FOREIGN KEY (id_periodo) REFERENCES periodos_academicos(id_periodo)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 13. NOTAS
-- =========================
CREATE TABLE notas (
    id_nota INT AUTO_INCREMENT PRIMARY KEY,
    id_evaluacion INT NOT NULL,
    id_estudiante INT NOT NULL,
    calificacion DECIMAL(5,2) DEFAULT NULL,
    observacion TEXT DEFAULT NULL,
    periodo VARCHAR(20) DEFAULT NULL,
    registrado_por INT DEFAULT NULL,
    registrado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_evaluacion, id_estudiante),
    KEY idx_notas_estudiante (id_estudiante),
    CONSTRAINT fk_notas_evaluacion FOREIGN KEY (id_evaluacion) REFERENCES evaluaciones(id_evaluacion)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_notas_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_notas_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 14. NOTICIAS (CMS)
-- =========================
CREATE TABLE noticias (
    id_noticia INT AUTO_INCREMENT PRIMARY KEY,
    id_autor INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    contenido TEXT DEFAULT NULL,
    imagen_url VARCHAR(255) DEFAULT NULL,
    publicado TINYINT(1) NOT NULL DEFAULT 0,
    destacado TINYINT(1) NOT NULL DEFAULT 0,
    publicado_en DATETIME DEFAULT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_not_pub (publicado, publicado_en),
    CONSTRAINT fk_not_autor FOREIGN KEY (id_autor) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 15. INFORMACION INSTITUCIONAL
-- =========================
CREATE TABLE informacion_institucional (
    id_info INT AUTO_INCREMENT PRIMARY KEY,
    seccion VARCHAR(100) NOT NULL UNIQUE,
    contenido TEXT DEFAULT NULL,
    actualizado_por INT DEFAULT NULL,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_info_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 16. AUDITORIA (tabla del sistema actual)
-- =========================
CREATE TABLE auditoria (
    id_auditoria BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT DEFAULT NULL,
    accion VARCHAR(40) NOT NULL,
    entidad VARCHAR(40) NOT NULL,
    entidad_id INT DEFAULT NULL,
    detalle VARCHAR(255) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_aud_usuario (id_usuario),
    KEY idx_aud_fecha (creado_en),
    KEY idx_aud_entidad (entidad, entidad_id),
    CONSTRAINT fk_aud_usr FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 17. LOGIN INTENTOS (tabla del sistema actual)
-- =========================
CREATE TABLE login_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identificador VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    exito TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_li_id (identificador, ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 18. NOTIFICACIONES (tabla del sistema actual)
-- =========================
CREATE TABLE notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    mensaje VARCHAR(255) DEFAULT NULL,
    tipo ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
    url VARCHAR(255) DEFAULT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_usr (id_usuario, leida),
    CONSTRAINT fk_notif_usr FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- 19. HORARIOS (tabla del sistema actual)
-- =========================
CREATE TABLE horarios (
    id_horario INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT NOT NULL,
    id_docente INT NOT NULL,
    dia_semana VARCHAR(20) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    aula VARCHAR(50) DEFAULT NULL,
    id_salon VARCHAR(50) DEFAULT NULL,
    id_periodo INT DEFAULT NULL,
    periodo VARCHAR(20) DEFAULT NULL,
    KEY idx_hor_curso (id_curso),
    KEY idx_hor_docente (id_docente),
    CONSTRAINT fk_hor_curso FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hor_docente FOREIGN KEY (id_docente) REFERENCES docentes(id_docente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hor_periodo FOREIGN KEY (id_periodo) REFERENCES periodos_academicos(id_periodo)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CARGA DE DATOS DESDE LA BASE ACTUAL
-- ============================================================

-- Especialidades reales (6), copiadas tal cual para conservar tildes y nombres.
INSERT INTO especialidades (id_especialidad, nombre)
SELECT id_especialidad, nombre
FROM escuela_artistica.especialidades;

UPDATE especialidades SET descripcion = 'Dibujo, pintura y artes visuales' WHERE id_especialidad = 1;
UPDATE especialidades SET descripcion = 'Modelado y técnicas de escultura' WHERE id_especialidad = 2;
UPDATE especialidades SET descripcion = 'Formación musical' WHERE id_especialidad = 3;
UPDATE especialidades SET descripcion = 'Formación en danza' WHERE id_especialidad = 4;
UPDATE especialidades SET descripcion = 'Interpretación dramática' WHERE id_especialidad = 5;
UPDATE especialidades SET descripcion = 'Formación teórica musical' WHERE id_especialidad = 21;

-- Un programa por especialidad, conservando el nombre original.
INSERT INTO programas (id_programa, id_especialidad, nombre, descripcion, duracion_anios)
SELECT CASE es.id_especialidad
           WHEN 1 THEN 1
           WHEN 2 THEN 2
           WHEN 3 THEN 3
           WHEN 4 THEN 4
           WHEN 5 THEN 5
           WHEN 21 THEN 6
       END,
       es.id_especialidad,
       CONCAT('Programa de ', es.nombre),
       CONCAT('Programa de ', LOWER(es.nombre)),
       5
FROM escuela_artistica.especialidades es;

-- Usuarios: las 120 cuentas historicas de alumno quedan con rol 3
-- y conservan activo = 0 (no pueden iniciar sesion).
INSERT INTO usuarios (id_usuario, id_rol, nombres, apellidos, email, password_hash,
                      telefono, avatar, activo, ultimo_acceso, creado_en)
SELECT u.id_usuario,
       COALESCE(u.id_rol, 3),
       u.nombres,
       u.apellidos,
       u.email,
       u.password_hash,
       u.telefono,
       u.avatar,
       u.activo,
       u.ultimo_acceso,
       u.creado_en
FROM escuela_artistica.usuarios u;

-- Estudiantes: se conservan los 120, incluso los 2 sin DNI.
INSERT INTO estudiantes (id_estudiante, id_usuario, id_especialidad, codigo, dni,
                         telefono, anio_ingreso, estado, creado_en, actualizado_en)
SELECT e.id_estudiante,
       e.id_usuario,
       e.id_especialidad,
       e.matricula,
       NULLIF(TRIM(e.dni), ''),
       e.telefono,
       e.anio_ingreso,
       e.estado,
       e.creado_en,
       e.actualizado_en
FROM escuela_artistica.estudiantes e;

-- Docentes: se conservan los 6 profesores.
-- La especialidad de Marta estaba como texto generico; se asigna Pintura
-- porque dicta Fundamentos del Dibujo e Historia del Arte.
INSERT INTO docentes (id_docente, id_usuario, id_especialidad, codigo, dni,
                      telefono, grado_academico, activo, creado_en)
SELECT p.id_profesor,
       p.id_usuario,
       CASE p.id_profesor
           WHEN 1 THEN 1   -- Marta Gomez -> Pintura
           WHEN 2 THEN 3   -- Carlos Lopez -> Musica
           WHEN 3 THEN 1   -- Laura Martinez -> Pintura
           WHEN 4 THEN 2   -- Javier Torres -> Escultura
           WHEN 5 THEN 4   -- Elena Ruiz -> Danza
           WHEN 6 THEN 5   -- Pedro Sanchez -> Teatro
           ELSE 1
       END,
       CONCAT('DOC', LPAD(p.id_profesor, 3, '0')),
       NULL,
       p.telefono,
       p.titulo,
       p.activo,
       p.creado_en
FROM escuela_artistica.profesores p;

-- Periodo: la base actual solo tiene la etiqueta 2026-1, sin fechas.
-- Se usan fechas provisionales del primer semestre.
INSERT INTO periodos_academicos (id_periodo, nombre, fecha_inicio, fecha_fin, activo) VALUES
(1, '2026-1', '2026-03-01', '2026-07-31', 1);

-- Cursos: se mapea cada area a su programa.
INSERT INTO cursos (id_curso, id_programa, codigo, nombre, area, tipo,
                    creditos, descripcion, activo, creado_en)
SELECT c.id_curso,
       CASE c.id_curso
           WHEN 1 THEN 1   -- Fundamentos del Dibujo -> Pintura
           WHEN 2 THEN 1   -- Historia del Arte -> Pintura
           WHEN 3 THEN 3   -- Audicion y Solfeo -> Musica
           WHEN 4 THEN 4   -- Fundamentos de la Danza -> Danza
           WHEN 5 THEN 6   -- Historia de la Musica -> Teoria Musical
           WHEN 6 THEN 2   -- Tecnicas de Escultura -> Escultura
           WHEN 7 THEN 5   -- Interpretacion Dramatica -> Teatro
           ELSE 1
       END,
       c.codigo,
       c.nombre,
       c.area,
       CASE WHEN c.area = 'Teoría' THEN 'teorico' ELSE 'practico' END,
       c.creditos,
       c.descripcion,
       c.activo,
       c.creado_en
FROM escuela_artistica.cursos c;

-- Matriculas: 1 registro actual. El estado "matriculado" pasa a "activo"
-- y el curso original se conserva en matricula_cursos.
INSERT INTO matriculas (id_matricula, id_estudiante, id_periodo, id_programa,
                        periodo, estado, fecha_matricula, observaciones, creado_en)
SELECT m.id_matricula,
       m.id_estudiante,
       1,
       CASE m.id_curso
           WHEN 1 THEN 1 WHEN 2 THEN 1 WHEN 3 THEN 3
           WHEN 4 THEN 4 WHEN 5 THEN 6 WHEN 6 THEN 2
           WHEN 7 THEN 5 ELSE 1
       END,
       m.periodo,
       CASE m.estado
           WHEN 'matriculado' THEN 'activo'
           WHEN 'retirado' THEN 'retirado'
           WHEN 'reservado' THEN 'reservado'
           ELSE 'activo'
       END,
       m.fecha_matricula,
       CONCAT('Estado original: ', m.estado, '. Curso original: ', c.nombre),
       m.fecha_matricula
FROM escuela_artistica.matriculas m
JOIN escuela_artistica.cursos c ON c.id_curso = m.id_curso;

INSERT INTO matricula_cursos (id_matricula, id_curso)
SELECT m.id_matricula, m.id_curso
FROM escuela_artistica.matriculas m;

-- Asignacion de docentes (equivale a curso_docentes).
INSERT INTO asignacion_docentes (id_docente, id_curso, id_periodo)
SELECT cd.id_profesor, cd.id_curso, 1
FROM escuela_artistica.curso_docentes cd;

-- Evaluaciones: se conserva el peso y se infiere el tipo por area.
INSERT INTO evaluaciones (id_evaluacion, id_curso, id_periodo, nombre, tipo,
                          peso_porcentaje, fecha)
SELECT ev.id_evaluacion,
       ev.id_curso,
       1,
       ev.nombre,
       CASE WHEN c.area = 'Teoría' THEN 'teorica' ELSE 'practica' END,
       ev.peso,
       ev.fecha
FROM escuela_artistica.evaluaciones ev
JOIN escuela_artistica.cursos c ON c.id_curso = ev.id_curso;

-- Notas: valor pasa a calificacion; se conserva periodo y quien registro.
INSERT INTO notas (id_nota, id_evaluacion, id_estudiante, calificacion,
                   periodo, registrado_por, registrado_en)
SELECT n.id_nota,
       n.id_evaluacion,
       n.id_estudiante,
       n.valor,
       n.periodo,
       n.registrado_por,
       n.creado_en
FROM escuela_artistica.notas n;

-- Noticias: la base actual no tiene registros.
-- Si mas adelante se cargan, se migran con esta consulta:
-- INSERT INTO noticias (id_noticia, id_autor, titulo, contenido, imagen_url,
--                       publicado, destacado, publicado_en, creado_en)
-- SELECT n.id_noticia, n.autor_id, n.titulo, n.contenido, n.imagen,
--        n.publicado, n.destacado, n.publicado_en, n.publicado_en
-- FROM escuela_artistica.noticias n;

-- Informacion institucional base
INSERT INTO informacion_institucional (seccion, contenido) VALUES
('mision', 'Formar artistas profesionales'),
('vision', 'Ser líder en educación artística');

-- Auditoria (226 registros)
INSERT INTO auditoria (id_auditoria, id_usuario, accion, entidad, entidad_id,
                       detalle, ip, creado_en)
SELECT a.id_auditoria, a.id_usuario, a.accion, a.entidad, a.entidad_id,
       a.detalle, a.ip, a.creado_en
FROM escuela_artistica.auditoria a;

-- Intentos de login (2 registros)
INSERT INTO login_intentos (id, identificador, ip, exito, creado_en)
SELECT l.id, l.identificador, l.ip, l.exito, l.creado_en
FROM escuela_artistica.login_intentos l;

-- Notificaciones (0 registros actualmente)
INSERT INTO notificaciones (id_notificacion, id_usuario, titulo, mensaje, tipo,
                            url, leida, creado_en)
SELECT n.id_notificacion, n.id_usuario, n.titulo, n.mensaje, n.tipo,
       n.url, n.leida, n.creado_en
FROM escuela_artistica.notificaciones n;

-- Horarios (7 registros)
INSERT INTO horarios (id_horario, id_curso, id_docente, dia_semana, hora_inicio,
                      hora_fin, aula, id_salon, id_periodo, periodo)
SELECT h.id_horario, h.id_curso, h.id_profesor, h.dia_semana, h.hora_inicio,
       h.hora_fin, h.aula, h.id_salon, 1, h.periodo
FROM escuela_artistica.horarios h;

-- ============================================================
-- VISTAS
-- ============================================================

-- Datos legibles del alumno: une cuenta, ficha y especialidad.
CREATE VIEW v_alumnos AS
SELECT e.id_estudiante,
       e.codigo,
       e.dni,
       u.nombres,
       u.apellidos,
       CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
       u.email,
       u.activo AS cuenta_activa,
       es.nombre AS especialidad,
       e.telefono,
       e.anio_ingreso,
       e.estado,
       e.creado_en
FROM estudiantes e
JOIN usuarios u ON u.id_usuario = e.id_usuario
JOIN especialidades es ON es.id_especialidad = e.id_especialidad;

-- Promedio ponderado por curso y periodo (adaptada al esquema v3).
CREATE VIEW v_promedio_por_curso AS
SELECT e.id_estudiante,
       CONCAT(u.nombres, ' ', u.apellidos) AS estudiante,
       c.nombre AS curso,
       pa.nombre AS periodo,
       ROUND(SUM(n.calificacion * ev.peso_porcentaje) / SUM(ev.peso_porcentaje), 2) AS promedio
FROM notas n
JOIN evaluaciones ev ON ev.id_evaluacion = n.id_evaluacion
JOIN cursos c ON c.id_curso = ev.id_curso
JOIN periodos_academicos pa ON pa.id_periodo = ev.id_periodo
JOIN estudiantes e ON e.id_estudiante = n.id_estudiante
JOIN usuarios u ON u.id_usuario = e.id_usuario
GROUP BY e.id_estudiante, u.nombres, u.apellidos,
         c.id_curso, c.nombre, pa.id_periodo, pa.nombre;

-- ============================================================
-- VERIFICACION
-- ============================================================
SELECT 'roles' AS tabla, COUNT(*) AS filas FROM roles
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'especialidades', COUNT(*) FROM especialidades
UNION ALL SELECT 'programas', COUNT(*) FROM programas
UNION ALL SELECT 'estudiantes', COUNT(*) FROM estudiantes
UNION ALL SELECT 'docentes', COUNT(*) FROM docentes
UNION ALL SELECT 'cursos', COUNT(*) FROM cursos
UNION ALL SELECT 'periodos', COUNT(*) FROM periodos_academicos
UNION ALL SELECT 'matriculas', COUNT(*) FROM matriculas
UNION ALL SELECT 'matricula_cursos', COUNT(*) FROM matricula_cursos
UNION ALL SELECT 'asignacion_docentes', COUNT(*) FROM asignacion_docentes
UNION ALL SELECT 'evaluaciones', COUNT(*) FROM evaluaciones
UNION ALL SELECT 'notas', COUNT(*) FROM notas
UNION ALL SELECT 'horarios', COUNT(*) FROM horarios
UNION ALL SELECT 'auditoria', COUNT(*) FROM auditoria
UNION ALL SELECT 'login_intentos', COUNT(*) FROM login_intentos
UNION ALL SELECT 'notificaciones', COUNT(*) FROM notificaciones;

SELECT COUNT(*) AS alumnos,
       SUM(dni IS NULL) AS alumnos_sin_dni,
       SUM(cuenta_activa = 1) AS alumnos_con_cuenta_activa
FROM v_alumnos;
