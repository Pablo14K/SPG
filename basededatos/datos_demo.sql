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
INSERT IGNORE INTO servicio (id_categoria_servicio, nombre, descripcion, precio, duracion_min, tasa_iva, activo, requiere_exclusividad) VALUES
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Corte'),                'Corte de dama',            'Corte con lavado y peinado',            75000,  45, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Corte'),                'Corte de caballero',       'Corte clásico o con máquina',           50000,  30, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Corte'),                'Corte de niño',            'Hasta 12 años',                         40000,  30, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Peinado y brushing'),   'Brushing',                 'Secado y modelado',                     60000,  40, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Peinado y brushing'),   'Peinado de fiesta',        'Recogido o semirecogido',              120000,  60, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Coloracion'),           'Coloración completa',      'Color de raíz a puntas',               280000, 120, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Coloracion'),           'Retoque de raíz',          'Sólo el crecimiento',                  150000,  75, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Coloracion'),           'Mechas / balayage',        'Aclarado por mechones',                350000, 180, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Tratamiento capilar'),  'Lavado y acondicionado',   'Lavado con masaje',                     25000,  20, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Tratamiento capilar'),  'Tratamiento capilar',      'Hidratación profunda',                  90000,  50, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Tratamiento capilar'),  'Keratina',                 'Alisado con keratina',                 400000, 180, 10, 1, 1),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Manicura y pedicura'),  'Manicura',                 'Manos, esmaltado tradicional',          45000,  40, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Manicura y pedicura'),  'Manicura semipermanente',  'Esmaltado semipermanente',              75000,  60, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Manicura y pedicura'),  'Pedicura',                 'Pies, esmaltado tradicional',           55000,  50, 10, 1, 0),
  ((SELECT id_categoria_servicio FROM categoria_servicio WHERE nombre = 'Otros'),                'Depilación de cejas',      'Diseño y depilación',                   30000,  20, 10, 1, 0);

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
INSERT IGNORE INTO producto (id_categoria, nombre, descripcion, unidad_medida, stock_minimo, precio_costo, precio_venta, tasa_iva, activo, contenido, unidad_consumo) VALUES
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar'),        'Shampoo profesional 1L',   'Para lavado en el salón',        'unidad', 3,  85000, 130000, 10, 1, 1000, 'ml'),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar'),        'Acondicionador 1L',        'Para lavado en el salón',        'unidad', 3,  80000, 125000, 10, 1, 1000, 'ml'),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Tinturas y coloracion'),  'Agua oxigenada 900ml',     'Revelador 20 volúmenes',         'unidad', 4,  35000,  55000, 10, 1,  900, 'ml'),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Tinturas y coloracion'),  'Tintura profesional',      'Tubo de 60 g, varios tonos',     'unidad', 6,  45000,  70000, 10, 1, NULL, NULL),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar'),        'Ampolla de keratina',      'Sachet individual',              'unidad', 10, 18000,  32000, 10, 1, NULL, NULL),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Cuidado capilar'),        'Serum reparador 100ml',    'Puntas abiertas',                'unidad', 5,  40000,  68000, 10, 1, NULL, NULL),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Insumos descartables'),   'Guantes de latex (caja)',  'Caja por 100 unidades',          'caja',   2,  38000,  60000, 10, 1, NULL, NULL),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Insumos descartables'),   'Toallas descartables',     'Paquete por 50',                 'paquete',3,  25000,  40000, 10, 1, NULL, NULL),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Herramientas y accesorios'),'Esmalte semipermanente', 'Frasco de 15 ml',                'unidad', 8,  22000,  38000, 10, 1, NULL, NULL),
  ((SELECT id_categoria FROM categoria_producto WHERE nombre = 'Productos de reventa'),    'Shampoo x 300ml (venta)','Para llevar',                    'unidad', 5,  45000,  85000, 10, 1, NULL, NULL);

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
       'y$SMjbuml1HAqLKDz59hh1iO4dfg7ja7mJoEfTO6iARaPUybSSd0qeG', 1
FROM (SELECT '3800111' ci, 'marta'  username UNION ALL
      SELECT '3800222',    'rocio'           UNION ALL
      SELECT '3800333',    'lucia'           UNION ALL
      SELECT '3800444',    'sofia') u
JOIN persona p ON p.cedula = u.ci
WHERE NOT EXISTS (SELECT 1 FROM usuario us WHERE us.id_persona = p.id_persona);

-- ---- Turnos ---------------------------------------------------------------
-- Sin turno asignado la agenda no ofrece ni un horario: `fn_verificar_dispo-
-- nibilidad` exige que la cita entre en uno.
INSERT IGNORE INTO turno_laboral (id_sucursal, nombre, hora_inicio, hora_fin, activo) VALUES
  (1, 'Turno Mañana', '08:00:00', '13:00:00', 1),
  (1, 'Turno Tarde',  '13:00:00', '19:00:00', 1);

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
