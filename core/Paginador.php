<?php

/**
 * Paginador reutilizable para listados con búsqueda y filtros.
 */

class Paginador
{
    private $total;
    private $porPagina;
    private $pagina;
    private $paginas;

    public function __construct(int $total, int $porPagina = 10, int $pagina = 1)
    {
        $this->total     = max(0, $total);
        $this->porPagina = max(1, $porPagina);
        $this->pagina    = max(1, $pagina);
        $this->paginas   = (int) max(1, ceil($this->total / $this->porPagina));
        $this->pagina    = min($this->pagina, $this->paginas);
    }

    /**
     * Cuenta el total de registros aplicando los filtros.
     *
     * @param string $tabla    Tabla o expresión FROM
     * @param array  $filtros  ['columna' => 'valor'] o ['columna' => ['like','busqueda']]
     */
    public static function contar(string $tabla, array $filtros = [], ?string $join = null): int
    {
        [$where, $params] = self::construir($filtros);

        try {
            $sql = "SELECT COUNT(*) FROM {$tabla} " . ($join ? $join : '') . $where;
            return (int) db()->valor($sql, $params, 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Obtiene una porción de los registros.
     */
    public static function obtener(
        string $tabla,
        array $columnas = ['*'],
        array $filtros = [],
        string $orden = '',
        int $limite = 10,
        int $offset = 0,
        ?string $join = null
    ): PDOStatement {
        [$where, $params] = self::construir($filtros);

        $cols = implode(', ', $columnas);
        $sql  = "SELECT {$cols} FROM {$tabla} " . ($join ? $join : '') . $where;
        if ($orden !== '') {
            $sql .= " ORDER BY {$orden}";
        }
        $sql .= " LIMIT {$limite} OFFSET {$offset}";

        return db()->query($sql, $params);
    }

    /**
     * Traduce el array de filtros a WHERE + parámetros.
     *
     * Las columnas comparadas con `like` y el mismo texto forman un único
     * grupo OR: es una sola caja de búsqueda que abarca varias columnas
     * (nombre, correo, matrícula…). El resto de los filtros se une con AND.
     */
    private static function construir(array $filtros): array
    {
        if (!$filtros) {
            return ['', []];
        }

        $donde  = [];
        $params = [];
        $i      = 0;
        $busquedas = [];

        foreach ($filtros as $columna => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            /* Varias condiciones sobre la misma columna: [['>=', $desde], ['<=', $hasta]] */
            if (is_array($valor) && array_is_list($valor) && isset($valor[0]) && is_array($valor[0])) {
                foreach ($valor as $condicion) {
                    $i++;
                    $ph = ":f{$i}";
                    $donde[]  = "{$columna} {$condicion[0]} {$ph}";
                    $params[$ph] = $condicion[1] ?? '';
                }
                continue;
            }

            if (is_array($valor)) {
                $tipo = $valor[0] ?? 'like';
                $dato = $valor[1] ?? '';

                if ($dato === '') {
                    continue;
                }

                $i++;
                $ph = ":f{$i}";

                if ($tipo === 'like' || $tipo === 'like_i') {
                    $busquedas[(string) $dato][] = "{$columna} LIKE {$ph}";
                    $params[$ph] = '%' . $dato . '%';
                    continue;
                }

                if ($tipo === 'in') {
                    $marcas = [];
                    foreach ((array) $dato as $k => $v) {
                        /* El marcador no puede contener puntos (v.id_curso). */
                        $p = ":f{$i}_{$k}";
                        $marcas[] = $p;
                        $params[$p] = $v;
                    }
                    if ($marcas) {
                        $donde[] = "{$columna} IN (" . implode(',', $marcas) . ')';
                    }
                    continue;
                }

                $donde[]  = "{$columna} = {$ph}";
                $params[$ph] = $dato;
                continue;
            }

            $i++;
            $ph = ":f{$i}";
            $donde[]    = "{$columna} = {$ph}";
            $params[$ph] = $valor;
        }

        $grupos = [];
        foreach ($busquedas as $trozos) {
            $grupos[] = count($trozos) > 1
                ? '(' . implode(' OR ', $trozos) . ')'
                : $trozos[0];
        }

        $donde = array_merge($grupos, $donde);

        return [$donde ? ' WHERE ' . implode(' AND ', $donde) : '', $params];
    }

    /* ---------------- Acceso ---------------- */

    public function total(): int      { return $this->total; }
    public function pagina(): int     { return $this->pagina; }
    public function paginas(): int    { return $this->paginas; }
    public function por_pagina(): int { return $this->porPagina; }
    public function offset(): int     { return ($this->pagina - 1) * $this->porPagina; }

    public function inicio(): int     { return $this->total ? $this->offset() + 1 : 0; }
    public function fin(): int        { return min($this->offset() + $this->porPagina, $this->total); }

    public function hay_paginas(): bool { return $this->paginas > 1; }

    /**
     * Renders the pagination controls.
     */
    public function render(array $params = []): string
    {
        if (!$this->hay_paginas()) {
            return '';
        }

        $desde = max(1, $this->pagina - 2);
        $hasta = min($this->paginas, $this->pagina + 2);

        $html = '<nav class="paginacion" aria-label="Paginación">';
        $html .= '<span class="paginacion-info">Mostrando <strong>' . $this->inicio() . '–' . $this->fin()
               . '</strong> de <strong>' . $this->total . '</strong></span>';
        $html .= '<div class="paginacion-botones">';

        $prev = max(1, $this->pagina - 1);
        $html .= '<a class="page-btn' . ($this->pagina === 1 ? ' disabled' : '')
               . '" href="' . e(url_con(['pag' => $prev], $params)) . '" aria-label="Anterior">&larr;</a>';

        if ($desde > 1) {
            $html .= '<a class="page-btn" href="' . e(url_con(['pag' => 1], $params)) . '">1</a>';
            if ($desde > 2) {
                $html .= '<span class="page-gap">…</span>';
            }
        }

        for ($i = $desde; $i <= $hasta; $i++) {
            $html .= '<a class="page-btn' . ($i === $this->pagina ? ' active' : '')
                   . '" href="' . e(url_con(['pag' => $i], $params)) . '">' . $i . '</a>';
        }

        if ($hasta < $this->paginas) {
            if ($hasta < $this->paginas - 1) {
                $html .= '<span class="page-gap">…</span>';
            }
            $html .= '<a class="page-btn" href="' . e(url_con(['pag' => $this->paginas], $params)) . '">'
                   . $this->paginas . '</a>';
        }

        $next = min($this->paginas, $this->pagina + 1);
        $html .= '<a class="page-btn' . ($this->pagina === $this->paginas ? ' disabled' : '')
               . '" href="' . e(url_con(['pag' => $next], $params)) . '" aria-label="Siguiente">&rarr;</a>';

        $html .= '</div></nav>';

        return $html;
    }
}