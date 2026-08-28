@extends('layout.app')

@section('titulo', 'Mis citas')

@section('contenido')
    <div class="spg-page-head spg-head-flex">
        <div class="spg-head-txt">
            <h1>Mis citas</h1>
            <div class="sub">Las que vienen y las que ya pasaron.</div>
        </div>
        <div class="spg-head-acciones">
            <a class="btn btn-oro" href="{{ route('portal.reservar') }}">
                <i class="bi bi-calendar-plus"></i> Reservar</a>
        </div>
    </div>

    <div class="spg-panel mb-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-event"></i> Próximas</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Servicios</th><th>Profesional</th><th>Estado</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @forelse ($prox as $c)
                        <tr>
                            <td><strong>{{ fecha($c->fecha_hora) }}</strong></td>
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            <td>{{ $c->profesional }}</td>
                            <td>
                                {!! estado_badge($c->estado) !!}
                                @if ($c->en_curso)<span class="badge-estado e-proc">en curso</span>@endif
                                @if ((float) $c->sena > 0)
                                    <span class="badge-estado e-ok" title="Ya recibida en el salón">
                                        seña {{ money($c->sena) }}</span>
                                @elseif ((float) $c->sena_pedida > 0)
                                    <span class="badge-estado e-warn" title="Falta confirmarla en el salón">
                                        seña {{ money($c->sena_pedida) }} a confirmar</span>
                                @elseif ((float) ($c->sena_requerida ?? 0) > 0)
                                    {{-- **Sin la seña la cita no está confirmada**, y el
                                         badge lo dice con esas palabras: «falta seña» a
                                         secas se lee como un detalle administrativo, no
                                         como que el lugar se puede perder. --}}
                                    <span class="badge-estado e-no"
                                          title="Te guardamos el horario, pero se suelta si no confirmamos la seña">
                                        sin confirmar · falta seña {{ money($c->sena_requerida) }}</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                {{-- **Se puede seguir hasta que el pago esté cerrado.**
                                     Antes el botón sólo estaba con la cita «En proceso», así
                                     que la clienta veía el detalle mientras la atendían y lo
                                     perdía justo cuando quería revisar el comprobante. --}}
                                @if (in_array($c->estado, ['En proceso', 'Atendida'], true))
                                    <a class="btn btn-sm btn-oro" href="{{ route('portal.atencion', ['id' => $c->id_cita]) }}">
                                        <i class="bi bi-eye"></i> Ver</a>
                                @elseif (! in_array($c->estado, ['Atendida', 'Cancelada'], true))
                                    {{-- **Los dos calendarios, en un solo desplegable.**
                                         Estaban como dos botones y el segundo era una «G»
                                         suelta: no se leía como un calendario, así que
                                         quien no usa Google entendía que no había opción
                                         para su teléfono — y quien sí lo usa no sabía que
                                         esa letra abría el suyo. Ahora el botón dice
                                         «Calendario» y adentro cada opción se nombra.

                                         **Es `<details>`, el desplegable del propio
                                         navegador, y no uno de Bootstrap**: así funciona
                                         con `app.js` caído. Agendar la cita en el teléfono
                                         no puede depender de que cargue una librería. --}}
                                    <details class="spg-desple d-inline-block">
                                        <summary class="btn btn-sm btn-rapido">
                                            <i class="bi bi-calendar-plus"></i> Calendario</summary>
                                        <div class="spg-desple-menu">
                                            <a download href="{{ route('cita.calendario', ['id' => $c->id_cita]) }}">
                                                <i class="bi bi-phone"></i> Calendario del celular</a>
                                            <a target="_blank" rel="noopener"
                                               href="{{ \App\Servicios\Calendario::urlGoogle($c, $lugar) }}">
                                                <i class="bi bi-google"></i> Calendario de Google</a>
                                        </div>
                                    </details>
                                    {{-- Registrar la seña NO es pagarla: no hay pasarela de pago
                                         y no la va a haber. Es un aviso, y el salón lo confirma
                                         cuando recibe el dinero. Por eso el botón dice
                                         «Registrar» y el modal lo aclara. --}}
                                    @php
                                        // **Lo canjeado ya está pagado con puntos.** Si el canje
                                        // cubre toda la cita no queda nada que adelantar, y
                                        // ofrecerle una seña es pedirle plata dos veces. Si además
                                        // pidió algo sin canje, eso sí se puede señar — por eso es
                                        // una resta y no un «tiene canje: no muestres nada».
                                        $porPagar = (float) ($c->total_cita ?? 0) - (float) ($c->canjeado ?? 0);
                                    @endphp
                                    @if ((float) $c->sena <= 0 && (float) $c->sena_pedida <= 0 && $porPagar > 0)
                                        <button type="button" class="btn btn-sm btn-outline-neutro"
                                                data-bs-toggle="modal" data-bs-target="#modalSena{{ $c->id_cita }}">
                                            <i class="bi bi-cash-coin"></i> Seña</button>
                                    @endif

                                    {{-- **Cambiar de día no es lo mismo que no venir.**
                                         Antes sólo se podía cancelar, así que quien no
                                         podía el martes tenía que cancelar y volver a
                                         reservar — perdiendo el lugar y la seña.

                                         **Pero una sola vez.** El estado «Reprogramada»
                                         (2) es la marca: lo pone `sp_reprogramar_cita`
                                         desde siempre. Sin el tope, la reserva se empuja
                                         hacia adelante indefinidamente y el hueco queda
                                         tomado sin que nadie lo use.

                                         Se dice por qué en vez de esconder el botón sin
                                         más: un botón que desaparece se lee como un
                                         error del sistema. --}}
                                    @if ((int) $c->id_estado_cita === 2)
                                        <span class="badge-estado e-warn"
                                              title="Ya usaste tu cambio de día. Si necesitás otro, escribinos.">
                                            <i class="bi bi-calendar-check"></i> Ya cambiada</span>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-neutro"
                                                data-bs-toggle="modal" data-bs-target="#modalRepro{{ $c->id_cita }}">
                                            <i class="bi bi-calendar-event"></i> Cambiar de día</button>
                                    @endif

                                    <form method="post" action="{{ route('portal.cancelar') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <button class="btn btn-sm btn-outline-neutro"
                                                data-confirmar="¿Cancelar tu cita del {{ fecha($c->fecha_hora) }}?">
                                            Cancelar</button>
                                    </form>

                                    @if ((int) $c->id_estado_cita !== 2)
                                    <div class="modal fade" id="modalRepro{{ $c->id_cita }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <form method="post" action="{{ route('portal.reprogramar') }}" class="modal-content">
                                                @csrf
                                                <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" style="font-size:1rem">
                                                        <i class="bi bi-calendar-event"></i> Cambiar el día de tu cita</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="text-muted-warm" style="font-size:.86rem">
                                                        Ahora la tenés para el <strong>{{ fecha($c->fecha_hora) }}</strong>
                                                        con <strong>{{ $c->profesional }}</strong>.
                                                        Seguís con la misma profesional y con lo que ya señaste.
                                                    </p>

                                                    <div class="alert alert-warning py-2 px-3" style="font-size:.84rem">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                        <strong>Es el único cambio que podés hacer desde acá.</strong>
                                                        Después de esto, si necesitás moverla otra vez tenés que
                                                        escribirnos.
                                                    </div>

                                                    <label class="form-label" for="rp{{ $c->id_cita }}">Nueva fecha y hora</label>
                                                    <input type="datetime-local" class="form-control"
                                                           id="rp{{ $c->id_cita }}" name="fecha_hora" required
                                                           min="{{ date('Y-m-d\TH:i') }}">
                                                    <div class="form-text mb-2">
                                                        Si ese horario no está libre te lo decimos y elegís otro.
                                                    </div>

                                                    {{-- El motivo no es burocracia: es lo que le deja al
                                                         salón ver POR QUÉ se mueven las citas. Si siempre
                                                         es el mismo horario, el problema es el horario. --}}
                                                    <label class="form-label" for="mo{{ $c->id_cita }}">¿Por qué lo cambiás?</label>
                                                    <input type="text" class="form-control" id="mo{{ $c->id_cita }}"
                                                           name="motivo" required maxlength="200"
                                                           placeholder="Me salió un viaje, no llego a esa hora…">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-neutro"
                                                            data-bs-dismiss="modal">Dejarlo como está</button>
                                                    <button class="btn btn-oro">
                                                        <i class="bi bi-check2"></i> Cambiar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-calendar-week"></i>
                                    <div class="t">No tenés citas próximas.</div>
                                    <div class="d">Reservá una con el botón de arriba.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($pasadas)
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Anteriores</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Servicios</th><th>Profesional</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($pasadas as $c)
                            <tr>
                                <td>{{ fecha($c->fecha_hora) }}</td>
                                <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                                <td>{{ $c->profesional }}</td>
                                <td>{!! estado_badge($c->estado) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    {{-- Un modal por cita próxima sin seña. --}}
    @foreach ($prox as $c)
        @continue (in_array($c->estado, ['Atendida', 'Cancelada', 'En proceso'], true)
                   || (float) $c->sena > 0 || (float) $c->sena_pedida > 0)
        <div class="modal fade" id="modalSena{{ $c->id_cita }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('portal.sena') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-cash-coin"></i> Dejar una seña</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.85rem">
                                Para tu cita del <strong>{{ fecha($c->fecha_hora) }}</strong>.
                            </p>

                            {{-- **Cuánta seña pide el salón, y por qué.** Antes acá
                                 no decía nada: la clienta escribía el monto que
                                 quisiera y el salón se lo confirmaba de palabra. El
                                 número sale de `servicio.sena_porcentaje` y lo
                                 calcula la base — quien fija cuánto es el salón. --}}
                            @if ((float) ($c->sena_requerida ?? 0) > 0)
                                <div class="alert alert-warning py-2" style="font-size:.85rem">
                                    Alguno de los servicios que elegiste se reserva con seña.
                                    Para esta cita son <strong>{{ money($c->sena_requerida) }}</strong>.
                                </div>
                            @endif

                            {{-- **A dónde transferir.** Es lo único que faltaba
                                 para que la clienta pueda pagar sin llamar a
                                 nadie: el sistema no cobra —no hay pasarela y no
                                 la va a haber— pero sí puede decir a qué cuenta.

                                 Son las del local DONDE RESERVÓ: dos sucursales
                                 pueden cobrar en cuentas distintas. --}}
                            @php $ctas = $cuentas[(int) $c->id_sucursal] ?? []; @endphp
                            @if ($ctas)
                                <div class="spg-cuentas mt-3">
                                    <div class="spg-cuentas-tit">
                                        <i class="bi bi-bank"></i> Podés transferir a:
                                    </div>
                                    @foreach ($ctas as $ct)
                                        <div class="spg-cuenta">
                                            <div class="spg-cuenta-cab">
                                                <strong>{{ $ct->entidad }}</strong>
                                                <span class="text-muted-warm">· {{ $ct->medio }}</span>
                                            </div>
                                            @if ($ct->alias)
                                                @php
                                                    $comoBuscar = [
                                                        'CI' => 'cédula', 'RUC' => 'RUC',
                                                        'CELULAR' => 'celular', 'EMAIL' => 'correo',
                                                    ][$ct->alias_tipo] ?? 'alias';
                                                @endphp
                                                <div class="spg-cuenta-nro">{{ $ct->alias }}</div>
                                                <div class="spg-cuenta-pie">
                                                    buscalo por <strong>{{ $comoBuscar }}</strong>
                                                    · o por número: {{ $ct->numero_cuenta }}</div>
                                            @else
                                                <div class="spg-cuenta-nro">{{ $ct->numero_cuenta }}</div>
                                            @endif
                                            <div class="spg-cuenta-pie">
                                                {{ $ct->titular }}@if ($ct->documento) · {{ $ct->documento }}@endif
                                                @if ($ct->tipo_cuenta) · {{ $ct->tipo_cuenta }}@endif
                                            </div>
                                            @if ($ct->observacion)
                                                <div class="spg-cuenta-obs">{{ $ct->observacion }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- **Sin cuentas cargadas se dice, no se calla.** Un
                                     bloque que desaparece deja a la clienta sin saber
                                     si tenía que transferir a algún lado. --}}
                                <div class="alert alert-warning mt-3 py-2" style="font-size:.84rem">
                                    Todavía no tenemos publicados los datos para transferir.
                                    Escribinos y te los pasamos.
                                </div>
                            @endif

                            {{-- **De dónde sale ese número.** Un total suelto no se
                                 puede comprobar: con tres servicios marcados, la
                                 clienta no sabe si la seña es de uno o de todos, ni
                                 qué porcentaje se le aplicó. El desglose es el mismo
                                 bloque que ve quien confirma el pago en el mostrador,
                                 así que los dos discuten sobre los mismos números. --}}
                            @if (! empty($desgloses[$c->id_cita]['filas']))
                                <div class="mt-3">
                                    <div class="form-label">Cómo se calcula</div>
                                    @include('facturacion._sena_desglose', [
                                        'desglose' => $desgloses[$c->id_cita],
                                        'yaPuesta' => (float) ($c->sena ?? 0),
                                    ])
                                </div>
                            @endif

                            <label class="form-label mt-3" for="ps{{ $c->id_cita }}">¿Cuánto vas a dejar?</label>
                            <input class="form-control input-miles" id="ps{{ $c->id_cita }}"
                                   name="monto" inputmode="numeric" required
                                   data-min="{{ (float) ($c->sena_requerida ?? 0) > 0 ? (int) $c->sena_requerida : 1 }}"
                                   value="{{ (float) ($c->sena_requerida ?? 0) > 0 ? monto_input($c->sena_requerida) : '' }}">
                            @if ((float) ($c->sena_requerida ?? 0) > 0)
                                {{-- **Menos de lo que se pide no reserva nada**, y hay
                                     que decirlo antes: con Gs. 10.000 sobre una seña de
                                     210.000 la cita queda igual de sin confirmar, pero
                                     con un aviso que alguien tiene que ir a rechazar. El
                                     servidor lo vuelve a comprobar. --}}
                                <div class="form-text">
                                    El mínimo es {{ money($c->sena_requerida) }}: con menos,
                                    el horario no queda confirmado.
                                </div>
                            @endif

                            {{-- **El comprobante de la transferencia.** La cita se
                                 reserva desde afuera del local, así que no hay nada
                                 físico que entregar y el salón no tiene cómo saber que
                                 la plata salió. Es opcional: si la clienta pasa por el
                                 local y deja el efectivo, el comprobante lo da el salón. --}}
                            <label class="form-label mt-3" for="pc{{ $c->id_cita }}">
                                Comprobante de la transferencia
                                <span class="text-muted-warm">(si ya la hiciste)</span>
                            </label>
                            <input class="form-control" id="pc{{ $c->id_cita }}" type="file"
                                   name="comprobante" accept="image/*,application/pdf">
                            <div class="form-text">
                                Una foto de la pantalla alcanza. Hasta 3 MB.
                            </div>

                            {{-- Que quede clarísimo que acá no se paga: la clienta
                                 no tiene que quedarse esperando un cobro que no
                                 va a llegar, ni creer que ya está saldado. --}}
                            <div class="alert alert-warning mt-3 mb-0" style="font-size:.82rem">
                                <strong>Acá no se paga.</strong> Lo anotamos para que el salón lo
                                sepa, y queda confirmado cuando entregues el dinero en el local.
                                Se descuenta solo del total cuando te facturen.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Registrar la seña</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
