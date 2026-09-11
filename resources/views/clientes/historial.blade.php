@extends('layout.app')

@section('titulo', 'Historial del cliente')

@section('contenido')
    <div class="sgp-page-head">
        <a class="sgp-back" href="{{ route('clientes.lista') }}"><i class="bi bi-arrow-left"></i> Clientes</a>
        <h1 class="mt-1">{{ $c->nombre }} {{ $c->apellido }}</h1>
        <div class="sub">{{ $c->telefono ?: 'Sin teléfono' }} · {{ $c->email ?: 'Sin email' }}</div>
    </div>

    {{-- **Las alergias van arriba de todo y en rojo.** Es lo único de esta
         pantalla que puede lastimar a alguien si se pasa por alto, así que no
         compite con el nivel ni con los puntos: se lee antes que nada. --}}
    @if (! empty($c->alergias))
        <div class="alert alert-danger mt-2 mb-2">
            <strong><i class="bi bi-exclamation-triangle-fill"></i> Alergias:</strong>
            {{ $c->alergias }}
        </div>
    @endif

    @if ($fid)
        <div class="sgp-metrics">
            <div class="sgp-metric">
                <div class="lbl">Nivel</div>
                <div class="val oro">{{ $fid->nivel ?: 'Bronce' }}</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Visitas</div>
                <div class="val">{{ (int) $fid->visitas }}</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Puntos</div>
                <div class="val">{{ (int) $fid->puntos }}</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Descuento</div>
                <div class="val" style="font-size:1rem">{{ $fid->descuento_del_nivel ?: '—' }}</div>
            </div>
        </div>
    @endif

    {{-- **El perfil: cómo es esta clienta, no qué pasó tal día.**

         La tabla de abajo contesta lo segundo, y con cien filas paginadas de a
         veinticinco saber que siempre pide lo mismo, que viene los sábados o
         que se atiende con la misma persona obligaba a leerlas todas llevando
         la cuenta a mano. Son las preguntas del mostrador antes de atender:
         qué ofrecerle, cuándo llamarla y a quién asignarle.

         **No respeta los filtros de la tabla, a propósito**: filtrado por un
         mes cualquiera diría que su servicio favorito es el único que se hizo
         ese mes. Es el perfil de la persona, no el total de lo filtrado. --}}
    @if ($perfil && (int) $perfil->visitas > 0)
        <div class="sgp-panel mt-2">
            <h2 style="font-size:1rem;font-weight:500;margin-bottom:.8rem;">
                <i class="bi bi-person-badge"></i> Su perfil
                <x-ayuda>Sale de todo su historial, no de lo que estés filtrando abajo.</x-ayuda>
            </h2>

            <div class="sgp-metrics mb-3">
                <div class="sgp-metric">
                    <div class="lbl">Visitas</div>
                    <div class="val">{{ (int) $perfil->visitas }}</div>
                </div>
                <div class="sgp-metric">
                    <div class="lbl">Servicios</div>
                    <div class="val">{{ (int) $perfil->servicios }}</div>
                </div>
                <div class="sgp-metric">
                    {{-- **Facturado, no cobrado.** Sale de los precios del historial:
                         son dos números distintos y el rótulo tiene que decir cuál. --}}
                    <div class="lbl">Facturado</div>
                    <div class="val" style="font-size:1rem">{{ money($perfil->gastado ?? 0) }}</div>
                </div>
                <div class="sgp-metric">
                    <div class="lbl">Viene cada</div>
                    <div class="val" style="font-size:1rem">
                        @if ($perfil->cada_dias)
                            {{ (int) $perfil->cada_dias }} días
                        @else
                            {{-- Con una sola visita no hay intervalo que medir, y un
                                 «0 días» se leería como un dato. --}}
                            <span class="text-muted-warm" style="font-size:.85rem">1ª visita</span>
                        @endif
                    </div>
                </div>
                <div class="sgp-metric">
                    <div class="lbl">Última</div>
                    <div class="val" style="font-size:1rem">{{ fecha($perfil->ultima, 'd/m/Y') }}</div>
                </div>
                <div class="sgp-metric">
                    <div class="lbl">Cliente desde</div>
                    <div class="val" style="font-size:1rem">{{ fecha($perfil->primera, 'd/m/Y') }}</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-label mb-1">Lo que más pide</div>
                    @php $topSrv = max(1, (int) ($favoritos[0]->veces ?? 1)); @endphp
                    @foreach ($favoritos as $sv)
                        <div class="sgp-graf-fila">
                            <div class="sgp-graf-rot" style="width:9rem">{{ $sv->servicio }}</div>
                            {{-- La barra es un `width` en por ciento sobre dos divs: no
                                 entra ninguna librería, la misma decisión que Reportes. --}}
                            <div class="sgp-graf-pista">
                                <div class="sgp-graf-barra" style="width:{{ round((int) $sv->veces / $topSrv * 100) }}%"></div>
                            </div>
                            <div class="sgp-graf-val sgp-graf-val-ancho">
                                {{ (int) $sv->veces }}×
                                <span class="text-muted-warm">· {{ money($sv->gastado) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="col-md-3">
                    <div class="form-label mb-1">Qué días viene</div>
                    @php
                        // 1 = lunes … 7 = domingo, la convención del proyecto.
                        $nomDia = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                                   5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                        $topDia = max(1, (int) ($porDia[0]->visitas ?? 1));
                    @endphp
                    @foreach (array_slice($porDia, 0, 4) as $d)
                        <div class="sgp-graf-fila">
                            <div class="sgp-graf-rot" style="width:4.6rem">{{ $nomDia[(int) $d->dia] ?? '—' }}</div>
                            <div class="sgp-graf-pista">
                                <div class="sgp-graf-barra" style="width:{{ round((int) $d->visitas / $topDia * 100) }}%"></div>
                            </div>
                            <div class="sgp-graf-val">{{ (int) $d->visitas }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="col-md-3">
                    <div class="form-label mb-1">A qué hora</div>
                    @php $topHora = max(1, (int) ($porHora[0]->visitas ?? 1)); @endphp
                    @foreach (array_slice($porHora, 0, 4) as $h)
                        <div class="sgp-graf-fila">
                            <div class="sgp-graf-rot" style="width:3.2rem">{{ sprintf('%02d:00', (int) $h->hora) }}</div>
                            <div class="sgp-graf-pista">
                                <div class="sgp-graf-barra" style="width:{{ round((int) $h->visitas / $topHora * 100) }}%"></div>
                            </div>
                            <div class="sgp-graf-val">{{ (int) $h->visitas }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if (count($conQuien) > 0)
                <div class="mt-3">
                    <div class="form-label mb-1">Con quién se atiende</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($conQuien as $q)
                            <span class="badge-estado e-muted">{{ $q->profesional }} · {{ (int) $q->visitas }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <x-filtros :f="$f" />

    <div class="sgp-panel mt-2">
        <h2 style="font-size:1rem;font-weight:500;margin-bottom:.8rem;">Historial de servicios</h2>
        <div class="table-responsive sgp-tabla-movil">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Fecha</th><th>Servicio</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                    @forelse ($hist as $h)
                        <tr>
                            <td class="sgp-movil-titulo" data-label="Fecha">{{ fecha($h->fecha_hora) }}</td>
                            <td data-label="Servicio">{{ $h->servicio }}</td>
                            <td class="text-end" style="white-space:nowrap">
                                <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                        data-bs-target="#detHist{{ $loop->index }}" aria-expanded="false">
                                    <i class="bi bi-chevron-down"></i> Detalle
                                </button>
                            </td>
                        </tr>
                        <tr class="sgp-fila-detalle">
                            <td colspan="3">
                                <div class="collapse" id="detHist{{ $loop->index }}">
                                    <div class="sgp-det-cuerpo">
                                        <div class="sgp-det-grid">
                                            <div>
                                                <dt>Profesional</dt>
                                                <dd>{{ $h->profesional }}</dd>
                                            </div>
                                            <div>
                                                <dt>Comprobante</dt>
                                                <dd>{{ $h->nro_comprobante ?: '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt>Puntaje</dt>
                                                <dd class="txt-oro">{{ $h->puntaje ? str_repeat('★', (int) $h->puntaje) : '—' }}</dd>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted-warm py-4">
                                {{ $pag['total'] ? 'Ningún servicio coincide con lo que buscaste.' : 'Sin servicios registrados.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-paginacion :pag="$pag" :f="$f" />
@endsection
