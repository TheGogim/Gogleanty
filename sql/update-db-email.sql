USE gogleanty_db;

-- Añadir columna email a la tabla user si no existe
SET @dbname = DATABASE();
SET @tablename = "user";
SET @columnname = "email";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE user ADD COLUMN email VARCHAR(191) UNIQUE NULL AFTER username"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SELECT 'Columna email verificada/añadida' AS status;
