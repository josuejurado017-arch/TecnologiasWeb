<?php

declare(strict_types=1);

final class RegistroTutorController
{
    private RegistroTutor $model;

    public function __construct()
    {
        $this->model = new RegistroTutor();
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
            return [$data, ['El correo o usuario ya puede estar registrado.']];
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            return [$data, ['No fue posible crear la cuenta de tutor.']];
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
            'especialidad' => trim((string) ($input['especialidad'] ?? '')),
            'biografia' => trim((string) ($input['biografia'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
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
        if (strlen($data['contrasena']) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($data['contrasena'] !== $data['confirmacion']) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        $phoneError = validation_phone($data['telefono']);
        if ($phoneError !== null) {
            $errors[] = $phoneError;
        }
        $specialtyError = validation_text($data['especialidad'], 'especialidad', 150);
        if ($specialtyError !== null) {
            $errors[] = $specialtyError;
        }
        if (strlen($data['biografia']) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $data['biografia'])) {
            $errors[] = 'La biografia no puede superar 2000 caracteres ni contener caracteres no válidos.';
        }

        return $errors;
    }
}
