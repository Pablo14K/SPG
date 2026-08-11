@extends('layout.app')

@section('titulo', 'Mis citas')

@section('contenido')
    <div class="spg-page-head spg-head-flex">
        <div class="spg-head-txt">
            <h1>Mis citas</h1>
            <div class="sub">Las que vienen y las que ya pasaron.</div>
        </div>
        <div class="spg-head-acciones">
            <a class="btn btn-oro" href="{{ route('portal.reservar') }}">
                <i class="bi bi-calendar-plus"></i> Reservar</a>
        </div>
    </div>

    <div class="spg-panel mb-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-event"></i> Próximas</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Servicios</th><th>Profesional</th><th>Estado</th><th class="text-end"></th></tr></thead>
                <tbody>
                    @forelse ($prox as $c)
                        <tr>
                            <td><strong>{{ fecha($c->fecha_hora) }}</strong></td>
                            <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                            <td>{{ $c->profesional }}</td>
                            <td>
                                {!! estado_badge($c->estado) !!}
                                @if ($c->en_curso)<span class="badge-estado e-proc">en curso</span>@endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                @if ($c->estado === 'En proceso')
                                    <a class="btn btn-sm btn-oro" href="{{ route('portal.atencion', ['id' => $c->id_cita]) }}">
                                        <i class="bi bi-eye"></i> Ver</a>
                                @elseif (! in_array($c->estado, ['Atendida', 'Cancelada'], true))
                                    <form method="post" action="{{ route('portal.cancelar') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="id_cita" value="{{ $c->id_cita }}">
                                        <button class="btn btn-sm btn-outline-neutro"
                                                data-confirmar="¿Cancelar tu cita del {{ fecha($c->fecha_hora) }}?">
                                            Cancelar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-calendar-week"></i>
                                    <div class="t">No tenés citas próximas.</div>
                                    <div class="d">Reservá una con el botón de arriba.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($pasadas)
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Anteriores</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Servicios</th><th>Profesional</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($pasadas as $c)
                            <tr>
                                <td>{{ fecha($c->fecha_hora) }}</td>
                                <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                                <td>{{ $c->profesional }}</td>
                                <td>{!! estado_badge($c->estado) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
