-- =====================================================================
-- SPG 7.97.0 — Quiénes son las otras personas que vienen con la clienta
-- =====================================================================
--
-- `cita.personas` decía CUÁNTAS venían y nada más, así que el salón sabía
-- que iban a llegar tres y no a quiénes esperar. Ahora se cargan los
-- nombres, y van acá y no en un campo con comas: una lista adentro de un
-- `VARCHAR` es la falta a la 1FN que este proyecto ya evita con
-- `turno_dia` —una fila por día, nunca 'LMXJVS'—.
--
-- **La primera persona NO va acá**: es la clienta que pide la cita, y ya
-- está en `cita.id_cliente`. Repetirla sería el mismo dato dos veces.
-- Por eso `orden` arranca en 2: es el lugar que ocupa en el grupo.
--
-- Re-ejecutable: la tabla se crea sólo si no está.

CREATE TABLE IF NOT EXISTS `cita_acompanante` (
  `id_acompanante` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cita`        INT UNSIGNED NOT NULL,
  -- El lugar en el grupo. Arranca en 2 porque el 1 es la clienta.
  `orden`          TINYINT UNSIGNED NOT NULL,
  `nombre`         VARCHAR(60) NOT NULL,
  `apellido`       VARCHAR(60) NULL,
  PRIMARY KEY (`id_acompanante`),
  -- Una sola persona por lugar: sin esto, guardar dos veces el formulario
  -- dejaría el grupo duplicado.
  UNIQUE KEY `uq_acomp_cita_orden` (`id_cita`, `orden`),
  CONSTRAINT `fk_acomp_cita` FOREIGN KEY (`id_cita`)
    REFERENCES `cita` (`id_cita`) ON DELETE CASCADE,
  -- El 1 es la clienta, así que un acompañante nunca ocupa ese lugar; y el
  -- tope acompaña al de `cita.personas`, que admite hasta 20.
  CONSTRAINT `chk_acomp_orden` CHECK (`orden` BETWEEN 2 AND 20),
  CONSTRAINT `chk_acomp_nombre` CHECK (CHAR_LENGTH(TRIM(`nombre`)) >= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
