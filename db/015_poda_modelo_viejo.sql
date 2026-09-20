-- Rediseno institucional de tutorias UPDS (Fase 6: poda segura).
-- Elimina tablas obsoletas que ya no usa el modelo institucional.
-- Ejecutar despues de 014_historial_grupo.sql sobre testdb.
--
-- NOTA: se conservan a proposito (siguen en uso por codigo vivo):
--   - tutorias, asistencias_tutorias, evaluaciones_tutoria, historial_tutorias
--     (alimentan el dashboard y los reportes "clasicos"; su retiro exige
--      reescribir models/Dashboard.php y las vistas antiguas).
--   - permisos_usuario (motor de permisos por usuario en models/Permiso.php).
--   - tutor_materia (lo usa el motor de asignacion y "Mis materias" del tutor).

USE testdb;

-- Vestigio de un MVP de biblioteca; sin referencias en el codigo.
DROP TABLE IF EXISTS libros;

-- Flujo de horario especial: el estudiante ya no propone fecha/hora en el
-- modelo institucional (el sistema asigna el grupo automaticamente).
DROP TABLE IF EXISTS solicitudes_horario_especial;
