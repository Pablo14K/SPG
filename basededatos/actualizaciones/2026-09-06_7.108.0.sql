-- =====================================================================
-- SGP 7.108.0 — Roles que no llevan turno, y las alergias de la clienta
-- =====================================================================
--
-- Dos columnas nuevas, ninguna derivada de nada: las dos guardan un hecho
-- que hoy no está escrito en ningún otro lado, así que la 3FN se mantiene.
--
-- ---------------------------------------------------------------------
-- 1) `rol.exige_turno` — a quién le hace falta un turno
-- ---------------------------------------------------------------------
--
-- El aviso de «falta asignar turno» salía para toda cuenta con
-- `rol.es_personal = 1`, y eso incluye a gente que **no atiende**: la
-- recepcionista, la encargada de compras, quien lleva la caja. A esas
-- personas el sistema les pedía todos los días resolver algo que no era un
-- problema, y un aviso que no aplica enseña a ignorar los que sí.
--
-- La 7.107.0 lo tapó a medias exceptuando al Administrador **por id**
-- (`permisos.rol_admin`), que es justo lo que este proyecto no hace en
-- ninguna otra parte: los roles se crean desde la pantalla de Roles, así
-- que un salón que cree «Recepción» vuelve a tener el aviso y no hay
-- forma de callarlo sin tocar código.
--
-- Ahora es **un dato del rol**, editable desde Seguridad → Roles. El
-- turno existe para que la agenda sepa cuándo atiende esa persona: si el
-- rol no atiende, no hay nada que cargar.
--
-- **Por defecto 1**, que es lo que vale hoy para todos los roles menos
-- Administrador: la actualización no le saca el aviso a nadie que hoy lo
-- tenga con motivo.
--
-- Y la regla de la 7.107.0 se conserva: **basta con que UNO de los roles
-- de la cuenta exija turno** para que haga falta. La dueña que además
-- atiende lleva el rol Profesional encima, y ahí la agenda sí la va a
-- ofrecer.

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'rol'
                AND column_name = 'exige_turno');
SET @sql := IF(@hay = 0,
    'ALTER TABLE rol ADD COLUMN exige_turno TINYINT(1) NOT NULL DEFAULT 1
        COMMENT ''¿Las cuentas con este rol necesitan turno asignado?''',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- El Administrador arranca sin exigirlo, que es exactamente lo que la
-- 7.107.0 había escrito a mano en la consulta. Va por NOMBRE y no por id:
-- `permisos.rol_admin` es configurable, y el id 1 podría no serlo.
--
-- **Sólo la primera vez** (`@hay = 0`, o sea la columna no existía antes
-- de este guion). Una segunda corrida no vuelve a tocarlo: si el salón
-- marcó la casilla porque su Administrador sí atiende, esa decisión se
-- respeta. Es la misma precaución del relleno de `caja_fisica.creado_en`
-- en la 7.103.0 — un guion re-ejecutable no puede deshacer lo que alguien
-- configuró después.
SET @seed := IF(@hay = 0,
    'UPDATE rol SET exige_turno = 0 WHERE nombre = ''Administrador''',
    'DO 0');
PREPARE st3 FROM @seed; EXECUTE st3; DEALLOCATE PREPARE st3;

-- ---------------------------------------------------------------------
-- 2) `cliente.alergias` — lo que no se puede olvidar antes de atender
-- ---------------------------------------------------------------------
--
-- Se venía anotando en `observaciones`, que es un campo de notas sueltas:
-- ahí una alergia al amoníaco queda mezclada con «prefiere las 10» y con
-- «vino con su hija», y quien prepara la mezcla no la ve. El dato tiene
-- una consecuencia distinta de todas las demás notas —puede lastimar a
-- alguien— así que necesita su propio lugar y su propio destaque.
--
-- **No rompe la 3FN**: no es copia de nada ni se deduce de ninguna otra
-- tabla, es un atributo de la clienta que hoy no está guardado.
--
-- Admite NULL a propósito: «no sabemos» y «no tiene ninguna» son cosas
-- distintas, y afirmar la segunda sin que nadie la haya dicho sería
-- inventarla — la pantalla dice «sin alergias registradas», no «ninguna».

SET @hay2 := (SELECT COUNT(*) FROM information_schema.columns
               WHERE table_schema = DATABASE() AND table_name = 'cliente'
                 AND column_name = 'alergias');
SET @sql2 := IF(@hay2 = 0,
    'ALTER TABLE cliente ADD COLUMN alergias VARCHAR(300) NULL
        COMMENT ''Alergias y contraindicaciones. NULL = sin registrar''
        AFTER observaciones',
    'DO 0');
PREPARE st2 FROM @sql2; EXECUTE st2; DEALLOCATE PREPARE st2;

-- =====================================================================
-- Comprobación
-- =====================================================================
SELECT 'rol.exige_turno' AS columna,
       (SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'rol'
           AND column_name = 'exige_turno') AS existe,
       (SELECT COUNT(*) FROM rol WHERE exige_turno = 0) AS roles_sin_turno
UNION ALL
SELECT 'cliente.alergias',
       (SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'cliente'
           AND column_name = 'alergias'),
       (SELECT COUNT(*) FROM cliente WHERE alergias IS NOT NULL);
