<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Servicios\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Facturación electrónica: el puente con el Automatizador SIFEN.
 *
 * El SPG **no habla con la DNIT ni firma nada**. Toma un comprobante que ya
 * emitió y numeró —con su timbrado y su correlativo, como siempre—, lo escribe
 * en el formato de texto que el Automatizador entiende y se lo manda. Lo que
 * vuelve es el CDC, que es el número con el que la DNIT reconoce el documento,
 * y se guarda en `factura_electronica`.
 *
 * TRES REGLAS QUE NO HAY QUE PERDER:
 *
 *  1. **Emitir y enviar son dos cosas separadas.** La factura se emite en el
 *     SPG y queda válida aunque el Automatizador esté caído; el envío es un
 *     paso posterior que se puede repetir. Si emitir dependiera de que un
 *     servicio externo conteste, un corte de internet dejaría al salón sin
 *     poder cobrar.
 *
 *  2. **Un rechazo por datos NO se reintenta.** Si la DNIT dice que el RUC
 *     está mal, mandarlo de nuevo da el mismo error. Por eso hay dos estados
 *     distintos: PENDIENTE (se puede reintentar) y RECHAZADO (hay que
 *     corregir el dato y emitir de nuevo).
 *
 *  3. **Un fallo de red deja el envío en PENDIENTE, nunca en RECHAZADO.** El
 *     comprobante puede haberse emitido igual del otro lado: se reintenta a
 *     mano, mirando antes si ya tiene CDC.
 *
 * El Ticket no pasa por acá: es el comprobante interno del salón, el que se
 * usa cuando la clienta no pide factura.
 */
class Sifen
{
    /** ¿El salón usa facturación electrónica? Con esto en false, el módulo no existe. */
    public static function activo(): bool
    {
        return (bool) config('sifen.activo', false);
    }

    /** ¿Este tipo de comprobante va a la DNIT, o es interno del salón? */
    public static function esElectronico(int $idTipoComprobante): bool
    {
        return in_array($idTipoComprobante, (array) config('sifen.tipos_electronicos', [1, 5]), true);
    }

    /** El estado del envío de una factura, o null si nunca se intentó. */
    public static function estado(int $idFactura): ?object
    {
        return DB::selectOne('SELECT * FROM factura_electronica WHERE id_factura = ?', [$idFactura]);
    }

    // -----------------------------------------------------------------
    //  El receptor: qué pide el manual, y cómo se valida antes de mandar
    // -----------------------------------------------------------------

    /**
     * Un DE innominado —«Consumidor Final», sin documento— no se acepta a
     * partir de este monto (Manual Técnico v150, error **1321**, campo D208c).
     * Arriba de eso hay que identificar a la clienta.
     */
    public const TOPE_INNOMINADO = 60000000;

    /**
     * Dígito verificador de un RUC, por módulo 11.
     *
     * Es lo que la DNIT comprueba en el campo D207 del receptor: si no
     * coincide, rechaza con el error **1309**. Vale la pena calcularlo acá
     * porque un RUC mal tipeado es el rechazo más común y no hace falta ir
     * hasta la DNIT para descubrirlo.
     *
     * Las letras se convierten por su valor ASCII, como indica el manual para
     * los identificadores que las admiten.
     */
    public static function dvRuc(string $base): int
    {
        $limpio = strtoupper(preg_replace('/[^0-9A-Z]/', '', $base) ?? '');
        if ($limpio === '') {
            return -1;
        }

        $numero = '';
        foreach (str_split($limpio) as $c) {
            $numero .= ctype_digit($c) ? $c : (string) ord($c);
        }

        $total = 0;
        $k = 2;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $total += ((int) $numero[$i]) * $k;
            $k = $k === 11 ? 2 : $k + 1;
        }

        $resto = $total % 11;

        return $resto > 1 ? 11 - $resto : 0;
    }

    /** Valida un RUC completo sin imponer un dígito verificador concreto. */
    public static function rucValido(string $documento): bool
    {
        $doc = strtoupper(trim($documento));
        $doc = str_replace(['.', ' '], '', $doc);
        if (! preg_match('/^([0-9A-Z]{3,10})-?([0-9])$/', $doc, $m)) {
            return false;
        }

        return self::dvRuc($m[1]) === (int) $m[2];
    }

    /**
     * Revisa los datos del receptor antes de emitir. Devuelve el problema, o
     * null si está todo bien.
     *
     * Se valida acá y no después porque **un rechazo de la DNIT no se
     * reintenta**: si el RUC va mal, el comprobante ya quedó emitido con un
     * número que no se puede reusar y hay que anularlo y hacer otro. Todo lo
     * que se pueda comprobar sin salir del salón, se comprueba antes.
     *
     * Las reglas salen del Manual Técnico v150, grupo D (campos D201-D216).
     */
    public static function validarReceptor(array $d, float $total = 0): ?string
    {
        $tipo = strtoupper(trim((string) ($d['tipo_doc'] ?? '')));
        $doc = trim((string) ($d['documento'] ?? ''));
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $email = trim((string) ($d['email'] ?? ''));

        if (! in_array($tipo, ['RUC', 'CI', 'CF'], true)) {
            return 'Elegí si la clienta se identifica con RUC, con cédula o como consumidor final.';
        }

        // D211: el nombre es obligatorio siempre (ocurrencia 1-1).
        if ($tipo !== 'CF' && $nombre === '') {
            return 'Poné el nombre o la razón social: la DNIT lo exige en todos los comprobantes.';
        }

        if ($tipo === 'RUC') {
            // D206 + D207: el RUC va con su dígito verificador.
            $normalizado = strtoupper(str_replace(['.', ' '], '', $doc));
            if (! preg_match('/^([0-9A-Z]{3,10})-?([0-9])$/', $normalizado, $m)) {
                return 'El RUC va con su dígito verificador, así: 80012345-0.';
            }
            $esperado = self::dvRuc($m[1]);
            if ((int) $m[2] !== $esperado) {
                return 'El dígito verificador del RUC no corresponde: para ' . $m[1]
                       . ' tendría que ser ' . $esperado . ', no ' . $m[2] . '.';
            }
        } elseif ($tipo === 'CI') {
            // D210: número de documento. Se aceptan puntos porque la gente los
            // escribe, pero tiene que ser un número.
            if (! preg_match('/^[0-9][0-9\.\s]{2,19}$/', $doc)) {
                return 'La cédula tiene que ser un número. Si no la tenés a mano, elegí consumidor final.';
            }
        } elseif ($total >= self::TOPE_INNOMINADO) {
            // D208c / error 1321.
            return 'Una venta de ' . money($total) . ' no se puede emitir a consumidor final: '
                   . 'arriba de ' . money(self::TOPE_INNOMINADO) . ' la DNIT exige identificar a la clienta '
                   . 'con cédula o RUC.';
        }

        // D216: es opcional para la DNIT, pero es la dirección a la que el
        // Automatizador manda el PDF. Sin correo el comprobante se emite igual
        // y no le llega a nadie, así que conviene avisarlo antes.
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Ese correo no es válido: es a donde se le manda el comprobante.';
        }

        return null;
    }

    /**
     * Arma el comprobante en el formato del Automatizador.
     *
     * Son líneas separadas por `|`: una FAC con la cabecera, una CLI con el
     * cliente y una ITM por renglón. El total NO se escribe — lo calcula el
     * Automatizador desde los ítems, y el precio va con el IVA INCLUIDO, que
     * es como lo guarda el SPG.
     *
     * Se devuelve el texto para poder mirarlo antes de mandarlo: es la forma
     * de saber qué se envió cuando la DNIT rechaza algo.
     */
    /**
     * Parte un RUC «80012345-0» en el número y su dígito verificador.
     *
     * **Si viene sin guion se calcula el DV**, en vez de mandar uno en blanco:
     * el SIFEN los pide separados y un DV vacío es rechazo seguro. Y si el
     * guardado no coincide con el que corresponde, **manda el correcto**: el
     * RUC del salón se tipea a mano en la ficha de la sucursal, y un dígito
     * mal escrito ahí saldría impreso en cada comprobante.
     */
    private static function partirRuc(string $ruc): array
    {
        $ruc = trim($ruc);
        if ($ruc === '') {
            return ['', ''];
        }

        $base = preg_replace('/\D/', '', explode('-', $ruc)[0]) ?? '';
        if ($base === '') {
            return ['', ''];
        }

        return [$base, (string) self::dvRuc($base)];
    }

    public static function armarTxt(int $idFactura, array $receptor = []): string
    {
        $f = DB::selectOne(
            "SELECT f.id_factura, f.fecha_emision, f.nro_correlativo, f.id_condicion_venta,
                    t.establecimiento, t.punto_expedicion,
                    t.nro_timbrado, t.fecha_inicio AS timbrado_inicio, t.fecha_fin AS timbrado_fin,
                    su.nombre AS suc_nombre, su.ruc AS suc_ruc,
                    su.direccion AS suc_direccion, su.ciudad AS suc_ciudad, su.telefono AS suc_telefono,
                    pe.nombre, pe.apellido, pe.cedula, pe.ruc, pe.email, pe.telefono, pe.direccion
               FROM factura f
               JOIN timbrado t ON t.id_timbrado = f.id_timbrado
               -- **El emisor es el LOCAL que emitió, no el salón en abstracto.**
               -- El establecimiento impreso dice de qué sede salió el
               -- comprobante (7.37.0), así que la dirección y el timbrado
               -- tienen que ser los de esa misma sede o el papel se
               -- contradice a sí mismo.
               LEFT JOIN sucursal su ON su.id_sucursal = COALESCE(f.id_sucursal, t.id_sucursal)
               JOIN cliente c  ON c.id_cliente = f.id_cliente
               JOIN persona pe ON pe.id_persona = c.id_persona
              WHERE f.id_factura = ?", [$idFactura]
        );
        if (! $f) {
            throw new \RuntimeException('Esa factura no existe.');
        }

        $items = DB::select(
            'SELECT item, cantidad, precio_unitario, tasa_iva
               FROM vw_detalle_factura WHERE id_factura = ? ORDER BY id_detalle_factura', [$idFactura]
        );
        if (! $items) {
            throw new \RuntimeException('La factura no tiene renglones que declarar.');
        }

        // Nada puede llevar el separador adentro, o la línea se parte de más.
        $limpiar = fn ($v) => trim(str_replace(['|', "\r", "\n"], ['/', ' ', ' '], (string) $v));

        // Lo que se cargó en el formulario del receptor manda sobre la ficha:
        // ahí es donde se eligió, por ejemplo, emitir a consumidor final
        // aunque la clienta tenga la cédula cargada. Sin el formulario —un
        // reenvío a mano, más adelante— se usa la ficha, que es la fuente de
        // verdad persistida.
        [$tipoDoc, $doc] = match (true) {
            ! empty($receptor['tipo_doc']) => strtoupper((string) $receptor['tipo_doc']) === 'CF'
                ? ['CI', 'CF']
                : [strtoupper((string) $receptor['tipo_doc']), trim((string) ($receptor['documento'] ?? ''))],
            trim((string) $f->ruc) !== '' => ['RUC', trim((string) $f->ruc)],
            trim((string) $f->cedula) !== '' => ['CI', trim((string) $f->cedula)],
            default => ['CI', 'CF'],
        };

        $nombre = trim((string) ($receptor['nombre'] ?? '')) ?: trim($f->nombre . ' ' . $f->apellido);
        $nombre = $nombre ?: 'Consumidor Final';
        $email = trim((string) ($receptor['email'] ?? '')) ?: (string) $f->email;
        $direccion = trim((string) ($receptor['direccion'] ?? '')) ?: (string) $f->direccion;
        $telefono = trim((string) ($receptor['telefono'] ?? '')) ?: (string) $f->telefono;

        // Innominado: el manual pide el nombre en «Sin Nombre» y el documento
        // en cero, pero el Automatizador ya traduce `CF` a eso, así que se le
        // manda tal cual y se evita duplicar la regla de los dos lados.
        if ($doc === 'CF') {
            $nombre = 'Consumidor Final';
        }

        // ------------------------------------------------------------------
        //  EMI: quién emite.
        //
        //  **Antes no se mandaba y el KuDE lo sacaba del `.env` del
        //  Automatizador**, que trae los datos del archivo de ejemplo: salía
        //  «MI EMPRESA S.A.», RUC 80012345-6 —con el dígito verificador mal,
        //  que es el rechazo 1309 de la DNIT— y actividad «VENTA AL POR
        //  MENOR». Un comprobante con el emisor de otro no sirve para nada.
        //
        //  Y no se puede resolver dejándolo fijo del otro lado: **el emisor
        //  cambia con la sucursal**. La dirección y el timbrado son los del
        //  local que atendió, igual que el establecimiento del número.
        //
        //  Si el Automatizador recibe un TXT sin esta línea sigue usando su
        //  `.env`, así que un envío viejo no se rompe.
        // ------------------------------------------------------------------
        $act = Config::actividad();
        [$rucEmisor, $dvEmisor] = self::partirRuc((string) ($f->suc_ruc ?? ''));

        $lineas = [];
        $lineas[] = implode('|', [
            'EMI',
            $limpiar(Config::nombreSalon()),
            $limpiar($rucEmisor),
            $limpiar($dvEmisor),
            $limpiar($f->suc_direccion ?? ''),
            $limpiar($f->suc_ciudad ?? ''),
            $limpiar($f->suc_telefono ?? ''),
            $limpiar(Config::email()),
            $limpiar($act['cod']),
            $limpiar($act['desc']),
            $limpiar($f->nro_timbrado ?? ''),
            date('Y-m-d', strtotime((string) ($f->timbrado_inicio ?? 'now'))),
            date('Y-m-d', strtotime((string) ($f->timbrado_fin ?? 'now'))),
            // El nombre del local: con varias sedes, saber de cuál salió el
            // papel no se deduce del número para quien lo recibe.
            $limpiar($f->suc_nombre ?? ''),
        ]);
        $lineas[] = implode('|', [
            'FAC',
            str_pad((string) $f->establecimiento, 3, '0', STR_PAD_LEFT),
            str_pad((string) $f->punto_expedicion, 3, '0', STR_PAD_LEFT),
            str_pad((string) $f->nro_correlativo, 7, '0', STR_PAD_LEFT),
            date('Y-m-d', strtotime((string) $f->fecha_emision)),
            (int) $f->id_condicion_venta === 2 ? '2' : '1',   // 1 contado · 2 crédito
            'PYG',
            // **Tipo de transacción (D011 `iTipTra`): 2, prestación de
            // servicios.** El Automatizador lo traía fijo en 1, «venta de
            // mercadería», que describe a un comercio y no a un salón — y va
            // impreso en el KuDE **y** dentro del XML que ve la DNIT. El salón
            // no vende productos (fuera de alcance desde la 7.23.1); el día
            // que venda, esto pasa a 3, «mixto».
            '2',
        ]);
        $lineas[] = implode('|', [
            'CLI', $tipoDoc, $limpiar($doc), $limpiar($nombre),
            $limpiar($email), $limpiar($direccion), $limpiar($telefono),
        ]);

        foreach ($items as $i => $it) {
            $lineas[] = implode('|', [
                'ITM',
                'S' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                $limpiar($it->item),
                // Cantidad sin decimales sobrantes: «1» y no «1.00».
                rtrim(rtrim(number_format((float) $it->cantidad, 2, '.', ''), '0'), '.'),
                (string) (int) round((float) $it->precio_unitario),
                (string) (int) $it->tasa_iva,
            ]);
        }

        return implode("\n", $lineas) . "\n";
    }

    /**
     * Manda el comprobante y guarda lo que conteste.
     *
     * Devuelve `['ok' => bool, 'mensaje' => string, 'cdc' => ?string]`. Nunca
     * lanza: el resultado se le muestra a quien apretó el botón, y el estado
     * queda escrito pase lo que pase.
     */
    public static function enviar(int $idFactura, array $receptor = []): array
    {
        $f = DB::selectOne(
            'SELECT f.id_factura, f.id_tipo_comprobante, f.id_estado_factura, fn_factura_nro(f.id_factura) AS nro
               FROM factura f WHERE f.id_factura = ?', [$idFactura]
        );
        if (! $f) {
            return ['ok' => false, 'mensaje' => 'Esa factura no existe.', 'cdc' => null];
        }
        if ((int) $f->id_estado_factura === 2) {
            return ['ok' => false, 'mensaje' => 'Ese comprobante está anulado: no se manda a la DNIT.', 'cdc' => null];
        }
        if (! self::esElectronico((int) $f->id_tipo_comprobante)) {
            return ['ok' => false, 'mensaje' => 'Ese comprobante es interno del salón y no se declara.', 'cdc' => null];
        }

        $ya = self::estado($idFactura);
        if ($ya && $ya->estado === 'ENVIADO') {
            return ['ok' => true, 'mensaje' => 'Ya estaba declarado. CDC ' . $ya->cdc . '.', 'cdc' => $ya->cdc];
        }

        try {
            $txt = self::armarTxt($idFactura, $receptor);
        } catch (Throwable $e) {
            self::guardar($idFactura, 'RECHAZADO', null, null, [], $e->getMessage());

            return ['ok' => false, 'mensaje' => $e->getMessage(), 'cdc' => null];
        }

        // A dónde va el PDF: es el 4º campo de la línea CLI. Se lee del TXT ya
        // armado y no de los parámetros, así que dice lo que REALMENTE se
        // mandó, venga del formulario o de la ficha.
        $correo = self::correoDelTxt($txt);

        $r = config('sifen.modo') === 'http'
            ? self::porHttp($txt, $correo)
            : self::simulado($idFactura, $correo);

        self::guardar($idFactura, $r['estado'], $r['cdc'], $r['track_id'] ?? null, $r, $r['mensaje']);
        self::guardarCopias($idFactura, $txt, $r);

        if ($r['estado'] === 'ENVIADO') {
            Auditoria::registrar('SIFEN', 'Facturacion', 'factura', $idFactura,
                'Comprobante ' . $f->nro . ' declarado. CDC ' . $r['cdc']);
        }

        return ['ok' => $r['estado'] === 'ENVIADO', 'mensaje' => $r['mensaje'], 'cdc' => $r['cdc']];
    }

    /** El correo del receptor tal como quedó escrito en la línea CLI. */
    private static function correoDelTxt(string $txt): string
    {
        foreach (explode("\n", $txt) as $l) {
            if (str_starts_with($l, 'CLI|')) {
                $p = explode('|', $l);

                return trim($p[4] ?? '');
            }
        }

        return '';
    }

    /** El envío de verdad, contra el Automatizador. */
    private static function porHttp(string $txt, string $correo = ''): array
    {
        $url = (string) config('sifen.url');
        if ($url === '') {
            return ['estado' => 'PENDIENTE', 'cdc' => null,
                    'mensaje' => 'Falta configurar SIFEN_URL: no hay a dónde mandarlo.'];
        }

        try {
            $resp = Http::withHeaders(['X-API-Token' => (string) config('sifen.token')])
                ->withBody($txt, 'text/plain; charset=utf-8')
                ->timeout((int) config('sifen.timeout', 60))
                ->post($url);
        } catch (Throwable $e) {
            // Puede haberse emitido igual del otro lado: queda PENDIENTE para
            // reintentar a mano, nunca RECHAZADO.
            return ['estado' => 'PENDIENTE', 'cdc' => null,
                    'mensaje' => 'No se pudo llegar al servicio (' . $e->getMessage()
                                 . '). Puede haberse emitido igual: fijate antes de reenviar.'];
        }

        $j = $resp->json();

        if ($resp->successful() && ! empty($j['ok']) && ! empty($j['facturas'][0]['cdc'])) {
            $d = $j['facturas'][0];

            return [
                'estado' => 'ENVIADO',
                'cdc' => (string) $d['cdc'],
                'track_id' => (string) ($d['track_id'] ?? ''),
                'kude_url' => (string) ($d['kude_url'] ?? ''),
                'xml_url' => (string) ($d['xml_url'] ?? ''),
                'mensaje' => 'Declarado. CDC ' . $d['cdc'] . '. ' . self::avisoCorreo($correo, $d['mail_enviado'] ?? null),
            ];
        }

        $motivo = (string) ($j['error'] ?? ('El servicio contestó ' . $resp->status() . '.'));

        // 4xx es un problema de los datos y repetirlo da lo mismo; 5xx o un
        // corte es del otro lado y sí se puede reintentar.
        return [
            'estado' => $resp->clientError() ? 'RECHAZADO' : 'PENDIENTE',
            'cdc' => null,
            'mensaje' => $motivo,
        ];
    }

    /**
     * Modo simulado: arma el TXT de verdad pero no sale de acá.
     *
     * Sirve para ver el circuito completo —emitir, declarar, guardar el CDC,
     * mostrarlo en el comprobante— sin depender de que el Automatizador esté
     * publicado. El CDC arranca con `0` para que se note de una que no es real.
     */
    private static function simulado(int $idFactura, string $correo = ''): array
    {
        $cdc = '0' . str_pad((string) $idFactura, 8, '0', STR_PAD_LEFT)
             . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT)
             . str_pad((string) time(), 10, '0', STR_PAD_LEFT)
             . str_pad((string) random_int(0, 999999999), 20, '0', STR_PAD_LEFT);

        return [
            'estado' => 'ENVIADO',
            'cdc' => substr($cdc, 0, 44),
            'track_id' => 'SIM-' . $idFactura,
            'mensaje' => 'Declarado en modo simulado: el comprobante NO se mandó a la DNIT. '
                         . ($correo !== ''
                            ? 'Con el servicio conectado, el PDF le habría llegado a ' . $correo . '.'
                            : 'Ojo: no hay correo cargado, así que no le habría llegado a nadie.'),
        ];
    }

    /**
     * Qué contar sobre el correo del comprobante.
     *
     * El Automatizador devuelve `mail_enviado`, y ese dato importa tanto como
     * el CDC: para la clienta, «facturado» significa que le llegó el PDF. Si
     * el comprobante se declaró pero el correo no salió, hay que decirlo — si
     * no, el salón da por hecho que la clienta lo tiene.
     */
    private static function avisoCorreo(string $correo, ?bool $enviado): string
    {
        if ($correo === '') {
            return 'No se le mandó el PDF: la clienta no tiene correo cargado.';
        }
        if ($enviado === false) {
            return 'El PDF NO salió por correo a ' . $correo . ': mandaselo a mano desde el comprobante.';
        }

        return 'El PDF le fue enviado a ' . $correo . '.';
    }

    // -----------------------------------------------------------------
    //  Las copias: el comprobante tiene que poder verse desde acá
    // -----------------------------------------------------------------

    /** Dónde viven las copias de un comprobante declarado. */
    public static function carpeta(int $idFactura): string
    {
        return storage_path('app/sifen/' . $idFactura);
    }

    /**
     * Guarda una copia de todo lo que se mandó y de lo que volvió.
     *
     * **El sistema NO puede depender del Automatizador para mostrar un
     * comprobante que ya emitió.** Las URL que devuelve (`kude_url`, `xml_url`)
     * apuntan a SU dominio publicado, que hoy no responde: el botón «KuDE»
     * mandaba a una página caída. Además esas URL **no llevan el token**, así
     * que ni siquiera desde adentro servirían tal cual.
     *
     * Por eso se baja el PDF y el XML apenas se declaran y se guardan acá. Es
     * lo que el propio manual del Automatizador recomienda: descargarlo desde
     * el servidor, con el token del lado del servidor, y servirlo uno mismo
     * para que el token no llegue nunca al navegador.
     *
     * El TXT se guarda siempre, aunque el envío falle: es la prueba de qué se
     * mandó, que es lo primero que hace falta cuando la DNIT rechaza algo.
     */
    private static function guardarCopias(int $idFactura, string $txt, array $r): void
    {
        $dir = self::carpeta($idFactura);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            Log::warning('SIFEN: no se pudo crear ' . $dir);

            return;
        }

        @file_put_contents($dir . '/enviado.txt', $txt);

        $cdc = (string) ($r['cdc'] ?? '');
        if (($r['estado'] ?? '') !== 'ENVIADO' || $cdc === '' || config('sifen.modo') !== 'http') {
            return;   // en simulado no hay nada que bajar: no se mandó a ningún lado
        }

        foreach (['pdf', 'xml'] as $ext) {
            $destino = $dir . '/' . $cdc . '.' . $ext;
            if (is_file($destino)) {
                continue;
            }
            try {
                // Se arma desde SIFEN_URL y no desde la `kude_url` que vuelve:
                // esa apunta al dominio publicado del Automatizador, que puede
                // no ser el que estamos usando.
                $resp = Http::withHeaders(['X-API-Token' => (string) config('sifen.token')])
                    ->timeout((int) config('sifen.timeout', 60))
                    ->get(rtrim((string) config('sifen.url'), '/') . '/descargar.php', ['f' => $cdc . '.' . $ext]);

                if ($resp->successful() && $resp->body() !== '') {
                    @file_put_contents($destino, $resp->body());
                } else {
                    Log::warning('SIFEN: no se pudo bajar el ' . $ext . ' de ' . $cdc
                                 . ' (HTTP ' . $resp->status() . ')');
                }
            } catch (Throwable $e) {
                // Que no se pueda bajar la copia NO invalida el envío: el
                // comprobante ya está declarado y el CDC guardado.
                Log::warning('SIFEN: no se pudo bajar el ' . $ext . ' de ' . $cdc . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * La copia local de un comprobante, o null si no está.
     *
     * `$que` es `pdf`, `xml` o `txt`.
     */
    public static function copia(int $idFactura, string $que): ?string
    {
        $dir = self::carpeta($idFactura);

        if ($que === 'txt') {
            return is_file($dir . '/enviado.txt') ? $dir . '/enviado.txt' : null;
        }
        if (! in_array($que, ['pdf', 'xml'], true)) {
            return null;
        }

        $cdc = (string) (self::estado($idFactura)->cdc ?? '');

        return $cdc !== '' && is_file($dir . '/' . $cdc . '.' . $que) ? $dir . '/' . $cdc . '.' . $que : null;
    }

    /**
     * Vuelve a pedirle al Automatizador el PDF y el XML de un comprobante ya
     * declarado. Para los que se declararon cuando esto no existía, o cuando
     * el servicio estaba caído justo al bajarlos.
     */
    public static function bajarCopias(int $idFactura): bool
    {
        $e = self::estado($idFactura);
        if (! $e || $e->estado !== 'ENVIADO' || ! $e->cdc) {
            return false;
        }

        self::guardarCopias($idFactura, self::copia($idFactura, 'txt')
            ? (string) file_get_contents(self::copia($idFactura, 'txt'))
            : self::armarTxt($idFactura), ['estado' => 'ENVIADO', 'cdc' => $e->cdc]);

        return self::copia($idFactura, 'pdf') !== null;
    }

    /** Deja escrito el resultado del envío. Una fila por factura. */
    private static function guardar(int $idFactura, string $estado, ?string $cdc, ?string $trackId, array $r, string $mensaje): void
    {
        DB::statement(
            'INSERT INTO factura_electronica
                (id_factura, cdc, estado, track_id, kude_url, xml_url, mensaje, intentos, fecha_envio)
             VALUES (:id, :cdc, :estado, :track, :kude, :xml, :msg, 1, NOW())
             ON DUPLICATE KEY UPDATE
                cdc = COALESCE(VALUES(cdc), cdc),
                estado = VALUES(estado),
                track_id = COALESCE(VALUES(track_id), track_id),
                kude_url = COALESCE(VALUES(kude_url), kude_url),
                xml_url = COALESCE(VALUES(xml_url), xml_url),
                mensaje = VALUES(mensaje),
                intentos = intentos + 1,
                fecha_envio = NOW()',
            [
                'id' => $idFactura, 'cdc' => $cdc, 'estado' => $estado,
                'track' => $trackId ?: null,
                'kude' => ($r['kude_url'] ?? '') ?: null,
                'xml' => ($r['xml_url'] ?? '') ?: null,
                'msg' => mb_substr($mensaje, 0, 500),
            ]
        );
    }
}
