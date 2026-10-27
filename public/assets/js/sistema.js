/* =========================================================
   Sistema Académico — Interacciones
   ========================================================= */

(function () {
    'use strict';

    /* ---------- Tema claro / oscuro ---------- */

    var TEMA_KEY = 'tema';

    function aplicarTema(tema) {
        document.documentElement.setAttribute('data-theme', tema);
        document.cookie = TEMA_KEY + '=' + tema + ';path=/;max-age=31536000;samesite=lax';
        try {
            localStorage.setItem(TEMA_KEY, tema);
        } catch (e) { /* almacenamiento no disponible */ }

        var btns = document.querySelectorAll('[data-tema-toggle]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].setAttribute('aria-label',
                tema === 'oscuro' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
            var icono = btns[i].querySelector('[data-icono-claro]');
            var iconoOscuro = btns[i].querySelector('[data-icono-oscuro]');
            if (icono) icono.style.display = tema === 'oscuro' ? 'none' : '';
            if (iconoOscuro) iconoOscuro.style.display = tema === 'oscuro' ? '' : 'none';
        }
    }

    function temaInicial() {
        var guardado = null;
        try {
            guardado = localStorage.getItem(TEMA_KEY);
        } catch (e) { /* noop */ }

        if (!guardado && document.documentElement.getAttribute('data-theme')) {
            return document.documentElement.getAttribute('data-theme');
        }
        if (guardado) return guardado;

        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'oscuro' : 'claro';
    }

    aplicarTema(temaInicial());

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-tema-toggle]');
        if (!btn) return;
        var actual = document.documentElement.getAttribute('data-theme');
        aplicarTema(actual === 'oscuro' ? 'claro' : 'oscuro');
    });

    /* ---------- Menú lateral ---------- */

    document.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-nav-toggle]')) {
            document.body.classList.toggle('nav-open');
        } else if (ev.target.closest('.scrim')) {
            document.body.classList.remove('nav-open');
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            document.body.classList.remove('nav-open');
            cerrarPopovers();
        }
    });

    /* ---------- Popovers (notificaciones, menú usuario) ---------- */

    function cerrarPopovers() {
        var ps = document.querySelectorAll('.popover.open');
        for (var i = 0; i < ps.length; i++) {
            ps[i].classList.remove('open');
        }
    }

    document.addEventListener('click', function (ev) {
        var trigger = ev.target.closest('[data-popover]');
        if (trigger) {
            var target = document.getElementById(trigger.getAttribute('data-popover'));
            if (target) {
                var abierto = target.classList.contains('open');
                cerrarPopovers();
                if (!abierto) target.classList.add('open');
            }
            return;
        }

        if (!ev.target.closest('.popover')) {
            cerrarPopovers();
        }
    });

    /* ---------- Alertas descartables ---------- */

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.alert-close');
        if (btn) {
            var alert = btn.closest('.alert');
            alert.style.transition = 'opacity .2s ease, transform .2s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(function () { alert.remove(); }, 200);
        }
    });

    /* ---------- Mostrar / ocultar contraseña ---------- */

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-toggle-password]');
        if (!btn) return;

        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;

        var ver = input.type === 'password';
        input.type = ver ? 'text' : 'password';
        btn.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');

        var iconos = btn.querySelectorAll('svg');
        for (var i = 0; i < iconos.length; i++) {
            iconos[i].style.display = (ver && i === 1) || (!ver && i === 0) ? '' : 'none';
        }
    });

    /* ---------- Envío automático de formularios de filtro ---------- */

    var autoForm = document.querySelectorAll('[data-auto-submit]');
    for (var i = 0; i < autoForm.length; i++) {
        (function (form) {
            var control = form.querySelector('[data-auto-submit]');
            if (!control) return;
            control.addEventListener('change', function () { form.submit(); });
        })(autoForm[i]);
    }

    /* ---------- Tablas ordenables ---------- */

    document.addEventListener('click', function (ev) {
        var th = ev.target.closest('th.sortable');
        if (!th) return;

        var tabla = th.closest('table');
        if (!tabla) return;

        var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
        var ascendente = th.getAttribute('data-sort') !== 'asc';

        var url = new URL(window.location.href);
        url.searchParams.set('orden_col', idx);
        url.searchParams.set('orden_dir', ascendente ? 'asc' : 'desc');
        window.location.href = url.toString();
    });

    /* ---------- Confirmación de acciones destructivas ---------- */

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        var mensaje = form.getAttribute('data-confirmar');
        if (mensaje && !window.confirm(mensaje)) {
            ev.preventDefault();
        }
    });

    /* ---------- Animación de entrada al hacer scroll ---------- */

    var animables = document.querySelectorAll('[data-animar]');
    if (animables.length && 'IntersectionObserver' in window) {
        var obs = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('visible');
                    obs.unobserve(entrada.target);
                }
            });
        }, { threshold: 0.08 });

        for (var a = 0; a < animables.length; a++) {
            obs.observe(animables[a]);
        }
    } else {
        for (var b = 0; b < animables.length; b++) {
            animables[b].classList.add('visible');
        }
    }

    /* ---------- Preview de avatar ---------- */

    document.addEventListener('change', function (ev) {
        var input = ev.target.closest('[data-avatar-input]');
        if (!input || !input.files || !input.files[0]) return;

        var img = document.querySelector('[data-avatar-preview]');
        if (!img) return;

        var lector = new FileReader();
        lector.onload = function (e) { img.src = e.target.result; };
        lector.readAsDataURL(input.files[0]);
    });

    /* ---------- Barras animadas al cargar ---------- */

    var barras = document.querySelectorAll('[data-bar]');
    if (barras.length && 'IntersectionObserver' in window) {
        var obsBarras = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (entrada.isIntersecting) {
                    var el = entrada.target;
                    el.style.width = el.getAttribute('data-bar') + '%';
                    obsBarras.unobserve(el);
                }
            });
        }, { threshold: 0.2 });

        for (var c = 0; c < barras.length; c++) {
            obsBarras.observe(barras[c]);
        }
    }

    /* ---------- Alta de matrícula: buscador y resumen previo ---------- */

    var formMatricula = document.querySelector('[data-form-matricula]');
    if (formMatricula) {
        var selecEst = formMatricula.querySelector('#id_estudiante');
        var selecCur = formMatricula.querySelector('#id_curso');
        var selecPer = formMatricula.querySelector('#periodo');
        var busqueda = formMatricula.querySelector('[data-busqueda-estudiante]');
        var sinCoi = formMatricula.querySelector('[data-sin-coincidencias]');
        var boton = formMatricula.querySelector('[data-enviar]');

        function asignarResumen(clave, valor) {
            var el = formMatricula.querySelector('[data-res="' + clave + '"]');
            if (el) el.textContent = valor;
        }

        function actualizarResumen() {
            var optEst = selecEst && selecEst.value ? selecEst.options[selecEst.selectedIndex] : null;
            var optCur = selecCur && selecCur.value ? selecCur.options[selecCur.selectedIndex] : null;

            asignarResumen('codigo', optEst ? optEst.getAttribute('data-codigo') || '—' : '—');
            asignarResumen('estudiante', optEst ? optEst.getAttribute('data-nombre') || '—' : '—');
            asignarResumen('dni', optEst ? optEst.getAttribute('data-dni') || '—' : '—');
            asignarResumen('especialidad', optEst ? optEst.getAttribute('data-especialidad') || '—' : '—');
            asignarResumen('curso', optCur ? optCur.getAttribute('data-nombre') || '—' : '—');
            asignarResumen('creditos', optCur ? optCur.getAttribute('data-creditos') || '—' : '—');
            asignarResumen('periodo', selecPer && selecPer.value ? selecPer.options[selecPer.selectedIndex].text : '—');

            var completo = !!optEst && !!optCur && !!selecPer && !!selecPer.value;
            var estado = formMatricula.querySelector('[data-res="estado"]');
            if (estado) {
                estado.textContent = completo ? 'Lista para confirmar' : 'Incompleta';
                estado.className = 'badge ' + (completo ? 'badge-green' : 'badge-amber');
            }
            var aviso = formMatricula.querySelector('[data-res="aviso"]');
            if (aviso) {
                aviso.textContent = completo
                    ? 'Revisa los datos antes de confirmar la matrícula.'
                    : 'Selecciona estudiante, curso y periodo para poder confirmar.';
            }
            if (boton) boton.disabled = !completo;
        }

        function filtrarEstudiantes() {
            if (!selecEst) return;
            var q = (busqueda ? busqueda.value.trim() : '').toLowerCase();
            var opciones = selecEst.querySelectorAll('option');
            var visibles = 0;
            for (var i = 0; i < opciones.length; i++) {
                var opt = opciones[i];
                if (opt.value === '') { continue; }
                var coincide = q === '' || (opt.getAttribute('data-buscar') || opt.textContent || '').toLowerCase().indexOf(q) !== -1;
                opt.style.display = coincide ? '' : 'none';
                opt.hidden = !coincide;
                if (!coincide && opt.selected) { selecEst.value = ''; }
                if (coincide) visibles++;
            }
            var grupos = selecEst.querySelectorAll('optgroup');
            for (var g = 0; g < grupos.length; g++) {
                var hijos = grupos[g].querySelectorAll('option');
                var hay = false;
                for (var h = 0; h < hijos.length; h++) {
                    if (!hijos[h].hidden) { hay = true; break; }
                }
                grupos[g].style.display = hay ? '' : 'none';
            }
            if (sinCoi) sinCoi.hidden = visibles > 0;
            actualizarResumen();
        }

        if (busqueda) busqueda.addEventListener('input', filtrarEstudiantes);
        if (selecEst) selecEst.addEventListener('change', actualizarResumen);
        if (selecCur) selecCur.addEventListener('change', actualizarResumen);
        if (selecPer) selecPer.addEventListener('change', actualizarResumen);
        actualizarResumen();
    }

    /* ---------- Impresión de constancias ---------- */

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-imprimir]');
        if (!btn) return;
        if (window.print) window.print();
    });

    /* ---------- Cierre automático de sesión por inactividad ---------- */

    var LIMITE_MS = 30 * 60 * 1000;
    var timer = null;

    function reiniciar() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
            var aviso = document.getElementById('aviso-sesion');
            if (aviso) aviso.hidden = false;
        }, LIMITE_MS);
    }

    ['click', 'keydown', 'mousemove', 'scroll'].forEach(function (ev) {
        document.addEventListener(ev, reiniciar, { passive: true });
    });
    reiniciar();
})();