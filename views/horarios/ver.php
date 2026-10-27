<?php

/**
 * Horarios de clase — vista semanal.
 */

require_once __DIR__ . '/../../core/bootstrap.php';

Auth::requerir_rol(1, 2);

$periodo = input('periodo') ?: date('Y') . '-1';
$idCurso = input_int('curso');
$esAdmin = Auth::es_admin();
$idProfesor = $esAdmin ? null : db()->valor(
    "SELECT id_profesor FROM profesores WHERE id_usuario = :u AND activo = 1 LIMIT 1",
    [':u' => Auth::id()]
);

$dias    = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
/* La base puede guardar los días con o sin tilde: se normalizan para agrupar */
$normalizarDia = static function (string $dia): string {
    $mapa = [
        'Lunes' => 'Lunes',
        'Martes' => 'Martes',
        'Miercoles' => 'Miércoles',
        'Miércoles' => 'Miércoles',
        'Jueves' => 'Jueves',
        'Viernes' => 'Viernes',
        'Sabado' => 'Sábado',
        'Sábado' => 'Sábado',
        'Domingo' => 'Domingo',
    ];

    return $mapa[$dia] ?? $dia;
};
$franjas = ['07:00', '09:00', '11:00', '13:00', '15:00', '17:00', '19:00'];

if ($esAdmin) {
    $cursos = db()->todas("SELECT id_curso, codigo, nombre FROM cursos WHERE activo = 1 ORDER BY nombre ASC");
} elseif ($idProfesor) {
    $cursos = db()->todas(
        "SELECT DISTINCT c.id_curso, c.codigo, c.nombre
           FROM cursos c
           JOIN curso_docentes cd ON cd.id_curso = c.id_curso
          WHERE c.activo = 1 AND cd.id_profesor = :p
          ORDER BY c.nombre ASC",
        [':p' => $idProfesor]
    );
} else {
    $cursos = [];
}

if ($idCurso && !$esAdmin && !in_array($idCurso, array_map('intval', array_column($cursos, 'id_curso')), true)) {
    $idCurso = 0;
}

$filtros = ['h.periodo' => $periodo];
if ($idCurso) {
    $filtros['h.id_curso'] = $idCurso;
}

$horarios = [];
try {
    $horarios = db()->todas(
        "SELECT h.id_horario, h.dia_semana, h.hora_inicio, h.hora_fin, h.aula, h.id_salon,
                c.nombre AS curso, c.codigo,
                CONCAT(p.nombres,' ',p.apellidos) AS profesor
           FROM horarios h
           JOIN cursos c ON c.id_curso = h.id_curso
           JOIN profesores p ON p.id_profesor = h.id_profesor
          WHERE h.periodo = :p"
            . ($idCurso ? ' AND h.id_curso = :c' : '')
            . (!$esAdmin ? ' AND h.id_profesor = :profesor' : '') . "
          ORDER BY FIELD(h.dia_semana,'Lunes','Martes','Miércoles','Miercoles','Jueves','Viernes','Sábado','Sabado','Domingo'),
                   h.hora_inicio",
        array_merge(
            [':p' => $periodo],
            $idCurso ? [':c' => $idCurso] : [],
            !$esAdmin ? [':profesor' => $idProfesor ?? 0] : []
        )
    );
} catch (Throwable $e) {
    $horarios = [];
}

/* Agrupar por día */
$porDia = [];
foreach ($horarios as $h) {
    $porDia[$normalizarDia($h['dia_semana'])][] = $h;
}

/* Detectar solapamientos */
$conflictos = [];
foreach ($porDia as $dia => $lista) {
    usort($lista, static fn($a, $b) => strcmp($a['hora_inicio'], $b['hora_inicio']));
    for ($i = 0; $i < count($lista) - 1; $i++) {
        for ($j = $i + 1; $j < count($lista); $j++) {
            if ($lista[$j]['hora_inicio'] < $lista[$i]['hora_fin']) {
                $conflictos[] = "{$dia}: «{$lista[$i]['curso']}» se superpone con «{$lista[$j]['curso']}»";
            }
        }
    }
}

/* Carga por profesor */
$cargaDocente = [];
foreach ($horarios as $h) {
    $inicio = strtotime($h['hora_inicio']);
    $fin    = strtotime($h['hora_fin']);
    $horas  = ($fin - $inicio) / 3600;
    $cargaDocente[$h['profesor']] = ($cargaDocente[$h['profesor']] ?? 0) + $horas;
}
arsort($cargaDocente);

$titulo    = 'Horarios';
$subtitulo = count($horarios) . ' ' . (count($horarios) === 1 ? 'bloque' : 'bloques') . ' en el periodo ' . $periodo;
$seccion   = 'horarios';

$accionesExtra = '<a class="btn btn-outline btn-sm no-print" href="javascript:window.print()">'
    . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg> Imprimir</a>';

require_once APP_ROOT . '/layout/header.php';
?>

<?php if ($conflictos): ?>
    <div class="alert alert-warning">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M12 3l9 16H3l9-16z" />
        </svg>
        <div>
            <strong>Se detectaron <?= count($conflictos) ?> solapamiento(s) de horario:</strong>
            <ul style="margin:6px 0 0;padding-left:16px">
                <?php foreach (array_slice($conflictos, 0, 4) as $c): ?>
                    <li><?= e($c) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="toolbar no-print">
    <form class="flex gap-sm" method="GET" style="flex:1;flex-wrap:wrap">
        <select class="input" name="curso" data-auto-submit>
            <option value="">Todos los cursos</option>
            <?php foreach ($cursos as $c): ?>
                <option value="<?= (int) $c['id_curso'] ?>" <?= $idCurso === (int) $c['id_curso'] ? 'selected' : '' ?>>
                    <?= e($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select class="input" name="periodo" data-auto-submit>
            <?php
            $anios = range((int) date('Y') - 2, (int) date('Y') + 1);
            rsort($anios);
            ?>
            <?php foreach ($anios as $a): ?>
                <?php foreach (['1', '2'] as $s): ?>
                    <option value="<?= "{$a}-{$s}" ?>" <?= $periodo === "{$a}-{$s}" ? 'selected' : '' ?>>
                        <?= e("{$a}-{$s}") ?>
                    </option>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($cargaDocente): ?>
        <span class="muted small nowrap">
            <?= count($cargaDocente) ?> docente(s) con carga asignada
        </span>
    <?php endif; ?>
</div>

<?php if (!$horarios): ?>
    <div class="panel">
        <div class="empty">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
            </svg>
            <h3>No hay horarios para este periodo</h3>
            <p>Todavía no se han asignado bloques de clase. Los horarios se registran desde phpMyAdmin
                o mediante el script de carga inicial.</p>
            <a class="btn btn-outline" href="<?= e(base_url('views/cursos/listar.php')) ?>">Ver cursos</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid-2 horarios-layout">
        <div class="panel horarios-semana">
            <div class="panel-head">
                <div>
                    <h2>Horario semanal</h2>
                    <p>Periodo <?= e($periodo) ?></p>
                </div>
            </div>
            <div class="panel-body">
                <div class="horarios-scroll">
                    <table class="data horarios-tabla">
                        <thead>
                            <tr>
                                <?php foreach ($dias as $d): ?>
                                    <th><?= e($d) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach ($dias as $d): ?>
                                    <td>
                                        <?php if (empty($porDia[$d])): ?>
                                            <span class="horario-vacio">Sin clases</span>
                                        <?php else: ?>
                                            <div class="horario-dia">
                                                <?php foreach ($porDia[$d] as $h): ?>
                                                    <div class="horario-bloque">
                                                        <b class="horario-curso"><?= e($h['curso']) ?></b>
                                                        <span class="horario-hora">
                                                            <?= e(substr($h['hora_inicio'], 0, 5)) ?> – <?= e(substr($h['hora_fin'], 0, 5)) ?>
                                                        </span>
                                                        <span class="horario-dato"><?= e($h['profesor']) ?></span>
                                                        <?php if ($h['aula'] || $h['id_salon']): ?>
                                                            <span class="horario-dato">
                                                                Aula: <?= e($h['aula'] ?: $h['id_salon']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>Carga horaria docente</h2>
                    <p>Horas semanales asignadas</p>
                </div>
            </div>
            <div class="panel-body">
                <?php $maxCarga = max(1, max($cargaDocente)); ?>
                <div class="form-grid" style="gap:14px">
                    <?php foreach ($cargaDocente as $docente => $horas): ?>
                        <?php $pct = round(($horas / $maxCarga) * 100); ?>
                        <div>
                            <div class="flex-between" style="margin-bottom:6px">
                                <span style="font-size:.87rem;font-weight:600"><?= e($docente) ?></span>
                                <span class="tabular" style="font-size:.84rem;font-weight:700;color:var(--brand)">
                                    <?= e(rtrim(rtrim(number_format($horas, 1, ',', ''), '0'), ',')) ?> h
                                </span>
                            </div>
                            <div class="bar <?= $horas > 20 ? 'warn' : '' ?>">
                                <span data-bar="<?= $pct ?>" style="width:0"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>Detalle de bloques</h2>
                    <p>Todas las sesiones del periodo</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-wrap" style="max-height:340px;overflow-y:auto">
                    <table class="data" style="min-width:auto">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Docente</th>
                                <th>Horario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios as $h): ?>
                                <tr>
                                    <td><?= e($h['curso']) ?></td>
                                    <td class="muted"><?= e($h['profesor']) ?></td>
                                    <td class="tabular nowrap">
                                        <?= e($h['dia_semana'] . ' ' . substr($h['hora_inicio'], 0, 5)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once APP_ROOT . '/layout/footer.php'; ?>