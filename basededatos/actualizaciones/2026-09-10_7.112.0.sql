-- =====================================================================
-- SGP 7.112.0 — La foto de perfil de cada persona
-- =====================================================================
--
-- **Va en `persona`, no en `usuario`.** Es la cara de alguien, y la regla
-- número dos de este proyecto dice dónde viven los datos de una persona: en
-- `persona`, una sola vez. Colgada de `usuario` habría dos problemas — quien
-- trabaja en el salón sin cuenta de sistema (existe desde la 7.68.0) no podría
-- tener foto, y el día que una persona tuviera dos cuentas habría dos caras
-- para la misma cara.
--
-- **Se guarda el NOMBRE del archivo, no el archivo.** Es el criterio del logo
-- (7.35.0) y de la imagen del servicio (7.70.0): un BLOB hincha la base,
-- complica el volcado que se entrega y obliga a servir la imagen por PHP en
-- cada carga de la pantalla. El archivo vive en `public/assets/personas/`, que
-- en el servidor es un volumen de Docker.
--
-- **NULL es «no cargó ninguna», y no es un error**: `Imagen::url()` devuelve
-- null y la pantalla dibuja las iniciales. Por eso admite NULL y no lleva
-- valor por defecto.
--
-- No rompe la 3FN: el nombre del archivo no se deduce de ninguna otra columna
-- ni está guardado en otro lado.
--
-- ---------------------------------------------------------------------
-- Re-ejecutable y sin tocar datos.
-- ---------------------------------------------------------------------

-- 1) La columna, sólo si todavía no está.
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'persona'
                AND column_name = 'foto');

SET @sql := IF(@hay = 0,
    'ALTER TABLE persona ADD COLUMN foto VARCHAR(120) NULL AFTER direccion',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Que no entre una cadena vacía disfrazada de nombre de archivo.
--
--    Vacío y NULL significarían lo mismo —«no hay foto»— y con los dos
--    conviviendo cada consulta tendría que preguntar por ambos. NULL es el
--    único que dice «no cargó ninguna».
SET @hayck := (SELECT COUNT(*) FROM information_schema.check_constraints
                WHERE constraint_schema = DATABASE()
                  AND constraint_name = 'chk_persona_foto');

SET @sql := IF(@hayck = 0,
    'ALTER TABLE persona ADD CONSTRAINT chk_persona_foto CHECK (foto IS NULL OR CHAR_LENGTH(TRIM(foto)) > 0)',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
