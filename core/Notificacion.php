<?php

/**
 * Notificaciones internas del usuario (matrículas nuevas, avisos).
 */

class Notificacion
{
    /**
     * Crea una notificación.
     */
    public static function crear(?int $idUsuario, string $titulo, string $mensaje, string $tipo = 'info', ?string $url = null): void
    {
        try {
            db()->query(
                "INSERT INTO notificaciones (id_usuario, titulo, mensaje, tipo, url)
                 VALUES (:u, :t, :m, :tp, :url)",
                [
                    ':u'   => $idUsuario,
                    ':t'   => mb_substr($titulo, 0, 120),
                    ':m'   => mb_substr($mensaje, 0, 255),
                    ':tp'  => in_array($tipo, ['info', 'success', 'warning', 'error'], true) ? $tipo : 'info',
                    ':url' => $url,
                ]
            );
        } catch (Throwable $e) {
            // no crítico
        }
    }

    /**
     * Notificaciones del usuario actual, no leídas primero.
     */
    public static function listar(?int $idUsuario = null, int $limite = 8): array
    {
        $id = $idUsuario ?? Auth::id();
        if (!$id) {
            return [];
        }
        try {
            return db()->todas(
                "SELECT id_notificacion, titulo, mensaje, tipo, url, leida, creado_en
                   FROM notificaciones
                  WHERE id_usuario = :id
                  ORDER BY creado_en DESC
                  LIMIT {$limite}",
                [':id' => $id]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function no_leidas(?int $idUsuario = null): int
    {
        $id = $idUsuario ?? Auth::id();
        if (!$id) {
            return 0;
        }
        try {
            return (int) db()->valor(
                "SELECT COUNT(*) FROM notificaciones WHERE id_usuario = :id AND leida = 0",
                [':id' => $id],
                0
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function marcar_todas_leidas(?int $idUsuario = null): void
    {
        $id = $idUsuario ?? Auth::id();
        if (!$id) {
            return;
        }
        try {
            db()->query("UPDATE notificaciones SET leida = 1 WHERE id_usuario = :id", [':id' => $id]);
        } catch (Throwable $e) {
            // no crítico
        }
    }
}