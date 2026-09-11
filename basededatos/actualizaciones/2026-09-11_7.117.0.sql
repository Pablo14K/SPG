-- =====================================================================
-- SGP 7.117.0 — Cada servicio de la cita dice PARA QUIÉN es, y una cita
--               de varias se cobra y se factura junta O por persona
-- =====================================================================
--
-- Una cita puede ser de tres amigas (`cita.personas`, 7.57.0) y desde la
-- 7.97.0 se sabe QUIÉNES vienen (`cita_acompanante`). Lo que faltaba era
-- lo de en medio: **qué servicio es de cuál**. Sin eso la cita era una
-- bolsa —«tres personas: corte, mechas, manicura»— y no había forma de
-- decir que la manicura es de Josefina, ni de cobrarle a Josefina sólo su
-- manicura, ni de hacerle SU factura.
--
-- Tres columnas, las tres con el mismo criterio —**el lugar que ocupa la
-- persona en el grupo**, que es como `cita_acompanante.orden` ya la
-- nombra desde la 7.97.0—:
--
--   · `cita_servicio.persona`  para quién es ese servicio. 1 es la clienta
--                              titular (o `nombre_para` si la cita es para
--                              otra), 2..N son los acompañantes por su
--                              `orden`. Por defecto 1: todo lo que ya
--                              estaba agendado es de la titular, que es
--                              lo que siempre fue.
--   · `cobro.persona`          de quién es ese cobro. NULL = de la cita
--                              entera (el grupo paga junto), que es como
--                              se cobró hasta hoy.
--   · `factura.persona`        para quién es ese comprobante. NULL = toda
--                              la cita, que es como se facturó hasta hoy.
--
-- **No es una copia ni un derivado**: no hay otra columna de la que salga
-- para quién es un servicio. Y no rompe la 1FN: un número por fila.
--
-- **Lo que sigue igual a propósito**: `uq_cita_servicio (id_cita,
-- id_servicio)`. El mismo servicio dos veces en la misma cita —dos cortes
-- para dos amigas— no entra: la atención, la factura y la comisión están
-- escritas sobre «un servicio por cita» en treinta lugares, y abrir eso
-- es otro cambio. Para ese caso se reservan citas aparte, una por
-- persona.
--
-- Cambian dos rutinas:
--
--   · `sp_emitir_factura` gana `p_persona` (NULL = toda la cita): con un
--     número, el detalle sale sólo de los servicios de esa persona y el
--     comprobante queda marcado como suyo.
--   · `fn_factura_saldo`: un comprobante de UNA persona descuenta sólo
--     los cobros de ESA persona. El de toda la cita sigue descontando
--     todo lo cobrado contra la cita, como hasta hoy.
--
-- Re-ejecutable y sin tocar datos: cada `ALTER` va detrás de una
-- comprobación contra `information_schema`, y las rutinas se reemplazan
-- enteras (DROP + CREATE).
--
--     docker exec sgp_app sh -c 'mysql --skip-ssl -hbd -uroot -p"$DB_PASSWORD" \
--       --default-character-set=utf8mb4 peluqueria_bd \
--       < basededatos/actualizaciones/2026-09-11_7.117.0.sql'
--
-- Al terminar: `docker exec sgp_app php artisan sgp:diagnostico --produccion`
-- tiene que decir 87 CHECK, 22 procedimientos, 43 funciones y «Todo en orden».

-- --- 1. Para quién es cada servicio ------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'cita_servicio' AND column_name = 'persona');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cita_servicio ADD COLUMN persona TINYINT UNSIGNED NOT NULL DEFAULT 1
     COMMENT ''Para quién es: 1 = la titular (o nombre_para), 2..N = cita_acompanante.orden''
     AFTER orden',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- El mismo tope que `chk_acomp_orden` y que `cita.personas`: nadie viene
-- con más de veinte.
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name = 'cita_servicio' AND constraint_name = 'chk_cs_persona');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cita_servicio ADD CONSTRAINT chk_cs_persona CHECK (persona BETWEEN 1 AND 20)',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 2. De quién es cada cobro contra la cita --------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'cobro' AND column_name = 'persona');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cobro ADD COLUMN persona TINYINT UNSIGNED NULL
     COMMENT ''De quién es este cobro cuando la cita es de varias. NULL = de toda la cita''
     AFTER id_cita',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- Un cobro de UNA persona sólo tiene sentido contra una cita: contra una
-- factura la persona ya la dice la factura.
SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name = 'cobro' AND constraint_name = 'chk_cobro_persona');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cobro ADD CONSTRAINT chk_cobro_persona CHECK (
     persona IS NULL OR (persona BETWEEN 1 AND 20 AND id_cita IS NOT NULL))',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 3. Para quién es cada comprobante ---------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'factura' AND column_name = 'persona');
SET @sql := IF(@hay = 0,
  'ALTER TABLE factura ADD COLUMN persona TINYINT UNSIGNED NULL
     COMMENT ''Para quién es: NULL = toda la cita, N = esa persona del grupo''
     AFTER id_cita',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE()
                AND table_name = 'factura' AND constraint_name = 'chk_factura_persona');
SET @sql := IF(@hay = 0,
  'ALTER TABLE factura ADD CONSTRAINT chk_factura_persona CHECK (
     persona IS NULL OR (persona BETWEEN 1 AND 20 AND id_cita IS NOT NULL))',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 4. sp_emitir_factura: el comprobante de UNA persona ---------------
DROP PROCEDURE IF EXISTS `sp_emitir_factura`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_emitir_factura`(
  IN  p_id_cliente           INT UNSIGNED,
  IN  p_id_cita              INT UNSIGNED,
  IN  p_id_usuario           INT UNSIGNED,
  IN  p_id_tipo_comprobante  INT UNSIGNED,
  IN  p_id_condicion_venta   INT UNSIGNED,
  IN  p_id_sucursal          INT UNSIGNED,
  IN  p_persona              TINYINT UNSIGNED,
  OUT p_id_factura           INT UNSIGNED
)
BEGIN
  DECLARE v_timbrado  INT UNSIGNED DEFAULT NULL;
  DECLARE v_nro       INT UNSIGNED DEFAULT 0;
  DECLARE v_nivel     INT UNSIGNED DEFAULT NULL;
  DECLARE v_suc       INT UNSIGNED DEFAULT NULL;

  -- La sucursal del comprobante es la de la CITA cuando la hay: el hecho
  -- ocurrió en un lugar, y de ahí sale el timbrado y el establecimiento
  -- impreso (7.37.0).
  SET v_suc = p_id_sucursal;
  IF p_id_cita IS NOT NULL THEN
    SELECT id_sucursal INTO v_suc FROM cita WHERE id_cita = p_id_cita;
  END IF;

  SET v_timbrado = fn_timbrado_vigente(p_id_tipo_comprobante, CURRENT_DATE, v_suc);
  IF v_timbrado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay timbrado vigente para ese tipo de comprobante.';
  END IF;

  -- **El comprobante de una persona sólo vale sobre una cita de varias.**
  -- Sin cita no hay grupo; y con el número fuera de `cita.personas` se
  -- estaría facturando a alguien que no vino.
  IF p_persona IS NOT NULL THEN
    IF p_id_cita IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un comprobante por persona necesita una cita.';
    END IF;
    IF p_persona > (SELECT personas FROM cita WHERE id_cita = p_id_cita) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esa persona no esta en la cita.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM cita_servicio
                    WHERE id_cita = p_id_cita AND persona = p_persona) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esa persona no tiene servicios en la cita.';
    END IF;
  END IF;

  SET v_nro = fn_siguiente_correlativo(v_timbrado);

  INSERT INTO factura (id_cliente, id_cita, persona, id_usuario, id_tipo_comprobante, id_condicion_venta,
                       id_sucursal, id_timbrado, id_estado_factura, nro_correlativo)
  VALUES (p_id_cliente, p_id_cita, p_persona, p_id_usuario, p_id_tipo_comprobante, p_id_condicion_venta,
          v_suc, v_timbrado, 1, v_nro);
  SET p_id_factura = LAST_INSERT_ID();

  -- El detalle sale de `cita_servicio` —lo que se HIZO, no lo que se
  -- reservó— y, con `p_persona`, sólo de lo que es de esa persona. Lo
  -- canjeado con puntos va a cero: se hizo y tiene que constar.
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
    WHERE cs.id_cita = p_id_cita
      AND (p_persona IS NULL OR cs.persona = p_persona);

    UPDATE servicio_realizado sr
      JOIN detalle_factura df
        ON df.id_factura = p_id_factura AND df.id_servicio = sr.id_servicio
       SET sr.id_detalle_factura = df.id_detalle_factura
     WHERE sr.id_cita = p_id_cita AND sr.id_detalle_factura IS NULL;
  END IF;

  -- El mejor descuento que le corresponda, sobre ESTE detalle: con un
  -- comprobante por persona, cada uno lleva el suyo.
  SET v_nivel = fn_cliente_descuento(p_id_cliente);
  CALL sp_aplicar_descuentos(p_id_factura, v_nivel);
END ;;
DELIMITER ;

-- --- 5. fn_factura_saldo: descuenta los cobros de SU persona -----------
DROP FUNCTION IF EXISTS `fn_factura_saldo`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_factura_saldo`(p_id_factura INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_cobrado DECIMAL(14,2) DEFAULT 0;
  DECLARE v_sena    DECIMAL(14,2) DEFAULT 0;

  -- Lo cobrado contra el comprobante mismo.
  SELECT COALESCE(SUM(monto), 0) INTO v_cobrado
  FROM cobro WHERE id_factura = p_id_factura AND id_estado_cobro = 1;

  -- Lo cobrado contra la CITA antes de emitirlo: la seña, y desde la 7.19.0
  -- también el cobro de la atención. **Si el comprobante es de una
  -- persona, sólo lo que cobró ESA persona**: si no, dos comprobantes de la
  -- misma cita se descontarían los dos la plata de las dos. El de toda la
  -- cita (persona NULL) sigue descontando todo, como hasta hoy.
  SELECT COALESCE(SUM(co.monto), 0) INTO v_sena
  FROM factura f
  JOIN cobro co ON co.id_cita = f.id_cita AND co.id_estado_cobro = 1
              AND co.id_factura IS NULL
              AND (f.persona IS NULL OR co.persona = f.persona)
  WHERE f.id_factura = p_id_factura AND f.id_cita IS NOT NULL;

  RETURN fn_factura_total(p_id_factura) - v_cobrado - v_sena;
END ;;
DELIMITER ;

-- --- 6. Qué alerta ya vio cada persona ---------------------------------
-- **La campanita es una bandeja, así que necesita saber qué está leído.**
-- El numerito rojo baja al abrirla —lo pidió el usuario— y eso es un hecho
-- nuevo: «esta persona vio este aviso». No se deduce de ninguna otra tabla,
-- así que se guarda; y va por USUARIO porque leerlo es de cada uno: que la
-- dueña abra la campanita no significa que la recepcionista se enteró.
--
-- `clave` es la identidad del aviso, no su texto: `caja:12` sigue siendo el
-- mismo aviso aunque mañana diga «hace 3 días» en vez de «hace 2». Si esa
-- caja se cierra y se abre otra, la clave es otra y vuelve a contar.
--
-- **Lo que NO entra acá son los pendientes** —lo que falta cargar—, y es una
-- excepción que pidió el usuario explícitamente: un timbrado sin cargar
-- sigue contando hasta que alguien lo cargue, por más veces que se lo haya
-- mirado. Verlo no lo resuelve.
CREATE TABLE IF NOT EXISTS `alerta_vista` (
  `id_alerta_vista` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `clave` varchar(80) NOT NULL COMMENT 'Identidad del aviso, p. ej. caja:12',
  `visto_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_alerta_vista`),
  UNIQUE KEY `uq_alerta_vista` (`id_usuario`,`clave`),
  CONSTRAINT `fk_alertavista_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 7. Reparación: `usuario_rol` faltaba en la base que se entrega -------
-- **La tabla existe desde el cambio de perspectiva y el `.sql` de instalación
-- no la traía.** La leen `Sesion::roles()`, `Pendientes` y la ficha de
-- Usuarios, así que un salón instalado desde cero se encontraba con el
-- ingreso y la lista de usuarios reventando por una tabla que no está — y no
-- se notaba acá porque `peluqueria_test`, que es contra la que corren las
-- pruebas, sí la tenía. Apareció al regenerar los dos volcados y compararlos.
--
-- Va con `IF NOT EXISTS`: donde ya está no hace nada, y donde falta la repara.
-- **No se cargan filas**: sin ninguna, cada cuenta tiene el rol de
-- `usuario.id_rol`, que es exactamente como se comportaba hasta hoy.
CREATE TABLE IF NOT EXISTS `usuario_rol` (
  `id_usuario` int(10) unsigned NOT NULL,
  `id_rol` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_rol`),
  KEY `fk_usuario_rol_rol` (`id_rol`),
  CONSTRAINT `fk_usuario_rol_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON DELETE CASCADE,
  CONSTRAINT `fk_usuario_rol_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
