{{-- **Las alergias de la clienta, en UN solo bloque.**

     Se cargan desde dos pantallas —«Mi ficha» y «Mi cuenta»— porque en las dos
     se las busca: la clienta reportó que en Mi cuenta no aparecían y las fue a
     buscar ahí, que es donde uno mira lo suyo. Escrito dos veces se desfasa, y
     entonces una pantalla pediría 300 caracteres y la otra otra cosa.

     Parámetros:
     · $alergias  lo que hay anotado hoy (string o null)
     · $volver    'cuenta' para regresar a Mi cuenta; nada para Mi ficha --}}
@php $volver = $volver ?? ''; @endphp

<h2 class="sgp-form-titulo mb-2">
    <i class="bi bi-exclamation-triangle txt-oro"></i> ¿Sos alérgica a algo?
</h2>
<p class="text-muted-warm" style="font-size:.85rem">
    Anotalo acá y lo van a ver <strong>antes</strong> de prepararte cualquier
    mezcla — tinturas, decolorantes, keratinas, guantes de látex. Aparece
    destacado en la agenda del día, así que no depende de que alguien se
    acuerde de preguntarte.
</p>

<form method="post" action="{{ route('portal.ficha') }}">
    @csrf
    @if ($volver !== '')
        <input type="hidden" name="volver" value="{{ $volver }}">
    @endif
    <label class="form-label" for="alergias{{ $volver }}">Mis alergias</label>
    <textarea class="form-control" id="alergias{{ $volver }}" name="alergias" rows="3" maxlength="300"
              placeholder="Amoníaco, tinturas con PPD, látex…">{{ old('alergias', $alergias ?? '') }}</textarea>
    <div class="form-text mb-3">
        Si no sos alérgica a nada, dejalo vacío. Si alguna vez tuviste una
        reacción y no sabés a qué, escribilo igual: sirve.
    </div>
    <button class="btn btn-oro"><i class="bi bi-check-lg"></i> Guardar</button>
</form>
