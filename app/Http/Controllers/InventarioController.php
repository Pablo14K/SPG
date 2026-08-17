<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Borrador;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use App\Servicios\Sucursales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Inventario: productos, stock, movimientos, compras y proveedores.
 *
 * Dos cosas del modelo que conviene no perder de vista:
 *
 *  · **El stock no se guarda**: lo calcula `fn_producto_stock` sumando los
 *    movimientos según su signo. No hay una columna «stock» que pueda quedar
 *    desfasada de la realidad.
 *
 *  · **Lo que se compra y lo que se gasta no se miden igual.** El shampoo se
 *    compra por frasco de 1 litro y se usa de a 30 ml. El stock se guarda
 *    siempre en la unidad de compra —la que factura el proveedor—, y la
 *    conversión pasa al entrar y al salir.
 */
class InventarioController extends Controller
{
    /** Tipos de movimiento: 2 salida por consumo · 3 ajuste + · 4 ajuste − · 9 stock inicial */
    private const AJUSTE_MAS = 3;

    private const AJUSTE_MENOS = 4;

    private const STOCK_INICIAL = 9;

    public function index(): View
    {
        return view('inventario.index', [
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'inventario.productos', 'ruta' => 'inventario.productos', 'ic' => 'box-seam',
                 't' => 'Productos', 'd' => 'Catálogo, precios y stock mínimo'],
                ['p' => 'inventario.productos', 'ruta' => 'inventario.categorias', 'ic' => 'tags',
                 't' => 'Categorías', 'd' => 'Tipos de producto'],
                ['p' => 'inventario.proveedores', 'ruta' => 'inventario.proveedores', 'ic' => 'truck',
                 't' => 'Proveedores', 'd' => 'Datos y saldos'],
                ['p' => 'inventario.stock', 'ruta' => 'inventario.stock', 'ic' => 'clipboard-data',
                 't' => 'Stock', 'd' => 'Existencias y alertas de reposición'],
                ['p' => 'inventario.stock', 'ruta' => 'inventario.movimientos', 'ic' => 'arrow-left-right',
                 't' => 'Movimientos', 'd' => 'Entradas y salidas de stock'],
                ['p' => 'inventario.compras', 'ruta' => 'inventario.compras', 'ic' => 'bag',
                 't' => 'Compras', 'd' => 'Ingresos de mercadería'],
            ]),
        ]);
    }

    // ---------- Productos ----------

    public function productos(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Nombre del producto', 'ancho' => '240px'],
            'categoria' => ['tipo' => 'select', 'etiqueta' => 'Categoría',
                            'opciones' => ['' => 'Todas'] + $this->categoriasPorNombre()],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos', '1' => 'Activos', '0' => 'Inactivos']],
            'stock' => ['tipo' => 'select', 'etiqueta' => 'Existencias',
                        'opciones' => ['' => 'Todas', 'bajo' => 'Bajo el mínimo', 'cero' => 'Sin stock', 'hay' => 'Con stock']],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['v.nombre'], Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'categoria')) {
            $w[] = 'v.categoria = :c';
            $par['c'] = Listado::valor($f, 'categoria');
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'v.activo = :e';
            $par['e'] = (int) Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'stock')) {
            $w[] = ['bajo' => 'v.stock_actual < v.stock_minimo',
                    'cero' => 'v.stock_actual <= 0',
                    'hay' => 'v.stock_actual > 0'][Listado::valor($f, 'stock')];
        }

        $w[] = ltrim(Sucursales::filtro('v', $par), ' AND') ?: '1=1';
        $desde = 'FROM vw_producto_stock v WHERE ' . implode(' AND ', $w);

        if (Listado::pideExport()) {
            return Listado::exportar('productos',
                // «Venta» sale de la exportación por lo mismo que de la pantalla:
                // el salón vende servicios, no productos (ver el formulario del
                // producto). Para revertirlo, se devuelven la columna y el campo.
                ['Producto', 'Categoría', 'Unidad', 'Stock', 'Mínimo', 'Costo', 'Estado'],
                array_map(fn ($r) => [$r->nombre, $r->categoria, $r->unidad_medida, $r->stock_actual,
                    $r->stock_minimo, $r->precio_costo, $r->activo ? 'Activo' : 'Inactivo'],
                    DB::select("SELECT * $desde ORDER BY v.nombre", $par)),
                $f, 'Productos'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('inventario.productos', [
            'rows' => DB::select("SELECT * $desde ORDER BY v.nombre LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    public function productoForm(int $id = 0): View|RedirectResponse
    {
        $p = $id ? DB::selectOne('SELECT * FROM producto WHERE id_producto = ?', [$id]) : null;
        if ($id && ! $p) {
            flash('Producto no encontrado.', 'error');

            return redirect()->route('inventario.productos');
        }

        return view('inventario.producto_form', [
            'p' => $p,
            'cats' => DB::select('SELECT * FROM categoria_producto ORDER BY nombre'),
        ]);
    }

    public function productoGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_producto', 0);
        $d = [
            'id_categoria' => (int) $request->input('id_categoria', 0),
            'nombre' => trim((string) $request->input('nombre', '')),
            'descripcion' => trim((string) $request->input('descripcion', '')) ?: null,
            'unidad_medida' => trim((string) $request->input('unidad_medida', 'unidad')) ?: 'unidad',
            // Los dos van juntos o ninguno: con uno solo no se puede convertir
            'contenido' => num($request->input('contenido')) ?: null,
            'unidad_consumo' => trim((string) $request->input('unidad_consumo', '')) ?: null,
            'stock_minimo' => num($request->input('stock_minimo')),
            'precio_costo' => num($request->input('precio_costo')),
            'tasa_iva' => (int) $request->input('tasa_iva', 10),
        ];
        $stockInicial = num($request->input('stock_inicial'));

        // Un producto nace en el local en el que se está trabajando: cada
        // sucursal tiene su catálogo, por decisión del usuario.
        $d['id_sucursal'] = Sucursales::activa() ?: 1;

        // **El precio de venta ya NO se pide** —el salón vende servicios, no
        // productos— pero la columna es NOT NULL y sigue en la base, así que
        // hay que darle un valor. Y hay que darle **el que ya tenía**: leerlo
        // del formulario que no lo manda daría 0, y editar cualquier producto
        // le borraría el precio cargado. Si algún día se revierte la decisión,
        // lo que el salón había puesto sigue estando.
        $d['precio_venta'] = $id
            ? (float) DB::scalar('SELECT precio_venta FROM producto WHERE id_producto = ?', [$id])
            : 0.0;
        $volver = $id ? redirect()->route('inventario.producto_form', $id) : redirect()->route('inventario.producto_form');

        $error = null;
        if ($d['nombre'] === '') {
            $error = 'El nombre del producto es obligatorio.';
        } elseif ($d['id_categoria'] <= 0
            || ! DB::scalar('SELECT COUNT(*) FROM categoria_producto WHERE id_categoria = ?', [$d['id_categoria']])) {
            $error = 'Elegí una categoría válida.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM producto WHERE nombre = ? AND id_producto <> ?', [$d['nombre'], $id])) {
            $error = 'Ya existe un producto con ese nombre.';
        } elseif ($d['stock_minimo'] < 0 || $d['precio_costo'] < 0) {
            $error = 'El precio y el stock mínimo no pueden ser negativos.';
        } elseif (! in_array($d['tasa_iva'], [0, 5, 10], true)) {
            $error = 'La tasa de IVA debe ser 0, 5 o 10.';
        } elseif ($stockInicial < 0) {
            $error = 'El stock inicial no puede ser negativo.';
        } elseif (($d['contenido'] === null) !== ($d['unidad_consumo'] === null)) {
            $error = 'Para un producto que se usa de a poco hacen falta las dos cosas: '
                   . 'cuánto trae cada unidad y en qué se mide.';
        } elseif ($d['contenido'] !== null && $d['contenido'] <= 0) {
            $error = 'El contenido de cada unidad tiene que ser mayor a cero.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        try {
            if ($id) {
                DB::update(
                    'UPDATE producto SET id_categoria=:id_categoria, nombre=:nombre, descripcion=:descripcion,
                        unidad_medida=:unidad_medida, contenido=:contenido, unidad_consumo=:unidad_consumo,
                        stock_minimo=:stock_minimo, precio_costo=:precio_costo, precio_venta=:precio_venta,
                        tasa_iva=:tasa_iva
                      WHERE id_producto=:id', $d + ['id' => $id]
                );
                Auditoria::registrar('MODIFICACION', 'Inventario', 'producto', $id, $d['nombre']);
                flash('Producto actualizado.');
            } else {
                DB::insert(
                    'INSERT INTO producto (id_sucursal,id_categoria,nombre,descripcion,unidad_medida,contenido,unidad_consumo,
                        stock_minimo,precio_costo,precio_venta,tasa_iva)
                     VALUES (:id_sucursal,:id_categoria,:nombre,:descripcion,:unidad_medida,:contenido,:unidad_consumo,
                        :stock_minimo,:precio_costo,:precio_venta,:tasa_iva)', $d
                );
                $id = (int) DB::getPdo()->lastInsertId();
                Auditoria::registrar('ALTA', 'Inventario', 'producto', $id, $d['nombre']);

                // Stock inicial sin pasar por una compra
                if ($stockInicial > 0) {
                    Bd::procedimiento('sp_registrar_movimiento_inventario', [
                        $id, (int) session('uid'), self::STOCK_INICIAL, $stockInicial,
                        $d['precio_costo'], 'ALTA', 'Stock inicial cargado al crear el producto',
                    ]);
                    flash('Producto creado con un stock inicial de ' . cant($stockInicial) . ' ' . $d['unidad_medida'] . '.');
                } else {
                    flash('Producto creado. Cargale stock desde «Cargar stock» cuando lo tengas.');
                }
            }

            // Se compra por envase y nadie dijo qué trae adentro.
            //
            // No se rechaza —hay envases que sí se gastan enteros— pero se
            // avisa, porque el efecto no se ve hasta que alguien registra una
            // atención: ahí la pantalla pide «cantidad» en cajas, y un 1
            // descuenta la caja entera cuando lo que se usó fue un par de
            // guantes. Es exactamente lo que pasó con «Guantes de latex (caja)».
            if (unidad_es_envase($d['unidad_medida']) && $d['contenido'] === null) {
                flash('Ojo: lo cargaste por «' . $d['unidad_medida'] . '» y no dijiste cuánto trae cada una, '
                    . 'así que al registrar una atención se va a descontar de a ' . $d['unidad_medida']
                    . ' enteras. Si se gasta por partes, completá «Contenido de cada unidad» y '
                    . '«Se gasta en» —por ejemplo 100 y «par»— y el sistema hace la cuenta solo.', 'warning');
            }
        } catch (Throwable) {
            flash('No se pudo guardar el producto.', 'error');

            return $volver->withInput();
        }

        return redirect()->route('inventario.productos');
    }

    public function productoBaja(Request $request): RedirectResponse
    {
        DB::update('UPDATE producto SET activo = 1 - activo WHERE id_producto = ?',
            [(int) $request->input('id_producto', 0)]);
        flash('Estado del producto actualizado.');

        return redirect()->route('inventario.productos');
    }

    // ---------- Categorías de producto ----------

    public function categorias(): View
    {
        return view('inventario.categorias', [
            'rows' => DB::select(
                'SELECT c.*, (SELECT COUNT(*) FROM producto p WHERE p.id_categoria = c.id_categoria) AS usos
                   FROM categoria_producto c ORDER BY c.nombre'
            ),
        ]);
    }

    public function categoriaCrear(Request $request): RedirectResponse
    {
        $nombre = trim((string) $request->input('nombre', ''));
        if ($nombre === '') {
            flash('Escribí el nombre de la categoría.', 'error');

            return redirect()->route('inventario.categorias');
        }

        try {
            DB::insert('INSERT INTO categoria_producto (nombre) VALUES (?)', [$nombre]);
            Auditoria::registrar('ALTA', 'Inventario', 'categoria_producto', (int) DB::getPdo()->lastInsertId(), $nombre);
            flash('Categoría «' . $nombre . '» agregada.');
        } catch (Throwable) {
            flash('Ya existe una categoría con ese nombre.', 'error');
        }

        return redirect()->route('inventario.categorias');
    }

    public function categoriaEditar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $nombre = trim((string) $request->input('nombre', ''));
        if (! $id || $nombre === '') {
            flash('El nombre no puede quedar vacío.', 'error');

            return redirect()->route('inventario.categorias');
        }

        try {
            DB::update('UPDATE categoria_producto SET nombre = ? WHERE id_categoria = ?', [$nombre, $id]);
            Auditoria::registrar('MODIFICACION', 'Inventario', 'categoria_producto', $id, $nombre);
            flash('Categoría actualizada.');
        } catch (Throwable) {
            flash('Ya existe otra categoría con ese nombre.', 'error');
        }

        return redirect()->route('inventario.categorias');
    }

    public function categoriaBorrar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $c = DB::selectOne('SELECT nombre FROM categoria_producto WHERE id_categoria = ?', [$id]);
        if (! $c) {
            flash('Esa categoría no existe.', 'error');

            return redirect()->route('inventario.categorias');
        }

        // No se borra una categoría en uso: los productos quedarían sin clasificar
        $usos = (int) DB::scalar('SELECT COUNT(*) FROM producto WHERE id_categoria = ?', [$id]);
        if ($usos) {
            flash("No se puede eliminar «{$c->nombre}»: hay $usos producto(s) en esa categoría.", 'warning');

            return redirect()->route('inventario.categorias');
        }

        try {
            DB::delete('DELETE FROM categoria_producto WHERE id_categoria = ?', [$id]);
            Auditoria::registrar('BAJA', 'Inventario', 'categoria_producto', $id, 'Eliminó ' . $c->nombre);
            flash('Categoría eliminada.');
        } catch (Throwable) {
            flash('No se pudo eliminar la categoría.', 'error');
        }

        return redirect()->route('inventario.categorias');
    }

    // ---------- Stock y movimientos ----------

    public function stock(): View
    {
        $ps = []; $pb = [];

        return view('inventario.stock', [
            'rows' => DB::select('SELECT * FROM vw_producto_stock WHERE activo = 1' . Sucursales::filtro('vw_producto_stock', $ps) . ' ORDER BY nombre', $ps),
            'bajo' => DB::select('SELECT * FROM vw_producto_bajo_stock WHERE 1=1' . Sucursales::filtro('vw_producto_bajo_stock', $pb, 'sucb') . ' ORDER BY faltante DESC', $pb),
        ]);
    }

    public function movimientos(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'producto' => ['tipo' => 'select', 'etiqueta' => 'Producto', 'ancho' => '220px',
                           'opciones' => ['' => 'Todos'] + $this->productosPorId()],
            'tipo' => ['tipo' => 'select', 'etiqueta' => 'Tipo',
                       'opciones' => ['' => 'Todos'] + $this->tiposMovimiento()],
            'signo' => ['tipo' => 'select', 'etiqueta' => 'Sentido',
                        'opciones' => ['' => 'Ambos', 'E' => 'Entradas', 'S' => 'Salidas']],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'producto')) {
            $w[] = 'm.id_producto = :p';
            $par['p'] = (int) Listado::valor($f, 'producto');
        }
        if (Listado::hay($f, 'tipo')) {
            $w[] = 'm.id_tipo_movimiento = :t';
            $par['t'] = (int) Listado::valor($f, 'tipo');
        }
        if (Listado::hay($f, 'signo')) {
            $w[] = 'tm.signo = :s';
            $par['s'] = Listado::valor($f, 'signo');
        }
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(m.fecha) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(m.fecha) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        $desde = 'FROM movimiento_inventario m
                  JOIN producto p ON p.id_producto = m.id_producto
                  JOIN tipo_movimiento_inventario tm ON tm.id_tipo_movimiento = m.id_tipo_movimiento
                  JOIN usuario u  ON u.id_usuario = m.id_usuario
                  JOIN persona pe_u ON pe_u.id_persona = u.id_persona
                  WHERE ' . implode(' AND ', $w);
        $cols = "m.fecha, m.cantidad, m.precio_unitario, m.referencia, m.observaciones,
                 p.nombre AS producto, p.unidad_medida, p.contenido, p.unidad_consumo,
                 tm.nombre AS tipo, tm.signo,
                 CONCAT(pe_u.nombre,' ',pe_u.apellido) AS usuario";
        $orden = 'ORDER BY m.fecha DESC, m.id_movimiento DESC';

        if (Listado::pideExport()) {
            return Listado::exportar('movimientos',
                ['Fecha', 'Producto', 'Tipo', 'Sentido', 'Cantidad', 'Unidad', 'Precio', 'Referencia', 'Usuario', 'Observaciones'],
                array_map(fn ($r) => [fecha($r->fecha, 'd/m/Y H:i'), $r->producto, $r->tipo,
                    $r->signo === 'E' ? 'Entrada' : 'Salida', $r->cantidad, $r->unidad_medida,
                    $r->precio_unitario, $r->referencia, $r->usuario, $r->observaciones],
                    DB::select("SELECT $cols $desde $orden", $par)),
                $f, 'Movimientos de stock'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par), 30);
        $idp = (int) Listado::valor($f, 'producto');

        return view('inventario.movimientos', [
            'rows' => DB::select("SELECT $cols $desde $orden LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
            'prod' => $idp ? DB::selectOne(
                'SELECT nombre, unidad_medida, contenido, unidad_consumo, fn_producto_stock(id_producto) AS stock
                   FROM producto WHERE id_producto = ?', [$idp]
            ) : null,
        ]);
    }

    // ---------- Cargar / corregir stock sin pasar por una compra ----------
    //
    //  Dos modos: «fijar» deja el stock en el número que se indique (calcula la
    //  diferencia y genera el ajuste que corresponda) y «movimiento» registra
    //  una entrada o salida puntual (merma, devolución, inventario inicial).

    public function ajuste(): View
    {
        // Sólo los productos de ESTE local: cargarle stock a un producto de la
        // otra sucursal sería mover mercadería que no está acá.
        $pp = [];

        return view('inventario.ajuste', [
            'prods' => DB::select(
                'SELECT id_producto, nombre, unidad_medida, contenido, unidad_consumo, precio_costo,
                        fn_producto_stock(id_producto) AS stock
                   FROM producto WHERE activo = 1' . Sucursales::filtro('producto', $pp) . ' ORDER BY nombre', $pp
            ),
            'tipos' => DB::select('SELECT * FROM tipo_movimiento_inventario WHERE activo = 1 ORDER BY signo DESC, nombre'),
            'cats' => DB::select('SELECT id_categoria, nombre FROM categoria_producto ORDER BY nombre'),
            'sel' => (int) request()->query('producto', 0),
        ]);
    }

    public function ajusteGuardar(Request $request): RedirectResponse
    {
        $idp = (int) $request->input('id_producto', 0);
        $modo = (string) $request->input('modo', 'movimiento');
        $ref = trim((string) $request->input('referencia', '')) ?: null;
        $obs = trim((string) $request->input('observaciones', '')) ?: null;
        $precio = num($request->input('precio_unitario'));
        $volver = redirect()->route('inventario.ajuste');

        $prod = DB::selectOne(
            'SELECT id_producto, nombre, unidad_medida, precio_costo, fn_producto_stock(id_producto) AS stock
               FROM producto WHERE id_producto = ? AND activo = 1', [$idp]
        );
        if (! $prod) {
            flash('Elegí un producto activo.', 'error');

            return $volver;
        }
        if ($precio < 0) {
            flash('El precio no puede ser negativo.', 'error');

            return $volver;
        }

        if ($modo === 'fijar') {
            $destino = num($request->input('stock_nuevo'), -1);
            if ($destino < 0) {
                flash('Indicá en cuánto tiene que quedar el stock.', 'error');

                return $volver;
            }
            $actual = (float) $prod->stock;
            $dif = round($destino - $actual, 2);
            if (abs($dif) < 0.005) {
                flash('El stock de ' . $prod->nombre . ' ya es ' . cant($destino) . ': no hay nada que ajustar.', 'info');

                return redirect()->route('inventario.stock');
            }

            try {
                Bd::procedimiento('sp_registrar_movimiento_inventario', [
                    $idp, (int) session('uid'), $dif > 0 ? self::AJUSTE_MAS : self::AJUSTE_MENOS,
                    abs($dif), $precio ?: (float) $prod->precio_costo, $ref ?: 'AJUSTE',
                    $obs ?: ('Stock fijado en ' . cant($destino) . ' (antes ' . cant($actual) . ')'),
                ]);
                Auditoria::registrar('AJUSTE_STOCK', 'Inventario', 'producto', $idp,
                    $prod->nombre . ': ' . cant($actual) . ' → ' . cant($destino));
                flash('Stock de ' . $prod->nombre . ' ajustado a ' . cant($destino) . ' ' . $prod->unidad_medida . '.');
            } catch (Throwable) {
                flash('No se pudo ajustar el stock.', 'error');
            }

            return redirect()->route('inventario.stock');
        }

        // Modo movimiento puntual
        $tipoMov = (int) $request->input('id_tipo_movimiento', 0);
        $cantidad = num($request->input('cantidad'));
        $tipo = DB::selectOne(
            'SELECT id_tipo_movimiento, nombre, signo FROM tipo_movimiento_inventario
              WHERE id_tipo_movimiento = ? AND activo = 1', [$tipoMov]
        );
        if (! $tipo) {
            flash('Elegí un tipo de movimiento válido.', 'error');

            return $volver;
        }
        if ($cantidad <= 0) {
            flash('La cantidad tiene que ser mayor a cero.', 'error');

            return $volver;
        }
        if ($tipo->signo === 'S' && $cantidad > (float) $prod->stock) {
            flash('No hay stock suficiente: ' . $prod->nombre . ' tiene ' . cant($prod->stock)
                . ' ' . $prod->unidad_medida . '.', 'error');

            return $volver;
        }

        try {
            Bd::procedimiento('sp_registrar_movimiento_inventario',
                [$idp, (int) session('uid'), $tipoMov, $cantidad, $precio, $ref, $obs]);
            Auditoria::registrar('MOVIMIENTO_STOCK', 'Inventario', 'producto', $idp,
                $tipo->nombre . ' de ' . cant($cantidad) . ' — ' . $prod->nombre);
            flash('Movimiento registrado: ' . $tipo->nombre . ' de ' . cant($cantidad) . ' ' . $prod->unidad_medida . '.');
        } catch (Throwable $ex) {
            flash(str_contains($ex->getMessage(), 'stock')
                ? 'No hay stock suficiente para esa salida.' : 'No se pudo registrar el movimiento.', 'error');
        }

        return redirect()->route('inventario.stock');
    }

    // ---------- Proveedores ----------

    public function proveedores(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Razón social, RUC o contacto', 'ancho' => '280px'],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos', '1' => 'Activos', '0' => 'Inactivos']],
            'saldo' => ['tipo' => 'select', 'etiqueta' => 'Deuda',
                        'opciones' => ['' => 'Todos', 'pend' => 'Con saldo pendiente', 'ok' => 'Sin deuda']],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['pe.nombre', 'pe.ruc', 'p.contacto', 'pe.telefono'],
                Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'p.activo = :e';
            $par['e'] = (int) Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'saldo')) {
            $w[] = Listado::valor($f, 'saldo') === 'pend'
                ? 'fn_proveedor_saldo(p.id_proveedor) > 0'
                : 'fn_proveedor_saldo(p.id_proveedor) <= 0';
        }

        $desde = 'FROM proveedor p JOIN persona pe ON pe.id_persona = p.id_persona WHERE ' . implode(' AND ', $w);
        $cols = 'p.*, pe.nombre, pe.ruc, pe.telefono, pe.email, pe.direccion,
                 fn_proveedor_saldo(p.id_proveedor) AS saldo';

        if (Listado::pideExport()) {
            return Listado::exportar('proveedores',
                ['Proveedor', 'RUC', 'Contacto', 'Teléfono', 'Email', 'Dirección', 'Saldo', 'Estado'],
                array_map(fn ($r) => [$r->nombre, $r->ruc, $r->contacto, $r->telefono, $r->email,
                    $r->direccion, $r->saldo, $r->activo ? 'Activo' : 'Inactivo'],
                    DB::select("SELECT $cols $desde ORDER BY pe.nombre", $par)),
                $f, 'Proveedores'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('inventario.proveedores', [
            'rows' => DB::select("SELECT $cols $desde ORDER BY pe.nombre LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    public function proveedorForm(int $id = 0): View|RedirectResponse
    {
        $p = $id ? DB::selectOne(
            'SELECT p.*, pe.nombre, pe.ruc, pe.telefono, pe.email, pe.direccion
               FROM proveedor p JOIN persona pe ON pe.id_persona = p.id_persona
              WHERE p.id_proveedor = ?', [$id]
        ) : null;

        if ($id && ! $p) {
            flash('Proveedor no encontrado.', 'error');

            return redirect()->route('inventario.proveedores');
        }

        return view('inventario.proveedor_form', ['p' => $p]);
    }

    public function proveedorGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_proveedor', 0);
        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'contacto' => trim((string) $request->input('contacto', '')) ?: null,
            'ruc' => trim((string) $request->input('ruc', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'direccion' => trim((string) $request->input('direccion', '')) ?: null,
        ];
        $volver = $id ? redirect()->route('inventario.proveedor_form', $id) : redirect()->route('inventario.proveedor_form');

        $error = $d['nombre'] === '' ? 'El nombre es obligatorio.' : Persona::error($d);
        if ($error) {
            flash($error, 'error');

            return $volver->withInput();
        }

        try {
            if ($id) {
                $idPersona = (int) DB::scalar('SELECT id_persona FROM proveedor WHERE id_proveedor = ?', [$id]);
                Persona::guardar($idPersona, $d);
                DB::update('UPDATE proveedor SET contacto = :contacto WHERE id_proveedor = :id',
                    ['contacto' => $d['contacto'], 'id' => $id]);
                Auditoria::registrar('MODIFICACION', 'Inventario', 'proveedor', $id, $d['nombre']);
                flash('Proveedor actualizado.');
            } else {
                // Si el RUC ya está cargado puede ser la misma empresa: se
                // reutiliza la persona en vez de duplicarla.
                $idPersona = Persona::guardar(Persona::porDocumento(null, $d['ruc']), $d);
                DB::insert('INSERT INTO proveedor (id_persona, contacto) VALUES (?,?)', [$idPersona, $d['contacto']]);
                Auditoria::registrar('ALTA', 'Inventario', 'proveedor', (int) DB::getPdo()->lastInsertId(), $d['nombre']);
                flash('Proveedor creado.');
            }
        } catch (Throwable) {
            flash('No se pudo guardar (¿RUC duplicado?).', 'error');

            return $volver->withInput();
        }

        return redirect()->route('inventario.proveedores');
    }

    public function proveedorBaja(Request $request): RedirectResponse
    {
        DB::update('UPDATE proveedor SET activo = 1 - activo WHERE id_proveedor = ?',
            [(int) $request->input('id_proveedor', 0)]);
        flash('Estado del proveedor actualizado.');

        return redirect()->route('inventario.proveedores');
    }

    // ---------- Compras ----------

    public function compras(): View|StreamedResponse
    {
        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Proveedor o nº de factura', 'ancho' => '250px'],
            'estado' => ['tipo' => 'select', 'etiqueta' => 'Estado',
                         'opciones' => ['' => 'Todos'] + $this->estadosCompra()],
            'saldo' => ['tipo' => 'select', 'etiqueta' => 'Deuda',
                        'opciones' => ['' => 'Todas', 'pend' => 'Con saldo', 'ok' => 'Pagadas']],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['v.proveedor', 'c.nro_factura_proveedor'], Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'estado')) {
            $w[] = 'v.estado = :e';
            $par['e'] = Listado::valor($f, 'estado');
        }
        if (Listado::hay($f, 'saldo')) {
            $w[] = Listado::valor($f, 'saldo') === 'pend'
                ? 'fn_compra_saldo(v.id_compra) > 0' : 'fn_compra_saldo(v.id_compra) <= 0';
        }
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(v.fecha) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(v.fecha) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        $w[] = ltrim(Sucursales::filtro('c', $par), ' AND') ?: '1=1';
        $desde = 'FROM vw_compra_resumen v JOIN compra c ON c.id_compra = v.id_compra WHERE ' . implode(' AND ', $w);
        $cols = 'v.*, c.nro_factura_proveedor, fn_compra_saldo(v.id_compra) AS saldo,
                 (SELECT COUNT(*) FROM detalle_compra d WHERE d.id_compra = v.id_compra) AS items';

        if (Listado::pideExport()) {
            return Listado::exportar('compras',
                ['Fecha', 'Proveedor', 'Nº factura', 'Ítems', 'Total', 'Saldo', 'Estado', 'Registró'],
                array_map(fn ($r) => [fecha($r->fecha, 'd/m/Y'), $r->proveedor, $r->nro_factura_proveedor,
                    $r->items, $r->total, $r->saldo, $r->estado, $r->registro],
                    DB::select("SELECT $cols $desde ORDER BY v.fecha DESC", $par)),
                $f, 'Compras'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par));

        return view('inventario.compras', [
            'rows' => DB::select("SELECT $cols $desde ORDER BY v.fecha DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    public function compraVer(Request $request): View|RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        $compra = DB::selectOne(
            'SELECT v.*, fn_compra_saldo(v.id_compra) AS saldo, fn_compra_vencimiento(v.id_compra) AS vencimiento,
                    c.nro_factura_proveedor, cv.nombre AS condicion
               FROM vw_compra_resumen v
               JOIN compra c ON c.id_compra = v.id_compra
               JOIN condicion_venta cv ON cv.id_condicion_venta = c.id_condicion_venta
              WHERE v.id_compra = ?', [$id]
        );
        if (! $compra) {
            flash('Esa compra no existe.', 'error');

            return redirect()->route('inventario.compras');
        }

        return view('inventario.compra_ver', [
            'compra' => $compra,
            'lineas' => DB::select(
                'SELECT p.nombre, p.unidad_medida, cp.nombre AS categoria,
                        d.cantidad, d.precio_unitario,
                        ROUND(d.cantidad * d.precio_unitario, 2) AS total_linea
                   FROM detalle_compra d
                   JOIN producto p ON p.id_producto = d.id_producto
                   JOIN categoria_producto cp ON cp.id_categoria = p.id_categoria
                  WHERE d.id_compra = ? ORDER BY p.nombre', [$id]
            ),
        ]);
    }

    public function compraForm(Request $request): View
    {
        // Sólo los productos de este local: una compra ingresa mercadería acá.
        $pc = [];

        return view('inventario.compra_form', [
            'proveedores' => DB::select(
                'SELECT p.id_proveedor, pe.nombre FROM proveedor p
                   JOIN persona pe ON pe.id_persona = p.id_persona
                  WHERE p.activo = 1 ORDER BY pe.nombre'
            ),
            'categorias' => DB::select('SELECT id_categoria, nombre FROM categoria_producto ORDER BY nombre'),
            'condiciones' => DB::select('SELECT * FROM condicion_venta WHERE activo = 1 ORDER BY id_condicion_venta'),
            // El id va junto al nombre: la pantalla manda el id cuando el
            // producto ya existe, así un espacio de más no termina creando un
            // producto duplicado y partiendo el stock en dos.
            'productos' => DB::select('SELECT id_producto, nombre FROM producto WHERE activo = 1'
                . Sucursales::filtro('producto', $pc) . ' ORDER BY nombre', $pc),
            'sel_proveedor' => (int) $request->query('proveedor', 0),
        ]);
    }

    public function compraGuardar(Request $request): RedirectResponse
    {
        $idProveedor = (int) $request->input('id_proveedor', 0);
        $idCondicion = (int) $request->input('id_condicion_venta', 0);
        $nroFactura = trim((string) $request->input('nro_factura_proveedor', '')) ?: null;
        $obs = trim((string) $request->input('observaciones', '')) ?: null;

        $nombres = (array) $request->input('nombre', []);
        $idsProd = (array) $request->input('id_producto', []);   // lo pone el JS al elegir de la lista
        $cantidades = (array) $request->input('cantidad', []);
        $precios = (array) $request->input('precio', []);
        $categorias = (array) $request->input('categoria', []);
        $volver = redirect()->route('inventario.compra_form');

        if (! $idProveedor || ! DB::scalar('SELECT COUNT(*) FROM proveedor WHERE id_proveedor = ? AND activo = 1', [$idProveedor])) {
            flash('Elegí un proveedor activo.', 'error');

            return $volver->withInput();
        }
        if (! $idCondicion || ! DB::scalar('SELECT COUNT(*) FROM condicion_venta WHERE id_condicion_venta = ? AND activo = 1', [$idCondicion])) {
            flash('Elegí una condición de compra válida.', 'error');

            return $volver->withInput();
        }

        $catPorDefecto = (int) DB::scalar('SELECT MIN(id_categoria) FROM categoria_producto');
        $lineas = [];
        foreach ($nombres as $i => $nom) {
            $nom = trim((string) $nom);
            $cant = num($cantidades[$i] ?? 0);
            $prec = num($precios[$i] ?? 0);

            if ($nom === '' && $cant <= 0) {
                continue;   // fila vacía
            }
            if ($nom === '') {
                flash('Hay una fila con cantidad pero sin producto.', 'error');

                return $volver->withInput();
            }
            if ($cant <= 0) {
                flash('El producto «' . $nom . '» necesita una cantidad mayor a cero.', 'error');

                return $volver->withInput();
            }
            if ($prec < 0) {
                flash('El precio de «' . $nom . '» no puede ser negativo.', 'error');

                return $volver->withInput();
            }

            $lineas[] = [
                'nombre' => $nom,
                'id' => (int) ($idsProd[$i] ?? 0),
                'cantidad' => $cant,
                'precio' => $prec,
                'categoria' => (int) ($categorias[$i] ?? 0) ?: $catPorDefecto,
            ];
        }

        if (! $lineas) {
            flash('Agregá al menos un producto con cantidad.', 'error');

            return $volver->withInput();
        }

        // A crédito hay cuotas; al contado no. Sale de la condición elegida,
        // que es la que dice cuántos días de plazo tiene.
        $dias = (int) DB::scalar("SELECT dias_credito FROM condicion_venta WHERE id_condicion_venta = ?", [$idCondicion]);

        try {
            $r = DB::transaction(function () use ($idProveedor, $idCondicion, $nroFactura, $obs, $lineas, $dias, $request) {
                DB::insert(
                    'INSERT INTO compra (id_sucursal,id_proveedor,id_usuario,id_estado_compra,id_condicion_venta,
                        nro_factura_proveedor,observaciones)
                     VALUES (?,?,?,1,?,?,?)',
                    [Sucursales::activa() ?: 1, $idProveedor, (int) session('uid'),
                     $idCondicion, $nroFactura, $obs]
                );
                $idCompra = (int) DB::getPdo()->lastInsertId();

                $creados = [];
                $total = 0.0;

                foreach ($lineas as $l) {
                    $idp = 0;

                    // 1) Por id: es lo que manda la pantalla cuando se eligió de la lista
                    if ($l['id'] > 0) {
                        $idp = (int) (DB::scalar('SELECT id_producto FROM producto WHERE id_producto = ? LIMIT 1', [$l['id']]) ?: 0);
                    }
                    // 2) Por nombre normalizado: espacios de más colapsados y sin
                    //    distinguir mayúsculas ni tildes (la colación es _ci).
                    //    Sin esto, «Shampoo  1L» con doble espacio creaba un
                    //    producto nuevo y partía el stock en dos.
                    if (! $idp) {
                        $idp = (int) (DB::scalar(
                            "SELECT id_producto FROM producto
                              WHERE TRIM(REGEXP_REPLACE(nombre, '[[:space:]]+', ' ')) = ? LIMIT 1",
                            [preg_replace('/\s+/u', ' ', trim($l['nombre']))]
                        ) ?: 0);
                    }
                    // 3) Recién ahí es uno nuevo
                    if (! $idp) {
                        DB::insert(
                            // Antes se copiaba el costo como precio de venta, que era
                            // vender al costo. Va en 0 por lo mismo que en el alta:
                            // el salón no vende productos.
                            "INSERT INTO producto (id_sucursal,id_categoria,nombre,unidad_medida,precio_costo,precio_venta,tasa_iva)
                             VALUES (?,?, 'unidad', ?, 0, 10)",
                            [Sucursales::activa() ?: 1, $l['categoria'], $l['nombre'], $l['precio']]
                        );
                        $idp = (int) DB::getPdo()->lastInsertId();
                        $creados[] = $l['nombre'];
                    }

                    DB::insert('INSERT INTO detalle_compra (id_compra,id_producto,cantidad,precio_unitario) VALUES (?,?,?,?)',
                        [$idCompra, $idp, $l['cantidad'], $l['precio']]);
                    $total += round($l['cantidad'] * $l['precio'], 2);
                }

                // Confirmar genera los movimientos de inventario y actualiza el costo
                Bd::procedimiento('sp_confirmar_compra', [$idCompra, (int) session('uid')]);

                // Las cuotas, si la compra es a crédito. Una fila por cuota:
                // nunca una lista de fechas en un solo campo.
                $cuotas = 0;
                if ($dias > 0) {
                    $fechas = (array) $request->input('cuota_fecha', []);
                    $montos = (array) $request->input('cuota_monto', []);
                    foreach ($fechas as $i => $fv) {
                        $fv = trim((string) $fv);
                        $mo = num($montos[$i] ?? 0);
                        if ($fv === '' || $mo <= 0) {
                            continue;
                        }
                        $cuotas++;
                        DB::insert(
                            'INSERT INTO compra_cuota (id_compra,nro_cuota,fecha_vencimiento,monto) VALUES (?,?,?,?)',
                            [$idCompra, $cuotas, $fv, $mo]
                        );
                    }
                }

                return ['id' => $idCompra, 'creados' => $creados, 'total' => $total,
                        'lineas' => count($lineas), 'cuotas' => $cuotas];
            });

            Auditoria::registrar('COMPRA', 'Inventario', 'compra', $r['id'],
                $r['lineas'] . ' producto(s), ' . count($r['creados']) . ' nuevo(s)'
                . ($r['creados'] ? ' (' . implode(', ', $r['creados']) . ')' : '')
                . ', total ' . money($r['total']));

            flash('Compra registrada por ' . money($r['total']) . ': el stock quedó actualizado.'
                . ($r['cuotas'] ? ' Quedaron ' . $r['cuotas'] . ' cuota(s) con su vencimiento.' : ''));

            // Se nombran los productos creados: si uno salió de un error de
            // tipeo, se ve acá y no dentro de tres meses con el stock partido.
            if ($r['creados']) {
                flash('Se crearon ' . count($r['creados']) . ' producto(s) nuevo(s): «'
                    . implode('», «', $r['creados']) . '». Si alguno ya existía con otro nombre, '
                    . 'unificalos desde Inventario → Productos.', 'warning');
            }
        } catch (Throwable) {
            flash('No se pudo registrar la compra.', 'error');

            return $volver->withInput();
        }

        return redirect()->route('inventario.compras');
    }

    // ---------- Altas rápidas ----------

    /** Producto nuevo con su stock inicial, sin salir de «Cargar stock». */
    public function productoRapido(Request $request): RedirectResponse
    {
        $nombre = trim((string) $request->input('nombre', ''));
        $idCat = (int) $request->input('id_categoria', 0);
        $unidad = trim((string) $request->input('unidad_medida', 'unidad')) ?: 'unidad';
        $stock = num($request->input('stock_inicial'));
        $costo = num($request->input('precio_costo'));
        // El ajuste a medio cargar vuelve con el borrador: crear un producto no
        // puede borrar el motivo y las cantidades que ya estaban escritas.
        $volver = Borrador::conservar(redirect()->route('inventario.ajuste'), $request);

        $error = null;
        if ($nombre === '') {
            $error = 'El nombre del producto es obligatorio.';
        } elseif (! $idCat || ! DB::scalar('SELECT COUNT(*) FROM categoria_producto WHERE id_categoria = ?', [$idCat])) {
            $error = 'Elegí una categoría válida.';
        } elseif (DB::scalar('SELECT COUNT(*) FROM producto WHERE nombre = ?', [$nombre])) {
            $error = 'Ya existe un producto con ese nombre.';
        } elseif ($stock < 0 || $costo < 0) {
            $error = 'Las cantidades y los precios no pueden ser negativos.';
        }
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        try {
            DB::insert(
                // `precio_venta` va en 0: el salón vende servicios, no productos,
                // así que el campo salió de la pantalla. La columna es NOT NULL
                // y sigue en la base por si se revierte la decisión.
                'INSERT INTO producto (id_sucursal,id_categoria,nombre,unidad_medida,stock_minimo,precio_costo,precio_venta,tasa_iva,activo)
                 VALUES (?,?,?,?,0,?,0,10,1)', [Sucursales::activa() ?: 1, $idCat, $nombre, $unidad, $costo]
            );
            $idp = (int) DB::getPdo()->lastInsertId();

            if ($stock > 0) {
                Bd::procedimiento('sp_registrar_movimiento_inventario', [
                    $idp, (int) session('uid'), self::STOCK_INICIAL, $stock, $costo,
                    'ALTA', 'Stock inicial (alta directa, sin compra)',
                ]);
            }

            Auditoria::registrar('ALTA', 'Inventario', 'producto', $idp, $nombre . ' (alta rápida)');
            flash('Producto «' . $nombre . '» creado'
                . ($stock > 0 ? ' con ' . cant($stock) . ' ' . $unidad . ' de stock.' : '.'));

            // Se vuelve al ajuste con el producto ya elegido: llevarlo a Stock lo
            // sacaba de la pantalla y le hacía perder lo que estaba cargando.
            return redirect()->route('inventario.ajuste', ['producto' => $idp]);
        } catch (Throwable) {
            flash('No se pudo crear el producto.', 'error');

            return $volver;
        }
    }

    /** Proveedor nuevo sin salir de «Nueva compra». */
    public function proveedorRapido(Request $request): RedirectResponse
    {
        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'ruc' => trim((string) $request->input('ruc', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
        ];
        $contacto = trim((string) $request->input('contacto', '')) ?: null;
        // Las filas de la compra ya cargadas vuelven con el borrador.
        $volver = Borrador::conservar(redirect()->route('inventario.compra_form'), $request);

        if ($d['nombre'] === '') {
            flash('El nombre o razón social del proveedor es obligatorio.', 'error');

            return $volver->withInput();
        }

        // Si el RUC ya está cargado puede ser la misma empresa registrada de
        // otra forma: se avisa en vez de crear un duplicado.
        $idPersona = Persona::porDocumento(null, $d['ruc']);
        if ($idPersona && DB::scalar('SELECT COUNT(*) FROM proveedor WHERE id_persona = ?', [$idPersona])) {
            $ya = (int) DB::scalar('SELECT id_proveedor FROM proveedor WHERE id_persona = ? LIMIT 1', [$idPersona]);
            flash('Ya existe un proveedor con ese RUC: lo dejamos elegido.', 'warning');

            return redirect()->route('inventario.compra_form', ['proveedor' => $ya]);
        }

        try {
            $idPersona = Persona::guardar($idPersona, $d);
            DB::insert('INSERT INTO proveedor (id_persona, contacto, activo) VALUES (?,?,1)', [$idPersona, $contacto]);
            $idp = (int) DB::getPdo()->lastInsertId();
            Auditoria::registrar('ALTA', 'Inventario', 'proveedor', $idp,
                $d['nombre'] . ' (alta rápida desde Nueva compra)');
            flash('Proveedor «' . $d['nombre'] . '» creado y seleccionado.');

            return redirect()->route('inventario.compra_form', ['proveedor' => $idp]);
        } catch (Throwable) {
            flash('No se pudo crear el proveedor (¿RUC duplicado?).', 'error');

            return $volver->withInput();
        }
    }

    // -----------------------------------------------------------------

    private function categoriasPorNombre(): array
    {
        $out = [];
        foreach (DB::select('SELECT nombre FROM categoria_producto ORDER BY nombre') as $c) {
            $out[$c->nombre] = $c->nombre;
        }

        return $out;
    }

    private function productosPorId(): array
    {
        $out = [];
        foreach (DB::select('SELECT id_producto, nombre FROM producto ORDER BY nombre') as $p) {
            $out[(string) $p->id_producto] = $p->nombre;
        }

        return $out;
    }

    private function tiposMovimiento(): array
    {
        $out = [];
        foreach (DB::select('SELECT id_tipo_movimiento, nombre FROM tipo_movimiento_inventario ORDER BY nombre') as $t) {
            $out[(string) $t->id_tipo_movimiento] = $t->nombre;
        }

        return $out;
    }

    private function estadosCompra(): array
    {
        $out = [];
        foreach (DB::select('SELECT nombre FROM estado_compra ORDER BY id_estado_compra') as $e) {
            $out[$e->nombre] = $e->nombre;
        }

        return $out;
    }
}
