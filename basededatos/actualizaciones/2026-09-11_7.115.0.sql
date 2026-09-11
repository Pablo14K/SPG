-- =========================================================================
--  Actualización de la base a la 7.115.0
--
--  Se corre UNA vez sobre una base que ya está andando, con datos del salón
--  adentro. **No toca ni una fila**: es un disparador, y un disparador se
--  reemplaza entero (DROP + CREATE), así que volver a correr esto es inocuo.
--
--      docker exec spg_app sh -c 'mysql --skip-ssl -hbd -uroot -p"$DB_PASSWORD" \
--        --default-character-set=utf8mb4 peluqueria_bd \
--        < basededatos/actualizaciones/2026-09-11_7.115.0.sql'
--
--  Qué cambia:
--    · trg_citaserv_bi   comprueba la habilitación de QUIEN HACE el servicio,
--                        no del dueño de la cita
--
--  Al terminar, comprobar con:
--      docker exec spg_app php artisan spg:diagnostico --produccion
--  Tiene que seguir diciendo 17 disparadores, y «Todo en orden».
-- =========================================================================


-- ---- trg ----
-- La habilitación se comprueba sobre QUIEN HACE ese servicio.
--
-- El disparador preguntaba `fn_puede_realizar(dueño de la cita, servicio)`
-- para CADA servicio de la cita, y desde la 5.3.0 cada servicio puede tener
-- su propio profesional (`cita_servicio.id_usuario`, NULL = el dueño). Así que
-- una cita repartida entre dos personas con oficios distintos —Lucía el corte,
-- Gloria la manicura— se rechazaba SIEMPRE: fuera quien fuera el dueño, no
-- hace lo de la otra. Se leía en pantalla como «el profesional no está
-- habilitado para alguno de esos servicios», sobre un reparto en que cada una
-- hace exactamente lo suyo.
--
-- No se notaba porque el guardado buscaba a UNA persona que hiciera TODO, y
-- con eso la cita repartida entre oficios distintos nunca llegaba al
-- disparador. Desde la 7.115.0 la pantalla ofrece la intersección de las
-- agendas de quienes atienden y reparte entre ellas, así que el disparador
-- tiene que mirar lo mismo que el resto de la base: quién hace cada servicio
-- es `COALESCE(cs.id_usuario, c.id_usuario)`, que es como lo resuelven
-- `fn_cita_duracion_de` y compañía.
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
  DECLARE v_para_txt VARCHAR(120) DEFAULT '';
  DECLARE v_repetido INT DEFAULT 0;
  DECLARE v_nombre   VARCHAR(100) DEFAULT '';
  DECLARE v_msg      VARCHAR(255);

  SELECT c.id_usuario, c.id_cliente, DATE(c.fecha_hora),
         COALESCE(c.para_otra_persona, 0), LOWER(COALESCE(TRIM(c.nombre_para), '')),
         COALESCE(TRIM(c.nombre_para), '')
    INTO v_usuario, v_cliente, v_dia, v_otra, v_para, v_para_txt
    FROM cita c WHERE c.id_cita = NEW.id_cita;

  -- Quien hace ESTE servicio: el asignado, o el dueño si no hay asignado.
  IF fn_puede_realizar(COALESCE(NEW.id_usuario, v_usuario), NEW.id_servicio) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quien hace ese servicio en la cita no esta habilitado para el.';
  END IF;

  -- El mismo servicio no se repite en el día PARA LA MISMA PERSONA (7.88.0).
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
