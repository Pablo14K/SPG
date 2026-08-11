@extends('layout.app')

@section('titulo', 'Mis recordatorios')

@section('contenido')
    <div class="spg-page-head">
        <h1>Mis recordatorios</h1>
        <div class="sub">Con cuánta anticipación querés que te avisemos de tu cita.</div>
    </div>

    <div class="spg-panel" style="max-width:520px">
        <form method="post" action="{{ route('portal.preferencias') }}">
            @csrf

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo"
                       @checked((int) $pref->activo === 1)>
                <label class="form-check-label" for="activo">Quiero recibir recordatorios por correo</label>
            </div>

            <div class="mb-3">
                <label class="form-label" for="dias_antes">Avisarme con</label>
                <div class="input-group" style="max-width:220px">
                    <input type="number" class="form-control" id="dias_antes" name="dias_antes"
                           min="0" max="15" value="{{ (int) $pref->dias_antes }}">
                    <span class="input-group-text">día(s) antes</span>
                </div>
                <div class="form-text">Con 0 te avisamos el mismo día.</div>
            </div>

            <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
        </form>
    </div>
@endsection
