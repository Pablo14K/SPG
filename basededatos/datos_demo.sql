-- ---------------------------------------------------------------------------
--  DATOS DE ARRANQUE — para que el sistema se pueda probar apenas se instala
-- ---------------------------------------------------------------------------
--
--  Carga el catálogo que hace falta para recorrer el sistema de punta a punta:
--  servicios con precio y duración, productos —incluidos los fraccionados—,
--  proveedores, profesionales con su turno y su comisión, y los timbrados.
--
--  **Sin esto, una cuenta recién creada no puede hacer nada**: no hay servicios
--  para agendar, ni profesionales con horario, ni productos que descontar. Con
--  esto, se entra y se reserva.
--
--  QUÉ NO CARGA, a propósito: ni una cita, ni una factura, ni un cobro, ni un
--  movimiento de caja. La operación se genera usando el sistema, que es de lo
--  que se trata. Los clientes tampoco: se crean desde el portal.
--
--  Se corre sobre `peluqueria_bd` después del esquema:
--
--      mysql -u root peluqueria_bd < basededatos/datos_demo.sql
--
--  Es re-ejecutable: todo va con INSERT IGNORE o comprobando antes, así que
--  correrlo dos veces no duplica nada.
--
--  Las contraseñas de los profesionales son `profesional123`, con el mismo
--  hash bcrypt para todos. **Hay que cambiarlas antes de usar el sistema en
--  serio**, y el salón las cambia desde Seguridad → Usuarios.
-- ---------------------------------------------------------------------------

-- ---- Servicios ------------------------------------------------------------
-- Los precios son de referencia de una peluquería de Luque. La duración es lo
-- que la agenda usa para calcular los huecos, así que conviene que sea real.
-- **La ZONA es lo que decide qué se puede hacer a la vez** (7.43.0): dos
-- servicios de la misma zona se turnan y sus tiempos se suman; de zonas
-- distintas conviven. Sin zona cargada un servicio no comparte con nadie, así
-- que el salón lo dejaría en paralelo con cualquier cosa sin quererlo.
--
-- La SEÑA va como porcentaje del precio, no como monto: un monto fijo se
-- separa del precio el día que el servicio sube. Se le pone a los tres caros,
-- que son los que dejan el sillón ocupado tres horas si la clienta no viene.
--
-- `requiere_exclusividad` sigue en la tabla y NO la usa nadie desde la 7.43.0:
-- se deja por el mismo motivo que las piezas de la venta de productos.
INSERT IGNORE INTO servicio (id_categoria_servicio, id_zona, nombre, descripcion, precio, sena_porcentaje, duracion_min, tasa_iva, activo, requiere_exclusividad) VALUES
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Corte'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Corte de dama', 'Corte con lavado y peinado', 75000, NULL,  45, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Corte'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Corte de caballero', 'Corte clásico o con máquina', 50000, NULL,  30, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Corte'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Corte de niño', 'Hasta 12 años', 40000, NULL,  30, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Peinado y brushing'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Brushing', 'Secado y modelado', 60000, NULL,  40, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Peinado y brushing'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Peinado de fiesta', 'Recogido o semirecogido', 120000, NULL,  60, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Coloracion'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Coloración completa', 'Color de raíz a puntas', 280000, 50, 120, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Coloracion'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Retoque de raíz', 'Sólo el crecimiento', 150000, NULL,  75, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Coloracion'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Mechas / balayage', 'Aclarado por mechones', 350000, 50, 180, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Tratamiento capilar'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Lavado y acondicionado', 'Lavado con masaje', 25000, NULL,  20, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Tratamiento capilar'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Tratamiento capilar', 'Hidratación profunda', 90000, NULL,  50, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Tratamiento capilar'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Cabello'), 'Keratina', 'Alisado con keratina', 400000, 50, 180, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Manicura y pedicura'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Manos'), 'Manicura', 'Manos, esmaltado tradicional', 45000, NULL,  40, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Manicura y pedicura'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Manos'), 'Manicura semipermanente', 'Esmaltado semipermanente', 75000, NULL,  60, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Manicura y pedicura'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Pies'), 'Pedicura', 'Pies, esmaltado tradicional', 55000, NULL,  50, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Otros'), (SELECT id_zona FROM zona_servicio WHERE nombre = 'Rostro'), 'Depilación de cejas', 'Diseño y depilación', 30000, NULL,  20, 10, 1, 0);

-- **Qué servicios publica cada local** (7.30.0). La convención es «sin filas
-- vale en todas», pero en cuanto UNO tiene una fila deja de valer en todas: por
-- eso se publica el catálogo entero en la sucursal 1, que es la única que trae
-- la base que se entrega. Sin esto, un local recién abierto nace sin nada que
-- reservar y la clienta que lo elige en el portal ve la carta vacía.
INSERT IGNORE INTO servicio_sucursal (id_servicio, id_sucursal)
SELECT s.id_servicio, 1 FROM servicio s;

-- ---- Proveedores ----------------------------------------------------------
-- Los datos de las personas van SÓLO en `persona`: `proveedor` la referencia.
INSERT IGNORE INTO persona (nombre, apellido, ruc, telefono, email, direccion) VALUES
  ('Distribuidora Capilar SA', '', '80012345-0', '021445566', 'ventas@capilar.com.py',  'Avda. Mariscal López 1234, Asunción'),
  ('Belleza Total SRL',        '', '80098765-1', '021778899', 'pedidos@bellezatotal.py', 'Ruta Mcal. Estigarribia km 12, Luque'),
  ('Insumos del Este SA',      '', '80055443-3', '021332211', 'contacto@insumoseste.py', 'Avda. España 890, Asunción');

INSERT IGNORE INTO proveedor (id_persona, contacto, activo)
SELECT p.id_persona, c.contacto, 1
FROM (SELECT '80012345-0' ruc, 'Marisa Duarte'  contacto UNION ALL
      SELECT '80098765-1',     'Jorge Cabrera'          UNION ALL
      SELECT '80055443-3',     'Liliana Ayala') c
JOIN persona p ON p.ruc = c.ruc
WHERE NOT EXISTS (SELECT 1 FROM proveedor pr WHERE pr.id_persona = p.id_persona);

-- ---- Productos ------------------------------------------------------------
-- Los tres primeros son FRACCIONADOS: se compran por frasco y se gastan de a
-- mililitros. `contenido` + `unidad_consumo` son lo que activa esa conversión.
-- **`stock_minimo` NO va acá**: la 7.33.0 pasó el catálogo a ser único y el
-- mínimo es de cada local, así que vive en `producto_sucursal` — se carga
-- unas líneas más abajo, junto con qué local maneja cada producto.
-- **Va con NOT EXISTS y no con INSERT IGNORE**: `producto` no tiene índice único
-- por nombre, así que IGNORE no frena nada y una segunda corrida deja el
-- catálogo duplicado — es el defecto de los 20 productos de la 7.13.2.
INSERT INTO producto (id_categoria, nombre, descripcion, unidad_medida, precio_costo, precio_venta, tasa_iva, activo, contenido, unidad_consumo)
SELECT v.* FROM (
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar') AS id_categoria, 'Shampoo profesional 1L' AS nombre, 'Para lavado en el salón' AS descripcion, 'unidad' AS unidad_medida, 85000 AS precio_costo, 130000 AS precio_venta, 10 AS tasa_iva, 1 AS activo, 1000 AS contenido, 'ml' AS unidad_consumo UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar'),         'Acondicionador 1L', 'Para lavado en el salón', 'unidad',   80000, 125000, 10, 1, 1000, 'ml' UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Tinturas y coloracion'),   'Agua oxigenada 900ml', 'Revelador 20 volúmenes', 'unidad',   35000,  55000, 10, 1,  900, 'ml' UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Tinturas y coloracion'),   'Tintura profesional', 'Tubo de 60 g, varios tonos', 'unidad',   45000,  70000, 10, 1, NULL, NULL UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar'),         'Ampolla de keratina', 'Sachet individual', 'unidad',  18000,  32000, 10, 1, NULL, NULL UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar'),         'Serum reparador 100ml', 'Puntas abiertas', 'unidad',   40000,  68000, 10, 1, NULL, NULL UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Insumos descartables'),    'Guantes de latex (caja)', 'Caja por 100 unidades', 'caja',     38000,  60000, 10, 1, NULL, NULL UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Insumos descartables'),    'Toallas descartables', 'Paquete por 50', 'paquete',  25000,  40000, 10, 1, NULL, NULL UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Herramientas y accesorios'), 'Esmalte semipermanente', 'Frasco de 15 ml', 'unidad',   22000,  38000, 10, 1, NULL, NULL UNION ALL
      SELECT (SELECT id_categoria FROM categoria_producto WHERE nombre = 'Productos de reventa'),     'Shampoo x 300ml (venta)', 'Para llevar', 'unidad',   45000,  85000, 10, 1, NULL, NULL
) v
WHERE NOT EXISTS (SELECT 1 FROM producto x WHERE x.nombre = v.nombre);

-- **Qué productos maneja cada local, y con qué mínimo** (7.33.0). El catálogo
-- es único y el stock es de cada sede, así que el mínimo también: un salón
-- grande guarda más. Sin fila acá el producto existe y **ese local no lo
-- maneja**, así que «Registrar atención» no lo ofrece.
INSERT IGNORE INTO producto_sucursal (id_producto, id_sucursal, stock_minimo, activo)
SELECT p.id_producto, 1, m.minimo, 1
FROM (SELECT 'Shampoo profesional 1L' n,  3 minimo UNION ALL
      SELECT 'Acondicionador 1L',        3 UNION ALL
      SELECT 'Agua oxigenada 900ml',     4 UNION ALL
      SELECT 'Tintura profesional',      6 UNION ALL
      SELECT 'Ampolla de keratina',     10 UNION ALL
      SELECT 'Serum reparador 100ml',    5 UNION ALL
      SELECT 'Guantes de latex (caja)',  2 UNION ALL
      SELECT 'Toallas descartables',     3 UNION ALL
      SELECT 'Esmalte semipermanente',   8 UNION ALL
      SELECT 'Shampoo x 300ml (venta)',  5) m
JOIN producto p ON p.nombre = m.n;

-- ---- Profesionales --------------------------------------------------------
-- Otra vez: la persona va en `persona`, y `usuario` la referencia.
INSERT IGNORE INTO persona (nombre, apellido, cedula, telefono, email) VALUES
  ('Marta',  'Cáceres',  '3800111', '0981200100', 'marta.caceres@peluqueria.local'),
  ('Rocío',  'Duarte',   '3800222', '0981200200', 'rocio.duarte@peluqueria.local'),
  ('Lucía',  'Benítez',  '3800333', '0981200300', 'lucia.benitez@peluqueria.local'),
  ('Sofía',  'Espínola', '3800444', '0981200400', 'sofia.espinola@peluqueria.local');

-- La contraseña de las cuatro es `profesional123`.
INSERT IGNORE INTO usuario (id_persona, id_rol, id_sucursal, username, password_hash, activo)
SELECT p.id_persona, 2, 1, u.username,
       '$2y$12$Tl6dYeN6n5TYvdP/RZH6v.oll4BX3dAjKYnrewjAQ0fz7pWwT2jY6', 1
FROM (SELECT '3800111' ci, 'marta'  username UNION ALL
      SELECT '3800222',    'rocio'           UNION ALL
      SELECT '3800333',    'lucia'           UNION ALL
      SELECT '3800444',    'sofia') u
JOIN persona p ON p.cedula = u.ci
WHERE NOT EXISTS (SELECT 1 FROM usuario us WHERE us.id_persona = p.id_persona);

-- ---- Turnos ---------------------------------------------------------------
-- Sin turno asignado la agenda no ofrece ni un horario: `fn_verificar_dispo-
-- nibilidad` exige que la cita entre en uno.
-- Igual que `producto`: sin índice único por nombre, IGNORE no frena la
-- segunda corrida y quedarían cuatro turnos donde hay dos.
INSERT INTO turno_laboral (id_sucursal, nombre, hora_inicio, hora_fin, flexibilidad_entrada_min, activo)
SELECT v.* FROM (
      SELECT 1 AS id_sucursal, 'Turno Mañana' AS nombre, '08:00:00' AS hora_inicio, '13:00:00' AS hora_fin, 15 AS flexibilidad_entrada_min, 1 AS activo UNION ALL
      SELECT 1, 'Turno Tarde',  '14:00:00', '19:00:00', 15, 1
) v
WHERE NOT EXISTS (SELECT 1 FROM turno_laboral x WHERE x.nombre = v.nombre);

-- Los días van 1 = lunes … 6 = sábado, una fila por día (1FN).
INSERT IGNORE INTO turno_dia (id_turno, dia_semana)
SELECT t.id_turno, d.dia
FROM turno_laboral t
JOIN (SELECT 1 dia UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
      SELECT 4     UNION ALL SELECT 5 UNION ALL SELECT 6) d
WHERE t.nombre IN ('Turno Mañana', 'Turno Tarde');

-- Marta y Lucía a la mañana, Rocío y Sofía a la tarde.
INSERT IGNORE INTO usuario_turno (id_usuario, id_turno)
SELECT u.id_usuario, t.id_turno
FROM usuario u
JOIN persona p ON p.id_persona = u.id_persona
JOIN turno_laboral t ON t.nombre = IF(u.username IN ('marta', 'lucia'), 'Turno Mañana', 'Turno Tarde')
WHERE u.username IN ('marta', 'rocio', 'lucia', 'sofia');

-- ---- Qué hace cada una ----------------------------------------------------
-- `usuario_servicio` decide a quién le ofrece la agenda cada servicio, y su
-- criterio es permisivo: **quien no tiene ninguno cargado los hace todos**.
-- Con la tabla vacía, entonces, la agenda le ofrece una coloración a la
-- manicurista y el «no» llega el día de la cita.
--
-- El reparto de acá muestra justamente para qué existe la tabla: Lucía es la
-- peluquera, Marta hace manos, pies y cejas, Rocío es la del color y Sofía la
-- generalista. **Cada servicio del catálogo lo hace al menos una**, que es lo
-- que hay que cuidar al tocarlo: uno que no haga nadie no se puede reservar
-- con nadie.
--
-- Se apuntan por NOMBRE y no por id, como los canjes: si el catálogo se
-- regenera con otros ids, esto sigue señalando al servicio correcto.
--
-- **Ana Propietaria queda afuera a propósito.** Administra y no atiende, así
-- que no lleva ni turno ni servicios — es el caso que AG-01 vino a arreglar,
-- cuando la propietaria y la recepcionista se llevaron 302 de 557 citas que el
-- salón nunca iba a poder dar.
INSERT IGNORE INTO usuario_servicio (id_usuario, id_servicio)
SELECT u.id_usuario, s.id_servicio
FROM (SELECT 'lucia' username, 'Corte de dama' serv           UNION ALL
      SELECT 'lucia', 'Corte de caballero'                    UNION ALL
      SELECT 'lucia', 'Lavado y acondicionado'                UNION ALL
      SELECT 'lucia', 'Mechas / balayage'                     UNION ALL
      SELECT 'lucia', 'Peinado de fiesta'                     UNION ALL
      SELECT 'marta', 'Manicura'                              UNION ALL
      SELECT 'marta', 'Manicura semipermanente'               UNION ALL
      SELECT 'marta', 'Pedicura'                              UNION ALL
      SELECT 'marta', 'Depilación de cejas'                   UNION ALL
      SELECT 'rocio', 'Coloración completa'                   UNION ALL
      SELECT 'rocio', 'Keratina'                              UNION ALL
      SELECT 'rocio', 'Retoque de raíz'                       UNION ALL
      SELECT 'rocio', 'Tratamiento capilar'                   UNION ALL
      SELECT 'rocio', 'Mechas / balayage'                     UNION ALL
      SELECT 'rocio', 'Brushing'                              UNION ALL
      SELECT 'sofia', 'Corte de niño'                         UNION ALL
      SELECT 'sofia', 'Corte de dama'                         UNION ALL
      SELECT 'sofia', 'Lavado y acondicionado'                UNION ALL
      SELECT 'sofia', 'Brushing'                              UNION ALL
      SELECT 'sofia', 'Peinado de fiesta') r
JOIN usuario u  ON u.username = r.username
JOIN servicio s ON s.nombre  = r.serv;

-- ---- Comisiones -----------------------------------------------------------
-- Sin comisión cargada, la liquidación al personal da cero y el informe del
-- equipo dice «sin cargar». Se carga una general para cada una.
INSERT IGNORE INTO comision (id_usuario, id_servicio, tipo, valor, vigente_desde, activo)
SELECT u.id_usuario, NULL, 'PORCENTAJE', 15.00, CURDATE(), 1
FROM usuario u
WHERE u.username IN ('marta', 'rocio', 'lucia', 'sofia')
  AND NOT EXISTS (SELECT 1 FROM comision c WHERE c.id_usuario = u.id_usuario);

-- ---- Timbrados ------------------------------------------------------------
-- Cada tipo de comprobante necesita el suyo. Sin timbrado no se puede numerar
-- nada, y la pantalla de emitir queda avisando que falta.
--
-- El punto de expedición separa las series: 001 para la Factura y la Nota de
-- crédito, 999 para el Comprobante de pago, que es interno del salón.
INSERT IGNORE INTO timbrado (id_sucursal, id_tipo_comprobante, nro_timbrado, establecimiento, punto_expedicion, fecha_inicio, fecha_fin, nro_desde, nro_hasta, activo) VALUES
  (1, 1, '12345678', '001', '001', CURDATE(), CURDATE() + INTERVAL 1 YEAR, 1, 9999999, 1),
  (1, 5, '12345679', '001', '001', CURDATE(), CURDATE() + INTERVAL 1 YEAR, 1, 9999999, 1),
  (1, 8, '12345680', '001', '999', CURDATE(), CURDATE() + INTERVAL 1 YEAR, 1, 9999999, 1);

-- ---- Canjes por puntos ----------------------------------------------------
-- Con qué arranca el programa de fidelización. Sin ninguno cargado, la clienta
-- junta puntos y el portal le dice que todavía no hay nada para canjear: se
-- ve la pantalla, pero no la función.
--
-- Se apunta por NOMBRE y no por id: si el catálogo de servicios se regenera
-- con otros ids, esto sigue señalando el servicio correcto.
--
-- La vigencia es de 30 días, que es el plazo con el que viene el formulario.
-- El salón lo cambia desde Clientes → Canjes por puntos, sin tocar código.
-- **La escala tiene que ser alcanzable, o el programa es decorativo.**
--
-- Se entregaba con 3.000 y 2.000 puntos, y a razón de 1 punto cada Gs. 10.000
-- eso pide Gs. 30.000.000 y Gs. 20.000.000 de consumo ACUMULADO. La simulación
-- de 30 días lo midió: la clienta que más juntó llegó a 326 puntos. O sea que
-- el portal le mostraba un catálogo que nadie podía alcanzar nunca — la misma
-- clase de problema que una función apagada en silencio.
--
-- Con estos valores, una clienta habitual —unos Gs. 200.000 por visita, una vez
-- al mes— llega al lavado en poco más de medio año y a la coloración en dos.
-- Es lo que un programa de fidelización tiene que hacer: premiar la vuelta.
--
-- Los dos números se editan sin tocar código: los puntos desde Clientes →
-- Canjes por puntos, y la relación con el guaraní desde Servicios → Descuentos.
INSERT IGNORE INTO servicio_canjeable (id_servicio, puntos, dias_vigencia, activo)
SELECT s.id_servicio, 400, 30, 1 FROM servicio s WHERE s.nombre = 'Coloración completa';

INSERT IGNORE INTO servicio_canjeable (id_servicio, puntos, dias_vigencia, activo)
SELECT s.id_servicio, 150, 30, 1 FROM servicio s WHERE s.nombre = 'Lavado y acondicionado';
