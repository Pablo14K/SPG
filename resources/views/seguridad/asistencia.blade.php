@extends('layout.app')

@section('titulo', 'Asistencia')

{{-- **Se actualiza sola** (7.122.0): la profesional ficha desde su cuenta y
     quien administra tiene esta planilla abierta en otra computadora. Sin
     esto la seguía viendo «Sin fichar» hasta recargar. --}}
@section('vivo', 'asistencia')

@section('contenido')
    <x-encabezado sub="Quiénes trabajan hoy, según el turno que tienen asignado. <strong>No se escriben horarios a mano</strong>: se ficha con un botón y queda la hora del clic." />

    <div class="sgp-panel mb-3">
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

    {{-- **Cuántos vinieron, de un vistazo.** Con las filas sueltas había que
         contar badges para saber cómo venía el día. --}}
    @if ($filas)
        <div class="sgp-metrics sgp-metrics-compacto mb-3">
            <div class="sgp-metric">
                <div class="lbl">{{ $porOtros ? 'Turnos de hoy' : 'Tus turnos' }}</div>
                <div class="val">{{ count($filas) }}</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Presentes</div>
                <div class="val txt-ok">{{ $cuenta['presente'] }}</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Sin fichar</div>
                <div class="val">{{ $cuenta['sin_fichar'] }}</div>
            </div>
            <div class="sgp-metric">
                <div class="lbl">Faltas</div>
                <div class="val {{ $cuenta['falta'] ? 'txt-no' : '' }}">{{ $cuenta['falta'] }}</div>
                @if ($cuenta['permiso'])
                    <div class="sgp-metric-pie">+ {{ $cuenta['permiso'] }} con permiso</div>
                @endif
            </div>
        </div>
    @endif

    {{-- **Un bloque por TURNO, con el turno a la vista** (7.122.0). Era una sola
         tabla con el turno escondido detrás de «Detalle»: quien trabaja mañana y
         tarde aparecía dos veces con el mismo nombre, y la entrada marcada en un
         turno no se veía en la fila del otro — «en la vista de admin no cambió». --}}
    @forelse ($turnos as $t)
        <div class="sgp-panel mb-3 sgp-asis-turno">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <h2 class="sgp-form-titulo mb-0">
                    <i class="bi bi-clock"></i> {{ $t->nombre }}
                    <span class="text-muted-warm" style="font-size:.85rem;font-weight:400">
                        {{ substr((string) $t->hora_inicio, 0, 5) }} a {{ substr((string) $t->hora_fin, 0, 5) }}
                        · tolerancia {{ $t->tolerancia }} min</span>
                </h2>
                <div class="d-flex gap-1 flex-wrap" style="font-size:.78rem">
                    <span class="badge-estado e-ok">{{ $t->cuenta['presente'] }} presente{{ $t->cuenta['presente'] === 1 ? '' : 's' }}</span>
                    @if ($t->cuenta['sin_fichar'])
                        <span class="badge-estado e-muted">{{ $t->cuenta['sin_fichar'] }} sin fichar</span>
                    @endif
                    @if ($t->cuenta['falta'])
                        <span class="badge-estado e-no">{{ $t->cuenta['falta'] }} falta{{ $t->cuenta['falta'] === 1 ? '' : 's' }}</span>
                    @endif
                    @if ($t->cuenta['permiso'])
                        <span class="badge-estado e-warn">{{ $t->cuenta['permiso'] }} con permiso</span>
                    @endif
                </div>
            </div>

            <div class="table-responsive sgp-tabla-movil">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Profesional</th><th>Estado</th><th>Entrada</th><th>Salida</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($t->filas as $f)
                            @php
                                $mio = (int) $f->id_usuario === $yo;
                                // Un día que ya pasó no se ficha: se corrige la planilla, y ahí
                                // la hora la pone quien corrige. La del reloj es de otro día.
                                $corrige = $fecha < $hoy;
                                // **Pasada la franja, el botón no se ofrece.** La regla la hace
                                // cumplir el servidor; un botón que no puede hacer nada promete
                                // algo que no cumple.
                                $cerrado = ! $corrige && ! empty($f->fuera);
                                $entradaTardiaJustificada = $f->tardia_justificada;
                            @endphp
                            <tr>
                                <td class="sgp-movil-titulo" data-label="Profesional">
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <x-avatar :foto="$f->foto" :nombre="$f->pnombre" :apellido="$f->papellido" />
                                        <span>{{ $f->profesional }}@if ($mio) <span class="text-muted-warm" style="font-size:.78rem">(vos)</span>@endif</span>
                                    </span>
                                </td>
                                <td data-label="Estado">
                                    @switch ($f->estado)
                                        @case ('presente')
                                            <span class="badge-estado e-ok"><i class="bi bi-check2"></i>
                                                {{ $f->hora_salida ? 'Vino y ya salió' : 'Presente' }}</span>
                                            @if ($entradaTardiaJustificada || str_starts_with((string) ($f->observaciones ?? ''), 'Llegada tardía justificada:'))
                                                <div class="text-muted-warm" style="font-size:.72rem">con llegada tardía justificada</div>
                                            @endif
                                            @break
                                        @case ('permiso')
                                            <span class="badge-estado e-warn">
                                                {{ str_starts_with((string) ($f->observaciones ?? ''), 'Llegada tardía justificada:')
                                                    ? 'Llegada tardía justificada' : 'Falta con permiso' }}</span>
                                            @if ($f->motivo_ausencia)
                                                <div class="text-muted-warm" style="font-size:.72rem">{{ $f->motivo_ausencia }}</div>
                                            @endif
                                            @break
                                        @case ('falta')
                                            <span class="badge-estado e-no">Falta sin aviso</span>
                                            @if ($f->motivo_ausencia)
                                                <div class="text-muted-warm" style="font-size:.72rem">{{ $f->motivo_ausencia }}</div>
                                            @endif
                                            @break
                                        @default
                                            <span class="badge-estado e-muted">Sin fichar</span>
                                            @if ($cerrado)
                                                <div class="text-muted-warm" style="font-size:.72rem" title="{{ $f->fuera }}">
                                                    <i class="bi bi-clock-history"></i> fuera de horario</div>
                                            @endif
                                    @endswitch
                                </td>
                                <td data-label="Entrada" style="font-variant-numeric:tabular-nums">
                                    {{ $f->hora_entrada ? substr((string) $f->hora_entrada, 0, 5) : '—' }}</td>
                                <td data-label="Salida" style="font-variant-numeric:tabular-nums">
                                    {{ $f->hora_salida ? substr((string) $f->hora_salida, 0, 5) : '—' }}
                                    @if ((float) ($f->horas_extras ?? 0) > 0)
                                        <div class="text-muted-warm" style="font-size:.72rem">
                                            +{{ cant($f->horas_extras) }} h extra
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
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
                                                    {{ $entradaTardiaJustificada ? 'Entrada justificada' : 'Marcar entrada' }}</button>
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
                                                <button class="btn btn-sm btn-oro"><i class="bi bi-box-arrow-right"></i> Marcar salida</button>
                                            </form>
                                        @endif

                                        {{-- **Falta sólo donde todavía no hay entrada.** Sobre una
                                             fila que ya fichó, marcar la falta borraba la entrada;
                                             el servidor también lo rechaza. --}}
                                        @if ($porOtros && ! $f->hora_entrada && $f->estado === 'sin_fichar')
                                            <button class="btn btn-sm btn-outline-neutro" title="Registrar que no vino"
                                                    data-bs-toggle="modal" data-bs-target="#modalFalta{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                                <i class="bi bi-person-x"></i> Falta</button>
                                        @endif

                                        @if (\App\Servicios\Permisos::esAdmin() && ! $f->hora_entrada && $f->id_asistencia
                                             && (int) ($f->justificada ?? -1) === 0)
                                            <button class="btn btn-sm btn-outline-neutro" title="Darle el permiso y registrar por qué"
                                                    data-bs-toggle="modal" data-bs-target="#modalJustificar{{ $f->id_usuario }}_{{ $f->id_turno }}">
                                                <i class="bi bi-chat-square-text"></i> Justificar</button>
                                        @endif

                                        @if ($porOtros && $f->id_asistencia)
                                            <form method="post" action="{{ route('seguridad.asistencia.marcar') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="accion" value="limpiar">
                                                <input type="hidden" name="id_usuario" value="{{ $f->id_usuario }}">
                                                <input type="hidden" name="id_turno" value="{{ $f->id_turno }}">
                                                <input type="hidden" name="fecha" value="{{ $fecha }}">
                                                <button class="btn btn-sm btn-outline-neutro sgp-btn-ico" title="Borrar lo registrado"
                                                        data-confirmar="¿Borrar lo registrado de {{ $f->profesional }} en el {{ $t->nombre }}? El turno queda como si nada.">
                                                    <i class="bi bi-eraser txt-no"></i></button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="sgp-panel mb-3">
            <div class="sgp-vacio">
                <i class="bi bi-calendar-check"></i>
                <div class="t">Ese día no trabaja nadie en este local.</div>
                <div class="d">
                    Depende de los turnos asignados. Si falta alguien, revisá su ficha
                    en Usuarios o el turno en Turnos.
                </div>
            </div>
        </div>
    @endforelse

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
                                <x-ayuda>Si escribís algo, que sean al menos 10 caracteres.</x-ayuda>

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
                                        <x-ayuda>Sin marcar entra como <strong>falta sin aviso</strong>, que es lo normal: recién cuando la persona explica se decide si corresponde el permiso, y eso se hace después con «Justificar». Marcándola, el motivo pasa a ser obligatorio.</x-ayuda>
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
                                <label class="form-label" for="just{{ $f->id_usuario }}_{{ $f->id_turno }}">Motivo *</label><x-ayuda>Al menos 10 caracteres: es lo único que explica la falta.</x-ayuda>
                                <textarea class="form-control" id="just{{ $f->id_usuario }}_{{ $f->id_turno }}"
                                          name="motivo_ausencia" maxlength="200" rows="2"
                                          minlength="10" required
                                          placeholder="Por qué se le da el permiso"></textarea>
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
    <div class="sgp-panel mt-3">
        <h2 class="sgp-form-titulo mb-2"><i class="bi bi-clock-history"></i> Últimos registros</h2>

        <x-filtros :f="$fa" />

        @if ($rows)
            <div class="table-responsive sgp-tabla-movil">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Fecha</th><th>Turno</th><th>Profesional</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="sgp-movil-titulo" data-label="Fecha">{{ fecha($r->fecha, 'd/m/Y') }}</td>
                                {{-- El turno en su columna y no detrás de «Detalle»: con dos
                                     turnos el mismo día, sin él las dos filas se leen iguales. --}}
                                <td class="text-muted-warm" data-label="Turno">{{ $r->turno }}</td>
                                <td data-label="Profesional">{{ $r->profesional }}</td>
                                <td data-label="Entrada">{{ $r->hora_entrada ? substr((string) $r->hora_entrada, 0, 5) : '—' }}</td>
                                <td data-label="Salida">{{ $r->hora_salida ? substr((string) $r->hora_salida, 0, 5) : '—' }}</td>
                                <td data-label="Estado">
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
            <div class="sgp-vacio">
                <i class="bi bi-clock-history"></i>
                <div class="t">No hay registros con esos filtros</div>
                <div class="d">Probá con otro rango de fechas o sacando algún filtro.</div>
            </div>
        @endif
    </div>
@endsection
