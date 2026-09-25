<?php

declare(strict_types=1);

final class MateriasController
{
    private Materia $model;

    public function __construct()
    {
        $this->model = new Materia();
    }

    public function index(): array
    {
        return $this->model->all();
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function careers(): array
    {
        return $this->model->careers();
    }

    public function store(array $input): array
    {
        $data = $this->normalize($input);
        $errors = $this->validate($data, null);

        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->create($data['nombre_materia'], $data['id_carrera'], $data['modalidad_requerida']);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo guardar la materia.']];
        }
    }

    /**
     * Actualiza la materia. Si cambia su modalidad requerida, avisa a los tutores cuya
     * configuracion queda incompatible y reprocesa la demanda (la oferta puede cambiar).
     * Devuelve [$data, $errors, $tutoresIncompatibles].
     */
    public function update(int $id, array $input): array
    {
        $data = $this->normalize($input);
        $errors = $this->validate($data, $id);

        if ($errors) {
            return [$data, $errors, 0];
        }

        $anterior = $this->model->findById($id);
        try {
            $this->model->update($id, $data['nombre_materia'], $data['id_carrera'], $data['modalidad_requerida']);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No se pudo actualizar la materia.'], 0];
        }

        $incompatibles = [];
        if ($anterior !== null && $anterior['modalidad_requerida'] !== $data['modalidad_requerida']) {
            $incompatibles = $this->model->tutoresIncompatibles($id);
            try {
                $notif = new Notificacion();
                foreach ($incompatibles as $tutor) {
                    $notif->notifyTutorModalidadMateria(Database::connection(), (int) $tutor['id_usuario'], $id, $data['nombre_materia'], $data['modalidad_requerida']);
                }
            } catch (Throwable $exception) {
                error_log('Notificacion modalidad materia: ' . $exception->getMessage());
            }
            (new AsignacionController())->reprocesarMateria($id);
        }

        return [$data, [], count($incompatibles)];
    }

    public function delete(int $id): ?string
    {
        try {
            $this->model->delete($id);
            return null;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No se puede eliminar la materia porque tiene tutores o tutorias asociadas.';
        }
    }

    private function normalize(array $input): array
    {
        $careerId = filter_var($input['id_carrera'] ?? null, FILTER_VALIDATE_INT);

        $modalidad = (string) ($input['modalidad_requerida'] ?? 'libre');

        return [
            'nombre_materia' => normalize_name((string) ($input['nombre_materia'] ?? '')),
            'id_carrera' => $careerId !== false ? $careerId : null,
            'modalidad_requerida' => isset(Materia::MODALIDADES_REQUERIDAS[$modalidad]) ? $modalidad : 'libre',
        ];
    }

    private function validate(array $data, ?int $ignoreId): array
    {
        $errors = [];
        $error = validation_label($data['nombre_materia'], 'nombre de la materia', 150);
        if ($error !== null) {
            $errors[] = $error;
        } elseif ($this->model->nameExists($data['nombre_materia'], $ignoreId)) {
            $errors[] = 'Ya existe una materia con ese nombre.';
        }
        if ($data['id_carrera'] !== null && !$this->model->careerExists((int) $data['id_carrera'])) {
            $errors[] = 'La carrera seleccionada no existe.';
        }

        return $errors;
    }
}
