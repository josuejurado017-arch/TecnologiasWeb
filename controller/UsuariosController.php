<?php

declare(strict_types=1);

final class UsuariosController
{
    private Usuario $model;

    public function __construct()
    {
        $this->model = new Usuario();
    }

    public function index(): array
    {
        return $this->model->all();
    }

    public function roles(): array
    {
        return $this->model->roles();
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function careers(): array
    {
        return (new Estudiante())->careers();
    }

    public function store(array $input): array
    {
        $data = $this->normalize($input);
        $data['estado'] = 'activo';
        $roleId = filter_var($data['id_rol'], FILTER_VALIDATE_INT) ?: 0;
        $roleName = $roleId ? $this->model->roleNameById($roleId) : null;
        $data['rol_nombre'] = $roleName;
        $errors = $this->validate($data, false, $roleName);

        if ($errors) {
            return [$data, $errors];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $userId = $this->model->create($data);
            if ($roleName === 'estudiante') {
                (new Estudiante())->create([
                    'id_usuario' => $userId,
                    'id_carrera' => (int) $data['id_carrera'],
                    'semestre' => (int) $data['semestre'],
                    'registro_universitario' => $data['registro_universitario'],
                ]);
            } elseif ($roleName === 'tutor') {
                (new Tutor())->create([
                    'id_usuario' => $userId,
                    'especialidad' => $data['especialidad'],
                    'biografia' => $data['biografia'],
                ]);
            }
            $pdo->commit();
            return [$data, []];
        } catch (PDOException $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return [$data, ['No se pudo crear: el correo, usuario, carnet o registro universitario ya existen.']];
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return [$data, ['No se pudo completar la creacion de la cuenta.']];
        }
    }

    public function update(int $id, array $input): array
    {
        $data = $this->normalize($input);
        $errors = $this->validate($data, true, null);

        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->update($id, $data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['El correo o el usuario ya pueden estar registrados.']];
        }
    }

    public function deactivate(int $id): ?string
    {
        $currentUser = Auth::user();
        if ((int) ($currentUser['id_usuario'] ?? 0) === $id) {
            return 'No puede desactivar su propia cuenta.';
        }

        try {
            return $this->model->deactivate($id) ? null : 'El usuario ya estaba inactivo o no existe.';
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No fue posible desactivar el usuario.';
        }
    }

    public function activate(int $id): ?string
    {
        try {
            return $this->model->activate($id) ? null : 'La cuenta ya estaba activa o no existe.';
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return 'No fue posible activar la cuenta.';
        }
    }

    private function normalize(array $input): array
    {
        return [
            'id_rol' => trim((string) ($input['id_rol'] ?? '')),
            'nombre' => normalize_name((string) ($input['nombre'] ?? '')),
            'apellido' => normalize_name((string) ($input['apellido'] ?? '')),
            'correo' => trim((string) ($input['correo'] ?? '')),
            'usuario' => trim((string) ($input['usuario'] ?? '')),
            'contrasena' => (string) ($input['contrasena'] ?? ''),
            'confirmacion' => (string) ($input['confirmacion'] ?? ''),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'carnet_identidad' => trim((string) ($input['carnet_identidad'] ?? '')),
            'estado' => trim((string) ($input['estado'] ?? 'activo')),
            // Campos de perfil (segun rol), usados solo en la creacion unificada.
            'id_carrera' => trim((string) ($input['id_carrera'] ?? '')),
            'semestre' => trim((string) ($input['semestre'] ?? '')),
            'registro_universitario' => trim((string) ($input['registro_universitario'] ?? '')),
            'especialidad' => trim((string) ($input['especialidad'] ?? '')),
            'biografia' => trim((string) ($input['biografia'] ?? '')),
        ];
    }

    private function validate(array $data, bool $editing, ?string $roleName): array
    {
        $errors = [];
        $roleId = filter_var($data['id_rol'], FILTER_VALIDATE_INT);

        if ($roleId === false || $roleId < 1) {
            $errors[] = 'Seleccione un rol válido.';
        } elseif (!$editing && $roleName === null) {
            $errors[] = 'El rol seleccionado no existe.';
        }
        foreach ([['value' => $data['nombre'], 'label' => 'nombre'], ['value' => $data['apellido'], 'label' => 'apellido']] as $personField) {
            $error = validation_name($personField['value'], $personField['label']);
            if ($error !== null) {
                $errors[] = $error;
            }
        }
        if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ingrese un correo válido.';
        }
        $usernameError = validation_username($data['usuario']);
        if ($usernameError !== null) {
            $errors[] = $usernameError;
        }
        $phoneError = validation_phone($data['telefono']);
        if ($phoneError !== null) {
            $errors[] = $phoneError;
        }
        if (!$editing && $data['contrasena'] === '') {
            $errors[] = 'La contraseña es obligatoria.';
        }
        if ($data['contrasena'] !== '' && strlen($data['contrasena']) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (($data['contrasena'] !== '' || $data['confirmacion'] !== '') && $data['contrasena'] !== $data['confirmacion']) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        if ($editing && !in_array($data['estado'], ['activo', 'inactivo'], true)) {
            $errors[] = 'Seleccione un estado válido.';
        }

        // Carnet de Identidad: obligatorio al crear; en edicion se valida si se envia.
        if (!$editing || $data['carnet_identidad'] !== '') {
            $ciError = validation_ci($data['carnet_identidad']);
            if ($ciError !== null) {
                $errors[] = $ciError;
            }
        }

        // Campos de perfil segun rol (solo en la creacion unificada).
        if (!$editing && $roleName === 'estudiante') {
            $careerId = filter_var($data['id_carrera'], FILTER_VALIDATE_INT);
            $semester = filter_var($data['semestre'], FILTER_VALIDATE_INT);
            if ($careerId === false || $careerId < 1 || !(new Estudiante())->careerExists((int) $careerId)) {
                $errors[] = 'Seleccione una carrera válida.';
            }
            if ($semester === false || $semester < 1 || $semester > 10) {
                $errors[] = 'El semestre debe estar entre 1 y 10.';
            }
            $ruError = validation_code($data['registro_universitario'], 'registro universitario', 30);
            if ($ruError !== null) {
                $errors[] = $ruError;
            }
        }
        if (!$editing && $roleName === 'tutor') {
            $specialtyError = validation_text($data['especialidad'], 'especialidad', 150);
            if ($specialtyError !== null) {
                $errors[] = $specialtyError;
            }
            if (mb_strlen($data['biografia']) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $data['biografia'])) {
                $errors[] = 'La biografia no puede superar 2000 caracteres ni contener caracteres no validos.';
            }
        }

        return $errors;
    }
}
