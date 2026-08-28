@extends('layout.app')

@section('titulo', 'Pagos al personal')

@section('contenido')
    <x-encabezado sub="Liquidación de comisiones. Se paga por los servicios realizados que todavía no se liquidaron; el monto lo calcula la base con la comisión vigente de cada servicio." />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-wallet2"></i> Liquidar</h2>

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

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Profesional</th><th class="text-end">Pendientes</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($profs as $p)
                                <tr>
                                    <td>{{ $p->nombre }} {{ $p->apellido }}</td>
                                    <td class="text-end">
                                        @if ((int) $p->pendientes)
                                            <strong>{{ (int) $p->pendientes }}</strong>
                                        @else
                                            <span class="text-muted-warm">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ((int) $p->pendientes)
                                            <form method="post" action="{{ route('facturacion.pagar_personal') }}"
                                                  class="d-flex gap-1 justify-content-end">
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
                                                            @selected($m->tipo === 'EFECTIVO')>{{ $m->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                @include('facturacion._caja_elegir', [
                                                    'cajas' => $cajas,
                                                    'uid' => 'Pers' . $p->id_usuario,
                                                    'rotulo' => '¿De qué caja sale la plata?',
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
                                <tr><td colspan="3" class="text-center text-muted-warm py-3">No hay personal activo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-clock-history"></i> Liquidaciones</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Fecha</th><th>Profesional</th><th>Período</th>
                                <th class="text-end">Monto</th><th>Estado</th><th class="text-end">Revertir</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>{{ fecha($r->fecha, 'd/m/Y') }}</td>
                                    <td>{{ $r->beneficiario ?? $r->profesional ?? '—' }}</td>
                                    <td class="text-muted-warm">{{ $r->periodo }}</td>
                                    <td class="text-end">{{ money($r->monto ?? 0) }}</td>
                                    <td>{!! estado_badge($r->estado) !!}</td>
                                    <td class="text-end">
                                        @if ($r->estado !== 'Revertido' && $r->estado !== 'Anulado')
                                            <button class="btn btn-sm btn-outline-neutro" title="Revertir"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalRev{{ $r->id_pago_personal }}">
                                                <i class="bi bi-arrow-counterclockwise"></i></button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="spg-vacio">
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
