-- Formacion de grupos por quorum: estados claros de cada materia.
--
--   Interes registrado  1 a (minimo-1) estudiantes esperando la materia en un turno
--                       compatible: NO hay grupo (demanda 'esperando_companeros').
--   En formacion        grupo creado al llegar al minimo del periodo (por defecto 3),
--                       espera la revision de la coordinacion (estado 'por_aprobar').
--   Listo para revision el mismo grupo con 8 o mas estudiantes (Grupo::UMBRAL_GRUPO_NORMAL):
--                       prioridad en la bandeja y Lunes a Viernes al aprobar.
--   Confirmado          aprobado con aula o enlace; se generan las sesiones.
--   En curso            automatico al llegar la fecha de la primera sesion.
--   Finalizado          cierre del periodo.
--
-- Como ningun grupo nace con menos del minimo, desaparece el estado 'formacion'
-- posterior a la aprobacion (grupo aprobado sin llegar al minimo): los que existan
-- ya estaban aprobados y con calendario, pasan a 'confirmado'.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 034_oferta_sin_espacio.sql sobre testdb.

USE testdb;

ALTER TABLE demanda_tutoria
  MODIFY COLUMN motivo ENUM('sin_tutor','sin_horario','grupo_cancelado','esperando_companeros') NOT NULL DEFAULT 'sin_horario';

ALTER TABLE periodos
  MODIFY COLUMN cupo_min_grupo TINYINT UNSIGNED NOT NULL DEFAULT 3;

INSERT INTO historial_grupo (id_grupo, tipo_evento, estado_anterior, estado_nuevo, id_usuario, motivo)
SELECT id_grupo, 'confirmado', 'formacion', 'confirmado', NULL, 'Regla de quorum (db/035): todo grupo aprobado queda confirmado.'
FROM grupos_tutoria WHERE estado = 'formacion';

UPDATE grupos_tutoria SET estado = 'confirmado', fecha_estado = NOW() WHERE estado = 'formacion';
