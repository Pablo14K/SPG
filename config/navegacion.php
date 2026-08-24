<?php

declare(strict_types=1);

/**
 * Los cuatro niveles de navegación del sistema, en un solo lugar.
 *
 * Cada nivel contesta una pregunta distinta, y si se saca alguno la anterior
 * vuelve a quedar sin respuesta:
 *
 *   · barra de módulos  → ¿a qué otro módulo voy?
 *   · migas de pan      → ¿dónde estoy y cómo vuelvo?
 *   · tarjetas          → ¿qué hay dentro de este módulo?
 *   · accesos rápidos   → ¿qué suelo hacer después de esto?
 *
 * Las migas y los accesos rápidos salen solos de acá: ninguna vista los
 * declara, así no se desfasan cuando se renombra una pantalla.
 */
return [

    // -----------------------------------------------------------------
    //  Tarjetas del panel de gestión. Cada una declara el módulo que la
    //  habilita; el rol que no lo tenga, no la ve.
    // -----------------------------------------------------------------
    'modulos' => [
        ['mod' => 'citas',         'ruta' => 'citas.index',         'ic' => 'calendar-event', 'titulo' => 'Citas',     'sub' => 'Calendario · Nueva cita · Estados · Ausencias'],
        ['mod' => 'clientes',      'ruta' => 'clientes.index',      'ic' => 'people',         'titulo' => 'Clientes',           'sub' => 'Registro · Historial · Preferencias · Valoraciones'],
        ['mod' => 'servicios',     'ruta' => 'servicios.index',     'ic' => 'scissors',       'titulo' => 'Servicios',          'sub' => 'Catálogo · Categorías · Descuentos · Promos'],
        ['mod' => 'inventario',    'ruta' => 'inventario.index',    'ic' => 'box-seam',       'titulo' => 'Inventario',         'sub' => 'Productos · Categorías · Stock · Compras'],
        ['mod' => 'facturacion',   'ruta' => 'facturacion.index',   'ic' => 'cash-stack',     'titulo' => 'Tesorería', 'sub' => 'Cobros · Facturas · Caja · Timbrados'],
        ['mod' => 'reportes',      'ruta' => 'reportes.index',      'ic' => 'bar-chart',      'titulo' => 'Reportes',           'sub' => 'Servicios top · Demanda · Ingresos'],
        // **Seguridad se partió en tres**, por pedido del usuario. Cada una
        // contesta una pregunta distinta y juntas obligaban a buscar los
        // turnos en el mismo lugar que la auditoría. Las tres conservan el
        // fondo oscuro, que las separa de la operación diaria del salón.
        ['mod' => 'personal',      'ruta' => 'seguridad.personal.index',      'ic' => 'person-badge',   'titulo' => 'Personal',           'sub' => 'Turnos · Asistencia · Comisiones', 'dark' => true],
        ['mod' => 'seguridad',     'ruta' => 'seguridad.index',     'ic' => 'shield-lock',    'titulo' => 'Seguridad',          'sub' => 'Usuarios · Roles · Auditoría', 'dark' => true],
        ['mod' => 'configuracion', 'ruta' => 'seguridad.configuracion.index', 'ic' => 'sliders',        'titulo' => 'Configuración',      'sub' => 'Mi cuenta · Sucursales · Contacto', 'dark' => true],
    ],

    // -----------------------------------------------------------------
    //  Catálogo de pantallas: etiqueta, ícono y CLAVE DEL PERMISO.
    //
    //  La clave del permiso tiene que ser la misma que pide el middleware de
    //  la pantalla: de acá salen los accesos rápidos, y un atajo hacia algo
    //  que el rol no puede abrir es peor que no ofrecerlo.
    // -----------------------------------------------------------------
    'pantallas' => [
        'citas.agenda'              => ['Agenda',                'calendar-week',      'citas.agenda'],
        'citas.form'                => ['Nueva cita',            'calendar-plus',      'citas.agenda'],
        'citas.atender'             => ['Registrar atención',    'clipboard-check',    'citas.atencion', false],
        'citas.ausencias'           => ['Excepciones',           'calendar-x',         'citas.ausencias'],
        'citas.reasignar'           => ['Reasignar citas',       'arrow-left-right',   'citas.agenda', false],
        'clientes.lista'            => ['Clientes',              'people',             'clientes.registro'],
        'clientes.form'             => ['Nuevo cliente',         'person-plus',        'clientes.registro', false],
        'clientes.historial'        => ['Historial',             'clock-history',      'clientes.registro', false],
        'clientes.fidelizacion'     => ['Fidelización',          'award',              'clientes.fidelizacion'],
        'clientes.canjes'           => ['Canjes por puntos',     'gift',               'clientes.canjes'],
        'clientes.valoraciones'     => ['Valoraciones',          'star',               'clientes.valoraciones'],
        'servicios.lista'           => ['Servicios',             'scissors',           'servicios.catalogo'],
        'servicios.categorias'      => ['Categorías',            'tags',               'servicios.categorias'],
        'servicios.zonas'           => ['Zonas del cuerpo',      'person-arms-up',     'servicios.categorias'],
        'servicios.descuentos'      => ['Promociones',           'percent',            'servicios.descuentos'],
        'inventario.productos'      => ['Productos',             'box-seam',           'inventario.productos'],
        'inventario.categorias'     => ['Categorías',            'tags',               'inventario.productos'],
        'inventario.stock'          => ['Stock',                 'clipboard-data',     'inventario.stock'],
        'inventario.movimientos'    => ['Movimientos',           'arrow-left-right',   'inventario.stock'],
        'inventario.ajuste'         => ['Cargar stock',          'plus-slash-minus',   'inventario.stock', false],
        'inventario.compras'        => ['Compras',               'bag',                'inventario.compras'],
        'inventario.compra_form'    => ['Nueva compra',          'bag-plus',           'inventario.compras', false],
        'inventario.proveedores'    => ['Proveedores',           'truck',              'inventario.proveedores'],
        'facturacion.timbrados'     => ['Timbrados',             'file-earmark-text',  'facturacion.timbrados', true, 'Facturación'],
        'facturacion.facturas'      => ['Facturas',              'receipt',            'facturacion.facturas', true, 'Facturación'],
        'facturacion.factura_ver'   => ['Ver comprobante',       'file-earmark-text',  'facturacion.facturas', false],
        'facturacion.emitir'        => ['Emitir factura',        'receipt-cutoff',     'facturacion.facturas', false],
        'facturacion.receptor'      => ['Datos para la factura', 'person-vcard',       'facturacion.facturas', false],
        'facturacion.cobros'        => ['Cobros',                'cash-coin',          'facturacion.cobros', true, 'Cobros'],
        'facturacion.cajas'         => ['Cajas',                 'safe',               'facturacion.caja', true, 'Caja'],
        'facturacion.caja_ver'      => ['Ver la caja',           'safe',               'facturacion.caja', false, 'Caja'],
        'facturacion.arqueo'        => ['Arqueos',               'clipboard-check',    'facturacion.caja', true, 'Caja'],
        'facturacion.movimientos'   => ['Movimiento de efectivo', 'cash-coin',      'facturacion.movimientos', true, 'Caja'],
        'facturacion.pagos'         => ['Pagos al profesional',  'wallet2',            'facturacion.pagos', true, 'Pagos'],
        'facturacion.proveedores'   => ['Pagos a proveedores',   'truck',              'facturacion.proveedores', true, 'Pagos'],
        'reportes.index'            => ['Reportes',              'bar-chart',          'reportes'],
        'reportes.imprimir'         => ['Informe para imprimir', 'printer',            'reportes', false],
        'seguridad.usuarios'        => ['Usuarios',              'person-badge',       'seguridad.usuarios'],
        'seguridad.profesionales'   => ['Profesionales',         'people',             'personal.profesionales'],
        'seguridad.profesional_form' => ['Nuevo profesional',    'person-plus',        'personal.profesionales', false],
        'seguridad.usuario_form'    => ['Nuevo usuario',         'person-plus',        'seguridad.usuarios', false],
        'seguridad.roles'           => ['Roles',                 'shield-check',       'seguridad.roles'],
        'seguridad.turnos'          => ['Turnos',                'clock',              'personal.turnos'],
        'seguridad.asistencia'      => ['Asistencia',            'calendar-check',     'personal.asistencia'],
        'seguridad.comisiones'      => ['Comisiones',            'percent',            'personal.comisiones'],
        'seguridad.sucursales'      => ['Sucursales',            'shop',               'configuracion.sucursales'],
        'seguridad.contacto'        => ['Contacto',                'headset',            'configuracion.contacto'],
        'seguridad.pagos'           => ['Datos de pago',           'bank',               'configuracion.pagos'],
        'seguridad.auditoria'       => ['Auditoría',             'journal-text',       'seguridad.auditoria'],
    ],

    // -----------------------------------------------------------------
    //  Pantallas PRESTADAS a otro módulo
    // -----------------------------------------------------------------
    //  Una pantalla pertenece al módulo de su permiso, y con eso alcanza
    //  para casi todas. El mecanismo existe para la excepción: cuando una
    //  pantalla se necesita desde dos módulos, se declara acá con el título
    //  con el que se la nombra en el otro.
    //
    //  **Hoy no lo usa nadie.** Lo usaba la ficha del equipo, que abría
    //  `seguridad.usuarios` y era además donde Personal cargaba a la gente;
    //  desde la 7.68.0 Profesionales tiene su propia pantalla y su propio
    //  permiso, así que no hay nada que prestar. El arreglo queda vacío en
    //  vez de borrarse: el mecanismo sigue siendo correcto y la próxima
    //  pantalla compartida lo va a necesitar.
    'tambien' => [],

    // -----------------------------------------------------------------
    //  Pantallas relacionadas: lo que uno suele necesitar después de esto.
    //  La idea es no tener que volver al panel para seguir trabajando.
    // -----------------------------------------------------------------
    'relaciones' => [
        'citas.agenda'            => ['citas.form', 'clientes.lista', 'facturacion.emitir', 'citas.ausencias', 'citas.reasignar'],
        'citas.form'              => ['citas.agenda', 'clientes.form', 'servicios.lista', 'seguridad.turnos'],
        'citas.atender'           => ['citas.agenda', 'inventario.stock', 'facturacion.emitir'],
        'citas.ausencias'         => ['citas.agenda', 'seguridad.turnos'],
        'citas.reasignar'         => ['citas.agenda', 'citas.ausencias', 'seguridad.usuarios'],
        'clientes.lista'          => ['clientes.form', 'citas.form', 'clientes.fidelizacion', 'clientes.valoraciones'],
        'clientes.form'           => ['clientes.lista', 'citas.form'],
        'clientes.historial'      => ['clientes.lista', 'citas.form', 'facturacion.facturas'],
        'clientes.fidelizacion'   => ['clientes.lista', 'clientes.canjes', 'servicios.descuentos', 'clientes.valoraciones'],
        'clientes.canjes'         => ['clientes.fidelizacion', 'servicios.lista', 'clientes.lista'],
        'clientes.valoraciones'   => ['clientes.lista', 'clientes.fidelizacion'],
        'servicios.lista'         => ['servicios.categorias', 'servicios.descuentos', 'citas.form'],
        'servicios.categorias'    => ['servicios.lista', 'servicios.descuentos'],
        'servicios.descuentos'    => ['servicios.lista', 'clientes.fidelizacion'],
        'inventario.productos'    => ['inventario.ajuste', 'inventario.stock', 'inventario.compra_form', 'inventario.movimientos'],
        'inventario.categorias'   => ['inventario.productos', 'inventario.stock'],
        'inventario.stock'        => ['inventario.ajuste', 'inventario.productos', 'inventario.compra_form', 'inventario.movimientos'],
        'inventario.movimientos'  => ['inventario.stock', 'inventario.ajuste', 'inventario.productos'],
        'inventario.ajuste'       => ['inventario.stock', 'inventario.productos', 'inventario.movimientos'],
        'inventario.compras'      => ['inventario.compra_form', 'inventario.proveedores', 'facturacion.proveedores', 'inventario.stock'],
        'inventario.compra_form'  => ['inventario.compras', 'inventario.proveedores', 'inventario.productos'],
        'inventario.proveedores'  => ['inventario.compras', 'facturacion.proveedores'],
        'facturacion.facturas'    => ['facturacion.emitir', 'facturacion.cobros', 'facturacion.cajas', 'facturacion.timbrados'],
        'facturacion.emitir'      => ['facturacion.facturas', 'citas.agenda', 'facturacion.timbrados'],
        'facturacion.receptor'    => ['facturacion.emitir', 'clientes.lista'],
        'facturacion.factura_ver' => ['facturacion.facturas', 'facturacion.cobros', 'facturacion.emitir'],
        'facturacion.cobros'      => ['facturacion.facturas', 'facturacion.cajas'],
        'facturacion.cajas'       => ['facturacion.cobros', 'facturacion.facturas', 'facturacion.proveedores'],
        'facturacion.pagos'       => ['seguridad.comisiones', 'facturacion.cajas'],
        'facturacion.proveedores' => ['inventario.compras', 'inventario.proveedores', 'facturacion.cajas'],
        'facturacion.timbrados'   => ['facturacion.facturas', 'facturacion.emitir', 'seguridad.sucursales'],
        'reportes.index'          => ['facturacion.facturas', 'clientes.fidelizacion', 'inventario.stock', 'citas.agenda'],
        'seguridad.usuarios'      => ['seguridad.usuario_form', 'seguridad.roles', 'seguridad.turnos', 'seguridad.auditoria'],
        'seguridad.usuario_form'  => ['seguridad.usuarios', 'seguridad.turnos', 'seguridad.sucursales', 'seguridad.roles'],
        'seguridad.roles'         => ['seguridad.usuarios', 'seguridad.auditoria', 'seguridad.sucursales'],
        'seguridad.turnos'        => ['seguridad.asistencia', 'seguridad.usuarios', 'citas.ausencias'],
        'seguridad.asistencia'    => ['seguridad.turnos', 'seguridad.usuarios'],
        'seguridad.comisiones'    => ['facturacion.pagos', 'seguridad.usuarios', 'servicios.lista'],
        'seguridad.sucursales'    => ['seguridad.usuarios', 'facturacion.timbrados'],
        'seguridad.contacto'      => ['seguridad.sucursales', 'seguridad.roles'],
        'seguridad.auditoria'     => ['seguridad.usuarios', 'seguridad.roles'],
    ],

    // -----------------------------------------------------------------
    //  Secciones del portal, para el pie cuando quien mira es una clienta
    // -----------------------------------------------------------------
    // Las secciones de la clienta. Alimentan **la barra de arriba y el pie**:
    // hasta la 7.37.1 sólo el pie, así que para pasar de «Reservar» a «Mis
    // citas» había que bajar hasta el final de la página. El personal tenía
    // tres niveles de navegación y la clienta ninguno, justo en la parte del
    // sistema que usa gente sin entrenamiento.
    //
    // «Mi cuenta» y «Mis recordatorios» quedan sólo en el pie y en el
    // desplegable de la cuenta —que es donde se los busca—: en la barra
    // competirían con lo que la clienta viene a hacer, que es reservar.
    'portal' => [
        ['ruta' => 'portal.index',        'titulo' => 'Inicio',         'ic' => 'house-door', 'barra' => true],
        ['ruta' => 'portal.reservar',     'titulo' => 'Reservar cita',  'ic' => 'calendar-plus', 'barra' => true],
        ['ruta' => 'portal.citas',        'titulo' => 'Mis citas',      'ic' => 'calendar-week', 'barra' => true],
        ['ruta' => 'portal.promociones',  'titulo' => 'Promociones',    'ic' => 'gift',          'barra' => true],
        ['ruta' => 'portal.valoraciones', 'titulo' => 'Valoraciones',   'ic' => 'star',          'barra' => true],
        ['ruta' => 'portal.preferencias', 'titulo' => 'Mis recordatorios', 'ic' => 'bell'],
        ['ruta' => 'cuenta.index',        'titulo' => 'Mi cuenta',      'ic' => 'gear'],
    ],
];
