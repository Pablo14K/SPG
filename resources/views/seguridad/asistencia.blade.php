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
            <a class="btn btn-sm btn-outline-neutro" href="{{ route('seguridad.asistencia') }}">Hoy</a>
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
                                    · tolerancia {{ (int) ($f->flexibilidad_entrada_min ?? 15) }} min
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
                                    <span class="badge-estado e-warn">
                                        {{ str_starts_with((string) ($f->observaciones ?? ''), 'Llegada tardía justificada:')
                                            ? 'Llegada tardía justificada' : 'Falta con permiso' }}</span>
                                    <div class="text-muted-warm" style="font-size:.72rem">{{ $f->motivo_ausencia }}</div>
                                @elseif ((int) $f->justificada === 0 && $f->id_asistencia)
                                    <span class="badge-estado e-no">Falta sin aviso</span>
                                    @if ($f->motivo_ausencia)
                                        <div class="text-muted-warm" style="font-size:.72rem">{{ $f->motivo_ausencia }}</div>
                                    @endif
                                @else
                                    <span class="badge-estado e-muted">Sin fichar</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                @php
                                    $mio = (int) $f->id_usuario === $yo;
                                    // Un día que ya pasó no se ficha: se corrige la planilla, y ahí
                                    // la hora la pone quien corrige. La del reloj es de otro día.
                                    $corrige = $fecha < $hoy;
                                    // **Pasada la franja, el botón no se ofrece.** La regla
                                    // ya la hacía cumplir el servidor, pero la pantalla lo
                                    // mostraba igual y el rechazo llegaba después de
                                    // apretarlo: un botón que no puede hacer nada promete
                                    // algo que no cumple. Con un día anterior se sigue
                                    // pudiendo corregir la planilla, que es otra cosa.
                                    $cerrado = ! $corrige && ! empty($f->fuera);
                                    $entradaTardiaJustificada = ! $f->hora_entrada
                                        && (int) ($f->justificada ?? -1) === 1
                                        && str_starts_with((string) ($f->observaciones ?? ''), 'Llegada tardía justificada:');
                                @endphp
                                @if ($cerrado)
                                    <span class="text-muted-warm" style="font-size:.78rem"
                                          title="{{ $f->fuera }}">
                                        <i class="bi bi-clock-history"></i> fuera de horario</span>
                                @endif
                                @if ($porOtros || $mio)
                                    @if (! $cerrado && ! $f->hora_entrada
                                         && ($f->justificada === null || $entradaTardiaJustificada))
                                        <form method="post" action="{{ route('seguridad.asistencia.marcar') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="accion" value="entrada">
                                            <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                                            <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                                            @if ($corrige)
                                                <input type="time" name="hora" class="form-control form-control-sm d-inline-block"
                                                       style="width:105px" required
                                                       min="{{ substr((string) $f->hora_inicio, 0, 5) }}"
                                                       max="{{ substr((string) $f->hora_fin, 0, 5) }}"
                                                       value="{{ substr((string) $f->hora_inicio, 0, 5) }}"
                                                       title="Hora real de entrada de ese día">
                                            @endif
                                            <button class="btn btn-sm btn-oro"><i class="bi bi-box-arrow-in-right"></i>
                                                {{ $entradaTardiaJustificada ? 'Entrada justificada' : 'Entrada' }}</button>
                                        </form>
                                    @elseif (! $cerrado && $f->hora_entrada && ! $f->hora_salida)
                                        <form method="post" action="{{ route('seguridad.asistencia.marcar') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="accion" value="salida">
                                            <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                                            <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                                            @if ($corrige)
                                                <input type="time" name="hora" class="form-control form-control-sm d-inline-block"
                                                       style="width:105px" required
                                                       min="{{ substr((string) $f->hora_entrada, 0, 5) }}"
                                                       max="{{ substr((string) $f->hora_fin, 0, 5) }}"
                                                       value="{{ substr((string) $f->hora_fin, 0, 5) }}"
                                                       title="Hora real de salida de ese día">
                                            @endif
                                            <button class="btn btn-sm btn-oro"><i class="bi bi-box-arrow-right"></i> Salida</button>
                                        </form>
                                    @endif

                                    @if ($porOtros)
                                        {{-- **Con nombre, no sólo el ícono.** Eran dos
                                             botones neutros seguidos —un monigote y una
                                             goma— y había que pasar el mouse por encima
                                             para saber cuál borraba. --}}
                                        <button class="btn btn-sm btn-outline-neutro" title="Registrar que no vino"
                                                data-bs-toggle="modal" data-bs-target="#modalFalta{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                            <i class="bi bi-person-x"></i> Falta</button>

                                        @if ($f->id_asistencia)
                                            <form method="post" action="{{ route('seguridad.asistencia.marcar') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="accion" value="limpiar">
                                                <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                                                <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                                                <input type="hidden" name="fecha" value="{{ $fecha }}">
                                                <button class="btn btn-sm btn-outline-neutro" title="Borrar lo registrado y dejar el turno como si nada"
                                                        data-confirmar="¿Borrar lo registrado de {{ $f->profesional }} para ese turno?">
                                                    <i class="bi bi-eraser txt-no"></i> Borrar</button>
                                            </form>
                                        @endif
                                    @endif

                                    @if (! $f->hora_entrada && $f->id_asistencia
                                         && (int) ($f->justificada ?? -1) === 0)
                                        <button class="btn btn-sm btn-outline-neutro" title="Darle el permiso y registrar por qué"
                                                data-bs-toggle="modal" data-bs-target="#modalJustificar{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                            <i class="bi bi-chat-square-text"></i> Justificar</button>
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

    {{-- Marcar falta: constatar que no vino. El permiso se da después. --}}
    @if ($porOtros)
        @foreach ($filas as $f)
            <div class="modal fade" id="modalFalta{{ $f->id_usuario }}_{{ $f->id_turno }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('seguridad.asistencia.marcar') }}">
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
                                {{-- **Marcar es constatar, no decidir.** El servidor ya
                                     trabaja así desde la 7.81.0 y la pantalla se había
                                     quedado con los dos botones viejos: obligaban a
                                     resolver si hubo permiso **en el momento de marcar**,
                                     que es justo cuando todavía no se sabe por qué no
                                     vino. Entra como falta sin aviso, y el permiso se da
                                     después con «Justificar», cuando la persona explica. --}}
                                <p class="text-muted-warm" style="font-size:.84rem">
                                    Se registra que <strong>{{ $f->profesional }}</strong> no vino
                                    a ese turno.
                                </p>
                                <label class="form-label" for="mot{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                    Observación <span class="text-muted-warm">(opcional)</span></label>
                                <textarea class="form-control" id="mot{{ $f->id_usuario }}_{{ $f->id_turno }}"
                                          name="motivo_ausencia" maxlength="150" rows="2"
                                          minlength="10"
                                          placeholder="Lo que se sepa hasta ahora — se puede dejar vacío"></textarea>
                                {{-- **Si se escribe, que diga algo.** «ok» o un punto ocupan
                                     el lugar de una explicación sin serlo, y esto es lo que
                                     va a leer quien revise la planilla dentro de tres meses.
                                     Vacío se admite: marcar una falta no obliga a inventar
                                     un motivo. El servidor lo vuelve a comprobar. --}}
                                <div class="form-text">Si escribís algo, que sean al menos 10 caracteres.</div>

                                {{-- **Escribir el motivo NO da el permiso, y eso hay que
                                     decirlo acá.** El campo se llama «Observación» y se lee
                                     como el motivo de la falta, así que quien escribía
                                     «avisó que estaba con fiebre» esperaba una falta
                                     justificada y leía después «sin permiso»: el motivo
                                     parecía ignorado.

                                     Siguen siendo dos cosas —constatar y justificar— y el
                                     camino de dos pasos se conserva: por defecto entra sin
                                     aviso. Lo que se agrega es que quien YA lo sabe lo pueda
                                     decir de una, en vez de marcar y volver a entrar. --}}
                                @if (\App\Servicios\Permisos::esAdmin())
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               name="con_permiso"
                                               id="perm{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                        <label class="form-check-label"
                                               for="perm{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                            Esta falta <strong>tiene permiso</strong>
                                        </label>
                                        <div class="form-text">
                                            Sin marcar entra como <strong>falta sin aviso</strong>, que es lo
                                            normal: recién cuando la persona explica se decide si corresponde
                                            el permiso, y eso se hace después con «Justificar».
                                            Marcándola, el motivo pasa a ser obligatorio.
                                        </div>
                                    </div>
                                @else
                                    <div class="form-text mt-3">
                                        Entra como <strong>falta sin aviso</strong>. Dar el permiso es una
                                        decisión sobre el sueldo de alguien, así que lo hace el
                                        Administrador desde «Justificar».
                                    </div>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro" name="accion" value="falta_sin">
                                    <i class="bi bi-person-x"></i> Marcar la falta</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @foreach ($filas as $f)
        @php $mio = (int) $f->id_usuario === $yo; @endphp
        @if (\App\Servicios\Permisos::esAdmin() && ! $f->hora_entrada && $f->id_asistencia
             && (int) ($f->justificada ?? -1) === 0)
            <div class="modal fade" id="modalJustificar{{ $f->id_usuario }}_{{ $f->id_turno }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="{{ route('seguridad.asistencia.marcar') }}">
                            @csrf
                            <input type="hidden" name="accion" value="justificar">
                            <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                            <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                            <input type="hidden" name="fecha" value="{{ $fecha }}">
                            <div class="modal-header">
                                <h5 class="modal-title" style="font-size:1rem">Justificar la falta de {{ $f->profesional }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted-warm" style="font-size:.84rem">
                                    Queda como falta <strong>con permiso</strong>, así que no se le
                                    descuenta. {{ $f->profesional }} puede además marcar la entrada
                                    después de la tolerancia. El motivo queda registrado.
                                </p>
                                {{-- **Al menos diez caracteres.** «ok», «sí» o un
                                     punto no explican nada, y esto es lo único que
                                     queda escrito de por qué esa falta no se
                                     descuenta: el que lo lea dentro de tres meses
                                     tiene que poder entenderlo. El servidor lo
                                     vuelve a comprobar. --}}
                                <label class="form-label" for="just{{ $f->id_usuario }}_{{ $f->id_turno }}">Motivo *</label>
                                <textarea class="form-control" id="just{{ $f->id_usuario }}_{{ $f->id_turno }}"
                                          name="motivo_ausencia" maxlength="200" rows="2"
                                          minlength="10" required
                                          placeholder="Por qué se le da el permiso"></textarea>
                                <div class="form-text">Al menos 10 caracteres: es lo único que explica la falta.</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Justificar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    {{-- **Los últimos registros, con filtros.** Eran sesenta filas fijas: para
         saber si alguien faltó el mes pasado había que recorrerlas a ojo, y a
         los seis meses de operación esa tabla deja de decir nada. El panel se
         dibuja aunque no haya filas, que si no el filtro que no encuentra nada
         desaparece junto con la respuesta. --}}
    <div class="spg-panel mt-3">
        <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Últimos registros</h2>

        <x-filtros :f="$fa" />

        @if ($rows)
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
        @else
            <div class="spg-vacio">
                <i class="bi bi-clock-history"></i>
                <div class="t">No hay registros con esos filtros</div>
                <div class="d">Probá con otro rango de fechas o sacando algún filtro.</div>
            </div>
        @endif
    </div>
@endsection
