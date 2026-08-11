{{--
    Barra de filtros estándar.

    Se dibuja sola con lo que declaró el controlador en Listado::filtros(); la
    vista solo la incluye:  <x-filtros :f="$f" />

    Es un GET y no un POST: así el resultado filtrado tiene su propia URL y se
    puede compartir, guardar en favoritos o recargar sin que el navegador
    pregunte si reenvía el formulario. Por lo mismo, al filtrar se vuelve
    siempre a la página 1 (no se arrastra `p`).
--}}
@props(['f' => null])

@if ($f)
    @php $hayFiltros = $f['activos'] > 0; @endphp

    <form class="spg-filtros" method="get" action="{{ url()->current() }}">
        @foreach ($f['campos'] as $clave => $def)
            @php
                $tipo = $def['tipo'] ?? 'texto';
                $valor = $f['v'][$clave] ?? '';
                $ancho = $def['ancho'] ?? ($tipo === 'texto' ? '260px' : '170px');
            @endphp

            <div class="spg-filtro" style="flex:0 1 {{ $ancho }}">
                <label class="form-label" for="flt_{{ $clave }}">{{ $def['etiqueta'] ?? $clave }}</label>

                @if ($tipo === 'select')
                    <select class="form-select form-select-sm" id="flt_{{ $clave }}" name="{{ $clave }}">
                        @foreach ($def['opciones'] as $ov => $ot)
                            <option value="{{ $ov }}" @selected($valor === (string) $ov)>{{ $ot }}</option>
                        @endforeach
                    </select>
                @elseif ($tipo === 'fecha')
                    <input type="date" class="form-control form-control-sm" id="flt_{{ $clave }}"
                           name="{{ $clave }}" value="{{ $valor }}">
                @elseif ($tipo === 'numero')
                    <input type="number" class="form-control form-control-sm" id="flt_{{ $clave }}"
                           name="{{ $clave }}" value="{{ $valor }}" placeholder="{{ $def['ph'] ?? '' }}">
                @else
                    <input type="search" class="form-control form-control-sm" id="flt_{{ $clave }}"
                           name="{{ $clave }}" value="{{ $valor }}"
                           placeholder="{{ $def['ph'] ?? 'Buscar…' }}" autocomplete="off">
                @endif
            </div>
        @endforeach

        <div class="spg-filtro-btns">
            <button class="btn btn-sm btn-oro" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>

            @if ($hayFiltros)
                <a class="btn btn-sm btn-outline-neutro" href="{{ url()->current() }}"
                   title="Quitar todos los filtros"><i class="bi bi-x-lg"></i> Limpiar</a>
            @endif

            @if (! empty($f['csv']))
                {{-- Los dos bajan TODO lo filtrado, no la página que se está
                     viendo. El CSV para seguir trabajando los datos en una
                     planilla; el PDF para imprimirlo o mandarlo sin que el que
                     lo recibe tenga que abrir Excel. --}}
                @php $qs = \App\Servicios\Listado::query($f); @endphp
                <a class="btn btn-sm btn-outline-neutro"
                   href="{{ url()->current() . '?' . http_build_query(array_merge($qs, ['export' => 'csv'])) }}"
                   title="Bajar lo que se está viendo como planilla"><i class="bi bi-filetype-csv"></i> CSV</a>
                <a class="btn btn-sm btn-outline-neutro"
                   href="{{ url()->current() . '?' . http_build_query(array_merge($qs, ['export' => 'pdf'])) }}"
                   title="Bajar lo que se está viendo como PDF"><i class="bi bi-filetype-pdf"></i> PDF</a>
            @endif
        </div>

        @if ($hayFiltros)
            <div class="spg-filtro-aviso">
                <i class="bi bi-funnel-fill"></i>
                {{ $f['activos'] }} filtro{{ $f['activos'] > 1 ? 's' : '' }}
                aplicado{{ $f['activos'] > 1 ? 's' : '' }}
            </div>
        @endif
    </form>
@endif
