@extends('layout.app')

@section('titulo', $u ? 'Editar usuario' : 'Nuevo usuario')

@section('contenido')
    @php
        $id = $u->id_usuario ?? 0;
        // **La misma ficha, con el foco donde corresponde.**
        //
        // «Usuarios» y «Profesionales» abren esta pantalla y son dos trabajos
        // distintos: uno administra la CUENTA —quién entra, con qué clave y
        // con qué rol— y el otro los DATOS DE LA PERSONA y lo que hace en el
        // salón. Duplicar el formulario los desfasa: se cambia un campo en uno
        // y el otro queda viejo, que es el error que este proyecto ya se hizo
        // varias veces.
        //
        // Por eso es una sola ficha con pestañas, y lo que cambia es cuál abre
        // y cómo se titula.
        $desdePersonal = request()->query('desde') === 'personal';
        $vuelve = $desdePersonal
            ? ['ruta' => route('seguridad.personal.index'), 't' => 'Personal']
            : ['ruta' => route('seguridad.usuarios'), 't' => 'Usuarios'];
    @endphp

    <div class="spg-page-head">
        <a class="spg-back" href="{{ $vuelve['ruta'] }}"><i class="bi bi-arrow-left"></i> {{ $vuelve['t'] }}</a>
        <h1 class="mt-1">
            @if ($desdePersonal)
                {{ $id ? 'Ficha de ' . ($u->nombre ?? 'la profesional') : 'Nueva profesional' }}
            @else
                {{ $id ? 'Editar usuario' : 'Nuevo usuario' }}
            @endif
        </h1>
        <div class="sub">
            {{ $desdePersonal
                ? 'Los datos de la persona, qué servicios hace y en qué turnos trabaja.'
                : 'La cuenta con la que entra al sistema: usuario, contraseña y rol.' }}
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="spg-panel">
                <form method="post" action="{{ route('seguridad.usuario.guardar') }}" id="formUsuario">
                    @csrf
                    <input type="hidden" name="id_usuario" value="{{ $id }}">
                    <input type="hidden" name="desde" value="{{ request()->query('desde', '') }}">

                    {{-- **Las tres secciones se ven juntas, y las pestañas
                         salieron por eso.**

                         No era una preferencia: **crear un usuario no
                         funcionaba**. Los campos obligatorios de una pestaña
                         cerrada están en `display:none`, y el navegador se
                         niega a enviar un formulario con un `required` que no
                         puede enfocar — **sin decir nada**. Se apretaba Guardar
                         y no pasaba absolutamente nada; el único rastro quedaba
                         en la consola («An invalid form control … is not
                         focusable»), que nadie mira.

                         Es el patrón de siempre de este proyecto: algo se
                         apaga en silencio y se descubre cuando alguien intenta
                         usarlo. Con las tres a la vista, el navegador puede
                         enfocar el campo que falta y el rechazo se ve.

                         Se guardan juntas, como antes: un solo POST y un solo
                         botón al pie.

                         **Los datos de la persona salieron de acá** en la
                         7.68.0: viven en `persona` y se cargan en Personal →
                         Profesionales. Esta pantalla administra la CUENTA —
                         usuario, contraseña, rol— y lo que cuelga de ella:
                         sucursales, servicios y turnos. --}}
                    <div>
                    <div id="fmPersona">
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-person"></i> ¿A quién?</h2>

                    {{-- **La persona se ELIGE, no se tipea.**

                         Sus datos —nombre, cédula, teléfono, correo, dirección—
                         viven en `persona` y se cargan en **Personal →
                         Profesionales**. Pedirlos otra vez acá era pedir dos
                         veces el mismo dato y arriesgarse a que quedaran
                         distintos, que es exactamente lo que la regla número
                         dos prohíbe.

                         Se ofrecen las personas del personal que todavía NO
                         tienen cuenta: una persona, una cuenta. --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label" for="id_persona">Persona *</label>
                            @if ($personas)
                                <input class="form-control mb-1" data-filtra="#id_persona"
                                       placeholder="Buscar por nombre o cédula...">
                                <select class="form-select" id="id_persona" name="id_persona" required>
                                    <option value="">— Elegí a quién —</option>
                                    @foreach ($personas as $pp)
                                        <option value="{{ $pp->id_persona }}"
                                            @selected((int) old('id_persona', $u->id_persona ?? $personaSug) === (int) $pp->id_persona)>
                                            {{ $pp->nombre }}@if ($pp->cedula) · {{ $pp->cedula }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                {{-- **Sin nadie a quien darle la cuenta se dice, y se
                                     nombra el camino.** Un combo vacío no explica nada:
                                     es el criterio de IN-06. --}}
                                <div class="alert alert-warning py-2 mb-0" style="font-size:.86rem">
                                    Todas las personas cargadas ya tienen cuenta.
                                    <a href="{{ route('seguridad.profesional_form') }}">Cargá al profesional primero</a>
                                    y después volvé acá.
                                </div>
                            @endif
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <a class="btn btn-rapido w-100" href="{{ route('seguridad.profesional_form') }}">
                                <i class="bi bi-person-plus"></i> Cargar una persona</a>
                        </div>
                    </div>

                    </div>{{-- /fmPersona --}}

                    <div id="fmCuenta">
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-key"></i> Cuenta y acceso</h2>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="username">Usuario *</label>
                            <input class="form-control" id="username" name="username" required
                                   value="{{ old('username', $u->username ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">
                                Contraseña {{ $id ? '' : '*' }}
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                   {{ $id ? '' : 'required' }} minlength="6"
                                   autocomplete="new-password"
                                   placeholder="{{ $id ? 'Dejala vacía para no cambiarla' : 'Al menos 6 caracteres' }}">
                            {{-- **Vacío NO quiere decir «sin contraseña».** El
                                 campo nunca trae la que hay cargada —eso sería
                                 mandarla al navegador en cada carga de la
                                 pantalla— así que vacío sólo significa «no la
                                 toques»; el servidor la deja como está. --}}
                            <div class="form-text">
                                @if ($id)
                                    Vacío = <strong>no se cambia</strong>. Por seguridad no traemos la
                                    que tiene cargada. Si escribís una nueva, mínimo 6 caracteres.
                                @else
                                    Mínimo 6 caracteres.
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="id_rol">Rol *</label>
                            <select class="form-select" id="id_rol" name="id_rol" required>
                                @foreach ($roles as $r)
                                    <option value="{{ $r->id_rol }}"
                                        @selected((int) old('id_rol', $u->id_rol ?? 0) === (int) $r->id_rol)>
                                        {{ $r->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    </div>{{-- /fmCuenta --}}

                    <div id="fmTrabajo">
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-shop"></i> Sucursales donde trabaja</h2>
                    {{-- **Una sola pregunta, no dos.** Acá había además un selector de
                         «Sucursal principal» que repetía lo mismo con otras palabras: en
                         cuál está HOY lo decide la sesión al entrar, no la ficha. Lo que
                         queda de `usuario.id_sucursal` es la red para las cuentas viejas
                         sin asignaciones y para lo que agenda sin sesión, así que se
                         deduce de la primera marcada en vez de preguntarse aparte. --}}
                    <p class="text-muted-warm mb-2" style="font-size:.8rem">
                        Marcá todos los locales en los que atiende. Al entrar elige en cuál
                        está ese día, y desde ahí ve la agenda, la caja y el stock de ese local.
                    </p>
                    <div class="mb-3">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="gSucursalesTodo" data-marca-todo="#gSucursales">
                            <label class="form-check-label fw-semibold" for="gSucursalesTodo">Todas</label>
                        </div>
                        <div class="d-flex gap-3 flex-wrap" id="gSucursales">
                            @foreach ($sucursales as $s)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sucursales[]"
                                           value="{{ $s->id_sucursal }}" id="suc{{ $s->id_sucursal }}"
                                           @checked(in_array((int) $s->id_sucursal, old('sucursales', $misSuc), false))>
                                    <label class="form-check-label" for="suc{{ $s->id_sucursal }}">{{ $s->nombre }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- **Los servicios que hace salieron de acá** (7.68.0): son
                         de la PERSONA y no de su cuenta, así que se cargan en su
                         ficha de profesional. Una manicurista que no entra a la
                         computadora hace manicura igual.

                         Lo que queda acá es lo que de verdad cuelga de la cuenta:
                         sucursales a las que entra y turnos que trabaja. --}}

                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-clock"></i> Turnos que trabaja</h2>
                    <p class="text-muted-warm mb-2" style="font-size:.8rem">
                        <strong>Sin turno asignado no aparece en la agenda</strong>: el sistema no sabría
                        cuándo atiende. El mismo turno lo puede compartir todo el equipo.
                    </p>
                    <div class="mb-3">
                        @if (count($turnos) > 1)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="gTurnosTodo" data-marca-todo="#gTurnos">
                                <label class="form-check-label fw-semibold" for="gTurnosTodo">Todos</label>
                            </div>
                        @endif
                        <div id="gTurnos">
                        @forelse ($turnos as $t)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="turnos[]"
                                       value="{{ $t->id_turno }}" id="tur{{ $t->id_turno }}"
                                       @checked(in_array((int) $t->id_turno, old('turnos', $misTurnos), false))>
                                <label class="form-check-label" for="tur{{ $t->id_turno }}">
                                    {{ $t->nombre }}
                                    <span class="text-muted-warm">
                                        · {{ substr((string) $t->hora_inicio, 0, 5) }} a {{ substr((string) $t->hora_fin, 0, 5) }}
                                        · {{ mb_strtolower($t->dias_texto) }}
                                        · {{ $t->sucursal }}
                                    </span>
                                </label>
                            </div>
                        @empty
                            <p class="txt-no" style="font-size:.85rem">
                                Todavía no hay turnos cargados. Creá uno acá al costado.
                            </p>
                        @endforelse
                        </div>
                    </div>

                    </div>{{-- /fmTrabajo --}}
                    </div>

                    {{-- Un solo botón, al pie de las tres secciones: se guardan
                         juntas y siempre se guardaron juntas. --}}
                    <div class="d-flex gap-2 pt-2 border-top">
                        <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                        <a class="btn btn-outline-neutro" href="{{ $vuelve['ruta'] }}">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Altas rápidas: crear un turno o una sucursal sin salir de la
                 ficha. Sin esto había que ir a otra pantalla, crearlo y volver
                 a cargar todo de cero. --}}
            <div class="spg-panel mb-3">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock"></i> Turno nuevo</h2>
                {{-- data-borrador: al enviar, app.js le adjunta lo que haya
                     cargado en la ficha, y el controlador se lo devuelve. --}}
                <form method="post" action="{{ route('seguridad.turno.rapido') }}"
                      data-borrador="#formUsuario">
                    @csrf
                    <input type="hidden" name="id_usuario" value="{{ $id }}">
                    <div class="mb-2">
                        <label class="form-label" for="tr_nombre">Nombre *</label>
                        <input class="form-control form-control-sm" id="tr_nombre" name="nombre" required maxlength="60">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="tr_ini">Desde</label>
                            <input type="time" class="form-control form-control-sm" id="tr_ini"
                                   name="hora_inicio" value="08:00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="tr_fin">Hasta</label>
                            <input type="time" class="form-control form-control-sm" id="tr_fin"
                                   name="hora_fin" value="12:00" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="tr_suc">Sucursal</label>
                        <select class="form-select form-select-sm" id="tr_suc" name="id_sucursal" required>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s->id_sucursal }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Días</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="gDiasRapidoTodo" data-marca-todo="#gDiasRapido">
                            <label class="form-check-label fw-semibold" for="gDiasRapidoTodo" style="font-size:.8rem">Todos</label>
                        </div>
                        <div class="d-flex gap-1 flex-wrap" id="gDiasRapido">
                            @foreach ($dias as $n => $nombreDia)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dias[]" value="{{ $n }}"
                                           id="trd{{ $n }}" @checked($n <= 6)>
                                    <label class="form-check-label" for="trd{{ $n }}"
                                           style="font-size:.8rem">{{ mb_substr($nombreDia, 0, 3) }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button class="btn btn-rapido w-100"><i class="bi bi-plus-lg"></i> Crear turno</button>
                </form>
            </div>

            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-shop"></i> Sucursal nueva</h2>
                <form method="post" action="{{ route('seguridad.sucursal.rapida') }}"
                      data-borrador="#formUsuario">
                    @csrf
                    <input type="hidden" name="id_usuario" value="{{ $id }}">
                    <div class="mb-2">
                        <label class="form-label" for="sr_nombre">Nombre *</label>
                        <input class="form-control form-control-sm" id="sr_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sr_ciudad">Ciudad</label>
                        <input class="form-control form-control-sm" id="sr_ciudad" name="ciudad" value="Luque" list="ciudadesPy">
                    </div>
                    <button class="btn btn-rapido w-100"><i class="bi bi-plus-lg"></i> Crear sucursal</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@once
    {{-- **Ciudades sugeridas.** Va como `<datalist>` y no como `<select>` a
         propósito: sugiere las de siempre y deja escribir cualquier otra. Un
         selector cerrado obligaría a mantener el padrón entero del país para
         que alguien pueda poner su localidad. --}}
    <datalist id="ciudadesPy">
        @foreach (config('spg.ciudades', []) as $ciudad)
            <option value="{{ $ciudad }}"></option>
        @endforeach
    </datalist>
@endonce
