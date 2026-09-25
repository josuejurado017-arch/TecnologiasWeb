<?php

declare(strict_types=1);

/**
 * Ciclo de vida de los periodos (db/031): BORRADOR -> ACTIVA -> CERRADA.
 *
 *   borrador: se edita todo, se activa o se elimina (si no tiene datos).
 *   activa:   se editan solo configuraciones compatibles (nombre, extender la fecha
 *             de fin, cupos y modalidad "ambas" para grupos nuevos) y se cierra.
 *   cerrada:  historial. Solo lectura, reportes y observaciones administrativas.
 *
 * No hay transiciones hacia atras: activar no degrada a otro periodo (hay que
 * cerrar el activo primero) y un periodo cerrado no se reabre.
 */
final class PeriodosController
{
    /**
     * Regla institucional: la tutoria ocupa el ultimo mes del semestre (julio para
     * la Gestion I, enero para la Gestion II). Seis semanas dan margen para
     * feriados o una semana de inscripcion.
     */
    public const DURACION_MAXIMA_DIAS = 42;

    public const ESTADOS = ['borrador' => 'Borrador', 'activa' => 'Activo', 'cerrada' => 'Cerrado'];

    private Periodo $model;

    public function __construct()
    {
        $this->model = new Periodo();
    }

    public function index(): array
    {
        return $this->model->all();
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function observaciones(int $id): array
    {
        return $this->model->observaciones($id);
    }

    public function impactoCierre(int $id): array
    {
        return $this->model->impactoCierre($id);
    }

    /** Campos que se pueden editar segun el estado (la vista los bloquea igual). */
    public function camposEditables(array $periodo): array
    {
        if ($periodo['estado'] === 'cerrada') {
            return [];
        }
        if ($periodo['estado'] === 'activa') {
            $campos = ['nombre', 'fecha_fin', 'cupo_min_grupo', 'cupo_max_default', 'max_grupos_tutor', 'modalidad_ambas'];
            if ($this->model->countGrupos((int) $periodo['id_periodo']) === 0) {
                $campos[] = 'fecha_inicio';
            }
            return $campos;
        }

        return ['nombre', 'fecha_inicio', 'fecha_fin', 'cupo_min_grupo', 'cupo_max_default', 'max_grupos_tutor', 'modalidad_ambas'];
    }

    public function store(array $input): array
    {
        $data = $this->normalize($input);
        $errors = $this->validate($data, null);
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->create($data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo guardar el período.']];
        }
    }

    /**
     * Guarda los campos editables en el estado actual; el resto conserva su valor.
     * En un periodo activo la fecha de fin solo se extiende, y al extenderla los
     * grupos aprobados reciben las sesiones de las semanas nuevas.
     */
    public function update(int $id, array $input): array
    {
        $actual = $this->model->findById($id);
        if ($actual === null) {
            return [[], ['El período no existe.']];
        }
        if ($actual['estado'] === 'cerrada') {
            return [$actual, ['El período está cerrado: es historial y no se puede editar. Puedes agregar observaciones.']];
        }

        $editables = $this->camposEditables($actual);
        $propuesto = $this->normalize($input);
        $data = [];
        foreach (['nombre', 'fecha_inicio', 'fecha_fin', 'cupo_min_grupo', 'cupo_max_default', 'max_grupos_tutor', 'modalidad_ambas'] as $campo) {
            $data[$campo] = in_array($campo, $editables, true) ? $propuesto[$campo] : $actual[$campo];
        }
        $data['estado'] = $actual['estado'];

        $errors = $this->validate($data, $id);
        if ($actual['estado'] === 'activa') {
            if ($data['fecha_fin'] < $actual['fecha_fin']) {
                $errors[] = 'En un período activo la fecha de fin solo se puede extender (actual: ' . $actual['fecha_fin'] . ').';
            }
            if ($data['fecha_fin'] < date('Y-m-d')) {
                $errors[] = 'La fecha de fin de un período activo no puede quedar en el pasado.';
            }
        }
        if ($errors) {
            return [$data, $errors];
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            if ($this->model->lockEstado($id) !== $actual['estado']) {
                $connection->rollBack();
                return [$data, ['El estado del período cambió mientras editabas. Recarga la página.']];
            }
            $this->model->update($id, $data);

            // Semanas nuevas de un periodo activo: calendario para los grupos ya aprobados.
            if ($actual['estado'] === 'activa' && $data['fecha_fin'] > $actual['fecha_fin']) {
                $desde = (new DateTimeImmutable($actual['fecha_fin']))->modify('+1 day')->format('Y-m-d');
                $grupos = new Grupo();
                foreach ($this->model->gruposConCalendario($id) as $grupoId) {
                    $grupos->generateSessions($grupoId, $grupos->dias($grupoId), max($desde, date('Y-m-d')), $data['fecha_fin']);
                }
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());
            return [$data, ['No se pudo actualizar el período.']];
        }

        return [$data, []];
    }

    /** Borrador -> activo. Si ya hay un periodo activo, hay que cerrarlo primero. */
    public function activate(int $id, int $userId): ?string
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $estado = $this->model->lockEstado($id);
            if ($estado === null) {
                $connection->rollBack();
                return 'El período no existe.';
            }
            if ($estado !== 'borrador') {
                $connection->rollBack();
                return $estado === 'cerrada'
                    ? 'Un período cerrado es historial y no se reactiva.'
                    : 'El período ya está activo.';
            }
            $otro = $this->model->lockOtroActivo($id);
            if ($otro !== null) {
                $connection->rollBack();
                return 'Ya hay un período activo ("' . $otro['nombre'] . '"). Ciérralo antes de activar otro.';
            }
            $periodo = $this->model->findById($id);
            if ((string) $periodo['fecha_fin'] < date('Y-m-d')) {
                $connection->rollBack();
                return 'La fecha de fin del período ya pasó. Corrígela antes de activarlo.';
            }
            $this->model->marcarActiva($id, $userId);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo activar el período.';
        }

        return null;
    }

    /** Activo -> cerrado, con los efectos de Periodo::aplicarCierre y avisos. */
    public function cerrar(int $id, int $userId): ?string
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $estado = $this->model->lockEstado($id);
            if ($estado !== 'activa') {
                $connection->rollBack();
                return $estado === 'cerrada' ? 'El período ya estaba cerrado.' : 'Solo se puede cerrar el período activo.';
            }
            $resumen = $this->model->impactoCierre($id);
            $this->model->aplicarCierre($id, $userId, $resumen);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log('Cierre periodo ' . $id . ': ' . $exception->getMessage());
            return 'No se pudo cerrar el período.';
        }

        try {
            $periodo = $this->model->findById($id);
            (new Notificacion())->notifyPeriodoCerrado($connection, $id, (string) $periodo['nombre'], (string) $periodo['evaluaciones_hasta']);
        } catch (Throwable $exception) {
            error_log('Notificacion cierre periodo: ' . $exception->getMessage());
        }

        return null;
    }

    /** Solo un borrador sin datos asociados. */
    public function delete(int $id): ?string
    {
        $periodo = $this->model->findById($id);
        if ($periodo === null) {
            return 'El período no existe.';
        }
        if ($periodo['estado'] !== 'borrador') {
            return 'Solo se puede eliminar un período en borrador. Los períodos activos o cerrados conservan su historial.';
        }
        if ($this->model->countDependencias($id) > 0) {
            return 'El período tiene grupos, demanda u observaciones registradas y no se puede eliminar.';
        }

        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se pudo eliminar el período: tiene información asociada.';
        }
    }

    public function agregarObservacion(int $id, string $texto, int $userId): ?string
    {
        if ($this->model->findById($id) === null) {
            return 'El período no existe.';
        }
        $texto = trim($texto);
        if (mb_strlen($texto) < 4 || mb_strlen($texto) > 1000) {
            return 'La observación debe tener entre 4 y 1000 caracteres.';
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $texto)) {
            return 'La observación contiene caracteres no válidos.';
        }

        try {
            $this->model->agregarObservacion($id, $texto, $userId);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se pudo guardar la observación.';
        }
    }

    private function normalize(array $input): array
    {
        $minGroup = filter_var($input['cupo_min_grupo'] ?? null, FILTER_VALIDATE_INT);
        $maxDefault = filter_var($input['cupo_max_default'] ?? null, FILTER_VALIDATE_INT);
        $maxTutor = filter_var($input['max_grupos_tutor'] ?? null, FILTER_VALIDATE_INT);
        $modalidadAmbas = (string) ($input['modalidad_ambas'] ?? 'virtual');

        return [
            'nombre' => normalize_name((string) ($input['nombre'] ?? '')),
            'fecha_inicio' => trim((string) ($input['fecha_inicio'] ?? '')),
            'fecha_fin' => trim((string) ($input['fecha_fin'] ?? '')),
            'cupo_min_grupo' => $minGroup !== false ? $minGroup : 3,
            'cupo_max_default' => $maxDefault !== false ? $maxDefault : 20,
            // Tope de grupos por tutor en el periodo, siempre en turnos distintos (db/037).
            'max_grupos_tutor' => $maxTutor !== false ? $maxTutor : 2,
            // Modalidad que el motor da a un grupo cuando el tutor acepta ambas y la materia es libre.
            'modalidad_ambas' => in_array($modalidadAmbas, ['virtual', 'presencial'], true) ? $modalidadAmbas : 'virtual',
        ];
    }

    private function validate(array $data, ?int $ignoreId): array
    {
        $errors = [];

        $nameError = validation_label((string) $data['nombre'], 'nombre del período', 120);
        if ($nameError !== null) {
            $errors[] = $nameError;
        } elseif ($this->model->nameExists((string) $data['nombre'], $ignoreId)) {
            $errors[] = 'Ya existe un período con ese nombre.';
        }

        $start = $this->parseDate((string) $data['fecha_inicio']);
        $end = $this->parseDate((string) $data['fecha_fin']);
        if ($start === null) {
            $errors[] = 'La fecha de inicio no es válida.';
        }
        if ($end === null) {
            $errors[] = 'La fecha de fin no es válida.';
        }
        if ($start !== null && $end !== null && $end < $start) {
            $errors[] = 'La fecha de fin debe ser posterior o igual a la de inicio.';
        } elseif ($start !== null && $end !== null && $start->diff($end)->days + 1 > self::DURACION_MAXIMA_DIAS) {
            $errors[] = 'Un período de tutoría dura como máximo ' . intdiv(self::DURACION_MAXIMA_DIAS, 7)
                . ' semanas: es el último mes del semestre (julio o enero).';
        }

        $maxTutor = (int) $data['max_grupos_tutor'];
        if ($maxTutor < 1 || $maxTutor > 2) {
            $errors[] = 'Los grupos por tutor deben estar entre 1 y 2 por período.';
        }

        $min = (int) $data['cupo_min_grupo'];
        $max = (int) $data['cupo_max_default'];
        if ($min < 1 || $min > 100) {
            $errors[] = 'El cupo mínimo por grupo debe estar entre 1 y 100.';
        }
        if ($max < 1 || $max > 200) {
            $errors[] = 'El cupo máximo por grupo debe estar entre 1 y 200.';
        }
        if ($max < $min) {
            $errors[] = 'El cupo máximo no puede ser menor que el cupo mínimo.';
        }

        return $errors;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
