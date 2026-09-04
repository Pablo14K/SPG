<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Qué se carga en cada campo
|--------------------------------------------------------------------------
|
| El texto que muestra `<x-ayuda campo="…">` al lado del rótulo de un campo.
| **Está acá y no escrito en cada vista** por el motivo de siempre: los mismos
| campos aparecen en varias pantallas —`nombre` en nueve, `email` en cuatro— y
| copiados terminan diciendo cosas distintas del mismo dato.
|
| Dos reglas al escribir uno:
|
|   · **Con ejemplo cuando el formato importa.** «Cédula» no necesita
|     explicación, pero sí necesita que se sepa que va sin puntos: el ejemplo
|     ahorra el rechazo del servidor.
|   · **Decir la CONSECUENCIA, no repetir el rótulo.** «Nombre: el nombre» no
|     ayuda a nadie; lo que ayuda es «así lo va a ver la clienta en el
|     comprobante».
|
| La clave es el `for` de la etiqueta. Los prefijos de los formularios rápidos
| —`cr_`, `pr_`, `pv_`, `tr_`, `sr_`, `rn_`, `cf_`, `prov`— los resuelve
| `Ayuda::de()`, así que no hace falta repetirlos acá.
|
*/

return [

    // ---- Personas: nombre y contacto ---------------------------------
    'nombre' => 'Así se va a mostrar en todo el sistema y en el comprobante. Ej.: Ana.',
    'apellido' => 'Se usa junto al nombre para identificarla en la agenda. Ej.: Villalba.',
    'cedula' => 'Sólo números, sin puntos ni guiones. Ej.: 4200000.',
    'ruc' => 'Con el guion y el dígito verificador. Ej.: 80012345-0. Si está mal, la DNIT rechaza la factura.',
    'documento' => 'El número del documento elegido arriba. La cédula va sin puntos; el RUC, con su dígito verificador.',
    'tipo_doc' => 'Cédula para una persona, RUC para una empresa. Consumidor final no lleva datos, y no se admite desde Gs. 60.000.000.',
    'telefono' => 'Con el 0 adelante, como se marca acá. Ej.: 0981123456.',
    'email' => 'Ahí llegan el comprobante y los recordatorios de cita. Ej.: ana@gmail.com.',
    'direccion' => 'Calle y número. Sale impresa en el comprobante. Ej.: Avda. Aquino 1250.',
    'ciudad' => 'Si no está en la lista, elegí «Otra» y escribila. Ej.: Luque.',
    'fecha_nacimiento' => 'Opcional. Sirve para saludarla en su cumpleaños.',
    'contacto' => 'Con quién se habla en ese proveedor. Ej.: Carlos, de ventas.',
    'titular' => 'El nombre tal como figura en el banco: es el que la clienta va a ver al transferir.',

    // ---- Cuentas y seguridad -----------------------------------------
    'username' => 'Con esto entra al sistema. Sin espacios ni acentos. Ej.: ana.villalba.',
    'usuario' => 'Podés entrar con el nombre de usuario o con tu correo.',
    'password' => 'Al menos 8 caracteres. Mezclá letras y números.',
    'password2' => 'La misma de arriba, para descartar un error de tipeo.',
    'actual' => 'La que usás hoy. Sin ella no se puede cambiar, ni siquiera con la sesión abierta.',
    'nueva' => 'Al menos 8 caracteres. Después te mandamos un código al correo para confirmar.',
    'nueva2' => 'La misma de arriba, para descartar un error de tipeo.',
    'codigo' => 'El que te llegó por correo. Vence a los 30 minutos.',
    'id_rol' => 'Decide qué pantallas ve y qué puede tocar. Se ajusta fino en Seguridad → Roles.',
    'id_persona' => 'La persona ya cargada a la que pertenece esta cuenta. Si no está, cargala primero en Profesionales.',
    'mail_clave' => 'La contraseña de aplicación de Gmail, no la de la cuenta. Se genera en myaccount.google.com/apppasswords.',

    // ---- Dinero -------------------------------------------------------
    'precio' => 'Lo que se le cobra a la clienta, con IVA incluido. Ej.: 75.000.',
    'precio_costo' => 'Lo que te cuesta a vos, sin ganancia. Sirve para saber cuánto deja cada servicio.',
    'precio_venta' => 'Lo que se le cobraría a la clienta si se vendiera suelto.',
    'precio_unitario' => 'Lo que cuesta UNA unidad, no el total del renglón.',
    'monto' => 'En guaraníes, sin decimales. Ej.: 150.000.',
    'monto_inicial' => 'El efectivo con el que arranca el cajón. Es contra esto que se compara el arqueo al cerrar.',
    'monto_contado' => 'El efectivo que hay AHORA en el cajón, contado a mano. El sistema lo compara con lo que debería haber.',
    'tasa_iva' => 'En Paraguay casi todo va al 10 %. El IVA está incluido en el precio, no se suma aparte.',
    'referencia' => 'El número que devuelve el banco, o el del voucher de la tarjeta. Sirve para reclamar si algo no aparece.',
    'medio' => 'Con qué paga. El efectivo es lo único que se cuenta en el arqueo del cajón.',
    'id_condicion_venta' => 'Contado si paga ahora; crédito si queda debiendo, con sus vencimientos.',
    'cantCuotas' => 'En cuántas veces se paga. El sistema reparte el total, y lo que no divide exacto va en la última.',

    // ---- Fiscal --------------------------------------------------------
    'nro_timbrado' => 'Los 8 dígitos que da la DNIT. Ej.: 16005678.',
    'establecimiento' => 'Los 3 primeros dígitos del comprobante: dicen de qué local salió. Ej.: 001.',
    'punto_expedicion' => 'Los 3 dígitos del medio: la caja o el puesto que emite. Ej.: 001.',
    'nro_desde' => 'El primer número del rango que autorizó la DNIT. Ej.: 1.',
    'nro_hasta' => 'El último del rango. Cuando se llega, hay que cargar un timbrado nuevo.',
    'vigente_desde' => 'Desde cuándo se puede usar. Antes de esa fecha la DNIT lo rechaza.',
    'id_tipo_comprobante' => 'Factura para lo que se declara. Cada tipo lleva su propio timbrado y su propia numeración.',
    'nro_factura_proveedor' => 'El número del comprobante que dio el proveedor. Se puede cargar después, cuando llegue el papel.',
    'actividad_desc' => 'A qué se dedica el salón, como figura en el RUC. Sale impresa en la factura electrónica.',

    // ---- Inventario ----------------------------------------------------
    'unidad_medida' => 'Cómo lo comprás: frasco, caja, unidad. Ej.: frasco de 1 L.',
    'contenido' => 'Cuánto trae cada envase. Con esto, quien atiende anota «30 ml» y el sistema descuenta la parte que corresponde.',
    'stock_nuevo' => 'La cantidad REAL que contaste. El sistema calcula solo la diferencia contra lo que tenía anotado.',
    'cantidad' => 'Cuánto entra o sale. Los productos fraccionados van en su unidad de consumo, no en envases.',
    'id_producto' => 'Si todavía no está cargado, se crea al confirmar la compra.',
    'id_proveedor' => 'A quién se le compró. Sirve para ver la deuda y el historial con cada uno.',

    // ---- Servicios y agenda --------------------------------------------
    'descripcion' => 'Una línea que la clienta lee al elegir. Ej.: corte con lavado y peinado.',
    'imagen' => 'La foto del resultado. La clienta elige mirándola: «mechas» es una palabra, la foto es lo que va a recibir.',
    'id_zona' => 'Dos servicios de la misma parte se hacen uno después del otro; de partes distintas, a la vez.',
    'id_categoria_servicio' => 'Agrupa los servicios para que la clienta los encuentre. Ej.: Color.',
    'id_categoria' => 'Agrupa los productos en las listas y en los informes.',
    'fecha_hora' => 'Elegí el día y después la hora. Sólo se ofrecen las que están libres de verdad.',
    'personas' => 'Cuántas van a venir, contándote. Sirve para preparar el lugar.',
    'nombre_para' => 'Si la cita es para otra persona, su nombre. Quien atiende va a saber a quién esperar.',
    'observaciones' => 'Lo que quien atiende tiene que saber antes: alergias, un color que no le gustó, lo que sea.',
    'observacion' => 'Lo que la clienta va a leer en el aviso. Se le manda tal cual.',
    'pedido' => 'Lo que se te ocurrió agregar. Quien te atiende confirma precio y tiempo en el momento.',
    'motivo' => 'Queda registrado, y es lo único que explica esta decisión dentro de tres meses. Al menos 10 caracteres.',
    'id_tipo_ausencia' => 'Vacaciones, licencia o un feriado del salón. Mientras dure, esa persona no aparece en la agenda.',

    // ---- Turnos y asistencia -------------------------------------------
    'hora_inicio' => 'A qué hora empieza el turno. Ej.: 08:00.',
    'hora_fin' => 'A qué hora termina. Entre un turno y otro tienen que quedar al menos 60 minutos.',
    'tr_flex' => 'Cuántos minutos de tolerancia hay para marcar la entrada. Pasado eso, entra como falta.',

    // ---- Promociones y puntos ------------------------------------------
    'valor' => 'Si es porcentaje va el número sin el signo (10 = 10 %); si es monto fijo, en guaraníes.',
    'puntos' => 'Cuántos puntos cuesta. A razón de 1 punto cada Gs. 10.000, 400 son unos Gs. 4.000.000 de consumo.',
    'dias_vigencia' => 'Cuántos días tiene la clienta para usarlo desde que lo canjea. Ej.: 30.',
    'fecha_inicio' => 'Desde cuándo se aplica. Antes de esa fecha el sistema la ignora.',
    'fecha_fin' => 'Hasta cuándo. Una promoción que nace vencida no se puede guardar.',

    // ---- Datos de pago -------------------------------------------------
    'alias' => 'Con esto sola alcanza para transferirte: reemplaza al número de cuenta y al banco.',
    'alias_tipo' => 'Por dónde lo va a buscar la clienta en su banco: cédula, RUC, celular o correo.',
    'entidad' => 'El banco o la billetera donde está la cuenta. Ej.: Ueno, Tigo Money.',
    'tipo_cuenta' => 'Cuenta corriente o caja de ahorro, como figura en el banco.',
    'numero_cuenta' => 'Tal como aparece en el banco. Si cargaste el alias, con eso ya alcanza.',
];
