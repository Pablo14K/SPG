@extends('layout.app')

@section('titulo', 'Roles')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="Quién puede entrar a qué. <strong>Ningún módulo es todo o nada</strong>: son 28 permisos, no 8. Quien registra la atención no tiene por qué agendar, y quien cobra no tiene por qué anular una liquidación." />

    <div class="alert alert-warning">
        Ojo con <strong>Configuración → Roles</strong>: quien tenga este permiso puede editar la matriz,
        <strong>incluida la suya</strong>. La creación de cuentas, en cambio, es siempre del Administrador.
    </div>

    {{-- Un bloque por rol, con su casilla maestra por módulo --}}
    <form method="post" action="{{ route('configuracion.permisos.guardar') }}">
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
                            <div class="col-md-4 col-lg-3">
                                <div style="border:1px solid var(--gris-calido);border-radius:8px;padding:.5rem .7rem">
                                    <div style="font-weight:500;font-size:.85rem">{{ $m['etiqueta'] }}</div>
                                    @if ($m['hijos'])
                                        @foreach ($m['hijos'] as $clave => $etiqueta)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="perm[{{ $rol->id_rol }}][{{ $clave }}]" value="1"
                                                       id="p{{ $rol->id_rol }}_{{ str_replace('.', '_', $clave) }}"
                                                       @checked(Permisos::marcado($perm[$rol->id_rol] ?? [], $clave))>
                                                <label class="form-check-label" style="font-size:.8rem"
                                                       for="p{{ $rol->id_rol }}_{{ str_replace('.', '_', $clave) }}">
                                                    {{ $etiqueta }}</label>
                                            </div>
                                        @endforeach
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
                <form method="post" action="{{ route('configuracion.rol.crear') }}">
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
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-trash"></i> Eliminar un rol</h2>
                <p class="text-muted-warm" style="font-size:.82rem">
                    No se puede eliminar un rol que tenga usuarios, ni el Administrador ni el Cliente:
                    esos dos los referencia el código.
                </p>
                @foreach ($roles as $rol)
                    @continue (in_array((int) $rol->id_rol, $protegidos, true))
                    <form method="post" action="{{ route('configuracion.rol.borrar') }}"
                          class="d-flex justify-content-between align-items-center py-1">
                        @csrf
                        <input type="hidden" name="id_rol" value="{{ $rol->id_rol }}">
                        <span>{{ $rol->nombre }}
                            <span class="text-muted-warm" style="font-size:.8rem">
                                · {{ (int) $rol->usuarios }} usuario(s)</span></span>
                        <button class="btn btn-sm btn-outline-neutro"
                                data-confirmar="¿Eliminar el rol «{{ $rol->nombre }}»?">
                            <i class="bi bi-trash"></i></button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
@endsection
