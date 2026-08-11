{{-- Correo del código de seguridad.

     Los estilos van en línea a propósito: los clientes de correo descartan las
     hojas de estilo y buena parte del CSS moderno. La paleta es la misma del
     sistema, para que el correo se reconozca como del salón. --}}
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#F7F5F2;font-family:Segoe UI,Arial,sans-serif;color:#2C2C2A">
    <div style="max-width:520px;margin:24px auto;background:#FFFFFF;border-radius:12px;overflow:hidden;
                border:1px solid #E0DDD8">

        <div style="background:#0D0D0D;padding:18px 24px">
            <span style="color:#C9A84C;font-size:1.05rem;font-weight:bold">{{ config('app.name') }}</span>
        </div>

        <div style="padding:24px">
            <h1 style="font-size:18px;font-weight:500;margin:0 0 14px">{{ $asunto }}</h1>

            @if ($nombre)
                <p style="margin:0 0 10px">Hola {{ $nombre }},</p>
            @endif

            <p style="margin:0 0 6px">{{ $intro }}</p>

            <p style="font-size:30px;font-weight:bold;letter-spacing:6px;color:#8A6C1E;
                      text-align:center;margin:18px 0">{{ $codigo }}</p>

            <p style="color:#888;font-size:13px;margin:0">
                El código vence en {{ $minutos }} minutos y se puede usar una sola vez.
                Si no fuiste vos, ignorá este correo: no hace falta que hagas nada.
            </p>
        </div>

        <div style="background:#F7F5F2;padding:12px 24px;color:#555;font-size:11px;text-align:center">
            {{ config('app.name') }} · Luque, Paraguay
        </div>
    </div>
</body>
</html>
