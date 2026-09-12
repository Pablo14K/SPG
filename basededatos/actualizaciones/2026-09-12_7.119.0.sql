-- =====================================================================
-- SGP 7.119.0 — El mismo servicio puede ir para VARIAS personas de la
--               misma cita: dos cortes, dos amigas, una reserva
-- =====================================================================
--
-- Desde la 7.117.0 cada servicio de la cita dice para quién es
-- (`cita_servicio.persona`), pero `uq_cita_servicio (id_cita,
-- id_servicio)` seguía impidiendo el mismo servicio dos veces en la
-- misma cita: dos amigas que vienen a cortarse el pelo tenían que
-- reservar dos citas. Reportado tal cual —«el sistema solo permite
-- elegir un cliente por servicio, debe permitir que se pueda poner más
-- de un cliente al mismo servicio»—.
--
-- El único cambia de forma: **(id_cita, id_servicio, persona)**. Lo que
-- sigue sin poder pasar es el mismo servicio dos veces PARA LA MISMA
-- persona, que es lo que el único siempre quiso decir. Las rutinas ya
-- estaban escritas sobre filas: `fn_cita_duracion` suma las de la
-- misma profesional en el mismo turno, `sp_emitir_factura` arma un
-- renglón por fila y `trg_citaserv_bi` sólo mira OTRAS citas.
--
-- **El orden importa y no es cosmético**: `fk_cs_cita` se apoya en el
-- único viejo —es el índice que empieza por `id_cita`—, así que MariaDB
-- no deja soltarlo hasta que exista otro que empiece por la misma
-- columna. El nuevo se crea ANTES.
--
-- Y `vw_agenda_citas.servicios` deja de repetir el nombre: con dos
-- cortes decía «Corte, Corte», ahora dice «Corte ×2».
--
-- Re-ejecutable y sin tocar datos.
--
--     docker exec sgp_app sh -c 'mysql --skip-ssl -hbd -uroot -p"$DB_PASSWORD" \
--       --default-character-set=utf8mb4 peluqueria_bd \
--       < basededatos/actualizaciones/2026-09-12_7.119.0.sql'
--
-- Al terminar: `docker exec sgp_app php artisan sgp:diagnostico --produccion`
-- tiene que decir 87 CHECK, 22 procedimientos, 43 funciones y «Todo en orden».

-- --- 1. El único pasa a incluir a la persona --------------------------
SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = 'cita_servicio' AND index_name = 'uq_cita_servicio_persona');
SET @sql := IF(@hay = 0,
  'ALTER TABLE cita_servicio ADD UNIQUE KEY uq_cita_servicio_persona (id_cita, id_servicio, persona)',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = 'cita_servicio' AND index_name = 'uq_cita_servicio');
SET @sql := IF(@hay > 0,
  'ALTER TABLE cita_servicio DROP INDEX uq_cita_servicio',
  'DO 0');
PREPARE p FROM @sql; EXECUTE p; DEALLOCATE PREPARE p;

-- --- 2. La vista de la agenda: «Corte ×2» y no «Corte, Corte» ----------
CREATE OR REPLACE VIEW vw_agenda_citas AS
SELECT c.id_cita,
       c.fecha_hora,
       fn_cita_duracion(c.id_cita) AS duracion_min,
       TRIM(CONCAT_WS(' ', pc.nombre, pc.apellido)) AS cliente,
       pc.telefono,
       TRIM(CONCAT_WS(' ', pu.nombre, pu.apellido)) AS profesional,
       ec.nombre AS estado,
       (SELECT GROUP_CONCAT(DISTINCT
                 CONCAT(s.nombre,
                        IF((SELECT COUNT(*) FROM cita_servicio cs2
                             WHERE cs2.id_cita = cs.id_cita AND cs2.id_servicio = cs.id_servicio) > 1,
                           CONCAT(' ×', (SELECT COUNT(*) FROM cita_servicio cs3
                                          WHERE cs3.id_cita = cs.id_cita AND cs3.id_servicio = cs.id_servicio)),
                           ''))
                 ORDER BY s.nombre SEPARATOR ', ')
          FROM cita_servicio cs
          JOIN servicio s ON s.id_servicio = cs.id_servicio
         WHERE cs.id_cita = c.id_cita) AS servicios,
       c.observaciones
  FROM cita c
  JOIN cliente cl ON cl.id_cliente = c.id_cliente
  JOIN persona pc ON pc.id_persona = cl.id_persona
  JOIN usuario u  ON u.id_usuario = c.id_usuario
  JOIN persona pu ON pu.id_persona = u.id_persona
  JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita;
