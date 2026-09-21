<?php

declare(strict_types=1);

final class TutorPortalController
{
    private TutorPortal $model;

    public function __construct()
    {
        $this->model = new TutorPortal();
    }

    public function profile(int $userId): ?array
    {
        return $this->model->profile($userId);
    }

    public function subjects(int $userId): array
    {
        return $this->model->subjects($userId);
    }

    public function availableSubjects(int $userId): array
    {
        return $this->model->availableSubjects($userId);
    }

    public function addSubject(int $userId, array $input): ?string
    {
        $subjectId = filter_var($input['id_materia'] ?? null, FILTER_VALIDATE_INT);
        if ($subjectId === false || $subjectId < 1) {
            return 'Seleccione una materia válida.';
        }

        try {
            $this->model->addSubject($userId, $subjectId);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'La materia ya esta asignada a tu perfil.';
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function removeSubject(int $userId, array $input): ?string
    {
        $subjectId = filter_var($input['id_materia'] ?? null, FILTER_VALIDATE_INT);
        if ($subjectId === false || $subjectId < 1) {
            return 'Materia no válida.';
        }

        try {
            $this->model->removeSubject($userId, $subjectId);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function update(int $userId, array $input): array
    {
        $data = [
            'especialidad' => trim((string) ($input['especialidad'] ?? '')),
            'biografia' => trim((string) ($input['biografia'] ?? '')),
        ];
        $errors = [];
        $specialtyError = validation_text($data['especialidad'], 'especialidad', 150);
        if ($specialtyError !== null) {
            $errors[] = $specialtyError;
        }
        if (strlen($data['biografia']) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $data['biografia'])) {
            $errors[] = 'La biografia no puede superar 2000 caracteres ni contener caracteres no válidos.';
        }
        if (!$errors) {
            $this->model->updateProfile($userId, $data['especialidad'], $data['biografia']);
        }

        return [$data, $errors];
    }
}
