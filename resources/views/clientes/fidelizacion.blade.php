@extends('layout.app')

@section('titulo', 'Fidelización')

@section('contenido')
    @php use App\Servicios\Navegacion; @endphp

    <x-encabezado sub="Nivel, visitas y puntos: los tres los calcula la base de datos, no se cargan a mano." />

    @if ($niveles)
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-award"></i> Los niveles y cómo se llega</h2>
            <div class="spg-niveles">
                @foreach ($niveles as $n)
                    <div class="spg-nivel">
                        <div class="spg-nivel-nombre">{{ $n->nombre }}</div>
                        <div class="spg-nivel-req">
                            desde {{ (int) $n->visitas_minimas }} visita{{ (int) $n->visitas_minimas === 1 ? '' : 's' }}
                        </div>
                        <div class="spg-nivel-desc">{{ $n->descuento ?: 'sin descuento' }}</div>
                        <div class="spg-nivel-clientes">
                            {{ (int) $n->clientes }} cliente{{ (int) $n->clientes === 1 ? '' : 's' }}
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-muted-warm mb-0 mt-2" style="font-size:.76rem">
                El nivel sube solo con las visitas, no se asigna a mano.
                @if ($urlDesc = Navegacion::url('servicios.descuentos'))
                    Los porcentajes se editan en
                    <a class="link-oro" href="{{ $urlDesc }}">Servicios → Descuentos</a>.
                @endif
            </p>
        </div>
    @endif

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Cliente</th><th>Teléfono</th><th class="text-end">Visitas</th>
                        <th class="text-end">Puntos</th><th>Nivel</th>
                        <th>Descuento del nivel</th><th class="text-end">Ver</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $r->cliente }}</td>
                            <td class="text-muted-warm">{{ $r->telefono ?: '—' }}</td>
                            <td class="text-end">{{ (int) $r->visitas }}</td>
                            <td class="text-end">{{ (int) $r->puntos }}</td>
                            <td><span class="badge-estado e-prog">{{ $r->nivel ?: 'Bronce' }}</span></td>
                            <td class="text-muted-warm">{{ $r->descuento_del_nivel ?: '—' }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-neutro" title="Historial"
                                   href="{{ route('clientes.historial', $r->id_cliente) }}">
                                    <i class="bi bi-clock-history"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-award"></i>
                                    <div class="t">
                                        {{ $f['activos'] ? 'Ningún cliente coincide con esos filtros.' : 'Todavía no hay clientes con visitas.' }}
                                    </div>
                                    <div class="d">El nivel sube solo a medida que el cliente vuelve.</div>
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
