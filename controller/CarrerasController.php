<?php

declare(strict_types=1);

final class CarrerasController
{
    private Carrera $model;

    public function __construct()
    {
        $this->model = new Carrera();
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
        $data = ['nombre_carrera' => normalize_name((string) ($input['nombre_carrera'] ?? ''))];
        $errors = $this->validate($data, null);

        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->create($data['nombre_carrera']);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo guardar la carrera.']];
        }
    }

    public function update(int $id, array $input): array
    {
        $data = ['nombre_carrera' => normalize_name((string) ($input['nombre_carrera'] ?? ''))];
        $errors = $this->validate($data, $id);

        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->update($id, $data['nombre_carrera']);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo actualizar la carrera.']];
        }
    }

    public function delete(int $id): ?string
    {
        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se puede eliminar la carrera porque tiene estudiantes o materias asociadas.';
        }
    }

    private function validate(array $data, ?int $ignoreId): array
    {
        $error = validation_label($data['nombre_carrera'], 'nombre de la carrera', 150);
        if ($error !== null) {
            return [$error];
        }
        if ($this->model->nameExists($data['nombre_carrera'], $ignoreId)) {
            return ['Ya existe una carrera con ese nombre.'];
        }
        return [];
    }
}
