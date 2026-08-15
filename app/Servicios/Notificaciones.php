<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Mail\AvisoCita;
use Illuminate\Support\Facades\DB;
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

        // **Lo que este despachador no va a tomar nunca, se cierra** (NO-02).
        //
        // La consulta de arriba sólo toma los avisos de destinatario CLIENTE
        // con `id_cliente` cargado. El que no cumple eso no se manda ni se
        // marca: se queda en PENDIENTE para siempre y la cola no se vacía nunca
        // del todo —quedó uno de 1.091 en la simulación de 90 días—.
        //
        // No es que se pierda un aviso: es que **ese aviso no tiene a quién
        // mandárselo**. Marcarlo FALLIDA dice exactamente eso y saca el ruido
        // de la cola, que si no deja de servir para ver si algo anda mal.
        // Se le da un día de gracia por si el dato se completa después.
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
     * Cierra los avisos internos de stock que ya no corresponden.
     *
     * El disparador crea uno cada vez que un producto cae bajo su mínimo, pero
     * el despacho sólo toma los del cliente: los internos quedaban en PENDIENTE
     * para siempre. No hace falta mandarlos a ningún lado —el aviso de
     * reposición se dibuja en vivo—, lo que hace falta es cerrarlos cuando el
     * producto se repuso.
     */
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

    public static function urlReprogramar(string $codigo): string
    {
        return route('cita.token', ['t' => $codigo]);
    }
}
