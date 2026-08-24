<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Imagen;
use App\Servicios\Config;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use App\Servicios\Sucursales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;
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
                ['p' => 'servicios.categorias', 'ruta' => 'servicios.zonas', 'ic' => 'diagram-3',
                 't' => 'Zonas del cuerpo', 'd' => 'Qué se puede hacer a la vez'],
                ['p' => 'servicios.descuentos', 'ruta' => 'servicios.descuentos', 'ic' => 'percent',
                 // La 7.55.0 la renombró a Promociones, que es lo que administra;
                 // acá había quedado el nombre viejo, así que la tarjeta del Panel
                 // y la del módulo decían cosas distintas de la misma pantalla.
                 't' => 'Promociones', 'd' => 'Vigencia y valor'],
            ]),
        ]);
    }

    public function lista(): View|StreamedResponse
    {
        // **El filtro de local sólo existe con más de una sucursal**, y con él
        // aparece la mitad que faltaba: ver lo que el salón ya tiene cargado en
        // OTRO local y traerlo acá con un clic, en vez de volver a escribirlo.
        // Cargarlo de nuevo deja dos filas con el mismo servicio escrito
        // distinto («Corte de dama» y «Corte dama»), y a partir de ahí ningún
        // informe los puede comparar entre sucursales.
        $sucursales = Sucursales::delUsuario();
        $varias = count($sucursales) > 1;
        $aqui = Sucursales::activa();

        $campos = [
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nombre o descripción', 'ancho' => '240px'],
            'categoria' => ['tipo' => 'select', 'etiqueta' => 'Categoría',
                            'opciones' => ['' => 'Todas'] + $this->opcionesCategorias()],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos', '1' => 'Activos', '0' => 'Inactivos']],
        ];

        // Con un solo local la pregunta no significa nada: todo lo que existe
        // se ofrece acá.
        if ($varias) {
            $campos['aqui'] = ['tipo' => 'select', 'etiqueta' => 'Disponible acá',
                               'opciones' => ['' => 'Todos', '1' => 'Sí', '0' => 'No']];
        }

        $f = Listado::filtros($campos);
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

        // **Sin filas en `servicio_sucursal` el servicio vale en TODAS**, que es
        // lo que espera quien recién abre el segundo local: el catálogo que ya
        // tenía no se le apaga solo. Por eso las dos ramas preguntan por la
        // existencia de alguna fila antes de mirar la del local.
        // **La lista es la de ESTE local.** Antes había un filtro «Dónde se
        // ofrece» y una tabla con todo el catálogo diciendo si estaba activo
        // acá; el usuario pidió lo contrario y tiene razón: quien administra el
        // salón de San Lorenzo no tiene por qué ver una lista de servicios que
        // no da, con una columna para adivinar cuáles sí. Lo que se ofrece en
        // otro lado se trae desde el alta, con «traer uno existente».
        //
        // Sigue valiendo que **sin filas en `servicio_sucursal` el servicio vale
        // en TODAS**: es la red para el catálogo que ya estaba cargado antes de
        // que las sucursales importaran, y no se le apaga solo a nadie.
        // **La disponibilidad es una COLUMNA, no un filtro escondido.**
        //
        // Hasta la 7.62.1 la lista mostraba sólo lo de este local, y el botón
        // «No ofrecerlo en esta sucursal» borraba la fila de
        // `servicio_sucursal` — con lo cual el servicio **dejaba de cumplir el
        // filtro y desaparecía de la pantalla**. Desde ahí no había forma de
        // volver a ofrecerlo: había que ir al alta y usar «traer uno
        // existente», que nadie va a adivinar. Parecía que el botón borraba el
        // servicio.
        //
        // Ahora se ve el catálogo del salón con **Disponible acá: sí/no**, y
        // el botón alterna. Quien quiera ver sólo lo suyo tiene el filtro.
        if ($aqui && Listado::hay($f, 'aqui')) {
            $cond = '(EXISTS (SELECT 1 FROM servicio_sucursal ss
                               WHERE ss.id_servicio = s.id_servicio AND ss.id_sucursal = :loc)
                      OR NOT EXISTS (SELECT 1 FROM servicio_sucursal ss2
                                      WHERE ss2.id_servicio = s.id_servicio))';
            $w[] = Listado::valor($f, 'aqui') === '1' ? $cond : 'NOT ' . $cond;
            $par['loc'] = $aqui;
        }

        // **Se ofrece acá** = tiene su fila, o no tiene ninguna (que vale en
        // todas). Es la misma condición del filtro, expuesta como columna.
        $ofrece = $aqui
            ? '(EXISTS (SELECT 1 FROM servicio_sucursal sx
                         WHERE sx.id_servicio = s.id_servicio AND sx.id_sucursal = ' . (int) $aqui . ')
                OR NOT EXISTS (SELECT 1 FROM servicio_sucursal sy
                                WHERE sy.id_servicio = s.id_servicio)) AS aqui'
            : '1 AS aqui';

        $desde = 'FROM servicio s JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
                  WHERE ' . implode(' AND ', $w);
        $orden = 'ORDER BY cs.nombre, s.nombre';

        if (Listado::pideExport()) {
            return Listado::exportar('servicios',
                ['Servicio', 'Categoría', 'Precio', 'Duración (min)', 'IVA %', 'Estado', 'Disponible acá'],
                array_map(fn ($r) => [$r->nombre, $r->categoria, $r->precio, $r->duracion_min,
                    $r->tasa_iva, $r->activo ? 'Activo' : 'Inactivo',
                    $r->aqui ? 'Sí' : 'No'],
                    DB::select("SELECT s.*, cs.nombre AS categoria, $ofrece $desde $orden", $par)),
                $f, 'Servicios'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('servicios.lista', [
            'rows' => DB::select("SELECT s.*, cs.nombre AS categoria, $ofrece $desde $orden LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
            'varias' => $varias,
        ]);
    }

    public function form(int $id = 0): View|RedirectResponse
    {
        $s = $id ? DB::selectOne('SELECT * FROM servicio WHERE id_servicio = ?', [$id]) : null;
        if ($id && ! $s) {
            flash('Servicio no encontrado.', 'error');

            return redirect()->route('servicios.lista');
        }

        // **Lo que ya existe se trae, no se vuelve a cargar.** Escrito de nuevo,
        // «Corte de dama» termina siendo dos filas con el nombre distinto según
        // quién lo tipeó, cada una con su precio y su duración: a partir de ahí
        // ningún informe puede comparar el mismo servicio entre sucursales. Por
        // eso el alta empieza ofreciendo el catálogo que este local todavía no
        // publica, y traerlo es un clic — no copia nada, agrega la fila de
        // `servicio_sucursal` que dice que acá también se ofrece.
        $suc = Sucursales::activa();
        $ajenos = ($suc && ! $id) ? DB::select(
            'SELECT s.id_servicio, s.nombre, s.precio, cs.nombre AS categoria
               FROM servicio s
               JOIN categoria_servicio cs ON cs.id_categoria_servicio = s.id_categoria_servicio
              WHERE s.activo = 1
                AND EXISTS (SELECT 1 FROM servicio_sucursal x WHERE x.id_servicio = s.id_servicio)
                AND NOT EXISTS (SELECT 1 FROM servicio_sucursal y
                                 WHERE y.id_servicio = s.id_servicio AND y.id_sucursal = ?)
              ORDER BY cs.nombre, s.nombre', [$suc]
        ) : [];

        return view('servicios.form', [
            'ajenos' => $ajenos,
            's' => $s,
            // Null si el archivo ya no está: la pantalla dibuja el placeholder
            // en vez del ícono roto.
            'imagenUrl' => Imagen::url($s->imagen ?? null, 'servicios'),
            'cats' => DB::select('SELECT * FROM categoria_servicio ORDER BY nombre'),
            'zonas' => DB::select('SELECT id_zona, nombre FROM zona_servicio WHERE activo = 1 ORDER BY nombre'),
            // Dónde más se ofrece, sólo para decirlo al editar: el catálogo es
            // único —«Corte de dama» es UN servicio con un precio— y cada local
            // publica los suyos. Ya no se elige con casillas: dar de alta lo
            // publica acá, y los demás lo traen con «traer uno existente».
            'tambienEn' => $id ? DB::select(
                'SELECT su.nombre FROM servicio_sucursal ss
                   JOIN sucursal su ON su.id_sucursal = ss.id_sucursal
                  WHERE ss.id_servicio = ? AND ss.id_sucursal <> ? ORDER BY su.nombre',
                [$id, Sucursales::activa() ?: 0]
            ) : [],
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_servicio', 0);
        $esAlta = $id === 0;
        $d = [
            'id_categoria_servicio' => (int) $request->input('id_categoria_servicio', 0),
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')) ?: null,
            'precio' => num($request->input('precio')),
            'duracion_min' => entero($request->input('duracion_min'), 30),
            'tasa_iva' => (int) $request->input('tasa_iva', 10),
            // Ocupa a la clienta entera: no se puede hacer en paralelo con otro
            // servicio exclusivo, porque los dos necesitan a la misma persona.
            // `requiere_exclusividad` queda en la base pero ya no se pregunta:
            // lo reemplaza la zona. Se conserva la columna por el mismo motivo
            // que las piezas de la venta de productos — bajar el conteo del
            // modelo para sacar algo que no molesta es peor negocio.
            'requiere_exclusividad' => 0,
            'id_zona' => ((int) $request->input('id_zona', 0)) ?: null,
            // **Cuánta seña pide este servicio, en porcentaje del precio.**
            // Vacío = no pide. Va como porcentaje y no como monto para que no
            // se separe del precio cuando el servicio sube.
            'sena_porcentaje' => ($p = entero($request->input('sena_porcentaje'), 0)) > 0 ? $p : null,
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
        } elseif ($d['sena_porcentaje'] !== null && ($d['sena_porcentaje'] < 1 || $d['sena_porcentaje'] > 100)) {
            $error = 'La seña va entre 1 y 100 por ciento del precio. Dejalo vacío si no se pide seña.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        // El precio anterior se lee ANTES de escribir: es lo único que no se
        // puede reconstruir después, y es el dato que se va a querer mirar si
        // una factura sale por un importe que no cuadra.
        $previo = $id
            ? DB::selectOne('SELECT precio, duracion_min, imagen FROM servicio WHERE id_servicio = ?', [$id])
            : null;

        // **La imagen de referencia.** Es lo que la clienta mira para saber qué
        // está pidiendo: «mechas» es una palabra, la foto es el resultado.
        //
        // Se escribe ANTES de tocar la base, así que si falla no queda una fila
        // apuntando a un archivo que no está.
        $imagen = $previo->imagen ?? null;
        $sacar = $request->boolean('sacar_imagen');

        if (($archivo = $request->file('imagen')) !== null) {
            try {
                $imagen = Imagen::guardar($archivo, 'servicios', 'srv');
                Imagen::borrar($previo->imagen ?? null, 'servicios');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'error');

                return $volver->withInput();
            }
        } elseif ($sacar) {
            Imagen::borrar($imagen, 'servicios');
            $imagen = null;
        }
        $d['imagen'] = $imagen;

        try {
            if ($id) {
                DB::update(
                    'UPDATE servicio SET id_categoria_servicio=:id_categoria_servicio, nombre=:nombre,
                        descripcion=:descripcion, precio=:precio, duracion_min=:duracion_min,
                        tasa_iva=:tasa_iva, requiere_exclusividad=:requiere_exclusividad, id_zona=:id_zona,
                        sena_porcentaje=:sena_porcentaje, imagen=:imagen
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
                    'INSERT INTO servicio (id_categoria_servicio,id_zona,nombre,descripcion,imagen,precio,duracion_min,tasa_iva,requiere_exclusividad,sena_porcentaje)
                     VALUES (:id_categoria_servicio,:id_zona,:nombre,:descripcion,:imagen,:precio,:duracion_min,:tasa_iva,:requiere_exclusividad,:sena_porcentaje)', $d
                );
                $id = (int) DB::getPdo()->lastInsertId();
                Auditoria::registrar('ALTA', 'Servicios', 'servicio', $id, $d['nombre']);
                flash('Servicio creado.');
            }

            // **Un servicio nuevo se publica en el local que lo crea, y nada
            // más.** Antes había casillas para elegir en cuáles, y el usuario
            // pidió sacarlas: cada sede administra lo suyo y lo trae si le sirve.
            // **Editar no toca la publicación de nadie** — con las casillas, un
            // cambio de precio hecho desde acá reescribía la lista entera y podía
            // apagarle el servicio a otra sucursal sin que nadie se enterara.
            $sucursales = ($esAlta && Sucursales::activa()) ? [Sucursales::activa()] : [];
            foreach ($sucursales as $idSuc) {
                DB::insert('INSERT IGNORE INTO servicio_sucursal (id_servicio, id_sucursal) VALUES (?,?)',
                    [$id, $idSuc]);
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

    /**
     * Trae a este local un servicio que ya existe en otro.
     *
     * **Es la alternativa a volver a cargarlo, y hace falta justamente para que
     * nadie lo vuelva a cargar.** Escrito de nuevo, «Corte de dama» termina
     * siendo dos filas con el nombre distinto según quién lo tipeó, cada una
     * con su precio y su duración: a partir de ahí ningún informe puede
     * comparar el mismo servicio entre sucursales, que es la razón por la que
     * el catálogo es único desde la 7.30.0.
     *
     * No copia nada: agrega una fila en `servicio_sucursal` diciendo que este
     * local también lo publica. El servicio sigue siendo uno.
     */
    public function publicar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_servicio', 0);
        $suc = Sucursales::activa();

        if (! $suc) {
            flash('Elegí una sucursal antes de publicar un servicio.', 'error');

            return redirect()->route('servicios.lista');
        }

        $s = DB::selectOne('SELECT nombre FROM servicio WHERE id_servicio = ?', [$id]);
        if (! $s) {
            flash('Servicio no encontrado.', 'error');

            return redirect()->route('servicios.lista');
        }

        // **Es un interruptor: publica y despublica.** Antes sólo agregaba,
        // así que no había forma de sacar un servicio de la carta de un local
        // sin darlo de baja en todo el salón — y es lo que hace falta para
        // decidir qué ve la clienta en cada sucursal.
        $sacar = (bool) $request->input('sacar', 0);

        // **Sin ninguna fila el servicio vale en TODAS**, así que tocar una
        // sola lo dejaría valiendo únicamente ahí —al revés de lo que se
        // pidió—. En ese caso se materializa el estado que ya tenía: se
        // escriben todas las sucursales activas, y recién entonces agregar o
        // quitar una significa lo que dice.
        if (! DB::scalar('SELECT COUNT(*) FROM servicio_sucursal WHERE id_servicio = ?', [$id])) {
            if (! $sacar) {
                flash('«' . $s->nombre . '» ya se ofrece en todas las sucursales.', 'info');

                return redirect()->back();
            }

            DB::insert(
                'INSERT IGNORE INTO servicio_sucursal (id_servicio, id_sucursal)
                 SELECT ?, id_sucursal FROM sucursal WHERE activo = 1', [$id]
            );
        }

        if ($sacar) {
            // **No se deja sin ninguna.** Un servicio sin filas vuelve a valer
            // en todas, que es justo lo contrario de sacarlo del último local.
            $quedan = (int) DB::scalar(
                'SELECT COUNT(*) FROM servicio_sucursal WHERE id_servicio = ? AND id_sucursal <> ?', [$id, $suc]);
            if ($quedan === 0) {
                flash('«' . $s->nombre . '» quedaría sin ninguna sucursal, y eso lo devolvería a todas. '
                    . 'Si no se hace en ningún local, dalo de baja desde el catálogo.', 'warning');

                return redirect()->back();
            }

            DB::delete('DELETE FROM servicio_sucursal WHERE id_servicio = ? AND id_sucursal = ?', [$id, $suc]);
            Auditoria::registrar('PUBLICACION', 'servicios', 'servicio_sucursal', $id,
                'Se dejó de ofrecer «' . $s->nombre . '» en ' . Sucursales::nombreActiva());
            flash('«' . $s->nombre . '» ya no se ofrece en ' . Sucursales::nombreActiva()
                . ': la clienta deja de verlo al reservar acá.');

            return redirect()->back();
        }

        DB::insert('INSERT IGNORE INTO servicio_sucursal (id_servicio, id_sucursal) VALUES (?,?)', [$id, $suc]);

        Auditoria::registrar('PUBLICACION', 'servicios', 'servicio_sucursal', $id,
            'Se publicó «' . $s->nombre . '» en ' . Sucursales::nombreActiva());

        flash('«' . $s->nombre . '» ya se ofrece en ' . Sucursales::nombreActiva() . '.');

        return redirect()->back();
    }

    // ---------- Categorías ----------

    public function categorias(): View
    {
        return view('servicios.categorias', [
            // **Las categorias de este local SE DEDUCEN**, por decision del
            // usuario: no hay tabla que diga cuales publica cada sede. Una
            // categoria se ve si tiene al menos un servicio de aca, y se cuenta
            // con los de aca — con el conteo del salon entero, una categoria con
            // ocho servicios en la casa central y ninguno aca diria «8» y quien
            // la mira no encontraria ninguno en su lista.
            //
            // Deducirlas evita una pantalla mas y evita el caso raro: una
            // categoria marcada para un local pero sin un solo servicio ahi.
            'rows' => DB::select(
                'SELECT cs.*, (SELECT COUNT(*) FROM servicio s
                                WHERE s.id_categoria_servicio = cs.id_categoria_servicio
                                  AND (:s1 = 0
                                       OR EXISTS (SELECT 1 FROM servicio_sucursal ss
                                                   WHERE ss.id_servicio = s.id_servicio AND ss.id_sucursal = :s2)
                                       OR NOT EXISTS (SELECT 1 FROM servicio_sucursal ss2
                                                       WHERE ss2.id_servicio = s.id_servicio))) AS usos
                   FROM categoria_servicio cs ORDER BY nombre',
                ['s1' => Sucursales::activa(), 's2' => Sucursales::activa()]
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

    // ---------- Zonas del cuerpo ----------
    //
    //  Qué parte del cuerpo ocupa cada servicio. **Es lo que decide si dos
    //  servicios pueden hacerse a la vez**: dos cosas sobre el mismo pelo no,
    //  el pelo y las manos sí. Se administran acá y no en un archivo de
    //  configuración porque cada salón tiene las suyas — el que suma masajes o
    //  depilación no debería necesitar una versión nueva del sistema.

    public function zonas(): View
    {
        return view('servicios.zonas', [
            'rows' => DB::select(
                'SELECT z.*, (SELECT COUNT(*) FROM servicio s WHERE s.id_zona = z.id_zona) AS usos
                   FROM zona_servicio z ORDER BY z.nombre'
            ),
            // Los que todavía no tienen zona: mientras estén así se pueden hacer
            // junto con cualquier cosa, y eso casi nunca es lo que el salón
            // quiere. Se dicen por su nombre en vez de dejar que se descubra
            // cuando una cita salga durando menos de lo que dura de verdad.
            'sinZona' => DB::select(
                'SELECT id_servicio, nombre FROM servicio
                  WHERE activo = 1 AND id_zona IS NULL ORDER BY nombre'
            ),
        ]);
    }

    public function zonaCrear(Request $request): RedirectResponse
    {
        $nombre = trim((string) $request->input('nombre', ''));
        if ($nombre !== '') {
            try {
                DB::insert('INSERT INTO zona_servicio (nombre) VALUES (?)', [$nombre]);
                Auditoria::registrar('ALTA', 'Servicios', 'zona_servicio',
                    (int) DB::getPdo()->lastInsertId(), $nombre);
                flash('Zona agregada.');
            } catch (Throwable) {
                flash('Esa zona ya existe.', 'error');
            }
        }

        return redirect()->route('servicios.zonas');
    }

    public function zonaEditar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $nombre = trim((string) $request->input('nombre', ''));

        if (! $id || $nombre === '') {
            flash('El nombre no puede quedar vacío.', 'error');

            return redirect()->route('servicios.zonas');
        }

        try {
            DB::update('UPDATE zona_servicio SET nombre = ? WHERE id_zona = ?', [$nombre, $id]);
            Auditoria::registrar('MODIFICACION', 'Servicios', 'zona_servicio', $id, $nombre);
            flash('Zona actualizada.');
        } catch (Throwable) {
            flash('Ya existe otra zona con ese nombre.', 'error');
        }

        return redirect()->route('servicios.zonas');
    }

    public function zonaBorrar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $usos = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE id_zona = ?', [$id]);

        if ($usos) {
            // No se borra con servicios adentro: quedarían sin zona y pasarían a
            // poder hacerse junto con cualquier cosa, en silencio.
            flash("No se puede eliminar: hay $usos servicio(s) en esa zona. "
                . 'Cambialos de zona primero.', 'warning');
        } else {
            DB::delete('DELETE FROM zona_servicio WHERE id_zona = ?', [$id]);
            Auditoria::registrar('BAJA', 'Servicios', 'zona_servicio', $id, 'Zona eliminada');
            flash('Zona eliminada.');
        }

        return redirect()->route('servicios.zonas');
    }

    // ---------- Descuentos y promociones ----------

    public function descuentos(): View
    {
        return view('servicios.descuentos', [
            'rows' => DB::select('SELECT * FROM descuento ORDER BY activo DESC, nombre'),
            // Cuánto hay que facturar para dar un punto. Es una decisión
            // comercial del salón, así que se edita acá y no en un archivo.
            'puntosCadaGs' => Config::puntosCadaGs(),
        ]);
    }

    /**
     * Cuántos guaraníes facturados valen un punto.
     *
     * Vivía en `config/spg.php`, o sea que cambiarlo era editar código y volver
     * a desplegar. Es un número del negocio —lo decide el salón— y va con los
     * descuentos porque contesta la misma pregunta que ellos: **cuánto se le
     * devuelve al cliente por comprar acá.**
     *
     * Pide `servicios.descuentos`, el mismo permiso que las promociones: subir
     * o bajar esta relación es fijar cuánto regala el salón, y por eso el
     * Profesional no lo tiene desde la 6.4.0.
     */
    public function puntosGuardar(Request $request): RedirectResponse
    {
        $antes = Config::puntosCadaGs();
        $gs = entero($request->input('puntos_cada_gs'));
        $volver = redirect()->route('servicios.descuentos');

        if (Config::guardarPuntosCadaGs($gs) === null) {
            flash('La relación tiene que ir de Gs. 100 a Gs. 10.000.000 por punto. '
                . 'Con menos, un punto valdría casi nada; con más, no se llegaría nunca.', 'error');

            return $volver;
        }

        Auditoria::registrar('MODIFICACION', 'Servicios', 'configuracion', 1,
            'Puntos: de 1 cada ' . money($antes) . ' a 1 cada ' . money($gs));

        // **Lo ya acumulado no se recalcula**, y conviene decirlo: los puntos
        // que tiene cada clienta son movimientos ya escritos en
        // `movimiento_punto`. Cambiar la relación afecta de acá en adelante.
        flash('Listo: de ahora en más, 1 punto por cada ' . money($gs) . ' facturados. '
            . 'Los puntos que las clientas ya tienen no cambian.');

        return $volver;
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
