@extends('layout.app')

@section('titulo', 'Roles')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="Quién puede entrar a qué. <strong>Ningún módulo es todo o nada</strong>: son 28 permisos, no 8. Quien registra la atención no tiene por qué agendar, y quien cobra no tiene por qué anular una liquidación." />

    {{-- El aviso es SÓLO para quien puede dejarse afuera con lo que está por
         guardar. Antes se le decía a todo el mundo «quien tenga este permiso
         puede editar la matriz»: a quien ya está leyendo esta pantalla eso no
         le informa nada —lo tiene, por eso entró—, y al Administrador menos
         todavía, porque su fila ni siquiera es editable. --}}
    @if (! Permisos::esAdmin() && $miRol)
        @php $miNombre = collect($roles)->firstWhere('id_rol', $miRol)?->nombre; @endphp
        <div class="alert alert-warning">
            Tu propio rol{{ $miNombre ? ' (' . $miNombre . ')' : '' }} se edita en esta misma pantalla.
            Si le destildás <strong>Seguridad → Roles</strong> y guardás,
            <strong>dejás de poder entrar acá</strong> y te lo va a tener que devolver un Administrador.
        </div>
    @endif

    {{-- Un bloque por rol, con su casilla maestra por módulo --}}
    <form method="post" action="{{ route('seguridad.permisos.guardar') }}">
        @csrf

        @foreach ($roles as $rol)
            @php
                $esAdmin = (int) $rol->id_rol === $admin;
                $editable = (int) $rol->es_personal === 1 && ! $esAdmin;
            @endphp

            <div class="spg-panel mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <h2 style="font-size:1rem;font-weight:500;margin:0">
                            {{ $rol->nombre }}
                            @if ($esAdmin)<span class="spg-rol-chip">superadministrador</span>@endif
                            @if (! $rol->es_personal)<span class="badge-estado e-muted">portal</span>@endif
                            @if (! $rol->activo)<span class="badge-estado e-no">inactivo</span>@endif
                        </h2>
                        <div class="text-muted-warm" style="font-size:.8rem">
                            {{ $rol->descripcion ?: 'Sin descripción' }}
                            · {{ (int) $rol->usuarios }} usuario(s)
                            · alcance {{ (int) $rol->alcance }} de {{ $totalClaves }}
                        </div>
                    </div>
                </div>

                @if ($esAdmin)
                    <p class="text-muted-warm mb-0" style="font-size:.85rem">
                        El Administrador ve todo por definición: no tiene casillas que marcar.
                    </p>
                @elseif (! $rol->es_personal)
                    <p class="text-muted-warm mb-0" style="font-size:.85rem">
                        Es un rol del portal de la clienta: no entra al panel de gestión.
                    </p>
                @else
                    <div class="row g-2">
                        @foreach ($matriz as $m)
                            {{-- Seguridad tiene ocho submódulos: ocupa el doble
                                 de ancho y sus casillas van en dos columnas, o
                                 la fila queda con una torre al lado de cajas
                                 de cuatro renglones. --}}
                            <div class="{{ count($m['hijos']) > 6 ? 'col-md-8 col-lg-6' : 'col-md-4 col-lg-3' }}">
                                <div class="h-100" style="border:1px solid var(--gris-calido);border-radius:8px;padding:.5rem .7rem">
                                    <div style="font-weight:500;font-size:.85rem">{{ $m['etiqueta'] }}</div>
                                    @if ($m['hijos'])
                                        <div style="{{ count($m['hijos']) > 6 ? 'columns:2;column-gap:1rem' : '' }}">
                                        @foreach ($m['hijos'] as $clave => $etiqueta)
                                            <div class="form-check" style="break-inside:avoid">
                                                <input class="form-check-input" type="checkbox"
                                                       name="perm[{{ $rol->id_rol }}][{{ $clave }}]" value="1"
                                                       id="p{{ $rol->id_rol }}_{{ str_replace('.', '_', $clave) }}"
                                                       @checked(Permisos::marcado($perm[$rol->id_rol] ?? [], $clave))>
                                                <label class="form-check-label" style="font-size:.8rem"
                                                       for="p{{ $rol->id_rol }}_{{ str_replace('.', '_', $clave) }}">
                                                    {{ $etiqueta }}</label>
                                            </div>
                                        @endforeach
                                        </div>
                                    @else
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="perm[{{ $rol->id_rol }}][{{ $m['clave'] }}]" value="1"
                                                   id="p{{ $rol->id_rol }}_{{ $m['clave'] }}"
                                                   @checked(Permisos::marcado($perm[$rol->id_rol] ?? [], $m['clave']))>
                                            <label class="form-check-label" style="font-size:.8rem"
                                                   for="p{{ $rol->id_rol }}_{{ $m['clave'] }}">Acceso</label>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        <div class="mb-4">
            <button class="btn btn-oro"
                    data-confirmar="Vas a guardar los permisos de todos los roles. ¿Confirmás?">
                <i class="bi bi-check-lg"></i> Guardar permisos
            </button>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-plus-lg"></i> Rol nuevo</h2>
                <form method="post" action="{{ route('seguridad.rol.crear') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label" for="rn_nombre">Nombre *</label>
                        <input class="form-control form-control-sm" id="rn_nombre" name="nombre" required maxlength="60">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="rn_desc">Descripción</label>
                        <input class="form-control form-control-sm" id="rn_desc" name="descripcion" maxlength="150">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="es_personal" value="1"
                               id="rn_personal" checked>
                        <label class="form-check-label" for="rn_personal">
                            Es personal del salón (entra al panel de gestión)
                        </label>
                    </div>
                    <button class="btn btn-rapido"><i class="bi bi-plus-lg"></i> Crear rol</button>
                    <div class="form-text">Los permisos se marcan arriba, después de crearlo.</div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-pencil"></i> Roles del salón</h2>
                <p class="text-muted-warm" style="font-size:.82rem">
                    Acá se le cambia el nombre y la descripción a un rol. No se puede eliminar uno que
                    tenga usuarios, ni el Administrador ni el Cliente: esos dos los referencia el código.
                </p>
                @foreach ($roles as $rol)
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span>{{ $rol->nombre }}
                            <span class="text-muted-warm" style="font-size:.8rem">
                                · {{ (int) $rol->usuarios }} usuario(s)</span>
                            @if (! (int) $rol->activo)
                                <span class="badge-estado e-muted">inactivo</span>
                            @endif
                        </span>
                        <span style="white-space:nowrap">
                            <button class="btn btn-sm btn-outline-neutro" title="Editar el rol"
                                    data-bs-toggle="modal" data-bs-target="#modalRol{{ $rol->id_rol }}">
                                <i class="bi bi-pencil"></i></button>

                            @if (! in_array((int) $rol->id_rol, $protegidos, true))
                                <form method="post" action="{{ route('seguridad.rol.borrar') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_rol" value="{{ $rol->id_rol }}">
                                    <button class="btn btn-sm btn-outline-neutro" title="Eliminar el rol"
                                            data-confirmar="¿Eliminar el rol «{{ $rol->nombre }}»?">
                                        <i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Un modal de edición por rol.
         Los dos protegidos se pueden renombrar, pero no dejar de ser personal ni
         desactivarse: el código los referencia por id, y un Administrador
         inactivo deja el salón sin quién gestione las cuentas. --}}
    @foreach ($roles as $rol)
        <div class="modal fade" id="modalRol{{ $rol->id_rol }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('seguridad.rol.editar') }}">
                        @csrf
                        <input type="hidden" name="id_rol" value="{{ $rol->id_rol }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">
                                <i class="bi bi-pencil"></i> Editar «{{ $rol->nombre }}»
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label" for="re_nombre{{ $rol->id_rol }}">Nombre *</label>
                                <input class="form-control form-control-sm" id="re_nombre{{ $rol->id_rol }}"
                                       name="nombre" value="{{ $rol->nombre }}" required maxlength="60">
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="re_desc{{ $rol->id_rol }}">Descripción</label>
                                <input class="form-control form-control-sm" id="re_desc{{ $rol->id_rol }}"
                                       name="descripcion" value="{{ $rol->descripcion }}" maxlength="150">
                            </div>

                            @if (in_array((int) $rol->id_rol, $protegidos, true))
                                <p class="text-muted-warm mb-0" style="font-size:.78rem">
                                    Es un rol que el sistema referencia por su id: se le puede cambiar el
                                    nombre, pero no si es personal ni darlo de baja.
                                </p>
                            @else
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="es_personal" value="1"
                                           id="re_personal{{ $rol->id_rol }}" @checked((int) $rol->es_personal)>
                                    <label class="form-check-label" for="re_personal{{ $rol->id_rol }}">
                                        Es personal del salón (entra al panel de gestión)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="activo" value="1"
                                           id="re_activo{{ $rol->id_rol }}" @checked((int) $rol->activo)>
                                    <label class="form-check-label" for="re_activo{{ $rol->id_rol }}">Activo</label>
                                </div>
                                <p class="text-muted-warm mt-2 mb-0" style="font-size:.78rem">
                                    Si dejás de marcarlo como personal, pierde todos los módulos del panel.
                                </p>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
