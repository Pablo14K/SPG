@extends('layout.app')

@section('titulo', 'Confirmar el cambio')

@section('contenido')
    <div class="spg-page-head">
        <h1>Confirmá el cambio</h1>
        <div class="sub">
            Te mandamos un código a <strong>{{ $email }}</strong>.
            Tenés {{ $minutos }} minuto(s) para usarlo.
        </div>
    </div>

    <div class="spg-panel" style="max-width:460px">
        <form method="post" action="{{ route('cuenta.password_confirmar') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="codigo">Código de 6 dígitos</label><x-ayuda campo="codigo" />
                <input class="form-control text-center" id="codigo" name="codigo" required autofocus
                       inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                       style="font-size:1.4rem;letter-spacing:.4rem">
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Confirmar el cambio</button>
                <button class="btn btn-outline-neutro" name="reenviar" value="1">Reenviar el código</button>
            </div>
        </form>

        <hr class="my-3">

        <form method="post" action="{{ route('cuenta.password_cancelar') }}">
            @csrf
            <button class="btn btn-sm btn-outline-neutro"
                    data-confirmar="¿Descartar el cambio? Tu contraseña va a seguir siendo la de antes.">
                Cancelar el cambio
            </button>
            <x-ayuda>Mientras no confirmes, tu contraseña sigue siendo la de siempre.</x-ayuda>
        </form>
    </div>
@endsection
