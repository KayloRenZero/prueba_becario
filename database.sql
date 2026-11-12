-- =====================================================================
-- Base de datos: albardi_portal
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS albardi_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE albardi_portal;

-- Se eliminan en orden inverso a sus dependencias (claves foráneas).
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS servicios;
DROP TABLE IF EXISTS usuarios;

-- ---------------------------------------------------------------------
-- Usuarios del portal (administradores y clientes)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'Hash de password_hash(), nunca texto plano',
  rol ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_usuarios_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catálogo de servicios
-- ---------------------------------------------------------------------
CREATE TABLE servicios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  descripcion TEXT NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'DECIMAL y no FLOAT: el dinero exige exactitud',
  activo TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = visible en el sitio público',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_servicios_precio CHECK (precio >= 0),
  INDEX idx_servicios_activo (activo) -- la API pública filtra por activo = 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tickets de soporte
-- ---------------------------------------------------------------------
CREATE TABLE tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  asunto VARCHAR(150) NOT NULL,
  mensaje TEXT NOT NULL,
  estado ENUM('abierto','en_proceso','cerrado') NOT NULL DEFAULT 'abierto',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pagos de servicios
-- RESTRICT: no se puede borrar un usuario o servicio que tenga pagos,
-- para no perder el historial contable.
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  servicio_id INT NOT NULL,
  monto DECIMAL(10,2) NOT NULL COMMENT 'Precio congelado al momento de la compra',
  estado ENUM('pendiente','pagado','fallido') NOT NULL DEFAULT 'pendiente',
  referencia VARCHAR(64) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_pagos_monto CHECK (monto >= 0),
  CONSTRAINT uq_pagos_referencia UNIQUE (referencia),
  CONSTRAINT fk_pagos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pagos_servicio FOREIGN KEY (servicio_id)
    REFERENCES servicios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Datos semilla
-- admin@albardi.com   / Admin123!    (rol admin: gestiona servicios)
-- cliente@albardi.com / Cliente123!  (rol cliente: compra servicios)
-- ---------------------------------------------------------------------
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Administrador', 'admin@albardi.com',
 '$2y$10$c/rmtPi6lR/UKFNLU8Jb4u9yDMyuPCHrgG3VtaEYwTfTYDwrwOBty', 'admin'),
('Cliente Demo', 'cliente@albardi.com',
 '$2y$10$E45sBr3iJKTjmdQZZpDdcOQG7ZXVs7nnxTmAdpAENaKmIYZb4kBKG', 'cliente');

INSERT INTO servicios (nombre, descripcion, precio) VALUES
('Desarrollo Web', 'Sitios y aplicaciones web a medida.', 15000.00),
('Mantenimiento', 'Soporte y actualizaciones mensuales.', 3500.00),
('Integración de APIs', 'Conexión con pasarelas y servicios externos.', 8000.00),
('QA y Testing', 'Detección y corrección de bugs.', 2500.00);
