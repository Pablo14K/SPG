<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServiciosController extends Controller
{
    public function index(): View
    {
        return view('servicios.index', [
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'servicios.catalogo', 'ruta' => 'servicios.lista', 'ic' => 'scissors',
                 't' => 'Catálogo de servicios', 'd' => 'Nombre, precio y duración'],
                ['p' => 'servicios.categorias', 'ruta' => 'servicios.categorias', 'ic' => 'tags',
                 't' => 'Categorías', 'd' => 'Tipos de servicio'],
                ['p' => 'servicios.descuentos', 'ruta' => 'servicios.descuentos', 'ic' => 'percent',
                 't' => 'Descuentos y promos', 'd' => 'Vigencia y valor'],
            ]),
        ]);
    }

    public function lista(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nombre o descripción', 'ancho' => '240px'],
            'categoria' => ['tipo' => 'select', 'etiqueta' => 'Categoría',
                            'opciones' => ['' => 'Todas'] + $this->opcionesCategorias()],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos', '1' => 'Activos', '0' => 'Inactivos']],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['s.nombre', 's.descripcion'], Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'categoria')) {
            $w[] = 's.id_categoria_servicio = :c';
            $par['c'] = (int) Listado::valor($f, 'categoria');
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 's.activo = :e';
            $par['e'] = (int) Listado::valor($f, 'estado');
        }

        $desde = 'FROM servicio s JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
                  WHERE ' . implode(' AND ', $w);
        $orden = 'ORDER BY cs.nombre, s.nombre';

        if (Listado::pideExport()) {
            return Listado::exportar('servicios',
                ['Servicio', 'Categoría', 'Precio', 'Duración (min)', 'IVA %', 'Estado'],
                array_map(fn ($r) => [$r->nombre, $r->categoria, $r->precio, $r->duracion_min,
                    $r->tasa_iva, $r->activo ? 'Activo' : 'Inactivo'],
                    DB::select("SELECT s.*, cs.nombre AS categoria $desde $orden", $par)),
                $f, 'Servicios'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('servicios.lista', [
            'rows' => DB::select("SELECT s.*, cs.nombre AS categoria $desde $orden LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    public function form(int $id = 0): View|RedirectResponse
    {
        $s = $id ? DB::selectOne('SELECT * FROM servicio WHERE id_servicio = ?', [$id]) : null;
        if ($id && ! $s) {
            flash('Servicio no encontrado.', 'error');

            return redirect()->route('servicios.lista');
        }

        return view('servicios.form', [
            's' => $s,
            'cats' => DB::select('SELECT * FROM categoria_servicio ORDER BY nombre'),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_servicio', 0);
        $d = [
            'id_categoria_servicio' => (int) $request->input('id_categoria_servicio', 0),
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')) ?: null,
            'precio' => num($request->input('precio')),
            'duracion_min' => entero($request->input('duracion_min'), 30),
            'tasa_iva' => (int) $request->input('tasa_iva', 10),
            // Ocupa a la clienta entera: no se puede hacer en paralelo con otro
            // servicio exclusivo, porque los dos necesitan a la misma persona.
            'requiere_exclusividad' => $request->boolean('requiere_exclusividad') ? 1 : 0,
        ];
        $volver = $id ? redirect()->route('servicios.form', $id) : redirect()->route('servicios.form');

        $error = null;
        if ($d['nombre'] === '') {
            $error = 'El nombre del servicio es obligatorio.';
        } elseif ($d['id_categoria_servicio'] <= 0
            || ! DB::scalar('SELECT COUNT(*) FROM categoria_servicio WHERE id_categoria_servicio = ?', [$d['id_categoria_servicio']])) {
            $error = 'Elegí una categoría válida.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM servicio WHERE nombre = ? AND id_servicio <> ?', [$d['nombre'], $id])) {
            $error = 'Ya existe un servicio con ese nombre.';
        } elseif ($d['precio'] < 0) {
            $error = 'El precio no puede ser negativo.';
        } elseif ($d['duracion_min'] < 5 || $d['duracion_min'] > 600) {
            $error = 'La duración debe estar entre 5 y 600 minutos.';
        } elseif (! in_array($d['tasa_iva'], [0, 5, 10], true)) {
            $error = 'La tasa de IVA debe ser 0, 5 o 10.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // El precio anterior se lee ANTES de escribir: es lo único que no se
        // puede reconstruir después, y es el dato que se va a querer mirar si
        // una factura sale por un importe que no cuadra.
        $previo = $id ? DB::selectOne('SELECT precio, duracion_min FROM servicio WHERE id_servicio = ?', [$id]) : null;

        try {
            if ($id) {
                DB::update(
                    'UPDATE servicio SET id_categoria_servicio=:id_categoria_servicio, nombre=:nombre,
                        descripcion=:descripcion, precio=:precio, duracion_min=:duracion_min,
                        tasa_iva=:tasa_iva, requiere_exclusividad=:requiere_exclusividad
                      WHERE id_servicio=:id', $d + ['id' => $id]
                );
                $cambios = [];
                if ($previo && (float) $previo->precio !== (float) $d['precio']) {
                    $cambios[] = 'precio ' . money($previo->precio) . ' → ' . money($d['precio']);
                }
                if ($previo && (int) $previo->duracion_min !== (int) $d['duracion_min']) {
                    $cambios[] = 'duración ' . (int) $previo->duracion_min . ' → ' . (int) $d['duracion_min'] . ' min';
                }
                Auditoria::registrar('MODIFICACION', 'Servicios', 'servicio', $id,
                    $d['nombre'] . ($cambios ? ' · ' . implode(', ', $cambios) : ''));
                flash('Servicio actualizado.');
            } else {
                DB::insert(
                    'INSERT INTO servicio (id_categoria_servicio,nombre,descripcion,precio,duracion_min,tasa_iva,requiere_exclusividad)
                     VALUES (:id_categoria_servicio,:nombre,:descripcion,:precio,:duracion_min,:tasa_iva,:requiere_exclusividad)', $d
                );
                Auditoria::registrar('ALTA', 'Servicios', 'servicio', (int) DB::getPdo()->lastInsertId(), $d['nombre']);
                flash('Servicio creado.');
            }
        } catch (Throwable) {
            flash('No se pudo guardar (¿nombre duplicado?).', 'error');

            return $volver->withInput();
        }

        return redirect()->route('servicios.lista');
    }

    public function baja(Request $request): RedirectResponse
    {
        DB::update('UPDATE servicio SET activo = 1 - activo WHERE id_servicio = ?',
            [(int) $request->input('id_servicio', 0)]);
        flash('Estado del servicio actualizado.');

        return redirect()->route('servicios.lista');
    }

    // ---------- Categorías ----------

    public function categorias(): View
    {
        return view('servicios.categorias', [
            'rows' => DB::select(
                'SELECT cs.*, (SELECT COUNT(*) FROM servicio s
                                WHERE s.id_categoria_servicio = cs.id_categoria_servicio) AS usos
                   FROM categoria_servicio cs ORDER BY nombre'
            ),
        ]);
    }

    public function categoriaCrear(Request $request): RedirectResponse
    {
        $nombre = trim((string) $request->input('nombre', ''));
        if ($nombre !== '') {
            try {
                DB::insert('INSERT INTO categoria_servicio (nombre) VALUES (?)', [$nombre]);
                flash('Categoría agregada.');
            } catch (Throwable) {
                flash('Esa categoría ya existe.', 'error');
            }
        }

        return redirect()->route('servicios.categorias');
    }

    public function categoriaEditar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $nombre = trim((string) $request->input('nombre', ''));

        if (! $id || $nombre === '') {
            flash('El nombre no puede quedar vacío.', 'error');

            return redirect()->route('servicios.categorias');
        }

        try {
            DB::update('UPDATE categoria_servicio SET nombre = ? WHERE id_categoria_servicio = ?', [$nombre, $id]);
            Auditoria::registrar('MODIFICACION', 'Servicios', 'categoria_servicio', $id, $nombre);
            flash('Categoría actualizada.');
        } catch (Throwable) {
            flash('Ya existe otra categoría con ese nombre.', 'error');
        }

        return redirect()->route('servicios.categorias');
    }

    public function categoriaBorrar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $usos = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE id_categoria_servicio = ?', [$id]);

        if ($usos) {
            flash("No se puede eliminar: hay $usos servicio(s) en esa categoría.", 'warning');
        } else {
            DB::delete('DELETE FROM categoria_servicio WHERE id_categoria_servicio = ?', [$id]);
            Auditoria::registrar('BAJA', 'Servicios', 'categoria_servicio', $id, 'Categoría eliminada');
            flash('Categoría eliminada.');
        }

        return redirect()->route('servicios.categorias');
    }

    // ---------- Descuentos y promociones ----------

    public function descuentos(): View
    {
        return view('servicios.descuentos', [
            'rows' => DB::select('SELECT * FROM descuento ORDER BY activo DESC, nombre'),
        ]);
    }

    public function descuentoForm(int $id = 0): View|RedirectResponse
    {
        $d = $id ? DB::selectOne('SELECT * FROM descuento WHERE id_descuento = ?', [$id]) : null;
        if ($id && ! $d) {
            flash('Descuento no encontrado.', 'error');

            return redirect()->route('servicios.descuentos');
        }

        return view('servicios.descuento_form', [
            'd' => $d,
            'servicios' => DB::select('SELECT id_servicio, nombre, precio FROM servicio WHERE activo = 1 ORDER BY nombre'),
            'elegidos' => $id
                ? array_map(fn ($r) => (int) $r->id_servicio,
                    DB::select('SELECT id_servicio FROM servicio_descuento WHERE id_descuento = ?', [$id]))
                : [],
            // Un descuento atado a un nivel de fidelización no es una promoción:
            // lo aplica fn_cliente_descuento por cantidad de visitas y no se le
            // eligen servicios. Se avisa para no prometer algo que no pasa.
            'nivel' => $id ? DB::scalar('SELECT nombre FROM nivel WHERE id_descuento = ?', [$id]) : null,
        ]);
    }

    public function descuentoGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_descuento', 0);
        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')) ?: null,
            'tipo' => (string) $request->input('tipo', 'PORCENTAJE'),
            'valor' => num($request->input('valor')),
            'fecha_inicio' => $request->input('fecha_inicio') ?: null,
            'fecha_fin' => $request->input('fecha_fin') ?: null,
        ];
        $volver = $id ? redirect()->route('servicios.descuento_form', $id) : redirect()->route('servicios.descuento_form');

        $error = null;
        if ($d['nombre'] === '') {
            $error = 'El nombre del descuento es obligatorio.';
        } elseif (! in_array($d['tipo'], ['PORCENTAJE', 'MONTO'], true)) {
            $error = 'Elegí si el descuento es un porcentaje o un monto fijo.';
        } elseif ($d['valor'] <= 0) {
            $error = 'El valor del descuento tiene que ser mayor a cero.';
        } elseif ($d['tipo'] === 'PORCENTAJE' && $d['valor'] > 100) {
            $error = 'Un descuento en porcentaje no puede superar el 100%.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM descuento WHERE nombre = ? AND id_descuento <> ?', [$d['nombre'], $id])) {
            $error = 'Ya existe un descuento con ese nombre.';
        } elseif ($d['fecha_inicio'] && $d['fecha_fin'] && $d['fecha_inicio'] > $d['fecha_fin']) {
            $error = 'La fecha de inicio no puede ser posterior a la de fin.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // A qué servicios aplica. Vacío = a toda la factura, que es como venía
        // funcionando. `sp_aplicar_descuento` ya sabía leer esta tabla: sin esta
        // pantalla, `servicio_descuento` no tenía forma de cargarse.
        $servicios = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('servicios', []))
        )));

        try {
            DB::transaction(function () use (&$id, $d, $servicios) {
                if ($id) {
                    DB::update(
                        'UPDATE descuento SET nombre=:nombre, descripcion=:descripcion, tipo=:tipo,
                            valor=:valor, fecha_inicio=:fecha_inicio, fecha_fin=:fecha_fin
                          WHERE id_descuento=:id', $d + ['id' => $id]
                    );
                    Auditoria::registrar('MODIFICACION', 'Servicios', 'descuento', $id, $d['nombre']);
                    flash('Descuento actualizado.');
                } else {
                    DB::insert(
                        'INSERT INTO descuento (nombre,descripcion,tipo,valor,fecha_inicio,fecha_fin)
                         VALUES (:nombre,:descripcion,:tipo,:valor,:fecha_inicio,:fecha_fin)', $d
                    );
                    $id = (int) DB::getPdo()->lastInsertId();
                    Auditoria::registrar('ALTA', 'Servicios', 'descuento', $id, $d['nombre']);
                    flash('Descuento creado.');
                }

                // Se rehace la lista completa: es N:M y no hay nada que conservar.
                DB::delete('DELETE FROM servicio_descuento WHERE id_descuento = ?', [$id]);
                foreach ($servicios as $s) {
                    if (DB::scalar('SELECT COUNT(*) FROM servicio WHERE id_servicio = ?', [$s])) {
                        DB::insert('INSERT IGNORE INTO servicio_descuento (id_descuento,id_servicio) VALUES (?,?)', [$id, $s]);
                    }
                }
            });

            flash($servicios
                ? 'Aplica sólo a ' . count($servicios) . ' servicio(s).'
                : 'Aplica al total de la factura.', 'info');
        } catch (Throwable) {
            flash('No se pudo guardar (¿nombre duplicado o fechas inválidas?).', 'error');

            return $volver->withInput();
        }

        return redirect()->route('servicios.descuentos');
    }

    public function descuentoBaja(Request $request): RedirectResponse
    {
        DB::update('UPDATE descuento SET activo = 1 - activo WHERE id_descuento = ?',
            [(int) $request->input('id_descuento', 0)]);
        flash('Estado del descuento actualizado.');

        return redirect()->route('servicios.descuentos');
    }

    /** Opciones del filtro por categoría (no confundir con la pantalla). */
    private function opcionesCategorias(): array
    {
        $out = [];
        foreach (DB::select('SELECT id_categoria_servicio, nombre FROM categoria_servicio ORDER BY nombre') as $c) {
            $out[(string) $c->id_categoria_servicio] = $c->nombre;
        }

        return $out;
    }
}
