-- Gestion manual de la coordinacion sobre grupos y ofertas.
--
-- 1. Asignar tutor a una materia sin tutor: la coordinacion crea una oferta en
--    estado 'propuesta' (turnos, modalidad y cupo) y el tutor la ACEPTA o la
--    RECHAZA desde "Mis materias". Aceptada pasa directo a 'aprobado' (la propuso
--    la coordinacion) y el motor reprocesa la demanda en espera; rechazada queda
--    'rechazado' con el motivo del tutor. El motor solo usa ofertas 'aprobado'.
-- 2. Cambiar el tutor de un grupo, inscribir y retirar estudiantes a mano: no
--    necesitan columnas nuevas (inscripciones.origen ya admite 'manual_admin';
--    los eventos quedan en historial_grupo).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente (MODIFY de ENUM ampliado).
-- Ejecutar despues de 038_datos_operativos.sql sobre testdb.

USE testdb;

ALTER TABLE tutor_materia_config
  MODIFY COLUMN estado ENUM('pendiente','aprobado','rechazado','propuesta') NOT NULL DEFAULT 'aprobado';

ALTER TABLE tutor_materia_historial
  MODIFY COLUMN estado_anterior ENUM('pendiente','aprobado','rechazado','propuesta') NULL,
  MODIFY COLUMN estado_nuevo ENUM('pendiente','aprobado','rechazado','propuesta') NOT NULL;
