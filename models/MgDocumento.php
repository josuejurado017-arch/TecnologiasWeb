<?php

declare(strict_types=1);

/**
 * Documentos de Modalidades de Grado (db/046, HU-027/030): plantillas HTML con
 * {{variables}} de lista blanca, correlativo por prefijo y anio, y snapshot
 * inmutable de cada documento emitido.
 *
 * Seguridad: los valores se escapan al reemplazar, y el HTML de la plantilla se
 * sanea al guardarla (etiquetas de texto y tablas, sin atributos salvo class).
 */
final class MgDocumento
{
    /** Variables permitidas por plantilla. */
    public const VARIABLES = [
        'CARTA_ASIGNACION_TUTOR' => ['numero', 'fecha_larga', 'ciudad', 'firma', 'destinatario_nombre', 'estudiante_nombre',
            'registro_universitario', 'carrera', 'modalidad', 'tema', 'tutor_nombre', 'cohorte', 'referencia_decanatura'],
        'CITACION_TRIBUNAL' => ['numero', 'fecha_larga', 'ciudad', 'firma', 'destinatario_nombre', 'estudiante_nombre',
            'registro_universitario', 'carrera', 'modalidad', 'tema', 'tutor_nombre', 'cohorte', 'etapa', 'fecha_defensa',
            'hora_inicio', 'hora_fin', 'ambiente', 'tribunales'],
        'CITACION_ESTUDIANTE' => ['numero', 'fecha_larga', 'ciudad', 'firma', 'destinatario_nombre', 'estudiante_nombre',
            'registro_universitario', 'carrera', 'modalidad', 'tema', 'tutor_nombre', 'cohorte', 'etapa', 'fecha_defensa',
            'hora_inicio', 'hora_fin', 'ambiente', 'tribunales'],
    ];

    private const ETIQUETAS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'table',
        'thead', 'tbody', 'tr', 'th', 'td', 'span', 'div', 'hr', 'small', 'blockquote'];

    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function plantillas(): array
    {
        return Database::connection()->query(
            "SELECT p.*, CONCAT(u.nombre, ' ', u.apellido) AS actualizado
             FROM plantillas_documento_mg p LEFT JOIN usuarios u ON u.id_usuario = p.actualizado_por ORDER BY p.nombre"
        )->fetchAll();
    }

    public function plantilla(string $codigo): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM plantillas_documento_mg WHERE codigo = :c');
        $statement->execute(['c' => $codigo]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function plantillaPorId(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM plantillas_documento_mg WHERE id_plantilla = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** Guarda una version nueva; los documentos ya emitidos no cambian (tienen snapshot). */
    public function actualizarPlantilla(PDO $pdo, int $id, string $nombre, string $cuerpo, int $userId): void
    {
        $pdo->prepare(
            'UPDATE plantillas_documento_mg SET nombre = :n, cuerpo_html = :c, version = version + 1, actualizado_por = :u, fecha_actualizacion = NOW()
             WHERE id_plantilla = :id'
        )->execute(['n' => $nombre, 'c' => $cuerpo, 'u' => $userId, 'id' => $id]);
    }

    /** Variables {{x}} usadas en el texto que no estan en la lista blanca. */
    public static function variablesDesconocidas(string $codigo, string $cuerpo): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $cuerpo, $coincidencias);

        return array_values(array_diff(array_unique($coincidencias[1]), self::VARIABLES[$codigo] ?? []));
    }

    /** Deja solo etiquetas de la lista blanca y el atributo class (sin scripts, estilos ni eventos). */
    public static function sanear(string $html): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="raiz">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $raiz = $dom->getElementById('raiz');
        if ($raiz === null) {
            return '';
        }
        self::sanearNodo($raiz);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= $dom->saveHTML($hijo);
        }

        return trim($salida);
    }

    private static function sanearNodo(DOMNode $nodo): void
    {
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            if ($hijo instanceof DOMElement) {
                $etiqueta = strtolower($hijo->tagName);
                if (!in_array($etiqueta, self::ETIQUETAS, true)) {
                    // script/style y similares se eliminan con su contenido; el resto conserva el texto.
                    if (in_array($etiqueta, ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math'], true)) {
                        $nodo->removeChild($hijo);
                        continue;
                    }
                    self::sanearNodo($hijo);
                    while ($hijo->firstChild) {
                        $nodo->insertBefore($hijo->firstChild, $hijo);
                    }
                    $nodo->removeChild($hijo);
                    continue;
                }
                foreach (iterator_to_array($hijo->attributes) as $atributo) {
                    $valido = $atributo->name === 'class' && preg_match('/^[a-zA-Z0-9_\- ]*$/', $atributo->value);
                    if (!$valido) {
                        $hijo->removeAttribute($atributo->name);
                    }
                }
                self::sanearNodo($hijo);
            } elseif ($hijo instanceof DOMComment || $hijo instanceof DOMProcessingInstruction) {
                $nodo->removeChild($hijo);
            }
        }
    }

    /** Reemplaza {{variables}} de la lista blanca por sus valores escapados. */
    public static function render(string $codigo, string $cuerpo, array $valores): string
    {
        $permitidas = self::VARIABLES[$codigo] ?? [];

        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static function (array $m) use ($permitidas, $valores): string {
            if (!in_array($m[1], $permitidas, true)) {
                return '';
            }
            $valor = trim((string) ($valores[$m[1]] ?? ''));

            return htmlspecialchars($valor !== '' ? $valor : '—', ENT_QUOTES, 'UTF-8');
        }, $cuerpo);
    }

    public static function fechaLarga(string $fecha): string
    {
        $t = strtotime($fecha);

        return (int) date('j', $t) . ' de ' . self::MESES[(int) date('n', $t)] . ' de ' . date('Y', $t);
    }

    /**
     * Siguiente correlativo del prefijo en el anio: MG-CAT-2026-0001. El formato
     * real es [PENDIENTE] (pregunta 4). Atomico: INSERT ... ON DUPLICATE KEY con
     * LAST_INSERT_ID, dentro de la transaccion del documento.
     */
    public function siguienteNumero(PDO $pdo, string $prefijo, int $anio): string
    {
        $pdo->prepare(
            'INSERT INTO contadores_documento_mg (prefijo, anio, ultimo_numero) VALUES (:p, :a, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE ultimo_numero = LAST_INSERT_ID(ultimo_numero + 1)'
        )->execute(['p' => $prefijo, 'a' => $anio]);
        $numero = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();

        return sprintf('MG-%s-%d-%04d', $prefijo, $anio, $numero);
    }

    public function crear(PDO $pdo, array $data, int $userId): int
    {
        $pdo->prepare(
            'INSERT INTO documentos_generados_mg (id_plantilla, version_plantilla, codigo, id_expediente, id_asignacion, id_defensa, destinatario, numero, contenido_snapshot, generado_por)
             VALUES (:pl, :v, :c, :e, :a, :d, :dest, :n, :s, :u)'
        )->execute([
            'pl' => $data['id_plantilla'], 'v' => $data['version_plantilla'], 'c' => $data['codigo'], 'e' => $data['id_expediente'],
            'a' => $data['id_asignacion'] ?? null, 'd' => $data['id_defensa'] ?? null, 'dest' => mb_substr($data['destinatario'], 0, 200),
            'n' => $data['numero'], 's' => $data['contenido_snapshot'], 'u' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT d.*, p.nombre AS plantilla, CONCAT(u.nombre, ' ', u.apellido) AS generado
             FROM documentos_generados_mg d INNER JOIN plantillas_documento_mg p ON p.id_plantilla = d.id_plantilla
             LEFT JOIN usuarios u ON u.id_usuario = d.generado_por WHERE d.id_documento = :id"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** Documentos de un expediente o, con $defensaId, de una defensa. */
    public function listar(?int $expedienteId = null, ?int $defensaId = null, ?array $ids = null): array
    {
        $where = [];
        $params = [];
        if ($expedienteId !== null) {
            $where[] = 'd.id_expediente = :e';
            $params['e'] = $expedienteId;
        }
        if ($defensaId !== null) {
            $where[] = 'd.id_defensa = :d';
            $params['d'] = $defensaId;
        }
        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if ($ids === []) {
                return [];
            }
            $where[] = 'd.id_documento IN (' . implode(',', $ids) . ')';
        }
        $statement = Database::connection()->prepare(
            "SELECT d.id_documento, d.id_expediente, d.codigo, d.numero, d.destinatario, d.id_defensa, d.id_asignacion, d.fecha_generacion, d.contenido_snapshot,
                    p.nombre AS plantilla, CONCAT(u.nombre, ' ', u.apellido) AS generado
             FROM documentos_generados_mg d INNER JOIN plantillas_documento_mg p ON p.id_plantilla = d.id_plantilla
             LEFT JOIN usuarios u ON u.id_usuario = d.generado_por"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY d.fecha_generacion DESC, d.id_documento DESC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
