-- Base de datos: albardi_portal
-- Importar en phpMyAdmin o: mysql -u root -p < database.sql

CREATE DATABASE IF NOT EXISTS albardi_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE albardi_portal;

-- Usuarios
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol ENUM('admin','cliente') DEFAULT 'cliente',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Servicios
CREATE TABLE IF NOT EXISTS servicios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  descripcion TEXT,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tickets de soporte
CREATE TABLE IF NOT EXISTS tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  asunto VARCHAR(150) NOT NULL,
  mensaje TEXT NOT NULL,
  estado ENUM('abierto','en_proceso','cerrado') DEFAULT 'abierto',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pagos
CREATE TABLE IF NOT EXISTS pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  servicio_id INT NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  estado ENUM('pendiente','pagado','fallido') DEFAULT 'pendiente',
  referencia VARCHAR(64) NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (servicio_id) REFERENCES servicios(id)
) ENGINE=InnoDB;

-- Datos semilla: usuario admin (password: Admin123!)
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Administrador', 'admin@albardi.com',
 '$2y$10$c/rmtPi6lR/UKFNLU8Jb4u9yDMyuPCHrgG3VtaEYwTfTYDwrwOBty', 'admin');

-- Servicios de ejemplo
INSERT INTO servicios (nombre, descripcion, precio) VALUES
('Desarrollo Web', 'Sitios y aplicaciones web a medida.', 15000.00),
('Mantenimiento', 'Soporte y actualizaciones mensuales.', 3500.00),
('Integración de APIs', 'Conexión con pasarelas y servicios externos.', 8000.00),
('QA y Testing', 'Detección y corrección de bugs.', 2500.00);
