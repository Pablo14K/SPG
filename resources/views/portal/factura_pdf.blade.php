{{--
    El comprobante en papel, para Dompdf.

    Los colores van escritos y no como `var(--oro)`: Dompdf no lee `app.css` ni
    resuelve variables CSS, así que una hoja de estilos compartida saldría en
    negro sobre blanco. Son los mismos valores de la identidad — el oro de la
    regla bajo el encabezado y el rojo semántico del sello de acreditada.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante {{ $f->nro }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1A1A1A; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .tenue { color: #555555; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { text-align: left; border-bottom: 2px solid #C9A84C; padding: 5px 4px; font-size: 10px; }
        td { padding: 5px 4px; border-bottom: 1px solid #E0DDD8; }
        .der { text-align: right; }
        .tot { font-size: 14px; font-weight: bold; }
        .sello { border: 2px solid #993535; color: #993535; padding: 6px 10px;
                 font-weight: bold; margin: 0 0 10px; }
    </style>
</head>
<body>
    @include('portal._factura_cuerpo', ['papel' => true])

    <p class="tenue" style="margin-top:24px">
        Comprobante emitido por {{ $salon }}. Documento de respaldo del cobro.
    </p>
</body>
</html>
