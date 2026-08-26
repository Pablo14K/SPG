@extends('layout.app')

@section('titulo', 'Cajas')

@section('contenido')
@php use App\Servicios\Permisos; @endphp

{{-- **Filtros arriba, tabla, paginación.** Es la misma forma que Movimientos y
     Arqueos, y no cambia con el tamaño del salón: con 3 cajones o con 300 lo
     único que crece son las filas.

     Cada fila dice lo mínimo para ELEGIR —caja, estado, responsable, hora— y
     nada más. El monto, los movimientos y el arqueo se consultan entrando: una
     tabla que lo muestra todo no se lee, se hojea. --}}
<x-encabezado sub="Los cajones del salón y cuál está abierto ahora." />

@if ($puedeCrear)
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modalCajaNueva">
            <i class="bi bi-plus-circle"></i> Nueva caja
        </button>
    </div>
@endif

<x-filtros :f="$f" />

<div class="spg-panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Caja</th>
                    @if (count($sucursales) > 1)<th>Sucursal</th>@endif
                    <th>Estado</th><th>Responsable</th><th>Apertura</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $c)
                    <tr>
                        <td>{{ $c->nombre }}</td>
                        @if (count($sucursales) > 1)
                            <td class="text-muted-warm">{{ $c->sucursal }}</td>
                        @endif
                        <td>
                            @if ($c->id_caja)
                                <span class="badge-estado e-ok"><i class="bi bi-unlock"></i> Abierta</span>
                            @else
                                <span class="badge-estado e-muted"><i class="bi bi-lock"></i> Cerrada</span>
                            @endif
                        </td>
                        <td class="text-muted-warm">{{ $c->responsable ?: '—' }}</td>
                        <td class="text-muted-warm" style="white-space:nowrap">
                            {{ $c->fecha_apertura ? fecha($c->fecha_apertura, 'd/m H:i') : '—' }}</td>
                        <td class="text-end">
                            {{-- **Un solo botón por fila.** «Ver» si está abierta,
                                 «Abrir» si no: son las dos únicas cosas que se
                                 hacen desde una lista. --}}
                            <a class="btn btn-sm {{ $c->id_caja ? 'btn-outline-neutro' : 'btn-oro' }}"
                               href="{{ route('facturacion.caja_ver', $c->id_caja_fisica) }}">
                                {{ $c->id_caja ? 'Ver' : 'Abrir' }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($sucursales) > 1 ? 6 : 5 }}">
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
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
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
                            <label class="form-label" for="cf_nombre">Nombre</label>
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
