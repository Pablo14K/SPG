<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Canje;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ClientesController extends Controller
{
    public function index(): View
    {
        return view('clientes.index', [
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'clientes.registro', 'ruta' => 'clientes.lista', 'ic' => 'people',
                 't' => 'Clientes', 'd' => 'Registro y datos de contacto'],
                ['p' => 'clientes.fidelizacion', 'ruta' => 'clientes.fidelizacion', 'ic' => 'award',
                 't' => 'Fidelización', 'd' => 'Niveles, visitas y puntos'],
                ['p' => 'clientes.canjes', 'ruta' => 'clientes.canjes', 'ic' => 'gift',
                 't' => 'Canjes por puntos', 'd' => 'Qué se lleva la clienta con sus puntos'],
                ['p' => 'clientes.valoraciones', 'ruta' => 'clientes.valoraciones', 'ic' => 'star',
                 't' => 'Valoraciones', 'd' => 'Calificaciones de los servicios'],
            ]),
        ]);
    }

    public function lista(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar',
                    'ph' => 'Nombre, cédula, teléfono o email', 'ancho' => '300px'],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos', '1' => 'Activos', '0' => 'Inactivos']],
            'nivel' => ['tipo' => 'select', 'etiqueta' => 'Nivel',
                        'opciones' => ['' => 'Todos'] + $this->niveles()],
        ]);
        $f['csv'] = true;

        // El WHERE se arma una sola vez y lo comparten el COUNT y la lista: si
        // se separaran, el «de 137» del pie podría no coincidir con lo que se ve.
        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(
                ["CONCAT(pe.nombre,' ',pe.apellido)", 'pe.cedula', 'pe.telefono', 'pe.email'],
                Listado::valor($f, 'q'), 'q', $par
            );
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'c.activo = :est';
            $par['est'] = (int) Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'nivel')) {
            $w[] = 'fn_cliente_nivel(c.id_cliente) = :niv';
            $par['niv'] = (int) Listado::valor($f, 'nivel');
        }

        $desde = 'FROM cliente c JOIN persona pe ON pe.id_persona = c.id_persona WHERE ' . implode(' AND ', $w);
        $cols = "c.id_cliente, pe.nombre, pe.apellido, pe.cedula, pe.telefono, pe.email, c.activo,
                 fn_cliente_visitas(c.id_cliente) AS visitas";
        $orden = 'ORDER BY pe.apellido, pe.nombre';

        if (Listado::pideExport()) {
            return Listado::exportar('clientes',
                ['Cliente', 'Cédula', 'Teléfono', 'Email', 'Visitas', 'Estado'],
                array_map(fn ($c) => [
                    $c->apellido . ', ' . $c->nombre, $c->cedula, $c->telefono, $c->email,
                    $c->visitas, $c->activo ? 'Activo' : 'Inactivo',
                ], DB::select("SELECT $cols $desde $orden", $par)),
                $f, 'Clientes'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));
        $clientes = DB::select("SELECT $cols $desde $orden LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par);

        return view('clientes.lista', compact('clientes', 'f', 'pag'));
    }

    public function form(int $id = 0): View|RedirectResponse
    {
        $c = $id ? $this->cliente($id) : null;
        if ($id && ! $c) {
            flash('Cliente no encontrado.', 'error');

            return redirect()->route('clientes.lista');
        }

        return view('clientes.form', ['c' => $c]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_cliente', 0);
        $datos = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'apellido' => trim((string) $request->input('apellido', '')),
            'cedula' => trim((string) $request->input('cedula', '')) ?: null,
            'ruc' => trim((string) $request->input('ruc', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'fecha_nacimiento' => $request->input('fecha_nacimiento') ?: null,
            // `persona.direccion` existía y no la capturaba ninguna pantalla:
            // la columna quedaba siempre vacía.
            'direccion' => trim((string) $request->input('direccion', '')) ?: null,
            'observaciones' => trim((string) $request->input('observaciones', '')) ?: null,
        ];
        $volver = $id ? redirect()->route('clientes.form', $id) : redirect()->route('clientes.form');

        $error = null;
        if ($datos['nombre'] === '' || $datos['apellido'] === '') {
            $error = 'Nombre y apellido son obligatorios.';
        } elseif ($datos['email'] && ! filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no tiene un formato válido.';
        } elseif ($datos['fecha_nacimiento'] && (! strtotime($datos['fecha_nacimiento']) || $datos['fecha_nacimiento'] > date('Y-m-d'))) {
            $error = 'La fecha de nacimiento no es válida.';
        } else {
            $error = Persona::error($datos);
        }

        // La cédula y el RUC son únicos a nivel de persona, no de cliente: si
        // ya existen, puede ser la misma persona cargada como empleada.
        $personaActual = $id ? (int) DB::scalar('SELECT id_persona FROM cliente WHERE id_cliente = ?', [$id]) : 0;
        if (! $error) {
            $choque = Persona::porDocumento($datos['cedula'], $datos['ruc'], $personaActual);
            if ($choque) {
                $yaCliente = (int) DB::scalar('SELECT COUNT(*) FROM cliente WHERE id_persona = ?', [$choque]);
                $error = $yaCliente
                    ? 'Ya existe otro cliente con esa cédula o ese RUC.'
                    : 'Esa cédula o RUC ya está cargada en el sistema (como personal o proveedor). Revisá los datos.';
            }
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        try {
            DB::transaction(function () use ($id, $datos, $personaActual) {
                if ($id) {
                    Persona::guardar($personaActual, $datos);
                    DB::update('UPDATE cliente SET observaciones = :obs WHERE id_cliente = :id',
                        ['obs' => $datos['observaciones'], 'id' => $id]);
                    Auditoria::registrar('MODIFICACION', 'Clientes', 'cliente', $id,
                        $datos['nombre'] . ' ' . $datos['apellido']);
                    flash('Cliente actualizado.');
                } else {
                    $idPersona = Persona::guardar(null, $datos);
                    DB::insert('INSERT INTO cliente (id_persona, observaciones) VALUES (?,?)',
                        [$idPersona, $datos['observaciones']]);
                    Auditoria::registrar('ALTA', 'Clientes', 'cliente', (int) DB::getPdo()->lastInsertId(),
                        $datos['nombre'] . ' ' . $datos['apellido']);
                    flash('Cliente registrado.');
                }
            });
        } catch (Throwable) {
            flash('No se pudo guardar (¿cédula o RUC duplicado?).', 'error');

            return $volver->withInput();
        }

        return redirect()->route('clientes.lista');
    }

    /** Baja lógica: nunca se borra, porque el historial lo referencia. */
    public function baja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_cliente', 0);
        $c = DB::selectOne(
            'SELECT pe.nombre, pe.apellido, c.activo FROM cliente c
               JOIN persona pe ON pe.id_persona = c.id_persona WHERE c.id_cliente = ?', [$id]
        );
        if (! $c) {
            flash('Ese cliente no existe.', 'error');

            return redirect()->route('clientes.lista');
        }

        // Al desactivar, avisar si tiene citas pendientes: se le estaría
        // cerrando la ficha a alguien que ya tiene turno dado.
        if ((int) $c->activo === 1) {
            $pend = (int) DB::scalar(
                'SELECT COUNT(*) FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                  WHERE c.id_cliente = ? AND ec.bloquea_agenda = 1 AND c.fecha_hora >= NOW()', [$id]
            );
            if ($pend) {
                flash("Ojo: {$c->nombre} tiene $pend cita(s) futura(s) sin atender.", 'warning');
            }
        }

        DB::update('UPDATE cliente SET activo = 1 - activo WHERE id_cliente = ?', [$id]);
        Auditoria::registrar('MODIFICACION', 'Clientes', 'cliente', $id,
            ((int) $c->activo ? 'Desactivó' : 'Activó') . ' a ' . $c->nombre . ' ' . $c->apellido);
        flash('Estado del cliente actualizado.');

        return redirect()->route('clientes.lista');
    }

    public function historial(int $id): View|RedirectResponse
    {
        $c = $this->cliente($id);
        if (! $c) {
            flash('Cliente no encontrado.', 'error');

            return redirect()->route('clientes.lista');
        }

        return view('clientes.historial', [
            'c' => $c,
            'hist' => DB::select('SELECT * FROM vw_historial_cliente WHERE id_cliente = ? ORDER BY fecha_hora DESC', [$id]),
            'fid' => DB::selectOne('SELECT * FROM vw_cliente_fidelizacion WHERE id_cliente = ?', [$id]),
            'pref' => DB::select('SELECT * FROM preferencia_cliente WHERE id_cliente = ? ORDER BY fecha_registro DESC', [$id]),
        ]);
    }

    // -----------------------------------------------------------------
    //  Canjes por puntos
    //
    //  El catálogo de lo que el salón regala a cambio de puntos. Es su propio
    //  permiso (`clientes.canjes`) porque decidir por cuántos puntos se regala
    //  un servicio es fijar precio, no consultar fidelización.
    // -----------------------------------------------------------------

    public function canjes(): View
    {
        return view('clientes.canjes', [
            'rows' => Canje::catalogo(false),
            // Para elegir en qué locales vale el canje. Con una sola sucursal
            // el bloque no se dibuja: no hay nada que elegir.
            'sucursales' => DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY nombre'),
            // Los que todavía no están en el catálogo: no tiene sentido
            // ofrecer dos veces el mismo servicio.
            'servicios' => DB::select(
                'SELECT s.id_servicio, s.nombre, s.precio, cs.nombre AS categoria
                   FROM servicio s
                   JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
                  WHERE s.activo = 1
                    AND NOT EXISTS (SELECT 1 FROM servicio_canjeable x WHERE x.id_servicio = s.id_servicio)
                  ORDER BY cs.nombre, s.nombre'
            ),
            // Lo que las clientas ya canjearon, para ver si el programa se usa.
            'canjeados' => DB::select(
                "SELECT c.id_canje, c.puntos, c.fecha, c.vence_en,
                        fn_canje_estado(c.id_canje) AS estado,
                        s.nombre AS servicio,
                        CONCAT(pe.nombre,' ',pe.apellido) AS cliente
                   FROM canje c
                   JOIN servicio s ON s.id_servicio = c.id_servicio
                   JOIN cliente cl ON cl.id_cliente = c.id_cliente
                   JOIN persona pe ON pe.id_persona = cl.id_persona
                  ORDER BY c.fecha DESC LIMIT 50"
            ),
        ]);
    }

    public function canjeGuardar(Request $request): RedirectResponse
    {
        $idServicio = (int) $request->input('id_servicio', 0);
        $puntos = entero($request->input('puntos'));
        $dias = entero($request->input('dias_vigencia'));
        $volver = redirect()->route('clientes.canjes');

        $error = null;
        if (! $idServicio || ! DB::scalar('SELECT COUNT(*) FROM servicio WHERE id_servicio = ? AND activo = 1', [$idServicio])) {
            $error = 'Elegí un servicio activo.';
        } elseif ($puntos <= 0) {
            $error = 'Los puntos tienen que ser más que cero.';
        } elseif ($dias < 1 || $dias > 365) {
            $error = 'La vigencia va de 1 a 365 días.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM servicio_canjeable WHERE id_servicio = ?', [$idServicio])) {
            $error = 'Ese servicio ya está en la lista de canjes. Editalo en vez de agregarlo de nuevo.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // **A qué locales aplica el canje.** Sin marcar ninguno se entiende que
        // vale en todos: es lo que espera quien tiene un solo local, y evita
        // que un canje quede creado sin poder usarse en ningún lado.
        $sucursales = array_values(array_filter(array_map('intval', (array) $request->input('sucursales', []))));
        if (! $sucursales) {
            $sucursales = array_map(fn ($s) => (int) $s->id_sucursal,
                DB::select('SELECT id_sucursal FROM sucursal WHERE activo = 1'));
        }

        try {
            DB::insert('INSERT INTO servicio_canjeable (id_servicio, puntos, dias_vigencia, activo) VALUES (?,?,?,1)',
                [$idServicio, $puntos, $dias]);
            $idCanjeable = (int) DB::getPdo()->lastInsertId();

            foreach ($sucursales as $idSuc) {
                DB::insert('INSERT IGNORE INTO canjeable_sucursal (id_servicio_canjeable, id_sucursal) VALUES (?,?)',
                    [$idCanjeable, $idSuc]);
            }

            $nombre = (string) DB::scalar('SELECT nombre FROM servicio WHERE id_servicio = ?', [$idServicio]);
            Auditoria::registrar('ALTA', 'Clientes', 'servicio_canjeable', $idCanjeable,
                $nombre . ' por ' . $puntos . ' puntos, con ' . $dias . ' día(s) de vigencia'
                . ' — en ' . count($sucursales) . ' sucursal(es)');
            flash($nombre . ' ya se puede canjear por ' . $puntos . ' puntos'
                . (count($sucursales) > 1 ? ' en ' . count($sucursales) . ' sucursales.' : '.'));
        } catch (Throwable $ex) {
            flash('No se pudo agregar el canje. El detalle quedó registrado.', 'error');
            Log::error('Alta de servicio canjeable', ['error' => $ex->getMessage()]);
        }

        return $volver;
    }

    public function canjeEditar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_servicio_canjeable', 0);
        $puntos = entero($request->input('puntos'));
        $dias = entero($request->input('dias_vigencia'));
        $volver = redirect()->route('clientes.canjes');

        $fila = DB::selectOne(
            'SELECT sc.*, s.nombre FROM servicio_canjeable sc
               JOIN servicio s ON s.id_servicio = sc.id_servicio
              WHERE sc.id_servicio_canjeable = ?', [$id]
        );
        if (! $fila) {
            flash('Ese canje no existe.', 'error');

            return $volver;
        }
        if ($puntos <= 0 || $dias < 1 || $dias > 365) {
            flash('Los puntos tienen que ser más que cero y la vigencia ir de 1 a 365 días.', 'error');

            return $volver;
        }

        DB::update('UPDATE servicio_canjeable SET puntos = ?, dias_vigencia = ? WHERE id_servicio_canjeable = ?',
            [$puntos, $dias, $id]);

        // **Lo ya canjeado no cambia**: `canje` guardó los puntos y el
        // vencimiento acordados. Cambiar el catálogo no le mueve el piso a
        // quien ya canjeó.
        Auditoria::registrar('MODIFICACION', 'Clientes', 'servicio_canjeable', $id,
            $fila->nombre . ': de ' . (int) $fila->puntos . ' a ' . $puntos . ' puntos, '
            . 'vigencia de ' . (int) $fila->dias_vigencia . ' a ' . $dias . ' día(s)');
        flash('Canje actualizado. Lo que ya se canjeó conserva sus condiciones.');

        return $volver;
    }

    public function canjeBaja(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_servicio_canjeable', 0);
        DB::update('UPDATE servicio_canjeable SET activo = 1 - activo WHERE id_servicio_canjeable = ?', [$id]);
        Auditoria::registrar('MODIFICACION', 'Clientes', 'servicio_canjeable', $id, 'Alta/baja del canje');
        flash('Listo. Los canjes que las clientas ya hicieron siguen valiendo.');

        return redirect()->route('clientes.canjes');
    }

    /**
     * Canjear los puntos de una clienta **desde el mostrador**.
     *
     * No todas las clientas usan el portal —la mayoría entra por teléfono y ni
     * siquiera tiene cuenta—, así que la que viene al local y pide gastar sus
     * puntos tiene que poder hacerlo ahí mismo, con quien la atiende.
     *
     * **Pide `clientes.fidelizacion`, no `clientes.canjes`**, y la diferencia
     * importa: canjear POR una clienta es una acción del mostrador, y decidir
     * por cuántos puntos el salón regala un servicio es fijar precio. El
     * Profesional tiene la primera y no la segunda.
     *
     * Pasa por el mismo `sp_canjear_servicio` que el portal: mismo candado,
     * mismas validaciones y mismo descuento de puntos. Lo único distinto es
     * quién aprieta el botón, y eso queda en la auditoría.
     */
    public function canjearPara(Request $request): RedirectResponse
    {
        $idCliente = (int) $request->input('id_cliente', 0);
        $idServicio = (int) $request->input('id_servicio', 0);
        $volver = redirect()->route('clientes.fidelizacion');

        $cliente = DB::selectOne(
            "SELECT cl.id_cliente, CONCAT(pe.nombre,' ',pe.apellido) AS nombre
               FROM cliente cl JOIN persona pe ON pe.id_persona = cl.id_persona
              WHERE cl.id_cliente = ? AND cl.activo = 1", [$idCliente]
        );
        if (! $cliente) {
            flash('Esa clienta no existe o está dada de baja.', 'error');

            return $volver;
        }

        try {
            $idCanje = Canje::canjear($idCliente, $idServicio);
            $c = DB::selectOne(
                'SELECT s.nombre, cj.puntos, cj.vence_en FROM canje cj
                   JOIN servicio s ON s.id_servicio = cj.id_servicio
                  WHERE cj.id_canje = ?', [$idCanje]
            );

            Auditoria::registrar('CANJE', 'Clientes', 'canje', $idCanje,
                $cliente->nombre . ' canjeó ' . ($c->nombre ?? '') . ' por '
                . (int) ($c->puntos ?? 0) . ' puntos (desde el mostrador)');

            flash('Listo: ' . $cliente->nombre . ' canjeó ' . ($c->nombre ?? 'el servicio')
                . '. Le quedan ' . Canje::puntos($idCliente) . ' punto(s), y tiene hasta el '
                . fecha($c->vence_en ?? null, 'd/m/Y') . ' para usarlo. '
                . 'Aparece al agendarle la cita.');
        } catch (Throwable $ex) {
            flash(Bd::traducir($ex, [
                'no alcanzan' => 'A ' . $cliente->nombre . ' no le alcanzan los puntos para ese canje: '
                                 . 'tiene ' . Canje::puntos($idCliente) . '.',
                'no se puede canjear' => 'Ese servicio no está en la lista de canjes.',
            ], 'No se pudo hacer el canje. El detalle quedó registrado.'), 'error');

            if (! str_contains($ex->getMessage(), 'alcanzan') && ! str_contains($ex->getMessage(), 'canjear')) {
                Log::error('Canje desde el mostrador', ['cliente' => $idCliente, 'error' => $ex->getMessage()]);
            }
        }

        return $volver;
    }

    public function fidelizacion(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nombre del cliente', 'ancho' => '240px'],
            'nivel' => ['tipo' => 'select', 'etiqueta' => 'Nivel',
                        'opciones' => ['' => 'Todos'] + $this->nivelesPorNombre()],
            'minvis' => ['tipo' => 'numero', 'etiqueta' => 'Visitas desde', 'ph' => '0', 'ancho' => '130px'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['v.cliente', 'v.telefono'], Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'nivel')) {
            $w[] = 'v.nivel = :n';
            $par['n'] = Listado::valor($f, 'nivel');
        }
        if (Listado::hay($f, 'minvis')) {
            $w[] = 'v.visitas >= :mv';
            $par['mv'] = (int) Listado::valor($f, 'minvis');
        }

        $desde = 'FROM vw_cliente_fidelizacion v WHERE ' . implode(' AND ', $w);
        $orden = 'ORDER BY v.visitas DESC, v.cliente';

        if (Listado::pideExport()) {
            return Listado::exportar('fidelizacion',
                ['Cliente', 'Teléfono', 'Visitas', 'Puntos', 'Nivel', 'Descuento del nivel'],
                array_map(fn ($r) => [$r->cliente, $r->telefono, $r->visitas, $r->puntos, $r->nivel, $r->descuento_del_nivel],
                    DB::select("SELECT * $desde $orden", $par)),
                $f, 'Fidelización'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        $canjeables = Canje::catalogo();

        return view('clientes.fidelizacion', [
            'rows' => DB::select("SELECT * $desde $orden LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
            // Los niveles estaban escondidos en Configuración, lejos de donde
            // se usan. Van acá, con cuánta gente hay hoy en cada uno.
            'niveles' => DB::select(
                'SELECT n.nombre, n.visitas_minimas, d.nombre AS descuento,
                        (SELECT COUNT(*) FROM cliente cl
                          WHERE cl.activo = 1 AND fn_cliente_nivel(cl.id_cliente) = n.id_nivel) AS clientes
                   FROM nivel n LEFT JOIN descuento d ON d.id_descuento = n.id_descuento
                  ORDER BY n.visitas_minimas'
            ),
            // Para canjear desde el mostrador: la clienta que viene al local y
            // pide gastar sus puntos no tiene por qué entrar al portal —la
            // mayoría ni siquiera tiene cuenta—.
            'canjeables' => $canjeables,
            // El canje más barato del catálogo: con menos puntos que eso no hay
            // nada que ofrecerle, y el botón no se dibuja. Un botón que abre un
            // modal donde todo dice «no te alcanza» es el mismo cartel que
            // promete y no cumple.
            'canjeMasBarato' => $canjeables
                ? min(array_map(fn ($c) => (int) $c->puntos, $canjeables))
                : PHP_INT_MAX,
        ]);
    }

    public function valoraciones(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Cliente o comentario', 'ancho' => '240px'],
            'prof' => ['tipo' => 'select', 'etiqueta' => 'Profesional', 'ancho' => '190px',
                       'opciones' => ['' => 'Todos'] + $this->profesionales()],
            'puntaje' => ['tipo' => 'select', 'etiqueta' => 'Puntaje',
                          'opciones' => ['' => 'Todos', '5' => '5 ★', '4' => '4 ★', '3' => '3 ★', '2' => '2 ★', '1' => '1 ★']],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(["CONCAT(pe_cl.nombre,' ',pe_cl.apellido)", 'cal.comentario'],
                Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'prof')) {
            $w[] = 'c.id_usuario = :p';
            $par['p'] = (int) Listado::valor($f, 'prof');
        }
        if (Listado::hay($f, 'puntaje')) {
            $w[] = 'cal.puntaje = :pt';
            $par['pt'] = (int) Listado::valor($f, 'puntaje');
        }
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(cal.fecha) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(cal.fecha) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        $desde = 'FROM calificacion cal
                  JOIN cita c        ON c.id_cita = cal.id_cita
                  JOIN cliente cl    ON cl.id_cliente = c.id_cliente
                  JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
                  JOIN usuario u     ON u.id_usuario = c.id_usuario
                  JOIN persona pe_u  ON pe_u.id_persona = u.id_persona
                  WHERE ' . implode(' AND ', $w);
        $cols = "cal.puntaje, cal.comentario, cal.fecha,
                 CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente,
                 CONCAT(pe_u.nombre,' ',pe_u.apellido) AS profesional";

        if (Listado::pideExport()) {
            return Listado::exportar('valoraciones',
                ['Fecha', 'Cliente', 'Profesional', 'Puntaje', 'Comentario'],
                array_map(fn ($r) => [fecha($r->fecha, 'd/m/Y'), $r->cliente, $r->profesional, $r->puntaje, $r->comentario],
                    DB::select("SELECT $cols $desde ORDER BY cal.fecha DESC", $par)),
                $f, 'Valoraciones'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('clientes.valoraciones', [
            'rows' => DB::select("SELECT $cols $desde ORDER BY cal.fecha DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            // El promedio es el de lo filtrado, no el general: si se mira a una
            // profesional, el número que interesa es el de ella.
            'prom' => DB::scalar("SELECT ROUND(AVG(cal.puntaje),2) $desde", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    // -----------------------------------------------------------------

    private function cliente(int $id): ?object
    {
        return DB::selectOne(
            'SELECT c.*, pe.nombre, pe.apellido, pe.cedula, pe.ruc, pe.telefono, pe.email,
                    pe.fecha_nacimiento, pe.direccion
               FROM cliente c JOIN persona pe ON pe.id_persona = c.id_persona
              WHERE c.id_cliente = ?', [$id]
        );
    }

    private function niveles(): array
    {
        $out = [];
        foreach (DB::select('SELECT id_nivel, nombre FROM nivel ORDER BY visitas_minimas') as $n) {
            $out[(string) $n->id_nivel] = $n->nombre;
        }

        return $out;
    }

    private function nivelesPorNombre(): array
    {
        $out = [];
        foreach (DB::select('SELECT nombre FROM nivel ORDER BY visitas_minimas') as $n) {
            $out[$n->nombre] = $n->nombre;
        }

        return $out;
    }

    private function profesionales(): array
    {
        $out = [];
        foreach (DB::select(
            "SELECT u.id_usuario, CONCAT(pe.nombre,' ',pe.apellido) n
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
               JOIN rol r ON r.id_rol = u.id_rol WHERE r.es_personal = 1 ORDER BY pe.nombre"
        ) as $p) {
            $out[(string) $p->id_usuario] = $p->n;
        }

        return $out;
    }
}
