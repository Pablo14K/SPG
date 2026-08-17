@extends('layout.app')

@section('titulo', 'Contacto y soporte')

@section('contenido')
    <x-encabezado sub="Cómo se presenta el salón: su nombre y su logo, y los medios por los que la clienta le escribe." />

    {{-- **La identidad va arriba porque se ve antes que todo lo demás**: el
         nombre y el logo salen en la pantalla de ingreso —o sea antes de que
         nadie entre— y en la barra de arriba de todas las pantallas. Antes
         vivían en `APP_NAME`, así que cambiarlos era editar el `.env` y volver
         a desplegar. --}}
    <div class="spg-panel mb-3" style="max-width:860px">
        <h2 style="font-size:1rem;font-weight:500;">Identidad del salón</h2>
        <p class="text-muted-warm mb-3" style="font-size:.82rem">
            Se ven en la pantalla de ingreso y arriba de todas las pantallas, para el equipo
            y para las clientas. El cambio se aplica de una, sin volver a entrar.
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

    <div class="spg-panel" style="max-width:860px">
        <form method="post" action="{{ route('seguridad.contacto.guardar') }}">
            @csrf

            <p class="text-muted-warm mb-3" style="font-size:.82rem">
                Podés cargar varios del mismo tipo —dos WhatsApp, por ejemplo— y distinguirlos con la
                etiqueta. Un contacto que no se entienda <strong>no se guarda</strong>: el sistema avisa
                en vez de publicar un enlace que no lleva a ningún lado.
            </p>

            <div id="filasContacto">
                @php $filas = count($contactos) ? $contactos : [null, null, null]; @endphp
                @foreach ($filas as $c)
                    <div class="row g-2 mb-2 filaContacto">
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" name="canal[]">
                                @foreach ($canales as $clave => $def)
                                    <option value="{{ $clave }}" @selected(($c->canal ?? '') === $clave)>
                                        {{ $def['etiqueta'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input class="form-control form-control-sm" name="valor[]" maxlength="160"
                                   placeholder="Número, usuario o enlace" value="{{ $c->valor ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <input class="form-control form-control-sm" name="etiqueta[]" maxlength="40"
                                   placeholder="Etiqueta (opcional)" value="{{ $c->etiqueta ?? '' }}">
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-sm btn-rapido mb-3" id="masContactos">
                <i class="bi bi-plus-lg"></i> Otra fila
            </button>

            <div class="spg-panel" style="background:var(--blanco-hueso)">
                <h3 style="font-size:.9rem;font-weight:500">Cómo se carga cada uno</h3>
                <ul class="text-muted-warm mb-0" style="font-size:.8rem">
                    @foreach ($canales as $def)
                        <li><strong>{{ $def['etiqueta'] }}:</strong> {{ $def['ayuda'] }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-3">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.getElementById('masContactos').addEventListener('click', function () {
    var cont = document.getElementById('filasContacto');
    var copia = cont.querySelector('.filaContacto').cloneNode(true);
    copia.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    cont.appendChild(copia);
});
</script>
@endpush
