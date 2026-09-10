@extends('layout.app')

@section('titulo', 'Auditoría')

@section('contenido')
    <x-encabezado sub="Qué se hizo, quién y cuándo. Las anulaciones y reversiones las registra la propia base con un disparador, así que quedan aunque nadie las anote desde la aplicación." />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive spg-tabla-movil">
            <table class="table align-middle">
                <thead>
                    <tr><th>Fecha</th><th>Usuario</th><th class="spg-movil-oculto">Sucursal</th><th>Acción</th><th>Módulo</th>
                        <th class="spg-movil-oculto">Registro</th><th>Detalle</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $a)
                        <tr>
                            <td class="spg-movil-titulo" style="white-space:nowrap" data-label="Fecha">{{ fecha($a->fecha) }}</td>
                            <td data-label="Usuario">{{ $a->usuario }}</td>
                            <td class="text-muted-warm spg-movil-oculto" data-label="Sucursal">{{ $a->sucursal }}</td>
                            <td data-label="Acción"><span class="badge-estado e-prog">{{ $a->accion }}</span></td>
                            <td class="text-muted-warm" data-label="Módulo">{{ $a->modulo }}</td>
                            <td class="text-muted-warm spg-movil-oculto" style="font-size:.8rem" data-label="Registro">
                                {{ $a->tabla_afectada }}{{ $a->id_registro ? ' #' . $a->id_registro : '' }}
                            </td>
                            <td class="text-muted-warm" style="font-size:.82rem" data-label="Detalle">{{ $a->detalle ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
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
