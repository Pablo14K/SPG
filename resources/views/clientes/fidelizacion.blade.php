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
                            <td class="text-end" style="white-space:nowrap">
                                {{-- Canjear desde el mostrador: la clienta viene al local y
                                     pide gastar sus puntos. La mayoría ni tiene cuenta en el
                                     portal, así que sin esto no podría canjear nunca.
                                     El botón sale sólo si hay algo que canjear y si le
                                     alcanza para algo: ofrecerlo sin poder usarlo sería el
                                     mismo cartel que promete y no cumple. --}}
                                @if ($canjeables && (int) $r->puntos >= $canjeMasBarato)
                                    <button class="btn btn-sm btn-oro" data-bs-toggle="modal"
                                            data-bs-target="#modalCanje"
                                            data-cliente="{{ $r->id_cliente }}"
                                            data-nombre="{{ $r->cliente }}"
                                            data-puntos="{{ (int) $r->puntos }}"
                                            title="Canjear sus puntos">
                                        <i class="bi bi-gift"></i></button>
                                @endif
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

    @if ($canjeables)
        <div class="modal fade" id="modalCanje" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('clientes.canjear') }}">
                        @csrf
                        <input type="hidden" name="id_cliente" id="canjeCliente">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-gift"></i> Canjear puntos de <span id="canjeNombre"></span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.82rem">
                                Tiene <strong id="canjePuntos"></strong> punto(s). Al canjear se le
                                descuentan y le queda el servicio guardado: <strong>aparece al
                                agendarle la cita</strong>, y ahí no se le cobra.
                            </p>
                            <label class="form-label" for="canjeServicio">¿Qué canjea?</label>
                            <select class="form-select" id="canjeServicio" name="id_servicio" required>
                                @foreach ($canjeables as $c)
                                    <option value="{{ $c->id_servicio }}" data-puntos="{{ (int) $c->puntos }}">
                                        {{ $c->nombre }} — {{ (int) $c->puntos }} pts ·
                                        vale {{ money($c->precio) }} ·
                                        {{ (int) $c->dias_vigencia }} día(s) para usarlo
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2 text-muted-warm" id="canjeAviso" style="font-size:.82rem"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Canjear</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
            (function () {
                var modal = document.getElementById('modalCanje');
                if (!modal) return;
                var sel = document.getElementById('canjeServicio');
                var aviso = document.getElementById('canjeAviso');
                var tiene = 0;

                // Se avisa ANTES de apretar si no le alcanza: el procedimiento lo
                // rechaza igual, pero enterarse después de confirmar es peor.
                function revisar() {
                    var op = sel.options[sel.selectedIndex];
                    var cuesta = op ? parseInt(op.getAttribute('data-puntos'), 10) : 0;
                    if (cuesta > tiene) {
                        aviso.innerHTML = '<span class="txt-no">No le alcanza: le faltan '
                                        + (cuesta - tiene) + ' punto(s).</span>';
                    } else {
                        aviso.textContent = 'Le quedan ' + (tiene - cuesta) + ' punto(s) después del canje.';
                    }
                }

                modal.addEventListener('show.bs.modal', function (e) {
                    var b = e.relatedTarget;
                    if (!b) return;
                    document.getElementById('canjeCliente').value = b.getAttribute('data-cliente');
                    document.getElementById('canjeNombre').textContent = b.getAttribute('data-nombre');
                    tiene = parseInt(b.getAttribute('data-puntos'), 10) || 0;
                    document.getElementById('canjePuntos').textContent = tiene;
                    revisar();
                });
                sel.addEventListener('change', revisar);
            })();
            </script>
        @endpush
    @endif
@endsection
