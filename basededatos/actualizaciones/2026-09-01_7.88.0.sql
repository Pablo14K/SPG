-- =========================================================================
--  Actualización de la base a la 7.88.0
--
--  Se corre UNA vez sobre una base que ya está andando, con datos del salón
--  adentro. **No toca ni una fila**: son seis rutinas, y una rutina se
--  reemplaza entera (DROP + CREATE), así que volver a correr esto es inocuo.
--
--      docker exec -i spg_bd sh -c 'mysql --skip-ssl -uroot \
--        -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 peluqueria_bd' \
--        < basededatos/actualizaciones/2026-09-01_7.88.0.sql
--
--  Qué cambia:
--    · trg_citaserv_bi          el mismo servicio se repite en el día si la
--                               cita es para OTRA persona
--    · fn_cita_descuento_total  NUEVA  · descuento por servicio, sumado
--    · fn_factura_descuento_total NUEVA · lo mismo sobre la factura
--    · sp_aplicar_descuentos    NUEVA  · escribe una fila por descuento
--    · fn_cita_total            usa la regla nueva
--    · sp_emitir_factura        usa la regla nueva
--
--  Al terminar, comprobar con:
--      docker exec spg_app php artisan spg:diagnostico --produccion
--  Tiene que decir 22 procedimientos y 41 funciones, y «Todo en orden».
-- =========================================================================


-- ---- trg ----
-- El mismo servicio no se repite en el día PARA LA MISMA PERSONA.
--
-- Antes la regla era «para el mismo CLIENTE», y eso rechazaba un caso legítimo
-- y frecuente: la clienta reserva un corte para su hija a las 10 y otro para
-- ella a las 15. Son dos personas distintas y las dos citas cuelgan de la misma
-- cuenta, así que el disparador las veía como una repetición.
--
-- La cita ya sabe para quién es desde la 7.57.0 (`para_otra_persona` y
-- `nombre_para`), que existe justamente porque esas citas SÍ se superponen a
-- propósito. Lo único que faltaba era mirarlo acá.
DROP TRIGGER IF EXISTS trg_citaserv_bi;
DELIMITER ;;
CREATE TRIGGER trg_citaserv_bi
BEFORE INSERT ON cita_servicio FOR EACH ROW
BEGIN
  DECLARE v_usuario  INT UNSIGNED DEFAULT NULL;
  DECLARE v_cliente  INT UNSIGNED DEFAULT NULL;
  DECLARE v_dia      DATE DEFAULT NULL;
  DECLARE v_otra     TINYINT DEFAULT 0;
  DECLARE v_para     VARCHAR(120) DEFAULT '';
  -- El nombre tal como se cargó: comparar va en minúscula, pero el aviso
  -- que lee la persona tiene que decir «Josefina» y no «josefina».
  DECLARE v_para_txt VARCHAR(120) DEFAULT '';
  DECLARE v_repetido INT DEFAULT 0;
  DECLARE v_nombre   VARCHAR(100) DEFAULT '';
  DECLARE v_msg      VARCHAR(255);

  SELECT c.id_usuario, c.id_cliente, DATE(c.fecha_hora),
         COALESCE(c.para_otra_persona, 0), LOWER(COALESCE(TRIM(c.nombre_para), '')),
         COALESCE(TRIM(c.nombre_para), '')
    INTO v_usuario, v_cliente, v_dia, v_otra, v_para, v_para_txt
    FROM cita c WHERE c.id_cita = NEW.id_cita;

  IF fn_puede_realizar(v_usuario, NEW.id_servicio) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El profesional de la cita no esta habilitado para ese servicio.';
  END IF;

  -- **Se comparan DESTINATARIOS, no cuentas.** Dos citas de la misma cuenta
  -- chocan sólo si son para la misma persona: las dos para la titular, o las
  -- dos para el mismo nombre cargado en `nombre_para`.
  SELECT COUNT(*) INTO v_repetido
    FROM cita_servicio cs
    JOIN cita c        ON c.id_cita = cs.id_cita
    JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
   WHERE cs.id_servicio = NEW.id_servicio
     AND c.id_cliente   = v_cliente
     AND c.id_cita     <> NEW.id_cita
     AND DATE(c.fecha_hora) = v_dia
     AND e.bloquea_agenda = 1
     AND COALESCE(c.para_otra_persona, 0) = v_otra
     AND (v_otra = 0
          OR LOWER(COALESCE(TRIM(c.nombre_para), '')) = v_para);

  IF v_repetido > 0 THEN
    SELECT s.nombre INTO v_nombre FROM servicio s WHERE s.id_servicio = NEW.id_servicio;
    SET v_msg = CONCAT('Ya hay "', v_nombre, '" agendado para ',
                       IF(v_otra = 1, CONCAT('"', v_para_txt, '"'), 'esa clienta'),
                       ' ese mismo dia. No se repite el mismo servicio en el dia para la misma ',
                       'persona: cambia la fecha, o cancela la otra cita primero.');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;
END ;;
DELIMITER ;

-- ---- desc ----
-- El descuento se calcula POR SERVICIO, y se suma.
--
-- Antes se elegía UNA promoción para toda la cita —la que más descontara— y se
-- la comparaba contra el descuento del nivel: ganaba la mayor y la otra se
-- perdía. Con dos promociones sobre servicios DISTINTOS eso está mal, porque no
-- son ofertas que compitan: una es sobre el corte y la otra sobre el lavado, y
-- cada una tiene que aplicarse a lo suyo.
--
-- La regla nueva, en una línea: **cada servicio recibe el mejor descuento que le
-- aplique, y los servicios se suman.** Dos promociones sobre el MISMO servicio
-- siguen compitiendo —gana la mejor—, y el descuento del nivel sigue sin
-- acumularse con una promoción sobre ese mismo servicio.
DROP FUNCTION IF EXISTS fn_cita_descuento_total;
DELIMITER ;;
CREATE FUNCTION fn_cita_descuento_total(p_id_cita INT UNSIGNED, p_id_nivel INT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;

  SELECT COALESCE(SUM(
    (SELECT COALESCE(MAX(fn_descuento_monto(d.id_descuento, s.precio)), 0)
       FROM descuento d
      WHERE d.activo = 1
        -- Los descuentos de nivel sólo cuentan si son EL del cliente: el resto
        -- son los de otros niveles, que no le corresponden.
        AND (d.id_descuento = p_id_nivel
             OR NOT EXISTS (SELECT 1 FROM nivel n WHERE n.id_descuento = d.id_descuento))
        -- Una promoción sin servicios cargados vale para todo el catálogo; con
        -- servicios cargados, sólo para ésos.
        AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd
                          WHERE sd.id_descuento = d.id_descuento)
             OR EXISTS (SELECT 1 FROM servicio_descuento sd
                         WHERE sd.id_descuento = d.id_descuento
                           AND sd.id_servicio = cs.id_servicio))
        -- **Un monto fijo se aplica UNA vez, no por servicio.** Un porcentaje es
        -- una tasa y se reparte solo; «Gs. 50.000 de descuento» es un monto, y
        -- aplicado a cada renglón se multiplicaría por la cantidad de servicios.
        -- Se lo carga al más caro de los que cubre, con el id como desempate
        -- para que no dependa del orden en que salgan las filas.
        AND (d.tipo = 'PORCENTAJE'
             OR cs.id_cita_servicio = (
                  SELECT cs2.id_cita_servicio
                    FROM cita_servicio cs2
                    JOIN servicio s2 ON s2.id_servicio = cs2.id_servicio
                   WHERE cs2.id_cita = p_id_cita
                     AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd2
                                       WHERE sd2.id_descuento = d.id_descuento)
                          OR EXISTS (SELECT 1 FROM servicio_descuento sd2
                                      WHERE sd2.id_descuento = d.id_descuento
                                        AND sd2.id_servicio = cs2.id_servicio))
                   ORDER BY s2.precio DESC, cs2.id_cita_servicio ASC
                   LIMIT 1)))
  ), 0) INTO v
  FROM cita_servicio cs
  JOIN servicio s ON s.id_servicio = cs.id_servicio
  WHERE cs.id_cita = p_id_cita
    -- Un servicio canjeado ya está pagado con puntos: descontarle algo sería
    -- descontar sobre cero.
    AND NOT EXISTS (SELECT 1 FROM canje cj
                     WHERE cj.id_cita = cs.id_cita AND cj.id_servicio = cs.id_servicio);

  RETURN v;
END ;;
DELIMITER ;

-- ---- desc2 ----
-- La misma regla por servicio, resuelta sobre la FACTURA.
--
-- Es replicación deliberada, como el espejo de la agenda: la cita todavía no
-- tiene factura y la factura ya no depende de la cita. **Si cambia el criterio
-- de una, cambia el de la otra**, o la pantalla ofrece un total que el
-- comprobante no respeta.
DROP FUNCTION IF EXISTS fn_factura_descuento_total;
DELIMITER ;;
CREATE FUNCTION fn_factura_descuento_total(p_id_factura INT UNSIGNED, p_id_nivel INT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;

  SELECT COALESCE(SUM(
    (SELECT COALESCE(MAX(fn_descuento_monto(d.id_descuento,
                                            ROUND(df.cantidad * df.precio_unitario, 2))), 0)
       FROM descuento d
      WHERE d.activo = 1
        AND (d.id_descuento = p_id_nivel
             OR NOT EXISTS (SELECT 1 FROM nivel n WHERE n.id_descuento = d.id_descuento))
        AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd
                          WHERE sd.id_descuento = d.id_descuento)
             OR EXISTS (SELECT 1 FROM servicio_descuento sd
                         WHERE sd.id_descuento = d.id_descuento
                           AND sd.id_servicio = df.id_servicio))
        -- Un monto fijo se aplica UNA vez, sobre el renglón más caro que cubre.
        AND (d.tipo = 'PORCENTAJE'
             OR df.id_detalle_factura = (
                  SELECT df2.id_detalle_factura
                    FROM detalle_factura df2
                   WHERE df2.id_factura = p_id_factura
                     AND df2.id_servicio IS NOT NULL
                     AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd2
                                       WHERE sd2.id_descuento = d.id_descuento)
                          OR EXISTS (SELECT 1 FROM servicio_descuento sd2
                                      WHERE sd2.id_descuento = d.id_descuento
                                        AND sd2.id_servicio = df2.id_servicio))
                   ORDER BY ROUND(df2.cantidad * df2.precio_unitario, 2) DESC,
                            df2.id_detalle_factura ASC
                   LIMIT 1)))
  ), 0) INTO v
  FROM detalle_factura df
  WHERE df.id_factura = p_id_factura
    AND df.id_servicio IS NOT NULL;

  RETURN v;
END ;;
DELIMITER ;

-- ---- desc3 ----
-- Aplica a la factura TODOS los descuentos que corresponden, uno por servicio.
--
-- `factura_descuento` siempre fue una tabla de filas y `fn_factura_descuento`
-- las suma, así que el modelo admitía varios desde el principio: lo único que
-- lo impedía era que `sp_emitir_factura` aplicaba UNO —el mejor— y descartaba
-- el resto. Con dos promociones sobre servicios distintos eso está mal: no son
-- ofertas que compitan.
--
-- La regla: **cada renglón se lleva el mejor descuento que le aplique**, y
-- después se agrupa por descuento para escribir una fila por cada uno con lo
-- que aportó. Dos promociones sobre el MISMO servicio siguen compitiendo.
DROP PROCEDURE IF EXISTS sp_aplicar_descuentos;
DELIMITER ;;
CREATE PROCEDURE sp_aplicar_descuentos(IN p_id_factura INT UNSIGNED, IN p_id_nivel INT UNSIGNED)
BEGIN
  -- Se rehace desde cero: si se vuelve a llamar, no se duplican los montos.
  DELETE FROM factura_descuento WHERE id_factura = p_id_factura;

  INSERT INTO factura_descuento (id_factura, id_descuento, monto_aplicado)
  WITH candidatos AS (
    SELECT df.id_detalle_factura,
           d.id_descuento,
           fn_descuento_monto(d.id_descuento, ROUND(df.cantidad * df.precio_unitario, 2)) AS monto,
           ROW_NUMBER() OVER (
             PARTITION BY df.id_detalle_factura
             ORDER BY fn_descuento_monto(d.id_descuento,
                                         ROUND(df.cantidad * df.precio_unitario, 2)) DESC,
                      d.id_descuento ASC) AS puesto
      FROM detalle_factura df
      JOIN descuento d ON d.activo = 1
     WHERE df.id_factura = p_id_factura
       AND df.id_servicio IS NOT NULL
       -- Los descuentos de nivel sólo cuentan si son EL del cliente.
       AND (d.id_descuento = p_id_nivel
            OR NOT EXISTS (SELECT 1 FROM nivel n WHERE n.id_descuento = d.id_descuento))
       -- Sin servicios cargados vale para todo; con servicios, sólo para ésos.
       AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd
                         WHERE sd.id_descuento = d.id_descuento)
            OR EXISTS (SELECT 1 FROM servicio_descuento sd
                        WHERE sd.id_descuento = d.id_descuento
                          AND sd.id_servicio = df.id_servicio))
       -- Un monto fijo se aplica UNA vez, sobre el renglón más caro que cubre:
       -- un porcentaje es una tasa y se reparte solo, un monto no.
       AND (d.tipo = 'PORCENTAJE'
            OR df.id_detalle_factura = (
                 SELECT df2.id_detalle_factura
                   FROM detalle_factura df2
                  WHERE df2.id_factura = p_id_factura
                    AND df2.id_servicio IS NOT NULL
                    AND (NOT EXISTS (SELECT 1 FROM servicio_descuento sd2
                                      WHERE sd2.id_descuento = d.id_descuento)
                         OR EXISTS (SELECT 1 FROM servicio_descuento sd2
                                     WHERE sd2.id_descuento = d.id_descuento
                                       AND sd2.id_servicio = df2.id_servicio))
                  ORDER BY ROUND(df2.cantidad * df2.precio_unitario, 2) DESC,
                           df2.id_detalle_factura ASC
                  LIMIT 1))
  )
  SELECT p_id_factura, c.id_descuento, SUM(c.monto)
    FROM candidatos c
   WHERE c.puesto = 1 AND c.monto > 0
   GROUP BY c.id_descuento;
END ;;
DELIMITER ;

-- ---- las dos rutinas que pasan a usar la regla nueva ----
DROP PROCEDURE IF EXISTS sp_emitir_factura;
DELIMITER ;;
CREATE PROCEDURE `sp_emitir_factura`(
  IN  p_id_cliente           INT UNSIGNED,
  IN  p_id_cita              INT UNSIGNED,
  IN  p_id_usuario           INT UNSIGNED,
  IN  p_id_tipo_comprobante  INT UNSIGNED,
  IN  p_id_condicion_venta   INT UNSIGNED,
  IN  p_id_sucursal          INT UNSIGNED,
  OUT p_id_factura           INT UNSIGNED
)
BEGIN
  DECLARE v_timbrado  INT UNSIGNED DEFAULT NULL;
  DECLARE v_nro       INT UNSIGNED DEFAULT 0;
  DECLARE v_nivel     INT UNSIGNED DEFAULT NULL;
  DECLARE v_promo     INT UNSIGNED DEFAULT NULL;
  DECLARE v_suc       INT UNSIGNED DEFAULT NULL;
  DECLARE v_m_nivel   DECIMAL(14,2) DEFAULT 0;
  DECLARE v_m_promo   DECIMAL(14,2) DEFAULT 0;

  
  
  
  SET v_suc = p_id_sucursal;
  IF p_id_cita IS NOT NULL THEN
    SELECT id_sucursal INTO v_suc FROM cita WHERE id_cita = p_id_cita;
  END IF;

  SET v_timbrado = fn_timbrado_vigente(p_id_tipo_comprobante, CURRENT_DATE, v_suc);
  IF v_timbrado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay timbrado vigente para ese tipo de comprobante.';
  END IF;

  SET v_nro = fn_siguiente_correlativo(v_timbrado);

  INSERT INTO factura (id_cliente, id_cita, id_usuario, id_tipo_comprobante, id_condicion_venta,
                       id_sucursal, id_timbrado, id_estado_factura, nro_correlativo)
  VALUES (p_id_cliente, p_id_cita, p_id_usuario, p_id_tipo_comprobante, p_id_condicion_venta,
          
          
          
          v_suc, v_timbrado, 1, v_nro);
  SET p_id_factura = LAST_INSERT_ID();

  IF p_id_cita IS NOT NULL THEN
    INSERT INTO detalle_factura (id_factura, id_servicio, cantidad, precio_unitario, tasa_iva)
    SELECT p_id_factura, s.id_servicio, 1,
           CASE WHEN EXISTS (SELECT 1 FROM canje cj
                              WHERE cj.id_cita = p_id_cita
                                AND cj.id_servicio = s.id_servicio)
                THEN 0 ELSE s.precio END,
           s.tasa_iva
    FROM cita_servicio cs
    JOIN servicio s ON s.id_servicio = cs.id_servicio
    WHERE cs.id_cita = p_id_cita;

    UPDATE servicio_realizado sr
      JOIN detalle_factura df
        ON df.id_factura = p_id_factura AND df.id_servicio = sr.id_servicio
       SET sr.id_detalle_factura = df.id_detalle_factura
     WHERE sr.id_cita = p_id_cita AND sr.id_detalle_factura IS NULL;
  END IF;

  -- **El descuento se calcula por SERVICIO y se suman.** Antes se elegia la
  -- mejor promocion de toda la factura y se la comparaba contra el descuento
  -- del nivel: ganaba una y la otra se perdia. Con dos promociones sobre
  -- servicios DISTINTOS eso esta mal, porque no compiten entre si.
  --
  -- `factura_descuento` siempre admitio varias filas y `fn_factura_descuento`
  -- las suma: lo unico que faltaba era escribirlas.
  SET v_nivel = fn_cliente_descuento(p_id_cliente);
  CALL sp_aplicar_descuentos(p_id_factura, v_nivel);
END ;;
DELIMITER ;

DROP FUNCTION IF EXISTS fn_cita_total;
DELIMITER ;;
CREATE FUNCTION `fn_cita_total`(p_id_cita INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
    DETERMINISTIC
BEGIN
  DECLARE v_cliente  INT UNSIGNED;
  DECLARE v_bruto    DECIMAL(14,2) DEFAULT 0;
  DECLARE v_canjeado DECIMAL(14,2) DEFAULT 0;
  DECLARE v_nivel    INT UNSIGNED;
  DECLARE v_promo    INT UNSIGNED;
  DECLARE v_m_nivel  DECIMAL(14,2) DEFAULT 0;
  DECLARE v_m_promo  DECIMAL(14,2) DEFAULT 0;
  DECLARE v_desc     DECIMAL(14,2) DEFAULT 0;

  SELECT id_cliente INTO v_cliente FROM cita WHERE id_cita = p_id_cita;
  IF v_cliente IS NULL THEN RETURN 0; END IF;

  SELECT COALESCE(SUM(s.precio), 0) INTO v_bruto
    FROM cita_servicio cs
    JOIN servicio s ON s.id_servicio = cs.id_servicio
   WHERE cs.id_cita = p_id_cita;

  
  SELECT COALESCE(SUM(s.precio), 0) INTO v_canjeado
    FROM canje ca
    JOIN servicio s ON s.id_servicio = ca.id_servicio
   WHERE ca.id_cita = p_id_cita;

  -- Mismo criterio que `sp_emitir_factura`, resuelto sobre la cita: cada
  -- servicio con el mejor descuento que le aplique, y se suman. Si cambia uno
  -- hay que cambiar el otro, o la pantalla ofrece un total que el comprobante
  -- no respeta.
  SET v_nivel = fn_cliente_descuento(v_cliente);
  SET v_desc = fn_cita_descuento_total(p_id_cita, v_nivel);

  RETURN GREATEST(v_bruto - v_canjeado - v_desc, 0);
END ;;
DELIMITER ;
