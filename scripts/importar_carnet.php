<?php

/**
 * Importa la tabla `alumnos` del sistema carnet (carnet_esfap_v2) a este
 * sistema, creando el usuario de acceso y la ficha del estudiante.
 *
 *   php scripts/importar_carnet.php --simular
 *   php scripts/importar_carnet.php
 *   php scripts/importar_carnet.php --incluir-borrados
 *   php scripts/importar_carnet.php --sin-mapeo
 *
 * Clave natural: el código de matrícula del alumno. Un alumno ya importado
 * se actualiza, nunca se duplica, y nunca se le cambia el correo.
 *
 * $MAPEO_CARRERAS traduce las carreras del carnet a las especialidades que
 * ya existen en este sistema; con --sin-mapeo se crean con su nombre propio
 * (y al final se retiran las especialidades del carnet que quedaron vacías).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo disponible desde línea de comandos.\n");
}

const BASE             = 'carnet_esfap_v2';
const DESTINO          = 'escuela_artistica';
const TEMP_PASS        = 'Carnet2026x';
const ID_ROL_ESTUDIANTE = 3;

/** Carrera del carnet => especialidad de este sistema. */
const MAPEO_CARRERAS = [
    'Artistica Profesional'                  => 'Escultura',
    'Profesor de  Educacion Artistica-Musica' => 'Música',
    'Profesor de Educacion de Artes Visuales' => 'Pintura',
];

$simular     = in_array('--simular', $argv, true);
$incluirBorr = in_array('--incluir-borrados', $argv, true);
$sinMapeo    = in_array('--sin-mapeo', $argv, true);
$mapear      = $sinMapeo ? [] : MAPEO_CARRERAS;

$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$src = new PDO('mysql:host=localhost;charset=utf8mb4;dbname=' . BASE, 'root', '', $opt);
$dst = new PDO('mysql:host=localhost;charset=utf8mb4;dbname=' . DESTINO, 'root', '', $opt);

function normalizarEmail(?string $correo): string
{
    $correo = mb_strtolower(trim((string) $correo));
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return $correo;
}

function acortarEmail(string $correo, int $max = 100): string
{
    if (strlen($correo) <= $max) {
        return $correo;
    }
    $pos = strrpos($correo, '@');
    if ($pos === false) {
        return substr($correo, 0, $max);
    }
    $dominio = substr($correo, $pos);
    return substr($correo, 0, $max - strlen($dominio)) . $dominio;
}

/* ------------------------------------------------------------------ */

printf("Origen  : %s\n", BASE);
printf("Destino : %s\n", DESTINO);
printf("Modo    : %s\n", $simular ? 'SIMULACIÓN (no escribe)' : 'Escritura real');
printf("Borrados: %s\n\n", $incluirBorr ? 'se incluyen' : 'se omiten');

/* 1. Carreras -> especialidades ------------------------------------------------- */

$mapaEspecialidad = [];
$faltantes = [];
$carrerasOrigen = [];

foreach ($src->query('SELECT carrera_id, nombre FROM carreras ORDER BY carrera_id')->fetchAll() as $c) {
    $nombre = trim($c['nombre']);
    if ($nombre === '') {
        continue;
    }
    $carrerasOrigen[(int) $c['carrera_id']] = $nombre;

    $destino = $mapear[$nombre] ?? $nombre;

    $s = $dst->prepare('SELECT id_especialidad FROM especialidades WHERE nombre = ?');
    $s->execute([$destino]);
    $id = $s->fetchColumn();

    if ($id) {
        $mapaEspecialidad[(int) $c['carrera_id']] = (int) $id;
    } elseif (isset($mapear[$nombre])) {
        fwrite(STDERR, "El mapeo apunta a una especialidad inexistente: «{$destino}» (carrera «{$nombre}»)\n");
        exit(1);
    } else {
        $faltantes[(int) $c['carrera_id']] = $destino;
    }
}

if ($mapear) {
    echo "Mapeo de carreras del carnet a especialidades existentes:\n";
    foreach ($carrerasOrigen as $cid => $n) {
        $destino = $mapear[$n] ?? $n;
        $id = $mapaEspecialidad[$cid] ?? 0;
        printf("  carrera %d  %-46s -> %-12s (id %d)\n", $cid, $n, $destino, $id);
    }
    echo "\n";
}

if ($faltantes) {
    echo "Especialidades por crear:\n";
    foreach ($faltantes as $cid => $n) {
        echo "  carrera $cid -> $n\n";
    }
    echo "\n";

    if ($simular) {
        foreach ($faltantes as $cid => $n) {
            $mapaEspecialidad[$cid] = 0;   // solo para que el plan no se detenga
        }
    } else {
        $ins = $dst->prepare('INSERT IGNORE INTO especialidades (nombre) VALUES (?)');
        foreach ($faltantes as $n) {
            $ins->execute([$n]);
        }
        foreach ($faltantes as $cid => $n) {
            $s = $dst->prepare('SELECT id_especialidad FROM especialidades WHERE nombre = ?');
            $s->execute([$n]);
            $id = $s->fetchColumn();
            if (!$id) {
                fwrite(STDERR, "No se pudo crear la especialidad: $n\n");
                exit(1);
            }
            $mapaEspecialidad[$cid] = (int) $id;
        }
    }
}

/* 2. Datos de acceso del carnet ------------------------------------------------- */

$hashPorAlumno = [];
$usuarioPorAlumno = [];
foreach ($src->query('SELECT alumno_id, password, username FROM usuarios WHERE alumno_id IS NOT NULL') as $u) {
    $aId = (int) $u['alumno_id'];
    $hashPorAlumno[$aId] = (string) $u['password'];
    $usuarioPorAlumno[$aId] = (string) $u['username'];
}

/* 3. Claves ya usadas en el destino --------------------------------------------- */

$emailsUsados = [];
foreach ($dst->query('SELECT email FROM usuarios') as $u) {
    $emailsUsados[mb_strtolower($u['email'])] = true;
}

/* Fichas existentes: matrícula -> [id_estudiante, id_usuario, email] */
$fichas = [];
$porUsuario = [];
foreach ($dst->query(
    'SELECT e.id_estudiante, e.id_usuario, e.matricula, u.email
       FROM estudiantes e JOIN usuarios u ON u.id_usuario = e.id_usuario'
) as $f) {
    $mat = trim((string) $f['matricula']);
    if ($mat !== '') {
        $fichas[$mat] = $f;
    }
    $porUsuario[(int) $f['id_usuario']] = $f;
}

/* 4. Lectura de los alumnos ----------------------------------------------------- */

$sql = 'SELECT alumno_id, carrera_id, codigo_alumno, dni, nombres, apellido_paterno,
               apellido_materno, telefono, correo, estado, fecha_registro, deleted_at
          FROM ' . BASE . '.alumnos';
if (!$incluirBorr) {
    $sql .= ' WHERE deleted_at IS NULL';
}
$sql .= ' ORDER BY alumno_id';

$plan = [];          // matrícula => registro a aplicar
$sinCuenta = [];     // alumnos sin hash en el carnet
$reusarHash = 0;

foreach ($src->query($sql) as $a) {
    $aId    = (int) $a['alumno_id'];
    $codigo = trim((string) $a['codigo_alumno']);
    $dni    = trim((string) $a['dni']);

    $nombres = trim((string) $a['nombres']);
    $apellidos = trim((string) $a['apellido_paterno'] . ' ' . (string) $a['apellido_materno']);
    if ($apellidos === '') {
        $apellidos = $nombres;
    }

    $matricula = $codigo !== '' ? $codigo : 'C-' . $dni;
    $matricula = mb_substr($matricula, 0, 30);

    /* La ficha existente manda sobre el correo: no se cambia el correo de
       una cuenta ya creada, solo se actualizan datos. */
    $ficha = $fichas[$matricula] ?? null;
    $correoActual = $ficha ? mb_strtolower($ficha['email']) : '';

    $correo = normalizarEmail((string) $a['correo']);
    if ($correo === '' && isset($usuarioPorAlumno[$aId])) {
        $correo = normalizarEmail($usuarioPorAlumno[$aId]);
    }

    if ($ficha !== null) {
        $correo = $correoActual;                      // conservar el correo actual
    } else {
        if ($correo === '') {
            $correo = 'sin-correo-' . ($dni !== '' ? $dni : $codigo) . '@import.local';
        }
        $correo = acortarEmail($correo);
        $base = $correo;
        $n = 1;
        while (isset($emailsUsados[$correo])) {
            $n++;
            $pos = strrpos($base, '@');
            $correo = substr($base, 0, $pos) . ".$n" . substr($base, $pos);
        }
    }
    $emailsUsados[$correo] = true;

    $hash = $hashPorAlumno[$aId] ?? '';
    $esHashBcrypt = $hash !== '' && (str_starts_with($hash, '$2') || str_starts_with($hash, '$argon'));
    if ($esHashBcrypt) {
        $reusarHash++;
    } else {
        $hash = password_hash(TEMP_PASS, PASSWORD_DEFAULT);
        $sinCuenta[] = [
            'matricula' => $matricula,
            'nombre'    => trim("$nombres $apellidos"),
            'dni'       => $dni,
        ];
    }

    $carreraId = (int) $a['carrera_id'];
    $idEsp = $mapaEspecialidad[$carreraId] ?? null;
    if ($idEsp === null) {
        fwrite(STDERR, "Sin especialidad para el alumno de matrícula $matricula (carrera $carreraId)\n");
        exit(1);
    }

    $estadoDestino = match (strtoupper((string) $a['estado'])) {
        'EGRESADO' => 'egresado',
        'RETIRADO' => 'retirado',
        default    => 'activo',
    };

    $plan[$matricula] = [
        'matricula' => $matricula,
        'dni'       => $dni !== '' ? mb_substr($dni, 0, 20) : null,
        'correo'    => $correo,
        'nombres'   => mb_substr($nombres, 0, 100),
        'apellidos' => mb_substr($apellidos, 0, 100),
        'hash'      => $hash,
        'telefono'  => trim((string) $a['telefono']) ?: null,
        'id_esp'    => (int) $idEsp,
        'anio'      => (int) (new DateTime($a['fecha_registro']))->format('Y'),
        'estado'    => $estadoDestino,
        'id_ficha'  => $ficha ? (int) $ficha['id_estudiante'] : null,
        'id_usuario' => $ficha ? (int) $ficha['id_usuario'] : null,
    ];
}

$crear    = array_filter($plan, static fn($p) => $p['id_ficha'] === null);
$actualizar = array_filter($plan, static fn($p) => $p['id_ficha'] !== null);

echo "Resumen previo:\n";
printf("  alumnos leídos              : %d\n", count($plan));
printf("  ya presentes en destino     : %d\n", count($actualizar));
printf("  nuevos por crear            : %d\n", count($crear));
printf("  contraseñas reusadas        : %d\n", $reusarHash);
printf("  sin cuenta en carnet        : %d (clave %s)\n", count($sinCuenta), TEMP_PASS);
if ($mapear) {
    $cambioEsp = 0;
    foreach ($plan as $p) {
        if ($p['id_ficha'] !== null) {
            $s = $dst->prepare('SELECT id_especialidad FROM estudiantes WHERE id_estudiante = ?');
            $s->execute([$p['id_ficha']]);
            if ((int) $s->fetchColumn() !== $p['id_esp']) {
                $cambioEsp++;
            }
        }
    }
    printf("  cambian de especialidad    : %d\n", $cambioEsp);
}
echo "\n";

/* Especialidades con el nombre de una carrera del carnet que el mapeo deja de usar */
$porRetirar = [];
if ($mapear) {
    $marcas = implode(',', array_fill(0, count($mapear), '?'));
    $stmt = $dst->prepare("SELECT nombre FROM especialidades WHERE nombre IN ($marcas)");
    $stmt->execute(array_keys($mapear));
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $porRetirar[$n] = true;
    }
}

if ($simular) {
    echo "-- Simulación: no se escribió nada --\n";
    echo "Primeros 5 que se crearían:\n";
    foreach (array_slice($crear, 0, 5, true) as $c) {
        printf("  %-10s %-45s %s\n", $c['matricula'], $c['nombres'] . ' ' . $c['apellidos'], $c['correo']);
    }
    if ($porRetirar) {
        echo "\nEspecialidades que se retirarían al quedar vacías:\n";
        foreach (array_keys($porRetirar) as $n) {
            echo "  $n\n";
        }
    }
    if ($sinCuenta) {
        echo "\nAlumnos sin cuenta en carnet (clave temporal " . TEMP_PASS . "):\n";
        foreach ($sinCuenta as $s) {
            echo "  matrícula {$s['matricula']} · {$s['nombre']} · DNI {$s['dni']}\n";
        }
    }
    exit(0);
}

/* 5. Escritura ------------------------------------------------------------------ */

$dst->beginTransaction();

try {
    $insUsuario = $dst->prepare(
        'INSERT INTO usuarios (nombres, apellidos, email, password_hash, id_rol, telefono, activo)
         VALUES (:n, :a, :e, :p, :r, :t, 1)'
    );
    $updUsuario = $dst->prepare(
        'UPDATE usuarios SET nombres = :n, apellidos = :a, telefono = :t WHERE id_usuario = :id'
    );
    $insFicha = $dst->prepare(
        'INSERT INTO estudiantes (id_usuario, id_especialidad, dni, matricula, anio_ingreso, estado)
         VALUES (:u, :esp, :dni, :mat, :anio, :est)'
    );
    $updFicha = $dst->prepare(
        'UPDATE estudiantes
            SET id_especialidad = :esp, dni = :dni, anio_ingreso = :anio, estado = :est
          WHERE id_usuario = :u'
    );

    $creados = $actualizados = 0;

    foreach ($plan as $p) {
        $idUsuario = $p['id_usuario'];

        if ($idUsuario) {
            $updUsuario->execute([
                ':n'  => $p['nombres'],
                ':a'  => $p['apellidos'],
                ':t'  => $p['telefono'],
                ':id' => $idUsuario,
            ]);
            $updFicha->execute([
                ':esp' => $p['id_esp'],
                ':dni' => $p['dni'],
                ':anio' => $p['anio'],
                ':est' => $p['estado'],
                ':u'   => $idUsuario,
            ]);
            $actualizados++;
        } else {
            $insUsuario->execute([
                ':n' => $p['nombres'],
                ':a' => $p['apellidos'],
                ':e' => $p['correo'],
                ':p' => $p['hash'],
                ':r' => ID_ROL_ESTUDIANTE,
                ':t' => $p['telefono'],
            ]);
            $idUsuario = (int) $dst->lastInsertId();
            $insFicha->execute([
                ':u'    => $idUsuario,
                ':esp'  => $p['id_esp'],
                ':dni'  => $p['dni'],
                ':mat'  => $p['matricula'],
                ':anio' => $p['anio'],
                ':est'  => $p['estado'],
            ]);
            $creados++;
        }
    }

    $dst->commit();
} catch (Throwable $e) {
    $dst->rollBack();
    fwrite(STDERR, "Fallo durante la importación, se revirtió todo: " . $e->getMessage() . "\n");
    exit(1);
}

echo "-- Importación completada --\n";
printf("  usuarios creados      : %d\n", $creados);
printf("  usuarios actualizados : %d\n\n", $actualizados);

/* 6. Retiro de las especialidades del carnet que quedaron sin alumnos ------ */

$retiradas = [];
if ($porRetirar) {
    $del = $dst->prepare(
        'DELETE e FROM especialidades e
          WHERE e.nombre = ?
            AND NOT EXISTS (SELECT 1 FROM estudiantes x WHERE x.id_especialidad = e.id_especialidad)'
    );
    foreach (array_keys($porRetirar) as $n) {
        $del->execute([$n]);
        if ($del->rowCount() > 0) {
            $retiradas[] = $n;
        }
    }
}
if ($retiradas) {
    echo "Especialidades retiradas (sin alumnos): " . implode(', ', $retiradas) . "\n\n";
}

/* 7. Verificación --------------------------------------------------------------- */

$estudiantes = (int) $dst->query('SELECT COUNT(*) FROM estudiantes')->fetchColumn();
$rol3        = (int) $dst->query('SELECT COUNT(*) FROM usuarios WHERE id_rol = 3')->fetchColumn();
$espec       = (int) $dst->query('SELECT COUNT(*) FROM especialidades')->fetchColumn();
$sinFicha    = (int) $dst->query(
    'SELECT COUNT(*) FROM usuarios WHERE id_rol = 3
       AND id_usuario NOT IN (SELECT id_usuario FROM estudiantes)'
)->fetchColumn();
$matDuplicadas = (int) $dst->query(
    'SELECT COUNT(*) FROM (SELECT matricula FROM estudiantes
       WHERE matricula IS NOT NULL AND matricula <> ""
        GROUP BY matricula HAVING COUNT(*) > 1) d'
)->fetchColumn();
$correosDup = (int) $dst->query(
    'SELECT COUNT(*) FROM (SELECT email FROM usuarios GROUP BY email HAVING COUNT(*) > 1) d'
)->fetchColumn();

printf("  estudiantes            : %d\n", $estudiantes);
printf("  usuarios con rol 3     : %d\n", $rol3);
printf("  especialidades         : %d\n", $espec);
printf("  alumnos sin ficha      : %d\n", $sinFicha);
printf("  matrículas duplicadas  : %d\n", $matDuplicadas);
printf("  correos duplicados     : %d\n", $correosDup);

$fallas = $sinFicha + $matDuplicadas + $correosDup;
if ($fallas > 0) {
    fwrite(STDERR, "\n[AVISO] Verificación final con $fallas inconsistencia(s).\n");
    exit(1);
}

if ($sinCuenta) {
    echo "\n[IMPORTANTE] Alumnos sin cuenta en el sistema carnet.\n";
    echo "  Contraseña temporal: " . TEMP_PASS . "\n";
    foreach ($sinCuenta as $s) {
        echo "  matrícula {$s['matricula']} · {$s['nombre']} · DNI {$s['dni']}\n";
    }
}

echo "\nListo.\n";