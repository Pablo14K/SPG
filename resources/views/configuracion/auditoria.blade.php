@extends('layout.app')

@section('titulo', 'Auditoría')

@section('contenido')
    <x-encabezado sub="Qué se hizo, quién y cuándo. Las anulaciones y reversiones las registra la propia base con un disparador, así que quedan aunque nadie las anote desde la aplicación." />

    <div class="spg-panel">
        <x-filtros :f="$f" />

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th>
                        <th>Registro</th><th>Detalle</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $a)
                        <tr>
                            <td style="white-space:nowrap">{{ fecha($a->fecha) }}</td>
                            <td>{{ $a->usuario }}</td>
                            <td><span class="badge-estado e-prog">{{ $a->accion }}</span></td>
                            <td class="text-muted-warm">{{ $a->modulo }}</td>
                            <td class="text-muted-warm" style="font-size:.8rem">
                                {{ $a->tabla_afectada }}{{ $a->id_registro ? ' #' . $a->id_registro : '' }}
                            </td>
                            <td class="text-muted-warm" style="font-size:.82rem">{{ $a->detalle ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
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
