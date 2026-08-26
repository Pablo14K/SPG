{{--
    Informe listo para papel.

    No usa el layout general a propósito: sin barra de módulos, sin migas y sin
    pie, maquetado para hoja A4. La misma vista sirve para la descarga PDF y
    para una eventual previsualización en el navegador.
--}}
@php
    $pdf = $pdf ?? false;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informe {{ fecha($desde, 'd/m/Y') }} – {{ fecha($hasta, 'd/m/Y') }} · {{ config('app.name') }}</title>
    @if (! $pdf)
        @include('layout._favicon')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
        <link href="{{ recurso('css/imprimir.css') }}" rel="stylesheet" media="print">
    @else
        <style>
            @page { margin: 24px 28px; }
            * { box-sizing: border-box; }
            body { margin: 0; color: #28251f; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
            .container { width: 100%; max-width: none; padding: 0; }
            .d-flex { display: flex; }
            .justify-content-between { justify-content: space-between; }
            .align-items-start { align-items: flex-start; }
            .text-end { text-align: right; }
            .text-muted-warm { color: #746d61; }
            .table { width: 100%; border-collapse: collapse; margin: 5px 0 13px; }
            .table th { background: #f1e7c3; color: #382f1e; font-weight: bold; }
            .table th, .table td { border: 0.5px solid #d8d0c2; padding: 4px 5px; }
            .table-sm th, .table-sm td { padding: 3px 4px; }
            h1, h2 { color: #4d3b18; }
            hr { border: 0; border-top: 1px solid #d8d0c2; margin: 8px 0; }
            .mt-4 { margin-top: 14px; }
        </style>
    @endif
</head>
<body class="spg-imprimir">

<div class="container py-3" style="max-width:900px">

    @if (! $pdf)
    <div class="no-imprimir mb-3 d-flex gap-2">
        <button class="btn btn-oro" type="button" onclick="window.print(); return false;">
            <i class="bi bi-printer"></i> Imprimir / guardar PDF
        </button>
        <span class="form-text align-self-center">Descargar PDF: elegí «Guardar como PDF» en la ventana de impresión.</span>
        <a class="btn btn-outline-neutro" href="{{ route('reportes.index', request()->query()) }}">Volver</a>
    </div>
    @endif

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 style="font-size:1.2rem;margin-bottom:.1rem">{{ $emisor->nombre }}</h1>
            <div class="text-muted-warm" style="font-size:.8rem">
                @if ($emisor->ruc ?? null)RUC {{ $emisor->ruc }} · @endif
                {{ $emisor->ciudad ?? '' }}
            </div>
        </div>
        <div class="text-end" style="font-size:.8rem">
            <strong>Informe de gestión</strong><br>
            <span style="font-size:.78rem">{{ $bloqueNombre }}</span><br>
            {{ fecha($desde, 'd/m/Y') }} – {{ fecha($hasta, 'd/m/Y') }}<br>
            <span class="text-muted-warm">Emitido el {{ $emitido }} por {{ $porQuien }}</span>
        </div>
    </div>

    <hr>

    @if ($ver("resumen"))
    <h2 style="font-size:1rem;margin:1rem 0 .5rem">Resumen del período</h2>
    <table class="table table-sm">
        <tbody>
            <tr><td>Citas del período</td><td class="text-end">{{ (int) $citas->total }}</td></tr>
            <tr><td>Atendidas</td><td class="text-end">{{ (int) $citas->atendidas }}</td></tr>
            <tr><td>Canceladas</td><td class="text-end">{{ (int) $citas->canceladas }}</td></tr>
            <tr><td>No vino la clienta</td><td class="text-end">{{ (int) $citas->ausencias }}</td></tr>
            <tr><td><strong>Ingresos cobrados</strong></td>
                <td class="text-end"><strong>{{ money($ingresos) }}</strong></td></tr>
            @if ($devoluciones > 0)
                <tr><td>Devuelto por notas de crédito</td>
                    <td class="text-end">− {{ money($devoluciones) }}</td></tr>
                <tr><td><strong>Ingreso neto</strong></td>
                    <td class="text-end"><strong>{{ money($ingresos - $devoluciones) }}</strong></td></tr>
            @endif
            <tr><td>Ticket promedio</td><td class="text-end">{{ money($ticket) }}</td></tr>
        </tbody>
    </table>

    @endif

    @if ($ver("servicios"))
    <h2 style="font-size:1rem;margin:1.2rem 0 .5rem">Servicios más solicitados</h2>
    <table class="table table-sm">
        <thead><tr><th>Servicio</th><th>Categoría</th><th class="text-end">Veces</th><th class="text-end">Ingreso</th></tr></thead>
        <tbody>
            @forelse ($servicios as $s)
                <tr>
                    <td>{{ $s->servicio }}</td>
                    <td>{{ $s->categoria }}</td>
                    <td class="text-end">{{ (int) $s->veces_realizado }}</td>
                    <td class="text-end">{{ money($s->ingreso_generado) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin servicios en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    @endif

    @include("reportes.demanda_impresa")

    @if ($ver("medios"))
    <h2 style="font-size:1rem;margin:1.2rem 0 .5rem">Medios de pago</h2>
    <table class="table table-sm">
        <thead><tr><th>Medio</th><th class="text-end">Cobros</th><th class="text-end">Total</th></tr></thead>
        <tbody>
            @forelse ($medios as $m)
                <tr>
                    <td>{{ $m->medio }}{{ $m->tipo === 'EFECTIVO' ? ' (efectivo)' : '' }}</td>
                    <td class="text-end">{{ (int) $m->cantidad }}</td>
                    <td class="text-end">{{ money($m->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Sin cobros en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    @endif

    @php
        $mostrarSucursales = $ver('sucursales') && count($porSucursal) > 1;
    @endphp
    @if ($mostrarSucursales)
    <h2 style="font-size:1rem;margin:1.2rem 0 .5rem">Por sucursal</h2>
    <table class="table table-sm">
        <thead><tr><th>Sucursal</th><th class="text-end">Citas</th><th class="text-end">Atendidas</th>
            <th class="text-end">No vino</th><th class="text-end">Clientas</th>
            <th class="text-end">Cobrado</th></tr></thead>
        <tbody>
            @foreach ($porSucursal as $s)
                @php $ing = collect($ingresoSucursal)->firstWhere('sucursal', $s->sucursal); @endphp
                <tr>
                    <td>{{ $s->sucursal }}</td>
                    <td class="text-end">{{ entero($s->citas) }}</td>
                    <td class="text-end">{{ entero($s->atendidas) }}</td>
                    <td class="text-end">{{ entero($s->ausentes) }}</td>
                    <td class="text-end">{{ entero($s->clientes) }}</td>
                    <td class="text-end">{{ money($ing->total ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if ($ver("equipo"))
    <h2 style="font-size:1rem;margin:1.2rem 0 .5rem">El equipo</h2>
    <table class="table table-sm">
        <thead><tr><th>Profesional</th><th class="text-end">Citas</th><th class="text-end">Atendidas</th>
            <th class="text-end">No vino</th><th class="text-end">Canceladas</th>
            <th class="text-end">Faltó</th><th class="text-end">Servicios</th>
            <th class="text-end">Generado</th><th class="text-end">Comisión</th>
            <th class="text-end">Puntaje</th></tr></thead>
        <tbody>
            @forelse ($equipo as $e)
                <tr>
                    <td>{{ $e->profesional }}</td>
                    <td class="text-end">{{ (int) $e->citas }}</td>
                    <td class="text-end">{{ (int) $e->atendidas }}</td>
                    <td class="text-end">{{ (int) $e->clienta_no_vino }}</td>
                    <td class="text-end">{{ (int) $e->canceladas }}</td>
                    <td class="text-end">{{ (int) $e->falto }}</td>
                    <td class="text-end">{{ (int) $e->servicios }}</td>
                    <td class="text-end">{{ money($e->generado) }}</td>
                    <td class="text-end">{{ $e->tiene_comision ? money($e->comision) : "sin cargar" }}</td>
                    <td class="text-end">{{ $e->puntaje ? cant($e->puntaje) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Sin actividad en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    @endif

    @if ($ver("prov") && $prov)
        <h2 style="font-size:1rem;margin:1.2rem 0 .5rem">Deuda con proveedores</h2>
        <table class="table table-sm">
            <thead><tr><th>Proveedor</th><th>Vencimiento</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
                @foreach ($prov as $p)
                    <tr>
                        <td>{{ $p->proveedor }}</td>
                        <td>{{ $p->vencimiento ? fecha($p->vencimiento, 'd/m/Y') : '—' }}{{ $p->vencida ? ' (vencida)' : '' }}</td>
                        <td class="text-end">{{ money($p->saldo) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="text-muted-warm mt-4" style="font-size:.72rem">
        {{ config('app.name') }} · Sistema de gestión v{{ config('spg.version') }} ·
        Los ingresos corresponden a cobros registrados en el período; lo devuelto, a las notas de crédito emitidas en él.
    </p>
</div>

</body>
</html>
