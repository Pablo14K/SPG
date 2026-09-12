{{-- **La ficha de una cuenta bancaria**: la misma para crear y para editar,
     porque dos formularios iguales se desfasan.

     Parámetros:
     · $uid       sufijo para los ids (hay un modal por cuenta en la pantalla)
     · $editar    la cuenta, o null para una nueva
     · $sucursal  el local elegido en la pantalla
     · $sucursales, $medios, $tiposAlias, $ejemplosAlias, $filtroAlias, $tiposCuenta --}}
@php
    $e = $editar ?? null;
    $u = $uid ?? 'n';
    $viejo = $e && (int) old('id_cuenta', 0) === (int) $e->id_cuenta;
    $v = fn (string $campo, $porDefecto = '') => $viejo ? old($campo, $porDefecto) : (($e->$campo ?? null) ?? $porDefecto);
@endphp

<form method="post" action="{{ route('facturacion.cuentas.guardar') }}" data-cuenta-form="{{ $u }}">
    @csrf
    @if ($e)
        <input type="hidden" name="id_cuenta" value="{{ $e->id_cuenta }}">
    @endif

    {{-- ---------------------------------------------------------
         1. Dónde está la plata --}}
    <div class="sgp-paso">
        <span class="sgp-paso-n">1</span>
        <div class="sgp-paso-t">¿Dónde está la cuenta?</div>
    </div>

    <div class="row g-2 mb-3">
        @if (count($sucursales) > 1)
            <div class="col-12">
                <label class="form-label" for="suc{{ $u }}">Sucursal</label>
                <select class="form-select" id="suc{{ $u }}" name="id_sucursal" required>
                    @foreach ($sucursales as $s)
                        <option value="{{ $s->id_sucursal }}"
                            @selected((int) $v('id_sucursal', $sucursal) === (int) $s->id_sucursal)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <input type="hidden" name="id_sucursal" value="{{ $sucursal }}">
        @endif
        <div class="col-6">
            <label class="form-label" for="medio{{ $u }}">Cómo se paga</label><x-ayuda campo="medio" />
            <select class="form-select" id="medio{{ $u }}" name="id_metodo_pago" required data-medio-de="{{ $u }}">
                @foreach ($medios as $m)
                    <option value="{{ $m->id_metodo_pago }}" data-tipo="{{ $m->tipo }}"
                        @selected((int) $v('id_metodo_pago', 0) === (int) $m->id_metodo_pago)>
                        {{ $m->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6">
            <label class="form-label" for="entidad{{ $u }}" data-medio-label="entidad">Banco o billetera</label><x-ayuda campo="entidad" />
            <input class="form-control" id="entidad{{ $u }}" name="entidad" required maxlength="80"
                   value="{{ $v('entidad') }}" placeholder="Itaú, Ueno, Tigo Money…">
        </div>
    </div>

    {{-- ---------------------------------------------------------
         2. El ALIAS, y va primero porque es lo que se usa.

         **En Paraguay el alias es el ÚNICO dato necesario para
         transferir** (SIPAP): reemplaza al número de cuenta, a la
         entidad y al nombre del destinatario. Y no es una palabra
         inventada — es uno de cuatro: cédula, RUC, celular o correo.

         Por eso el tipo se guarda: permite validarlo y sobre todo
         DECIRLE a la clienta por dónde buscarlo. --}}
    <div class="sgp-paso">
        <span class="sgp-paso-n">2</span>
        <div class="sgp-paso-t">El alias
            <span class="text-muted-warm">— con esto solo alcanza para transferir</span></div>
    </div>

    <div class="row g-2 mb-1">
        <div class="col-5">
            <label class="form-label" for="alias_tipo{{ $u }}">Tipo de alias</label><x-ayuda campo="alias_tipo" />
            <select class="form-select" id="alias_tipo{{ $u }}" name="alias_tipo"
                    data-alias-tipo="#alias{{ $u }}">
                <option value="">— sin alias —</option>
                @foreach ($tiposAlias as $k => $nombreTipo)
                    <option value="{{ $k }}"
                        data-ph="{{ $ejemplosAlias[$k] ?? '' }}"
                        data-solo="{{ $filtroAlias[$k] ?? '' }}"
                        @selected($v('alias_tipo') === $k)>{{ $nombreTipo }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-7">
            <label class="form-label" for="alias{{ $u }}">Alias</label><x-ayuda campo="alias" />
            <input class="form-control" id="alias{{ $u }}" name="alias" maxlength="60"
                   value="{{ $v('alias') }}" placeholder="Elegí primero el tipo">
        </div>
    </div>
    <div class="form-text mb-3">
        Es el que el salón registró en su banco. La clienta lo busca por ese mismo
        tipo en su app y le aparece la cuenta: <strong>no tiene que tipear el número</strong>.
    </div>

    {{-- ---------------------------------------------------------
         3. El respaldo, para quien no transfiere por alias --}}
    <div class="sgp-paso">
        <span class="sgp-paso-n">3</span>
        <div class="sgp-paso-t">Los datos de siempre
            <span class="text-muted-warm">— por si transfiere sin alias</span></div>
    </div>

    <div class="mb-2">
        <label class="form-label" for="titular{{ $u }}">A nombre de</label><x-ayuda campo="titular" />
        <input class="form-control" id="titular{{ $u }}" name="titular" required maxlength="120"
               value="{{ $v('titular') }}" placeholder="Como figura en el banco">
    </div>

    <div class="row g-2 mb-2">
        <div class="col-7">
            <label class="form-label" for="numero_cuenta{{ $u }}" data-medio-label="numero">Número de cuenta</label><x-ayuda campo="numero_cuenta" />
            <input class="form-control" id="numero_cuenta{{ $u }}" name="numero_cuenta" required maxlength="40"
                   value="{{ $v('numero_cuenta') }}" placeholder="O el celular, si es billetera">
        </div>
        <div class="col-5" data-medio-campo="tipo-cuenta">
            <label class="form-label" for="tipo_cuenta{{ $u }}">Tipo de cuenta</label><x-ayuda campo="tipo_cuenta" />
            {{-- **Combo y no texto libre**: escrito a mano, «Caja de ahorro»,
                 «caja de ahorros» y «C. de ahorro» son la misma cosa tres
                 veces, y la clienta ve lo que se haya tipeado. --}}
            <select class="form-select" id="tipo_cuenta{{ $u }}" name="tipo_cuenta">
                <option value="">— sin especificar —</option>
                @foreach ($tiposCuenta as $tc)
                    <option value="{{ $tc }}" @selected($v('tipo_cuenta') === $tc)>{{ $tc }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mb-2">
        <label class="form-label" for="documento{{ $u }}">CI o RUC del titular</label><x-ayuda>Varios bancos lo piden al transferir sin alias.</x-ayuda>
        <input class="form-control" id="documento{{ $u }}" name="documento" maxlength="20"
               data-solo="ruc" value="{{ $v('documento') }}">
    </div>

    <div class="mb-2">
        <label class="form-label" for="observacion{{ $u }}">Aclaración para la clienta</label><x-ayuda campo="observacion" />
        <input class="form-control" id="observacion{{ $u }}" name="observacion" maxlength="200"
               value="{{ $v('observacion') }}" placeholder="Mandanos el comprobante por WhatsApp">
    </div>

    <div class="pt-2 border-top d-flex gap-2">
        <button class="btn btn-oro"><i class="bi bi-check2"></i> Guardar</button>
        <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
    </div>
</form>
