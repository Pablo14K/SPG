@extends('layout.app')

@section('titulo', 'Servicios')

@section('contenido')
    <x-encabezado
        sub="Lo que ofrece el salón: precio, duración e IVA. La duración es la que usa la agenda para calcular los huecos."
        :accion="['ruta' => 'servicios.form', 't' => 'Nuevo servicio', 'ic' => 'plus-lg']" />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Servicio</th><th>Categoría</th><th class="text-end">Precio</th>
                        <th class="text-end">Duración</th><th class="text-end">IVA</th>
                        <th>Estado</th><th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $s)
                        <tr>
                            <td>
                                {{ $s->nombre }}
                                @if ($s->requiere_exclusividad)
                                    <span class="badge-estado e-warn" title="No se puede hacer al mismo tiempo que otro servicio exclusivo">exclusivo</span>
                                @endif
                                @if ($s->descripcion)
                                    <div class="text-muted-warm" style="font-size:.76rem">{{ $s->descripcion }}</div>
                                @endif
                            </td>
                            <td class="text-muted-warm">{{ $s->categoria }}</td>
                            <td class="text-end">{{ money($s->precio) }}</td>
                            <td class="text-end">{{ (int) $s->duracion_min }} min</td>
                            <td class="text-end">{{ (int) $s->tasa_iva }}%</td>
                            <td>
                                @if ($s->activo)
                                    <span class="badge-estado e-ok">Activo</span>
                                @else
                                    <span class="badge-estado e-muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('servicios.form', $s->id_servicio) }}"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="{{ route('servicios.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_servicio" value="{{ $s->id_servicio }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $s->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $s->activo ? 'Desactivar' : 'Activar' }} «{{ $s->nombre }}»?">
                                        <i class="bi bi-toggle-{{ $s->activo ? 'on' : 'off' }}"></i></button>
                                </form>

                                {{-- **Qué ve la clienta en ESTE local.** Sacarlo de
                                     acá no lo da de baja en el salón: deja de
                                     ofrecerse en esta sucursal y la clienta no lo
                                     ve al reservar acá. Sólo tiene sentido con
                                     más de un local. --}}
                                @if ($varias)
                                    <form method="post" action="{{ route('servicios.publicar') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_servicio" value="{{ $s->id_servicio }}">
                                        <input type="hidden" name="sacar" value="1">
                                        <button class="btn btn-sm btn-outline-neutro" title="No ofrecerlo en esta sucursal"
                                                data-confirmar="La clienta va a dejar de ver «{{ $s->nombre }}» al reservar en esta sucursal. En los otros locales sigue igual. ¿Seguimos?">
                                            <i class="bi bi-shop-window"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-scissors"></i>
                                    <div class="t">{{ $f['activos'] ? 'Ningún servicio coincide con esos filtros.' : 'Todavía no hay servicios cargados.' }}</div>
                                    <div class="d">{{ $f['activos'] ? 'Probá con menos filtros.' : 'Sin servicios no se pueden agendar citas.' }}</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />
    </div>
@endsection
