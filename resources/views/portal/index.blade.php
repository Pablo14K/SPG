@extends('layout.app')

@section('titulo', 'Mi portal')

{{-- **La misma forma que el panel del personal** (pedido del usuario,
     7.118.1): el saludo como título chico arriba a la izquierda, a la
     izquierda sus próximas citas y debajo su nivel y sus puntos —que es lo
     que a ella le importa mirar, como al salón le importa la caja—, y a la
     derecha las pantallas del portal en pastillas. Antes era UNA cita en una
     tarjeta y cinco tarjetas apiladas debajo, que en el celular eran tres
     pantallas de scroll. Las clases son las del panel: escrito dos veces se
     desfasa. --}}

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

    <h1 class="sgp-saludo">Hola, {{ session('nombre') }}<x-ayuda lado="bottom">Desde acá reservás una cita, mirás las que tenés y nos contás cómo te fue.</x-ayuda></h1>

    <div class="row g-3">
        {{-- IZQUIERDA: sus citas, y debajo su nivel --}}
        <div class="col-lg-7 col-xl-8 d-flex flex-column gap-3">
            <div class="panel-box flex-grow-1 d-flex flex-column">
                <h2 class="panel-box-titulo"><i class="bi bi-calendar-check"></i> Tus próximas citas</h2>
                <div class="panel-box-inner flex-grow-1">
                    @if ($proximas)
                        <ul class="list-unstyled mb-0 sgp-lista-citas">
                            @foreach ($proximas as $c)
                                <li id="citaProxima{{ (int) $c->id_cita }}" class="flex-wrap">
                                    <span class="pe-2" style="min-width:0">
                                        <strong>{{ fecha($c->fecha_hora) }}</strong>
                                        {{-- **El estado va acá, y no es un adorno.** Sin él la
                                             lista anunciaba «próxima» igual a una cita normal y
                                             a una que se pasó de hora, así que el inicio y «Mis
                                             citas» parecían decir cosas distintas de la misma
                                             cita. Sale del MISMO estado que la lista. --}}
                                        {!! estado_badge($c->estado_nombre ?? '') !!}
                                        <span class="text-muted-warm d-block" style="font-size:.84rem">
                                            {{ $c->servicios ?: 'Sin servicios cargados' }}
                                            · con {{ $c->profesional }}
                                            · {{ (int) $c->duracion_min }} min
                                        </span>
                                        @if (($c->estado_nombre ?? '') === 'Atrasada')
                                            {{-- Atrasada quiere decir que la hora pasó y nadie la
                                                 tocó todavía. La clienta necesita ver eso —es la
                                                 que va a reclamar—, pero anunciada a secas como
                                                 «próxima» parece que todo está bien. --}}
                                            <span class="txt-no d-block" style="font-size:.84rem">
                                                Te esperábamos a esta hora. Si ya no vas a poder venir, avisanos.
                                            </span>
                                        @endif
                                    </span>
                                    @if ($enCurso && $proxima && (int) $c->id_cita === (int) $proxima->id_cita)
                                        <a class="btn btn-sm btn-oro" href="{{ route('portal.atencion', ['id' => $c->id_cita]) }}">
                                            <i class="bi bi-eye"></i> Ver cómo va</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="text-end mt-2">
                            <a class="link-oro" style="font-size:.78rem" href="{{ route('portal.citas') }}">ver todas mis citas &rarr;</a>
                        </div>
                    @else
                        <div class="sgp-vacio py-4">
                            <i class="bi bi-calendar-check"></i>
                            <div class="t">No tenés ninguna cita reservada.</div>
                        </div>
                        {{-- Fuera de `.sgp-vacio`: ahí todo ícono se dibuja grande y
                             en bloque, y el del botón salía como un cartel. --}}
                        <div class="text-center pb-3">
                            <a class="btn btn-oro" href="{{ route('portal.reservar') }}">
                                <i class="bi bi-calendar-plus"></i> Reservar una cita</a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- **Su nivel y sus puntos**, que es lo que la clienta mira como
                 el salón mira la caja: cuánto le descuenta el salón por venir y
                 cuánto le falta para el siguiente escalón. --}}
            <div class="panel-box">
                <h2 class="panel-box-titulo"><i class="bi bi-gift"></i> Tu nivel y tus puntos</h2>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-12 col-xl-6">
                        <div class="metric-card h-100">
                            <div class="metric-lbl">Tu nivel</div>
                            <div class="metric-value">{{ $nivel->nombre ?? 'Bronce' }}</div>
                            <div class="metric-comparado">
                                @if (($nivel->valor ?? 0) > 0)
                                    <span class="txt-ok">
                                        {{ ($nivel->tipo ?? '') === 'PORCENTAJE' ? rtrim(rtrim(number_format((float) $nivel->valor, 2, ',', '.'), '0'), ',') . ' % de descuento' : money($nivel->valor) . ' de descuento' }}
                                    </span>
                                @else
                                    <span class="text-muted-warm">sin descuento todavía</span>
                                @endif
                                <span class="text-muted-warm"> · {{ $visitas }} {{ $visitas === 1 ? 'visita' : 'visitas' }}</span>
                                @if ($siguiente)
                                    @php $sgpFaltan = (int) $siguiente->visitas_minimas - $visitas; @endphp
                                    <span class="text-muted-warm d-block">
                                        {{ $sgpFaltan === 1 ? 'Te falta 1 visita' : 'Te faltan ' . $sgpFaltan . ' visitas' }} para {{ $siguiente->nombre }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-12 col-xl-6">
                        <div class="metric-card h-100">
                            <div class="metric-lbl">Tus puntos</div>
                            <div class="metric-value">{{ number_format($puntos, 0, ',', '.') }}</div>
                            <div class="metric-comparado">
                                <a class="link-oro" href="{{ route('portal.promociones') }}">qué podés canjear &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DERECHA: las pantallas del portal, del mismo catálogo que la barra
             y el pie. Inicio queda afuera —es ésta— y Mi cuenta también, que
             vive en el desplegable de la cuenta. --}}
        <div class="col-lg-5 col-xl-4">
            <div class="panel-box h-100 d-flex flex-column">
                <div class="sgp-modulos sgp-modulos-2">
                    @foreach (config('navegacion.portal') as $sgpP)
                        @continue (in_array($sgpP['ruta'], ['portal.index', 'cuenta.index'], true))
                        @php $sgpUrl = Navegacion::url($sgpP['ruta']); @endphp
                        @if ($sgpUrl)
                            <a href="{{ $sgpUrl }}" class="sgp-modulo-pill">
                                <i class="bi bi-{{ $sgpP['ic'] }}"></i>
                                <span class="text-truncate w-100 px-1">{{ $sgpP['titulo'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
