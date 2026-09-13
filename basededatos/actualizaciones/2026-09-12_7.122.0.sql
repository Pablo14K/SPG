-- =====================================================================
-- SGP 7.122.0 — El arqueo de la cuenta bancaria gana su historial, y
--               Arqueos lista el del cajón y el del banco
-- =====================================================================
--
-- Hasta la 7.121.1 «declarar el saldo» de una cuenta PISABA el anterior:
-- `cuenta_bancaria.saldo_declarado` y `saldo_declarado_en` guardaban uno
-- solo. Eso alcanzaba para calcular cuánto hay, y no para lo que pidió
-- el usuario —«el submódulo de Arqueos también debe hacer el arqueo de
-- la cuenta bancaria, al igual que Cajas»—: un arqueo que no deja rastro
-- no se puede listar, ni decir si cuadró, ni a quién preguntarle.
--
-- Entra `arqueo_cuenta`, una fila por arqueo: cuánto dijo el banco, cuándo,
-- quién lo contó y, si no cuadró, por qué. **Es el mismo criterio que
-- `caja.monto_contado`**: lo que alguien vio con sus ojos se guarda; lo
-- que se deduce, no.
--
-- Y por eso las dos columnas de `cuenta_bancaria` SE VAN: el último arqueo
-- ES el saldo declarado, así que guardarlo en los dos lados sería el
-- mismo dato dos veces — la falta a la 3FN que este proyecto no acepta.
-- Lo que estaba declarado se muda como el primer arqueo de cada cuenta.
--
-- Lo que se deduce, y por eso son funciones y no columnas:
--
--   · fn_cuenta_movido(cuenta, desde, hasta)  lo que entró menos lo que
--       salió de la cuenta en ese tramo: cobros, ingresos, egresos,
--       pagos a proveedores y al personal
--   · fn_cuenta_saldo(cuenta)                 último arqueo + lo movido
--       desde entonces; NULL si nunca se arqueó (NULL no es cero)
--   · fn_arqueo_cuenta_esperado(arqueo)       lo que debería haber dicho
--       el banco: el arqueo anterior + lo movido entre los dos; NULL en
--       el primero, que no tiene contra qué comparar
--   · fn_arqueo_cuenta_diferencia(arqueo)     contado − esperado
--
-- Re-ejecutable y sin perder datos: cada paso mira `information_schema`
-- antes de actuar, y la mudanza de lo declarado sólo corre mientras las
-- columnas viejas existan.
--
--     docker exec sgp_app sh -c 'mysql --skip-ssl -hbd -uroot -p"$DB_PASSWORD" \
--       --default-character-set=utf8mb4 peluqueria_bd \
--       < basededatos/actualizaciones/2026-09-12_7.122.0.sql'
--
-- Al terminar: `docker exec sgp_app php artisan sgp:diagnostico --produccion`
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. La tabla del arqueo de la cuenta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arqueo_cuenta` (
  `id_arqueo_cuenta` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cuenta` int(10) unsigned NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `monto_contado` decimal(14,2) NOT NULL,
  -- NULL sólo en lo mudado desde el saldo declarado: de eso no se sabe
  -- quién lo cargó, y afirmarlo sería inventarlo.
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `motivo_diferencia` varchar(255) DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_arqueo_cuenta`),
  KEY `ix_arqcta_cuenta` (`id_cuenta`, `fecha`),
  KEY `ix_arqcta_usuario` (`id_usuario`),
  CONSTRAINT `fk_arqcta_cuenta` FOREIGN KEY (`id_cuenta`) REFERENCES `cuenta_bancaria` (`id_cuenta`),
  CONSTRAINT `fk_arqcta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `chk_arqcta_monto` CHECK (`monto_contado` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Lo declarado pasa a ser el primer arqueo de cada cuenta
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND column_name = 'saldo_declarado');
SET @sql := IF(@hay = 1,
  'INSERT INTO arqueo_cuenta (id_cuenta, fecha, monto_contado, id_usuario, observacion)
   SELECT cb.id_cuenta, cb.saldo_declarado_en, cb.saldo_declarado, NULL,
          ''Saldo declarado antes de que el arqueo de la cuenta tuviera historial''
     FROM cuenta_bancaria cb
    WHERE cb.saldo_declarado IS NOT NULL AND cb.saldo_declarado_en IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM arqueo_cuenta a WHERE a.id_cuenta = cb.id_cuenta)',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- ---------------------------------------------------------------------
-- 3. Las dos columnas se van: el último arqueo ES el saldo declarado
-- ---------------------------------------------------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.check_constraints
              WHERE constraint_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND constraint_name = 'chk_cuenta_saldo');
SET @sql := IF(@hay = 1, 'ALTER TABLE cuenta_bancaria DROP CONSTRAINT chk_cuenta_saldo', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND column_name = 'saldo_declarado');
SET @sql := IF(@hay = 1, 'ALTER TABLE cuenta_bancaria DROP COLUMN saldo_declarado', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'cuenta_bancaria'
                AND column_name = 'saldo_declarado_en');
SET @sql := IF(@hay = 1, 'ALTER TABLE cuenta_bancaria DROP COLUMN saldo_declarado_en', 'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- ---------------------------------------------------------------------
-- 4. Las funciones
-- ---------------------------------------------------------------------
DELIMITER $$

DROP FUNCTION IF EXISTS fn_cuenta_movido $$
CREATE FUNCTION fn_cuenta_movido(p_id_cuenta INT UNSIGNED, p_desde DATETIME, p_hasta DATETIME)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  -- Lo que entró menos lo que salió de la cuenta en [p_desde, p_hasta).
  -- `p_hasta` en NULL es «hasta ahora». El borde de abajo entra y el de
  -- arriba no: lo movido en el mismo instante de un arqueo es posterior a
  -- él, igual que contaba `fn_cuenta_saldo` desde la 7.110.0.
  DECLARE v_cobros DECIMAL(14,2) DEFAULT 0;
  DECLARE v_ing    DECIMAL(14,2) DEFAULT 0;
  DECLARE v_egr    DECIMAL(14,2) DEFAULT 0;
  DECLARE v_prov   DECIMAL(14,2) DEFAULT 0;
  DECLARE v_pers   DECIMAL(14,2) DEFAULT 0;

  SELECT COALESCE(SUM(co.monto), 0) INTO v_cobros
    FROM cobro co
   WHERE co.id_cuenta = p_id_cuenta AND co.id_estado_cobro = 1
     AND co.fecha >= p_desde AND (p_hasta IS NULL OR co.fecha < p_hasta);

  SELECT COALESCE(SUM(CASE WHEN tipo = 'INGRESO' THEN monto END), 0),
         COALESCE(SUM(CASE WHEN tipo = 'EGRESO'  THEN monto END), 0)
    INTO v_ing, v_egr
    FROM movimiento_caja
   WHERE id_cuenta = p_id_cuenta AND activo = 1
     AND fecha >= p_desde AND (p_hasta IS NULL OR fecha < p_hasta);

  SELECT COALESCE(SUM(fn_pago_proveedor_monto(pp.id_pago_proveedor)), 0) INTO v_prov
    FROM pago_proveedor pp
   WHERE pp.id_cuenta = p_id_cuenta AND pp.id_estado_pago_proveedor = 1
     AND pp.fecha >= p_desde AND (p_hasta IS NULL OR pp.fecha < p_hasta);

  SELECT COALESCE(SUM(fn_pago_personal_monto(pg.id_pago_personal)), 0) INTO v_pers
    FROM pago_personal pg
   WHERE pg.id_cuenta = p_id_cuenta AND pg.id_estado_pago = 1
     AND pg.fecha >= p_desde AND (p_hasta IS NULL OR pg.fecha < p_hasta);

  RETURN v_cobros + v_ing - v_egr - v_prov - v_pers;
END $$

DROP FUNCTION IF EXISTS fn_cuenta_saldo $$
CREATE FUNCTION fn_cuenta_saldo(p_id_cuenta INT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  -- El último arqueo más lo que se movió desde entonces. Sin ningún
  -- arqueo, NULL: una cuenta que nadie contó vale «no se sabe», no cero.
  DECLARE v_base  DECIMAL(14,2) DEFAULT NULL;
  DECLARE v_desde DATETIME DEFAULT NULL;

  SELECT monto_contado, fecha INTO v_base, v_desde
    FROM arqueo_cuenta
   WHERE id_cuenta = p_id_cuenta
   ORDER BY fecha DESC, id_arqueo_cuenta DESC
   LIMIT 1;

  IF v_base IS NULL THEN
    RETURN NULL;
  END IF;

  RETURN v_base + fn_cuenta_movido(p_id_cuenta, v_desde, NULL);
END $$

DROP FUNCTION IF EXISTS fn_arqueo_cuenta_esperado $$
CREATE FUNCTION fn_arqueo_cuenta_esperado(p_id_arqueo INT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  -- Lo que el banco debería haber dicho en ese arqueo: el anterior de la
  -- misma cuenta más lo que se movió entre los dos. El primero no tiene
  -- anterior y devuelve NULL — no hay contra qué comparar.
  DECLARE v_cuenta INT UNSIGNED DEFAULT NULL;
  DECLARE v_fecha  DATETIME DEFAULT NULL;
  DECLARE v_prev   DECIMAL(14,2) DEFAULT NULL;
  DECLARE v_pfecha DATETIME DEFAULT NULL;

  SELECT id_cuenta, fecha INTO v_cuenta, v_fecha
    FROM arqueo_cuenta WHERE id_arqueo_cuenta = p_id_arqueo;

  IF v_cuenta IS NULL THEN
    RETURN NULL;
  END IF;

  SELECT monto_contado, fecha INTO v_prev, v_pfecha
    FROM arqueo_cuenta
   WHERE id_cuenta = v_cuenta
     AND (fecha < v_fecha OR (fecha = v_fecha AND id_arqueo_cuenta < p_id_arqueo))
   ORDER BY fecha DESC, id_arqueo_cuenta DESC
   LIMIT 1;

  IF v_prev IS NULL THEN
    RETURN NULL;
  END IF;

  RETURN v_prev + fn_cuenta_movido(v_cuenta, v_pfecha, v_fecha);
END $$

DROP FUNCTION IF EXISTS fn_arqueo_cuenta_diferencia $$
CREATE FUNCTION fn_arqueo_cuenta_diferencia(p_id_arqueo INT UNSIGNED)
RETURNS DECIMAL(14,2)
READS SQL DATA
BEGIN
  -- Lo que dijo el banco menos lo que debería haber dicho. NULL en el
  -- primer arqueo de la cuenta, igual que `fn_caja_diferencia` sin conteo.
  DECLARE v_contado  DECIMAL(14,2) DEFAULT NULL;
  DECLARE v_esperado DECIMAL(14,2) DEFAULT NULL;

  SELECT monto_contado INTO v_contado FROM arqueo_cuenta WHERE id_arqueo_cuenta = p_id_arqueo;
  SET v_esperado = fn_arqueo_cuenta_esperado(p_id_arqueo);

  IF v_contado IS NULL OR v_esperado IS NULL THEN
    RETURN NULL;
  END IF;

  RETURN v_contado - v_esperado;
END $$

DELIMITER ;
