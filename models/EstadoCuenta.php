<?php

declare(strict_types=1);

/**
 * Reglas comunes para dar de baja o de alta una cuenta. Las pantallas de
 * Usuarios, Estudiantes y Tutores cambian el estado por caminos distintos
 * (boton Desactivar y formulario de edicion); todas pasan por aqui para que
 * ninguna deje grupos sin tutor, inscripciones de un estudiante sin acceso o
 * el sistema sin administrador.
 */
final class EstadoCuenta
{
    /** Motivo por el que la cuenta no puede pasar a inactiva, o null si puede. */
    public static function bloqueoDesactivar(int $userId): ?string
    {
        if ((int) (Auth::user()['id_usuario'] ?? 0) === $userId) {
            return 'No puede desactivar su propia cuenta.';
        }

        $pdo = Database::connection();
        $cuenta = self::cuenta($pdo, $userId);
        if ($cuenta === null) {
            return 'La cuenta no existe.';
        }
        if ($cuenta['estado'] !== 'activo') {
            return null;
        }

        if ($cuenta['nombre_rol'] === 'administrador') {
            $otros = $pdo->prepare(
                "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
                 WHERE r.nombre_rol = 'administrador' AND u.estado = 'activo' AND u.id_usuario <> :id"
            );
            $otros->execute(['id' => $userId]);
            if ((int) $otros->fetchColumn() === 0) {
                return 'Debe quedar al menos un administrador activo.';
            }
        }

        $vigentes = "'" . implode("','", Grupo::ESTADOS_VIGENTES) . "'";
        if ($cuenta['id_tutor'] !== null) {
            $grupos = $pdo->prepare("SELECT COUNT(*) FROM grupos_tutoria WHERE id_tutor = :t AND estado IN ({$vigentes})");
            $grupos->execute(['t' => $cuenta['id_tutor']]);
            $n = (int) $grupos->fetchColumn();
            if ($n > 0) {
                return sprintf('El tutor tiene %d grupo%s vigente%s. Asigna otro tutor a %s (Grupos → Cambiar tutor) antes de desactivar la cuenta.',
                    $n, $n === 1 ? '' : 's', $n === 1 ? '' : 's', $n === 1 ? 'ese grupo' : 'esos grupos');
            }
        }
        if ($cuenta['id_estudiante'] !== null) {
            $inscrito = $pdo->prepare(
                "SELECT COUNT(*) FROM inscripciones i INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                 WHERE i.id_estudiante = :e AND i.estado = 'inscrito' AND g.estado IN ({$vigentes})"
            );
            $inscrito->execute(['e' => $cuenta['id_estudiante']]);
            if ((int) $inscrito->fetchColumn() > 0) {
                return 'El estudiante está inscrito en un grupo vigente. Retíralo del grupo antes de desactivar la cuenta.';
            }
        }

        return null;
    }

    /**
     * Efectos de una baja ya aplicada: la demanda en espera del estudiante se
     * cancela (no puede recibir grupo) y las materias del tutor se reprocesan
     * (sus ofertas dejan de contar para el motor).
     */
    public static function despuesDeDesactivar(int $userId): void
    {
        try {
            $pdo = Database::connection();
            $cuenta = self::cuenta($pdo, $userId);
            if ($cuenta === null) {
                return;
            }
            if ($cuenta['id_estudiante'] !== null) {
                $pdo->prepare("UPDATE demanda_tutoria SET estado = 'cancelada' WHERE id_estudiante = :e AND estado = 'pendiente'")
                    ->execute(['e' => $cuenta['id_estudiante']]);
            }
            if ($cuenta['id_tutor'] !== null) {
                self::reprocesarMaterias((int) $cuenta['id_tutor']);
            }
        } catch (Throwable $exception) {
            error_log('Baja de cuenta ' . $userId . ': ' . $exception->getMessage());
        }
    }

    /** Un tutor reactivado vuelve a ofrecer sus materias: la demanda en espera se reprocesa. */
    public static function despuesDeActivar(int $userId): void
    {
        try {
            $cuenta = self::cuenta(Database::connection(), $userId);
            if ($cuenta !== null && $cuenta['id_tutor'] !== null) {
                self::reprocesarMaterias((int) $cuenta['id_tutor']);
            }
        } catch (Throwable $exception) {
            error_log('Alta de cuenta ' . $userId . ': ' . $exception->getMessage());
        }
    }

    /**
     * Aplica un cambio de estado pedido desde un formulario de edicion: valida la
     * baja antes de guardar y devuelve el efecto a ejecutar despues del commit.
     * Devuelve [error|null, callable|null].
     */
    public static function prepararCambio(int $userId, string $estadoActual, string $estadoNuevo): array
    {
        if ($estadoActual === $estadoNuevo) {
            return [null, null];
        }
        if ($estadoNuevo === 'inactivo') {
            $bloqueo = self::bloqueoDesactivar($userId);

            return $bloqueo !== null ? [$bloqueo, null] : [null, static fn () => self::despuesDeDesactivar($userId)];
        }

        return [null, static fn () => self::despuesDeActivar($userId)];
    }

    private static function reprocesarMaterias(int $tutorId): void
    {
        $motor = new AsignacionController();
        foreach ((new Tutor())->configuredMatterIds($tutorId) as $materiaId) {
            $motor->reprocesarMateria($materiaId);
        }
    }

    private static function cuenta(PDO $pdo, int $userId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT u.id_usuario, u.estado, r.nombre_rol,
                    (SELECT t.id_tutor FROM tutores t WHERE t.id_usuario = u.id_usuario LIMIT 1) AS id_tutor,
                    (SELECT e.id_estudiante FROM estudiantes e WHERE e.id_usuario = u.id_usuario LIMIT 1) AS id_estudiante
             FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = :id LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }
}
