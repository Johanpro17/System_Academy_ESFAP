<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$imagenes = "../imagenes/";

$modulos = [
    [
        'icono' => 'M4 6h16M4 12h16M4 18h10',
        'titulo' => 'Control de matrículas',
        'texto' => 'Administra inscripciones y grupos con seguimiento claro, para una coordinación efectiva de cada ciclo académico.',
        'meta' => 'Ciclos y grupos',
    ],
    [
        'icono' => 'M12 3l9 5-9 5-9-5 9-5zm0 10v8m9-8v6c0 1.7-4 3-9 3s-9-1.3-9-3v-6',
        'titulo' => 'Gestión de docentes',
        'texto' => 'Organiza horarios, asignaciones y datos de profesores desde una interfaz ordenada y accesible.',
        'meta' => 'Carga académica',
    ],
    [
        'icono' => 'M4 20V10m5 10V4m5 16v-7m5 7V8',
        'titulo' => 'Reportes inteligentes',
        'texto' => 'Genera estadísticas y promedios rápidamente, con datos listos para toma de decisiones y evaluación de desempeño.',
        'meta' => 'PDF y exportación',
    ],
    [
        'icono' => 'M12 3l8 4v5c0 5-3.4 8.3-8 9-4.6-.7-8-4-8-9V7l8-4z',
        'titulo' => 'Acceso seguro',
        'texto' => 'Un login exclusivo para usuarios autorizados, con un flujo de acceso sencillo y coherente con la portada institucional.',
        'meta' => 'Sesión protegida',
    ],
];

$pasos = [
    ['01', 'Inicia sesión', 'Accede con las credenciales institucionales asignadas por coordinación.'],
    ['02', 'Registra y organiza', 'Carga cursos, matricula estudiantes y asigna docentes en minutos.'],
    ['03', 'Consulta resultados', 'Revisa promedios, horarios y reportes consolidados del periodo.'],
];

$indicadores = [
    ['4', 'Módulos operativos'],
    ['5', 'Perfiles de acceso'],
    ['100%', 'Trazabilidad'],
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Plataforma institucional para la gestión académica: matrículas, cursos, docentes, horarios y reportes en un solo lugar.">
    <title>Portada - Sistema Académico</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="portada.css?v=<?= (int) @filemtime(__DIR__ . '/portada.css') ?>">
</head>

<body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header class="topbar">
        <div class="shell topbar-inner">
            <a href="index.php" class="brand">
                <span class="brand-mark" aria-hidden="true">SA</span>
                <span class="brand-text">
                    Sistema <strong>Académico</strong>
                    <small>Escuela de Formación Artística</small>
                </span>
            </a>

            <nav class="topnav" aria-label="Navegación principal">
                <a href="#modulos">Módulos</a>
                <a href="#proceso">Cómo funciona</a>
                <a href="#galeria">Institución</a>
            </nav>

            <div class="topbar-actions">
                <a href="login.php" class="btn btn-ghost">Acceder</a>
                <a href="login.php" class="btn btn-primary">Iniciar Sesión</a>
            </div>
        </div>
    </header>

    <main id="contenido">
        <section class="hero">
            <div class="shell hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow">Plataforma institucional</span>
                    <h1>Gestión académica <em>moderna</em> para tu institución</h1>
                    <p class="lead">
                        Accede a un sistema intuitivo para estudiantes, docentes, secretaría, auditoría y administración.
                        Controla matrículas, cursos, reportes y horarios en una sola experiencia profesional.
                    </p>

                    <div class="hero-actions">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            Ingresar al sistema
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </a>
                        <a href="#modulos" class="btn btn-outline btn-lg">Conocer funciones</a>
                    </div>

                    <ul class="hero-points">
                        <li>Sin instalaciones ni configuración local</li>
                        <li>Reportes listos para exportar</li>
                        <li>Soporte para ciclos y semestres</li>
                    </ul>
                </div>

                <div class="hero-visual">
                    <figure class="hero-banner">
                        <img src="<?= $imagenes ?>portada_esfap.png" alt="Portada institucional de la escuela" loading="eager">
                        <figcaption class="banner-caption">
                            <strong>ESFAP</strong>
                            <span>Formación artística con propósito</span>
                        </figcaption>
                    </figure>

                    <div class="hero-stats">
                        <?php foreach ($indicadores as $i => $ind): ?>
                            <div class="stat-card">
                                <span class="stat-value"><?= $ind[0] ?></span>
                                <span class="stat-label"><?= $ind[1] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="float-chip chip-a">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        Matrícula registrada
                    </div>
                    <div class="float-chip chip-b">
                        <span class="pulse"></span>
                        Sistema operativo
                    </div>
                </div>
            </div>

            <div class="shell">
                <div class="marquee">
                    <span>Escuela de Formación Artística</span>
                    <span class="dot"></span>
                    <span>Gestión de Estudiantes</span>
                    <span class="dot"></span>
                    <span>Cursos y Créditos</span>
                    <span class="dot"></span>
                    <span>Horarios</span>
                    <span class="dot"></span>
                    <span>Reportes Académicos</span>
                </div>
            </div>
        </section>

        <section id="modulos" class="section section-alt">
            <div class="shell">
                <div class="section-head">
                    <span class="eyebrow">Funcionalidades</span>
                    <h2>Todo lo que la coordinación necesita</h2>
                    <p>Cada módulo resuelve una tarea real del día a día, con datos conectados entre sí.</p>
                </div>

                <div class="cards">
                    <?php foreach ($modulos as $m): ?>
                        <article class="card">
                            <span class="card-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="<?= $m['icono'] ?>" />
                                </svg>
                            </span>
                            <h3><?= $m['titulo'] ?></h3>
                            <p><?= $m['texto'] ?></p>
                            <span class="card-tag"><?= $m['meta'] ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="proceso" class="section">
            <div class="shell">
                <div class="section-head">
                    <span class="eyebrow">Flujo de trabajo</span>
                    <h2>Tres pasos para empezar</h2>
                    <p>Sin nudos ni curvas de aprendizaje: el sistema está pensado para usarse el mismo día de instalación.</p>
                </div>

                <ol class="steps">
                    <?php foreach ($pasos as $p): ?>
                        <li class="step">
                            <span class="step-num"><?= $p[0] ?></span>
                            <h3><?= $p[1] ?></h3>
                            <p><?= $p[2] ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>

        <section id="galeria" class="section section-alt">
            <div class="shell gallery">
                <figure class="gallery-main">
                    <img src="<?= $imagenes ?>la-noche-estrellada.png" alt="Obra artística de la institución" loading="lazy">
                    <figcaption>Cultura, arte y formación profesional</figcaption>
                </figure>

                <div class="gallery-side">
                    <figure class="gallery-small">
                        <img src="<?= $imagenes ?>logoInstitucion.jpg" alt="Logo de la institución" loading="lazy">
                    </figure>
                    <div class="quote-card">
                        <p>“La administración académica se mide por la calidad de la información que deja a la comunidad.”</p>
                        <span>Coordinación Académica</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta">
            <div class="shell cta-inner">
                <div>
                    <h2>¿Listo para organizar tu próximo ciclo?</h2>
                    <p>Accede con las credenciales institucionales y empieza a gestionar matrículas, cursos y reportes.</p>
                </div>
                <a href="login.php" class="btn btn-inverse btn-lg">Iniciar Sesión</a>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="shell footer-inner">
            <div class="footer-brand">
                <span class="brand-mark" aria-hidden="true">SA</span>
                <div>
                    <strong>Sistema Académico</strong>
                    <span>Escuela de Formación Artística</span>
                </div>
            </div>
            <nav class="footer-links" aria-label="Enlaces del pie">
                <a href="#modulos">Módulos</a>
                <a href="#proceso">Proceso</a>
                <a href="login.php">Iniciar Sesión</a>
            </nav>
            <p class="footer-copy">&copy; <?= date('Y') ?> Sistema Académico. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>

</html>
