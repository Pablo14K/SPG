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

                <div class="mb-2">
                    <label class="form-label" for="medio">¿Cómo se paga?</label>
                    <select class="form-select" id="medio" name="id_metodo_pago" required>
                        @foreach ($medios as $m)
                            <option value="{{ $m->id_metodo_pago }}"
                                @selected((int) old('id_metodo_pago', $editar->id_metodo_pago ?? 0) === (int) $m->id_metodo_pago)>
                                {{ $m->nombre }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Sale de los medios de pago del sistema. El efectivo y las
                        tarjetas no están porque no hay cuenta que darle a nadie.
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="entidad">Banco o billetera</label>
                    <input class="form-control" id="entidad" name="entidad" required maxlength="80"
                           value="{{ old('entidad', $editar->entidad ?? '') }}"
                           placeholder="Banco Itaú, Tigo Money…">
                </div>

                <div class="mb-2">
                    <label class="form-label" for="titular">A nombre de</label>
                    <input class="form-control" id="titular" name="titular" required maxlength="120"
                           value="{{ old('titular', $editar->titular ?? '') }}"
                           placeholder="Como figura en el banco">
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-7">
                        <label class="form-label" for="documento">CI o RUC del titular</label>
                        <input class="form-control" id="documento" name="documento" maxlength="20"
                               data-solo="ruc" value="{{ old('documento', $editar->documento ?? '') }}">
                        <div class="form-text">Opcional, pero varios bancos lo piden al transferir.</div>
                    </div>
                    <div class="col-5">
                        <label class="form-label" for="tipo_cuenta">Tipo</label>
                        <input class="form-control" id="tipo_cuenta" name="tipo_cuenta" maxlength="30"
                               list="tiposCuenta" value="{{ old('tipo_cuenta', $editar->tipo_cuenta ?? '') }}">
                        <datalist id="tiposCuenta">
                            <option value="Caja de ahorro"></option>
                            <option value="Cuenta corriente"></option>
                            <option value="Billetera"></option>
                        </datalist>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="numero_cuenta">Número de cuenta</label>
                    <input class="form-control" id="numero_cuenta" name="numero_cuenta" required maxlength="40"
                           value="{{ old('numero_cuenta', $editar->numero_cuenta ?? '') }}"
                           placeholder="O el celular, si es billetera">
                </div>

                {{-- **El alias es lo que de verdad se copia hoy.** Varios bancos
                     paraguayos transfieren por alias en vez de por número de
                     cuenta, y es más corto y más difícil de tipear mal. Va como
                     campo propio y no dentro de la aclaración: la clienta lo
                     tiene que poder copiar de un toque. --}}
                <div class="mb-2">
                    <label class="form-label" for="alias">Alias</label>
                    <input class="form-control" id="alias" name="alias" maxlength="60"
                           value="{{ old('alias', $editar->alias ?? '') }}"
                           placeholder="Si el banco lo usa, es lo más fácil de copiar">
                </div>

                <div class="mb-2">
                    <label class="form-label" for="observacion">Aclaración para la clienta</label>
                    <input class="form-control" id="observacion" name="observacion" maxlength="200"
                           value="{{ old('observacion', $editar->observacion ?? '') }}"
                           placeholder="Mandanos el comprobante por WhatsApp">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="orden">Orden</label>
                    <input class="form-control" id="orden" name="orden" data-solo="numeros" maxlength="3"
                           value="{{ old('orden', $editar->orden ?? 0) }}">
                    <div class="form-text">La de menor número se muestra primero.</div>
                </div>

                <button class="btn btn-oro"><i class="bi bi-check2"></i> Guardar</button>
                @if ($editar)
                    <a class="btn btn-outline-neutro"
                       href="{{ route('seguridad.pagos', ['sucursal' => $sucursal]) }}">Cancelar</a>
                @endif
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
                                <th>Cómo</th><th>Cuenta</th><th>Titular</th>
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
                                        <div class="spg-cuenta-nro">{{ $d->numero_cuenta ?: '—' }}</div>
                                        @if ($d->alias)
                                            <div class="text-muted-warm" style="font-size:.8rem">
                                                alias: {{ $d->alias }}</div>
                                        @endif
                                        @if ($d->tipo_cuenta)
                                            <div class="text-muted-warm" style="font-size:.8rem">{{ $d->tipo_cuenta }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $d->titular }}</div>
                                        @if ($d->documento)
                                            <div class="text-muted-warm" style="font-size:.8rem">{{ $d->documento }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($d->activo)
                                            <span class="badge-estado e-ok">sí</span>
                                        @else
                                            <span class="badge-estado e-muted">no</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space:nowrap">
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
@endsection
