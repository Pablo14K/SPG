@extends('layout.app')

@section('titulo', 'Excepciones de agenda')

@section('contenido')
    <x-encabezado sub="Feriados, licencias y bloqueos. Mientras están cargados, la agenda no ofrece esos horarios." />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-x"></i> Nueva excepción</h2>

                <form method="post" action="{{ route('citas.ausencia.guardar') }}">
                    @csrf

                    @include('citas._ausencia_campos')

                    <button class="btn btn-oro w-100"
                            data-confirmar="Mientras esté cargada, la agenda no va a ofrecer esos horarios. ¿Registrar la excepción?">
                        <i class="bi bi-check-lg"></i> Registrar
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <div class="table-responsive spg-tabla-movil">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Quién</th><th>Dónde</th><th>Tipo</th><th>Desde</th><th>Hasta</th>
                                <th>Motivo</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $a)
                                {{-- **La dada de baja sigue en la lista.** Si al
                                     apagarla desapareciera, el botón se leería como
                                     «borrar» y no habría desde dónde deshacerlo. --}}
                                <tr @class(['text-muted-warm' => ! $a->activo])>
                                    <td class="spg-movil-titulo" data-label="Quién">{{ $a->quien }}</td>
                                    <td class="text-muted-warm" data-label="Dónde">{{ $a->donde }}</td>
                                    <td data-label="Tipo"><span class="badge-estado {{ $a->activo ? 'e-prog' : 'e-muted' }}">{{ $a->tipo }}</span></td>
                                    <td data-label="Desde">{{ fecha($a->fecha_inicio) }}</td>
                                    <td data-label="Hasta">{{ fecha($a->fecha_fin) }}</td>
                                    <td class="text-muted-warm" data-label="Motivo">{{ $a->motivo ?: '—' }}</td>
                                    <td data-label="Estado">
                                        @if ($a->activo)
                                            <span class="badge-estado e-ok">Vigente</span>
                                        @else
                                            <span class="badge-estado e-muted">De baja</span>
                                        @endif
                                    </td>
                                    <td class="text-end spg-movil-acciones" style="white-space:nowrap">
                                        {{-- **Editar sólo mientras no haya empezado.**
                                             Una excepción que ya arrancó dejó de ser un
                                             plan: la agenda no ofreció esos horarios,
                                             puede haber clientas avisadas y citas movidas
                                             por ella. Cambiarle el rango hacia atrás no
                                             deshace nada de eso y sí deja la fila
                                             diciendo algo que no fue lo que pasó — para
                                             eso está la baja, que corta de acá en
                                             adelante y lo dice.

                                             El botón no se dibuja cuando ya empezó, y el
                                             servidor lo vuelve a comprobar: esconderlo
                                             no es el control. --}}
                                        @if ($a->activo && $a->editable)
                                            <button class="btn btn-sm btn-outline-neutro" type="button"
                                                    title="Editar" data-bs-toggle="modal"
                                                    data-bs-target="#edAus{{ $a->id_ausencia }}">
                                                <i class="bi bi-pencil"></i></button>
                                        @endif
                                        <form method="post" action="{{ route('citas.ausencia.baja') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id_ausencia" value="{{ $a->id_ausencia }}">
                                            <button class="btn btn-sm btn-outline-neutro"
                                                    title="{{ $a->activo ? 'Dar de baja' : 'Volver a aplicar' }}"
                                                    data-confirmar="{{ $a->activo
                                                        ? '¿Dar de baja esta excepción? Esos horarios vuelven a poder agendarse. Las citas que ya se movieron por esto no se deshacen solas.'
                                                        : '¿Volver a aplicar esta excepción? Esos horarios dejan de ofrecerse.' }}">
                                                <i class="bi bi-toggle-{{ $a->activo ? 'on' : 'off' }}"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="spg-vacio">
                                            <i class="bi bi-calendar-x"></i>
                                            <div class="t">No hay excepciones cargadas.</div>
                                            <div class="d">Cargá una cuando el salón cierre o alguien se ausente.</div>
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

    {{-- **Los modales van fuera de la tabla.** Uno dibujado dentro de un `<tr>`
         hereda cualquier `display:none` del ancestro y después no se puede
         mostrar ni con Bootstrap haciendo su trabajo: es el defecto que el
         detalle de la agenda pagó en la 7.87.4. --}}
    @foreach ($rows as $a)
        @if ($a->activo && $a->editable)
            <div class="modal fade" id="edAus{{ $a->id_ausencia }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="post" action="{{ route('citas.ausencia.guardar') }}">
                            @csrf
                            <input type="hidden" name="id_ausencia" value="{{ $a->id_ausencia }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    <i class="bi bi-pencil"></i> Editar la excepción</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted-warm" style="font-size:.85rem">
                                    Todavía no empezó, así que se puede corregir entera.
                                    Empieza el <strong>{{ fecha($a->fecha_inicio) }}</strong>.
                                </p>
                                @include('citas._ausencia_campos', ['a' => $a, 'pfx' => 'ed' . $a->id_ausencia . '_'])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro"
                                        data-confirmar="¿Guardar los cambios? Si el rango cambia, se le vuelve a avisar a las clientas que queden dentro.">
                                    <i class="bi bi-check-lg"></i> Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
