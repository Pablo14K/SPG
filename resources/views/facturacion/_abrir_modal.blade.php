{{-- **Abrir una caja, en un modal, desde la tarjeta.**

     Es la otra mitad de «sin doble paso»: «Abrir caja» llevaba a la pantalla
     de la caja, que no tenía más que este formulario. Se abre acá mismo y se
     vuelve a la lista con la caja ya abierta.

     Parámetros: $cajon (la fila de `caja_fisica`) y $sufijo para los ids. --}}
@php $sufijo = $sufijo ?? ''; @endphp
<div class="modal fade" id="modalAbrir{{ $sufijo }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="{{ route('facturacion.caja.abrir') }}" class="modal-content">
        @csrf
        <input type="hidden" name="id_caja_fisica" value="{{ $cajon->id_caja_fisica }}">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-unlock"></i> Abrir · {{ $cajon->nombre }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
            <p class="text-muted-warm" style="font-size:.85rem">
                El monto inicial es el efectivo con el que arranca el cajón. Al cerrar se
                cuenta lo que hay y el sistema dice si cuadra.
            </p>
            <label class="form-label" for="monto_inicial{{ $sufijo }}">Monto inicial en efectivo</label><x-ayuda campo="monto_inicial" />
            <div class="input-group mb-3">
                <span class="input-group-text">Gs.</span>
                <input class="form-control input-miles" id="monto_inicial{{ $sufijo }}" name="monto_inicial"
                       inputmode="numeric" data-min="0" value="0" required>
            </div>
            <label class="form-label" for="obsApertura{{ $sufijo }}">
                Observación <span class="text-muted-warm">(opcional)</span></label>
            <input class="form-control" id="obsApertura{{ $sufijo }}" name="observacion" maxlength="255"
                   placeholder="Con qué se abre, si hay algo que aclarar">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-oro"><i class="bi bi-unlock"></i> Abrir caja</button>
        </div>
    </form>
    </div>
</div>
