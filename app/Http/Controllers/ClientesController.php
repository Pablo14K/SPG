<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'SELECT c.*, pe.nombre, pe.apellido, pe.cedula, pe.ruc, pe.telefono, pe.email, pe.fecha_nacimiento
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
