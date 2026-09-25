<?php

declare(strict_types=1);

/**
 * Catalogo de espacios de tutoria (db/029). Es configuracion ligera: nombre,
 * modalidad y un predeterminado por modalidad. No hay capacidad ni ocupacion, y
 * no se eliminan (los grupos los referencian): solo se desactivan.
 */
final class EspaciosController
{
    private EspacioTutoria $model;

    public function __construct()
    {
        $this->model = new EspacioTutoria();
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
            return [$data, ['No se pudo guardar el espacio.']];
        }
    }

    /** La modalidad de un espacio existente no cambia: se conserva la guardada. */
    public function update(int $id, array $input): array
    {
        $actual = $this->model->findById($id);
        if ($actual === null) {
            return [[], ['El espacio no existe.']];
        }
        $data = $this->normalize($input);
        $data['modalidad'] = $actual['modalidad'];
        $errors = $this->validate($data, $id);
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->update($id, $data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo actualizar el espacio.']];
        }
    }

    /** $accion: activar, desactivar o predeterminar. */
    public function cambiarEstado(int $id, string $accion): ?string
    {
        $espacio = $this->model->findById($id);
        if ($espacio === null) {
            return 'El espacio no existe.';
        }

        try {
            if ($accion === 'activar') {
                $this->model->setEstado($id, 'activo');
            } elseif ($accion === 'desactivar') {
                if ((int) $espacio['predeterminado'] === 1) {
                    return 'No puedes desactivar el espacio predeterminado de su modalidad. Marca otro como predeterminado primero.';
                }
                $this->model->setEstado($id, 'inactivo');
            } elseif ($accion === 'predeterminar') {
                $this->model->setPredeterminado($id, (string) $espacio['modalidad']);
            } else {
                return 'Acción no válida.';
            }
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return 'No se pudo actualizar el espacio.';
        }

        return null;
    }

    private function normalize(array $input): array
    {
        $modalidad = (string) ($input['modalidad'] ?? 'presencial');
        $descripcion = trim((string) ($input['descripcion'] ?? ''));

        return [
            'nombre' => normalize_name((string) ($input['nombre'] ?? '')),
            'modalidad' => isset(EspacioTutoria::MODALIDADES[$modalidad]) ? $modalidad : 'presencial',
            'descripcion' => $descripcion === '' ? null : $descripcion,
        ];
    }

    private function validate(array $data, ?int $ignoreId): array
    {
        $errors = [];
        $nameError = validation_label($data['nombre'], 'nombre del espacio', 80);
        if ($nameError !== null) {
            $errors[] = $nameError;
        } elseif ($this->model->nameExists($data['nombre'], $ignoreId)) {
            $errors[] = 'Ya existe un espacio con ese nombre.';
        }
        if ($data['descripcion'] !== null) {
            $error = validation_text($data['descripcion'], 'descripción', 255);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
