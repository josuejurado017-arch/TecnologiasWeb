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

    /**
     * Habilitacion docente (db/028): mientras la coordinacion no apruebe al tutor,
     * solo puede completar su perfil. Agregar, quitar o configurar materias queda
     * bloqueado. Devuelve el mensaje de bloqueo, o null si esta aprobado.
     */
    public function bloqueoHabilitacion(int $userId): ?string
    {
        $estado = $this->tutores->estadoDocenteByUserId($userId);

        return match ($estado['estado_docente'] ?? null) {
            'aprobado' => null,
            'rechazado' => 'La coordinación rechazó tu habilitación docente: no puedes gestionar materias.',
            'suspendido' => 'Tu habilitación docente está suspendida: no puedes gestionar materias.',
            default => 'Tu habilitación docente está en revisión. Podrás agregar y configurar materias cuando la coordinación la apruebe.',
        };
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
                'cupo_recomendado' => null,
                'turnos' => [],
                'modalidad_incompatible' => false,
                'configured' => false,
                'estado' => null,
                'motivo_rechazo' => null,
            ];
            $subject['cobertura'] = $this->config->coberturaMateria($materiaId);
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

    /** Guarda la configuracion (turnos, modalidad, cupo) de una materia del tutor. Los dias los decide la demanda (db/033). */
    public function saveMateriaConfig(int $userId, array $input): ?string
    {
        $tutorId = $this->tutores->findIdByUserId($userId);
        if ($tutorId === null) {
            return 'Tu perfil de tutor no esta completo.';
        }
        if (($bloqueo = $this->bloqueoHabilitacion($userId)) !== null) {
            return $bloqueo;
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
        $requerida = (string) ((new Materia())->findById($materiaId)['modalidad_requerida'] ?? 'libre');
        if (!in_array($modalidad, TutorMateriaConfig::modalidadesPermitidas($requerida), true)) {
            return 'Esta materia se dicta solo en modalidad ' . $requerida . '.';
        }

        $turnosInput = isset($input['turnos']) && is_array($input['turnos']) ? $input['turnos'] : [];
        $turnos = array_values(array_unique(array_filter(
            $turnosInput,
            fn ($turno): bool => is_string($turno) && $this->config->isValidTurno($turno)
        )));
        if (!$turnos) {
            return 'Selecciona al menos un turno.';
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
            $historialId = $this->config->save($tutorId, $materiaId, [
                'modalidad' => $modalidad,
                'cupo_recomendado' => $cupoRecomendado,
                'turnos' => $turnos,
            ]);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return 'No fue posible guardar la configuración.';
        }

        // La oferta queda pendiente (TutorMateriaConfig::save): el reproceso no
        // encontrara horarios aprobados todavia, pero queda listo para cuando la
        // coordinacion la apruebe.
        (new AsignacionController())->reprocesarMateria($materiaId);

        if ($historialId !== null) {
            try {
                $tutor = $this->tutores->findById($tutorId);
                $materiaNombre = (string) ((new Materia())->findById($materiaId)['nombre_materia'] ?? 'la materia');
                if ($tutor !== null) {
                    (new Notificacion())->notifyAdminsOfertaPendiente(
                        Database::connection(),
                        $tutorId,
                        $materiaId,
                        $tutor['nombre'] . ' ' . $tutor['apellido'],
                        $materiaNombre,
                        $historialId
                    );
                }
            } catch (Throwable $exception) {
                error_log('Notificacion oferta pendiente: ' . $exception->getMessage());
            }
        }

        return null;
    }

    public function availableSubjects(int $userId): array
    {
        if ((new Periodo())->activa() === null) { return []; }
        $tutorId = $this->tutores->findIdByUserId($userId);
        if ($tutorId !== null && $this->config->materiasOcupadas($tutorId) >= TutorMateriaConfig::MAX_MATERIAS) {
            return [];
        }
        return $this->model->availableSubjects($userId);
    }

    public function addSubject(int $userId, array $input): ?string
    {
        if ((new Periodo())->activa() === null) {
            return 'No hay un período activo. Espera la siguiente campaña para renovar tu oferta.';
        }
        if (($bloqueo = $this->bloqueoHabilitacion($userId)) !== null) {
            return $bloqueo;
        }
        $subjectId = filter_var($input['id_materia'] ?? null, FILTER_VALIDATE_INT);
        if ($subjectId === false || $subjectId < 1) {
            return 'Seleccione una materia válida.';
        }
        $tutorId = $this->tutores->findIdByUserId($userId);
        if ($tutorId !== null && $this->config->materiasOcupadas($tutorId) >= TutorMateriaConfig::MAX_MATERIAS) {
            return 'Ya tienes dos materias en este período.';
        }

        try {
            $this->model->addSubject($userId, $subjectId);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'La materia ya esta asignada a tu perfil.';
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        // Tutor nuevo para la materia: los estudiantes "sin tutor" pueden recibir grupo ahora.
        (new AsignacionController())->reprocesarMateria($subjectId);

        return null;
    }

    public function removeSubject(int $userId, array $input): ?string
    {
        if (($bloqueo = $this->bloqueoHabilitacion($userId)) !== null) {
            return $bloqueo;
        }
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
