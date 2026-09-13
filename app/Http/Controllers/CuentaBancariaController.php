<?php

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Bd;
use App\Servicios\Cuenta;
use App\Servicios\Movimientos;
use App\Servicios\Pagos;
use App\Servicios\Permisos;
use App\Servicios\Persona;
use App\Servicios\Sucursales;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Tesorería → Cuenta bancaria: las cuentas del salón, tratadas como una CAJA
 * dedicada al banco.
 *
 * Es su propio controlador y no un bloque más de `FacturacionController`, que
 * ya pasa las cuatro mil líneas: la excepción que este proyecto ya hizo con
 * Seguridad. Vivía en `ConfiguracionController` como «Datos de pago» —a dónde
 * le decimos a la clienta que transfiera— y se mudó entera en la 7.121.0,
 * cuando la cuenta pasó a tener saldo, movimientos y arqueo.
 *
 * Lo que decide el salón acá:
 *   · qué cuentas tiene cada local, con sus datos para transferir;
 *   · cuál de ellas se le muestra a la clienta para la seña (`para_senas`);
 *   · cuánto dice el banco que hay (`arqueo_cuenta`), que es el arqueo.
 *
 * Lo que NO decide: la plata no se mueve desde acá. Entra con los cobros por
 * transferencia y sale con los pagos y los movimientos, cada uno desde su
 * pantalla, igual que con el cajón.
 */
class CuentaBancariaController extends Controller
{
    /**
     * Los tipos de cuenta que se pueden elegir.
     *
     * **Va como combo y no como texto libre**: escrito a mano, «Caja de
     * ahorro», «caja de ahorros» y «C. de ahorro» son la misma cosa tres
     * veces, y la clienta ve lo que se haya tipeado.
     */
    public const CUENTA_TIPOS = ['Caja de ahorro', 'Cuenta corriente', 'Billetera', 'Cuenta única'];

    /**
     * Los medios que aceptan datos: cuentas bancarias y billeteras.
     *
     * **Sale de `metodo_pago` y no de una lista escrita acá**, así que esta
     * pantalla y la del cobro hablan del mismo vocabulario. El efectivo y las
     * tarjetas quedan afuera: no hay ninguna cuenta que darle a la clienta.
     */
    private function mediosConDatos(): array
    {
        return DB::select(
            "SELECT id_metodo_pago, nombre, tipo FROM metodo_pago
              WHERE activo = 1 AND tipo IN ('BANCO', 'OTRO')
              ORDER BY id_metodo_pago"
        );
    }

    /** Los locales a los que esta persona entra, como ids. */
    private function suyas(): array
    {
        return array_map(fn ($s) => (int) $s->id_sucursal, Sucursales::delUsuario());
    }

    /**
     * Una tarjeta por cuenta, como Cajas: cuánto hay, desde cuándo se sabe,
     * qué pasó hoy, y los botones.
     */
    public function index(Request $request): View
    {
        $mias = Sucursales::delUsuario();
        $suc = (int) $request->input('sucursal', Sucursales::activa() ?: 0);

        // Nadie pide las cuentas de un local al que no entra: es la misma
        // regla con la que se decide qué agenda ve.
        $ids = $this->suyas();
        if (! in_array($suc, $ids, true)) {
            $suc = $ids[0] ?? 0;
        }

        $cuentas = $suc ? Cuenta::deSucursal($suc, false) : [];

        // **Los movimientos de HOY de cada cuenta**, para el modal de la
        // tarjeta: es la misma pregunta del mostrador que contesta Cajas, y
        // sale de las mismas cuatro fuentes que Movimientos.
        $movs = [];
        if (Permisos::puede('facturacion.movimientos')) {
            foreach ($cuentas as $c) {
                if ((int) $c->activo) {
                    $movs[(int) $c->id_cuenta] = Movimientos::delDia(null, (int) $c->id_cuenta);
                }
            }
        }

        return view('facturacion.cuentas', [
            'sucursales' => $mias,
            'sucursal' => $suc,
            'cuentas' => $cuentas,
            'movs' => $movs,
            'medios' => $this->mediosConDatos(),
            'tiposAlias' => Pagos::ALIAS_TIPOS,
            'ejemplosAlias' => Pagos::ALIAS_EJEMPLOS,
            'filtroAlias' => Pagos::ALIAS_FILTROS,
            'tiposCuenta' => self::CUENTA_TIPOS,
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_cuenta');
        $suc = (int) $request->input('id_sucursal');
        $medio = (int) $request->input('id_metodo_pago');
        $entidad = trim((string) $request->input('entidad', ''));
        $titular = trim((string) $request->input('titular', ''));
        $doc = trim((string) $request->input('documento', ''));
        $tipoCta = trim((string) $request->input('tipo_cuenta', ''));
        $alias = trim((string) $request->input('alias', ''));
        $aliasTipo = trim((string) $request->input('alias_tipo', ''));
        $nro = trim((string) $request->input('numero_cuenta', ''));
        $obs = trim((string) $request->input('observacion', ''));
        // **El orden no se tipea.** Un campo numérico para ordenar dos o tres
        // filas hace pensar de más; se reordena con flechas en la lista.
        $orden = $id
            ? (int) DB::scalar('SELECT orden FROM cuenta_bancaria WHERE id_cuenta = ?', [$id])
            : (int) DB::scalar('SELECT COALESCE(MAX(orden), 0) + 1 FROM cuenta_bancaria WHERE id_sucursal = ?', [$suc]);

        $mediosConDatos = $this->mediosConDatos();
        $medioTipos = [];
        foreach ($mediosConDatos as $m) {
            $medioTipos[(int) $m->id_metodo_pago] = (string) $m->tipo;
        }
        $medios = array_keys($medioTipos);

        $error = match (true) {
            ! in_array($suc, $this->suyas(), true) => 'Elegí una sucursal a la que tengas acceso.',
            ! in_array($medio, $medios, true) => 'Elegí cómo se paga.',
            ! in_array($medioTipos[$medio] ?? '', ['BANCO', 'OTRO'], true) => 'Ese tipo de pago no admite datos de cuenta.',
            mb_strlen($entidad) < 2 => ($medioTipos[$medio] ?? '') === 'BANCO'
                ? 'Escribí el banco.' : 'Escribí la billetera o proveedor.',
            mb_strlen($titular) < 3 => 'Escribí a nombre de quién está la cuenta.',
            // El número es lo que la clienta va a copiar: sin él, el dato no
            // sirve para nada. Se pide siempre, aunque la columna admita NULL
            // para las filas que vengan de otro lado.
            $nro === '' => 'Escribí el número de cuenta (o el celular, si es billetera).',

            // **El documento del titular puede ser cédula O RUC**, y no sabemos
            // cuál escribió: se acepta si pasa por cualquiera de las dos. El
            // RUC lleva verificador y la cédula no, así que validar contra una
            // sola rechazaría la mitad de los casos legítimos.
            $doc !== ''
                && Persona::error(['cedula' => $doc]) !== null
                && Persona::error(['ruc' => $doc]) !== null
                    => 'El documento del titular no tiene un formato válido (cédula o RUC).',

            // **El alias y su tipo van juntos o no van.** Un alias sin tipo no
            // se le puede explicar a la clienta —«buscá por qué cosa»— y un
            // tipo sin alias no es nada.
            $alias !== '' && ! isset(Pagos::ALIAS_TIPOS[$aliasTipo])
                => 'Elegí de qué tipo es el alias: cédula, RUC, celular o correo.',
            $aliasTipo !== '' && $alias === ''
                => 'Escribí el alias, o dejá el tipo en «sin alias».',
            $tipoCta !== '' && ! in_array($tipoCta, self::CUENTA_TIPOS, true)
                => 'Elegí un tipo de cuenta de la lista.',

            // **Y se valida contra su tipo**, que es lo que el tipo hace útil:
            // un alias de correo mal escrito no lo encuentra nadie.
            default => $this->errorAlias($aliasTipo, $alias),
        };

        if ($error) {
            return back()->with('flash', ['msg' => $error, 'tipo' => 'error'])->withInput();
        }

        $campos = [$suc, $medio, $entidad, $titular, $doc ?: null,
            $tipoCta ?: null, $nro, $alias ?: null, $alias === '' ? null : $aliasTipo,
            $obs ?: null, max(0, min(255, $orden))];

        try {
            if ($id) {
                DB::update(
                    'UPDATE cuenta_bancaria
                        SET id_sucursal = ?, id_metodo_pago = ?, entidad = ?, titular = ?,
                            documento = ?, tipo_cuenta = ?, numero_cuenta = ?, alias = ?,
                            alias_tipo = ?, observacion = ?, orden = ?
                      WHERE id_cuenta = ?',
                    array_merge($campos, [$id])
                );
            } else {
                // **La primera cuenta del local queda marcada para las señas.**
                // Sin ninguna marcada, la clienta que reserva con seña no ve a
                // dónde transferir; con una sola cargada, la respuesta es ésa.
                $primera = (int) DB::scalar(
                    'SELECT COUNT(*) FROM cuenta_bancaria WHERE id_sucursal = ? AND activo = 1', [$suc]) === 0;
                DB::insert(
                    'INSERT INTO cuenta_bancaria
                        (id_sucursal, id_metodo_pago, entidad, titular, documento,
                         tipo_cuenta, numero_cuenta, alias, alias_tipo, observacion, orden, para_senas)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($campos, [$primera ? 1 : 0])
                );
                $id = (int) DB::scalar('SELECT LAST_INSERT_ID()');
            }
        } catch (QueryException $e) {
            return back()->with('flash', ['msg' => Bd::traducir($e, [
                'uq_cuenta_nro' => 'Esa cuenta ya está cargada en esta sucursal.',
            ], 'No se pudo guardar la cuenta.'), 'tipo' => 'error'])->withInput();
        }

        Auditoria::registrar($request->filled('id_cuenta') ? 'EDICION' : 'ALTA',
            'Facturacion', 'cuenta_bancaria', $id, $entidad . ' — ' . $titular);

        flash('Cuenta guardada.');

        return redirect()->route('facturacion.cuentas', ['sucursal' => $suc]);
    }

    /**
     * ¿El alias tiene la forma de su tipo? Devuelve el problema, o null.
     *
     * Se reusa `Persona::error()` para cédula, RUC y teléfono: son las mismas
     * reglas que el resto del sistema, y tenerlas dos veces las desincroniza.
     */
    private function errorAlias(string $tipo, string $alias): ?string
    {
        if ($alias === '') {
            return null;
        }

        return match ($tipo) {
            'CI' => Persona::error(['cedula' => $alias])
                ? 'El alias de tipo cédula sólo puede tener números.' : null,
            'RUC' => Persona::error(['ruc' => $alias])
                ? 'El alias de tipo RUC no tiene un formato válido (ej: 80012345-6).' : null,
            'CELULAR' => Persona::error(['telefono' => $alias])
                ? 'El alias de tipo celular tiene que ser un número de teléfono.' : null,
            'EMAIL' => filter_var($alias, FILTER_VALIDATE_EMAIL) === false
                ? 'El alias de tipo correo no tiene un formato válido.' : null,
            default => null,
        };
    }

    /** La cuenta del POST, si es de un local al que esta persona entra. */
    private function mia(Request $request): ?object
    {
        $id = (int) $request->input('id_cuenta');
        $d = DB::selectOne('SELECT * FROM cuenta_bancaria WHERE id_cuenta = ?', [$id]);
        if (! $d || ! in_array((int) $d->id_sucursal, $this->suyas(), true)) {
            flash('No encontramos esa cuenta.', 'error');

            return null;
        }

        return $d;
    }

    /**
     * Sube o baja una cuenta en la lista que ve la clienta.
     *
     * **Se intercambia el orden con la vecina**, no se recalcula todo: así una
     * sola fila se mueve y el resto queda donde estaba.
     */
    public function orden(Request $request): RedirectResponse
    {
        $d = $this->mia($request);
        if (! $d) {
            return back();
        }
        $id = (int) $d->id_cuenta;
        $arriba = $request->input('dir') === 'arriba';

        $vecina = DB::selectOne(
            'SELECT id_cuenta, orden FROM cuenta_bancaria
              WHERE id_sucursal = ? AND (orden ' . ($arriba ? '<' : '>') . ' ? OR (orden = ? AND id_cuenta '
              . ($arriba ? '<' : '>') . ' ?))
              ORDER BY orden ' . ($arriba ? 'DESC' : 'ASC') . ', id_cuenta '
              . ($arriba ? 'DESC' : 'ASC') . ' LIMIT 1',
            [$d->id_sucursal, $d->orden, $d->orden, $id]
        );

        // Ya está en la punta: no es un error, no hay nada que hacer.
        if ($vecina) {
            Bd::enTransaccion(function () use ($d, $vecina, $id) {
                DB::update('UPDATE cuenta_bancaria SET orden = ? WHERE id_cuenta = ?',
                    [$vecina->orden, $id]);
                DB::update('UPDATE cuenta_bancaria SET orden = ? WHERE id_cuenta = ?',
                    [$d->orden, $vecina->id_cuenta]);
            });
        }

        return redirect()->route('facturacion.cuentas', ['sucursal' => $d->id_sucursal]);
    }

    public function estado(Request $request): RedirectResponse
    {
        $d = $this->mia($request);
        if (! $d) {
            return back();
        }
        $id = (int) $d->id_cuenta;

        // **Se desactiva, no se borra.** Una cuenta que se dejó de usar sigue
        // siendo la que aparece en los comprobantes de las señas viejas, y la
        // que nombran los cobros y pagos que pasaron por ella: si desaparece,
        // no hay forma de saber a dónde fue la plata. Y al desactivarla deja
        // de ser la de las señas, que si no la clienta transferiría a una
        // cuenta que el salón dio de baja.
        // El orden de las dos asignaciones importa: MariaDB las evalúa de
        // izquierda a derecha y la segunda ya ve el valor nuevo, así que la
        // marca se decide ANTES de dar vuelta `activo`.
        DB::update('UPDATE cuenta_bancaria
                       SET para_senas = IF(activo = 1, 0, para_senas), activo = 1 - activo
                     WHERE id_cuenta = ?', [$id]);
        Auditoria::registrar($d->activo ? 'BAJA' : 'ALTA', 'Facturacion',
            'cuenta_bancaria', $id, $d->entidad . ' — ' . $d->titular);

        flash($d->activo
            ? 'La cuenta queda dada de baja: no se ofrece para cobrar ni pagar, y su historial queda.'
            : 'La cuenta vuelve a estar activa.');

        return redirect()->route('facturacion.cuentas', ['sucursal' => $d->id_sucursal]);
    }

    /**
     * «Usar para señas»: cuál es la cuenta a la que la clienta transfiere.
     *
     * Es un interruptor por cuenta y **puede haber más de una marcada** —dos
     * bancos, o el banco y la billetera—: la clienta ve todas las marcadas de
     * su local y elige por dónde le queda cómodo. Hasta la 7.121.0 se le
     * mostraban TODAS las activas, y una cuenta puede existir para pagarle a
     * proveedores sin ser a la que el salón quiere que le transfieran.
     */
    public function senas(Request $request): RedirectResponse
    {
        $d = $this->mia($request);
        if (! $d) {
            return back();
        }

        if (! (int) $d->activo && ! (int) $d->para_senas) {
            flash('Esa cuenta está dada de baja: activala antes de ofrecérsela a la clienta.', 'error');

            return redirect()->route('facturacion.cuentas', ['sucursal' => $d->id_sucursal]);
        }

        DB::update('UPDATE cuenta_bancaria SET para_senas = 1 - para_senas WHERE id_cuenta = ?',
            [(int) $d->id_cuenta]);
        Auditoria::registrar('EDICION', 'Facturacion', 'cuenta_bancaria', (int) $d->id_cuenta,
            ((int) $d->para_senas ? 'Deja de ofrecerse' : 'Se ofrece') . ' para las señas: ' . $d->entidad);

        flash((int) $d->para_senas
            ? 'La clienta deja de ver esta cuenta al registrar su seña.'
            : 'La clienta va a ver esta cuenta al registrar su seña.');

        return redirect()->route('facturacion.cuentas', ['sucursal' => $d->id_sucursal]);
    }

    /**
     * El arqueo de la cuenta: cuánta plata dice el banco que hay.
     *
     * **Cada arqueo queda** (7.122.0). Hasta la 7.121.1 esto pisaba el saldo
     * declarado anterior, así que Arqueos no tenía nada que listar y no había
     * forma de decir si la cuenta cuadró: ahora es una fila de
     * `arqueo_cuenta`, igual que el cierre de una caja es una fila de `caja`.
     * La regla —el motivo cuando no cuadra— vive en `Cuenta::arquear()`, que
     * es lo que usan las dos pantallas que lo ofrecen.
     *
     * **Vaciar el campo ya no deja la cuenta «sin declarar».** Con historial
     * eso sería borrar arqueos que ya pasaron; un arqueo nuevo es la forma de
     * corregir uno mal cargado.
     *
     * Vuelve a donde se hizo: la tarjeta de la cuenta o la lista de Arqueos.
     */
    public function arqueo(Request $request): RedirectResponse
    {
        $d = $this->mia($request);
        if (! $d) {
            return back();
        }
        $id = (int) $d->id_cuenta;
        $volver = $request->input('volver') === 'arqueos'
            ? redirect()->route('facturacion.arqueo', ['de' => 'cuentas'])
            : redirect()->route('facturacion.cuentas', ['sucursal' => $d->id_sucursal]);

        if (! (int) $d->activo) {
            flash('Esa cuenta está dada de baja: no se arquea.', 'error');

            return $volver;
        }
        if (trim((string) $request->input('saldo', '')) === '') {
            flash('Escribí cuánto dice el banco que hay en «' . $d->entidad . '».', 'error');

            return $volver;
        }

        $antes = Cuenta::saldo($id);
        $saldo = num($request->input('saldo'));
        $error = Cuenta::arquear($id, $saldo, (string) $request->input('motivo_diferencia', ''),
            (string) $request->input('observacion', ''), (int) session('uid'));
        if ($error) {
            flash($error, 'error');

            return $volver;
        }

        Auditoria::registrar('ARQUEO', 'Facturacion', 'cuenta_bancaria', $id,
            'Arqueo de ' . $d->entidad . ': el banco dice ' . money($saldo)
            . ($antes === null ? ' (primer arqueo)' : ', el sistema esperaba ' . money($antes)));

        if ($antes === null) {
            flash('Arqueo registrado: ' . money($saldo) . '. Desde acá se suman los cobros y se descuentan los pagos.');
        } elseif (abs($saldo - $antes) < 0.01) {
            flash('La cuenta cuadra: el banco dice ' . money($saldo) . ', lo mismo que el sistema.');
        } else {
            flash('Arqueo registrado con diferencia: el banco dice ' . money($saldo) . ' y el sistema esperaba '
                . money($antes) . '. Queda anotada en Arqueos.', 'warning');
        }

        return $volver;
    }
}
