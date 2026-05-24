-- ============================================================
--  CineFlow - Plataforma de Gestión y Venta de Entradas
-- ============================================================
--  Archivo   : schema.sql
--  Versión   : 2.0 — Clientes invitados (nombre + email)
--  Propósito : Diseño completo de la base de datos.
--
--  INSTRUCCIONES DE USO:
--  1. Abrir phpMyAdmin (o MySQL Workbench / terminal).
--  2. Crear la base de datos 'cineflow' si no existe.
--  3. Seleccionarla y ejecutar todo este script.
--  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
-- ============================================================

CREATE DATABASE IF NOT EXISTS cineflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cineflow;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reservas;
DROP TABLE IF EXISTS asientos;
DROP TABLE IF EXISTS funciones;
DROP TABLE IF EXISTS peliculas;
DROP TABLE IF EXISTS salas;
DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;


-- ─────────────────────────────────────────────────────────────
--  TABLA 1: usuarios  (solo administradores del cine)
--  Los clientes NO necesitan registrarse. Solo los admins
--  tienen cuenta en este sistema.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE usuarios (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(100)    NOT NULL,
    email         VARCHAR(150)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL                  COMMENT 'Hash bcrypt',
    rol           ENUM('admin')   NOT NULL DEFAULT 'admin',
    activo        TINYINT(1)      NOT NULL DEFAULT 1,
    fecha_registro DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Administradores del sistema';


-- ─────────────────────────────────────────────────────────────
--  TABLA 2: peliculas
-- ─────────────────────────────────────────────────────────────
CREATE TABLE peliculas (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    titulo         VARCHAR(200)   NOT NULL,
    genero         VARCHAR(80)    NOT NULL,
    sinopsis       TEXT               NULL,
    clasificacion  ENUM('TE','TE+7','MA+14','MA+18') NOT NULL DEFAULT 'TE',
    duracion_min   SMALLINT UNSIGNED  NULL,
    imagen         VARCHAR(300)       NULL                  COMMENT 'Ruta: uploads/peliculas/imagen.jpg',
    activa         TINYINT(1)     NOT NULL DEFAULT 1,
    fecha_estreno  DATE               NULL,

    PRIMARY KEY (id),
    INDEX idx_genero (genero),
    INDEX idx_activa (activa)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de películas';


-- ─────────────────────────────────────────────────────────────
--  TABLA 3: salas
-- ─────────────────────────────────────────────────────────────
CREATE TABLE salas (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(50)    NOT NULL,
    capacidad      SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    tipo           ENUM('estandar','vip','4dx','imax') NOT NULL DEFAULT 'estandar',
    activa         TINYINT(1)     NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    UNIQUE KEY uq_nombre_sala (nombre)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Salas físicas del cine';


-- ─────────────────────────────────────────────────────────────
--  TABLA 4: asientos  (todos los asientos físicos de cada sala)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE asientos (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    sala_id        INT UNSIGNED   NOT NULL,
    fila           CHAR(2)        NOT NULL,
    numero         TINYINT UNSIGNED NOT NULL,
    tipo           ENUM('normal','preferencial','discapacidad') NOT NULL DEFAULT 'normal',

    PRIMARY KEY (id),
    UNIQUE KEY uq_asiento_sala (sala_id, fila, numero),
    CONSTRAINT fk_asiento_sala
        FOREIGN KEY (sala_id) REFERENCES salas(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Asientos físicos de cada sala';


-- ─────────────────────────────────────────────────────────────
--  TABLA 5: funciones
-- ─────────────────────────────────────────────────────────────
CREATE TABLE funciones (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    pelicula_id    INT UNSIGNED   NOT NULL,
    sala_id        INT UNSIGNED   NOT NULL,
    fecha_hora     DATETIME       NOT NULL,
    precio         DECIMAL(8,2)   NOT NULL,
    idioma         ENUM('subtitulada','doblada','original') NOT NULL DEFAULT 'subtitulada',
    activa         TINYINT(1)     NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    UNIQUE KEY uq_sala_horario (sala_id, fecha_hora),
    INDEX idx_pelicula_fecha (pelicula_id, fecha_hora),
    INDEX idx_fecha_hora (fecha_hora),

    CONSTRAINT fk_funcion_pelicula
        FOREIGN KEY (pelicula_id) REFERENCES peliculas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_funcion_sala
        FOREIGN KEY (sala_id) REFERENCES salas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Funciones (proyecciones) programadas';


-- ─────────────────────────────────────────────────────────────
--  TABLA 6: reservas  ← LA MÁS CRÍTICA
--
--  ESTADOS:
--  - 'pendiente'   : Asientos bloqueados, esperando confirmación.
--  - 'confirmada'  : Reserva completada. Asiento asegurado.
--  - 'cancelada'   : Reserva anulada, asiento liberado.
--  - 'expirada'    : Tiempo límite vencido, asiento liberado.
--  - 'utilizada'   : Ticket validado en taquilla (check-in).
--
--  DOBLE PROTECCIÓN ANTI-DUPLICADO:
--  1. SELECT ... FOR UPDATE en PHP (bloquea la fila).
--  2. UNIQUE KEY (funcion_id, asiento_id) en BD (última defensa).
-- ─────────────────────────────────────────────────────────────
CREATE TABLE reservas (
    id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    funcion_id       INT UNSIGNED   NOT NULL,
    asiento_id       INT UNSIGNED   NOT NULL,
    nombre_cliente   VARCHAR(150)   NOT NULL                  COMMENT 'Nombre del cliente (sin registro)',
    email_cliente    VARCHAR(255)   NOT NULL                  COMMENT 'Correo para enviar confirmación',
    estado           ENUM('pendiente','confirmada','cancelada','expirada','utilizada')
                                    NOT NULL DEFAULT 'confirmada',
    fecha_reserva    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME           NULL,
    codigo_reserva   CHAR(10)       NOT NULL                  COMMENT 'Código único CF-XXXXXX',

    PRIMARY KEY (id),
    UNIQUE KEY uq_funcion_asiento (funcion_id, asiento_id),
    INDEX idx_email_cliente   (email_cliente),
    INDEX idx_codigo_reserva  (codigo_reserva),
    INDEX idx_estado          (estado),
    INDEX idx_fecha_expiracion (fecha_expiracion),

    CONSTRAINT fk_reserva_funcion
        FOREIGN KEY (funcion_id) REFERENCES funciones(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reserva_asiento
        FOREIGN KEY (asiento_id) REFERENCES asientos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Reservas de asientos; núcleo del negocio';


-- ─────────────────────────────────────────────────────────────
--  DATOS DE PRUEBA
-- ─────────────────────────────────────────────────────────────

-- Administrador (password: Admin123!)
INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
('Administrador', 'admin@cineflow.cl',
 '$2y$12$GVgAUjB9rYkfRW.O/wXHgONwqrQzBqPzFm0LfFp8WKH9XrZ1FVkCu', 'admin');

-- Películas
INSERT INTO peliculas (titulo, genero, sinopsis, clasificacion, duracion_min, imagen, fecha_estreno) VALUES
('Dune: Parte Dos',
 'Ciencia Ficción',
 'Paul Atreides se une a los Fremen y emprende un viaje de venganza contra los conspiradores que destruyeron a su familia.',
 'MA+14', 166, 'uploads/peliculas/dune2.jpg', '2024-03-01'),

('Deadpool & Wolverine',
 'Acción / Comedia',
 'Deadpool recluta a Wolverine para salvar su universo, aunque eso signifique irritar al temible TVA.',
 'MA+18', 127, 'uploads/peliculas/deadpool3.jpg', '2024-07-26'),

('El Señor de los Anillos: La Guerra de los Rohirrim',
 'Fantasía / Animación',
 'La historia épica del rey Helm Hammerhand y la heroica defensa del Abismo de Helm.',
 'MA+14', 134, 'uploads/peliculas/rohirrim.jpg', '2024-12-13'),

('Intensamente 2',
 'Animación / Familiar',
 'Riley enfrenta la adolescencia y sus emociones se amplían con nuevos y caóticos sentimientos.',
 'TE', 100, 'uploads/peliculas/intensamente2.jpg', '2024-06-14');

-- Salas
INSERT INTO salas (nombre, capacidad, tipo) VALUES
('Sala 1',   80, 'estandar'),
('Sala 2',   80, 'estandar'),
('Sala VIP', 40, 'vip'),
('Sala 4DX', 60, '4dx');

-- Asientos Sala 1 (A-H × 10 = 80 asientos)
INSERT INTO asientos (sala_id, fila, numero, tipo)
SELECT 1, fila, numero,
       CASE WHEN fila = 'A' THEN 'preferencial' ELSE 'normal' END
FROM (
    SELECT 'A' AS fila UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D'
    UNION SELECT 'E' UNION SELECT 'F' UNION SELECT 'G' UNION SELECT 'H'
) filas
CROSS JOIN (
    SELECT 1 AS numero UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
) numeros;

-- Asientos Sala 2 (misma distribución)
INSERT INTO asientos (sala_id, fila, numero, tipo)
SELECT 2, fila, numero,
       CASE WHEN fila = 'A' THEN 'preferencial' ELSE 'normal' END
FROM (
    SELECT 'A' AS fila UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D'
    UNION SELECT 'E' UNION SELECT 'F' UNION SELECT 'G' UNION SELECT 'H'
) filas
CROSS JOIN (
    SELECT 1 AS numero UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
) numeros;

-- Asientos Sala VIP (A-E × 8 = 40 asientos, todos preferenciales)
INSERT INTO asientos (sala_id, fila, numero, tipo)
SELECT 3, fila, numero, 'preferencial'
FROM (
    SELECT 'A' AS fila UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D' UNION SELECT 'E'
) filas
CROSS JOIN (
    SELECT 1 AS numero UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
) numeros;

-- Asientos Sala 4DX (A-F × 10 = 60 asientos)
INSERT INTO asientos (sala_id, fila, numero, tipo)
SELECT 4, fila, numero, 'normal'
FROM (
    SELECT 'A' AS fila UNION SELECT 'B' UNION SELECT 'C'
    UNION SELECT 'D' UNION SELECT 'E' UNION SELECT 'F'
) filas
CROSS JOIN (
    SELECT 1 AS numero UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
) numeros;

-- Funciones para los próximos días
INSERT INTO funciones (pelicula_id, sala_id, fecha_hora, precio, idioma) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL '14:30' HOUR_MINUTE, 5500, 'subtitulada'),
(1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL '18:00' HOUR_MINUTE, 5500, 'subtitulada'),
(1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL '20:30' HOUR_MINUTE, 6000, 'subtitulada'),
(2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL '16:00' HOUR_MINUTE, 5500, 'doblada'),
(2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL '20:00' HOUR_MINUTE, 5500, 'subtitulada'),
(3, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL '17:00' HOUR_MINUTE, 8500, 'subtitulada'),
(3, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL '19:00' HOUR_MINUTE, 8500, 'subtitulada'),
(4, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL '11:00' HOUR_MINUTE, 7000, 'doblada'),
(4, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL '15:00' HOUR_MINUTE, 7000, 'doblada');


-- ─────────────────────────────────────────────────────────────
--  VISTA: v_asientos_disponibles
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_asientos_disponibles AS
SELECT
    a.id          AS asiento_id,
    a.sala_id,
    a.fila,
    a.numero,
    a.tipo        AS tipo_asiento,
    f.id          AS funcion_id,
    f.fecha_hora,
    f.precio,
    CASE
        WHEN r.id IS NOT NULL THEN 'ocupado'
        ELSE 'libre'
    END AS estado
FROM asientos a
JOIN funciones f ON f.sala_id = a.sala_id
LEFT JOIN reservas r
    ON  r.asiento_id = a.id
    AND r.funcion_id = f.id
    AND r.estado IN ('pendiente', 'confirmada')
WHERE f.activa = 1;


-- ─────────────────────────────────────────────────────────────
--  VERIFICACIÓN
-- ─────────────────────────────────────────────────────────────
SELECT 'usuarios'  AS tabla, COUNT(*) AS registros FROM usuarios
UNION ALL SELECT 'peliculas',  COUNT(*) FROM peliculas
UNION ALL SELECT 'salas',      COUNT(*) FROM salas
UNION ALL SELECT 'asientos',   COUNT(*) FROM asientos
UNION ALL SELECT 'funciones',  COUNT(*) FROM funciones;
