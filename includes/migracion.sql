-- ============================================================
--  CineFlow — Script de Migración v1 → v2.0
-- ============================================================
--  Propósito : Adaptar la tabla reservas para clientes invitados
--              (nombre + email en lugar de usuario_id).
--              Agregar estado 'utilizada' para check-in de admin.
--
--  INSTRUCCIONES:
--    Ejecutar en phpMyAdmin sobre la BD 'cineflow' EXISTENTE.
--    NO ejecutar si vas a hacer instalación limpia (usa schema.sql).
-- ============================================================

USE cineflow;

-- ── Paso 1: Agregar columnas de cliente invitado ─────────────
ALTER TABLE reservas
    ADD COLUMN nombre_cliente VARCHAR(150) NOT NULL DEFAULT 'Cliente' AFTER asiento_id,
    ADD COLUMN email_cliente  VARCHAR(255) NOT NULL DEFAULT 'sin@email.cl' AFTER nombre_cliente;

-- ── Paso 2: Migrar datos existentes (copiar del usuario) ──────
UPDATE reservas r
JOIN usuarios u ON u.id = r.usuario_id
SET r.nombre_cliente = u.nombre,
    r.email_cliente  = u.email
WHERE r.usuario_id IS NOT NULL;

-- ── Paso 3: Eliminar FK y columna usuario_id ──────────────────
ALTER TABLE reservas
    DROP FOREIGN KEY fk_reserva_usuario;

ALTER TABLE reservas
    DROP INDEX idx_usuario_reservas;

ALTER TABLE reservas
    DROP COLUMN usuario_id;

-- ── Paso 4: Índice por email + actualizar ENUM estado ─────────
ALTER TABLE reservas
    ADD INDEX idx_email_cliente (email_cliente);

ALTER TABLE reservas
    MODIFY COLUMN estado
        ENUM('pendiente','confirmada','cancelada','expirada','utilizada')
        NOT NULL DEFAULT 'confirmada';

ALTER TABLE reservas
    MODIFY COLUMN codigo_reserva CHAR(10) NOT NULL
        COMMENT 'Código único CF-XXXXXX';

-- ── Paso 5: Actualizar vista ──────────────────────────────────
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

SELECT '✅ Migración completada correctamente.' AS resultado;
