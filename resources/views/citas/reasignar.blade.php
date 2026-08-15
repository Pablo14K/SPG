@extends('layout.app')

@section('titulo', 'Reasignar citas')

@section('contenido')
    <x-encabezado sub="Pasarle a otro profesional las citas futuras de alguien que no va a estar." />

    <div class="spg-panel">
        <form method="get" action="{{ route('citas.reasignar') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="de">¿De quién son las citas?</label>
                <select class="form-select" id="de" name="de" onchange="this.form.submit()">
                    <option value="0">Elegí a la persona…</option>
                    @foreach ($conCitas as $c)
                        <option value="{{ $c->id_usuario }}" @selected($de === (int) $c->id_usuario)>
                            {{ $c->nombre }}@if (! $c->activo) (dado de baja) @endif
                            — {{ (int) $c->pendientes }} cita(s)
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <p class="text-muted-warm mb-0" style="font-size:.85rem">
                    Sólo se listan las citas <strong>futuras que ocupan agenda</strong>. El horario no
                    cambia: lo único que cambia es quién atiende, así que la clienta no tiene que
                    hacer nada.
                </p>
            </div>
        </form>
    </div>

    @if ($de && ! $citas)
        <div class="spg-panel mt-3">
            <p class="mb-0 text-muted-warm">
                {{ $origen->nombre ?? 'Esa persona' }} no tiene citas futuras. No hay nada que reasignar.
            </p>
        </div>
    @endif

    @if ($citas)
        <form method="post" action="{{ route('citas.reasignar.guardar') }}">
            @csrf
            <input type="hidden" name="de" value="{{ $de }}">

            <div class="spg-panel mt-3">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:2.5rem">
                                    {{-- La misma pieza que usan la matriz de permisos y los
                                         bloques de Reportes: refleja lo marcado y prende o
                                         apaga el grupo. No lleva `name`, así que no se envía. --}}
                                    <input class="form-check-input" type="checkbox"
                                           data-marca-todo=".cita-check" checked aria-label="Todas">
                                </th>
                                <th>Cuándo</th><th>Clienta</th><th>Servicios</th>
                                <th class="text-end">Dura</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($citas as $c)
                                <tr>
                                    <td>
                                        <input class="form-check-input cita-check" type="checkbox"
                                               name="citas[]" value="{{ $c->id_cita }}" checked
                                               aria-label="Reasignar esta cita">
                                    </td>
                                    <td>{{ fecha($c->fecha_hora, 'd/m/Y H:i') }}</td>
                                    <td>{{ $c->cliente }}</td>
                                    <td class="text-muted-warm">{{ $c->servicios ?: '—' }}</td>
                                    <td class="text-end text-muted-warm">{{ (int) $c->dur }} min</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="spg-panel mt-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="a">¿A quién se las paso?</label>
                        <select class="form-select" id="a" name="a" required>
                            <option value="">Elegí un profesional…</option>
                            @foreach ($profs as $p)
                                @continue((int) $p->id_usuario === $de)
                                <option value="{{ $p->id_usuario }}">{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-flex gap-2">
                        <button class="btn btn-oro"
                                data-confirmar="Se van a pasar las citas marcadas. ¿Confirmás?">
                            <i class="bi bi-arrow-left-right"></i> Reasignar
                        </button>
                        <a class="btn btn-outline-neutro" href="{{ route('citas.agenda') }}">Volver a la agenda</a>
                    </div>
                </div>

                <p class="text-muted-warm mt-3 mb-0" style="font-size:.85rem">
                    Se comprueba una por una: <strong>las que caigan en un horario donde esa persona
                    no está libre quedan como estaban</strong> y el sistema dice cuáles son. No se
                    reasigna nada a ciegas, porque sería vender dos veces el mismo horario.
                </p>
            </div>
        </form>
    @endif
@endsection
