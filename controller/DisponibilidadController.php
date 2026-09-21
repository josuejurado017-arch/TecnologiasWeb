<?php

declare(strict_types=1);

final class DisponibilidadController
{
    private DisponibilidadTutor $model;

    public function __construct()
    {
        $this->model = new DisponibilidadTutor();
    }

    public function index(?int $tutorId = null): array
    {
        return $this->model->all($tutorId);
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function tutors(): array
    {
        return $this->model->tutors();
    }

    public function store(array $input, ?int $forcedTutorId = null): array
    {
        $data = $this->normalize($input, $forcedTutorId);
        $errors = $this->validate($data);
        if (!$errors && $this->model->overlaps($data)) {
            $errors[] = 'El tutor ya tiene un horario que se cruza ese dia.';
        }
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->create($data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No fue posible registrar este horario.']];
        }
    }

    public function update(int $id, array $input, ?int $forcedTutorId = null): array
    {
        $data = $this->normalize($input, $forcedTutorId);
        $errors = $this->validate($data);
        if (!$errors && $this->model->overlaps($data, $id)) {
            $errors[] = 'El tutor ya tiene un horario que se cruza ese dia.';
        }
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->update($id, $data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No fue posible actualizar este horario.']];
        }
    }

    public function delete(int $id, array $data): ?string
    {
        if ($this->model->overlapsActiveTutoring($data)) {
            return 'No puedes eliminar un horario que coincide con una tutoria pendiente o confirmada.';
        }

        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No fue posible eliminar este horario.';
        }
    }

    private function normalize(array $input, ?int $forcedTutorId): array
    {
        return [
            'id_tutor' => $forcedTutorId ?? trim((string) ($input['id_tutor'] ?? '')),
            'dia_semana' => trim((string) ($input['dia_semana'] ?? '')),
            'hora_inicio' => trim((string) ($input['hora_inicio'] ?? '')),
            'hora_fin' => trim((string) ($input['hora_fin'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        $tutorId = filter_var($data['id_tutor'], FILTER_VALIDATE_INT);
        $days = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

        if ($tutorId === false || $tutorId < 1) {
            $errors[] = 'Seleccione un tutor válido.';
        }
        if (!in_array($data['dia_semana'], $days, true)) {
            $errors[] = 'Seleccione un dia válido.';
        }
        $startError = validation_time($data['hora_inicio'], 'hora inicial');
        $endError = validation_time($data['hora_fin'], 'hora final');
        if ($startError !== null || $endError !== null) {
            $errors[] = 'Ingrese horarios válidos.';
        } elseif ($data['hora_fin'] <= $data['hora_inicio']) {
            $errors[] = 'La hora final debe ser posterior a la inicial.';
        }

        return $errors;
    }
}
