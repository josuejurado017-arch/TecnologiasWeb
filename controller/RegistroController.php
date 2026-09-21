<?php

declare(strict_types=1);

final class RegistroController
{
    private RegistroEstudiante $model;

    public function __construct()
    {
        $this->model = new RegistroEstudiante();
    }

    public function careers(): array
    {
        return $this->model->careers();
    }

    public function register(array $input): array
    {
        $data = $this->normalize($input);
        $errors = $this->validate($data);
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $this->model->register($data);
            return [$data, []];
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            return [$data, ['El correo, usuario o registro universitario ya puede estar registrado.']];
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No fue posible completar el registro.']];
        }
    }

    private function normalize(array $input): array
    {
        return [
            'nombre' => normalize_name((string) ($input['nombre'] ?? '')),
            'apellido' => normalize_name((string) ($input['apellido'] ?? '')),
            'correo' => trim((string) ($input['correo'] ?? '')),
            'usuario' => trim((string) ($input['usuario'] ?? '')),
            'contrasena' => (string) ($input['contrasena'] ?? ''),
            'confirmacion' => (string) ($input['confirmacion'] ?? ''),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'id_carrera' => trim((string) ($input['id_carrera'] ?? '')),
            'semestre' => trim((string) ($input['semestre'] ?? '')),
            'registro_universitario' => trim((string) ($input['registro_universitario'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        $careerId = filter_var($data['id_carrera'], FILTER_VALIDATE_INT);
        $semester = filter_var($data['semestre'], FILTER_VALIDATE_INT);

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
        if (strlen($data['contrasena']) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($data['contrasena'] !== $data['confirmacion']) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        if ($careerId === false || $careerId < 1 || !$this->model->careerExists((int) $careerId)) {
            $errors[] = 'Seleccione una carrera válida.';
        }
        if ($semester === false || $semester < 1 || $semester > 20) {
            $errors[] = 'El semestre debe estar entre 1 y 20.';
        }
        $registrationError = validation_code($data['registro_universitario'], 'registro universitario', 30);
        if ($registrationError !== null) {
            $errors[] = $registrationError;
        }

        return $errors;
    }
}
