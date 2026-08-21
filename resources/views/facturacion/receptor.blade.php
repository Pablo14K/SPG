{{--
    Datos del receptor para el comprobante electrónico.

    Se piden ANTES de emitir, y no después, por una razón concreta: un rechazo
    de la DNIT por un dato mal cargado **no se reintenta**. El número de
    comprobante ya se gastó —la numeración de la SET no puede tener huecos—, así
    que hay que anular y hacer otro. Todo lo que se pueda comprobar sin salir del
    salón se comprueba en esta pantalla.

    Los campos son los que exige la DNIT del receptor (Manual Técnico v150,
    grupo D). **En pantalla NO se nombran los códigos del manual**: esto lo usa
    quien atiende, no quien programa. La trazabilidad campo por campo está en
    CLAUDE.md, que es donde sirve.
--}}
@extends('layout.app')

@section('titulo', 'Datos para la factura')

@section('contenido')
    @php use App\Servicios\Permisos; @endphp

    <x-encabezado sub="Los datos con los que sale la factura. Vienen cargados de la ficha de la clienta y se pueden cambiar: puede pedirla a nombre de su empresa, o que se la mandes a otro correo." />

    {{-- El aviso de «modo simulado» no va acá: ocupa media pantalla justo
         arriba del formulario y repite algo que ya dice el comprobante una vez
         emitido, que es donde importa saber si salió o no hacia la DNIT. --}}
    <form method="post" action="{{ route('facturacion.receptor.guardar') }}">
        @csrf
        <input type="hidden" name="id_cita" value="{{ $idCita }}">
        <input type="hidden" name="id_tipo_comprobante" value="{{ $idTipo }}">
        <input type="hidden" name="id_condicion_venta" value="{{ $idCond }}">

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="spg-panel mb-3">
                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-person-vcard"></i> ¿A nombre de quién?</h2>
                    <p class="text-muted-warm mb-3" style="font-size:.82rem">
                        Si no pide la factura a su nombre, dejalo en <strong>consumidor final</strong>.
                    </p>

                    <div class="mb-3">
                        <label class="form-label" for="tipo_doc">Se identifica con <span class="txt-no">*</span></label>
                        <select class="form-select" id="tipo_doc" name="tipo_doc">
                            <option value="CF" @selected(old('tipo_doc', $tipoSugerido) === 'CF')>
                                Consumidor final (sin documento)
                            </option>
                            <option value="CI" @selected(old('tipo_doc', $tipoSugerido) === 'CI')>
                                Cédula de identidad
                            </option>
                            <option value="RUC" @selected(old('tipo_doc', $tipoSugerido) === 'RUC')>
                                RUC
                            </option>
                        </select>
                        <div class="form-text">Si no pide la factura a su nombre, dejalo como está.</div>
                    </div>

                    {{-- El bloque del documento se oculta con «consumidor final»,
                         que no lleva ninguno. Se oculta con clase y NO se saca del
                         formulario: si se quitara el input, `old()` perdería lo
                         escrito al volver con un error. --}}
                    <div class="mb-3 @if (old('tipo_doc', $tipoSugerido) === 'CF') d-none @endif" id="bloqueDoc">
                        <label class="form-label" for="documento">Número <span class="txt-no">*</span></label>
                        <input class="form-control" id="documento" name="documento"
                               value="{{ old('documento', $docSugerido) }}"
                               placeholder="4200000">
                        <div class="form-text" id="ayudaDoc"></div>
                    </div>

                    <div class="mb-3 @if (old('tipo_doc', $tipoSugerido) === 'CF') d-none @endif" id="bloqueNombre">
                        <label class="form-label" for="nombre">Nombre o razón social <span class="txt-no">*</span></label>
                        <input class="form-control" id="nombre" name="nombre"
                               value="{{ old('nombre', trim(($per->nombre ?? '') . ' ' . ($per->apellido ?? ''))) }}">
                        <div class="form-text">Con RUC tiene que decir lo mismo que figura en el RUC.</div>
                    </div>
                </div>

                <div class="spg-panel mb-3">
                    <h2 class="spg-form-titulo mb-1"><i class="bi bi-envelope-at"></i> ¿A dónde se la mandamos?</h2>
                    <p class="text-muted-warm mb-3" style="font-size:.82rem">
                        La factura le llega por correo apenas se emite. Si lo dejás vacío, la factura
                        vale igual pero <strong>no le llega a nadie</strong>.
                    </p>

                    <div class="mb-3">
                        <label class="form-label" for="email">Correo electrónico</label>
                        <input class="form-control" id="email" name="email" type="email"
                               value="{{ old('email', $per->email ?? '') }}" placeholder="clienta@correo.com">
                        <div class="form-text">Es a donde le llega la factura.</div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label" for="direccion">Dirección</label>
                            <input class="form-control" id="direccion" name="direccion"
                                   value="{{ old('direccion', $per->direccion ?? '') }}">
                            <div class="form-text">Opcional.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input class="form-control" id="telefono" name="telefono" data-solo="telefono" inputmode="tel"
                                   value="{{ old('telefono', $per->telefono ?? '') }}">
                            <div class="form-text">Opcional.</div>
                        </div>
                    </div>

                    <p class="text-muted-warm mb-0 mt-3" style="font-size:.78rem">
                        <i class="bi bi-info-circle"></i>
                        Lo que corrijas acá queda guardado en su ficha, así la próxima vez ya sale bien.
                    </p>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="spg-panel mb-3">
                    <h2 class="spg-form-titulo mb-2"><i class="bi bi-receipt"></i> Qué se va a emitir</h2>

                    <div class="d-flex justify-content-between mb-1" style="font-size:.9rem">
                        <span class="text-muted-warm">Comprobante</span>
                        <strong>{{ $tipoNombre }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3" style="font-size:.9rem">
                        <span class="text-muted-warm">Condición</span>
                        <strong>{{ $condNombre }}</strong>
                    </div>

                    <table class="table table-sm align-middle mb-2">
                        <tbody>
                            @foreach ($items as $it)
                                <tr>
                                    <td style="font-size:.85rem">{{ $it->nombre }}</td>
                                    <td class="text-end" style="font-size:.85rem">{{ money($it->precio) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end txt-oro">{{ money($total) }}</th>
                            </tr>
                        </tfoot>
                    </table>

                    <p class="text-muted-warm mb-0" style="font-size:.78rem">
                        El descuento lo aplica la base al emitir —el del nivel de la clienta o la mejor
                        promoción vigente, el que más le convenga—, así que el total de arriba es antes
                        de eso. El IVA va incluido en el precio: se desglosa, no se suma.
                    </p>

                    @if ($total >= $topeInnominado)
                        {{-- Error 1321 del manual: arriba de este monto la DNIT no
                             acepta un documento innominado. Se avisa antes y no
                             cuando ya se gastó el número del comprobante. --}}
                        <div class="alert alert-warning mt-3 mb-0" style="font-size:.82rem">
                            <strong>Esta venta pasa los {{ money($topeInnominado) }}.</strong>
                            Por ese monto no se puede facturar a consumidor final: hay que cargarle
                            la cédula o el RUC.
                        </div>
                    @endif
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-oro">
                        <i class="bi bi-send-check"></i> Emitir y declarar
                    </button>
                    <a class="btn btn-outline-neutro" href="{{ route('facturacion.emitir') }}">Volver</a>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
// Qué campos hacen falta según con qué se identifica. Consumidor final no
// lleva documento ni nombre: la DNIT los quiere vacíos, no en blanco.
(function () {
    // Lo que la ficha tiene de cada tipo: cambiar de tipo trae SU número.
    var DOCS = {
        CI:  @json($cedulaFicha ?? ''),
        RUC: @json($rucFicha ?? '')
    };

    var tipo   = document.getElementById('tipo_doc'),
        bloqueD = document.getElementById('bloqueDoc'),
        bloqueN = document.getElementById('bloqueNombre'),
        doc     = document.getElementById('documento'),
        ayuda   = document.getElementById('ayudaDoc');

    var AYUDA = {
        CI:  'Sólo números, como figura en la cédula.',
        RUC: 'Va con el dígito verificador, así: 80012345-0. Lo comprobamos antes de emitir.'
    };

    function ajustar() {
        var v = tipo.value;
        bloqueD.classList.toggle('d-none', v === 'CF');
        bloqueN.classList.toggle('d-none', v === 'CF');
        doc.placeholder = v === 'RUC' ? '80012345-0' : '4200000';
        ayuda.textContent = AYUDA[v] || '';
    }

    // **Al cambiar de tipo, el número cambia con él.** Antes sólo se movían
    // los bloques: quien elegía «cédula» se quedaba con el RUC escrito y
    // emitía con el documento que no era. Sólo se pisa cuando el campo tiene
    // el valor del OTRO tipo o está vacío: lo que la persona escribió a mano
    // no se toca.
    var previo = tipo.value;
    tipo.addEventListener('change', function () {
        var v = tipo.value;
        if (v !== 'CF') {
            var eraDelOtro = doc.value.trim() === '' || doc.value.trim() === (DOCS[previo] || '');
            if (eraDelOtro) { doc.value = DOCS[v] || ''; }
        }
        previo = v;
        ajustar();
    });
    ajustar();
})();
</script>
@endpush
