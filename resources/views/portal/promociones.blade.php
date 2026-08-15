@extends('layout.app')

@section('titulo', 'Promociones')

@section('contenido')
    <div class="spg-page-head">
        <h1>Promociones</h1>
        <div class="sub">Se aplica <strong>una sola</strong> por comprobante: la que más te convenga entre tu nivel y las promos vigentes.</div>
    </div>

    @if ($fid)
        <div class="spg-metrics mb-3">
            <div class="spg-metric">
                <div class="lbl">Tu nivel</div>
                <div class="val oro">{{ $fid->nivel ?: 'Bronce' }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Visitas</div>
                <div class="val">{{ (int) $fid->visitas }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Puntos</div>
                <div class="val">{{ (int) $fid->puntos }}</div>
            </div>
            <div class="spg-metric">
                <div class="lbl">Tu descuento</div>
                <div class="val" style="font-size:1rem">{{ $fid->descuento_del_nivel ?: '—' }}</div>
            </div>
        </div>
    @endif

    <div class="spg-panel">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-percent"></i> Promociones vigentes</h2>
        @forelse ($promos as $p)
            <div class="d-flex justify-content-between align-items-center py-2"
                 style="border-bottom:1px solid var(--gris-calido)">
                <div>
                    <strong>{{ $p->nombre }}</strong>
                    @if ($p->descripcion)
                        <div class="text-muted-warm" style="font-size:.82rem">{{ $p->descripcion }}</div>
                    @endif
                    @if ($p->fecha_fin)
                        <div class="text-muted-warm" style="font-size:.76rem">
                            hasta el {{ fecha($p->fecha_fin, 'd/m/Y') }}</div>
                    @endif
                </div>
                <span class="txt-oro" style="font-size:1.05rem;font-weight:600">
                    {{ $p->tipo === 'PORCENTAJE' ? cant($p->valor) . ' %' : money($p->valor) }}
                </span>
            </div>
        @empty
            <div class="spg-vacio">
                <i class="bi bi-percent"></i>
                <div class="t">Por ahora no hay promociones vigentes.</div>
                <div class="d">Tu descuento por nivel sigue aplicándose igual.</div>
            </div>
        @endforelse
    </div>

    {{-- ------------------------------------------------------------------
         Canje de puntos. Va DEBAJO de las promociones porque es lo mismo
         visto de otra manera: cómo pagar menos. La diferencia es que la
         promoción se aplica sola y el canje se elige.
         ------------------------------------------------------------------ --}}
    <div class="spg-panel mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
            <h2 style="font-size:1rem;font-weight:500;margin:0">
                <i class="bi bi-gift txt-oro"></i> Canjeá tus puntos
            </h2>
            <span class="txt-oro" style="font-weight:600">Tenés {{ (int) $puntos }} punto(s)</span>
        </div>

        @if ($canjeables)
            <p class="text-muted-warm" style="font-size:.82rem">
                Al canjear, el servicio te queda guardado y lo elegís cuando reservás tu cita.
                Cada canje tiene su fecha límite para usarlo.
            </p>

            <div class="spg-lista-simple">
                @foreach ($canjeables as $c)
                    @php $alcanza = (int) $puntos >= (int) $c->puntos; @endphp
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2"
                         style="border-bottom:1px solid var(--gris-calido)">
                        <div>
                            <strong>{{ $c->nombre }}</strong>
                            <div class="text-muted-warm" style="font-size:.8rem">
                                {{ $c->categoria }} · vale {{ money($c->precio) }} ·
                                {{ (int) $c->duracion_min }} min ·
                                lo usás dentro de {{ (int) $c->dias_vigencia }} día(s)
                            </div>
                        </div>
                        <div class="text-end" style="white-space:nowrap">
                            <div class="txt-oro" style="font-weight:600">{{ (int) $c->puntos }} pts</div>
                            @if ($alcanza)
                                <form method="post" action="{{ route('portal.canjear') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_servicio" value="{{ $c->id_servicio }}">
                                    <button class="btn btn-sm btn-oro mt-1"
                                            data-confirmar="Se te van a descontar {{ (int) $c->puntos }} puntos por {{ $c->nombre }}. ¿Canjeamos?">
                                        Canjear
                                    </button>
                                </form>
                            @else
                                <div class="text-muted-warm" style="font-size:.76rem">
                                    te faltan {{ (int) $c->puntos - (int) $puntos }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="spg-vacio">
                <i class="bi bi-gift"></i>
                <div class="t">Todavía no hay servicios para canjear.</div>
                <div class="d">Seguí sumando puntos: cuando el salón publique alguno, aparece acá.</div>
            </div>
        @endif
    </div>

    @if ($misCanjes)
        <div class="spg-panel mt-3">
            <h2 style="font-size:1rem;font-weight:500;margin:0 0 .5rem">
                <i class="bi bi-ticket-perforated txt-oro"></i> Lo que canjeaste
            </h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Servicio</th><th>Vence</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($misCanjes as $c)
                            <tr>
                                <td>{{ $c->nombre }}</td>
                                <td style="white-space:nowrap">{{ fecha($c->vence_en, 'd/m/Y') }}</td>
                                <td>
                                    @switch($c->estado)
                                        @case('USADO')
                                            <span class="badge-estado e-ok">Ya lo usaste</span> @break
                                        @case('VENCIDO')
                                            <span class="badge-estado e-no">Se venció</span> @break
                                        @default
                                            <span class="badge-estado e-warn">
                                                Para usar · quedan {{ (int) $c->dias_restantes }} día(s)
                                            </span>
                                    @endswitch
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted-warm mt-2 mb-0" style="font-size:.8rem">
                Los que están para usar te aparecen al <strong>reservar una cita</strong>.
            </p>
        </div>
    @endif
@endsection
