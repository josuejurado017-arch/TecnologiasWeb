<?php

declare(strict_types=1);

/**
 * Catalogo de tipos de tutoria (db/043). El nombre es libre; la duracion maxima
 * limita los periodos del tipo (vacio = sin tope). Un tipo con periodos no se
 * elimina (es historial): se desactiva, y solo si no tiene un periodo activo.
 */
final class TiposTutoriaController
{
    /** Tope razonable para un periodo: un año. */
    public const DURACION_MAXIMA_PERMITIDA = 366;

    private TipoTutoria $model;

    public function __construct()
    {
        $this->model = new TipoTutoria();
    }

    public function index(): array
    {
        return $this->model->all();
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
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
            return [$data, ['No se pudo guardar el tipo de tutoría.']];
        }
    }

    /**
     * La duracion nueva aplica a los periodos que se creen o editen despues; no
     * invalida los periodos que ya existen.
     */
    public function update(int $id, array $input): array
    {
        if ($this->model->findById($id) === null) {
            return [[], ['El tipo de tutoría no existe.']];
        }
        $data = $this->normalize($input);
        $errors = $this->validate($data, $id);
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->update($id, $data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo actualizar el tipo de tutoría.']];
        }
    }

    /** $accion: activar, desactivar o eliminar. */
    public function cambiarEstado(int $id, string $accion): ?string
    {
        $tipo = $this->model->findById($id);
        if ($tipo === null) {
            return 'El tipo de tutoría no existe.';
        }

        try {
            if ($accion === 'activar') {
                $this->model->setEstado($id, 'activo');
                return null;
            }
            if ($accion === 'desactivar') {
                if ($this->model->countPeriodos($id, 'activa') > 0) {
                    return 'El tipo "' . $tipo['nombre'] . '" tiene un período activo. Ciérralo antes de desactivar el tipo.';
                }
                $this->model->setEstado($id, 'inactivo');
                return null;
            }
            if ($accion === 'eliminar') {
                if ($this->model->countPeriodos($id) > 0) {
                    return 'El tipo "' . $tipo['nombre'] . '" tiene períodos registrados y no se puede eliminar. Puedes desactivarlo.';
                }
                $this->model->delete($id);
                return null;
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se pudo actualizar el tipo de tutoría.';
        }

        return 'Acción no válida.';
    }

    private function normalize(array $input): array
    {
        $duracion = trim((string) ($input['duracion_max_dias'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? ''));

        return [
            'nombre' => normalize_name((string) ($input['nombre'] ?? '')),
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            // Vacio = sin tope. Un valor no numerico queda como texto para que validate() lo rechace.
            'duracion_max_dias' => $duracion === '' ? null : (ctype_digit($duracion) ? (int) $duracion : $duracion),
        ];
    }

    private function validate(array $data, ?int $ignoreId): array
    {
        $errors = [];

        $nameError = validation_label((string) $data['nombre'], 'nombre del tipo', 80);
        if ($nameError !== null) {
            $errors[] = $nameError;
        } elseif ($this->model->nameExists((string) $data['nombre'], $ignoreId)) {
            $errors[] = 'Ya existe un tipo de tutoría con ese nombre.';
        }

        if ($data['descripcion'] !== null
            && (mb_strlen((string) $data['descripcion']) > 255 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', (string) $data['descripcion']))) {
            $errors[] = 'La descripción debe tener como máximo 255 caracteres válidos.';
        }

        $duracion = $data['duracion_max_dias'];
        if ($duracion !== null && (!is_int($duracion) || $duracion < 1 || $duracion > self::DURACION_MAXIMA_PERMITIDA)) {
            $errors[] = 'La duración máxima debe ser un número de días entre 1 y ' . self::DURACION_MAXIMA_PERMITIDA . ', o quedar vacía (sin tope).';
        }

        return $errors;
    }
}
