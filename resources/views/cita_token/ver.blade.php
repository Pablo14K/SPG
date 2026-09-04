{{-- Reprogramar o cancelar desde el enlace del correo, sin iniciar sesión.
     Acá la credencial es el token, así que no hay barra de módulos ni pie con
     secciones: quien entra puede no tener cuenta. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi cita · {{ config('app.name') }}</title>
    @include('layout._favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ recurso('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="container py-4" style="max-width:620px">

    <div class="text-center mb-3">
        <div class="logo-big">@include('layout._marca', ['modo' => 'grande'])</div>
        <h1 style="font-size:1.2rem;font-weight:500">{{ config('app.name') }}</h1>
    </div>

    @foreach (session('spg_flash', []) as $f)
        @php $cls = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$f['tipo']] ?? 'secondary'; @endphp
        <div class="alert alert-{{ $cls }}" style="font-size:.88rem">{{ $f['msg'] }}</div>
    @endforeach

    @if (! $cita)
        <div class="spg-panel text-center">
            <div style="font-size:2rem;color:var(--oro)"><i class="bi bi-link-45deg"></i></div>
            <h2 style="font-size:1.05rem;font-weight:500">Ese enlace ya no sirve</h2>
            <p class="text-muted-warm" style="font-size:.9rem">
                Los enlaces duran 30 días y dejan de valer cuando la cita se cancela.
                Escribinos y te pasamos uno nuevo.
            </p>
        </div>
    @else
        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-calendar-event"></i> Tu cita</h2>
            <p class="mb-1">
                <strong>{{ fecha($cita->fecha_hora) }}</strong>
                @if ($cal) · {{ (int) $cal->duracion_min }} min @endif
            </p>
            @if ($cal)
                <p class="text-muted-warm mb-2" style="font-size:.88rem">
                    {{ $cal->servicios ?: 'Sin servicios cargados' }} · con {{ $cal->profesional }}
                </p>
            @endif

            {{-- Dos caminos, porque uno solo no alcanza, y CADA UNO SE NOMBRA POR
                 LO QUE ES. Antes el de Google decía «Agendar en mi calendario» y
                 el otro «Bajar el archivo (.ics)»: el primero parecía el único
                 botón de calendario y el segundo una descarga técnica, así que
                 quien no usa Google leía que no había opción para su teléfono.
                 El .ics es justamente la genérica —la abre el calendario que
                 traiga el celular, sea iPhone, Samsung o el que sea—; el de
                 Google existe porque en Android el .ics suele quedarse en la
                 carpeta de descargas sin abrirse. --}}
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-sm btn-oro" download
                   href="{{ route('cita.calendario', ['t' => $codigo]) }}">
                    <i class="bi bi-phone"></i> Calendario del celular</a>
                @if ($urlGoogle)
                    <a class="btn btn-sm btn-outline-neutro" href="{{ $urlGoogle }}" target="_blank" rel="noopener">
                        <i class="bi bi-google"></i> Google Calendar</a>
                @endif
            </div>
            <div class="form-text">
                Así tu teléfono también te avisa.
                @if ($urlGoogle)
                    El primero sirve para cualquier calendario —iPhone, Samsung, Outlook—; el segundo,
                    si usás Google Calendar.
                @endif
            </div>
        </div>

        <div class="spg-panel mb-3">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-arrow-repeat"></i> Cambiar la fecha</h2>
            <form method="post" action="{{ route('cita.token.guardar') }}">
                @csrf
                <input type="hidden" name="t" value="{{ $codigo }}">

                <div class="mb-2">
                    <label class="form-label" for="fecha_hora">Nueva fecha y hora</label>
                    <input type="datetime-local" class="form-control" id="fecha_hora" name="fecha_hora" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="id_usuario">¿Con quién?</label><x-ayuda>Si tu profesional no está disponible, podés elegir a otra persona del equipo.</x-ayuda>
                    <select class="form-select" id="id_usuario" name="id_usuario">
                        <option value="0">Con quien me atendía</option>
                        @foreach ($profs as $p)
                            <option value="{{ $p->id_usuario }}">{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="btn btn-oro w-100">Reprogramar mi cita</button>
            </form>
        </div>

        <div class="spg-panel">
            <h2 class="spg-form-titulo mb-2"><i class="bi bi-x-circle"></i> ¿No vas a poder venir?</h2>
            <form method="post" action="{{ route('cita.token.guardar') }}">
                @csrf
                <input type="hidden" name="t" value="{{ $codigo }}">
                <input type="hidden" name="cancelar" value="1">
                <button class="btn btn-outline-neutro w-100"
                        data-confirmar="¿Cancelar tu cita? El horario queda libre para otra persona.">
                    Cancelar la cita</button>
            </form>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ recurso('js/app.js') }}"></script>
</body>
</html>
