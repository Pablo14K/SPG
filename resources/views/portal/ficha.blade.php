@extends('layout.app')

@section('titulo', 'Mi ficha')

@section('contenido')
    <div class="spg-page-head">
        <div>
            <h1>Mi ficha<x-ayuda lado="bottom">Lo que el salón tiene anotado de vos. Las alergias las cargás vos; el resto se cambia pidiéndolo en el salón.</x-ayuda></h1>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            {{-- **Las alergias van primero y solas.** Es lo único de esta
                 pantalla que puede lastimar a alguien si nadie lo mira, y es lo
                 único que la clienta puede cargar: ponerlo debajo de sus datos
                 de contacto lo convertiría en un campo más de un formulario. --}}
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2">
                    <i class="bi bi-exclamation-triangle txt-oro"></i> ¿Sos alérgica a algo?
                </h2>
                <p class="text-muted-warm" style="font-size:.85rem">
                    Anotalo acá y lo van a ver <strong>antes</strong> de prepararte
                    cualquier mezcla — tinturas, decolorantes, keratinas, guantes de
                    látex. Aparece destacado en la agenda del día, así que no depende
                    de que alguien se acuerde de preguntarte.
                </p>

                <form method="post" action="{{ route('portal.ficha') }}">
                    @csrf
                    <label class="form-label" for="alergias">Mis alergias</label>
                    <textarea class="form-control" id="alergias" name="alergias" rows="3" maxlength="300"
                              placeholder="Amoníaco, tinturas con PPD, látex…">{{ old('alergias', $yo->alergias ?? '') }}</textarea>
                    <div class="form-text mb-3">
                        Si no sos alérgica a nada, dejalo vacío. Si alguna vez tuviste
                        una reacción y no sabés a qué, escribilo igual: sirve.
                    </div>
                    <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="spg-panel">
                <h2 class="spg-form-titulo mb-2">
                    <i class="bi bi-person-vcard txt-oro"></i> Mis datos
                </h2>
                <dl class="row mb-0" style="font-size:.9rem">
                    <dt class="col-5 text-muted-warm">Nombre</dt>
                    <dd class="col-7">{{ $yo->nombre ?? '—' }}</dd>
                    <dt class="col-5 text-muted-warm">Cédula</dt>
                    <dd class="col-7">{{ $yo->cedula ?: '—' }}</dd>
                    <dt class="col-5 text-muted-warm">Teléfono</dt>
                    <dd class="col-7">{{ $yo->telefono ?: '—' }}</dd>
                    <dt class="col-5 text-muted-warm">Email</dt>
                    <dd class="col-7">{{ $yo->email ?: '—' }}</dd>
                    <dt class="col-5 text-muted-warm">Dirección</dt>
                    <dd class="col-7">{{ $yo->direccion ?: '—' }}</dd>
                </dl>
                {{-- **Se muestran y no se editan, a propósito.** El correo es con
                     lo que entrás y el teléfono es por donde el salón te llama:
                     cambiarlos es otra decisión, con su verificación. Lo que esto
                     resuelve es poder ver qué figura — que es como se descubre un
                     teléfono mal tipeado. --}}
                <p class="text-muted-warm mb-0 mt-3" style="font-size:.8rem">
                    ¿Hay algo mal? Decíselo al salón y lo corrigen: desde acá no se
                    tocan, porque con el correo entrás a tu cuenta.
                </p>
            </div>
        </div>
    </div>
@endsection
