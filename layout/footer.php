</main>

        <footer style="padding: 18px 26px; border-top: 1px solid var(--line); color: var(--text-mute); font-size: .78rem; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NOMBRE) ?> · <?= e(APP_INSTITUCION) ?></span>
            <span>Sesión iniciada el <?= e(fecha($_SESSION['user']['inicio_sesion'] ?? date('Y-m-d H:i:s'), 'd/m/Y \a \l\a\s H:i')) ?></span>
        </footer>
    </div>

    <div class="modal-backdrop" id="aviso-sesion" hidden role="dialog" aria-modal="true" aria-labelledby="aviso-titulo">
        <div class="modal">
            <h3 id="aviso-titulo">¿Sigues ahí?</h3>
            <p>Tu sesión quedó inactiva por seguridad. Inicia sesión de nuevo para continuar.</p>
            <div class="form-actions">
                <a class="btn btn-primary" href="<?= e(base_url('login.php')) ?>">Ir al inicio de sesión</a>
                <a class="btn btn-ghost" href="javascript:location.reload()">Cancelar</a>
            </div>
        </div>
    </div>

    <script src="<?= e(base_url('assets/js/sistema.js')) ?>"></script>
</body>

</html>