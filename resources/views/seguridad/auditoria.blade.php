@extends('layout.app')

@section('titulo', 'Auditoría')

@section('contenido')
    <x-encabezado sub="Qué se hizo, quién y cuándo. Las anulaciones y reversiones las registra la propia base con un disparador, así que quedan aunque nadie las anote desde la aplicación." />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive spg-tabla-movil">
            <table class="table align-middle">
                <thead>
                    <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th>
                        <th></th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $a)
                        {{-- Main row: only essential columns --}}
                        <tr>
                            <td class="spg-movil-titulo" style="white-space:nowrap" data-label="Fecha">{{ fecha($a->fecha) }}</td>
                            <td data-label="Usuario">{{ $a->usuario }}</td>
                            <td data-label="Acción"><span class="badge-estado e-prog">{{ $a->accion }}</span></td>
                            <td class="text-muted-warm" data-label="Módulo">{{ $a->modulo }}</td>
                            <td class="text-end spg-movil-acciones" style="white-space:nowrap">
                                <button class="spg-btn-detalle" data-bs-toggle="collapse"
                                        data-bs-target="#detAud{{ $a->id_auditoria }}" aria-expanded="false"
                                        aria-controls="detAud{{ $a->id_auditoria }}">
                                    <i class="bi bi-chevron-down"></i> Detalle
                                </button>
                            </td>
                        </tr>
                        {{-- Expandable detail row --}}
                        <tr class="spg-fila-detalle">
                            <td colspan="5">
                                <div class="collapse" id="detAud{{ $a->id_auditoria }}">
                                    <div class="spg-det-cuerpo">
                                        <div class="spg-det-grid">
                                            <div>
                                                <dt>Sucursal</dt>
                                                <dd>{{ $a->sucursal }}</dd>
                                            </div>
                                            <div>
                                                <dt>Registro</dt>
                                                <dd>{{ $a->tabla_afectada }}{{ $a->id_registro ? ' #' . $a->id_registro : '' }}</dd>
                                            </div>
                                            <div>
                                                <dt>Detalle</dt>
                                                <dd>{{ $a->detalle ?: '—' }}</dd>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="spg-vacio">
                                    <i class="bi bi-journal-text"></i>
                                    <div class="t">{{ $f['activos'] ? 'Nada coincide con esos filtros.' : 'Todavía no hay registros de auditoría.' }}</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-paginacion :pag="$pag" :f="$f" />
    </div>
@endsection
