{{-- El formulario del turno, usado dos veces: el de **crear**, siempre visible
     a la izquierda, y el de **editar**, dentro de un modal.

     Estaban fundidos en uno solo que cambiaba de cara segun `?editar=`, asi que
     al tocar «editar» el de crear desaparecia: para cargar otro turno habia que
     cancelar primero. Son dos acciones distintas y ninguna deberia tapar a la
     otra.

     · $t   el turno que se edita, o null para uno nuevo --}}
                <form method="post" action="{{ route('seguridad.turno.guardar') }}">
                    @csrf
                    <input type="hidden" name="id_turno" value="{{ $t->id_turno ?? 0 }}">

                    <div class="mb-2">
                        <label class="form-label" for="nombre">Nombre *</label>
                        <input class="form-control" id="nombre" name="nombre" required maxlength="60"
                               placeholder="Turno Mañana"
                               value="{{ old('nombre', $t->nombre ?? '') }}">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="hora_inicio">Desde *</label>
                            <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required
                                   value="{{ old('hora_inicio', isset($t) && $t ? substr((string) $t->hora_inicio, 0, 5) : '08:00') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="hora_fin">Hasta *</label>
                            <input type="time" class="form-control" id="hora_fin" name="hora_fin" required
                                   value="{{ old('hora_fin', isset($t) && $t ? substr((string) $t->hora_fin, 0, 5) : '12:00') }}">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="id_sucursal">Sucursal *</label>
                        <select class="form-select" id="id_sucursal" name="id_sucursal" required>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s->id_sucursal }}"
                                    @selected((int) old('id_sucursal', $t->id_sucursal ?? 0) === (int) $s->id_sucursal)>
                                    {{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Días que se trabaja *</label>
                        {{-- Un día por casilla, y en la base una fila por día:
                             nunca una lista tipo 'LMXJVS' dentro de una columna. --}}
                        @php($gDias = 'gDias' . ($t->id_turno ?? 0))
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="{{ $gDias }}Todo" data-marca-todo="#{{ $gDias }}">
                            <label class="form-check-label fw-semibold" for="{{ $gDias }}Todo">Todos</label>
                        </div>
                        <div class="d-flex gap-2 flex-wrap" id="{{ $gDias }}">
                            @foreach ($dias as $n => $nombreDia)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias[]" value="{{ $n }}"
                                           id="dia{{ $n }}"
                                           @checked(in_array($n, old('dias', $t->dias ?? [1, 2, 3, 4, 5, 6]), false))>
                                    {{-- mb_substr y no substr: 'Miércoles' cortado a los 3 bytes
                                         parte la é al medio y sale un rombo con un signo. --}}
                                    <label class="form-check-label" for="dia{{ $n }}">{{ mb_substr($nombreDia, 0, 3) }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-oro w-100"><i class="bi bi-check-lg"></i> Guardar</button>
                        @if ($t ?? null)
                            <button type="button" class="btn btn-outline-neutro"
                                    data-bs-dismiss="modal">Cancelar</button>
                        @endif
                    </div>
                </form>
