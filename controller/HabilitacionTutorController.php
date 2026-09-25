<?php

declare(strict_types=1);

/**
 * Habilitacion docente (db/028): el administrador aprueba o rechaza a los tutores
 * autorregistrados. Es independiente del estado de la cuenta: un tutor pendiente o
 * rechazado inicia sesion, pero el motor no le propone grupos.
 */
final class HabilitacionTutorController
{
    /** Transiciones permitidas en esta fase: estado nuevo => estados de origen. */
    private const TRANSICIONES = [
        'aprobado' => ['pendiente', 'rechazado'],
        'rechazado' => ['pendiente'],
    ];

    private Tutor $tutores;

    public function __construct()
    {
        $this->tutores = new Tutor();
    }

    public function bandeja(): array
    {
        return $this->tutores->forReview();
    }

    /**
     * Aprueba al tutor y, fuera de la transaccion, reprocesa la demanda en espera de
     * sus materias configuradas: el motor arma los grupos (que a su vez quedan por
     * aprobar). Devuelve [error|null, resumen].
     */
    public function aprobar(int $tutorId, int $adminId): array
    {
        $tutor = $this->tutores->findById($tutorId);
        if ($tutor === null) {
            return ['El tutor no existe.', null];
        }
        $anterior = (string) $tutor['estado_docente'];
        if (!in_array($anterior, self::TRANSICIONES['aprobado'], true)) {
            return ['Solo se puede aprobar a un tutor pendiente o rechazado.', null];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (!$this->tutores->transitionEstadoDocente($pdo, $tutorId, $anterior, 'aprobado', null, $adminId)) {
                $pdo->rollBack();
                return ['Otro administrador ya revisó a este tutor. Recarga la página.', null];
            }
            $historialId = (int) $pdo->lastInsertId();
            (new Notificacion())->notifyTutorApproved($pdo, (int) $tutor['id_usuario'], $historialId);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Aprobar tutor ' . $tutorId . ': ' . $exception->getMessage());
            return ['No fue posible aprobar al tutor.', null];
        }

        // reprocesarMateria() maneja sus propias transacciones y errores: un fallo
        // aqui no revierte la aprobacion.
        $materias = $this->tutores->configuredMatterIds($tutorId);
        $resumen = ['materias' => count($materias), 'atendidos' => 0, 'pendientes' => 0];
        $motor = new AsignacionController();
        foreach ($materias as $materiaId) {
            $resultado = $motor->reprocesarMateria($materiaId);
            $resumen['atendidos'] += $resultado['atendidos'];
            $resumen['pendientes'] += $resultado['pendientes'];
        }

        return [null, $resumen];
    }

    public function rechazar(int $tutorId, string $motivo, int $adminId): ?string
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 10) {
            return 'Indica el motivo del rechazo (al menos 10 caracteres).';
        }
        if (mb_strlen($motivo) > 500 || preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return 'El motivo no puede superar 500 caracteres ni contener caracteres no válidos.';
        }

        $tutor = $this->tutores->findById($tutorId);
        if ($tutor === null) {
            return 'El tutor no existe.';
        }
        if (!in_array($tutor['estado_docente'], self::TRANSICIONES['rechazado'], true)) {
            return 'Solo se puede rechazar a un tutor pendiente.';
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (!$this->tutores->transitionEstadoDocente($pdo, $tutorId, 'pendiente', 'rechazado', $motivo, $adminId)) {
                $pdo->rollBack();
                return 'Otro administrador ya revisó a este tutor. Recarga la página.';
            }
            $historialId = (int) $pdo->lastInsertId();
            (new Notificacion())->notifyTutorRejected($pdo, (int) $tutor['id_usuario'], $motivo, $historialId);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Rechazar tutor ' . $tutorId . ': ' . $exception->getMessage());
            return 'No fue posible rechazar al tutor.';
        }

        return null;
    }
}
