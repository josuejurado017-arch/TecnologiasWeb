<?php

declare(strict_types=1);

final class PeriodosController
{
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
            return [$data, ['No se pudo guardar la campana.']];
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
            return [$data, ['No se pudo actualizar la campana.']];
        }
    }

    public function activate(int $id): ?string
    {
        if (!$this->model->findById($id)) {
            return 'La campana no existe.';
        }
        try {
            $this->model->setActiva($id);
            return null;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return 'No se pudo activar la campana.';
        }
    }

    public function delete(int $id): ?string
    {
        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se puede eliminar la campana porque tiene grupos o inscripciones asociadas.';
        }
    }

    private function normalize(array $input): array
    {
        $minGroup = filter_var($input['cupo_min_grupo'] ?? null, FILTER_VALIDATE_INT);
        $maxDefault = filter_var($input['cupo_max_default'] ?? null, FILTER_VALIDATE_INT);
        $estado = (string) ($input['estado'] ?? 'borrador');

        return [
            'nombre' => trim((string) ($input['nombre'] ?? '')),
            'fecha_inicio' => trim((string) ($input['fecha_inicio'] ?? '')),
            'fecha_fin' => trim((string) ($input['fecha_fin'] ?? '')),
            'cupo_min_grupo' => $minGroup !== false ? $minGroup : 3,
            'cupo_max_default' => $maxDefault !== false ? $maxDefault : 20,
            'estado' => in_array($estado, ['borrador', 'activa', 'cerrada'], true) ? $estado : 'borrador',
        ];
    }

    private function validate(array $data, ?int $ignoreId): array
    {
        $errors = [];

        $nameError = validation_label($data['nombre'], 'nombre de la campana', 120);
        if ($nameError !== null) {
            $errors[] = $nameError;
        } elseif ($this->model->nameExists($data['nombre'], $ignoreId)) {
            $errors[] = 'Ya existe una campana con ese nombre.';
        }

        $start = $this->parseDate($data['fecha_inicio']);
        $end = $this->parseDate($data['fecha_fin']);
        if ($start === null) {
            $errors[] = 'La fecha de inicio no es valida.';
        }
        if ($end === null) {
            $errors[] = 'La fecha de fin no es valida.';
        }
        if ($start !== null && $end !== null && $end < $start) {
            $errors[] = 'La fecha de fin debe ser posterior o igual a la de inicio.';
        }

        if ($data['cupo_min_grupo'] < 1 || $data['cupo_min_grupo'] > 100) {
            $errors[] = 'El cupo minimo por grupo debe estar entre 1 y 100.';
        }
        if ($data['cupo_max_default'] < 1 || $data['cupo_max_default'] > 200) {
            $errors[] = 'El cupo maximo por defecto debe estar entre 1 y 200.';
        }
        if ($data['cupo_max_default'] < $data['cupo_min_grupo']) {
            $errors[] = 'El cupo maximo no puede ser menor que el cupo minimo.';
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
