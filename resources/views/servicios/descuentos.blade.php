@extends('layout.app')

@section('titulo', 'Promociones')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp
    <x-encabezado
        sub="Promociones del salón. Se aplica <strong>una sola</strong> por factura: la que más le convenga al cliente entre su nivel de fidelización y la mejor promoción vigente, nunca las dos sumadas."
        :accion="['ruta' => 'servicios.descuento_form', 't' => 'Nuevo descuento', 'ic' => 'plus-lg']" />

    {{-- **Los niveles, primero: son la regla que se aplica sola.**

         Se mudaron desde Clientes → Fidelización, donde convivían con el
         listado de quién tiene cuántos puntos. Van arriba porque el resto de
         la pantalla —el valor del punto y las promociones— son las otras dos
         formas de descontar, y las tres juntas son «cuánto le devuelve el
         salón al cliente».

         **Desde cuántas visitas y con qué descuento SÍ se administran acá**:
         eran dos números que sólo se podían cambiar con un `UPDATE` a mano, o
         sea imposibles para el salón. Lo que sigue sin tocarse es el nombre
         —lo nombran los comprobantes ya emitidos y el portal— y quién está en
         cada nivel, que lo calcula `fn_cliente_nivel` por cantidad de
         visitas. --}}
    @if ($niveles)
        <div class="sgp-panel mb-3">
            <h2 class="sgp-form-titulo mb-2"><i class="bi bi-award"></i> Niveles de fidelización<x-ayuda>El nivel se calcula solo por cantidad de visitas. Acá se decide desde cuántas empieza cada uno y qué descuento le corresponde; el nombre no se cambia porque lo nombran los comprobantes ya emitidos.</x-ayuda></h2>
            <div class="sgp-niveles">
                @foreach ($niveles as $n)
                    {{-- **El nivel dado de baja se sigue viendo, apagado.** Si
                         desapareciera de la lista, el botón que lo apaga sería
                         indistinguible de uno que lo borra y no habría desde
                         dónde volver a encenderlo — es el defecto que la 7.62.1
                         corrigió con «Disponible acá». --}}
                    <div class="sgp-nivel{{ $n->activo ? '' : ' sgp-nivel-baja' }}">
                        <div class="sgp-nivel-nombre">{{ $n->nombre }}</div>
                        <div class="sgp-nivel-req">
                            @if ($n->activo)
                                desde {{ (int) $n->visitas_minimas }} visita{{ (int) $n->visitas_minimas === 1 ? '' : 's' }}
                            @else
                                <span class="badge-estado e-muted">Dado de baja</span>
                            @endif
                        </div>
                        <div class="sgp-nivel-desc">{{ $n->descuento ?: 'sin descuento' }}</div>
                        <div class="sgp-nivel-clientes">
                            @if ($n->activo)
                                {{ (int) $n->clientes }} cliente{{ (int) $n->clientes === 1 ? '' : 's' }}
                            @else
                                no se aplica
                            @endif
                        </div>
                        <div class="d-flex gap-1 justify-content-center mt-2">
                            <button type="button" class="btn btn-sm btn-outline-neutro"
                                    data-bs-toggle="modal" data-bs-target="#nivel{{ $n->id_nivel }}">
                                <i class="bi bi-pencil"></i> Cambiar</button>
                            <form method="post" action="{{ route('servicios.nivel.baja') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="id_nivel" value="{{ $n->id_nivel }}">
                                <button class="btn btn-sm btn-outline-neutro"
                                        title="{{ $n->activo ? 'Dar de baja' : 'Volver a aplicar' }}"
                                        data-confirmar="{{ $n->activo
                                            ? '¿Dar de baja el nivel ' . $n->nombre . '? Las ' . (int) $n->clientes . ' clienta(s) que hoy están ahí pasan al nivel de abajo, así que su descuento cambia. Lo ya facturado no se toca.'
                                            : '¿Volver a aplicar el nivel ' . $n->nombre . '?' }}">
                                    <i class="bi bi-toggle-{{ $n->activo ? 'on' : 'off' }}"></i></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-muted-warm mb-0 mt-2" style="font-size:.76rem">
                El nivel sube solo con las visitas, no se asigna a mano. Quién está
                en cuál se mira en <a class="link-oro" href="{{ route('clientes.fidelizacion') }}">Clientes → Visitas y puntos</a>.
            </p>
        </div>

        {{-- **Un modal por nivel.** Se cambia desde cuántas visitas empieza y
             qué descuento le toca: son los dos números que hacen al programa, y
             hasta acá sólo se podían tocar con un UPDATE a mano.

             El nombre no está en el formulario a propósito — es UNIQUE y lo
             nombran los comprobantes ya emitidos y el portal («por su nivel
             Oro»): renombrarlo dejaría esos textos hablando de un nivel que no
             existe. --}}
        @foreach ($niveles as $n)
            <div class="modal fade" id="nivel{{ $n->id_nivel }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="post" action="{{ route('servicios.nivel.guardar') }}">
                            @csrf
                            <input type="hidden" name="id_nivel" value="{{ $n->id_nivel }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    <i class="bi bi-award"></i> Nivel {{ $n->nombre }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label" for="nv{{ $n->id_nivel }}">Desde cuántas visitas</label>
                                <input class="form-control mb-1" id="nv{{ $n->id_nivel }}"
                                       name="visitas_minimas" data-solo="numeros" inputmode="numeric"
                                       value="{{ (int) $n->visitas_minimas }}" required>
                                <div class="form-text mb-3">
                                    La clienta entra a este nivel al llegar a esa cantidad de visitas.
                                    Dos niveles no pueden arrancar en el mismo número.
                                </div>

                                <label class="form-label" for="nd{{ $n->id_nivel }}">Descuento del nivel</label>
                                <select class="form-select mb-1" id="nd{{ $n->id_nivel }}" name="id_descuento">
                                    <option value="">Sin descuento</option>
                                    @foreach ($descuentosNivel as $d)
                                        <option value="{{ $d->id_descuento }}"
                                                @selected((int) $n->id_descuento === (int) $d->id_descuento)>
                                            {{ $d->nombre }} ·
                                            {{ $d->tipo === 'PORCENTAJE' ? rtrim(rtrim(number_format((float) $d->valor, 2, ',', '.'), '0'), ',') . ' %' : money($d->valor) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Se aplica solo al facturar, y compite con las promociones:
                                    la clienta se lleva el mejor de los dos, nunca los dos sumados.
                                </div>

                                <p class="text-muted-warm mt-3 mb-0" style="font-size:.78rem">
                                    Ahora mismo hay <strong>{{ (int) $n->clientes }}</strong> clienta(s) en este nivel.
                                    Cambiar el corte las mueve de nivel, pero <strong>no toca lo ya
                                    facturado</strong>: el descuento de un comprobante emitido queda como está.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif


    {{-- Cuánto vale un punto. Va acá y no en un archivo de configuración porque
         contesta la misma pregunta que los descuentos —cuánto le devuelve el
         salón al cliente por comprar acá— y porque lo decide el salón, no quien
         programa: antes cambiarlo era editar código y volver a desplegar. --}}
    <div class="sgp-panel mb-3">
        <form method="post" action="{{ route('servicios.puntos.guardar') }}"
              class="d-flex align-items-end gap-2 flex-wrap">
            @csrf
            <div>
                <label class="form-label mb-1" for="puntos_cada_gs">
                    <i class="bi bi-award txt-oro"></i> Fidelización
                </label>
                <div class="d-flex align-items-center gap-2">
                    <span>1 punto por cada</span>
                    <div class="input-group" style="width:190px">
                        <span class="input-group-text">{{ config('sgp.moneda') }}</span>
                        <input class="form-control input-miles" id="puntos_cada_gs" name="puntos_cada_gs"
                               data-min="100" data-max="10000000"
                               value="{{ monto_input($puntosCadaGs) }}" required>
                    </div>
                    <span>facturados</span>
                    <button class="btn btn-oro">Guardar</button>
                </div>
            </div>
        </form>
        <p class="text-muted-warm mb-0 mt-2" style="font-size:.8rem">
            Hoy, una factura de {{ money($puntosCadaGs * 32) }} le deja
            <strong>32 puntos</strong> al cliente. Los puntos que las clientas ya tienen
            <strong>no cambian</strong>: esto vale de acá en adelante.
            @if (Permisos::puede('clientes.canjes'))
                Lo que se puede canjear con ellos se carga en
                <a class="link-oro" href="{{ route('clientes.canjes') }}">Clientes → Canjes por puntos</a>.
            @endif
        </p>
    </div>

    <div class="sgp-panel">
        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Promoción</th><th class="text-end">Valor</th>
                        <th>Estado</th><th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $d)
                        @php
                            $vigente = (! $d->fecha_inicio || $d->fecha_inicio <= date('Y-m-d'))
                                    && (! $d->fecha_fin || $d->fecha_fin >= date('Y-m-d'));
                        @endphp
                        <tr>
                            <td class="sgp-movil-titulo" data-label="Promoción">
                                {{ $d->nombre }}
                                @if ($d->descripcion)
                                    <div class="text-muted-warm" style="font-size:.76rem">{{ $d->descripcion }}</div>
                                @endif
                            </td>
                            <td class="text-end" data-label="Valor">
                                {{ $d->tipo === 'PORCENTAJE' ? cant($d->valor) . ' %' : money($d->valor) }}
                            </td>
                            <td data-label="Estado">
                                @if (! $d->activo)
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @elseif ($vigente)
                                    <span class="badge-estado e-ok">Vigente</span>
                                @else
                                    <span class="badge-estado e-warn">Fuera de fecha</span>
                                @endif
                            </td>
                            <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                                <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                        data-bs-target="#detDesc{{ $d->id_descuento }}" aria-expanded="false">
                                    <i class="bi bi-chevron-down"></i> Detalle
                                </button>
                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('servicios.descuento_form', $d->id_descuento) }}">
                                    <i class="bi bi-pencil"></i></a>
                                <form method="post" action="{{ route('servicios.descuento.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_descuento" value="{{ $d->id_descuento }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $d->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $d->activo ? 'Desactivar' : 'Activar' }} «{{ $d->nombre }}»?">
                                        <i class="bi bi-toggle-{{ $d->activo ? 'on' : 'off' }}"></i></button>
                                </form>
                            </td>
                        </tr>
                        <tr class="sgp-fila-detalle">
                            <td colspan="4">
                                <div class="collapse" id="detDesc{{ $d->id_descuento }}">
                                    <div class="sgp-det-cuerpo">
                                        <div class="sgp-det-grid">
                                            <div>
                                                <dt>Vigencia</dt>
                                                <dd>
                                                    @if ($d->fecha_inicio || $d->fecha_fin)
                                                        {{ $d->fecha_inicio ? fecha($d->fecha_inicio, 'd/m/Y') : 'siempre' }}
                                                        –
                                                        {{ $d->fecha_fin ? fecha($d->fecha_fin, 'd/m/Y') : 'sin fin' }}
                                                    @else
                                                        Sin límite de fechas
                                                    @endif
                                                </dd>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="sgp-vacio">
                                    <i class="bi bi-percent"></i>
                                    <div class="t">Todavía no hay promociones cargados.</div>
                                    <div class="d">Los de los niveles de fidelización se crean solos con el sistema.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
