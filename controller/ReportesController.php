<?php

declare(strict_types=1);

final class ReportesController
{
    private ReporteTutoria $model;

    public function __construct()
    {
        $this->model = new ReporteTutoria();
    }

    public function filters(array $input): array
    {
        return [
            'id_tutor' => filter_var($input['id_tutor'] ?? null, FILTER_VALIDATE_INT) ?: '',
            'id_estudiante' => filter_var($input['id_estudiante'] ?? null, FILTER_VALIDATE_INT) ?: '',
            'id_materia' => filter_var($input['id_materia'] ?? null, FILTER_VALIDATE_INT) ?: '',
            'estado' => in_array($input['estado'] ?? '', ['pendiente', 'confirmada', 'realizada', 'cancelada'], true) ? $input['estado'] : '',
            'fecha_desde' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['fecha_desde'] ?? '')) ? $input['fecha_desde'] : '',
            'fecha_hasta' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($input['fecha_hasta'] ?? '')) ? $input['fecha_hasta'] : '',
        ];
    }

    public function report(array $filters): array
    {
        $rows = $this->model->all($filters);
        $summary = [
            'total' => count($rows),
            'pendiente' => 0,
            'confirmada' => 0,
            'realizada' => 0,
            'cancelada' => 0,
            'asistio' => 0,
            'no_asistio' => 0,
            'parcial' => 0,
            'retraso' => 0,
        ];
        foreach ($rows as $row) {
            if (isset($summary[$row['estado']])) {
                $summary[$row['estado']]++;
            }
            if (isset($summary[$row['asistencia']])) {
                $summary[$row['asistencia']]++;
            }
        }

        return [$rows, $summary];
    }

    public function options(): array
    {
        return [
            'tutors' => $this->model->tutors(),
            'students' => $this->model->students(),
            'subjects' => $this->model->subjects(),
        ];
    }
}
