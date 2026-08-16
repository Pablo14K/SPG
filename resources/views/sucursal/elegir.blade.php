@extends('auth.marco')

@section('titulo', 'Elegí la sucursal')

@section('formulario')
    <div class="spg-panel" style="max-width:560px;width:100%">
        <h1 style="font-size:1.15rem;font-weight:500;margin-bottom:.2rem">
            <i class="bi bi-shop txt-oro"></i> ¿En qué sucursal vas a trabajar?
        </h1>
        <p class="text-muted-warm" style="font-size:.86rem">
            La agenda, la caja y el stock son de la sucursal que elijas. Podés cambiarla
            después desde <strong>Mi cuenta</strong>, sin volver a entrar.
        </p>

        <div class="d-grid gap-2 mt-3">
            @foreach ($sucursales as $s)
                <form method="post" action="{{ route('sucursal.entrar') }}">
                    @csrf
                    <input type="hidden" name="id_sucursal" value="{{ $s->id_sucursal }}">
                    <button class="btn {{ (int) $s->id_sucursal === $activa ? 'btn-oro' : 'btn-rapido' }} w-100 text-start py-2">
                        <i class="bi bi-shop"></i>
                        <strong>{{ $s->nombre }}</strong>
                        @if ($s->ciudad || $s->direccion)
                            <span class="d-block text-muted-warm" style="font-size:.8rem;margin-left:1.4rem">
                                {{ trim(($s->direccion ? $s->direccion . ' · ' : '') . ($s->ciudad ?: '')) }}
                            </span>
                        @endif
                        @if ((int) $s->id_sucursal === $activa)
                            <span class="d-block" style="font-size:.78rem;margin-left:1.4rem">estás acá ahora</span>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>

        <div class="mt-3 d-flex justify-content-between align-items-center">
            <span class="text-muted-warm" style="font-size:.78rem">
                {{ count($sucursales) }} sucursal(es) disponibles para tu cuenta
            </span>
            {{-- Una salida que anda sin JavaScript, como la pantalla de la huella --}}
            <form method="post" action="{{ route('salir') }}">
                @csrf
                <button class="btn btn-sm btn-outline-neutro">Cerrar sesión</button>
            </form>
        </div>
    </div>
@endsection
