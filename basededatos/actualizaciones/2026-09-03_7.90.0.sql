-- =====================================================================
-- SPG 7.90.0 — La cuenta de correo que envía los avisos se elige desde
--              el sistema, y sólo la cambia el Administrador.
-- =====================================================================
--
-- Hasta ahora el correo saliente vivía en `docker/php/secretos.env`
-- (MAIL_USERNAME / MAIL_PASSWORD / MAIL_FROM_ADDRESS): cambiar la cuenta
-- que manda el código de verificación, la recuperación y los
-- recordatorios era editar un archivo y volver a desplegar. Es el mismo
-- caso que el nombre del salón y los puntos: un dato del negocio
-- escondido detrás de un despliegue.
--
-- Se agregan tres columnas a `configuracion` (la tabla de UNA fila). La
-- clave viaja CIFRADA con la APP_KEY —`mail_clave` guarda el texto que
-- devuelve Crypt::encryptString—, así que un volcado de la base no la
-- deja legible. Vacías las tres, el sistema sigue usando el `.env`, que
-- es lo que hay en la base de instalación.
--
-- Re-ejecutable: cada columna se agrega sólo si no está.

DROP PROCEDURE IF EXISTS sp_tmp_agregar_columna;
DELIMITER //
CREATE PROCEDURE sp_tmp_agregar_columna(IN col VARCHAR(64), IN definicion VARCHAR(255))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE()
                    AND table_name = 'configuracion'
                    AND column_name = col) THEN
    SET @sql = CONCAT('ALTER TABLE configuracion ADD COLUMN ', col, ' ', definicion);
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

CALL sp_tmp_agregar_columna('mail_usuario', 'VARCHAR(120) NULL AFTER email');
CALL sp_tmp_agregar_columna('mail_clave',   'TEXT NULL AFTER mail_usuario');
CALL sp_tmp_agregar_columna('mail_desde',   'VARCHAR(120) NULL AFTER mail_clave');

DROP PROCEDURE IF EXISTS sp_tmp_agregar_columna;
