<?php

declare(strict_types=1);

final class TutoresController
{
    private Tutor $model;
    private Usuario $usuarios;

    public function __construct()
    {
        $this->model = new Tutor();
        $this->usuarios = new Usuario();
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

    /**
     * Actualiza en una sola transaccion los datos personales de la cuenta
     * (tabla usuarios) y el perfil profesional (tabla tutores). La cuenta
     * asociada NO se reasigna aqui.
     */
    public function update(int $id, array $input): array
    {
        $current = $this->model->findById($id);
        if ($current === null) {
            return [$this->normalize($input), ['El tutor ya no existe.']];
        }

        $data = $this->normalize($input);
        $data['usuario'] = $current['usuario'];
        $userId = (int) $current['id_usuario'];
        $errors = $this->validateEdit($data, $userId);
        if ($errors) {
            return [$data, $errors];
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $this->usuarios->updateIdentity($userId, $data);
            $this->model->updateProfile($id, $data);
            $connection->commit();

            return [$data, []];
        } catch (PDOException $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());

            return [$data, ['El correo o el carnet ya estan registrados en otra cuenta.']];
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());

            return [$data, ['No fue posible guardar los cambios.']];
        }
    }

    /**
     * Da de alta o de baja la cuenta del tutor. Alternativa no destructiva a
     * delete(): un tutor con grupos o materias no se puede borrar (FK), pero si
     * desactivar para que deje de recibir asignaciones.
     */
    public function setEstado(int $id, string $estado): ?string
    {
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            return 'Estado no válido.';
        }

        $current = $this->model->findById($id);
        if ($current === null) {
            return 'El tutor no existe.';
        }

        $userId = (int) $current['id_usuario'];
        if ((int) (Auth::user()['id_usuario'] ?? 0) === $userId) {
            return 'No puede cambiar el estado de su propia cuenta.';
        }

        try {
            $cambio = $estado === 'activo'
                ? $this->usuarios->activate($userId)
                : $this->usuarios->deactivate($userId);

            return $cambio ? null : 'La cuenta ya estaba ' . ($estado === 'activo' ? 'activa' : 'inactiva') . '.';
        } catch (PDOException $exception) {
            error_log($exception->getMessage());

            return 'No fue posible cambiar el estado de la cuenta.';
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
            // Datos de la persona (tabla usuarios).
            'nombre' => normalize_name((string) ($input['nombre'] ?? '')),
            'apellido' => normalize_name((string) ($input['apellido'] ?? '')),
            'correo' => trim((string) ($input['correo'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'carnet_identidad' => trim((string) ($input['carnet_identidad'] ?? '')),
            'estado' => trim((string) ($input['estado'] ?? 'activo')),
            // Perfil profesional (tabla tutores).
            'especialidad' => trim((string) ($input['especialidad'] ?? '')),
            'biografia' => trim((string) ($input['biografia'] ?? '')),
        ];
    }

    /**
     * Validacion de la edicion completa. $userId se excluye de las busquedas de
     * duplicados para que guardar sin cambiar nada no de error.
     */
    private function validateEdit(array $data, int $userId): array
    {
        $errors = [];

        foreach ([['value' => $data['nombre'], 'label' => 'nombre'], ['value' => $data['apellido'], 'label' => 'apellido']] as $field) {
            $error = validation_name($field['value'], $field['label']);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ingrese un correo válido.';
        } elseif ($this->usuarios->correoTaken($data['correo'], $userId)) {
            $errors[] = 'Ese correo ya pertenece a otra cuenta.';
        }

        $phoneError = validation_phone($data['telefono']);
        if ($phoneError !== null) {
            $errors[] = $phoneError;
        }

        if ($data['carnet_identidad'] !== '') {
            $ciError = validation_ci($data['carnet_identidad']);
            if ($ciError !== null) {
                $errors[] = $ciError;
            } elseif ($this->usuarios->carnetTaken($data['carnet_identidad'], $userId)) {
                $errors[] = 'Ese carnet de identidad ya pertenece a otra cuenta.';
            }
        }

        if (!in_array($data['estado'], ['activo', 'inactivo'], true)) {
            $errors[] = 'Seleccione un estado válido.';
        }

        if ($data['especialidad'] !== '') {
            $specialtyError = validation_text($data['especialidad'], 'especialidad', 150);
            if ($specialtyError !== null) {
                $errors[] = $specialtyError;
            }
        }

        if (mb_strlen($data['biografia']) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $data['biografia'])) {
            $errors[] = 'La biografía no puede superar 2000 caracteres ni contener caracteres no válidos.';
        }

        return $errors;
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
