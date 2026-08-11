@extends('auth.marco')

@section('titulo', 'Verificá tu cuenta')

@section('formulario')
    <form class="spg-login" method="post" action="{{ route('verificar') }}">
        @csrf
        <div class="logo-big"><i class="bi bi-envelope-check"></i></div>
        <h1 class="text-center" style="font-size:1.15rem;font-weight:500;margin-bottom:.2rem;">
            Verificá tu cuenta</h1>
        <p class="text-center text-muted-warm" style="font-size:.85rem;margin-bottom:1.2rem;">
            Te mandamos un código de 6 dígitos a<br><strong>{{ $email }}</strong>
        </p>

        @if ($errors->any())
            <div class="alert alert-danger py-2" style="font-size:.85rem;">{{ $errors->first() }}</div>
        @endif

        <div class="mb-3">
            <label class="form-label" for="codigo">Código</label>
            <input class="form-control text-center" id="codigo" name="codigo" required
                   inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                   style="font-size:1.4rem;letter-spacing:.4rem">
        </div>

        <button class="btn btn-oro w-100 py-2">Confirmar</button>

        <p class="text-center mt-3 mb-0" style="font-size:.85rem">
            ¿No te llegó?
            <button class="btn btn-link link-oro p-0" name="reenviar" value="1"
                    style="font-size:.85rem;vertical-align:baseline">Reenviar el código</button>
        </p>
    </form>
@endsection
