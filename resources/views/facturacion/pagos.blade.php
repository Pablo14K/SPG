@extends('layout.app')

@section('titulo', 'Pagos al personal')

@section('contenido')
    <x-encabezado sub="Liquidación de comisiones. Se paga por los servicios realizados que todavía no se liquidaron; el monto lo calcula la base con la comisión vigente de cada servicio." />

    <div class="row g-3">
        <div class="col-12">
            <div class="sgp-panel">
                <h2 class="sgp-form-titulo mb-2"><i class="bi bi-wallet2"></i> Liquidar</h2>

                {{-- **De qué cajón sale la plata.** Con uno solo no se pregunta,
                     pero se dice cuál es: quien liquida tiene que saber en qué
                     arqueo va a aparecer ese egreso. Con dos o más, cada fila
                     trae su combo. --}}
                @if (count($cajas) === 1)
                    <p class="text-muted-warm mb-2" style="font-size:.82rem">
                        <i class="bi bi-safe"></i> Sale de <strong>{{ $cajas[0]->nombre }}</strong>@if ($cajas[0]->responsable),
                        abierta por {{ $cajas[0]->responsable }}@endif.
                    </p>
                @elseif (count($cajas) > 1)
                    <p class="text-muted-warm mb-2" style="font-size:.82rem">
                        <i class="bi bi-safe"></i> Hay {{ count($cajas) }} cajas abiertas en este local:
                        elegí de cuál sale antes de liquidar.
                    </p>
                @endif

                <div class="table-responsive sgp-tabla-movil">
                    <table class="table table-sm align-middle mb-0">
                        {{-- **Cuánto se le debe, no sólo cuántos servicios.**
                             La tabla decía «3 pendientes» y ofrecía Liquidar:
                             había que apretar para enterarse del monto, o sea
                             decidir un pago sin ver la cifra. --}}
                        <thead><tr><th>Profesional</th><th class="text-end">Pendientes</th>
                            <th class="text-end">A pagar</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($profs as $p)
                                <tr>
                                    <td class="sgp-movil-titulo" data-label="Profesional">
                                        {{ $p->nombre }} {{ $p->apellido }}
                                        @if ($p->desde_cuando)
                                            <div class="text-muted-warm" style="font-size:.75rem">
                                                sin liquidar desde el {{ fecha($p->desde_cuando, 'd/m/Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end" data-label="Pendientes">
                                        @if ((int) $p->pendientes)
                                            <strong>{{ (int) $p->pendientes }}</strong>
                                        @else
                                            <span class="text-muted-warm">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end" data-label="A pagar">
                                        @if ((int) $p->pendientes)
                                            {{-- **«Gs. 0» acá casi nunca significa que ganó
                                                 cero**: significa que nadie le cargó una
                                                 comisión. `fn_comision_servicio` devuelve 0
                                                 en los dos casos y son indistinguibles. --}}
                                            @if ((float) $p->a_pagar > 0)
                                                <strong class="txt-oro">{{ money($p->a_pagar) }}</strong>
                                            @else
                                                <span class="text-muted-warm" title="Cargale la comisión en Personal → Comisiones">sin comisión cargada</span>
                                            @endif
                                        @else
                                            <span class="text-muted-warm">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end sgp-movil-acciones">
                                        @if ((int) $p->pendientes)
                                            <form method="post" action="{{ route('facturacion.pagar_personal') }}"
                                                  class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                                @csrf
                                                <input type="hidden" name="id_usuario" value="{{ $p->id_usuario }}">
                                                <input class="form-control form-control-sm" name="periodo"
                                                       value="{{ date('m/Y') }}" style="width:80px" maxlength="10"
                                                       aria-label="Período">
                                                {{-- Con qué se le paga. Hace falta para el arqueo: lo que sale
                                                     en efectivo baja del cajón y lo que sale por banco, no. --}}
                                                <select class="form-select form-select-sm" name="id_metodo_pago"
                                                        style="width:130px" aria-label="Medio de pago" required>
                                                    @foreach ($metodos as $m)
                                                        <option value="{{ $m->id_metodo_pago }}"
                                                            data-tipo="{{ $m->tipo }}"
                                                            @selected($m->tipo === 'EFECTIVO')>{{ $m->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                @include('facturacion._caja_elegir', [
                                                    'cajas' => $cajas,
                                                    'uid' => 'Pers' . $p->id_usuario,
                                                    'rotulo' => '¿De qué caja sale la plata?',
                                                    'compacto' => true,
                                                ])
                                                {{-- Y si se le transfiere, de qué cuenta:
                                                     el cajón no se toca y hasta acá nada
                                                     miraba si en el banco había plata. --}}
                                                @include('facturacion._cuenta_elegir', [
                                                    'cuentas' => $cuentasBanco,
                                                    'uid' => 'Pers' . $p->id_usuario,
                                                    'compacto' => true,
                                                ])
                                                <button class="btn btn-sm btn-oro"
                                                        data-confirmar="Se van a liquidar {{ (int) $p->pendientes }} servicio(s) de {{ $p->nombre }}. ¿Confirmás?">
                                                    Liquidar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted-warm py-3">No hay personal activo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="sgp-panel">
                <h2 class="sgp-form-titulo mb-2"><i class="bi bi-clock-history"></i> Liquidaciones</h2>
                {{-- Cortaba con `LIMIT 200` sin decirlo: a partir de la fila 201
                     las liquidaciones dejaban de existir para quien mira. --}}
                <x-filtros :f="$f" />
                <x-paginacion :pag="$pag" :f="$f" />
                <div class="table-responsive sgp-tabla-movil">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Fecha</th><th>Profesional</th>
                                <th class="text-end">Monto</th><th class="text-end"></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td class="sgp-movil-titulo" data-label="Fecha">{{ fecha($r->fecha, 'd/m/Y') }}</td>
                                    <td data-label="Profesional">{{ $r->beneficiario ?? $r->profesional ?? '—' }}</td>
                                    <td class="text-end" data-label="Monto">{{ money($r->monto ?? 0) }}</td>
                                    <td class="text-end sgp-movil-acciones" style="white-space:nowrap">
                                        <button class="sgp-btn-detalle" data-bs-toggle="collapse"
                                                data-bs-target="#detLiq{{ $r->id_pago_personal }}" aria-expanded="false">
                                            <i class="bi bi-chevron-down"></i> Detalle
                                        </button>
                                        @if ($r->estado !== 'Revertido' && $r->estado !== 'Anulado')
                                            <button class="btn btn-sm btn-outline-neutro" title="Revertir"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalRev{{ $r->id_pago_personal }}">
                                                <i class="bi bi-arrow-counterclockwise"></i></button>
                                        @endif
                                    </td>
                                </tr>
                                {{-- **El detalle abre el TRABAJO que se está pagando.**
                                     Decía el período y el estado, o sea nada que la fila
                                     no dijera ya: un monto sin su desglose no se puede
                                     comprobar ni defender, y quien revisa la planilla tres
                                     meses después no tiene de dónde agarrarse. Ahora salen
                                     los servicios que entraron, de qué cita, para quién,
                                     con qué comprobante y cuánto le tocó de cada uno. --}}
                                <tr class="sgp-fila-detalle">
                                    <td colspan="4">
                                        <div class="collapse" id="detLiq{{ $r->id_pago_personal }}">
                                            <div class="sgp-det-cuerpo">
                                                <div class="sgp-det-grid">
                                                    <div>
                                                        <dt>Período</dt>
                                                        <dd>{{ $r->periodo ?: '—' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Estado</dt>
                                                        <dd>{!! estado_badge($r->estado) !!}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Servicios liquidados</dt>
                                                        <dd>{{ (int) ($r->servicios ?? 0) }}</dd>
                                                    </div>
                                                </div>

                                                @php $sgpLineas = $detalle[$r->id_pago_personal] ?? []; @endphp
                                                @if ($sgpLineas)
                                                    <div class="table-responsive mt-2">
                                                        <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
                                                            <thead>
                                                                <tr>
                                                                    <th>Cuándo</th><th>Servicio</th><th>A quién</th>
                                                                    <th>Comprobante</th><th class="text-end">Le tocó</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($sgpLineas as $sgpL)
                                                                    <tr>
                                                                        <td style="white-space:nowrap">
                                                                            {{ fecha($sgpL->fecha_hora, 'd/m/Y H:i') }}
                                                                        </td>
                                                                        <td>
                                                                            {{ $sgpL->servicio }}
                                                                            @if ($sgpL->precio_unitario !== null)
                                                                                <div class="text-muted-warm" style="font-size:.76rem">
                                                                                    se facturó {{ money((float) $sgpL->precio_unitario * (float) $sgpL->cantidad) }}</div>
                                                                            @endif
                                                                        </td>
                                                                        <td>
                                                                            {{-- Quien SE ATIENDE, que en la cita para otra
                                                                                 persona no es la que la pidió. --}}
                                                                            {{ $sgpL->para ?: $sgpL->cliente }}
                                                                            @if ($sgpL->para)
                                                                                <div class="text-muted-warm" style="font-size:.76rem">
                                                                                    la pidió {{ $sgpL->cliente }}</div>
                                                                            @elseif ((int) $sgpL->personas > 1)
                                                                                <div class="text-muted-warm" style="font-size:.76rem">
                                                                                    cita de {{ (int) $sgpL->personas }} personas</div>
                                                                            @endif
                                                                        </td>
                                                                        <td>
                                                                            @if ($sgpL->nro)
                                                                                {{-- **Con qué número de comprobante está ligado
                                                                                     ese servicio.** Sale del renglón de la
                                                                                     factura que `servicio_realizado` apunta,
                                                                                     no de la cita: en la cita de varias cada
                                                                                     una puede irse con el suyo. --}}
                                                                                <a class="link-oro" href="{{ route('facturacion.factura_ver', ['id' => $sgpL->id_factura]) }}">
                                                                                    {{ $sgpL->nro }}</a>
                                                                                <div class="text-muted-warm" style="font-size:.76rem">{{ $sgpL->comprobante }}</div>
                                                                            @else
                                                                                <span class="text-muted-warm"
                                                                                      title="Se atendió, pero todavía no se le emitió el comprobante">sin comprobante</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="text-end" style="white-space:nowrap">
                                                                            {{-- El monto de ESE día, no el que daría la comisión
                                                                                 de hoy: si el salón la cambia, lo ya liquidado
                                                                                 tiene que seguir diciendo lo que se pagó. --}}
                                                                            {{ money($sgpL->monto) }}
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                            <tfoot>
                                                                <tr>
                                                                    <th colspan="4" class="text-end">Total liquidado</th>
                                                                    <th class="text-end">{{ money($r->monto ?? 0) }}</th>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="text-muted-warm mb-0 mt-2" style="font-size:.82rem">
                                                        Esta liquidación no tiene servicios cargados.
                                                        @if ($r->estado === 'Revertido')
                                                            Se revirtió, así que volvieron a quedar pendientes.
                                                        @endif
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="sgp-vacio">
                                            <i class="bi bi-wallet2"></i>
                                            <div class="t">Todavía no se liquidó ningún pago.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @foreach ($rows as $r)
        @continue ($r->estado === 'Revertido' || $r->estado === 'Anulado')
        <div class="modal fade" id="modalRev{{ $r->id_pago_personal }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('facturacion.revertir_pago_personal') }}">
                        @csrf
                        <input type="hidden" name="id_pago_personal" value="{{ $r->id_pago_personal }}">
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:1rem">Revertir la liquidación</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted-warm" style="font-size:.85rem">
                                Los servicios de esa liquidación vuelven a quedar pendientes y se van a poder
                                liquidar de nuevo. El motivo queda en la auditoría.
                            </p>
                            <label class="form-label" for="motRev{{ $r->id_pago_personal }}">Motivo *</label>
                            <input class="form-control" id="motRev{{ $r->id_pago_personal }}"
                                   name="motivo" required maxlength="200">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-oro">Revertir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
