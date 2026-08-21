@extends('layout.app')

@section('titulo', 'Sucursales')

@section('contenido')
    <x-encabezado
        sub="Los locales del salón. El RUC y la dirección de la sucursal son los que se imprimen en el comprobante."
        :accion="['ruta' => 'seguridad.sucursal_form', 't' => 'Nueva sucursal', 'ic' => 'plus-lg']" />

    {{-- **La identidad va arriba, y va acá por pedido del usuario.**

         Un aviso que la pantalla tiene que dar sola: el nombre y el logo son
         **de todo el sistema, no de cada local**. Puesto entre una lista de
         sucursales se puede leer al revés, y es el mismo criterio que el Centro
         de Ayuda y Soporte —uno para todo el negocio—: la clienta entra por un
         único portal y ve una sola marca.

         Se ven en la pantalla de ingreso —o sea antes de que nadie entre— y en
         la barra de arriba de todas las pantallas. Antes vivían en `APP_NAME`,
         así que cambiarlos era editar el `.env` y volver a desplegar. --}}
    <div class="spg-panel mb-3" style="max-width:860px">
        <h2 style="font-size:1rem;font-weight:500;">Identidad del salón</h2>
        <p class="text-muted-warm mb-3" style="font-size:.82rem">
            Son de <strong>todo el sistema, no de cada sucursal</strong>: se ven en la pantalla de
            ingreso y arriba de todas las pantallas, en los correos y en lo que se imprime, para el
            equipo y para las clientas, trabajen en el local que trabajen. El cambio se aplica de
            una, sin volver a entrar.
        </p>

        <form method="post" action="{{ route('seguridad.identidad.guardar') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="nombre_salon">Nombre del salón *</label>
                    <input class="form-control" id="nombre_salon" name="nombre_salon" required
                           maxlength="60" value="{{ old('nombre_salon', $nombreSalon) }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="logo">Logo</label>
                    <input type="file" class="form-control" id="logo" name="logo"
                           accept="image/png,image/jpeg,image/webp">
                    <div class="form-text">PNG, JPG o WEBP, hasta 512 KB. Si no subís nada, queda el que está.</div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-oro w-100"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </div>

            {{-- **Lo que sale impreso en el comprobante electrónico.**
                 Vive acá porque es del salón entero, como el nombre y el logo:
                 la dirección y el timbrado sí son de cada sucursal y se cargan
                 en su fila y en Timbrados. Sin esto el KuDE salía con la
                 actividad del archivo de ejemplo del Automatizador —«VENTA AL
                 POR MENOR»— que no describe a una peluquería. --}}
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <hr class="my-1">
                    <span class="text-muted-warm" style="font-size:.85rem">
                        <i class="bi bi-receipt"></i> Lo que sale impreso en la factura electrónica
                    </span>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="actividad_cod">Código de actividad</label>
                    <input class="form-control" id="actividad_cod" name="actividad_cod" maxlength="10"
                           value="{{ old('actividad_cod', $actividad['cod']) }}" placeholder="96021">
                    <div class="form-text">El de la SET, el mismo del RUC.</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="actividad_desc">Actividad económica</label>
                    <input class="form-control" id="actividad_desc" name="actividad_desc" maxlength="120"
                           value="{{ old('actividad_desc', $actividad['desc']) }}"
                           placeholder="PELUQUERIA Y OTROS TRATAMIENTOS DE BELLEZA">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="email_fiscal">Correo con el que facturás</label>
                    <input class="form-control" id="email_fiscal" name="email_fiscal" type="email"
                           maxlength="120" value="{{ old('email_fiscal', $emailFiscal) }}">
                    <div class="form-text">Va impreso; no es a donde llegan los avisos del sistema.</div>
                </div>
            </div>
        </form>

        @if ($logo)
            <div class="d-flex align-items-center gap-3 mt-3 pt-3" style="border-top:1px solid var(--gris-calido)">
                <img src="{{ $logo }}" alt="Logo del salón"
                     style="height:44px;width:auto;border-radius:6px;background:var(--negro);padding:4px">
                <span class="text-muted-warm" style="font-size:.82rem">Logo actual</span>
                <form method="post" action="{{ route('seguridad.identidad.logo.quitar') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-neutro"
                            data-confirmar="¿Quitar el logo y volver al ícono por defecto?">
                        <i class="bi bi-trash"></i> Quitar</button>
                </form>
            </div>
        @endif
    </div>

    <div class="spg-panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Nombre</th><th>RUC</th><th>Ciudad</th><th>Teléfono</th>
                        <th class="text-end">Personal</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $s)
                        <tr>
                            <td>{{ $s->nombre }}</td>
                            <td class="text-muted-warm">{{ $s->ruc ?: '—' }}</td>
                            <td class="text-muted-warm">{{ $s->ciudad ?: '—' }}</td>
                            <td>{{ $s->telefono ?: '—' }}</td>
                            <td class="text-end">{{ (int) $s->personal }}</td>
                            <td>
                                @if ($s->activo)
                                    <span class="badge-estado e-ok">Activa</span>
                                @else
                                    <span class="badge-estado e-muted">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap">
                                <a class="btn btn-sm btn-outline-neutro" title="Editar"
                                   href="{{ route('seguridad.sucursal_form', $s->id_sucursal) }}">
                                    <i class="bi bi-pencil"></i></a>
                                <form method="post" action="{{ route('seguridad.sucursal.baja') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="id_sucursal" value="{{ $s->id_sucursal }}">
                                    <button class="btn btn-sm btn-outline-neutro"
                                            title="{{ $s->activo ? 'Desactivar' : 'Activar' }}"
                                            data-confirmar="¿{{ $s->activo ? 'Desactivar' : 'Activar' }} «{{ $s->nombre }}»?">
                                        <i class="bi bi-toggle-{{ $s->activo ? 'on' : 'off' }}"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="spg-vacio">
                                    <i class="bi bi-shop"></i>
                                    <div class="t">No hay sucursales cargadas.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
