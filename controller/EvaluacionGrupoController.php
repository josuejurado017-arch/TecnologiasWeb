<?php

declare(strict_types=1);

/** Evaluacion ampliada del estudiante sobre su grupo/tutor. */
final class EvaluacionGrupoController
{
    private Evaluacion $model;

    private const CRITERIOS = ['general', 'puntualidad', 'dominio', 'claridad', 'utilidad'];

    public function __construct()
    {
        $this->model = new Evaluacion();
    }

    public function pending(int $studentId, int $periodoId): array
    {
        return $this->model->pendingForStudent($studentId, $periodoId);
    }

    public function done(int $studentId, int $periodoId): array
    {
        return $this->model->doneForStudent($studentId, $periodoId);
    }

    public function findEvaluable(int $inscripcionId, int $studentId): ?array
    {
        return $this->model->findEvaluableInscription($inscripcionId, $studentId);
    }

    /** Valida y guarda la evaluacion. Devuelve lista de errores (vacia si todo OK). */
    public function save(int $inscripcionId, int $studentId, array $input): array
    {
        if ($this->model->findEvaluableInscription($inscripcionId, $studentId) === null) {
            return ['Esta tutoria no esta disponible para evaluar.'];
        }

        $scores = [];
        $errors = [];
        foreach (self::CRITERIOS as $criterio) {
            $value = filter_var($input[$criterio] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 5) {
                $errors[] = 'Califica ' . $criterio . ' con un valor de 1 a 5.';
            } else {
                $scores[$criterio] = $value;
            }
        }

        $comentario = trim((string) ($input['comentario'] ?? ''));
        if ($comentario !== '') {
            if (mb_strlen($comentario) > 1000) {
                $errors[] = 'El comentario no puede superar 1000 caracteres.';
            } elseif (preg_match('/[\x00-\x1F\x7F]/', $comentario)) {
                $errors[] = 'El comentario contiene caracteres no válidos.';
            }
        }

        if ($errors) {
            return $errors;
        }

        try {
            $this->model->create($inscripcionId, $scores, $comentario === '' ? null : $comentario);
            return [];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return ['No se pudo guardar la evaluacion (quiza ya fue evaluada).'];
        }
    }
}
