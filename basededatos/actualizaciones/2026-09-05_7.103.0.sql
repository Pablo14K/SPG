-- =====================================================================
-- SPG 7.103.0 — Cuándo se creó cada cajón
-- =====================================================================
--
-- La lista de cajas se ordenaba por `id_caja_fisica`, que **es** el orden
-- en que se crearon, y con eso alcanzaba para ordenar. Lo que no se podía
-- contestar es *cuándo*: un id no es una fecha, y quien mira la pantalla
-- no tiene por qué deducirla de un número.
--
-- **No rompe la 3FN.** El momento en que se creó el cajón no se deduce de
-- ninguna otra columna ni está guardado en otro lado: el id da el orden,
-- no el instante. Es un dato nuevo, no una copia — el mismo criterio por
-- el que `caja.monto_contado` sí se guarda y `fn_caja_diferencia` no.
--
-- **Admite NULL a propósito**, igual que ese conteo. De los cajones que
-- ya existen no se sabe cuándo se crearon: lo que se puede reconstruir es
-- **cuándo se abrieron por primera vez**, que es lo más temprano que el
-- sistema puede probar que ya existían. Para los que nunca se abrieron no
-- hay ni eso, y ahí NULL es la respuesta honesta — la pantalla dice «sin
-- fecha» en vez de inventar el día de la actualización.
--
-- ---------------------------------------------------------------------
-- **EL ORDEN DE LOS TRES PASOS ES LO QUE IMPORTA, y saltearlo miente.**
--
-- Escrito como un solo `ADD COLUMN … DEFAULT CURRENT_TIMESTAMP`, MariaDB
-- **rellena las filas que ya están con la hora del ALTER**: los cajones
-- del salón pasaban a declarar que se crearon el día de la actualización,
-- y el `UPDATE` de relleno no tocaba ninguna porque ya no quedaba ningún
-- NULL. Un dato falso y sin nada que lo delate.
--
-- Por eso la columna entra **sin** valor por defecto —así las filas viejas
-- quedan en NULL, que es la verdad—, después se rellena lo que se puede
-- reconstruir, y recién al final se le pone el defecto para las que vengan.
-- ---------------------------------------------------------------------
--
-- Re-ejecutable: la columna se agrega sólo si no está, y el relleno sólo
-- toca lo que sigue vacío o lo que quedó en un estado imposible.

-- 1. La columna, SIN defecto: las filas que ya están quedan en NULL.
ALTER TABLE `caja_fisica`
    ADD COLUMN IF NOT EXISTS `creado_en` DATETIME NULL
    COMMENT 'Cuándo se creó el cajón. NULL en los anteriores a la 7.103.0 que nunca se abrieron.'
    AFTER `nombre`;

-- 2. Lo más temprano que se puede probar: su primera apertura. No es la
--    fecha de creación exacta —el cajón existía desde antes— pero es una
--    cota real, y por eso la pantalla la muestra como «en uso desde».
--
--    **La segunda condición repara una corrida anterior mal hecha**: un
--    cajón no puede haberse creado DESPUÉS de su primera apertura, así
--    que ese estado imposible es la marca de que la fecha se inventó.
UPDATE `caja_fisica` cf
   SET cf.creado_en = (
        SELECT MIN(c.fecha_apertura) FROM `caja` c
         WHERE c.id_caja_fisica = cf.id_caja_fisica
   )
 WHERE cf.creado_en IS NULL
    OR cf.creado_en > (
        SELECT MIN(c.fecha_apertura) FROM `caja` c
         WHERE c.id_caja_fisica = cf.id_caja_fisica
   );

-- 3. Y ahora sí el defecto, que vale para los cajones que se creen de acá
--    en adelante. Puesto en el paso 1 habría pisado la historia.
ALTER TABLE `caja_fisica`
    MODIFY COLUMN `creado_en` DATETIME NULL DEFAULT CURRENT_TIMESTAMP
    COMMENT 'Cuándo se creó el cajón. NULL en los anteriores a la 7.103.0 que nunca se abrieron.';
