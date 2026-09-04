@extends('auth.marco')

@section('titulo', 'Recuperar contraseña')

@section('formulario')
    <form class="spg-login" method="post" action="{{ route('recuperar') }}">
        @csrf
        <div class="logo-big"><i class="bi bi-key"></i></div>
        <h1 class="text-center" style="font-size:1.15rem;font-weight:500;margin-bottom:.2rem;">
            ¿Olvidaste tu contraseña?</h1>
        <p class="text-center text-muted-warm" style="font-size:.85rem;margin-bottom:1.2rem;">
            Poné tu email y te mandamos un código para cambiarla.
        </p>

        <div class="mb-3">
            <label class="form-label" for="email">Email</label><x-ayuda campo="email" />
            <input type="email" class="form-control" id="email" name="email" required autofocus>
        </div>

        <button class="btn btn-oro w-100 py-2">Enviarme el código</button>

        <p class="text-center mt-3 mb-0" style="font-size:.85rem">
            <a class="link-oro" href="{{ route('login') }}">Volver a ingresar</a>
        </p>
    </form>
@endsection
