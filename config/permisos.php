<?php

declare(strict_types=1);

/**
 * Ningún módulo es todo o nada: son 28 permisos, no 8.
 *
 * Quien registra la atención no tiene por qué agendar; quien cobra no tiene
 * por qué anular una liquidación al personal; el Profesional ficha su
 * asistencia sin ver las cuentas de sus compañeras.
 *
 * La clave del submódulo es `modulo.submodulo` y se guarda en `rol_modulo`,
 * una fila por permiso: sigue siendo un valor atómico por fila, así que la
 * primera forma normal se mantiene.
 *
 * De acá salen solos la matriz de Configuración → Roles, las claves que acepta
 * el POST y las etiquetas de los mensajes de «sin permiso». **Lo único que NO
 * sale solo son los guardias**: cada pantalla tiene que pedir su clave.
 */
return [

    'modulos' => [
        'citas' => 'Citas',
        'clientes' => 'Clientes',
        'servicios' => 'Servicios',
        'inventario' => 'Inventario',
        'facturacion' => 'Tesorería',
        'reportes' => 'Reportes',
        // Seguridad se partió en tres por pedido del usuario. Cada uno
        // contesta una pregunta distinta: **quién entra y qué puede hacer**
        // (Seguridad), **quién trabaja y cuándo** (Personal) y **cómo está
        // armado el salón** (Configuración). Juntas obligaban a buscar los
        // turnos en el mismo lugar que la auditoría.
        'seguridad' => 'Seguridad',
        'personal' => 'Personal',
        'configuracion' => 'Configuración',
    ],

    // Reportes no figura acá: es una sola pantalla y no se divide.
    'submodulos' => [
        'citas' => [
            'citas.agenda' => 'Agenda y citas',
            'citas.atencion' => 'Registrar atención',
            'citas.ausencias' => 'Excepciones',
        ],
        'clientes' => [
            'clientes.registro' => 'Registro',
            'clientes.fidelizacion' => 'Fidelización',
            // **Canjes es su propio permiso y NO viene con Fidelización.**
            // Ver los puntos de una clienta y decidir por cuántos regala el
            // salón un servicio son dos cosas distintas: lo segundo es fijar
            // precio, la misma razón por la que el Profesional no tiene
            // `servicios.descuentos` desde la 6.4.0.
            'clientes.canjes' => 'Canjes por puntos',
            'clientes.valoraciones' => 'Valoraciones',
        ],
        'servicios' => [
            'servicios.catalogo' => 'Catálogo',
            'servicios.categorias' => 'Categorías',
            'servicios.descuentos' => 'Promociones',
        ],
        'inventario' => [
            'inventario.productos' => 'Productos',
            'inventario.stock' => 'Stock y movimientos',
            'inventario.compras' => 'Compras',
            'inventario.proveedores' => 'Proveedores',
        ],
        'facturacion' => [
            'facturacion.facturas' => 'Facturas',
            'facturacion.cobros' => 'Cobros',
            'facturacion.caja' => 'Caja',
            // **El movimiento de efectivo es su propia clave.** Abrir y cerrar
            // el cajón es administrar el arqueo; meter o sacar plata a mano es
            // mover dinero **sin un documento detrás** —no hay cobro ni pago que
            // lo respalde, sólo un concepto escrito— así que es la parte que un
            // salón puede querer dar por separado. Es el mismo criterio que
            // separó Timbrados de Facturación en la 5.2.0.
            'facturacion.movimientos' => 'Movimiento de efectivo',
            'facturacion.pagos' => 'Pagos al personal',
            'facturacion.proveedores' => 'Pagos a proveedores',
            'facturacion.timbrados' => 'Timbrados',
        ],
        // Quién entra y qué puede hacer. La creación de cuentas sigue siendo
        // del Administrador por middleware, sin importar la matriz.
        'seguridad' => [
            'seguridad.usuarios' => 'Usuarios',
            'seguridad.roles' => 'Roles',
            'seguridad.auditoria' => 'Auditoría',
        ],
        // Quién trabaja y cuándo. Es lo que el mostrador administra todos los
        // días, y no tiene por qué venir junto con los roles ni la auditoría.
        'personal' => [
            'personal.profesionales' => 'Profesionales',
            'personal.turnos' => 'Turnos',
            'personal.asistencia' => 'Asistencia',
            'personal.comisiones' => 'Comisiones',
        ],
        // Cómo está armado el salón: los locales y por dónde lo contactan.
        'configuracion' => [
            'configuracion.sucursales' => 'Sucursales',
            'configuracion.contacto' => 'Contacto',
            'configuracion.pagos' => 'Datos de pago',
        ],
    ],

    /**
     * Claves viejas → claves nuevas, para los roles guardados antes de que
     * Personal y Configuración se unieran en Seguridad.
     *
     * `Permisos::leer()` las traduce al vuelo, así una base ya instalada no
     * pierde permisos en silencio al actualizar el sistema. Al guardar la
     * matriz de Roles quedan escritas con el nombre nuevo, y el día que ninguna
     * base tenga claves viejas este arreglo se puede vaciar.
     *
     * OJO: el módulo padre viejo NO se traduce a `seguridad` a secas, sino a la
     * lista de los submódulos que ese módulo tenía. Traducirlo al padre nuevo
     * le regalaría a quien administraba el personal los roles, la auditoría y
     * las sucursales, que nunca tuvo.
     */
    'equivalencias' => [
        // --- De la 7.57.0: Seguridad se partió en tres ---------------------
        //
        // Lo guardado como `seguridad.turnos` tiene que seguir dando turnos.
        // Sin esto el rol no da error: **pierde la pantalla en silencio**, que
        // es la peor forma de romperlo — el Asistente administrativo se
        // quedaba sin turnos ni asistencia al actualizar.
        'seguridad.turnos' => ['personal.turnos'],
        'seguridad.asistencia' => ['personal.asistencia'],
        'seguridad.comisiones' => ['personal.comisiones'],
        'seguridad.sucursales' => ['configuracion.sucursales'],
        'seguridad.contacto' => ['configuracion.contacto'],

        // --- De antes de la 6.2.0: Personal y Configuración eran módulos ---
        //
        // El módulo padre viejo se traduce a SUS submódulos, nunca al padre
        // nuevo: traducir `personal` a `personal` a secas le regalaría hoy
        // los roles y la auditoría a quien sólo administraba al personal.
        'personal' => [
            'seguridad.usuarios', 'personal.turnos', 'personal.comisiones', 'personal.asistencia',
        ],
        'personal.usuarios' => ['seguridad.usuarios'],
        'configuracion.roles' => ['seguridad.roles'],
        'configuracion.auditoria' => ['seguridad.auditoria'],
    ],

    // El rol 1 es superadministrador y el 4 es el cliente del portal. El código
    // los referencia por id, así que están protegidos contra el borrado.
    // Para todo lo demás se usa `rol.es_personal`, nunca una lista fija de ids:
    // así los roles creados desde Configuración funcionan sin tocar código.
    'rol_admin' => 1,
    'rol_cliente' => 4,
];
