-- Modalidades de Grado: asignacion de tutor (con historial) y documentos generados.
--
--   asignaciones_tutor_mg: nunca se borra (RN-MG-07). Un cambio cierra la vigente
--     ('reemplazada') y crea otra. "Una sola vigente por expediente" se refuerza
--     con la columna generada vigente_expediente + UNIQUE (NULL no choca).
--   plantillas_documento_mg: HTML con {{variables}} de lista blanca, editable en
--     pantalla. Las iniciales son PROVISIONALES hasta tener las reales de UPDS.
--   documentos_generados_mg: snapshot inmutable de cada documento emitido, con
--     su numero correlativo. Reimprimir muestra exactamente lo que se emitio.
--   contadores_documento_mg: correlativo por tipo y anio (se bloquea la fila).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 045_mg_expedientes.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS asignaciones_tutor_mg (
  id_asignacion INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  id_tutor INT NOT NULL,
  fecha_asignacion DATE NOT NULL,
  fecha_fin DATE NULL,
  estado ENUM('vigente','finalizada','reemplazada') NOT NULL DEFAULT 'vigente',
  motivo_fin VARCHAR(500) NULL,
  fecha_nota_renuncia DATE NULL,
  referencia_decanatura VARCHAR(100) NULL,
  disponibilidad_consultada TINYINT(1) NOT NULL DEFAULT 0,
  observaciones VARCHAR(500) NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vigente_expediente INT AS (IF(estado = 'vigente', id_expediente, NULL)) STORED,
  PRIMARY KEY (id_asignacion),
  UNIQUE KEY uq_asignacion_vigente (vigente_expediente),
  KEY idx_asignacion_expediente (id_expediente, fecha_asignacion),
  KEY idx_asignacion_tutor (id_tutor, estado),
  CONSTRAINT fk_asignacion_mg_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_asignacion_mg_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor),
  CONSTRAINT fk_asignacion_mg_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plantillas_documento_mg (
  id_plantilla INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  prefijo VARCHAR(10) NOT NULL,
  cuerpo_html MEDIUMTEXT NOT NULL,
  version INT NOT NULL DEFAULT 1,
  actualizado_por INT NULL,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_plantilla),
  UNIQUE KEY uq_plantilla_codigo (codigo),
  CONSTRAINT fk_plantilla_mg_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO plantillas_documento_mg (codigo, nombre, prefijo, cuerpo_html) VALUES
('CARTA_ASIGNACION_TUTOR', 'Carta de asignación de tutor', 'CAT',
'<p class="doc-provisional">[PLANTILLA PROVISIONAL]</p>
<p class="doc-derecha">{{ciudad}}, {{fecha_larga}}</p>
<p class="doc-derecha"><strong>{{numero}}</strong></p>
<p>Señor(a):<br><strong>{{destinatario_nombre}}</strong><br>Presente.-</p>
<p class="doc-ref"><strong>Ref.: Asignación de tutor de {{modalidad}}</strong></p>
<p>Por medio de la presente se comunica que, de acuerdo con la revisión de afinidad y experticia realizada por Decanatura ({{referencia_decanatura}}), se asigna como tutor(a) al docente <strong>{{tutor_nombre}}</strong> para acompañar al estudiante <strong>{{estudiante_nombre}}</strong>, R.U. {{registro_universitario}}, de la carrera de {{carrera}}, en la modalidad <strong>{{modalidad}}</strong> ({{cohorte}}).</p>
<p>Tema: <em>{{tema}}</em></p>
<p>El acompañamiento comprende las etapas MG1 y MG2 hasta la conclusión del proceso.</p>
<p>Sin otro particular, saludamos a usted atentamente.</p>
<p class="doc-firma">{{firma}}</p>'),
('CITACION_TRIBUNAL', 'Citación a tribunal', 'CIT',
'<p class="doc-provisional">[PLANTILLA PROVISIONAL]</p>
<p class="doc-derecha">{{ciudad}}, {{fecha_larga}}</p>
<p class="doc-derecha"><strong>{{numero}}</strong></p>
<p>Señor(a):<br><strong>{{destinatario_nombre}}</strong><br>Tribunal evaluador<br>Presente.-</p>
<p class="doc-ref"><strong>Ref.: Citación a defensa de {{etapa}}</strong></p>
<p>Se le cita en calidad de tribunal a la defensa de <strong>{{etapa}}</strong> del estudiante <strong>{{estudiante_nombre}}</strong>, R.U. {{registro_universitario}}, modalidad {{modalidad}}, tema <em>{{tema}}</em>.</p>
<p><strong>Fecha:</strong> {{fecha_defensa}}<br><strong>Hora:</strong> {{hora_inicio}} a {{hora_fin}}<br><strong>Ambiente:</strong> {{ambiente}}</p>
<p>Tribunales: {{tribunales}}. Tutor(a): {{tutor_nombre}}.</p>
<p>Sin otro particular, saludamos a usted atentamente.</p>
<p class="doc-firma">{{firma}}</p>'),
('CITACION_ESTUDIANTE', 'Citación al estudiante', 'CIE',
'<p class="doc-provisional">[PLANTILLA PROVISIONAL]</p>
<p class="doc-derecha">{{ciudad}}, {{fecha_larga}}</p>
<p class="doc-derecha"><strong>{{numero}}</strong></p>
<p>Señor(a):<br><strong>{{destinatario_nombre}}</strong><br>R.U. {{registro_universitario}}<br>Presente.-</p>
<p class="doc-ref"><strong>Ref.: Citación a defensa de {{etapa}}</strong></p>
<p>Se le comunica que su defensa de <strong>{{etapa}}</strong> en la modalidad {{modalidad}}, tema <em>{{tema}}</em>, se realizará en la siguiente fecha:</p>
<p><strong>Fecha:</strong> {{fecha_defensa}}<br><strong>Hora:</strong> {{hora_inicio}} a {{hora_fin}}<br><strong>Ambiente:</strong> {{ambiente}}</p>
<p>Tribunales: {{tribunales}}. Tutor(a): {{tutor_nombre}}.</p>
<p>Sin otro particular, saludamos a usted atentamente.</p>
<p class="doc-firma">{{firma}}</p>');

CREATE TABLE IF NOT EXISTS contadores_documento_mg (
  prefijo VARCHAR(10) NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (prefijo, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos_generados_mg (
  id_documento INT NOT NULL AUTO_INCREMENT,
  id_plantilla INT NOT NULL,
  version_plantilla INT NOT NULL,
  codigo VARCHAR(40) NOT NULL,
  id_expediente INT NOT NULL,
  id_asignacion INT NULL,
  id_defensa INT NULL,
  destinatario VARCHAR(200) NOT NULL,
  numero VARCHAR(40) NOT NULL,
  contenido_snapshot MEDIUMTEXT NOT NULL,
  generado_por INT NULL,
  fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_documento),
  UNIQUE KEY uq_documento_numero (numero),
  KEY idx_documento_expediente (id_expediente, fecha_generacion),
  KEY idx_documento_defensa (id_defensa),
  CONSTRAINT fk_documento_plantilla FOREIGN KEY (id_plantilla) REFERENCES plantillas_documento_mg (id_plantilla),
  CONSTRAINT fk_documento_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_documento_asignacion FOREIGN KEY (id_asignacion) REFERENCES asignaciones_tutor_mg (id_asignacion),
  CONSTRAINT fk_documento_usuario FOREIGN KEY (generado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
