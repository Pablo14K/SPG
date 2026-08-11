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
        'seguridad' => 'Seguridad',
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
        // Seguridad junta lo que antes eran Personal y Configuración: quién es
        // quién en el salón, qué puede hacer cada uno y qué quedó registrado.
        'seguridad' => [
            'seguridad.usuarios' => 'Usuarios',
            'seguridad.roles' => 'Roles',
            'seguridad.turnos' => 'Turnos',
            'seguridad.asistencia' => 'Asistencia',
            'seguridad.comisiones' => 'Comisiones',
            'seguridad.sucursales' => 'Sucursales',
            'seguridad.contacto' => 'Contacto y soporte',
            'seguridad.auditoria' => 'Auditoría',
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
        'personal' => [
            'seguridad.usuarios', 'seguridad.turnos', 'seguridad.comisiones', 'seguridad.asistencia',
        ],
        'configuracion' => [
            'seguridad.sucursales', 'seguridad.roles', 'seguridad.contacto', 'seguridad.auditoria',
        ],
        'personal.usuarios' => ['seguridad.usuarios'],
        'personal.turnos' => ['seguridad.turnos'],
        'personal.comisiones' => ['seguridad.comisiones'],
        'personal.asistencia' => ['seguridad.asistencia'],
        'configuracion.sucursales' => ['seguridad.sucursales'],
        'configuracion.roles' => ['seguridad.roles'],
        'configuracion.contacto' => ['seguridad.contacto'],
        'configuracion.auditoria' => ['seguridad.auditoria'],
    ],

    // El rol 1 es superadministrador y el 4 es el cliente del portal. El código
    // los referencia por id, así que están protegidos contra el borrado.
    // Para todo lo demás se usa `rol.es_personal`, nunca una lista fija de ids:
    // así los roles creados desde Configuración funcionan sin tocar código.
    'rol_admin' => 1,
    'rol_cliente' => 4,
];
