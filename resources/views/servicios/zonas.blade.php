@extends('layout.app')

@section('titulo', 'Zonas del cuerpo')

@section('contenido')
    <x-encabezado
        sub="Sobre qué parte del cuerpo trabaja cada servicio. <strong>Es lo que decide qué se puede hacer a la vez</strong>: dos servicios de la misma zona se hacen uno después del otro y los tiempos se suman; de zonas distintas se hacen en paralelo y la cita dura lo del más largo." />

    {{-- Un servicio sin zona se puede hacer junto con cualquier cosa, y eso casi
         nunca es lo que el salón quiere. Se dicen por su nombre en vez de dejar
         que se descubra cuando una cita salga durando menos de lo que dura. --}}
    @if (count($sinZona))
        <div class="alert alert-warning">
            <strong>{{ count($sinZona) }} servicio(s) todavía no tienen zona</strong>, así que el sistema
            los deja hacer junto con cualquier otra cosa:
            @foreach ($sinZona as $s)
                <a class="link-oro" href="{{ route('servicios.form', $s->id_servicio) }}">{{ $s->nombre }}</a>{{ ! $loop->last ? ' · ' : '' }}
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2"><i class="bi bi-plus-lg"></i> Nueva zona</h2>
                <form method="post" action="{{ route('servicios.zona.crear') }}" class="d-flex gap-2">
                    @csrf
                    <input class="form-control" name="nombre" placeholder="Ej. Cabello" required maxlength="60">
                    <button class="btn btn-oro">Agregar</button>
                </form>
                <p class="text-muted-warm mb-0 mt-2" style="font-size:.82rem">
                    Una zona es una parte del cuerpo que se ocupa entera mientras dura el servicio:
                    cabello, manos, pies, rostro. Si el salón suma masajes o depilación, se agregan acá.
                </p>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="spg-panel">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Zona</th><th class="text-end">Servicios</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $z)
                                <tr>
                                    <td>
                                        <form method="post" action="{{ route('servicios.zona.editar') }}"
                                              class="d-flex gap-2 align-items-center">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $z->id_zona }}">
                                            <input class="form-control form-control-sm" name="nombre"
                                                   value="{{ $z->nombre }}" required maxlength="60">
                                            <button class="btn btn-sm btn-outline-neutro" title="Guardar el nombre">
                                                <i class="bi bi-check-lg"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-end">{{ entero($z->usos) }}</td>
                                    <td class="text-end">
                                        {{-- No se borra con servicios adentro: quedarían sin zona y
                                             pasarían a poder hacerse junto con cualquier cosa, en
                                             silencio. El servidor lo vuelve a comprobar. --}}
                                        <form method="post" action="{{ route('servicios.zona.borrar') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $z->id_zona }}">
                                            <button class="btn btn-sm btn-outline-neutro"
                                                    @disabled((int) $z->usos > 0)
                                                    data-confirmar="¿Eliminar la zona {{ $z->nombre }}?">
                                                <i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted-warm">
                                        Todavía no hay ninguna zona cargada. Sin zonas, todos los servicios
                                        se pueden hacer a la vez.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
