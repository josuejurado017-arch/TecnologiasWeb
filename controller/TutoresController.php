<?php

declare(strict_types=1);

final class TutoresController
{
    private Tutor $model;

    public function __construct()
    {
        $this->model = new Tutor();
    }

    public function index(): array
    {
        return $this->model->all();
    }

    public function users(?int $currentUserId = null): array
    {
        return $this->model->usersForForm($currentUserId);
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
            return [$data, ['El usuario ya tiene un perfil de tutor.']];
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
            return [$data, ['El usuario ya tiene un perfil de tutor.']];
        }
    }

    public function delete(int $id): ?string
    {
        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se puede eliminar el perfil porque tiene relaciones asociadas.';
        }
    }

    private function normalize(array $input): array
    {
        return [
            'id_usuario' => trim((string) ($input['id_usuario'] ?? '')),
            'especialidad' => trim((string) ($input['especialidad'] ?? '')),
            'biografia' => trim((string) ($input['biografia'] ?? '')),
        ];
    }

    private function validate(array $data, ?int $currentUserId): array
    {
        $errors = [];
        $userId = filter_var($data['id_usuario'], FILTER_VALIDATE_INT);

        if ($userId === false || $userId < 1) {
            $errors[] = 'Seleccione un usuario tutor válido.';
        } elseif (!$this->model->userIsEligible((int) $userId, $currentUserId)) {
            $errors[] = 'El usuario seleccionado no es elegible para este perfil.';
        }
        if ($data['especialidad'] !== '') {
            $specialtyError = validation_text($data['especialidad'], 'especialidad', 150);
            if ($specialtyError !== null) {
                $errors[] = $specialtyError;
            }
        }
        if (strlen($data['biografia']) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $data['biografia'])) {
            $errors[] = 'La biografia no puede superar 2000 caracteres ni contener caracteres no válidos.';
        }

        return $errors;
    }
}
