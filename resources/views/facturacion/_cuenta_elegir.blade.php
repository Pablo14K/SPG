{{-- **De qué cuenta del salón sale la transferencia, o a cuál entra.**

     La cuenta bancaria es una caja dedicada al banco (7.121.0): lo que entra
     por transferencia se le suma y lo que sale se le resta, así que todo pago
     o cobro que no sea en efectivo tiene que decir CUÁL. Con dos cuentas —el
     banco y la billetera, o una por sucursal— sin esto no hay forma de saber
     cuál se llenó ni cuál se vació.

     **Al pagar esto AVISA, no impide.** `fn_cuenta_saldo` parte de lo
     declarado y suma lo que entró y salió desde entonces, pero un depósito
     hecho por fuera no lo ve: bloquear con un número que puede quedarse corto
     frenaría un pago legítimo. Y **sin saldo declarado no dice nada**: NULL
     es «no se sabe», no «no hay plata».

     Parámetros:
     · $cuentas   las activas del local que corresponda (`Cuenta::deSucursal`)
     · $uid       identificador único, para que dos modales no compartan el `id`
     · $compacto  sólo el `select`: para una fila de tabla, donde no entra un bloque
     · $linea     el combo de UNA línea del cobro: `name="cuenta[]"`, posicional
                  como `metodo[]`, y se muestra u oculta según el medio de esa
                  línea (lo hace `app.js`)
     · $rotulo    la pregunta, que cambia según entre o salga plata --}}
@php
    $lista = $cuentas ?? [];
    $u = $uid ?? 'x';
    $chico = ! empty($compacto);
    $enLinea = ! empty($linea);
    $pregunta = $rotulo ?? ($enLinea ? '¿A qué cuenta entra?' : '¿De qué cuenta sale?');
    $nombre = $enLinea ? 'cuenta[]' : 'id_cuenta';
@endphp

@if ($enLinea)
    {{-- **Dentro de la línea, como el detalle de la tarjeta o del banco.** El
         `select` SIGUE en el formulario aunque se esconda: el controlador toma
         cada dato por su POSICIÓN en el arreglo, y una línea sin su `cuenta[]`
         correría las de abajo. Con una sola cuenta no se pregunta pero SÍ se
         dice cuál es; sin ninguna, se avisa que el cobro no va a sumar a
         ningún lado. --}}
    <div class="col-md-5 sgp-extra-cuenta">
        @if (count($lista) > 1)
            <label class="form-label">{{ $pregunta }}</label>
            <select class="form-select form-select-sm sgp-cobro-cuenta" name="cuenta[]">
                @foreach ($lista as $ct)
                    <option value="{{ $ct->id_cuenta }}" data-tipo="{{ $ct->tipo }}">
                        {{ $ct->entidad }}@if ($ct->numero_cuenta) · {{ $ct->numero_cuenta }}@endif
                    </option>
                @endforeach
            </select>
        @elseif (count($lista) === 1)
            <input type="hidden" name="cuenta[]" value="{{ $lista[0]->id_cuenta }}">
            <div class="form-text mt-4">
                <i class="bi bi-bank"></i> Entra a <strong>{{ $lista[0]->entidad }}</strong>@if ($lista[0]->numero_cuenta) · {{ $lista[0]->numero_cuenta }}@endif
            </div>
        @else
            <input type="hidden" name="cuenta[]" value="">
            <div class="form-text mt-4 txt-no">
                <i class="bi bi-exclamation-triangle"></i> Sin cuenta bancaria cargada:
                esta plata no se va a sumar a ninguna.
                @if (\App\Servicios\Permisos::puede('facturacion.cuentas'))
                    <a href="{{ route('facturacion.cuentas') }}">Cargar una</a>
                @endif
            </div>
        @endif
    </div>
@elseif (count($lista))
    {{-- **Arranca visible y lo esconde el JS**, como el resto del sistema: con
         `app.js` caído se ve el combo y se puede elegir igual. --}}
    {{-- El bloque busca el combo de método de pago en SU MISMO formulario, no
         por un id que haya que pasarle: en la pantalla de pagos al personal hay
         una fila por profesional y todos los ids se repetirían. --}}
    <div data-cuenta-bloque class="{{ $chico ? '' : 'mb-3' }}">
        @unless ($chico)
            <label class="form-label" for="ctaSel{{ $u }}">{{ $pregunta }}</label>
        @endunless

        <select class="form-select form-select-sm" name="{{ $nombre }}" id="ctaSel{{ $u }}"
                @if ($chico) style="width:170px" aria-label="{{ $pregunta }}"
                             title="{{ $pregunta }}" @endif>
            @if (count($lista) > 1)
                <option value="">— elegí la cuenta —</option>
            @endif
            @foreach ($lista as $ct)
                <option value="{{ $ct->id_cuenta }}" data-tipo="{{ $ct->tipo }}">
                    {{ $ct->entidad }}@if ($ct->numero_cuenta) · {{ $ct->numero_cuenta }}@endif
                    @if ($ct->saldo !== null) · {{ money($ct->saldo) }} @endif
                </option>
            @endforeach
        </select>

        @unless ($chico)
            <div class="form-text">
                Lo que dice al lado es <strong>lo que el salón declaró</strong> más lo
                que entró y menos lo que salió desde entonces. Un depósito hecho por
                fuera el sistema no lo ve, así que puede haber más — nunca menos.
                {{-- El enlace sólo para quien puede abrir esa pantalla: ofrecer
                     un atajo que contesta 403 es el defecto que la 7.24.0 cerró. --}}
                @if (\App\Servicios\Permisos::puede('facturacion.cuentas'))
                    <a href="{{ route('facturacion.cuentas') }}">Actualizar el saldo</a>
                @endif
            </div>
        @endunless
    </div>
@else
    {{-- **Sin ninguna cuenta cargada se dice**, no se deja el hueco: un pago por
         transferencia sin cuenta no descuenta de ningún lado. --}}
    <div data-cuenta-bloque class="{{ $chico ? 'form-text' : 'form-text mb-3' }}">
        <i class="bi bi-bank"></i> Sin cuenta bancaria cargada
        @if (\App\Servicios\Permisos::puede('facturacion.cuentas'))
            — <a href="{{ route('facturacion.cuentas') }}">cargar una</a>
        @endif
    </div>
@endif
