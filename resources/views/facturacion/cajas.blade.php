@extends('layout.app')

@section('titulo', 'Cajas')

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **Filtros arriba, tarjetas, paginación.** Cada cajón es una tarjeta y
     **cada tarjeta trae sus propios movimientos**, que es lo que hace que la
     pantalla conteste sola: con dos cajones abiertos en el mismo local, leer el
     arqueo de uno con los movimientos del otro es peor que no verlos.

     Antes era una tabla y el botón mandaba al listado general de Movimientos —
     o sea que había que volver a filtrar por la caja en la que ya se estaba
     parado. Los del día se ven en un modal, acá mismo; la historia entera sigue
     estando en Movimientos, con sus filtros y su paginación.

     La forma no cambia con el tamaño del salón: con 3 cajones o con 300 lo
     único que crece son las tarjetas, y la paginación las corta. --}}
<x-encabezado sub="Los cajones del salón, cuál está abierto y qué pasó hoy con cada uno." />

@if ($puedeCrear)
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modalCajaNueva">
            <i class="bi bi-plus-circle"></i> Nueva caja
        </button>
    </div>
@endif

<x-filtros :f="$f" />

<div class="row g-3">
    @forelse ($rows as $c)
        @php
            $delDia = $movs[(int) $c->id_caja_fisica] ?? [];
            $entro = 0;
            $salio = 0;
            foreach ($delDia as $m) {
                if (! $m->activo) {
                    continue;
                }
                if ((int) $m->signo > 0) {
                    $entro += (float) $m->monto;
                } else {
                    $salio += (float) $m->monto;
                }
            }
        @endphp
        <div class="col-12 col-md-6 col-xl-4">
            <div class="spg-panel h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <h2 class="spg-form-titulo mb-0"><i class="bi bi-safe"></i> {{ $c->nombre }}</h2>
                        @if (count($sucursales) > 1)
                            <div class="text-muted-warm" style="font-size:.82rem">{{ $c->sucursal }}</div>
                        @endif
                    </div>
                    @if ($c->id_caja)
                        <span class="badge-estado e-ok"><i class="bi bi-unlock"></i> Abierta</span>
                    @else
                        <span class="badge-estado e-muted"><i class="bi bi-lock"></i> Cerrada</span>
                    @endif
                </div>

                @if ($c->id_caja)
                    {{-- **El efectivo esperado es lo que tiene que estar en ESTE
                         cajón**, no en el salón: es contra lo que se cuenta al
                         cerrar. --}}
                    <div class="mt-3">
                        <div class="text-muted-warm" style="font-size:.8rem">Efectivo esperado</div>
                        <div class="val oro" style="font-size:1.35rem">{{ money($c->saldo) }}</div>
                    </div>
                    {{-- **Una fecha suelta no dice de qué es.** «26/08 09:15» al
                         lado de un nombre se puede leer como el último movimiento,
                         el cierre previsto o cualquier otra cosa: es la apertura, y
                         el rótulo tiene que decirlo. --}}
                    <div class="text-muted-warm mt-2" style="font-size:.82rem">
                        <i class="bi bi-person"></i> Abierta por {{ $c->responsable ?: '—' }}
                    </div>
                    <div class="text-muted-warm" style="font-size:.82rem">
                        <i class="bi bi-clock"></i> Abierta el
                        {{ fecha($c->fecha_apertura, 'd/m/Y') }} a las
                        {{ fecha($c->fecha_apertura, 'H:i') }}
                    </div>

                    @if (Permisos::puede('facturacion.movimientos'))
                        <div class="text-muted-warm mt-2" style="font-size:.82rem">
                            @if ($delDia)
                                <i class="bi bi-list-ul"></i> {{ count($delDia) }} movimiento{{ count($delDia) === 1 ? '' : 's' }} hoy
                                · <span class="txt-ok">+ {{ money($entro) }}</span>
                                · <span class="txt-no">− {{ money($salio) }}</span>
                            @else
                                <i class="bi bi-list-ul"></i> Sin movimientos hoy
                            @endif
                        </div>
                    @endif
                @else
                    <div class="text-muted-warm mt-3" style="font-size:.85rem">
                        Sin caja abierta. Mientras esté cerrada no se puede cobrar desde este cajón.
                    </div>
                @endif

                <div class="d-flex gap-2 flex-wrap mt-3 pt-3 border-top">
                    @if ($c->id_caja && Permisos::puede('facturacion.movimientos'))
                        <button type="button" class="btn btn-sm btn-outline-neutro"
                                data-bs-toggle="modal" data-bs-target="#modalMovs{{ $c->id_caja_fisica }}">
                            <i class="bi bi-list-ul"></i> Movimientos de hoy</button>
                    @endif
                    <a class="btn btn-sm {{ $c->id_caja ? 'btn-outline-neutro' : 'btn-oro' }}"
                       href="{{ route('facturacion.caja_ver', $c->id_caja_fisica) }}">
                        {{ $c->id_caja ? 'Arqueo y cierre' : 'Abrir caja' }}</a>

                    {{-- **Renombrar y borrar son del Administrador.**

                         El nombre se cargaba una vez y quedaba para siempre: un
                         «Caja 2» mal tipeado no tenía arreglo. Renombrar no toca
                         ninguna historia — el arqueo cuelga del id, no del nombre.

                         **Borrar sólo el que nunca se abrió.** El que ya operó
                         tiene arqueos, cobros y egresos colgando de sus sesiones:
                         para ése está la baja, que lo saca de la lista sin romper
                         nada. --}}
                    @if (Permisos::esAdmin())
                        <button type="button" class="btn btn-sm btn-outline-neutro ms-auto"
                                data-bs-toggle="modal" data-bs-target="#modalCajaEd{{ $c->id_caja_fisica }}"
                                title="Cambiar el nombre"><i class="bi bi-pencil"></i></button>

                        @if ((int) ($c->sesiones ?? 0) === 0)
                            <form method="post" action="{{ route('facturacion.caja_fisica.borrar') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="id_caja_fisica" value="{{ $c->id_caja_fisica }}">
                                <button class="btn btn-sm btn-outline-neutro" title="Borrar esta caja"
                                        data-confirmar="«{{ $c->nombre }}» nunca se abrió, así que se puede borrar del todo. ¿Seguimos?">
                                    <i class="bi bi-trash"></i></button>
                            </form>
                        @else
                            <form method="post" action="{{ route('facturacion.caja_fisica.baja') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="id_caja_fisica" value="{{ $c->id_caja_fisica }}">
                                <button class="btn btn-sm btn-outline-neutro"
                                        title="Ya se usó {{ (int) $c->sesiones }} vez/veces: se da de baja, no se borra"
                                        data-confirmar="«{{ $c->nombre }}» ya se usó, así que se da de baja y su historial queda. ¿Seguimos?">
                                    <i class="bi bi-x-lg"></i></button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        @if (Permisos::esAdmin())
            <div class="modal fade" id="modalCajaEd{{ $c->id_caja_fisica }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="post" action="{{ route('facturacion.caja_fisica.guardar') }}">
                            @csrf
                            <input type="hidden" name="id_caja_fisica" value="{{ $c->id_caja_fisica }}">
                            <div class="modal-header">
                                <h2 class="modal-title fs-5"><i class="bi bi-pencil"></i> Renombrar la caja</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label" for="nombreCaja{{ $c->id_caja_fisica }}">Nombre</label>
                                <input class="form-control" id="nombreCaja{{ $c->id_caja_fisica }}"
                                       name="nombre" required maxlength="60" value="{{ $c->nombre }}">
                                <div class="form-text">
                                    El local no se cambia: moverlo reescribiría de dónde salió
                                    la plata de todas sus aperturas anteriores.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if ($c->id_caja && Permisos::puede('facturacion.movimientos'))
            <div class="modal fade" id="modalMovs{{ $c->id_caja_fisica }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5">
                                <i class="bi bi-list-ul"></i> {{ $c->nombre }} · movimientos de hoy</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            @include('facturacion._movs_dia', ['movs' => $delDia, 'cajon' => $c->nombre])
                        </div>
                        <div class="modal-footer justify-content-between">
                            {{-- La historia completa sigue estando donde se puede
                                 filtrar por fecha, medio y clase. --}}
                            <a class="btn btn-sm btn-outline-neutro"
                               href="{{ route('facturacion.movimientos', ['caja' => $c->id_caja_fisica]) }}">
                                <i class="bi bi-clock-history"></i> Ver todos los movimientos</a>
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @empty
        <div class="col-12">
            <div class="spg-panel">
                <div class="spg-vacio">
                    <i class="bi bi-safe"></i>
                    <div class="t">No hay cajas cargadas</div>
                    <div class="d">
                        @if ($puedeCrear)
                            Creá una con el botón «Nueva caja».
                        @else
                            Pedile a un Administrador que cargue una: sin caja no se cobra.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforelse
</div>

<x-paginacion :pag="$pag" :f="$f" />

@if ($puedeCrear)
    <div class="modal fade" id="modalCajaNueva" tabindex="-1" aria-labelledby="modalCajaNuevaTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalCajaNuevaTitulo"><i class="bi bi-plus-circle"></i> Caja nueva</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="{{ route('facturacion.caja_fisica.guardar') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="cf_nombre">Nombre</label><x-ayuda campo="cf_nombre" />
                            <input class="form-control" id="cf_nombre" name="nombre" required maxlength="60" placeholder="Caja 2, Mostrador…">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="cf_suc">Sucursal</label>
                            <select class="form-select" id="cf_suc" name="id_sucursal" required>
                                @foreach ($sucursales as $s)
                                    <option value="{{ $s->id_sucursal }}">{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="text-muted-warm mb-0 mt-3" style="font-size:.82rem">
                            Cada caja lleva su propio arqueo y movimientos independientes.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-oro"><i class="bi bi-check2"></i> Crear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection
