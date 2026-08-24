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
                                        Creá una con el formulario de abajo.
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

{{-- **Crear una caja es del Administrador, y por eso el formulario está abajo
     y no arriba.** La pantalla se piensa primero para operar los cajones que
     existen, no para crearlos: un salón carga los suyos una vez. --}}
@if ($puedeCrear)
    <div class="spg-panel mt-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-plus-circle"></i> Caja nueva</h2>
        <form method="post" action="{{ route('facturacion.caja_fisica.guardar') }}"
              class="d-flex gap-2 align-items-end flex-wrap">
            @csrf
            <div>
                <label class="form-label" for="cf_nombre">Nombre</label>
                <input class="form-control form-control-sm" id="cf_nombre" name="nombre"
                       required maxlength="60" placeholder="Caja 2, Mostrador…">
            </div>
            <div>
                <label class="form-label" for="cf_suc">Sucursal</label>
                <select class="form-select form-select-sm" id="cf_suc" name="id_sucursal" required>
                    @foreach ($sucursales as $s)
                        <option value="{{ $s->id_sucursal }}">{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-sm btn-oro"><i class="bi bi-check2"></i> Crear</button>
        </form>
        <p class="text-muted-warm mt-2 mb-0" style="font-size:.82rem">
            Cada caja lleva su propio arqueo. Dos personas cobrando en el mismo
            cajón cuentan la misma plata al cerrar, así que conviene una por puesto.
        </p>
    </div>
@endif
@endsection
