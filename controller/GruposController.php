<?php

declare(strict_types=1);

/** Supervision administrativa de grupos: listado, historial y cancelacion. */
final class GruposController
{
    private Grupo $grupos;
    private Inscripcion $inscripciones;
    private HistorialGrupo $historial;
    private Demanda $demanda;

    public function __construct()
    {
        $this->grupos = new Grupo();
        $this->inscripciones = new Inscripcion();
        $this->historial = new HistorialGrupo();
        $this->demanda = new Demanda();
    }

    public function history(int $grupoId): array
    {
        return $this->historial->forGroup($grupoId);
    }

    public function find(int $grupoId): ?array
    {
        return $this->grupos->findBasic($grupoId);
    }

    /**
     * Cancela un grupo: cancela inscripciones, devuelve a los estudiantes a la demanda,
     * los notifica y registra el evento en el historial.
     */
    public function cancel(int $grupoId, string $motivo, int $adminUserId): ?string
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 4) {
            return 'Indica un motivo de al menos 4 caracteres.';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return 'El motivo contiene caracteres no validos.';
        }

        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null) {
            return 'El grupo no existe.';
        }
        if ($grupo['estado'] === 'cancelado') {
            return 'El grupo ya estaba cancelado.';
        }

        $estudiantes = $this->inscripciones->activeStudentsOfGroup($grupoId);
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $estadoAnterior = (string) $grupo['estado'];
            $this->inscripciones->cancelByGroup($grupoId);
            $this->grupos->cancel($grupoId, $motivo);
            foreach ($estudiantes as $est) {
                $this->demanda->record((int) $grupo['id_periodo'], (int) $grupo['id_materia'], (int) $est['id_estudiante']);
            }
            $this->historial->log($connection, $grupoId, 'cancelado', $estadoAnterior, 'cancelado', $adminUserId, $motivo);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo cancelar el grupo.';
        }

        try {
            $notif = new Notificacion();
            foreach ($estudiantes as $est) {
                $notif->notifyGroupCancelled($connection, $grupoId, (int) $est['id_usuario'], (string) $grupo['nombre_materia']);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion cancelacion: ' . $exception->getMessage());
        }

        return null;
    }
}
