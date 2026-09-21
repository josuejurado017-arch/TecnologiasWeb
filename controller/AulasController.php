<?php

declare(strict_types=1);

final class AulasController
{
    private Aula $model;

    public function __construct()
    {
        $this->model = new Aula();
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
            return [$data, ['No se pudo guardar el aula.']];
        }
    }

    public function update(int $id, array $input): array
    {
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
            return [$data, ['No se pudo actualizar el aula.']];
        }
    }

    public function delete(int $id): ?string
    {
        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se puede eliminar el aula porque tiene grupos asociados.';
        }
    }

    private function normalize(array $input): array
    {
        $tipo = (string) ($input['tipo'] ?? 'fisica');
        $tipo = in_array($tipo, ['fisica', 'virtual'], true) ? $tipo : 'fisica';
        $capacidad = filter_var($input['capacidad'] ?? null, FILTER_VALIDATE_INT);
        $estado = (string) ($input['estado'] ?? 'activa');

        return [
            'nombre' => trim((string) ($input['nombre'] ?? '')),
            'tipo' => $tipo,
            'capacidad' => $capacidad !== false ? $capacidad : 0,
            'ubicacion' => ($v = trim((string) ($input['ubicacion'] ?? ''))) === '' ? null : $v,
            'enlace' => ($v = trim((string) ($input['enlace'] ?? ''))) === '' ? null : $v,
            'plataforma' => ($v = trim((string) ($input['plataforma'] ?? ''))) === '' ? null : $v,
            'estado' => in_array($estado, ['activa', 'inactiva'], true) ? $estado : 'activa',
        ];
    }

    private function validate(array $data, ?int $ignoreId): array
    {
        $errors = [];

        $nameError = validation_label($data['nombre'], 'nombre del aula', 120);
        if ($nameError !== null) {
            $errors[] = $nameError;
        } elseif ($this->model->nameExists($data['nombre'], $ignoreId)) {
            $errors[] = 'Ya existe un aula con ese nombre.';
        }

        if ($data['capacidad'] < 1 || $data['capacidad'] > 500) {
            $errors[] = 'La capacidad debe estar entre 1 y 500.';
        }

        if ($data['tipo'] === 'fisica') {
            if ($data['ubicacion'] === null) {
                $errors[] = 'Una aula fisica requiere ubicacion.';
            }
        } else { // virtual
            if ($data['enlace'] === null) {
                $errors[] = 'Una sala virtual requiere un enlace.';
            } elseif (!filter_var($data['enlace'], FILTER_VALIDATE_URL)) {
                $errors[] = 'El enlace de la sala virtual no es una URL válida.';
            }
        }

        foreach (['ubicacion' => 'ubicacion', 'plataforma' => 'plataforma'] as $field => $label) {
            if ($data[$field] !== null) {
                $error = validation_text($data[$field], $label, 200);
                if ($error !== null) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }
}
