-- =====================================================================
-- SPG 7.101.0 — El precio con descuento se ve al elegir el servicio
-- =====================================================================
--
-- El catálogo del portal mostraba siempre el precio de lista, así que una
-- clienta Oro veía Gs. 75.000 y pagaba 67.500: el descuento aparecía
-- recién al cobrar. Lo que se descuenta ya lo decide la base desde la
-- 7.88.0 —cada servicio con el mejor descuento que le aplique— y lo único
-- que faltaba era poder preguntarlo **por servicio suelto**, antes de que
-- exista la cita.
--
-- **Sólo cuenta los descuentos de tipo PORCENTAJE, y es a propósito.**
-- Un monto fijo se aplica UNA vez, sobre el renglón más caro que cubre
-- (`sp_aplicar_descuentos`), así que mostrarlo en cada tarjeta prometería
-- ese descuento tantas veces como servicios haya marcados. Un porcentaje
-- es una tasa: vale igual sobre uno o sobre cinco.
--
-- El criterio de cuáles compiten es el mismo del procedimiento, y eso
-- importa: escrito distinto, la tarjeta anunciaría un precio que la
-- factura no respeta.
--
--   · el descuento del NIVEL de esa clienta, y nada más de los de nivel;
--   · las promociones (las que no son de ningún nivel);
--   · de las restringidas por servicio, sólo si cubren a éste.
--
-- Re-ejecutable: la función se reemplaza entera.

DROP FUNCTION IF EXISTS `fn_servicio_descuento_monto`;

DELIMITER $$

CREATE FUNCTION `fn_servicio_descuento_monto`(
    p_id_servicio INT UNSIGNED,
    p_id_cliente  INT UNSIGNED
) RETURNS DECIMAL(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_precio DECIMAL(14,2) DEFAULT NULL;
  DECLARE v_nivel  INT UNSIGNED DEFAULT NULL;
  DECLARE v_mejor  DECIMAL(14,2) DEFAULT 0;

  SELECT precio INTO v_precio
    FROM servicio WHERE id_servicio = p_id_servicio AND activo = 1;

  IF v_precio IS NULL OR v_precio <= 0 THEN
    RETURN 0;
  END IF;

  -- Sin clienta —el catálogo que se mira sin haber entrado— quedan sólo
  -- las promociones, que valen para cualquiera.
  IF p_id_cliente IS NOT NULL THEN
    SET v_nivel = fn_cliente_descuento(p_id_cliente);
  END IF;

  SELECT COALESCE(MAX(fn_descuento_monto(d.id_descuento, v_precio)), 0)
    INTO v_mejor
    FROM descuento d
   WHERE d.activo = 1
     AND d.tipo = 'PORCENTAJE'
     -- De los de nivel, sólo el de ESTA clienta: el de Platino no se le
     -- puede anunciar a una de Bronce.
     AND (d.id_descuento = v_nivel
          OR NOT EXISTS (SELECT 1 FROM nivel n WHERE n.id_descuento = d.id_descuento))
     -- Una promo restringida a ciertos servicios sólo cuenta para ésos.
     AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd
                       WHERE sd.id_descuento = d.id_descuento)
          OR EXISTS (SELECT 1 FROM servicio_descuento sd
                      WHERE sd.id_descuento = d.id_descuento
                        AND sd.id_servicio = p_id_servicio));

  IF v_mejor > v_precio THEN
    SET v_mejor = v_precio;
  END IF;

  RETURN v_mejor;
END$$

DELIMITER ;

-- Una cuenta puede reunir varios roles. `usuario.id_rol` se conserva como rol
-- principal para compatibilidad con integraciones existentes; esta tabla es
-- la fuente de los roles adicionales y se inicializa con los actuales.
CREATE TABLE IF NOT EXISTS usuario_rol (
    id_usuario INT UNSIGNED NOT NULL,
    id_rol INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_usuario, id_rol),
    CONSTRAINT fk_usuario_rol_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_rol_rol FOREIGN KEY (id_rol) REFERENCES rol(id_rol) ON DELETE CASCADE
);
INSERT IGNORE INTO usuario_rol (id_usuario, id_rol) SELECT id_usuario, id_rol FROM usuario;
