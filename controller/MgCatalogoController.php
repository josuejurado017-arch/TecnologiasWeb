<?php

declare(strict_types=1);

/**
 * Parametros (HU-020) y catalogos (HU-022) de Modalidades de Grado. Todo cambio
 * de parametro queda en bitacora_mg.
 */
final class MgCatalogoController
{
    /** Parametros enteros que pueden quedar vacios ("sin maximo"). */
    private const PUEDEN_QUEDAR_VACIOS = ['tutor_max_estudiantes'];

    private MgCatalogo $catalogo;

    public function __construct()
    {
        $this->catalogo = new MgCatalogo();
    }

    // ------------------------------------------------------------------
    // Parametros
    // ------------------------------------------------------------------

    public function actualizarParametro(string $clave, string $valor, int $userId): ?string
    {
        $modelo = new MgParametro();
        $parametro = $modelo->find($clave);
        if ($parametro === null) {
            return 'El parámetro no existe.';
        }
        $valor = trim($valor);
        if ($parametro['tipo'] === 'entero') {
            if ($valor === '' && !in_array($clave, self::PUEDEN_QUEDAR_VACIOS, true)) {
                return 'El parámetro "' . $clave . '" no puede quedar vacío.';
            }
            if ($valor !== '' && (!ctype_digit($valor) || (int) $valor > 100000)) {
                return 'El parámetro "' . $clave . '" debe ser un número entero entre 0 y 100000.';
            }
        } elseif ($valor === '' || mb_strlen($valor) > 100 || preg_match('/[\x00-\x1F\x7F]/', $valor)) {
            return 'El texto del parámetro "' . $clave . '" debe tener entre 1 y 100 caracteres válidos.';
        }
        $nuevo = $valor === '' ? null : $valor;
        if ($nuevo === $parametro['valor']) {
            return null;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $modelo->actualizar($pdo, $clave, $nuevo, $userId);
            MgBitacora::registrar($pdo, 'parametro_actualizado', 'parametros_mg', $clave, ['valor' => $parametro['valor']], ['valor' => $nuevo]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo guardar el parámetro.';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Cohortes
    // ------------------------------------------------------------------

    public function guardarCohorte(?int $id, array $input): array
    {
        $data = [
            'codigo' => strtoupper(trim((string) ($input['codigo'] ?? ''))),
            'nombre' => preg_replace('/\s+/u', ' ', trim((string) ($input['nombre'] ?? ''))) ?? '',
            'fecha_inicio' => trim((string) ($input['fecha_inicio'] ?? '')),
            'fecha_fin' => trim((string) ($input['fecha_fin'] ?? '')) ?: null,
        ];
        $errors = [];
        if (!preg_match('/^[A-Z0-9][A-Z0-9_\-]{1,29}$/', $data['codigo'])) {
            $errors[] = 'El código debe tener de 2 a 30 caracteres: letras, números, guion o guion bajo (ej. G1-2026-03).';
        } elseif ($this->catalogo->cohorteExiste('codigo', $data['codigo'], $id)) {
            $errors[] = 'Ya existe una cohorte con ese código.';
        }
        $nombreError = validation_label($data['nombre'], 'nombre de la cohorte', 120);
        if ($nombreError !== null) {
            $errors[] = $nombreError;
        } elseif ($this->catalogo->cohorteExiste('nombre', $data['nombre'], $id)) {
            $errors[] = 'Ya existe una cohorte con ese nombre.';
        }
        if (!mg_fecha_valida($data['fecha_inicio'])) {
            $errors[] = 'La fecha de inicio no es válida.';
        }
        if ($data['fecha_fin'] !== null && (!mg_fecha_valida($data['fecha_fin']) || $data['fecha_fin'] < $data['fecha_inicio'])) {
            $errors[] = 'La fecha de fin debe ser válida y posterior o igual a la de inicio.';
        }
        if ($errors) {
            return [$data, $errors];
        }

        try {
            if ($id === null) {
                $this->catalogo->crearCohorte($data);
            } else {
                $this->catalogo->actualizarCohorte($id, $data);
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo guardar la cohorte.']];
        }

        return [$data, []];
    }

    public function cambiarEstadoCohorte(int $id, bool $activa): ?string
    {
        if ($this->catalogo->cohorte($id) === null) {
            return 'La cohorte no existe.';
        }
        $this->catalogo->setCohorteActiva($id, $activa);

        return null;
    }

    public function guardarHito(int $cohorteId, ?int $hitoId, array $input): array
    {
        $avance = trim((string) ($input['avance_esperado_pct'] ?? ''));
        $orden = filter_var($input['orden'] ?? 1, FILTER_VALIDATE_INT);
        $data = [
            'id_cohorte' => $cohorteId,
            'etapa' => (string) ($input['etapa'] ?? ''),
            'tipo' => (string) ($input['tipo'] ?? ''),
            'nombre' => preg_replace('/\s+/u', ' ', trim((string) ($input['nombre'] ?? ''))) ?? '',
            'orden' => $orden !== false ? $orden : 0,
            'fecha_limite' => trim((string) ($input['fecha_limite'] ?? '')),
            'avance_esperado_pct' => $avance === '' ? null : $avance,
        ];
        $errors = [];
        if ($this->catalogo->cohorte($cohorteId) === null) {
            $errors[] = 'La cohorte no existe.';
        }
        if (!isset(MgCatalogo::ETAPAS_HITO[$data['etapa']])) {
            $errors[] = 'Seleccione una etapa válida.';
        }
        if (!isset(MgCatalogo::TIPOS_HITO[$data['tipo']])) {
            $errors[] = 'Seleccione un tipo de hito válido.';
        }
        $nombreError = validation_label($data['nombre'], 'nombre del hito', 120);
        if ($nombreError !== null) {
            $errors[] = $nombreError;
        }
        if ($data['orden'] < 1 || $data['orden'] > 99) {
            $errors[] = 'El orden debe estar entre 1 y 99.';
        }
        if (!mg_fecha_valida($data['fecha_limite'])) {
            $errors[] = 'La fecha límite no es válida.';
        }
        if ($data['avance_esperado_pct'] !== null) {
            if (!ctype_digit((string) $data['avance_esperado_pct']) || (int) $data['avance_esperado_pct'] > 100) {
                $errors[] = 'El avance esperado debe ser un porcentaje entre 0 y 100, o quedar vacío.';
            } else {
                $data['avance_esperado_pct'] = (int) $data['avance_esperado_pct'];
            }
        }
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->catalogo->guardarHito($hitoId, $data);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo guardar el hito.']];
        }

        return [$data, []];
    }

    public function eliminarHito(int $hitoId): ?string
    {
        if ($this->catalogo->hito($hitoId) === null) {
            return 'El hito no existe.';
        }
        try {
            $this->catalogo->eliminarHito($hitoId);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'El hito tiene información asociada y no se puede eliminar.';
        }

        return null;
    }

    public function cambiarEstadoModalidad(int $id, bool $activa): ?string
    {
        if ($this->catalogo->modalidad($id) === null) {
            return 'La modalidad no existe.';
        }
        $this->catalogo->setModalidadActiva($id, $activa);

        return null;
    }
}
