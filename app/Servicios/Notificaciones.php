<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Mail\AvisoCita;
use App\Mail\AvisoInterno;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisos a las clientas.
 *
 * La tabla `notificacion` del TCC ya existía y se llenaba, pero **nadie
 * despachaba nada**: quedaba todo en PENDIENTE. Acá está lo que faltaba —
 * generar, enviar y marcar el resultado.
 *
 * Tres motivos de aviso:
 *   · recordatorio de la cita, con la anticipación que eligió cada clienta;
 *   · el profesional no va a estar (ausencia o baja), con enlace para
 *     reprogramar o cambiar de profesional;
 *   · confirmación al agendar, que crean los propios procedimientos.
 *
 * **El despacho lo dispara el cron, no una visita al sistema.** En el sistema
 * anterior salía cuando alguien entraba, así que un domingo con el salón
 * cerrado no salía ninguno.
 */
class Notificaciones
{
    private const RECORDATORIO = 1;

    private const CANCELACION = 3;

    /** Cuántos correos como mucho por pasada, para que el cron no se eternice. */
    private const POR_PASADA = 25;

    // -----------------------------------------------------------------
    //  Enlace para reprogramar sin iniciar sesión
    //
    //  La mayoría de las clientas que agendan en el local no tienen cuenta:
    //  el token del correo ES la credencial. Dura 30 días y muere al cancelar.
    // -----------------------------------------------------------------

    public static function tokenDeCita(int $idCita, int $dias = 30): string
    {
        $vigente = DB::scalar(
            'SELECT codigo FROM token_cita WHERE id_cita = ? AND usado = 0 AND expira_en > NOW()
              ORDER BY id_token DESC LIMIT 1', [$idCita]
        );
        if ($vigente) {
            return (string) $vigente;
        }

        $codigo = bin2hex(random_bytes(24));
        DB::insert('INSERT INTO token_cita (id_cita, codigo, expira_en) VALUES (?,?, DATE_ADD(NOW(), INTERVAL ? DAY))',
            [$idCita, $codigo, $dias]);

        return $codigo;
    }

    /** La cita del token, si el token sigue sirviendo. */
    public static function citaPorToken(string $codigo): ?object
    {
        if ($codigo === '' || ! preg_match('/^[a-f0-9]{48}$/', $codigo)) {
            return null;
        }

        return DB::selectOne(
            "SELECT t.id_token, t.id_cita, c.id_cliente, c.id_usuario, c.fecha_hora, c.id_estado_cita,
                    CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente
               FROM token_cita t
               JOIN cita c     ON c.id_cita = t.id_cita
               JOIN cliente cl ON cl.id_cliente = c.id_cliente
               JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
              WHERE t.codigo = ? AND t.usado = 0 AND t.expira_en > NOW()", [$codigo]
        );
    }

    // -----------------------------------------------------------------
    //  Alta de avisos
    // -----------------------------------------------------------------

    public static function crear(int $tipo, ?int $idCliente, ?int $idCita, string $mensaje, string $canal = 'EMAIL'): void
    {
        try {
            DB::insert(
                "INSERT INTO notificacion (id_tipo_notificacion, id_cliente, id_cita, canal, mensaje, estado)
                 VALUES (?,?,?,?,?, 'PENDIENTE')",
                [$tipo, $idCliente, $idCita, $canal, mb_substr($mensaje, 0, 300)]
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * El profesional no va a estar: se avisa a cada clienta con cita en el
     * rango. Devuelve a cuántas.
     *
     * `$idUsuario` en null significa **todo el salón**, que es como se carga
     * un feriado en `ausencia_agenda` (la columna admite NULL). Sin ese caso,
     * la excepción que cierra el local no avisaría a nadie, que es justo la
     * que más gente deja plantada.
     */
    public static function avisarProfesionalNoDisponible(?int $idUsuario, ?string $desde = null, ?string $hasta = null, string $motivo = ''): int
    {
        // OJO: los marcadores con nombre no se pueden repetir (la conexión va
        // con las preparadas nativas de MySQL), por eso el id va dos veces con
        // dos nombres distintos.
        $par = ['u1' => $idUsuario, 'u2' => $idUsuario];
        if ($desde !== null && $hasta !== null) {
            $rango = ' AND c.fecha_hora < :hasta AND c.fecha_hora >= :desde';
            $par['desde'] = $desde;
            $par['hasta'] = $hasta;
        } else {
            $rango = ' AND c.fecha_hora >= NOW()';
        }

        $citas = DB::select(
            "SELECT c.id_cita, c.id_cliente, c.fecha_hora,
                    CONCAT(pe_u.nombre,' ',pe_u.apellido) AS profesional
               FROM cita c
               JOIN usuario u  ON u.id_usuario = c.id_usuario
               JOIN persona pe_u ON pe_u.id_persona = u.id_persona
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE (:u1 IS NULL OR c.id_usuario = :u2)
                AND ec.bloquea_agenda = 1" . $rango, $par
        );

        foreach ($citas as $c) {
            self::crear(self::CANCELACION, (int) $c->id_cliente, (int) $c->id_cita,
                $c->profesional . ' no va a estar disponible el '
                . date('d/m/Y \a \l\a\s H:i', strtotime((string) $c->fecha_hora))
                . ($motivo !== '' ? ' (' . $motivo . ')' : '')
                . '. Podés reprogramar tu cita o elegir otro profesional.');
        }

        return count($citas);
    }

    /**
     * Le avisa a la clienta que su cita cambió de profesional.
     *
     * **Va a venir esperando a alguien y la atiende otra persona**, así que
     * enterarse en el sillón es la peor forma. El aviso sale por la misma cola
     * que los demás —`spg:notificaciones` la despacha— y por eso esto no
     * bloquea el cambio: si el correo falla, la cita ya está reasignada.
     *
     * Devuelve `false` cuando esa clienta no tiene correo cargado: no es un
     * error, es que no hay a dónde mandarlo, y la pantalla lo dice para que el
     * salón la llame.
     */
    public static function avisarCambioDeProfesional(int $idCita, string $nuevo, string $motivo): bool
    {
        $c = DB::selectOne(
            'SELECT c.id_cliente, c.fecha_hora, pe.email
               FROM cita c
               JOIN cliente cl ON cl.id_cliente = c.id_cliente
               JOIN persona pe ON pe.id_persona = cl.id_persona
              WHERE c.id_cita = ?', [$idCita]
        );
        if (! $c) {
            return false;
        }

        self::crear(self::CANCELACION, (int) $c->id_cliente, $idCita,
            'Tu cita del ' . date('d/m/Y \a \l\a\s H:i', strtotime((string) $c->fecha_hora))
            . ' la va a atender ' . $nuevo . '. El día y la hora no cambian.'
            . ' Motivo: ' . $motivo
            . ' Si preferís otro horario, podés cambiarlo desde «Mis citas».');

        return trim((string) ($c->email ?? '')) !== '';
    }

    /**
     * Tira el recordatorio de esa cita si todavía no salió.
     *
     * Lo llama `Agenda::reprogramar()`. `generarRecordatorios()` saltea toda
     * cita que **ya tenga** un aviso de tipo 1, así que una cita movida se
     * quedaba con el de la fecha anterior y no recibía ninguno de la nueva.
     *
     * **Sólo se borra lo que sigue PENDIENTE.** Un recordatorio ya enviado es
     * historia de lo que se mandó, y borrarlo no lo saca del buzón de nadie.
     */
    public static function descartarRecordatorioPendiente(int $idCita): int
    {
        return DB::delete(
            "DELETE FROM notificacion
              WHERE id_cita = ? AND id_tipo_notificacion = ? AND estado = 'PENDIENTE'",
            [$idCita, self::RECORDATORIO]
        );
    }

    /**
     * Recordatorios, con la anticipación que eligió cada clienta (1 día por
     * defecto). No se repite: se saltean las citas que ya tienen uno.
     */
    public static function generarRecordatorios(): int
    {
        $citas = DB::select(
            "SELECT c.id_cita, c.id_cliente, c.fecha_hora,
                    CONCAT(pe_u.nombre,' ',pe_u.apellido) AS profesional
               FROM cita c
               JOIN usuario u  ON u.id_usuario = c.id_usuario
               JOIN persona pe_u ON pe_u.id_persona = u.id_persona
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
               LEFT JOIN preferencia_recordatorio pr ON pr.id_cliente = c.id_cliente AND pr.activo = 1
              WHERE ec.bloquea_agenda = 1
                AND c.fecha_hora > NOW()
                AND c.fecha_hora <= DATE_ADD(NOW(), INTERVAL COALESCE(pr.dias_antes,1) DAY)
                AND NOT EXISTS (SELECT 1 FROM notificacion n
                                 WHERE n.id_cita = c.id_cita AND n.id_tipo_notificacion = 1)"
        );

        foreach ($citas as $c) {
            self::crear(self::RECORDATORIO, (int) $c->id_cliente, (int) $c->id_cita,
                'Te recordamos tu cita del ' . date('d/m/Y \a \l\a\s H:i', strtotime((string) $c->fecha_hora))
                . ' con ' . $c->profesional . '.');
        }

        return count($citas);
    }

    // -----------------------------------------------------------------
    //  Despacho de la cola
    // -----------------------------------------------------------------

    /** @return array{enviadas:int, fallidas:int, sin_correo:int, sin_destinatario:int} */
    public static function despachar(int $max = self::POR_PASADA): array
    {
        $res = ['enviadas' => 0, 'fallidas' => 0, 'sin_correo' => 0];

        // Se toman también las marcadas WHATSAPP: los procedimientos de la base
        // las crean con ese canal, y como todavía no hay integración de
        // WhatsApp quedarían en PENDIENTE para siempre. Se mandan por correo y
        // se corrige el canal, así el registro no miente.
        $pend = DB::select(
            "SELECT n.id_notificacion, n.id_tipo_notificacion, n.id_cita, n.mensaje,
                    pe_cl.email, CONCAT(pe_cl.nombre,' ',pe_cl.apellido) AS cliente
               FROM notificacion n
               LEFT JOIN cliente cl ON cl.id_cliente = n.id_cliente
               LEFT JOIN persona pe_cl ON pe_cl.id_persona = cl.id_persona
               JOIN tipo_notificacion tn ON tn.id_tipo_notificacion = n.id_tipo_notificacion
              WHERE n.estado = 'PENDIENTE' AND n.canal IN ('EMAIL','WHATSAPP')
                AND tn.destinatario = 'CLIENTE' AND n.id_cliente IS NOT NULL
              ORDER BY n.id_notificacion LIMIT " . max(1, $max)
        );

        foreach ($pend as $p) {
            $email = trim((string) ($p->email ?? ''));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Sin correo válido no se puede: se marca fallida y no se
                // reintenta para siempre.
                DB::update("UPDATE notificacion SET estado='FALLIDA', fecha_envio=NOW() WHERE id_notificacion=?",
                    [$p->id_notificacion]);
                $res['sin_correo']++;

                continue;
            }

            $url = $p->id_cita ? self::urlReprogramar(self::tokenDeCita((int) $p->id_cita)) : null;

            try {
                Mail::to($email)->send(new AvisoCita(
                    (int) $p->id_tipo_notificacion, (string) $p->mensaje, (string) $p->cliente, $url
                ));
                DB::update("UPDATE notificacion SET estado='ENVIADA', canal='EMAIL', fecha_envio=NOW()
                             WHERE id_notificacion=?", [$p->id_notificacion]);
                $res['enviadas']++;
            } catch (Throwable $e) {
                // Queda PENDIENTE a propósito: un fallo de red se reintenta solo
                report($e);
                $res['fallidas']++;
            }
        }

        // **Los avisos internos también se mandan** (7.29.0).
        //
        // Hasta acá el despachador tomaba sólo los de destinatario CLIENTE, así
        // que los de `destinatario = 'INTERNO'` —que un producto llegó al
        // mínimo, que se cerró una caja— no llegaban a nadie: el barrido de
        // abajo los cerraba como FALLIDA y ahí moría el asunto. En la
        // simulación de 60 días fueron **21 alertas de stock que no leyó
        // nadie**, con el salón comprando tarde.
        $res = array_merge($res, self::despacharInternos($max));

        // **Lo que este despachador no va a tomar nunca, se cierra** (NO-02).
        //
        // Queda para el aviso que de verdad no tiene a quién mandarse: uno de
        // cliente sin `id_cliente`, o uno interno sin un solo destinatario con
        // correo cargado. El que no cumple eso no se manda ni se marca: se
        // queda en PENDIENTE para siempre y la cola no se vacía nunca del todo
        // —quedó uno de 1.091 en la simulación de 90 días—.
        //
        // Marcarlo FALLIDA dice exactamente eso y saca el ruido de la cola,
        // que si no deja de servir para ver si algo anda mal. Se le da un día
        // de gracia por si el dato se completa después.
        $res['sin_destinatario'] = DB::update(
            "UPDATE notificacion n
                JOIN tipo_notificacion tn ON tn.id_tipo_notificacion = n.id_tipo_notificacion
               SET n.estado = 'FALLIDA', n.fecha_envio = NOW()
             WHERE n.estado = 'PENDIENTE'
               AND n.fecha_generacion < DATE_SUB(NOW(), INTERVAL 1 DAY)
               AND (tn.destinatario <> 'CLIENTE' OR n.id_cliente IS NULL)"
        );

        return $res;
    }

    /**
     * Quién recibe cada aviso interno.
     *
     * **Se resuelve por permiso, no por rol.** La clave es la del módulo que
     * hace falta para actuar sobre ese aviso: al que le avisan que falta
     * shampoo tiene que poder cargar stock, y al del cierre de caja, tocar la
     * caja. Hoy eso da exactamente el Administrador y el Asistente
     * administrativo, y si mañana el salón crea un rol nuevo con esa clave, le
     * llega solo — que es la razón por la que este proyecto nunca filtra con
     * `id_rol IN (…)`.
     */
    private const CLAVE_POR_TIPO = [
        5 => 'inventario.stock',      // Alerta de stock mínimo
        6 => 'facturacion.caja',      // Cierre de caja
    ];

    /**
     * A quién mandarle un aviso interno de este tipo: personal activo, con correo.
     *
     * **El Administrador entra por su rol y no por `rol_modulo`.** Esa tabla
     * no tiene ni una fila suya —su acceso lo resuelve `Permisos::esAdmin()`
     * comparando contra `permisos.rol_admin`—, así que una consulta que sólo
     * mire `rol_modulo` lo deja afuera justo a quien más le sirve el aviso.
     * Pasó al probarlo: la alerta de stock le llegaba a la recepcionista y no
     * a la dueña. El id sale de la configuración, no escrito a mano.
     */
    private static function destinatariosInternos(int $tipo): array
    {
        $clave = self::CLAVE_POR_TIPO[$tipo] ?? 'seguridad.usuarios';
        $modulo = explode('.', $clave)[0];
        $admin = (int) config('permisos.rol_admin', 1);

        // El módulo padre alcanza para todos sus submódulos: es la misma
        // jerarquía que resuelve Permisos::rolPuede().
        return DB::select(
            "SELECT DISTINCT pe.email, CONCAT(pe.nombre,' ',pe.apellido) AS nombre
               FROM usuario u
               JOIN persona pe ON pe.id_persona = u.id_persona
               JOIN rol r ON r.id_rol = u.id_rol
               LEFT JOIN rol_modulo rm ON rm.id_rol = u.id_rol AND rm.modulo IN (?, ?)
              WHERE u.activo = 1 AND r.es_personal = 1
                AND (rm.modulo IS NOT NULL OR u.id_rol = ?)
                AND pe.email IS NOT NULL AND pe.email <> ''",
            [$clave, $modulo, $admin]
        );
    }

    /**
     * Manda los avisos internos al equipo que puede actuar sobre ellos.
     *
     * @return array{internos_enviados:int, internos_sin_nadie:int}
     */
    private static function despacharInternos(int $max): array
    {
        $out = ['internos_enviados' => 0, 'internos_sin_nadie' => 0];

        $pend = DB::select(
            "SELECT n.id_notificacion, n.id_tipo_notificacion, n.mensaje
               FROM notificacion n
               JOIN tipo_notificacion tn ON tn.id_tipo_notificacion = n.id_tipo_notificacion
              WHERE n.estado = 'PENDIENTE' AND tn.destinatario = 'INTERNO'
              ORDER BY n.id_notificacion LIMIT " . max(1, $max)
        );
        if (! $pend) {
            return $out;
        }

        // Se resuelve una sola vez por tipo: son dos o tres tipos y muchas
        // filas, y la lista de destinatarios no cambia dentro de la pasada.
        $porTipo = [];

        foreach ($pend as $p) {
            $tipo = (int) $p->id_tipo_notificacion;
            $porTipo[$tipo] ??= self::destinatariosInternos($tipo);

            if (! $porTipo[$tipo]) {
                // Nadie con ese permiso tiene correo cargado: lo cierra el
                // barrido de NO-02, que para eso está.
                $out['internos_sin_nadie']++;

                continue;
            }

            $url = self::CLAVE_POR_TIPO[$tipo] === 'inventario.stock'
                ? self::base() . '/inventario/stock'
                : self::base() . '/panel';

            $mandados = 0;
            foreach ($porTipo[$tipo] as $d) {
                if (! filter_var((string) $d->email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                try {
                    Mail::to($d->email)->send(new AvisoInterno(
                        $tipo, (string) $p->mensaje, (string) $d->nombre, $url
                    ));
                    $mandados++;
                } catch (Throwable $e) {
                    // Uno que falla no arrastra a los demás; si no llegó a
                    // nadie, la fila queda PENDIENTE y se reintenta sola.
                    report($e);
                }
            }

            if ($mandados > 0) {
                DB::update("UPDATE notificacion SET estado='ENVIADA', canal='EMAIL', fecha_envio=NOW()
                             WHERE id_notificacion=?", [$p->id_notificacion]);
                $out['internos_enviados']++;
            }
        }

        return $out;
    }

    /**
     * Cierra los avisos internos de stock que ya no corresponden.
     *
     * El disparador crea uno cada vez que un producto cae bajo su mínimo, pero
     * el despacho sólo toma los del cliente: los internos quedaban en PENDIENTE
     * para siempre. No hace falta mandarlos a ningún lado —el aviso de
     * reposición se dibuja en vivo—, lo que hace falta es cerrarlos cuando el
     * producto se repuso.
     */
    /**
     * Las reservas que pedían seña y nadie confirmó a tiempo.
     *
     * **El horario se reserva desde el principio y eso es deliberado**: si la
     * cita no se creara hasta cobrar, la clienta perdería el lugar mientras
     * hace la transferencia — que es justo lo que la pantalla le promete.
     *
     * Pero un sillón bloqueado por alguien que nunca pagó tampoco puede quedar
     * así para siempre. Pasado `spg.agenda.sena_horas` sin confirmar, la cita
     * se cancela y se le avisa, con el motivo escrito: no desaparece en
     * silencio, que es lo que haría que la clienta se presentara igual.
     *
     * **No toca las citas de hoy ni las que ya pasaron.** Cancelar algo que es
     * dentro de dos horas no le da tiempo a nadie a reaccionar, y ahí el salón
     * decide por teléfono.
     */
    public static function cancelarSenasVencidas(): int
    {
        $horas = (int) config('spg.agenda.sena_horas', 24);
        if ($horas <= 0) {
            return 0;   // el salón apagó el plazo
        }

        $vencidas = DB::select(
            'SELECT c.id_cita, c.id_cliente, c.fecha_hora
               FROM cita c
              WHERE c.id_estado_cita IN (1, 2)
                AND DATE(c.fecha_hora) > CURDATE()
                AND fn_cita_sena_requerida(c.id_cita) > 0
                AND fn_cita_sena(c.id_cita) <= 0
                AND c.fecha_registro < DATE_SUB(NOW(), INTERVAL ? HOUR)
                -- Una solicitud pendiente es que la clienta YA avisó que pagó:
                -- ahí el salón tiene que confirmarla, no el sistema cancelarla.
                AND NOT EXISTS (SELECT 1 FROM sena_solicitud ss
                                 WHERE ss.id_cita = c.id_cita
                                   AND ss.id_cobro IS NULL AND ss.rechazada_en IS NULL)',
            [$horas]
        );

        $n = 0;
        foreach ($vencidas as $c) {
            try {
                Agenda::cancelar((int) $c->id_cita);
                self::crear(self::CANCELACION, (int) $c->id_cliente, (int) $c->id_cita,
                    'Soltamos tu reserva del ' . fecha($c->fecha_hora) . ' porque no llegamos a '
                    . 'confirmar la seña dentro de las ' . $horas . ' horas. '
                    . 'Podés volver a reservar cuando quieras.');
                Auditoria::registrarComo(1, 'CANCELACION', 'Sistema', 'cita', (int) $c->id_cita,
                    'Cancelada sola: pedía seña y no se confirmó en ' . $horas . ' horas');
                $n++;
            } catch (Throwable $e) {
                Log::error('No se pudo soltar la cita ' . $c->id_cita . ': ' . $e->getMessage());
            }
        }

        return $n;
    }

    public static function cerrarInternas(): int
    {
        try {
            return DB::update(
                "UPDATE notificacion n
                    SET n.estado = 'LEIDA', n.fecha_envio = NOW()
                  WHERE n.estado = 'PENDIENTE' AND n.canal = 'SISTEMA'
                    AND n.id_tipo_notificacion = 5
                    AND NOT EXISTS (SELECT 1 FROM vw_producto_bajo_stock b
                                     WHERE n.mensaje LIKE CONCAT('%', b.nombre, '%'))"
            );
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * El enlace que viaja en el correo.
     *
     * **Sale de `app.url`, NO del pedido que se está atendiendo**, y eso es lo
     * que arregla el defecto de los enlaces a `localhost`. `route()` arma la
     * dirección con la raíz de la petición en curso: sirve mientras el correo se
     * mande desde la web, pero acá se manda desde **dos** lados que no son lo
     * mismo:
     *
     *   · `spg:notificaciones`, que corre en el contenedor del planificador y
     *     **no tiene petición ninguna**. Ahí Laravel cae en `app.url` — bien si
     *     está cargada, y en `http://localhost` si no;
     *   · una acción de pantalla —dar de baja a alguien, cargar una ausencia—,
     *     donde la raíz es **el host que tipeó quien está usando el sistema**.
     *     Entrando por `localhost:8000` o por la IP de la red, el enlace sale con
     *     esa dirección y le llega así a la clienta, que no la puede abrir.
     *
     * La dirección del salón es una sola y está configurada. Que un correo
     * dependa de por dónde entró quien apretó el botón es un accidente, no una
     * función: por eso se arma con la base fija.
     */
    public static function urlReprogramar(string $codigo): string
    {
        // El tercer parámetro en `false` devuelve sólo la ruta, sin raíz: así
        // el nombre y los parámetros los sigue resolviendo Laravel —cambiar la
        // URL de la ruta no rompe esto— y la raíz la ponemos nosotros.
        return self::base() . route('cita.token', ['t' => $codigo], false);
    }

    /**
     * La dirección pública del salón, sin la barra final.
     *
     * Si `APP_URL` no está cargada, Laravel devuelve `http://localhost` y **el
     * correo sale con un enlace que no lleva a ningún lado**. No se puede
     * inventar la dirección buena, así que al menos queda registrado: es la
     * diferencia entre un correo roto que alguien reporta dentro de un mes y uno
     * que dejó una línea en el log el mismo día. `spg:diagnostico` lo comprueba
     * antes, que es donde de verdad hay que enterarse.
     */
    private static function base(): string
    {
        $url = rtrim((string) config('app.url'), '/');

        if ($url === '' || str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            Log::warning('SPG: APP_URL apunta a «' . ($url ?: 'nada')
                . '», así que los enlaces de los correos no van a servir fuera de esta máquina.');
        }

        return $url;
    }
}
