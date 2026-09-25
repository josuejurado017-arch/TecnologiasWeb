<?php

declare(strict_types=1);

/**
 * Aprobacion de la oferta del tutor por materia (db/032): el tutor elige materia,
 * turnos y modalidad en "Mis materias", pero el motor y el catalogo del estudiante
 * solo la usan cuando la coordinacion la aprueba. La oferta no lleva espacio ni
 * ubicacion: eso pertenece al grupo y se define al revisarlo (GruposController).
 */
final class OfertaTutorController
{
    private TutorMateriaConfig $config;

    public function __construct()
    {
        $this->config = new TutorMateriaConfig();
    }

    public function pendientes(): array
    {
        return $this->config->pendientes();
    }

    public function countPendientes(): int
    {
        return $this->config->countPendientes();
    }

    /**
     * Aprueba la oferta y reprocesa la demanda en espera de la materia: el motor
     * puede armar grupos (que a su vez quedan por aprobar en Grupos de tutoria).
     * Devuelve [error|null, resumen|null].
     */
    public function aprobar(int $tutorId, int $materiaId, int $adminId): array
    {
        [$error, $historialId] = $this->config->aprobar($tutorId, $materiaId, $adminId);
        if ($error !== null) {
            return [$error, null];
        }

        $tutor = (new Tutor())->findById($tutorId);
        $materia = (new Materia())->findById($materiaId);
        if ($tutor !== null && $materia !== null) {
            try {
                (new Notificacion())->notifyOfertaAprobada(
                    Database::connection(),
                    (int) $tutor['id_usuario'],
                    (string) $materia['nombre_materia'],
                    (int) $historialId
                );
            } catch (Throwable $exception) {
                error_log('Notificacion oferta aprobada: ' . $exception->getMessage());
            }
        }

        $resultado = (new AsignacionController())->reprocesarMateria($materiaId);

        return [null, $resultado];
    }

    public function rechazar(int $tutorId, int $materiaId, string $motivo, int $adminId): ?string
    {
        [$error, $historialId] = $this->config->rechazar($tutorId, $materiaId, $motivo, $adminId);
        if ($error !== null) {
            return $error;
        }

        $tutor = (new Tutor())->findById($tutorId);
        $materia = (new Materia())->findById($materiaId);
        if ($tutor !== null && $materia !== null) {
            try {
                (new Notificacion())->notifyOfertaRechazada(
                    Database::connection(),
                    (int) $tutor['id_usuario'],
                    (string) $materia['nombre_materia'],
                    $motivo,
                    (int) $historialId
                );
            } catch (Throwable $exception) {
                error_log('Notificacion oferta rechazada: ' . $exception->getMessage());
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Materias sin tutor: propuesta de la coordinacion (db/039)
    // ------------------------------------------------------------------

    /**
     * La coordinacion asigna un tutor habilitado a una materia: queda como propuesta
     * hasta que el tutor la acepte. $input: id_tutor, turnos[], modalidad, cupo_recomendado.
     */
    public function proponer(int $materiaId, array $input, int $adminId): ?string
    {
        $materia = (new Materia())->findById($materiaId);
        if ($materia === null) {
            return 'La materia no existe.';
        }
        $tutorId = (int) filter_var($input['id_tutor'] ?? 0, FILTER_VALIDATE_INT);
        $tutor = $tutorId > 0 ? (new Tutor())->findById($tutorId) : null;
        if ($tutor === null || $tutor['estado'] !== 'activo' || $tutor['estado_docente'] !== 'aprobado') {
            return 'Elige un tutor habilitado.';
        }
        $turnos = array_values(array_unique(array_filter(
            is_array($input['turnos'] ?? null) ? $input['turnos'] : [],
            fn ($t): bool => is_string($t) && $this->config->isValidTurno($t)
        )));
        if (!$turnos) {
            return 'Elige al menos un turno.';
        }
        $modalidad = (string) ($input['modalidad'] ?? '');
        if (!in_array($modalidad, TutorMateriaConfig::modalidadesPermitidas((string) ($materia['modalidad_requerida'] ?? 'libre')), true)) {
            return 'Elige una modalidad que la materia admita.';
        }
        $cupo = filter_var($input['cupo_recomendado'] ?? null, FILTER_VALIDATE_INT);
        $cupo = in_array($cupo, TutorMateriaConfig::CUPOS_RECOMENDADOS, true) ? $cupo : null;

        [$error, $historialId] = $this->config->proponer($tutorId, $materiaId, [
            'turnos' => $turnos,
            'modalidad' => $modalidad,
            'cupo_recomendado' => $cupo,
        ], $adminId);
        if ($error !== null) {
            return $error;
        }

        try {
            $detalle = implode(', ', array_map(static fn (string $t): string => TutorMateriaConfig::TURNOS[$t]['label'], $turnos)) . ' · ' . $modalidad;
            (new Notificacion())->notifyTutorPropuesta(Database::connection(), (int) $tutor['id_usuario'], (string) $materia['nombre_materia'], $detalle, (int) $historialId);
        } catch (Throwable $exception) {
            error_log('Notificacion propuesta: ' . $exception->getMessage());
        }

        return null;
    }

    /**
     * El tutor acepta o rechaza la materia que le propuso la coordinacion. Si acepta,
     * el motor reprocesa la demanda en espera de la materia: con quorum se forman grupos.
     */
    public function responderPropuesta(int $tutorUserId, int $materiaId, bool $acepta, string $motivo): ?string
    {
        $tutorId = (new Tutoria())->tutorIdByUserId($tutorUserId);
        if ($tutorId === null) {
            return 'No se encontró tu perfil de tutor.';
        }
        [$error, $historialId] = $this->config->responderPropuesta($tutorId, $materiaId, $acepta, $motivo, $tutorUserId);
        if ($error !== null) {
            return $error;
        }

        $tutor = (new Tutor())->findById($tutorId);
        $materia = (new Materia())->findById($materiaId);
        try {
            (new Notificacion())->notifyAdminsPropuestaRespondida(
                Database::connection(),
                (string) ($materia['nombre_materia'] ?? 'la materia'),
                trim(($tutor['nombre'] ?? '') . ' ' . ($tutor['apellido'] ?? '')),
                $acepta,
                trim($motivo),
                (int) $historialId
            );
        } catch (Throwable $exception) {
            error_log('Notificacion propuesta respondida: ' . $exception->getMessage());
        }
        if ($acepta) {
            (new AsignacionController())->reprocesarMateria($materiaId);
        }

        return null;
    }
}
