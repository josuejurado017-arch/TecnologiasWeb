-- Modalidades de Grado: solicitud del estudiante y aprobacion de la Coordinacion.
--
--   solicitudes_mg: el estudiante elige modalidad, propone un tema y adjunta un
--     documento obligatorio (record/certificado de notas, foto o PDF). La
--     Coordinacion lo revisa a mano y lo aprueba (crea el expediente), lo observa
--     (el estudiante corrige y reenvia) o lo rechaza con motivo. Nada se borra.
--     "Una sola solicitud abierta por estudiante" (pendiente u observada) se
--     refuerza con la columna generada abierta_estudiante + UNIQUE (NULL no choca).
--   situacion: lo que declara el estudiante. 'cursando_ultimo' sube su record de notas
--     hasta hoy y la Coordinacion lo aprueba a la etapa previa (talleres) mientras
--     termina; 'egresado' sube su certificado completo y puede entrar a MG1. La
--     Coordinacion verifica el documento y decide la etapa. [PENDIENTE] confirmar
--     la regla con el Coordinador.
--   El archivo NO vive en la base ni en la carpeta publica: se guarda en
--     storage/mg_solicitudes con nombre aleatorio y se sirve solo por PHP a su
--     dueño y a la Coordinacion. Aqui quedan nombre, tipo, tamano y hash.
--   expedientes_mg.origen gana el valor 'solicitud' (expediente creado al aprobar).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 048_mg_seguimiento.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS solicitudes_mg (
  id_solicitud INT NOT NULL AUTO_INCREMENT,
  id_estudiante INT NOT NULL,
  id_modalidad INT NOT NULL,
  situacion ENUM('cursando_ultimo','egresado') NOT NULL DEFAULT 'egresado',
  titulo_propuesto VARCHAR(255) NULL,
  mensaje VARCHAR(1000) NULL,
  documento_archivo VARCHAR(80) NOT NULL,
  documento_nombre VARCHAR(150) NOT NULL,
  documento_mime VARCHAR(50) NOT NULL,
  documento_tamano INT NOT NULL,
  documento_hash CHAR(64) NOT NULL,
  estado ENUM('pendiente','observada','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  motivo_revision VARCHAR(500) NULL,
  id_revisor INT NULL,
  id_expediente INT NULL,
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fecha_revision DATETIME NULL,
  abierta_estudiante INT AS (IF(estado IN ('pendiente','observada'), id_estudiante, NULL)) STORED,
  PRIMARY KEY (id_solicitud),
  UNIQUE KEY uq_solicitud_abierta (abierta_estudiante),
  KEY idx_solicitud_estado (estado, fecha_solicitud),
  KEY idx_solicitud_estudiante (id_estudiante, fecha_solicitud),
  CONSTRAINT fk_solicitud_mg_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
  CONSTRAINT fk_solicitud_mg_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades_grado (id_modalidad),
  CONSTRAINT fk_solicitud_mg_revisor FOREIGN KEY (id_revisor) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
  CONSTRAINT fk_solicitud_mg_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parametros_mg (clave, valor, tipo, descripcion, fuente, estado_evidencia) VALUES
  ('semestre_minimo_solicitud_mg', '9', 'entero', 'Semestre desde el que un estudiante puede solicitar su modalidad de grado. Por debajo de ese semestre no se ofrece la solicitud ni se acepta. Un egresado con semestre desactualizado pide a la Coordinacion que lo corrija.', 'Equipo', 'propuesta');

ALTER TABLE expedientes_mg
  MODIFY origen ENUM('manual','importacion','solicitud') NOT NULL DEFAULT 'manual';
