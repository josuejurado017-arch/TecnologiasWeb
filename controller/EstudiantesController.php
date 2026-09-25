<?php

declare(strict_types=1);

final class EstudiantesController
{
    private Estudiante $model;
    private Usuario $usuarios;

    public function __construct()
    {
        $this->model = new Estudiante();
        $this->usuarios = new Usuario();
    }

    public function index(array $filtros = []): array
    {
        return $this->model->all($filtros);
    }

    /**
     * Filtros del padron desde la query string. Listado y exportacion usan los
     * mismos, asi el archivo descargado es exactamente lo que se ve en pantalla.
     */
    public static function filtros(array $query): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', (string) ($query['q'] ?? '')) ?? '');
        $carrera = filter_var($query['carrera'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $semestre = filter_var($query['semestre'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        $estado = (string) ($query['estado'] ?? '');
        $tutoria = (string) ($query['tutoria'] ?? '');

        return [
            'q' => mb_substr($q, 0, 100),
            'carrera' => $carrera ?: null,
            'semestre' => $semestre ?: null,
            'estado' => in_array($estado, ['activo', 'inactivo'], true) ? $estado : '',
            'tutoria' => isset(Estudiante::FILTROS_TUTORIA[$tutoria]) ? $tutoria : '',
        ];
    }

    /** Query string de los filtros activos (enlace de exportacion); '' sin filtros. */
    public static function queryFiltros(array $filtros): string
    {
        return http_build_query(array_filter($filtros, static fn ($v): bool => $v !== null && $v !== ''));
    }

    public function careers(): array
    {
        return $this->model->careers();
    }

    public function options(?int $currentUserId = null): array
    {
        return [
            'users' => $this->model->usersForForm($currentUserId),
            'careers' => $this->model->careers(),
        ];
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
            return [$data, ['El usuario ya tiene un perfil o el registro universitario ya existe.']];
        }
    }

    /**
     * Actualiza en una sola transaccion los datos personales de la cuenta
     * (tabla usuarios) y los academicos del perfil (tabla estudiantes). La
     * cuenta asociada NO se reasigna aqui: cambiar de titular un expediente
     * academico no es una edicion, es otra operacion.
     */
    public function update(int $id, array $input): array
    {
        $current = $this->model->findById($id);
        if ($current === null) {
            return [$this->normalize($input), ['El estudiante ya no existe.']];
        }

        $data = $this->normalize($input);
        $data['usuario'] = $current['usuario'];
        $userId = (int) $current['id_usuario'];
        $errors = $this->validateEdit($data, $userId, $id);
        if ($errors) {
            return [$data, $errors];
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $this->usuarios->updateIdentity($userId, $data);
            $this->model->updateAcademic($id, $data);
            $connection->commit();

            return [$data, []];
        } catch (PDOException $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());

            return [$data, ['El correo, el carnet o el registro universitario ya estan registrados.']];
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());

            return [$data, ['No fue posible guardar los cambios.']];
        }
    }

    /**
     * Da de alta o de baja la cuenta del estudiante. Es la alternativa no
     * destructiva a delete(): un estudiante con inscripciones no se puede
     * borrar (FK), pero si desactivar para que deje de operar conservando su
     * historial academico.
     */
    public function setEstado(int $id, string $estado): ?string
    {
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            return 'Estado no válido.';
        }

        $current = $this->model->findById($id);
        if ($current === null) {
            return 'El estudiante no existe.';
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
            return 'No se puede eliminar el perfil porque tiene tutorias asociadas.';
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
            // Datos academicos (tabla estudiantes).
            'id_carrera' => trim((string) ($input['id_carrera'] ?? '')),
            'semestre' => trim((string) ($input['semestre'] ?? '')),
            'registro_universitario' => trim((string) ($input['registro_universitario'] ?? '')),
        ];
    }

    /**
     * Validacion de la edicion completa. $userId y $studentId se excluyen de las
     * busquedas de duplicados para que guardar sin cambiar nada no de error.
     */
    private function validateEdit(array $data, int $userId, int $studentId): array
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

        $careerId = filter_var($data['id_carrera'], FILTER_VALIDATE_INT);
        if ($careerId === false || $careerId < 1 || !$this->model->careerExists((int) $careerId)) {
            $errors[] = 'Seleccione una carrera válida.';
        }

        $semester = filter_var($data['semestre'], FILTER_VALIDATE_INT);
        if ($semester === false || $semester < 1 || $semester > 10) {
            $errors[] = 'El semestre debe estar entre 1 y 10.';
        }

        if ($data['registro_universitario'] !== '') {
            $registrationError = validation_code($data['registro_universitario'], 'registro universitario', 30);
            if ($registrationError !== null) {
                $errors[] = $registrationError;
            } elseif ($this->model->registroTaken($data['registro_universitario'], $studentId)) {
                $errors[] = 'Ese registro universitario ya pertenece a otro estudiante.';
            }
        }

        return $errors;
    }

    private function validate(array $data, ?int $currentUserId): array
    {
        $errors = [];
        $userId = filter_var($data['id_usuario'], FILTER_VALIDATE_INT);
        $careerId = filter_var($data['id_carrera'], FILTER_VALIDATE_INT);
        $semester = filter_var($data['semestre'], FILTER_VALIDATE_INT);

        if ($userId === false || $userId < 1) {
            $errors[] = 'Seleccione un usuario estudiante válido.';
        } elseif (!$this->model->userIsEligible((int) $userId, $currentUserId)) {
            $errors[] = 'El usuario seleccionado no es elegible para este perfil.';
        }
        if ($careerId === false || $careerId < 1 || !$this->model->careerExists((int) $careerId)) {
            $errors[] = 'Seleccione una carrera válida.';
        }
        if ($semester === false || $semester < 1 || $semester > 10) {
            $errors[] = 'El semestre debe estar entre 1 y 10.';
        }
        $registrationError = validation_code($data['registro_universitario'], 'registro universitario', 30);
        if ($registrationError !== null) {
            $errors[] = $registrationError;
        }

        return $errors;
    }
}
