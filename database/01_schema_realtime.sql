-- =========================================================
-- TICKETNOW · Extensión de esquema para venta en tiempo real
-- Se aplica SOBRE la base `ticketnow` ya existente
-- (administrador, artista, cliente, evento, evento_artista, lugar, sector)
-- =========================================================

USE `ticketnow`;

-- ---------------------------------------------------------
-- 1) Agregamos capacidad al sector (necesaria para el
--    "Contador Global de Remanentes" y para generar asientos)
-- ---------------------------------------------------------
ALTER TABLE `sector`
  ADD COLUMN `capacidad` INT NOT NULL DEFAULT 0 AFTER `precio`;

-- ---------------------------------------------------------
-- 2) Tabla ASIENTO: cada butaca individual del estadio
--    Estado maneja el ciclo de vida: disponible -> reservado -> vendido
-- ---------------------------------------------------------
CREATE TABLE `asiento` (
  `id_asiento` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sector` BIGINT UNSIGNED NOT NULL,
  `fila` VARCHAR(10) NOT NULL,
  `numero` INT NOT NULL,
  `estado` ENUM('disponible','reservado','vendido') NOT NULL DEFAULT 'disponible',
  `id_cliente_reserva` BIGINT UNSIGNED DEFAULT NULL,
  `reservado_en` DATETIME DEFAULT NULL,
  `version` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_asiento`),
  UNIQUE KEY `uk_sector_fila_numero` (`id_sector`,`fila`,`numero`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `fk_asiento_sector` FOREIGN KEY (`id_sector`) REFERENCES `sector` (`id_sector`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------
-- 3) Tabla VENTA: cabecera de una compra confirmada
-- ---------------------------------------------------------
CREATE TABLE `venta` (
  `id_venta` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cliente` BIGINT UNSIGNED NOT NULL,
  `id_evento` BIGINT UNSIGNED NOT NULL,
  `fecha_venta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `cargo_servicio` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `metodo_pago` VARCHAR(50) NOT NULL,
  `estado` ENUM('confirmada','cancelada') NOT NULL DEFAULT 'confirmada',
  PRIMARY KEY (`id_venta`),
  CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_venta_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------
-- 4) Tabla VENTA_ASIENTO: detalle (qué butacas se vendieron)
--    La UNIQUE sobre id_asiento es un segundo cinturón de
--    seguridad: a nivel de base de datos, un asiento JAMÁS
--    puede aparecer vendido dos veces, incluso si hubiera un
--    bug en la lógica de la aplicación.
-- ---------------------------------------------------------
CREATE TABLE `venta_asiento` (
  `id_venta_asiento` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_venta` BIGINT UNSIGNED NOT NULL,
  `id_asiento` BIGINT UNSIGNED NOT NULL,
  `precio` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id_venta_asiento`),
  UNIQUE KEY `uk_asiento_vendido` (`id_asiento`),
  CONSTRAINT `fk_va_venta` FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id_venta`),
  CONSTRAINT `fk_va_asiento` FOREIGN KEY (`id_asiento`) REFERENCES `asiento` (`id_asiento`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------
-- 5) Actualizamos el sector de ejemplo con su capacidad
-- ---------------------------------------------------------
UPDATE `sector` SET `capacidad` = 40 WHERE `id_sector` = 1;
