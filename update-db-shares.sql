-- ============================================
-- FASE 3: Sistema de Compartir Álbumes
-- Actualización de Base de Datos
-- ============================================

USE gogleanty_db;

-- Tabla de enlaces compartidos de álbumes
CREATE TABLE IF NOT EXISTS album_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    album_id INT NOT NULL,
    share_token VARCHAR(64) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    views INT DEFAULT 0,
    last_viewed_at TIMESTAMP NULL,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    INDEX idx_token (share_token),
    INDEX idx_album (album_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpiar enlaces expirados (si se configuró expiración)
DELETE FROM album_shares WHERE expires_at IS NOT NULL AND expires_at < NOW();

SELECT 'Tabla de compartir álbumes creada exitosamente' AS status;
