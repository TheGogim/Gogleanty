-- Script para actualizar la BD y soportar audios
-- Ejecutar en phpMyAdmin o MySQL

USE gogleanty_db;

-- Modificar el ENUM de file_type para incluir 'audio'
ALTER TABLE media MODIFY COLUMN file_type ENUM('image', 'video', 'gif', 'audio') NOT NULL;

-- Agregar columna para nombre editable (solo para audios)
ALTER TABLE media ADD COLUMN custom_name VARCHAR(255) DEFAULT NULL AFTER original_filename;

-- Crear directorio para audios (esto se hace en PHP, pero lo documentamos aquí)
-- mkdir uploads/audios

SELECT 'Base de datos actualizada exitosamente para soportar audios' AS status;
