@extends('layout.app')

@section('titulo', 'Datos de pago')

@section('contenido')

<x-encabezado sub="A qué cuenta le decimos a la clienta que transfiera la seña. Cada sucursal tiene las suyas." />

{{-- **Esto NO es una pasarela de pagos y no la va a haber.** La clienta
     transfiere por su cuenta y sube el comprobante; lo único que el sistema
     hace es decirle a dónde. Por eso acá no hay ningún token ni credencial:
     son los mismos datos que hoy se pasan por WhatsApp, escritos una vez. --}}
<div class="spg-panel mb-3">
    <p class="text-muted-warm mb-0" style="font-size:.88rem">
        <i class="bi bi-info-circle"></i>
        La clienta ve estas cuentas <strong>al registrar su seña</strong>, y ve
        sólo las del local donde reservó. El salón sigue recibiendo el dinero
        como siempre: acá no se cobra nada, se le dice a dónde transferir.
    </p>
</div>

@if (count($sucursales) > 1)
    <div class="spg-panel mb-3">
        <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label" for="suc">Sucursal</label>
                <select class="form-select form-select-sm" id="suc" name="sucursal"
                        onchange="this.form.submit()">
                    @foreach ($sucursales as $s)
                        <option value="{{ $s->id_sucursal }}"
                            @selected((int) $sucursal === (int) $s->id_sucursal)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button class="btn btn-sm btn-oro">Ver</button></noscript>
        </form>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2">
                <i class="bi bi-{{ $editar ? 'pencil' : 'plus-circle' }}"></i>
                {{ $editar ? 'Editar la cuenta' : 'Cargar una cuenta' }}</h2>

            <form method="post" action="{{ route('seguridad.pagos.guardar') }}">
                @csrf
                <input type="hidden" name="id_sucursal" value="{{ $sucursal }}">
                @if ($editar)
                    <input type="hidden" name="id_dato_pago" value="{{ $editar->id_dato_pago }}">
                @endif

                {{-- ---------------------------------------------------------
                     1. Dónde está la plata --}}
                <div class="spg-paso">
                    <span class="spg-paso-n">1</span>
                    <div class="spg-paso-t">¿Dónde está la cuenta?</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="medio">Cómo se paga</label>
                        <select class="form-select" id="medio" name="id_metodo_pago" required>
                            @foreach ($medios as $m)
                                <option value="{{ $m->id_metodo_pago }}" data-tipo="{{ $m->tipo }}"
                                    @selected((int) old('id_metodo_pago', $editar->id_metodo_pago ?? 0) === (int) $m->id_metodo_pago)>
                                    {{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="entidad" data-medio-label="entidad">Banco o billetera</label>
                        <input class="form-control" id="entidad" name="entidad" required maxlength="80"
                               value="{{ old('entidad', $editar->entidad ?? '') }}"
                               placeholder="Itaú, Ueno, Tigo Money…">
                    </div>
                </div>

                {{-- ---------------------------------------------------------
                     2. El ALIAS, y va primero porque es lo que se usa.

                     **En Paraguay el alias es el ÚNICO dato necesario para
                     transferir** (SIPAP): reemplaza al número de cuenta, a la
                     entidad y al nombre del destinatario. Y no es una palabra
                     inventada — es uno de cuatro: cédula, RUC, celular o
                     correo.

                     Por eso el tipo se guarda: permite validarlo y sobre todo
                     DECIRLE a la clienta por dónde buscarlo, que es como
                     funciona la pantalla de su banco. --}}
                <div class="spg-paso">
                    <span class="spg-paso-n">2</span>
                    <div class="spg-paso-t">El alias
                        <span class="text-muted-warm">— con esto solo alcanza para transferir</span></div>
                </div>

                {{-- **Es opcional**: no todos los bancos lo usan y no todos los
                     salones lo registraron. Sin alias la clienta transfiere con
                     los datos de siempre, que están abajo. --}}
                <div class="row g-2 mb-1">
                    <div class="col-5">
                        <label class="form-label" for="alias_tipo">Tipo de alias</label>
                        {{-- Los cuatro que habilita el BCP. Es un combo y no
                             texto libre porque son exactamente esos: escrito a
                             mano, el sistema no podría validarlo ni decirle a la
                             clienta por dónde buscarlo. --}}
                        <select class="form-select" id="alias_tipo" name="alias_tipo"
                                data-alias-tipo="#alias">
                            <option value="">— sin alias —</option>
                            @foreach ($tiposAlias as $k => $v)
                                <option value="{{ $k }}"
                                    data-ph="{{ $ejemplosAlias[$k] ?? '' }}"
                                    data-solo="{{ $filtroAlias[$k] ?? '' }}"
                                    @selected(old('alias_tipo', $editar->alias_tipo ?? '') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-7">
                        <label class="form-label" for="alias">Alias</label>
                        <input class="form-control" id="alias" name="alias" maxlength="60"
                               value="{{ old('alias', $editar->alias ?? '') }}"
                               placeholder="Elegí primero el tipo">
                    </div>
                </div>
                <div class="form-text mb-3">
                    Es el que el salón registró en su banco. La clienta lo busca por
                    ese mismo tipo en su app y le aparece la cuenta:
                    <strong>no tiene que tipear el número</strong>.
                </div>

                {{-- ---------------------------------------------------------
                     3. El respaldo, para quien no transfiere por alias --}}
                <div class="spg-paso">
                    <span class="spg-paso-n">3</span>
                    <div class="spg-paso-t">Los datos de siempre
                        <span class="text-muted-warm">— por si transfiere sin alias</span></div>
                </div>

                <div class="mb-2" data-medio-campo="titular">
                    <label class="form-label" for="titular">A nombre de</label>
                    <input class="form-control" id="titular" name="titular" required maxlength="120"
                           value="{{ old('titular', $editar->titular ?? '') }}"
                           placeholder="Como figura en el banco">
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-7" data-medio-campo="numero">
                        <label class="form-label" for="numero_cuenta" data-medio-label="numero">Número de cuenta</label>
                        <input class="form-control" id="numero_cuenta" name="numero_cuenta" required maxlength="40"
                               value="{{ old('numero_cuenta', $editar->numero_cuenta ?? '') }}"
                               placeholder="O el celular, si es billetera">
                    </div>
                    <div class="col-5" data-medio-campo="tipo-cuenta">
                        <label class="form-label" for="tipo_cuenta">Tipo de cuenta</label>
                        {{-- **Combo y no texto libre**: escrito a mano, «Caja de
                             ahorro», «caja de ahorros» y «C. de ahorro» son la
                             misma cosa tres veces, y la clienta ve lo que se
                             haya tipeado. --}}
                        <select class="form-select" id="tipo_cuenta" name="tipo_cuenta">
                            <option value="">— sin especificar —</option>
                            @foreach ($tiposCuenta as $tc)
                                <option value="{{ $tc }}"
                                    @selected(old('tipo_cuenta', $editar->tipo_cuenta ?? '') === $tc)>{{ $tc }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-2" data-medio-campo="documento">
                    <label class="form-label" for="documento">CI o RUC del titular</label>
                    <input class="form-control" id="documento" name="documento" maxlength="20"
                           data-solo="ruc" value="{{ old('documento', $editar->documento ?? '') }}">
                    <div class="form-text">Varios bancos lo piden al transferir sin alias.</div>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="observacion">Aclaración para la clienta</label>
                    <input class="form-control" id="observacion" name="observacion" maxlength="200"
                           value="{{ old('observacion', $editar->observacion ?? '') }}"
                           placeholder="Mandanos el comprobante por WhatsApp">
                </div>

                <div class="pt-2 border-top">
                    <button class="btn btn-oro"><i class="bi bi-check2"></i> Guardar</button>
                    @if ($editar)
                        <a class="btn btn-outline-neutro"
                           href="{{ route('seguridad.pagos', ['sucursal' => $sucursal]) }}">Cancelar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-bank"></i> Cuentas de este local</h2>

            @if (! $datos)
                <div class="spg-vacio">
                    <i class="bi bi-bank"></i>
                    <div class="t">Todavía no cargaste ninguna cuenta</div>
                    <div class="d">
                        Mientras no haya ninguna, a la clienta que reserve con seña
                        le decimos que se comunique con el salón.
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Dónde</th><th>Alias</th><th>Cuenta</th>
                                <th>Se le muestra</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($datos as $d)
                                <tr @class(['text-muted-warm' => ! $d->activo])>
                                    <td>
                                        <div>{{ $d->entidad }}</div>
                                        <div class="text-muted-warm" style="font-size:.8rem">{{ $d->medio }}</div>
                                    </td>
                                    <td>
                                        @if ($d->alias)
                                            <div class="spg-cuenta-nro">{{ $d->alias }}</div>
                                            <div class="text-muted-warm" style="font-size:.8rem">
                                                {{ $tiposAlias[$d->alias_tipo] ?? 'alias' }}</div>
                                        @else
                                            <span class="text-muted-warm" style="font-size:.85rem">sin alias</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="font-size:.88rem">{{ $d->numero_cuenta ?: '—' }}</div>
                                        <div class="text-muted-warm" style="font-size:.8rem">
                                            {{ $d->titular }}@if ($d->tipo_cuenta) · {{ $d->tipo_cuenta }}@endif
                                        </div>
                                    </td>
                                    <td>
                                        @if ($d->activo)
                                            <span class="badge-estado e-ok">sí</span>
                                        @else
                                            <span class="badge-estado e-muted">no</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space:nowrap">
                                        {{-- **Reordenar con flechas, no con un número.**
                                             El campo «orden» hacía elegir un número para
                                             ordenar dos o tres filas; acá se ve el efecto
                                             al instante y no hay nada que calcular. --}}
                                        @if (count($datos) > 1)
                                            @foreach (['arriba' => 'up', 'abajo' => 'down'] as $dir => $ic)
                                                <form method="post" action="{{ route('seguridad.pagos.orden') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="id_dato_pago" value="{{ $d->id_dato_pago }}">
                                                    <input type="hidden" name="dir" value="{{ $dir }}">
                                                    <button class="btn btn-sm btn-outline-neutro"
                                                            title="Mostrarla más {{ $dir }}">
                                                        <i class="bi bi-arrow-{{ $ic }}"></i></button>
                                                </form>
                                            @endforeach
                                        @endif
                                        <a class="btn btn-sm btn-outline-neutro"
                                           href="{{ route('seguridad.pagos', ['sucursal' => $sucursal, 'editar' => $d->id_dato_pago]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('seguridad.pagos.estado') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id_dato_pago" value="{{ $d->id_dato_pago }}">
                                            <button class="btn btn-sm btn-outline-neutro"
                                                    title="{{ $d->activo ? 'Dejar de mostrársela a la clienta' : 'Volver a mostrarla' }}">
                                                <i class="bi bi-{{ $d->activo ? 'eye-slash' : 'eye' }}"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- **Se desactiva, no se borra.** Una cuenta que se dejó de usar
                     sigue siendo la que aparece en los comprobantes de las señas
                     viejas: si desaparece, no hay forma de saber a dónde se
                     transfirió. --}}
                <p class="text-muted-warm mt-2 mb-0" style="font-size:.82rem">
                    Sacar una cuenta no la borra: deja de mostrársela a la clienta,
                    y las señas que ya se pagaron ahí siguen teniendo su respaldo.
                </p>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const medio = document.getElementById('medio');
    if (!medio) return;

    const entidad = document.getElementById('entidad');
    const entidadLabel = document.querySelector('[data-medio-label="entidad"]');
    const numeroLabel = document.querySelector('[data-medio-label="numero"]');
    const tipoCuenta = document.querySelector('[data-medio-campo="tipo-cuenta"]');

    function actualizarDatos() {
        const opcion = medio.options[medio.selectedIndex];
        const tipo = opcion ? opcion.dataset.tipo : '';
        const billetera = tipo === 'OTRO';

        if (entidadLabel) entidadLabel.textContent = billetera ? 'Billetera o proveedor' : 'Banco';
        if (entidad) entidad.placeholder = billetera ? 'Tigo Money, Personal Pay…' : 'Itaú, Ueno…';
        if (numeroLabel) numeroLabel.textContent = billetera ? 'Número de celular o cuenta' : 'Número de cuenta';
        if (tipoCuenta) {
            tipoCuenta.classList.toggle('d-none', billetera);
            const select = tipoCuenta.querySelector('select');
            if (select && billetera) select.value = '';
        }
    }

    medio.addEventListener('change', actualizarDatos);
    actualizarDatos();
});
</script>
@endpush
@endsection
