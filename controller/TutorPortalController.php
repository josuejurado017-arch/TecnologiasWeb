<?php

declare(strict_types=1);

final class TutorPortalController
{
    private TutorPortal $model;
    private Tutor $tutores;
    private TutorMateriaConfig $config;

    public function __construct()
    {
        $this->model = new TutorPortal();
        $this->tutores = new Tutor();
        $this->config = new TutorMateriaConfig();
    }

    public function profile(int $userId): ?array
    {
        return $this->model->profile($userId);
    }

    /** Materias del tutor con el resumen de configuracion de preferencias de cada una. */
    public function subjects(int $userId): array
    {
        $subjects = $this->model->subjects($userId);
        $tutorId = $this->tutores->findIdByUserId($userId);
        $summary = $tutorId !== null ? $this->config->summaryForTutor($tutorId) : [];

        foreach ($subjects as &$subject) {
            $materiaId = (int) $subject['id_materia'];
            $subject['config'] = $summary[$materiaId] ?? [
                'modalidad' => null,
                'disponible_sabados' => false,
                'cupo_recomendado' => null,
                'turnos' => [],
                'sabados_franjas' => [],
                'configured' => false,
            ];
        }
        unset($subject);

        return $subjects;
    }

    /** Configuracion actual de una materia del tutor, para precargar el formulario de edicion. */
    public function materiaConfig(int $userId, int $materiaId): ?array
    {
        $tutorId = $this->tutores->findIdByUserId($userId);

        return $tutorId !== null ? $this->config->find($tutorId, $materiaId) : null;
    }

    /** Guarda la configuracion (turnos, modalidad, sabados, cupo) de una materia del tutor. */
    public function saveMateriaConfig(int $userId, array $input): ?string
    {
        $tutorId = $this->tutores->findIdByUserId($userId);
        if ($tutorId === null) {
            return 'Tu perfil de tutor no esta completo.';
        }

        $materiaId = filter_var($input['id_materia'] ?? null, FILTER_VALIDATE_INT);
        if ($materiaId === false || $materiaId < 1) {
            return 'Materia no válida.';
        }

        $misMaterias = array_map(static fn (array $s): int => (int) $s['id_materia'], $this->model->subjects($userId));
        if (!in_array($materiaId, $misMaterias, true)) {
            return 'Esa materia no está asignada a tu perfil.';
        }

        $modalidad = is_string($input['modalidad'] ?? null) ? $input['modalidad'] : '';
        if (!in_array($modalidad, ['presencial', 'virtual', 'ambas'], true)) {
            return 'Selecciona una modalidad válida.';
        }

        $turnosInput = isset($input['turnos']) && is_array($input['turnos']) ? $input['turnos'] : [];
        $turnos = array_values(array_unique(array_filter(
            $turnosInput,
            fn ($turno): bool => is_string($turno) && $this->config->isValidTurno($turno)
        )));

        $disponibleSabados = !empty($input['disponible_sabados']);
        $franjasInput = isset($input['sabados_franjas']) && is_array($input['sabados_franjas']) ? $input['sabados_franjas'] : [];
        $franjas = array_values(array_unique(array_filter(
            $franjasInput,
            fn ($franja): bool => is_string($franja) && $this->config->isValidFranja($franja)
        )));

        if (!$turnos && !($disponibleSabados && $franjas)) {
            return 'Selecciona al menos un turno o una franja de sábado.';
        }

        $cupoRaw = $input['cupo_recomendado'] ?? '';
        $cupoRecomendado = null;
        if ($cupoRaw !== '' && $cupoRaw !== null) {
            $cupoInt = filter_var($cupoRaw, FILTER_VALIDATE_INT);
            if ($cupoInt === false || !in_array($cupoInt, TutorMateriaConfig::CUPOS_RECOMENDADOS, true)) {
                return 'Cupo recomendado no válido.';
            }
            $cupoRecomendado = $cupoInt;
        }

        try {
            $this->config->save($tutorId, $materiaId, [
                'modalidad' => $modalidad,
                'disponible_sabados' => $disponibleSabados,
                'cupo_recomendado' => $cupoRecomendado,
                'turnos' => $turnos,
                'sabados_franjas' => $disponibleSabados ? $franjas : [],
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return 'No fue posible guardar la configuración.';
        }

        return null;
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
