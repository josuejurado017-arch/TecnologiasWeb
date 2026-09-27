-- Modalidades de Grado: expedientes, historial de etapas e importaciones del padron.
--
--   expedientes_mg: un estudiante en un proceso de grado (RN-MG-02: individual).
--     etapa_actual y expediente_etapas_mg se cambian siempre en la misma
--     transaccion. La lista de estados es PROVISIONAL (pregunta 2 al Coordinador).
--   importaciones_mg(+_detalle): evidencia de cada carga del CSV, fila por fila.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 044_mg_base.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS expedientes_mg (
  id_expediente INT NOT NULL AUTO_INCREMENT,
  id_estudiante INT NOT NULL,
  id_modalidad INT NOT NULL,
  id_cohorte INT NOT NULL,
  etapa_actual ENUM('previa','mg1','mg2','finalizado') NOT NULL DEFAULT 'previa',
  estado ENUM('activo','aprobado','reprobado','abandono','retirado') NOT NULL DEFAULT 'activo',
  titulo_trabajo VARCHAR(255) NULL,
  fecha_inicio DATE NOT NULL,
  fecha_cierre DATE NULL,
  observaciones VARCHAR(1000) NULL,
  origen ENUM('manual','importacion') NOT NULL DEFAULT 'manual',
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_expediente),
  UNIQUE KEY uq_expediente (id_estudiante, id_modalidad, id_cohorte),
  KEY idx_expediente_filtros (id_cohorte, etapa_actual, estado),
  CONSTRAINT fk_expediente_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
  CONSTRAINT fk_expediente_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades_grado (id_modalidad),
  CONSTRAINT fk_expediente_cohorte FOREIGN KEY (id_cohorte) REFERENCES cohortes_mg (id_cohorte),
  CONSTRAINT fk_expediente_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expediente_etapas_mg (
  id_etapa INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  etapa ENUM('previa','mg1','mg2','finalizado') NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  resultado VARCHAR(255) NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_etapa),
  KEY idx_etapa_expediente (id_expediente, fecha_inicio),
  CONSTRAINT fk_etapa_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_etapa_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importaciones_mg (
  id_importacion INT NOT NULL AUTO_INCREMENT,
  archivo VARCHAR(255) NOT NULL,
  id_usuario INT NULL,
  total_filas INT NOT NULL DEFAULT 0,
  creados INT NOT NULL DEFAULT 0,
  omitidos INT NOT NULL DEFAULT 0,
  errores INT NOT NULL DEFAULT 0,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_importacion),
  CONSTRAINT fk_importacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importaciones_mg_detalle (
  id_detalle INT NOT NULL AUTO_INCREMENT,
  id_importacion INT NOT NULL,
  fila INT NOT NULL,
  registro_universitario VARCHAR(30) NULL,
  resultado ENUM('creado','omitido','pendiente_cuenta','error') NOT NULL,
  mensaje VARCHAR(255) NOT NULL,
  id_expediente INT NULL,
  PRIMARY KEY (id_detalle),
  KEY idx_detalle_importacion (id_importacion, fila),
  CONSTRAINT fk_detalle_importacion FOREIGN KEY (id_importacion) REFERENCES importaciones_mg (id_importacion),
  CONSTRAINT fk_detalle_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
