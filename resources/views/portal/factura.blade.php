@extends('layout.app')

@section('titulo', 'Comprobante ' . $f->nro)

@section('contenido')
    <div class="sgp-page-head">
        <a class="sgp-back" href="{{ route('portal.citas') }}">
            <i class="bi bi-arrow-left"></i> Mis citas</a>
        <h1 class="mt-1">Comprobante {{ $f->nro }}</h1>
        <div class="sub">Lo que se te cobró en esa cita, con el detalle de cada servicio.</div>
    </div>

    <div class="sgp-panel">
        @include('portal._factura_cuerpo', ['papel' => false])

        <div class="mt-3 d-flex gap-2 flex-wrap">
            {{-- `download` en el enlace, y la ruta anotada en `navegaDeVerdad()`
                 de app.js: la barra de carga se encendería y quedaría girando
                 para siempre, porque bajar un archivo no navega. --}}
            <a class="btn btn-oro" download
               href="{{ route('portal.factura_descargar', ['id' => $f->id_factura]) }}">
                <i class="bi bi-download"></i> Bajar en PDF</a>
            <a class="btn btn-outline-neutro" href="{{ route('portal.citas') }}">Volver a mis citas</a>
        </div>
    </div>
@endsection
