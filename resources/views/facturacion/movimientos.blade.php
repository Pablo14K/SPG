@extends('layout.app')

@section('titulo', 'Movimiento de efectivo')

@section('contenido')
    <x-encabezado
        sub="El gasto de caja chica, el retiro, la plata que se saca para el cambio: lo único del arqueo que <strong>no sale de un cobro ni de un pago</strong>. Sin esto el cierre no cuadra y no hay forma de saber por qué." />

    @if (! $abierta)
        {{-- Sin caja abierta no se mueve un guaraní: quedaría fuera del arqueo
             y el cierre no cerraría. El aviso dice qué hacer, no «no se puede». --}}
        <div class="alert alert-warning">
            <strong>No hay ninguna caja abierta en esta sucursal.</strong>
            Un movimiento sin caja quedaría fuera del arqueo.
            @if (\App\Servicios\Permisos::puede('facturacion.caja'))
                <a class="link-oro" href="{{ route('facturacion.cajas') }}">Abrí la caja</a> y volvé.
            @else
                Pedile a quien administra la caja que la abra.
            @endif
        </div>
    @else
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-coin"></i> Movimiento de efectivo</h2>
            <p class="text-muted-warm" style="font-size:.85rem">
                Para lo que entra o sale del cajón sin ser un cobro ni un pago: el delivery, el taxi,
                la plata que se saca para el cambio, un retiro. <strong>Queda en el arqueo</strong>, así
                que el cierre cuadra con lo que hay de verdad.
            </p>

            <form method="post" action="{{ route('facturacion.caja.movimiento') }}"
                  class="row g-2 align-items-end" enctype="multipart/form-data">
                @csrf

                {{-- **La clase decide el signo, y decide qué respaldo se pide.**
                     Antes había un «ingreso/egreso» suelto y un texto libre, así
                     que un gasto, un retiro de la dueña y la plata del cambio
                     entraban todos igual — y ninguno dejaba rastro de por qué esa
                     plata se movió. Fiscalmente no se sostiene: el dinero no
                     entra ni sale de la nada.

                     El signo sale del tipo y no de un selector aparte: un gasto no
                     puede ser un ingreso, y dejarlo elegir invitaba a cargar una
                     salida como entrada. --}}
                <div class="col-md-4">
                    <label class="form-label" for="mc_clase">¿Qué es?</label>
                    <select class="form-select" id="mc_clase" name="id_tipo_mov_caja" required
                            data-exige="#mc_doc" data-nota="#mc_nota">
                        <option value="">— elegí —</option>
                        @foreach ($tipos as $t)
                            <option value="{{ $t->id_tipo_mov_caja }}"
                                    data-doc="{{ (int) $t->exige_documento }}"
                                    @selected((int) old('id_tipo_mov_caja') === (int) $t->id_tipo_mov_caja)>
                                {{ $t->nombre }} · {{ $t->signo === 'E' ? 'entra al cajón' : 'sale del cajón' }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- **La devolución se estira de la nota, no se tipea.** El monto
                     sale del documento: es lo que evita que queden dos salidas por
                     la misma devolución con números distintos. --}}
                <div class="col-12 mt-2" id="mc_nota" hidden>
                    <label class="form-label" for="mc_nc">¿Qué nota de crédito estás devolviendo?</label>
                    <select class="form-select" id="mc_nc" name="id_factura">
                        <option value="">— elegí la nota —</option>
                        @foreach ($notas as $n)
                            <option value="{{ $n->id_factura }}" data-monto="{{ (float) $n->en_efectivo }}">
                                {{ $n->nro }} · {{ $n->cliente }} · en efectivo {{ money($n->en_efectivo) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        @if (count($notas))
                            Sale el efectivo que la clienta había pagado en efectivo; lo que pagó con
                            tarjeta o transferencia se le devuelve por el mismo camino y no toca el cajón.
                        @else
                            No hay ninguna nota de crédito pendiente de devolver en esta sucursal.
                            Se emiten desde <strong>Facturas</strong>.
                        @endif
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="mc_monto">Monto</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ config('spg.moneda') }}</span>
                        <input class="form-control input-miles" id="mc_monto" name="monto"
                               value="{{ old('monto') }}" data-min="1" required>
                    </div>
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="mc_concepto">Concepto</label>
                    <input class="form-control" id="mc_concepto" name="concepto" maxlength="150"
                           value="{{ old('concepto') }}" required
                           placeholder="Ej.: delivery del almuerzo, retiro de la dueña…">
                </div>

                {{-- El respaldo del gasto. Se muestra sólo cuando la clase elegida
                     lo exige, porque un retiro no tiene comprobante que adjuntar y
                     pedírselo sería inventar un papel. --}}
                <div class="col-12 mt-2" id="mc_doc" hidden>
                    <div class="spg-panel" style="background:var(--oro-tinte)">
                        <div class="row g-2 align-items-end">
                            <div class="col-12">
                                <strong style="font-size:.85rem">Respaldo del gasto</strong>
                                <div class="text-muted-warm" style="font-size:.78rem">
                                    Sin comprobante la plata sale de la nada, y eso no se puede justificar
                                    después. Van los tres: número, quién lo emitió y la foto del papel.
                                    <br>
                                    {{-- Quién emite el comprobante cambia según el caso, y no es
                                         evidente: el delivery está obligado a facturar su servicio,
                                         y la propietaria factura su propio retiro con su RUC. --}}
                                    En un <strong>gasto</strong> lo emite el proveedor —el delivery está
                                    obligado a facturar su servicio—. En un <strong>retiro</strong> lo emite
                                    la propietaria con <strong>su</strong> RUC y su punto de expedición
                                    (el salón factura con 001-001 y ella con 001-002).
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="mc_nro">Nº de comprobante</label>
                                <input class="form-control" id="mc_nro" name="nro_comprobante" data-solo="documento" inputmode="numeric"
                                       maxlength="30" value="{{ old('nro_comprobante') }}"
                                       placeholder="001-001-0001234">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="mc_ruc">RUC o cédula</label>
                                <input class="form-control" id="mc_ruc" name="ruc_emisor" data-solo="ruc" inputmode="text"
                                       maxlength="20" value="{{ old('ruc_emisor') }}"
                                       placeholder="80012345-0">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="mc_arch">Foto del comprobante</label>
                                <input class="form-control" id="mc_arch" type="file" name="archivo"
                                       accept="image/*,application/pdf">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mt-2">
                    <button class="btn btn-oro w-100"
                            data-confirmar="Este movimiento entra al arqueo de la caja abierta. ¿Confirmás?">
                        <i class="bi bi-plus-lg"></i> Registrar
                    </button>
                </div>
            </form>

        </div>
    @endif

            {{-- **Los cobros por medio de pago, de las mismas cajas que la tabla.**

         Separa lo que TIENE que estar en el cajón de lo que fue a la cuenta,
         que es la otra mitad de «qué pasó con la plata de esta caja». Estaba en
         la pantalla de la caja, donde contestaba la mitad de una pregunta que
         se hace acá.

         Respeta los mismos filtros: un resumen que mide otra cosa que la tabla
         de abajo es peor que no tenerlo. --}}
    @if ($porMedio)
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-cash-stack"></i> Cobros por medio de pago</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Caja</th><th>Medio</th><th>¿Está en el cajón?</th>
                            <th class="text-end">Cobros</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($porMedio as $m)
                            <tr>
                                <td class="text-muted-warm">{{ $m->caja_nombre }}</td>
                                <td>{{ $m->medio }}</td>
                                <td>
                                    @if ($m->tipo === 'EFECTIVO')
                                        <span class="badge-estado e-ok">sí, contalo</span>
                                    @else
                                        <span class="badge-estado e-muted">no, va a la cuenta</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ (int) $m->cantidad }}</td>
                                <td class="text-end">{{ money($m->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- **Filtros arriba, tabla, paginación**: es la pantalla que más
         registros acumula del módulo. Antes listaba sólo los de la caja
         abierta, que resolvía el caso de hoy y dejaba sin ver los de
         ayer. --}}
    <div class="mt-3">
        <x-filtros :f="$f" />
    </div>

    @if (count($movimientos))
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Cuándo</th><th>Caja</th><th>Movimiento</th>
            <th>Concepto</th><th>Quién</th>
            <th class="text-end">Monto</th><th class="text-end"></th></tr></thead>
        <tbody>
            @foreach ($movimientos as $m)
                <tr>
            <td style="white-space:nowrap">{{ fecha($m->fecha, 'd/m H:i') }}</td>
            <td class="text-muted-warm">{{ $m->caja_nombre }}</td>
            <td>
                {{-- La CLASE dice qué es —un gasto, un retiro,
                     una devolución—; el signo lo dice el color.
                     Con sólo «Ingreso/Egreso» dos movimientos muy
                     distintos se ven igual. --}}
                <span class="badge-estado {{ $m->tipo === 'INGRESO' ? 'e-ok' : 'e-no' }}">
                    {{ $m->clase ?: ($m->tipo === 'INGRESO' ? 'Ingreso' : 'Egreso') }}</span>
            </td>
            <td>
                {{ $m->concepto }}
                @unless ($m->activo)
                    <span class="badge-estado e-no">anulado</span>
                    <div class="text-muted-warm" style="font-size:.75rem">
                {{ $m->anulado_motivo }}</div>
                @endunless
            </td>
            <td class="text-muted-warm" style="font-size:.84rem">{{ $m->quien ?: '—' }}</td>
            <td class="text-end {{ $m->activo ? '' : 'text-muted-warm' }}"
                style="{{ $m->activo ? '' : 'text-decoration:line-through' }}">
                {{ money($m->monto) }}</td>
            {{-- **Se anula, no se borra**: el arqueo tiene que poder
                 explicar qué pasó. Y sólo mientras la caja siga
                 abierta — después del cierre el número ya se contó. --}}
            <td class="text-end">
                {{-- Sólo mientras SU caja siga abierta: después
                     del cierre el arqueo ya se contó, y cambiarlo
                     por atrás dejaría el cierre diciendo un número
                     y la base otro. --}}
                @if ($m->activo && $abierta && (int) $m->id_caja === (int) $abierta->id_caja)
                    <button type="button" class="btn btn-sm btn-outline-neutro"
                    data-bs-toggle="modal" data-bs-target="#anularMov{{ $m->id_movimiento_caja }}">
                <i class="bi bi-x-lg"></i> Anular</button>
                @endif
            </td>
                </tr>
            @endforeach
        </tbody>
            </table>
        </div>

        {{-- Un modal por movimiento: el motivo es obligatorio, porque es
             lo único que explica esa anulación al cerrar la caja. --}}
        @foreach ($movimientos as $m)
            @if ($m->activo && $abierta && (int) $m->id_caja === (int) $abierta->id_caja)
        <div class="modal fade" id="anularMov{{ $m->id_movimiento_caja }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
            <form method="post" action="{{ route('facturacion.caja.movimiento.anular') }}">
                @csrf
                <input type="hidden" name="id_movimiento_caja"
                       value="{{ $m->id_movimiento_caja }}">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-size:1rem">Anular el movimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted-warm" style="font-size:.85rem">
                {{ $m->tipo === 'INGRESO' ? 'Ingreso' : 'Egreso' }} de
                <strong>{{ money($m->monto) }}</strong> — {{ $m->concepto }}.
                El movimiento no se borra: queda anulado y con su motivo,
                y el saldo del cajón deja de contarlo.
                    </p>
                    <label class="form-label" for="mot{{ $m->id_movimiento_caja }}">¿Por qué?</label>
                    <input class="form-control" id="mot{{ $m->id_movimiento_caja }}"
                   name="motivo" maxlength="150" required
                   placeholder="Se cargó dos veces, monto equivocado…">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-neutro"
                    data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-oro">Anular</button>
                </div>
            </form>
                </div>
            </div>
        </div>
            @endif
        @endforeach

    @else
        <div class="spg-panel">
            <div class="spg-vacio">
                <i class="bi bi-cash-coin"></i>
                <div class="t">No hay movimientos con esos filtros</div>
                <div class="d">Probá con otro rango de fechas o con otra caja.</div>
            </div>
        </div>
    @endif

    <x-paginacion :pag="$pag" :f="$f" />
@endsection
