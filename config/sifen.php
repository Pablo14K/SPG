<?php

/**
 * Facturación electrónica — Automatizador SIFEN.
 *
 * El SPG **no habla con la DNIT**: le pasa el comprobante ya numerado al
 * Automatizador SIFEN, que es un proyecto aparte, y éste se encarga de firmar,
 * enviar y devolver el CDC. Acá sólo vive cómo llegar hasta él.
 *
 * Ver la sección «Facturación electrónica» de CLAUDE.md.
 */
return [

    /*
     * Con `false` el módulo entero desaparece de la interfaz: ni botón de
     * enviar ni columnas de estado. Es lo que se entrega, porque un salón que
     * no factura electrónicamente no tiene por qué ver nada de esto.
     */
    'activo' => env('SIFEN_ACTIVO', false),

    /*
     * Cómo se manda el comprobante:
     *
     *   simulado  no sale de acá. Arma el TXT de verdad y devuelve un CDC de
     *             prueba, para ver el circuito completo sin depender del
     *             servicio. Los comprobantes quedan marcados como simulados.
     *   http      se manda al Automatizador de verdad, a `url`.
     */
    'modo' => env('SIFEN_MODO', 'simulado'),

    'url' => env('SIFEN_URL', ''),
    'token' => env('SIFEN_TOKEN', ''),

    /*
     * Firmar y esperar a la DNIT lleva tiempo; el propio automatizador
     * recomienda no bajar de 60 s. Si se corta antes, el comprobante puede
     * haberse emitido igual: por eso un fallo de red deja el envío en
     * PENDIENTE y NUNCA se reintenta solo.
     */
    'timeout' => (int) env('SIFEN_TIMEOUT', 60),

    /*
     * Qué tipos de comprobante del SPG se mandan. La DNIT recibe facturas y
     * notas de crédito; el Ticket es un comprobante interno del salón y no
     * sale de acá — que es justamente lo que se emite cuando la clienta no
     * pide factura.
     */
    'tipos_electronicos' => [1, 5],

    /*
     * El comprobante que se propone al cobrar: **Factura (1)**.
     *
     * Hasta la 7.8.0 era el Ticket (3), con la idea de que la mayoría de las
     * clientas no pide factura. El salón decidió que no usa esos comprobantes
     * —se dieron de baja Boleta de venta, Ticket, Autofactura, Nota de débito
     * y Nota de remisión—, así que el que queda para vender es la Factura.
     *
     * La lista de la pantalla sale de `tipo_comprobante.activo`, así que para
     * volver a habilitar alguno se lo reactiva ahí y vuelve a aparecer: no hay
     * nada escrito en el código que dependa de estos números.
     */
    'tipo_por_defecto' => (int) env('SIFEN_TIPO_DEFECTO', 1),
];
