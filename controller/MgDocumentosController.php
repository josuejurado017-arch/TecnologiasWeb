<?php

declare(strict_types=1);

/**
 * Generacion documental de Modalidades de Grado (HU-027 carta de asignacion,
 * HU-030 citaciones) y edicion de plantillas. Cada documento recibe un numero
 * correlativo y guarda un snapshot: reimprimir muestra lo que se emitio aunque la
 * plantilla cambie despues.
 */
final class MgDocumentosController
{
    private const SALTO = '<div class="doc-salto"></div>';

    private MgDocumento $documentos;

    public function __construct()
    {
        $this->documentos = new MgDocumento();
    }

    /** Variables comunes a partir de un expediente (MgExpediente::find). */
    private function variablesExpediente(array $expediente): array
    {
        return [
            'ciudad' => MgParametro::texto('institucion_ciudad', 'Tarija'),
            'firma' => MgParametro::texto('firma_coordinacion', 'Coordinación de Modalidades de Grado'),
            'fecha_larga' => MgDocumento::fechaLarga(date('Y-m-d')),
            'estudiante_nombre' => $expediente['estudiante'],
            'registro_universitario' => $expediente['registro_universitario'],
            'carrera' => $expediente['carrera'],
            'modalidad' => $expediente['modalidad'],
            'tema' => $expediente['titulo_trabajo'],
            'cohorte' => $expediente['cohorte'],
            'tutor_nombre' => $expediente['tutor'],
        ];
    }

    /**
     * Carta de asignacion de tutor: una hoja para el tutor y otra para el estudiante,
     * con el mismo numero. Corre dentro de la transaccion de quien la llama.
     */
    public function generarCartaAsignacion(PDO $pdo, int $asignacionId, int $userId): int
    {
        $asignacion = (new MgAsignacion())->find($asignacionId);
        $expediente = $asignacion !== null ? (new MgExpediente())->find((int) $asignacion['id_expediente']) : null;
        $plantilla = $this->documentos->plantilla('CARTA_ASIGNACION_TUTOR');
        if ($asignacion === null || $expediente === null || $plantilla === null) {
            throw new RuntimeException('Faltan datos para generar la carta de asignación.');
        }

        $numero = $this->documentos->siguienteNumero($pdo, $plantilla['prefijo'], (int) date('Y'));
        $valores = $this->variablesExpediente($expediente) + [
            'numero' => $numero,
            'tutor_nombre' => $asignacion['tutor'],
            'referencia_decanatura' => $asignacion['referencia_decanatura'],
        ];
        $hojas = [];
        foreach ([$asignacion['tutor'], $expediente['estudiante']] as $destinatario) {
            $hojas[] = MgDocumento::render('CARTA_ASIGNACION_TUTOR', $plantilla['cuerpo_html'], ['destinatario_nombre' => $destinatario] + $valores);
        }

        return $this->documentos->crear($pdo, [
            'id_plantilla' => $plantilla['id_plantilla'], 'version_plantilla' => $plantilla['version'], 'codigo' => $plantilla['codigo'],
            'id_expediente' => $expediente['id_expediente'], 'id_asignacion' => $asignacionId,
            'destinatario' => $asignacion['tutor'] . ' / ' . $expediente['estudiante'],
            'numero' => $numero, 'contenido_snapshot' => implode(self::SALTO, $hojas),
        ], $userId);
    }

    /**
     * Citaciones de una defensa programada: una por tribunal vigente y una para el
     * estudiante. Devuelve [ids de documentos, error].
     */
    public function generarCitaciones(int $defensaId, int $userId): array
    {
        $defensa = (new MgDefensa())->find($defensaId);
        if ($defensa === null) {
            return [[], 'La defensa no existe.'];
        }
        if ($defensa['estado'] !== 'programada') {
            return [[], 'Solo se generan citaciones de una defensa programada.'];
        }
        $expediente = (new MgExpediente())->find((int) $defensa['id_expediente']);
        $tribunales = (new MgTribunal())->vigentes((int) $defensa['id_expediente'], (string) $defensa['etapa']);
        if ($tribunales === []) {
            return [[], 'La defensa no tiene tribunales asignados para ' . MgTribunal::ETAPAS[$defensa['etapa']] . '.'];
        }
        $plantillaTribunal = $this->documentos->plantilla('CITACION_TRIBUNAL');
        $plantillaEstudiante = $this->documentos->plantilla('CITACION_ESTUDIANTE');

        $valores = $this->variablesExpediente($expediente) + [
            'etapa' => MgTribunal::ETAPAS[$defensa['etapa']],
            'fecha_defensa' => MgDocumento::fechaLarga((string) $defensa['fecha']),
            'hora_inicio' => substr((string) $defensa['hora_inicio'], 0, 5),
            'hora_fin' => substr((string) $defensa['hora_fin'], 0, 5),
            'ambiente' => $defensa['ambiente'],
            'tribunales' => implode(', ', array_column($tribunales, 'docente')),
        ];

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $ids = [];
            $destinos = [];
            foreach ($tribunales as $tribunal) {
                $destinos[] = [$plantillaTribunal, $tribunal['docente']];
            }
            $destinos[] = [$plantillaEstudiante, $expediente['estudiante']];
            foreach ($destinos as [$plantilla, $destinatario]) {
                $numero = $this->documentos->siguienteNumero($pdo, $plantilla['prefijo'], (int) date('Y'));
                $html = MgDocumento::render($plantilla['codigo'], $plantilla['cuerpo_html'], ['numero' => $numero, 'destinatario_nombre' => $destinatario] + $valores);
                $ids[] = $this->documentos->crear($pdo, [
                    'id_plantilla' => $plantilla['id_plantilla'], 'version_plantilla' => $plantilla['version'], 'codigo' => $plantilla['codigo'],
                    'id_expediente' => $expediente['id_expediente'], 'id_defensa' => $defensaId,
                    'destinatario' => $destinatario, 'numero' => $numero, 'contenido_snapshot' => $html,
                ], $userId);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Citaciones MG: ' . $exception->getMessage());
            return [[], 'No se pudieron generar las citaciones.'];
        }

        return [$ids, null];
    }

    /** Citaciones de todas las defensas programadas del dia que aun no las tienen. */
    public function generarCitacionesDelDia(string $fecha, int $userId): array
    {
        $ids = [];
        $errores = [];
        foreach ((new MgDefensa())->agenda($fecha, $fecha, 'programada') as $defensa) {
            if ((int) $defensa['citaciones'] > 0) {
                continue;
            }
            [$nuevos, $error] = $this->generarCitaciones((int) $defensa['id_defensa'], $userId);
            if ($error !== null) {
                $errores[] = $defensa['estudiante'] . ': ' . $error;
            }
            $ids = array_merge($ids, $nuevos);
        }

        return [$ids, $errores];
    }

    public function guardarPlantilla(int $id, array $input, int $userId): array
    {
        $plantilla = $this->documentos->plantillaPorId($id);
        if ($plantilla === null) {
            return [[], ['La plantilla no existe.']];
        }
        $nombre = preg_replace('/\s+/u', ' ', trim((string) ($input['nombre'] ?? ''))) ?? '';
        $cuerpo = MgDocumento::sanear((string) ($input['cuerpo_html'] ?? ''));
        $data = ['nombre' => $nombre, 'cuerpo_html' => $cuerpo] + $plantilla;
        $errors = [];
        if (($error = validation_label($nombre, 'nombre de la plantilla', 120)) !== null) {
            $errors[] = $error;
        }
        if ($cuerpo === '' || mb_strlen($cuerpo) > 60000) {
            $errors[] = 'El contenido de la plantilla no puede quedar vacío ni superar 60.000 caracteres.';
        }
        if ($desconocidas = MgDocumento::variablesDesconocidas($plantilla['codigo'], $cuerpo)) {
            $errors[] = 'Variables no permitidas: {{' . implode('}}, {{', $desconocidas) . '}}.';
        }
        if ($errors) {
            return [$data, $errors];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->documentos->actualizarPlantilla($pdo, $id, $nombre, $cuerpo, $userId);
            MgBitacora::registrar($pdo, 'plantilla_actualizada', 'plantillas_documento_mg', $id,
                ['nombre' => $plantilla['nombre'], 'version' => (int) $plantilla['version'], 'cuerpo_html' => $plantilla['cuerpo_html']],
                ['nombre' => $nombre, 'version' => (int) $plantilla['version'] + 1, 'cuerpo_html' => $cuerpo]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return [$data, ['No se pudo guardar la plantilla.']];
        }

        return [$data, []];
    }
}
