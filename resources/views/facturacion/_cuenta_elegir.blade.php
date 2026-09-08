{{-- **De qué cuenta del salón sale la transferencia.**

     El efectivo tiene su control desde la 5.5.0 —no se puede sacar del cajón
     más de lo que hay— y el banco no tenía ninguno: el propio código lo decía
     al lado del `if`, «los pagos por banco no se frenan: no salen del cajón,
     salen de la cuenta», y de la cuenta no se sabía nada. Se podía liquidar el
     mes entero contra una cuenta vacía y enterarse cuando el banco rechazara
     la transferencia.

     **Esto AVISA, no impide.** `fn_cuenta_saldo` es un piso —el sistema conoce
     lo que sale, no lo que entra— así que bloquear con un número incompleto
     frenaría un pago legítimo. Y **sin saldo declarado no dice nada**: NULL es
     «no se sabe», no «no hay plata».

     Parámetros:
     · $cuentas   las activas del local del que sale la plata (`Cuenta::deSucursal`)
     · $uid       identificador único, para que dos modales no compartan el `id`
     · $compacto  sólo el `select`: para una fila de tabla, donde no entra un bloque --}}
@php
    $lista = $cuentas ?? [];
    $u = $uid ?? 'x';
    $chico = ! empty($compacto);
@endphp

@if (count($lista))
    {{-- **Arranca visible y lo esconde el JS**, como el resto del sistema: con
         `app.js` caído se ve el combo y se puede elegir igual. --}}
    {{-- El bloque busca el combo de método de pago en SU MISMO formulario, no
         por un id que haya que pasarle: en la pantalla de pagos al personal hay
         una fila por profesional y todos los ids se repetirían. --}}
    <div data-cuenta-bloque class="{{ $chico ? '' : 'mb-3' }}">
        @unless ($chico)
            <label class="form-label" for="ctaSel{{ $u }}">¿De qué cuenta sale?</label>
        @endunless

        <select class="form-select form-select-sm" name="id_dato_pago" id="ctaSel{{ $u }}"
                @if ($chico) style="width:170px" aria-label="¿De qué cuenta sale?"
                             title="¿De qué cuenta sale la transferencia?" @endif>
            <option value="">— no anotar la cuenta —</option>
            @foreach ($lista as $ct)
                <option value="{{ $ct->id_dato_pago }}">
                    {{ $ct->entidad }}@if ($ct->numero_cuenta) · {{ $ct->numero_cuenta }}@endif
                    @if ($ct->saldo !== null) · {{ money($ct->saldo) }} @endif
                </option>
            @endforeach
        </select>

        @unless ($chico)
            <div class="form-text">
                Lo que dice al lado es <strong>lo último que el salón declaró</strong>
                menos lo que se pagó desde entonces. El sistema no ve lo que entra
                al banco, así que puede haber más — nunca menos.
                {{-- El enlace sólo para quien puede abrir esa pantalla: ofrecer
                     un atajo que contesta 403 es el defecto que la 7.24.0 cerró. --}}
                @if (\App\Servicios\Permisos::puede('configuracion.pagos'))
                    <a href="{{ route('seguridad.pagos') }}">Actualizar el saldo</a>
                @endif
            </div>
        @endunless
    </div>
@endif
