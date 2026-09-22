<?php

declare(strict_types=1);

final class EstudiantesController
{
    private Estudiante $model;

    public function __construct()
    {
        $this->model = new Estudiante();
    }

    public function index(): array
    {
        return $this->model->all();
    }

    public function options(?int $currentUserId = null): array
    {
        return [
            'users' => $this->model->usersForForm($currentUserId),
            'careers' => $this->model->careers(),
        ];
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
            return [$data, ['El usuario ya tiene un perfil o el registro universitario ya existe.']];
        }
    }

    public function update(int $id, array $input): array
    {
        $data = $this->normalize($input);
        $current = $this->model->findById($id);
        $errors = $this->validate($data, $current ? (int) $current['id_usuario'] : null);
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->update($id, $data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['El usuario ya tiene un perfil o el registro universitario ya existe.']];
        }
    }

    public function delete(int $id): ?string
    {
        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se puede eliminar el perfil porque tiene tutorias asociadas.';
        }
    }

    private function normalize(array $input): array
    {
        return [
            'id_usuario' => trim((string) ($input['id_usuario'] ?? '')),
            'id_carrera' => trim((string) ($input['id_carrera'] ?? '')),
            'semestre' => trim((string) ($input['semestre'] ?? '')),
            'registro_universitario' => trim((string) ($input['registro_universitario'] ?? '')),
        ];
    }

    private function validate(array $data, ?int $currentUserId): array
    {
        $errors = [];
        $userId = filter_var($data['id_usuario'], FILTER_VALIDATE_INT);
        $careerId = filter_var($data['id_carrera'], FILTER_VALIDATE_INT);
        $semester = filter_var($data['semestre'], FILTER_VALIDATE_INT);

        if ($userId === false || $userId < 1) {
            $errors[] = 'Seleccione un usuario estudiante válido.';
        } elseif (!$this->model->userIsEligible((int) $userId, $currentUserId)) {
            $errors[] = 'El usuario seleccionado no es elegible para este perfil.';
        }
        if ($careerId === false || $careerId < 1 || !$this->model->careerExists((int) $careerId)) {
            $errors[] = 'Seleccione una carrera válida.';
        }
        if ($semester === false || $semester < 1 || $semester > 10) {
            $errors[] = 'El semestre debe estar entre 1 y 10.';
        }
        $registrationError = validation_code($data['registro_universitario'], 'registro universitario', 30);
        if ($registrationError !== null) {
            $errors[] = $registrationError;
        }

        return $errors;
    }
}
