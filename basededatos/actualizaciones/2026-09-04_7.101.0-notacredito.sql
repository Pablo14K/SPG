-- =====================================================================
-- SPG 7.101.0 — La nota de crédito puede ser por PARTE del comprobante
-- =====================================================================
--
-- Copiaba el detalle entero, así que la única nota posible era por el
-- total. El salón necesita poder revertir una parte —un servicio que al
-- final no se hizo, un cobro de más— y hasta ahora eso obligaba a
-- acreditar todo y volver a facturar el resto, gastando dos números de
-- la numeración de la DNIT por una diferencia chica.
--
-- **El monto es opcional para la pantalla y por defecto es el total**: el
-- servicio siempre manda el cuarto parámetro y usa NULL cuando se eligió
-- «por todo». Así el procedimiento conserva una firma explícita.
--
-- **Cómo se reparte una nota parcial**: los renglones se escalan por el
-- mismo factor, no se eligen. Elegir cuáles entran sería otra pantalla y
-- otra decisión —«¿de qué servicio es esta devolución?»— y el caso que
-- se pidió es el contrario: una reversa de un monto, sin un servicio
-- detrás. Escalando, la nota conserva el detalle de lo que se facturó y
-- el IVA se desglosa solo, que es lo que la DNIT necesita ver.
--
-- **Lo que el redondeo no divide exacto va en el renglón más caro**, que
-- es el mismo criterio con el que este sistema reparte las cuotas de una
-- compra: sin eso, la nota cierra en un guaraní distinto del pedido.
--
-- Re-ejecutable: el procedimiento se reemplaza entero.

DROP PROCEDURE IF EXISTS `sp_emitir_nota_credito`;

DELIMITER $$

CREATE PROCEDURE `sp_emitir_nota_credito`(
    IN  p_id_factura_origen INT UNSIGNED,
    IN  p_id_usuario        INT UNSIGNED,
    IN  p_motivo            VARCHAR(300),
    IN  p_monto             DECIMAL(14,2),
    OUT p_id_nota           INT UNSIGNED)
BEGIN
  DECLARE v_suc       INT UNSIGNED DEFAULT NULL;
  DECLARE v_cliente   INT UNSIGNED DEFAULT NULL;
  DECLARE v_signo     TINYINT DEFAULT 0;
  DECLARE v_condicion INT UNSIGNED DEFAULT 1;
  DECLARE v_timbrado  INT UNSIGNED DEFAULT NULL;
  DECLARE v_nro       INT UNSIGNED DEFAULT 0;
  DECLARE v_total     DECIMAL(14,2) DEFAULT 0;
  DECLARE v_factor    DECIMAL(18,8) DEFAULT 1;
  DECLARE v_dif       DECIMAL(14,2) DEFAULT 0;
  DECLARE v_detalle   INT UNSIGNED DEFAULT NULL;
  DECLARE v_cant      DECIMAL(12,4) DEFAULT 1;

  SELECT f.id_cliente, f.id_condicion_venta, tc.signo
    INTO v_cliente, v_condicion, v_signo
  FROM factura f
  JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
  WHERE f.id_factura = p_id_factura_origen;

  IF v_cliente IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La factura de origen no existe.';
  END IF;

  IF v_signo <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se puede acreditar un comprobante de venta.';
  END IF;

  -- El local sale de la factura y no del timbrado: desde la 7.49.0 un
  -- comprobante puede haberse numerado con el timbrado de otra sede.
  SELECT COALESCE(f.id_sucursal, t.id_sucursal) INTO v_suc
    FROM factura f LEFT JOIN timbrado t ON t.id_timbrado = f.id_timbrado
   WHERE f.id_factura = p_id_factura_origen;

  SET v_timbrado = fn_timbrado_vigente(5, CURRENT_DATE, v_suc);
  IF v_timbrado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay timbrado vigente para notas de credito.';
  END IF;

  -- **Cuánto se acredita.** Vacío es todo, que es el caso normal.
  SET v_total = fn_factura_total(p_id_factura_origen);
  IF p_monto IS NULL OR p_monto >= v_total THEN
    SET v_factor = 1;
  ELSEIF p_monto <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto a acreditar tiene que ser mayor a cero.';
  ELSEIF v_total <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ese comprobante no tiene monto que acreditar.';
  ELSE
    SET v_factor = p_monto / v_total;
  END IF;

  SET v_nro = fn_siguiente_correlativo(v_timbrado);

  INSERT INTO factura (id_cliente, id_cita, id_usuario, id_tipo_comprobante, id_condicion_venta,
                       id_timbrado, id_estado_factura, id_factura_origen, nro_correlativo, observaciones)
  VALUES (v_cliente, NULL, p_id_usuario, 5, v_condicion,
          v_timbrado, 1, p_id_factura_origen, v_nro, p_motivo);
  SET p_id_nota = LAST_INSERT_ID();

  INSERT INTO detalle_factura (id_factura, id_servicio, id_producto, cantidad, precio_unitario, tasa_iva)
  SELECT p_id_nota, df.id_servicio, df.id_producto, df.cantidad,
         ROUND(df.precio_unitario * v_factor, 2), df.tasa_iva
  FROM detalle_factura df
  WHERE df.id_factura = p_id_factura_origen;

  INSERT INTO factura_descuento (id_factura, id_descuento, monto_aplicado)
  SELECT p_id_nota, fd.id_descuento, ROUND(fd.monto_aplicado * v_factor, 2)
  FROM factura_descuento fd
  WHERE fd.id_factura = p_id_factura_origen;

  -- **Lo que el redondeo dejó afuera va en el renglón más caro.** Sin
  -- esto, una nota «por Gs. 100.000» puede salir en 99.998 y el papel
  -- contradice a lo que se pidió.
  IF v_factor < 1 THEN
    SET v_dif = p_monto - fn_factura_total(p_id_nota);

    IF v_dif <> 0 THEN
      SELECT df.id_detalle_factura, df.cantidad
        INTO v_detalle, v_cant
        FROM detalle_factura df
       WHERE df.id_factura = p_id_nota
       ORDER BY df.cantidad * df.precio_unitario DESC, df.id_detalle_factura ASC
       LIMIT 1;

      IF v_detalle IS NOT NULL AND v_cant > 0 THEN
        UPDATE detalle_factura
           SET precio_unitario = GREATEST(0, precio_unitario + (v_dif / v_cant))
         WHERE id_detalle_factura = v_detalle;
      END IF;
    END IF;
  END IF;
END$$

DELIMITER ;
