@extends('layout.app')

@section('titulo', 'Contacto y soporte')

@section('contenido')
    <x-encabezado sub="Los medios por los que la clienta le escribe al salón. Salen en el pie de todas las pantallas, bajo «Centro de Ayuda y Soporte». Si no cargás ninguno, el bloque no se dibuja." />

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
                        <div class="col-md-3">
                            <input class="form-control form-control-sm" name="etiqueta[]" maxlength="40"
                                   placeholder="Etiqueta (opcional)" value="{{ $c->etiqueta ?? '' }}">
                        </div>
                        {{-- **Se podían agregar filas y no sacarlas.** Una cargada por
                             error sólo se «borraba» vaciándola a mano, y con el canal
                             puesto no siempre se entiende que eso alcanza. --}}
                        <div class="col-md-1 d-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-neutro quitaContacto w-100"
                                    title="Quitar este contacto"><i class="bi bi-x-lg"></i></button>
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
var cont = document.getElementById('filasContacto');

document.getElementById('masContactos').addEventListener('click', function () {
    var copia = cont.querySelector('.filaContacto').cloneNode(true);
    copia.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    cont.appendChild(copia);
});

// **Nunca se queda sin ninguna fila**: con cero, «Otra fila» clona algo que ya
// no existe y el botón deja de funcionar. La última se vacía en vez de irse.
cont.addEventListener('click', function (e) {
    var b = e.target.closest('.quitaContacto');
    if (!b) { return; }
    var fila = b.closest('.filaContacto');
    if (cont.querySelectorAll('.filaContacto').length > 1) { fila.remove(); }
    else { fila.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
});
</script>
@endpush
