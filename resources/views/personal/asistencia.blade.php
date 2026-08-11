@extends('layout.app')

@section('titulo', 'Asistencia')

@section('contenido')
    <x-encabezado sub="Quiénes trabajan hoy, según el turno que tienen asignado. <strong>No se escriben horarios a mano</strong>: se ficha con un botón y queda la hora del clic." />

    <div class="spg-panel mb-3">
        <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label" for="fecha">Día</label>
                <input type="date" class="form-control form-control-sm" id="fecha" name="fecha" value="{{ $fecha }}">
            </div>
            <button class="btn btn-sm btn-oro"><i class="bi bi-calendar-check"></i> Ver</button>
            <a class="btn btn-sm btn-outline-neutro" href="{{ route('personal.asistencia') }}">Hoy</a>
            <span class="ms-auto text-muted-warm" style="font-size:.85rem">
                {{ fecha_larga($fecha) }} · son las {{ substr($ahora, 0, 5) }}
            </span>
        </form>
    </div>

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Profesional</th><th>Turno</th><th>Entrada</th><th>Salida</th>
                        <th>Estado</th><th class="text-end">Fichar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $f)
                        <tr>
                            <td>{{ $f->profesional }}</td>
                            <td>
                                {{ $f->turno }}
                                <div class="text-muted-warm" style="font-size:.76rem">
                                    {{ substr((string) $f->hora_inicio, 0, 5) }} a {{ substr((string) $f->hora_fin, 0, 5) }}
                                    · {{ $f->sucursal }}
                                </div>
                            </td>
                            <td>{{ $f->hora_entrada ? substr((string) $f->hora_entrada, 0, 5) : '—' }}</td>
                            <td>
                                {{ $f->hora_salida ? substr((string) $f->hora_salida, 0, 5) : '—' }}
                                @if ((float) ($f->horas_extras ?? 0) > 0)
                                    <div class="text-muted-warm" style="font-size:.72rem">
                                        +{{ cant($f->horas_extras) }} h extra
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($f->justificada === null && $f->hora_entrada)
                                    <span class="badge-estado e-ok">Presente</span>
                                @elseif ((int) $f->justificada === 1)
                                    <span class="badge-estado e-warn">Falta con permiso</span>
                                    <div class="text-muted-warm" style="font-size:.72rem">{{ $f->motivo_ausencia }}</div>
                                @elseif ((int) $f->justificada === 0 && $f->id_asistencia)
                                    <span class="badge-estado e-no">Falta sin aviso</span>
                                @else
                                    <span class="badge-estado e-muted">Sin fichar</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                @php $mio = (int) $f->id_usuario === $yo; @endphp
                                @if ($porOtros || $mio)
                                    @if (! $f->hora_entrada && $f->justificada === null)
                                        <form method="post" action="{{ route('personal.asistencia.marcar') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="accion" value="entrada">
                                            <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                                            <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                                            <button class="btn btn-sm btn-oro"><i class="bi bi-box-arrow-in-right"></i> Entrada</button>
                                        </form>
                                    @elseif ($f->hora_entrada && ! $f->hora_salida)
                                        <form method="post" action="{{ route('personal.asistencia.marcar') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="accion" value="salida">
                                            <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                                            <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                                            <button class="btn btn-sm btn-oro"><i class="bi bi-box-arrow-right"></i> Salida</button>
                                        </form>
                                    @endif

                                    @if ($porOtros)
                                        <button class="btn btn-sm btn-outline-neutro" title="Marcar falta"
                                                data-bs-toggle="modal" data-bs-target="#modalFalta{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                            <i class="bi bi-person-x"></i></button>

                                        @if ($f->id_asistencia)
                                            <form method="post" action="{{ route('personal.asistencia.marcar') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="accion" value="limpiar">
                                                <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                                                <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                                                <input type="hidden" name="fecha" value="{{ $fecha }}">
                                                <button class="btn btn-sm btn-outline-neutro" title="Borrar el registro"
                                                        data-confirmar="¿Borrar lo registrado de {{ $f->profesional }} para ese turno?">
                                                    <i class="bi bi-eraser"></i></button>
                                            </form>
                                        @endif
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="spg-vacio">
                                    <i class="bi bi-calendar-check"></i>
                                    <div class="t">Ese día no trabaja nadie.</div>
                                    <div class="d">
                                        Depende de los turnos asignados. Si falta alguien, revisá su ficha
                                        en Usuarios o el turno en Turnos.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Marcar falta: con permiso pide el motivo, que es lo que la justifica --}}
    @if ($porOtros)
        @foreach ($filas as $f)
            <div class="modal fade" id="modalFalta{{ $f->id_usuario }}_{{ $f->id_turno }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('personal.asistencia.marcar') }}">
                            @csrf
                            <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                            <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">
                                    Falta de {{ $f->profesional }} — {{ fecha($fecha, 'd/m/Y') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label" for="mot{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                    Motivo (obligatorio si es con permiso)</label>
                                <input class="form-control" id="mot{{ $f->id_usuario }}_{{ $f->id_turno }}"
                                       name="motivo_ausencia" maxlength="150">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-outline-neutro" name="accion" value="falta_sin">Sin aviso</button>
                                <button class="btn btn-oro" name="accion" value="falta_con">Con permiso</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @if ($rows)
        <div class="spg-panel mt-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Últimos registros</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Fecha</th><th>Profesional</th><th>Turno</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td>{{ fecha($r->fecha, 'd/m/Y') }}</td>
                                <td>{{ $r->profesional }}</td>
                                <td class="text-muted-warm">{{ $r->turno }}</td>
                                <td>{{ $r->hora_entrada ? substr((string) $r->hora_entrada, 0, 5) : '—' }}</td>
                                <td>{{ $r->hora_salida ? substr((string) $r->hora_salida, 0, 5) : '—' }}</td>
                                <td>
                                    @if ($r->justificada === null)
                                        <span class="badge-estado e-ok">Presente</span>
                                    @elseif ((int) $r->justificada === 1)
                                        <span class="badge-estado e-warn">Con permiso</span>
                                    @else
                                        <span class="badge-estado e-no">Sin aviso</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
