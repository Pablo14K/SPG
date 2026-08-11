@extends('auth.marco')

@section('titulo', 'Nueva contraseña')

@section('formulario')
    <form class="spg-login" method="post" action="{{ route('recuperar.codigo') }}">
        @csrf
        <div class="logo-big"><i class="bi bi-shield-lock"></i></div>
        <h1 class="text-center" style="font-size:1.15rem;font-weight:500;margin-bottom:.2rem;">
            Poné tu contraseña nueva</h1>
        <p class="text-center text-muted-warm" style="font-size:.85rem;margin-bottom:1.2rem;">
            Con el código que te mandamos a<br><strong>{{ $email }}</strong>
        </p>

        <div class="mb-3">
            <label class="form-label" for="codigo">Código</label>
            <input class="form-control text-center" id="codigo" name="codigo" required
                   inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                   style="font-size:1.3rem;letter-spacing:.4rem">
        </div>
        <div class="mb-2">
            <label class="form-label" for="nueva">Contraseña nueva</label>
            <input type="password" class="form-control" id="nueva" name="nueva" required minlength="6">
        </div>
        <div class="mb-3">
            <label class="form-label" for="nueva2">Repetila</label>
            <input type="password" class="form-control" id="nueva2" name="nueva2" required minlength="6">
        </div>

        <button class="btn btn-oro w-100 py-2">Cambiar la contraseña</button>
    </form>
@endsection
