{{-- El comprobante, por correo.

     Los estilos van en línea a propósito: los clientes de correo descartan las
     hojas de estilo y buena parte del CSS moderno. La paleta es la misma del
     sistema, para que el correo se reconozca como del salón.

     Va el detalle escrito, no un adjunto: este proyecto no tiene librería de
     PDF —lo que se imprime lo maqueta el navegador— y un adjunto que hay que
     abrir con otra aplicación es peor que algo que se lee de una en el
     teléfono, que es donde la clienta lo va a mirar. --}}
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#F7F5F2;font-family:Segoe UI,Arial,sans-serif;color:#2C2C2A">
    <div style="max-width:560px;margin:24px auto;background:#FFFFFF;border-radius:12px;overflow:hidden;
                border:1px solid #E0DDD8">

        <div style="background:#0D0D0D;padding:18px 24px">
            <span style="color:#C9A84C;font-size:1.05rem;font-weight:bold">{{ $salon }}</span>
        </div>

        <div style="padding:24px">
            <h1 style="font-size:18px;font-weight:500;margin:0 0 4px">
                {{ $f->tipo_comprobante }} {{ $f->nro_comprobante }}
            </h1>
            <p style="margin:0 0 18px;color:#888;font-size:13px">
                {{ fecha($f->fecha_emision) }}
                @if ($f->cliente) · {{ $f->cliente }} @endif
            </p>

            @if ($nota)
                <p style="margin:0 0 16px">{{ $nota }}</p>
            @endif

            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:6px 0;border-bottom:1px solid #E0DDD8;
                                   color:#555;font-weight:500">Detalle</th>
                        <th style="text-align:right;padding:6px 0;border-bottom:1px solid #E0DDD8;
                                   color:#555;font-weight:500">Cant.</th>
                        <th style="text-align:right;padding:6px 0;border-bottom:1px solid #E0DDD8;
                                   color:#555;font-weight:500">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $it)
                        <tr>
                            <td style="padding:6px 0;border-bottom:1px solid #F2F0EC">{{ $it->item }}</td>
                            <td style="padding:6px 0;border-bottom:1px solid #F2F0EC;text-align:right">
                                {{ cant($it->cantidad) }}</td>
                            <td style="padding:6px 0;border-bottom:1px solid #F2F0EC;text-align:right">
                                {{ money($it->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:12px">
                {{-- Los nombres son los de `vw_factura_resumen`: `descuento_total`
                     y `total_neto`, no `descuento` ni `total`. --}}
                @if ((float) $f->descuento_total > 0)
                    <tr>
                        <td style="padding:3px 0;color:#555">Subtotal</td>
                        <td style="padding:3px 0;text-align:right">{{ money($f->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0;color:#555">Descuento</td>
                        <td style="padding:3px 0;text-align:right">− {{ money($f->descuento_total) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:6px 0;font-weight:bold;font-size:16px">Total</td>
                    <td style="padding:6px 0;text-align:right;font-weight:bold;font-size:16px;color:#8A6C1E">
                        {{ money($f->total_neto) }}</td>
                </tr>
                @if ((float) $f->saldo > 0.01)
                    <tr>
                        <td style="padding:3px 0;color:#993535">Saldo pendiente</td>
                        <td style="padding:3px 0;text-align:right;color:#993535">{{ money($f->saldo) }}</td>
                    </tr>
                @endif
            </table>

            @if ($cobros)
                <p style="margin:18px 0 6px;font-weight:500;font-size:14px">Cómo se pagó</p>
                <table style="width:100%;border-collapse:collapse;font-size:13px;color:#555">
                    @foreach ($cobros as $c)
                        <tr>
                            <td style="padding:3px 0">{{ $c->metodo }} · {{ fecha($c->fecha) }}</td>
                            <td style="padding:3px 0;text-align:right">{{ money($c->monto) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            {{-- Acá había un cartel anunciando los adjuntos. Se sacó por dos
                 motivos. Uno: **el cliente de correo ya los muestra**, con su
                 nombre, su ícono y hasta la vista previa del PDF — decirlo de
                 nuevo no agrega nada. Dos: estaba mal escrito y se veía.

                 `@if` PEGADO A UNA PALABRA NO LO COMPILA BLADE. Su patrón lleva
                 `\B` delante de la arroba, así que `PDF@if (…)` no es una
                 directiva: sale tal cual, con paréntesis y todo, en el correo
                 que le llega a la clienta. Hace falta un espacio o un salto de
                 línea antes de la arroba. --}}
            <p style="color:#888;font-size:12px;margin:22px 0 0;border-top:1px solid #E0DDD8;padding-top:14px">
                Gracias por tu visita. Si algo de este comprobante no coincide, escribinos y lo revisamos.
            </p>
        </div>
    </div>
</body>
</html>
