@extends('layout.app')

@section('titulo', 'Turnos')

@section('contenido')
    <x-encabezado sub="El turno es una <strong>plantilla</strong>, no una fecha: un nombre, un horario y los días de la semana en que se trabaja. Se define una vez y se le asigna a cada persona desde su ficha." />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock"></i> Nuevo turno</h2>
                @include('seguridad._turno_form', ['t' => null])
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Turno</th><th>Horario</th><th>Entrada</th><th>Días</th><th>Quiénes lo trabajan</th>
                                <th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $t)
                                <tr>
                                    <td>
                                        {{ $t->nombre }}
                                        <div class="text-muted-warm" style="font-size:.76rem">{{ $t->sucursal }}</div>
                                    </td>
                                    <td style="white-space:nowrap">
                                        {{ substr((string) $t->hora_inicio, 0, 5) }}
                                        a {{ substr((string) $t->hora_fin, 0, 5) }}
                                    </td>
                                    <td>{{ (int) ($t->flexibilidad_entrada_min ?? 15) }} min</td>
                                    <td class="text-muted-warm" style="font-size:.82rem">{{ $t->dias_texto }}</td>
                                    <td class="text-muted-warm" style="font-size:.82rem">
                                        @if (! empty($gente[$t->id_turno]))
                                            {{ implode(', ', $gente[$t->id_turno]) }}
                                        @else
                                            <span class="txt-no">nadie todavía</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space:nowrap">
                                        {{-- Abre el modal en vez de recargar: asi el formulario
                                             de «Nuevo turno» sigue a la vista. --}}
                                        <button type="button" class="btn btn-sm btn-outline-neutro" title="Editar"
                                                data-bs-toggle="modal" data-bs-target="#modalTurno{{ $t->id_turno }}">
                                            <i class="bi bi-pencil"></i></button>
                                        <form method="post" action="{{ route('seguridad.turno.baja') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id_turno" value="{{ $t->id_turno }}">
                                            <button class="btn btn-sm btn-outline-neutro" title="Dar de baja"
                                                    data-confirmar="¿Dar de baja el turno «{{ $t->nombre }}»? Quienes lo trabajan van a quedar sin ese horario en la agenda.">
                                                <i class="bi bi-toggle-on"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="spg-vacio">
                                            <i class="bi bi-clock"></i>
                                            <div class="t">Todavía no hay turnos cargados.</div>
                                            <div class="d">
                                                Sin turnos, la agenda no sabe cuándo atiende cada persona.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- **Editar va en un emergente, no en el panel de la izquierda.** Antes los
         dos formularios eran el mismo y cambiaba de cara con `?editar=`, así que
         al tocar «editar» desaparecía el de crear: para cargar otro turno había
         que cancelar primero. Son dos acciones distintas y ninguna tapa a la
         otra. Los campos salen del mismo partial, así que no se pueden
         desfasar. --}}
    @foreach ($rows as $t)
        <div class="modal fade" id="modalTurno{{ $t->id_turno }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" style="font-size:1rem">
                            <i class="bi bi-clock"></i> Editar «{{ $t->nombre }}»</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @include('seguridad._turno_form', ['t' => $t])
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
