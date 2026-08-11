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
        'citas' => 'Citas y agenda',
        'clientes' => 'Clientes',
        'servicios' => 'Servicios',
        'inventario' => 'Inventario',
        'facturacion' => 'Facturación y caja',
        'reportes' => 'Reportes',
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
            'clientes.valoraciones' => 'Valoraciones',
        ],
        'servicios' => [
            'servicios.catalogo' => 'Catálogo',
            'servicios.categorias' => 'Categorías',
            'servicios.descuentos' => 'Descuentos',
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
            'facturacion.pagos' => 'Pagos al personal',
            'facturacion.proveedores' => 'Pagos a proveedores',
            'facturacion.timbrados' => 'Timbrados',
        ],
        'personal' => [
            'personal.usuarios' => 'Usuarios',
            'personal.turnos' => 'Turnos',
            'personal.comisiones' => 'Comisiones',
            'personal.asistencia' => 'Asistencia',
        ],
        'configuracion' => [
            'configuracion.sucursales' => 'Sucursales',
            'configuracion.roles' => 'Roles',
            'configuracion.contacto' => 'Contacto y soporte',
            'configuracion.auditoria' => 'Auditoría',
        ],
    ],

    // El rol 1 es superadministrador y el 4 es el cliente del portal. El código
    // los referencia por id, así que están protegidos contra el borrado.
    // Para todo lo demás se usa `rol.es_personal`, nunca una lista fija de ids:
    // así los roles creados desde Configuración funcionan sin tocar código.
    'rol_admin' => 1,
    'rol_cliente' => 4,
];
