<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Bd;
use App\Servicios\Persona;
use App\Servicios\Sucursales;
use Illuminate\Database\QueryException;
use App\Servicios\Auditoria;
use App\Servicios\Config;
use App\Servicios\Contacto;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * La mitad del módulo Seguridad que trata del sistema: sucursales, roles,
 * contacto y auditoría. La otra mitad —usuarios, turnos, comisiones y
 * asistencia— está en `PersonalController`, y la portada del módulo en
 * `SeguridadController`.
 */
class ConfiguracionController extends Controller
{
    // ---------- Sucursales ----------

    public function sucursales(): View
    {
        return view('seguridad.sucursales', [
            // El nombre y el logo del sistema. Van en esta pantalla por pedido del
            // usuario, y NO son de cada local: uno solo para todo el sistema.
            'nombreSalon' => Config::nombreSalon(),
            'logo' => Config::logo(),
            'actividad' => Config::actividad(),
            'emailFiscal' => Config::email(),
            'rows' => DB::select(
                'SELECT s.*, (SELECT COUNT(*) FROM usuario u WHERE u.id_sucursal = s.id_sucursal) AS personal
                   FROM sucursal s ORDER BY s.nombre'
            ),
        ]);
    }

    public function sucursalForm(int $id = 0): View|RedirectResponse
    {
        $s = $id ? DB::selectOne('SELECT * FROM sucursal WHERE id_sucursal = ?', [$id]) : null;
        if ($id && ! $s) {
            flash('Esa sucursal no existe.', 'error');

            return redirect()->route('seguridad.sucursales');
        }

        return view('seguridad.sucursal_form', ['s' => $s]);
    }

    public function sucursalGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_sucursal', 0);
        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'ruc' => trim((string) $request->input('ruc', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
            'direccion' => trim((string) $request->input('direccion', '')) ?: null,
            'ciudad' => ciudad_elegida($request->input('ciudad'), $request->input('ciudad_otra')),
        ];
        $volver = $id ? redirect()->route('seguridad.sucursal_form', $id) : redirect()->route('seguridad.sucursal_form');

        if ($d['nombre'] === '') {
            flash('El nombre de la sucursal es obligatorio.', 'error');

            return $volver->withInput();
        }
        if (DB::scalar('SELECT COUNT(*) FROM sucursal WHERE nombre = ? AND id_sucursal <> ?', [$d['nombre'], $id])) {
            flash('Ya existe una sucursal con ese nombre.', 'error');

            return $volver->withInput();
        }

        try {
            if ($id) {
                DB::update(
                    'UPDATE sucursal SET nombre=:nombre, ruc=:ruc, telefono=:telefono,
                        direccion=:direccion, ciudad=:ciudad WHERE id_sucursal=:id', $d + ['id' => $id]
                );
                Auditoria::registrar('MODIFICACION', 'Configuracion', 'sucursal', $id, $d['nombre']);
                flash('Sucursal actualizada.');
            } else {
                DB::insert(
                    'INSERT INTO sucursal (nombre,ruc,telefono,direccion,ciudad,activo)
                     VALUES (:nombre,:ruc,:telefono,:direccion,:ciudad,1)', $d
                );
                $nueva = (int) DB::getPdo()->lastInsertId();
                Auditoria::registrar('ALTA', 'Configuracion', 'sucursal', $nueva, $d['nombre']);

                // **Un local nuevo abre con el catálogo entero.** Es lo que
                // espera quien inaugura la segunda sucursal: ofrece lo mismo, y
                // después saca lo que ahí no hace.
                //
                // La convención «sin filas vale en todas» no alcanza sola: en
                // cuanto un servicio tiene UNA fila —porque alguien abrió su
                // formulario y guardó— deja de valer en todas, y la sucursal
                // nueva nacería sin ese servicio. Eso es lo que dejaba a la
                // clienta sin nada que reservar al elegir el local nuevo.
                // Se copia sólo lo que ya está publicado en algún lado; lo que
                // no tiene ninguna fila sigue valiendo en todas por su cuenta.
                $publicados = DB::insert(
                    'INSERT IGNORE INTO servicio_sucursal (id_servicio, id_sucursal)
                     SELECT DISTINCT ss.id_servicio, ? FROM servicio_sucursal ss
                       JOIN servicio s ON s.id_servicio = ss.id_servicio AND s.activo = 1',
                    [$nueva]
                );

                flash('Sucursal creada' . ($publicados
                    ? ', con el catálogo de servicios publicado. Sacale desde Servicios los que ahí no se hagan.'
                    : '. Ya ofrece todos los servicios del salón.'));
            }
        } catch (Throwable) {
            flash('No se pudo guardar la sucursal.', 'error');

            return $volver->withInput();
        }

        return redirect()->route('seguridad.sucursales');
    }

    public function sucursalBaja(Request $request): RedirectResponse
    {
        DB::update('UPDATE sucursal SET activo = 1 - activo WHERE id_sucursal = ?',
            [(int) $request->input('id_sucursal', 0)]);
        flash('Estado de la sucursal actualizado.');

        return redirect()->route('seguridad.sucursales');
    }

    // ---------- Contacto y soporte ----------

    // =================================================================
    //  Datos de pago: a dónde le transfiere la clienta
    // =================================================================

    /**
     * Los cuatro tipos de alias del SIPAP.
     *
     * **En Paraguay el alias es el ÚNICO dato necesario para transferir**:
     * reemplaza al número de cuenta, a la entidad y al nombre del
     * destinatario. Y no es una palabra inventada — el BCP habilita cuatro,
     * y son todos identificadores que la persona ya tiene.
     *
     * Guardar el tipo permite validarlo y sobre todo DECIRLE a la clienta por
     * dónde buscarlo, que es como funciona la pantalla de su banco.
     */
    /**
     * Los tipos de cuenta que se pueden elegir.
     *
     * **Va como combo y no como texto libre**: escrito a mano, «Caja de
     * ahorro», «caja de ahorros» y «C. de ahorro» son la misma cosa tres
     * veces, y la clienta ve lo que se haya tipeado.
     */
    private const CUENTA_TIPOS = ['Caja de ahorro', 'Cuenta corriente', 'Billetera', 'Cuenta única'];

    private const ALIAS_TIPOS = [
        'CI' => 'Cédula',
        'RUC' => 'RUC',
        'CELULAR' => 'Celular',
        'EMAIL' => 'Correo',
    ];

    /**
     * Cómo se ve cada tipo de alias, para que la pantalla lo muestre de ejemplo.
     *
     * Un placeholder que cambia con el tipo es lo que evita el error de tipeo
     * antes de que ocurra: quien ve «80012345-6» no escribe el RUC sin guion.
     */
    private const ALIAS_EJEMPLOS = [
        'CI' => '4200000',
        'RUC' => '80012345-6',
        'CELULAR' => '0981123456',
        'EMAIL' => 'salon@correo.com',
    ];

    /**
     * Qué caracteres deja escribir cada tipo (`data-solo` de `app.js`).
     *
     * **La pantalla no puede ser más estricta que el servidor**, así que cada
     * juego copia la regla de `Persona::error()`. El correo queda libre: no
     * hay juego de caracteres que lo describa sin dejar afuera uno válido.
     */
    private const ALIAS_FILTROS = [
        'CI' => 'numeros',
        'RUC' => 'ruc',
        'CELULAR' => 'telefono',
        'EMAIL' => '',
    ];

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

    public function pagos(Request $request): View
    {
        $mias = Sucursales::delUsuario();
        $suc = (int) $request->input('sucursal', Sucursales::activa() ?: 0);

        // Nadie pide los datos de un local al que no entra: es la misma regla
        // con la que se decide qué agenda ve.
        $ids = array_map(fn ($s) => (int) $s->id_sucursal, $mias);
        if (! in_array($suc, $ids, true)) {
            $suc = $ids[0] ?? 0;
        }

        return view('seguridad.pagos', [
            'sucursales' => $mias,
            'sucursal' => $suc,
            'medios' => $this->mediosConDatos(),
            'tiposAlias' => self::ALIAS_TIPOS,
            'ejemplosAlias' => self::ALIAS_EJEMPLOS,
            'filtroAlias' => self::ALIAS_FILTROS,
            'tiposCuenta' => self::CUENTA_TIPOS,
            'datos' => $suc ? DB::select(
                'SELECT d.*, m.nombre AS medio
                   FROM dato_pago_sucursal d
                   JOIN metodo_pago m ON m.id_metodo_pago = d.id_metodo_pago
                  WHERE d.id_sucursal = ?
                  ORDER BY d.activo DESC, d.orden, d.id_dato_pago', [$suc]
            ) : [],
            'editar' => $request->filled('editar') ? DB::selectOne(
                'SELECT * FROM dato_pago_sucursal WHERE id_dato_pago = ? AND id_sucursal = ?',
                [(int) $request->input('editar'), $suc]
            ) : null,
        ]);
    }

    public function pagosGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_dato_pago');
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
        // **El orden ya no se tipea.** Un campo numérico para ordenar dos o
        // tres filas hace pensar de más; se reordena con flechas en la lista,
        // que es lo mismo sin tener que elegir un número.
        $orden = $id
            ? (int) DB::scalar('SELECT orden FROM dato_pago_sucursal WHERE id_dato_pago = ?', [$id])
            : (int) DB::scalar('SELECT COALESCE(MAX(orden), 0) + 1 FROM dato_pago_sucursal WHERE id_sucursal = ?', [$suc]);

        $mediosConDatos = $this->mediosConDatos();
        $medioTipos = [];
        foreach ($mediosConDatos as $m) {
            $medioTipos[(int) $m->id_metodo_pago] = (string) $m->tipo;
        }
        $medios = array_keys($medioTipos);
        $suyas = array_map(fn ($s) => (int) $s->id_sucursal, Sucursales::delUsuario());

        $error = match (true) {
            ! in_array($suc, $suyas, true) => 'Elegí una sucursal a la que tengas acceso.',
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
            $alias !== '' && ! isset(self::ALIAS_TIPOS[$aliasTipo])
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
                    'UPDATE dato_pago_sucursal
                        SET id_sucursal = ?, id_metodo_pago = ?, entidad = ?, titular = ?,
                            documento = ?, tipo_cuenta = ?, numero_cuenta = ?, alias = ?,
                            alias_tipo = ?, observacion = ?, orden = ?
                      WHERE id_dato_pago = ?',
                    array_merge($campos, [$id])
                );
            } else {
                DB::insert(
                    'INSERT INTO dato_pago_sucursal
                        (id_sucursal, id_metodo_pago, entidad, titular, documento,
                         tipo_cuenta, numero_cuenta, alias, alias_tipo, observacion, orden)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $campos
                );
                $id = (int) DB::scalar('SELECT LAST_INSERT_ID()');
            }
        } catch (QueryException $e) {
            return back()->with('flash', ['msg' => Bd::traducir($e, [
                'uq_dpago_cuenta' => 'Esa cuenta ya está cargada en esta sucursal.',
            ], 'No se pudo guardar la cuenta.'), 'tipo' => 'error'])->withInput();
        }

        Auditoria::registrar($request->filled('id_dato_pago') ? 'EDICION' : 'ALTA',
            'Configuración', 'dato_pago_sucursal', $id, $entidad . ' — ' . $titular);

        flash('Datos de pago guardados.');

        return redirect()->route('seguridad.pagos', ['sucursal' => $suc]);
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

    /**
     * Sube o baja una cuenta en la lista que ve la clienta.
     *
     * **Se intercambia el orden con la vecina**, no se recalcula todo: así una
     * sola fila se mueve y el resto queda donde estaba.
     */
    public function pagosOrden(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_dato_pago');
        $arriba = $request->input('dir') === 'arriba';
        $suyas = array_map(fn ($su) => (int) $su->id_sucursal, Sucursales::delUsuario());

        $d = DB::selectOne('SELECT * FROM dato_pago_sucursal WHERE id_dato_pago = ?', [$id]);
        if (! $d || ! in_array((int) $d->id_sucursal, $suyas, true)) {
            flash('No encontramos esa cuenta.', 'error');

            return back();
        }

        $vecina = DB::selectOne(
            'SELECT id_dato_pago, orden FROM dato_pago_sucursal
              WHERE id_sucursal = ? AND (orden ' . ($arriba ? '<' : '>') . ' ? OR (orden = ? AND id_dato_pago '
              . ($arriba ? '<' : '>') . ' ?))
              ORDER BY orden ' . ($arriba ? 'DESC' : 'ASC') . ', id_dato_pago '
              . ($arriba ? 'DESC' : 'ASC') . ' LIMIT 1',
            [$d->id_sucursal, $d->orden, $d->orden, $id]
        );

        // Ya está en la punta: no es un error, no hay nada que hacer.
        if ($vecina) {
            Bd::enTransaccion(function () use ($d, $vecina, $id) {
                DB::update('UPDATE dato_pago_sucursal SET orden = ? WHERE id_dato_pago = ?',
                    [$vecina->orden, $id]);
                DB::update('UPDATE dato_pago_sucursal SET orden = ? WHERE id_dato_pago = ?',
                    [$d->orden, $vecina->id_dato_pago]);
            });
        }

        return redirect()->route('seguridad.pagos', ['sucursal' => $d->id_sucursal]);
    }

    public function pagosEstado(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_dato_pago');
        $suyas = array_map(fn ($s) => (int) $s->id_sucursal, Sucursales::delUsuario());

        $d = DB::selectOne('SELECT * FROM dato_pago_sucursal WHERE id_dato_pago = ?', [$id]);
        if (! $d || ! in_array((int) $d->id_sucursal, $suyas, true)) {
            flash('No encontramos esa cuenta.', 'error');

            return back();
        }

        // **Se desactiva, no se borra.** Una cuenta que se dejó de usar sigue
        // siendo la que aparece en los comprobantes de las señas viejas: si
        // desaparece, no hay forma de saber a dónde se transfirió.
        DB::update('UPDATE dato_pago_sucursal SET activo = 1 - activo WHERE id_dato_pago = ?', [$id]);
        Auditoria::registrar($d->activo ? 'BAJA' : 'ALTA', 'Configuración',
            'dato_pago_sucursal', $id, $d->entidad . ' — ' . $d->titular);

        flash($d->activo
            ? 'La cuenta ya no se le muestra a la clienta.'
            : 'La cuenta vuelve a mostrarse.');

        return redirect()->route('seguridad.pagos', ['sucursal' => $d->id_sucursal]);
    }

    public function contacto(): View
    {
        $contactos = [];
        try {
            $contactos = DB::select('SELECT canal, valor, etiqueta FROM contacto_soporte ORDER BY orden, id_contacto');
        } catch (Throwable) {
            // la tabla se crea en la migración del sistema anterior
        }

        return view('seguridad.contacto', [
            'contactos' => $contactos,
            'canales' => Contacto::canales(),
        ]);
    }

    /**
     * El nombre y el logo con los que se presenta el sistema.
     *
     * **Es UNO para todo el sistema, no uno por sucursal**, aunque viva en la
     * pantalla de Sucursales por pedido del usuario. Es el mismo criterio que
     * el Centro de Ayuda y Soporte: la clienta entra por un único portal y ve
     * una sola marca, y quien atiende ve la misma trabaje donde trabaje.
     *
     * **Se ven en el ingreso y en la barra de arriba, o sea antes y después de
     * entrar**, así que un archivo roto acá rompe la pantalla desde la que se
     * arregla. Por eso las tres defensas: se comprueba que sea una imagen de
     * verdad (`getimagesize`, no la extensión que diga el nombre), se limita el
     * tamaño, y **el archivo se escribe antes de tocar la base** — si falla la
     * escritura, la configuración queda como estaba.
     *
     * SVG no entra a propósito: se sirve como marcado y puede traer scripts
     * adentro, y este archivo se dibuja en **todas** las pantallas.
     */
    public function identidadGuardar(Request $request): RedirectResponse
    {
        $volver = redirect()->route('seguridad.sucursales');
        $nombre = trim((string) $request->input('nombre_salon', ''));

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            flash('El nombre del salón tiene que tener entre 2 y 60 caracteres.', 'error');

            return $volver;
        }

        // Los datos fiscales son opcionales: un salón que todavía no
        // factura electrónicamente no tiene por qué cargarlos para poder
        // cambiarle el nombre al local.
        $actCod = trim((string) $request->input('actividad_cod', ''));
        $actDesc = trim((string) $request->input('actividad_desc', ''));
        $emailFiscal = trim((string) $request->input('email_fiscal', ''));

        if ($actCod !== '' && ! preg_match('/^[0-9]{1,10}$/', $actCod)) {
            flash('El código de actividad es numérico: son los dígitos que figuran en el RUC.', 'error');

            return $volver;
        }
        if ($emailFiscal !== '' && ! filter_var($emailFiscal, FILTER_VALIDATE_EMAIL)) {
            flash('Ese correo no tiene forma de correo.', 'error');

            return $volver;
        }

        $archivo = $request->file('logo');
        $guardar = null;

        if ($archivo !== null) {
            if (! $archivo->isValid()) {
                flash('El logo no llegó completo. Probá de nuevo.', 'error');

                return $volver;
            }
            if ($archivo->getSize() > 512 * 1024) {
                flash('El logo no puede pesar más de 512 KB. Achicalo y volvé a subirlo.', 'error');

                return $volver;
            }
            // La extensión la elige quien sube el archivo; esto mira el contenido.
            $info = @getimagesize($archivo->getRealPath());
            $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
            if (! $info || ! isset($tipos[$info[2]])) {
                flash('El logo tiene que ser una imagen PNG, JPG o WEBP.', 'error');

                return $volver;
            }
            // El nombre lleva la fecha para que el navegador no se quede con el
            // logo viejo en caché, y para no pisar el anterior mientras se mira.
            $guardar = 'logo-' . date('YmdHis') . '.' . $tipos[$info[2]];
            try {
                $archivo->move(public_path('assets/logo'), $guardar);
            } catch (Throwable $e) {
                Log::error('No se pudo guardar el logo: ' . $e->getMessage());
                flash('No se pudo guardar el logo. El detalle quedó registrado.', 'error');

                return $volver;
            }
        }

        try {
            $guardar === null
                ? DB::update('UPDATE configuracion SET nombre_salon = ?, actividad_cod = ?,
                                    actividad_desc = ?, email = ? WHERE id_configuracion = 1',
                    [$nombre, $actCod ?: null, $actDesc ?: null, $emailFiscal ?: null])
                : DB::update('UPDATE configuracion SET nombre_salon = ?, logo = ?, actividad_cod = ?,
                                    actividad_desc = ?, email = ? WHERE id_configuracion = 1',
                    [$nombre, $guardar, $actCod ?: null, $actDesc ?: null, $emailFiscal ?: null]);
        } catch (Throwable $e) {
            Log::error('No se pudo guardar la identidad del salón: ' . $e->getMessage());
            flash('No se pudo guardar. El detalle quedó registrado.', 'error');

            return $volver;
        }

        Config::olvidar();
        Auditoria::registrar('MODIFICACION', 'Configuracion', 'configuracion', 1,
            'Identidad del salón: «' . $nombre . '»' . ($guardar ? ' + logo nuevo' : ''));

        flash('Listo. El nombre' . ($guardar ? ' y el logo' : '') . ' se ven en todas las pantallas, '
            . 'para todo el equipo y para las clientas.');

        return $volver;
    }

    /** Saca el logo y vuelve a la tijera de siempre. */
    public function identidadLogoQuitar(): RedirectResponse
    {
        DB::update('UPDATE configuracion SET logo = NULL WHERE id_configuracion = 1');
        Config::olvidar();
        Auditoria::registrar('MODIFICACION', 'Configuracion', 'configuracion', 1, 'Se quitó el logo del salón');
        flash('Logo quitado. Vuelve el ícono por defecto.');

        return redirect()->route('seguridad.sucursales');
    }

    public function contactoGuardar(Request $request): RedirectResponse
    {
        $canales = (array) $request->input('canal', []);
        $valores = (array) $request->input('valor', []);
        $etiquetas = (array) $request->input('etiqueta', []);
        $definidos = Contacto::canales();
        $volver = redirect()->route('seguridad.contacto');

        // Se valida ANTES de tocar la base: si el valor no forma un enlace
        // usable, se avisa en vez de guardar algo que no lleva a ninguna parte.
        $contactos = [];
        foreach ($canales as $i => $canal) {
            $canal = (string) $canal;
            $valor = trim((string) ($valores[$i] ?? ''));
            if ($valor === '') {
                continue;
            }
            if (! isset($definidos[$canal])) {
                flash('Hay una fila con un medio de contacto que no existe.', 'error');

                return $volver;
            }
            if (mb_strlen($valor) > 160) {
                flash('El contacto de ' . $definidos[$canal]['etiqueta'] . ' es demasiado largo.', 'error');

                return $volver;
            }
            if (! Contacto::url($canal, $valor)) {
                flash('No se entiende el contacto de ' . $definidos[$canal]['etiqueta'] . '. '
                    . $definidos[$canal]['ayuda'], 'error');

                return $volver;
            }
            $contactos[] = [
                'canal' => $canal, 'valor' => $valor,
                'etiqueta' => mb_substr(trim((string) ($etiquetas[$i] ?? '')), 0, 40) ?: null,
            ];
        }

        if (count($contactos) > 12) {
            flash('Son demasiados medios de contacto: dejá los que la gente use de verdad.', 'error');

            return $volver;
        }

        try {
            DB::transaction(function () use ($contactos) {
                // Se rehace: lo que se borró del formulario deja de mostrarse
                DB::delete('DELETE FROM contacto_soporte');
                foreach ($contactos as $n => $c) {
                    DB::insert('INSERT INTO contacto_soporte (canal, valor, etiqueta, orden) VALUES (?,?,?,?)',
                        [$c['canal'], $c['valor'], $c['etiqueta'], $n]);
                }
            });

            Auditoria::registrar('MODIFICACION', 'Configuracion', 'contacto_soporte', null,
                $contactos ? count($contactos) . ' medio(s): ' . implode(', ', array_column($contactos, 'canal'))
                           : 'Se quitaron todos los medios');

            flash($contactos
                ? count($contactos) . ' medio(s) de contacto guardado(s). Ya aparecen en el pie, bajo «Centro de Ayuda y Soporte».'
                : 'Se quitaron los contactos: el bloque de ayuda deja de mostrarse en el pie.');
        } catch (Throwable) {
            flash('No se pudo guardar el contacto.', 'error');
        }

        return $volver;
    }

    // ---------- Roles y permisos ----------

    public function roles(): View
    {
        $admin = (int) config('permisos.rol_admin', 1);

        // Se ordenan por ALCANCE, no por id: ordenados por id, el Profesional
        // salía arriba del Asistente administrativo y la matriz se leía al
        // revés de lo que es.
        $roles = DB::select(
            'SELECT r.*, (SELECT COUNT(*) FROM usuario u WHERE u.id_rol = r.id_rol) AS usuarios,
                    (SELECT COUNT(*) FROM rol_modulo rm WHERE rm.id_rol = r.id_rol) AS accesos
               FROM rol r
              ORDER BY (r.id_rol = :admin) DESC,
                       r.es_personal DESC,
                       accesos DESC,
                       r.nombre',
            ['admin' => $admin]
        );

        // Las claves se traducen igual que al comprobar un permiso: si un rol
        // quedó guardado con los nombres viejos, la matriz tiene que mostrar
        // las casillas que ese rol realmente tiene, no todas en blanco.
        $crudo = [];
        foreach (DB::select('SELECT id_rol, modulo FROM rol_modulo') as $p) {
            $crudo[(int) $p->id_rol][] = (string) $p->modulo;
        }

        $perm = [];
        foreach ($crudo as $idRol => $claves) {
            $perm[$idRol] = array_fill_keys(Permisos::equivaler($claves), true);
        }

        $claves = Permisos::claves();
        foreach ($roles as $r) {
            $r->alcance = (int) $r->id_rol === $admin
                ? count($claves)
                : count(array_filter($claves, fn ($c) => Permisos::marcado($perm[(int) $r->id_rol] ?? [], $c)));
        }

        return view('seguridad.roles', [
            'roles' => $roles,
            'matriz' => Permisos::matriz(),
            'perm' => $perm,
            'totalClaves' => count($claves),
            'admin' => $admin,
            'protegidos' => [$admin, (int) config('permisos.rol_cliente', 4)],
            // Para avisarle SOLO a quien puede quedarse afuera: el rol propio
            // se edita en esta misma matriz, salvo que sea el Administrador
            // (que queda fuera de `$editables` al guardar).
            'miRol' => (int) session('rol', 0),
        ]);
    }

    public function rolCrear(Request $request): RedirectResponse
    {
        $nombre = trim((string) $request->input('nombre', ''));
        $desc = trim((string) $request->input('descripcion', '')) ?: null;
        $esPersonal = $request->boolean('es_personal') ? 1 : 0;
        $volver = redirect()->route('seguridad.roles');

        if ($nombre === '') {
            flash('El nombre del rol es obligatorio.', 'error');

            return $volver;
        }
        if (mb_strlen($nombre) > 60) {
            flash('El nombre del rol no puede superar los 60 caracteres.', 'error');

            return $volver;
        }
        if (DB::scalar('SELECT COUNT(*) FROM rol WHERE nombre = ?', [$nombre])) {
            flash('Ya existe un rol con ese nombre.', 'error');

            return $volver;
        }

        try {
            DB::insert('INSERT INTO rol (nombre, descripcion, es_personal, activo) VALUES (?,?,?,1)',
                [$nombre, $desc, $esPersonal]);
            $id = (int) DB::getPdo()->lastInsertId();

            if ($esPersonal) {
                $sel = (array) $request->input('modulos', []);
                foreach (Permisos::claves() as $m) {
                    if (in_array($m, $sel, true)) {
                        DB::insert('INSERT IGNORE INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$id, $m]);
                    }
                }
            }

            Permisos::olvidar();
            Auditoria::registrar('ALTA', 'Configuracion', 'rol', $id, $nombre);
            flash('Rol «' . $nombre . '» creado.');
        } catch (Throwable) {
            flash('No se pudo crear el rol.', 'error');
        }

        return $volver;
    }

    public function rolEditar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_rol', 0);
        $nombre = trim((string) $request->input('nombre', ''));
        $desc = trim((string) $request->input('descripcion', '')) ?: null;
        $volver = redirect()->route('seguridad.roles');

        $rol = DB::selectOne('SELECT * FROM rol WHERE id_rol = ?', [$id]);
        if (! $rol) {
            flash('Ese rol no existe.', 'error');

            return $volver;
        }
        if ($nombre === '') {
            flash('El nombre del rol es obligatorio.', 'error');

            return $volver;
        }
        if (DB::scalar('SELECT COUNT(*) FROM rol WHERE nombre = ? AND id_rol <> ?', [$nombre, $id])) {
            flash('Ya existe otro rol con ese nombre.', 'error');

            return $volver;
        }

        // Ni es_personal ni activo se tocan en los roles que el código
        // referencia por id. Con el Administrador ya se cuidaba; el Cliente
        // quedaba afuera, y desactivarlo deja el portal sin rol al que asignar
        // a quien se registra. Se decide acá y no en la pantalla: esconder la
        // casilla no es el control.
        $protegido = $this->protegido($id);
        $esPersonal = $protegido ? (int) $rol->es_personal : ($request->boolean('es_personal') ? 1 : 0);
        $activo = $protegido ? (int) $rol->activo : ($request->boolean('activo') ? 1 : 0);

        DB::update('UPDATE rol SET nombre = ?, descripcion = ?, es_personal = ?, activo = ? WHERE id_rol = ?',
            [$nombre, $desc, $esPersonal, $activo, $id]);

        // Un rol que dejó de ser personal no debe conservar módulos del panel
        if (! $esPersonal) {
            DB::delete('DELETE FROM rol_modulo WHERE id_rol = ?', [$id]);
        }

        Permisos::olvidar();
        Auditoria::registrar('MODIFICACION', 'Configuracion', 'rol', $id, $nombre);
        flash('Rol actualizado.');

        return $volver;
    }

    public function rolBorrar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_rol', 0);
        $volver = redirect()->route('seguridad.roles');

        $rol = DB::selectOne('SELECT * FROM rol WHERE id_rol = ?', [$id]);
        if (! $rol) {
            flash('Ese rol no existe.', 'error');

            return $volver;
        }
        if ($this->protegido($id)) {
            flash('El rol «' . $rol->nombre . '» es parte del funcionamiento del sistema y no se puede '
                . 'eliminar. Podés desactivarlo o renombrarlo.', 'warning');

            return $volver;
        }

        $usuarios = (int) DB::scalar('SELECT COUNT(*) FROM usuario WHERE id_rol = ?', [$id]);
        if ($usuarios) {
            flash("No se puede eliminar: hay $usuarios usuario(s) con ese rol. Cambiales el rol primero.", 'warning');

            return $volver;
        }

        try {
            DB::transaction(function () use ($id) {
                DB::delete('DELETE FROM rol_modulo WHERE id_rol = ?', [$id]);
                DB::delete('DELETE FROM rol WHERE id_rol = ?', [$id]);
            });
            Permisos::olvidar();
            Auditoria::registrar('BAJA', 'Configuracion', 'rol', $id, 'Rol eliminado: ' . $rol->nombre);
            flash('Rol «' . $rol->nombre . '» eliminado.');
        } catch (Throwable) {
            flash('No se pudo eliminar el rol.', 'error');
        }

        return $volver;
    }

    /**
     * Guarda la matriz de permisos.
     *
     * Solo se aceptan las claves de la matriz, y lo que se guarda es siempre la
     * parte más chica —`facturacion.cobros`, no `facturacion`—, así el permiso
     * queda dicho una sola vez y sin ambigüedad.
     */
    public function permisosGuardar(Request $request): RedirectResponse
    {
        $matriz = (array) $request->input('perm', []);   // perm[id_rol][modulo] = 1
        $modulos = Permisos::claves();
        $volver = redirect()->route('seguridad.roles');

        // Solo roles de personal, excepto el Administrador (acceso total siempre)
        $editables = DB::select('SELECT id_rol FROM rol WHERE es_personal = 1 AND id_rol <> ?',
            [(int) config('permisos.rol_admin', 1)]);

        // Foto de cómo estaba ANTES: un cambio de permisos es lo que hay que
        // poder reconstruir después («¿quién le dio Timbrados al Profesional, y
        // cuándo?»), y con «Actualizó permisos de roles» a secas no se puede.
        $antes = [];
        foreach (DB::select('SELECT id_rol, modulo FROM rol_modulo') as $p) {
            $antes[(int) $p->id_rol][] = (string) $p->modulo;
        }

        try {
            DB::transaction(function () use ($editables, $modulos, $matriz) {
                foreach ($editables as $r) {
                    $idr = (int) $r->id_rol;
                    DB::delete('DELETE FROM rol_modulo WHERE id_rol = ?', [$idr]);
                    foreach ($modulos as $m) {
                        if (! empty($matriz[$idr][$m])) {
                            DB::insert('INSERT INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$idr, $m]);
                        }
                    }
                }
            });
        } catch (Throwable) {
            flash('No se pudieron guardar los permisos.', 'error');

            return $volver;
        }

        Permisos::olvidar();

        // Qué cambió, rol por rol y clave por clave.
        $nombres = [];
        foreach (DB::select('SELECT id_rol, nombre FROM rol') as $r) {
            $nombres[(int) $r->id_rol] = (string) $r->nombre;
        }
        $detalle = [];
        foreach ($editables as $r) {
            $idr = (int) $r->id_rol;
            $viejo = $antes[$idr] ?? [];
            $nuevo = array_values(array_filter($modulos, fn ($m) => ! empty($matriz[$idr][$m])));
            $mas = array_diff($nuevo, $viejo);
            $menos = array_diff($viejo, $nuevo);
            if ($mas || $menos) {
                $detalle[] = ($nombres[$idr] ?? "rol $idr") . ':'
                    . ($mas ? ' +' . implode(' +', $mas) : '')
                    . ($menos ? ' −' . implode(' −', $menos) : '');
            }
        }
        Auditoria::registrar('MODIFICACION', 'Configuracion', 'rol_modulo', null,
            $detalle ? implode(' | ', $detalle) : 'Se guardó la matriz sin cambios');
        flash('Permisos de los roles actualizados.');

        return $volver;
    }

    // ---------- Auditoría ----------

    // La pantalla también baja: sin `StreamedResponse` en la firma, exportar la
    // auditoría reventaba con un TypeError en vez de devolver el archivo.
    public function auditoria(): View|StreamedResponse
    {
        // Las opciones salen de lo que hay cargado, no de una lista fija: así
        // aparecen solas las acciones y módulos nuevos sin tocar el código.
        $opc = function (string $col): array {
            $vals = array_map(fn ($r) => (string) $r->v,
                DB::select("SELECT DISTINCT `$col` v FROM auditoria WHERE `$col` IS NOT NULL AND `$col` <> '' ORDER BY `$col`"));

            return ['' => 'Todos'] + array_combine($vals, $vals);
        };

        // **Dos vocabularios escriben en `auditoria`, y hay que buscarlos
        // juntos** (AU-01). Los controladores escriben el sustantivo
        // —`CANCELACION`, `EMISION`— y los disparadores de la base el verbo:
        // `trg_factura_au` y compañía anotan `ANULAR` y `REVERTIR`. El
        // resultado era que **filtrar por «anulación» no encontraba ninguna
        // anulación**: las 5 de cobro figuraban como `ANULAR`.
        //
        // No se reescribe lo que ya está guardado —el rastro es correcto, sólo
        // usa otra palabra— sino que el filtro busca las dos formas.
        $sinonimos = [
            'ANULACION' => ['ANULACION', 'ANULAR'],
            'REVERSION' => ['REVERSION', 'REVERTIR'],
        ];
        $agrupada = [];
        foreach ($sinonimos as $canon => $formas) {
            foreach ($formas as $forma) {
                $agrupada[$forma] = $canon;
            }
        }

        $opcAccion = function () use ($opc, $agrupada): array {
            $out = ['' => 'Todos'];
            foreach ($opc('accion') as $v => $etiqueta) {
                if ($v === '') {
                    continue;
                }
                $canon = $agrupada[$v] ?? $v;
                $out[$canon] = $canon;   // las dos formas caen en la misma opción
            }

            return $out;
        };

        // Las sucursales para el filtro. Se arma acá y no en el arreglo para
        // que la opción vacía diga «Todas», que es lo que se ve por defecto.
        $opcSucursal = function (): array {
            $o = ['' => 'Todas'];
            foreach (DB::select('SELECT id_sucursal, nombre FROM sucursal ORDER BY nombre') as $x) {
                $o[(string) $x->id_sucursal] = $x->nombre;
            }

            return $o;
        };

        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Detalle, tabla o usuario', 'ancho' => '250px'],
            'accion' => ['tipo' => 'select', 'etiqueta' => 'Acción', 'opciones' => $opcAccion()],
            'modulo' => ['tipo' => 'select', 'etiqueta' => 'Módulo', 'opciones' => $opc('modulo')],
            // **Se ve todo y se puede acotar**, igual que los reportes: quien
            // audita necesita el cuadro completo, y a veces revisar una sede.
            'sucursal' => ['tipo' => 'select', 'etiqueta' => 'Sucursal', 'opciones' => $opcSucursal()],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['a.detalle', 'a.tabla_afectada', "CONCAT(pe.nombre,' ',pe.apellido)"],
                Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'sucursal')) {
            $w[] = 'a.id_sucursal = :suc';
            $par['suc'] = (int) Listado::valor($f, 'sucursal');
        }
        if (Listado::hay($f, 'accion')) {
            // Si la acción elegida tiene sinónimo, se buscan las dos formas:
            // «Anulación» tiene que encontrar también lo que los disparadores
            // anotaron como ANULAR.
            $elegida = (string) Listado::valor($f, 'accion');
            $formas = $sinonimos[$elegida] ?? [$elegida];

            $marcas = [];
            foreach (array_values($formas) as $i => $forma) {
                $marcas[] = ":ac$i";
                $par["ac$i"] = $forma;
            }
            $w[] = 'a.accion IN (' . implode(',', $marcas) . ')';
        }
        if (Listado::hay($f, 'modulo')) {
            $w[] = 'a.modulo = :mo';
            $par['mo'] = Listado::valor($f, 'modulo');
        }
        // OJO: la columna de `auditoria` es `fecha_hora`, no `fecha`. Escrita
        // como `a.fecha` la pantalla contestaba 500 en todas sus consultas.
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(a.fecha_hora) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(a.fecha_hora) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        // **La auditoría se comparte, pero se puede mirar por local.** Es lo
        // mismo que hacen los reportes: el consolidado es lo que se ve por
        // defecto —quien audita necesita el cuadro completo— y el filtro acota
        // cuando se quiere revisar una sede. La sucursal es DONDE OCURRIÓ el
        // hecho, así que se guarda: no se deduce de nada.
        $desde = 'FROM auditoria a
                  JOIN usuario u  ON u.id_usuario = a.id_usuario
                  JOIN persona pe ON pe.id_persona = u.id_persona
                  LEFT JOIN sucursal su ON su.id_sucursal = a.id_sucursal
                  WHERE ' . implode(' AND ', $w);
        $cols = "a.fecha_hora AS fecha, a.accion, a.modulo, a.tabla_afectada, a.id_registro, a.detalle,
                 CONCAT(pe.nombre,' ',pe.apellido) AS usuario,
                 COALESCE(su.nombre,'—') AS sucursal";

        if (Listado::pideExport()) {
            return Listado::exportar('auditoria',
                ['Fecha', 'Usuario', 'Acción', 'Módulo', 'Tabla', 'Registro', 'Detalle'],
                array_map(fn ($r) => [fecha($r->fecha, 'd/m/Y H:i'), $r->usuario, $r->accion,
                    $r->modulo, $r->tabla_afectada, $r->id_registro, $r->detalle],
                    DB::select("SELECT $cols $desde ORDER BY a.fecha_hora DESC", $par)),
                $f, 'Auditoría'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par), 30);

        return view('seguridad.auditoria', [
            'rows' => DB::select("SELECT $cols $desde ORDER BY a.fecha_hora DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    /** El Administrador y el Cliente los usa el código: no se borran. */
    private function protegido(int $idRol): bool
    {
        return in_array($idRol, [
            (int) config('permisos.rol_admin', 1),
            (int) config('permisos.rol_cliente', 4),
        ], true);
    }
}
