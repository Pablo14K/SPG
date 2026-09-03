@extends('auth.marco')

@section('titulo', 'Crear cuenta')

@section('formulario')
    <form class="spg-login" method="post" action="{{ route('registro') }}">
        @csrf
        <div class="logo-big">@include('layout._marca', ['modo' => 'grande'])</div>
        <h1 class="text-center" style="font-size:1.2rem;font-weight:500;margin-bottom:.2rem;">Crear tu cuenta</h1>
        <p class="text-center text-muted-warm" style="font-size:.85rem;margin-bottom:1.1rem;">
            Para reservar tus citas desde el celular
        </p>

        @if ($errors->any())
            <div class="alert alert-danger py-2" style="font-size:.85rem;">{{ $errors->first() }}</div>
        @endif

        <div class="row g-2">
            <div class="col-6">
                <label class="form-label" for="nombre">Nombre *</label>
                <input class="form-control" id="nombre" name="nombre" required value="{{ old('nombre') }}">
            </div>
            <div class="col-6">
                <label class="form-label" for="apellido">Apellido *</label>
                <input class="form-control" id="apellido" name="apellido" required value="{{ old('apellido') }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="email">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required value="{{ old('email') }}">
                <div class="form-text">Ahí te mandamos el código para activar la cuenta.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="telefono">Celular</label>
                <input class="form-control" id="telefono" name="telefono" data-solo="telefono" inputmode="tel" value="{{ old('telefono') }}"
                       placeholder="0981123456">
                <div class="form-text">Opcional. Nos sirve para avisarte de tus citas.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="username">Usuario *</label>
                <input class="form-control" id="username" name="username" required value="{{ old('username') }}">
            </div>
            <div class="col-6">
                <label class="form-label" for="password">Contraseña *</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6">
            </div>
            <div class="col-6">
                <label class="form-label" for="password2">Repetila *</label>
                <input type="password" class="form-control" id="password2" name="password2" required minlength="6">
            </div>
        </div>

        <button class="btn btn-oro w-100 py-2 mt-3" type="submit">Crear cuenta</button>

        <p class="text-center mt-3 mb-0" style="font-size:.85rem;color:var(--gris-oscuro)">
            ¿Ya tenés cuenta? <a class="link-oro" href="{{ route('login') }}">Ingresá</a>
        </p>
    </form>
@endsection
