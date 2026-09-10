-- =====================================================================
-- SPG 7.113.0 — Las alergias de CADA persona que se atiende en la cita
-- =====================================================================
--
-- `cliente.alergias` (7.108.0) alcanza mientras la cita sea de una sola
-- persona y esa persona tenga ficha. No es el caso:
--
--   · la cita PARA OTRA PERSONA la recibe alguien que no es la clienta
--     —su nombre vive en `cita.nombre_para`, como texto, porque el salón
--     no la registró—;
--   · en una cita de varias, los acompañantes tampoco tienen ficha:
--     viven en `cita_acompanante` desde la 7.97.0.
--
-- O sea que hasta acá, en una cita de tres, el sistema sólo podía anotar
-- la alergia de UNA. Y una alergia es el único dato de la ficha que
-- puede lastimar a alguien si nadie lo mira.
--
-- **Por qué las alergias van donde va el NOMBRE de cada uno, y no en una
-- tabla nueva.** Un acompañante no es una `persona` del sistema: no
-- tiene ficha, y crearle una sería inventar a alguien que el salón no
-- registró —la regla que este proyecto sostiene desde la 7.97.0—. Así
-- que su alergia no es un dato permanente de nadie: es un dato **de esa
-- visita**, dicho al reservar, y el único lugar donde puede vivir es la
-- cita. Va exactamente al lado del nombre que ya está guardado ahí, con
-- el mismo tratamiento.
--
-- No rompe la 3FN: ninguna de las dos columnas es copia de nada ni se
-- deduce de ninguna otra. La de la clienta titular NO se toca — ésa sí
-- es un dato de su ficha y sigue en `cliente.alergias`.
--
-- Re-ejecutable y sin tocar datos: cada `ALTER` va detrás de una
-- comprobación contra `information_schema`.

-- --- 1. La persona para quien es la cita ------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'cita' AND column_name = 'alergias_para');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cita ADD COLUMN alergias_para VARCHAR(300) NULL
     COMMENT ''Alergias de quien se atiende cuando la cita es para otra persona. NULL = sin registrar''
     AFTER nombre_para',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- Sin `para_otra_persona` no hay a quién atribuírselas: la alergia de la
-- clienta va en su ficha. Es el mismo criterio de `chk_cita_para`, que
-- exige el nombre cuando la casilla está marcada.
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name = 'cita' AND constraint_name = 'chk_cita_alergias_para');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cita ADD CONSTRAINT chk_cita_alergias_para CHECK (
     alergias_para IS NULL
     OR (para_otra_persona = 1 AND CHAR_LENGTH(TRIM(alergias_para)) >= 2))',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 2. Cada acompañante ----------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'cita_acompanante' AND column_name = 'alergias');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cita_acompanante ADD COLUMN alergias VARCHAR(300) NULL
     COMMENT ''Alergias de esta persona, dichas al reservar. NULL = sin registrar''
     AFTER apellido',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- Una alergia de una letra no advierte de nada, igual que un nombre de
-- una letra no identifica a nadie (`chk_acomp_nombre`). Y vacía no es
-- cero: NULL quiere decir «no se registró», que la pantalla dice con
-- esas palabras en vez de afirmar que no tiene ninguna.
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name = 'cita_acompanante' AND constraint_name = 'chk_acomp_alergias');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cita_acompanante ADD CONSTRAINT chk_acomp_alergias CHECK (
     alergias IS NULL OR CHAR_LENGTH(TRIM(alergias)) >= 2)',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;
