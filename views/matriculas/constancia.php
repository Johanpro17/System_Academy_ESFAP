<?php

/**
 * Constancia de matrícula imprimible con folio.
 * Accesible para administración, secretaría y docentes.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_permiso('matriculas.ver');

$id = input_int('id');

$registro = $id ? db()->fila(
    "SELECT m.id_matricula, m.periodo, m.estado, m.fecha_matricula,
            e.matricula AS codigo, e.dni,
            CONCAT(u.nombres,' ',u.apellidos) AS estudiante, u.email,
            esp.nombre AS especialidad,
            c.nombre AS curso, c.codigo AS codigo_curso, c.creditos, c.area,
            p.nombres AS docente
       FROM matriculas m
       JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
       JOIN usuarios u ON u.id_usuario = e.id_usuario
       JOIN especialidades esp ON esp.id_especialidad = e.id_especialidad
       JOIN cursos c ON c.id_curso = m.id_curso
       LEFT JOIN horarios h ON h.id_curso = c.id_curso
            AND h.periodo = m.periodo
       LEFT JOIN profesores p ON p.id_profesor = h.id_profesor
      WHERE m.id_matricula = :id"
        . (Auth::es_docente() ? "
            AND EXISTS (
                SELECT 1 FROM curso_docentes cd
                 WHERE cd.id_curso = m.id_curso
                   AND cd.id_profesor = (
                       SELECT p.id_profesor FROM profesores p
                        WHERE p.id_usuario = " . (int) Auth::id() . " AND p.activo = 1
                        LIMIT 1
                   )
            )" : '') . "
      ORDER BY h.id_horario IS NULL, h.id_horario
      LIMIT 1",
    [':id' => $id]
) : null;

if (!$registro) {
    http_response_code(404);
    require_once APP_ROOT . '/public/404.php';
    exit;
}

$folio      = sprintf('MAT-%s-%04d', $registro['periodo'], $registro['id_matricula']);
$identidad  = '';
if ($registro['dni']) {
    $identidad .= ', DNI ' . $registro['dni'];
}
if ($registro['codigo']) {
    $identidad .= ', código de estudiante ' . $registro['codigo'];
}
if ($registro['email']) {
    $identidad .= ' (' . $registro['email'] . ')';
}
$etiqueta   = [
    'matriculado' => ['Matriculado', 'green'],
    'aprobado'    => ['Aprobado', 'blue'],
    'reprobado'   => ['Reprobado', 'red'],
    'retirado'    => ['Retirado', 'gray'],
];
[$estadoTexto, $estadoColor] = $etiqueta[$registro['estado']] ?? ['Matriculado', 'green'];

$titulo    = 'Constancia de matrícula';
$subtitulo = 'Folio ' . $folio;
$seccion   = 'matriculas';
$ancho     = 'content-narrow constancia-pagina';

$accionesExtra = '<div class="flex gap-sm no-print">'
    . '<button class="btn btn-primary btn-sm" type="button" data-imprimir>'
    . '<svg viewBox="0 0 24 24" aria-hidden="true">'
    . '<path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" />'
    . '<path d="M6 14h12v8H6z" />'
    . '</svg> Imprimir</button>'
    . '<a class="btn btn-ghost btn-sm" href="listar.php">Volver al listado</a>'
    . '</div>';

require_once APP_ROOT . '/layout/header.php';
?>

<article class="constancia">
    <header class="constancia-head">
        <div class="constancia-marca">
            <img src="<?= e(base_url('assets/img/favicon.svg')) ?>" alt="" width="44" height="44">
            <div>
                <b><?= e(APP_NOMBRE) ?></b>
                <span><?= e(APP_INSTITUCION) ?></span>
                <span>Oficina de Registro Académico</span>
            </div>
        </div>
        <div class="constancia-folio">
            <span>Folio</span>
            <b><?= e($folio) ?></b>
        </div>
    </header>

    <div class="constancia-body">
        <h2>Constancia de matrícula</h2>
        <p class="constancia-lead">
            Se certifica que
            <b><?= e($registro['estudiante']) ?></b><?= e($identidad) ?>,
            se encuentra inscrito en la especialidad
            <b><?= e($registro['especialidad']) ?></b>
            y cursa el curso <b><?= e($registro['curso']) ?></b>
            <?php if ($registro['codigo_curso']): ?>
                (<?= e($registro['codigo_curso']) ?>)
            <?php endif; ?>
            por <?= (int) $registro['creditos'] ?> cr&eacute;ditos
            <?php if ($registro['area']): ?>
                del &aacute;rea de <?= e($registro['area']) ?>
            <?php endif; ?>
            <?php if ($registro['docente']): ?>
                bajo la docencia de <b><?= e($registro['docente']) ?></b>
            <?php endif; ?>
            durante el periodo acad&eacute;mico <b><?= e($registro['periodo']) ?></b>.
        </p>

        <dl class="constancia-datos">
            <div><dt>Estudiante</dt><dd><?= e($registro['estudiante']) ?></dd></div>
            <div><dt>DNI</dt><dd><?= $registro['dni'] ? e($registro['dni']) : '—' ?></dd></div>
            <div><dt>C&oacute;digo</dt><dd><?= $registro['codigo'] ? e($registro['codigo']) : '—' ?></dd></div>
            <div><dt>Especialidad</dt><dd><?= e($registro['especialidad']) ?></dd></div>
            <div><dt>Curso</dt><dd><?= e($registro['curso']) ?></dd></div>
            <div><dt>Cr&eacute;ditos</dt><dd><?= (int) $registro['creditos'] ?></dd></div>
            <div><dt>Periodo</dt><dd><?= e($registro['periodo']) ?></dd></div>
            <div>
                <dt>Estado</dt>
                <dd><span class="badge badge-<?= e($estadoColor) ?>"><?= e($estadoTexto) ?></span></dd>
            </div>
            <div><dt>Fecha de matr&iacute;cula</dt>
                <dd><?= e(fecha($registro['fecha_matricula'], 'd/m/Y H:i')) ?></dd></div>
            <div><dt>Emitida</dt><dd><?= e(fecha('now', 'd/m/Y')) ?></dd></div>
        </dl>

        <div class="constancia-firmas">
            <div class="firma">
                <span></span>
                <p>Responsable de Registro Acad&eacute;mico</p>
            </div>
            <div class="firma">
                <span></span>
                <p><?= e($registro['estudiante']) ?></p>
            </div>
        </div>

        <p class="constancia-pie">
            Documento generado por <?= e(APP_NOMBRE) ?> &middot;
            Folio <?= e($folio) ?> &middot;
            V&aacute;lido hasta ser invalidado por la Oficina de Registro Acad&eacute;mico.
        </p>
    </div>
</article>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>
