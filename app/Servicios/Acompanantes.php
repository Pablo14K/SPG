<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Quiénes vienen con la clienta.
 *
 * `cita.personas` decía CUÁNTAS venían y nada más, así que el salón sabía que
 * iban a llegar tres y no a quiénes esperar. Los nombres viven en
 * `cita_acompanante`, una fila por persona — **nunca una lista adentro de un
 * campo**, que es la falta a la 1FN que este proyecto ya evita con `turno_dia`.
 *
 * **La primera persona no está acá y es a propósito**: es la clienta que pidió
 * la cita, y ya está en `cita.id_cliente`. Guardarla otra vez sería el mismo
 * dato dos veces, que es lo que prohíbe la regla número dos. Por eso `orden`
 * arranca en 2: es el lugar que ocupa en el grupo.
 *
 * **Y cada uno declara sus alergias** (7.113.0), en `cita_acompanante.alergias`:
 * quien acompaña se atiende igual que quien reservó, y hasta entonces la cita
 * anotaba una sola alergia —la de la ficha de la titular—. Va al lado de su
 * nombre porque esta persona no tiene ficha donde dejarla. Ver `Alergias`.
 *
 * Está en un servicio y no en cada controlador porque lo escriben **dos**
 * pantallas —el portal y Nueva cita— y copiado se desfasan, que es un error que
 * este proyecto ya se hizo varias veces.
 */
class Acompanantes
{
    /**
     * Guarda los que vienen con la clienta. Se rehace la lista entera: lo que
     * el formulario ya no manda, deja de estar.
     *
     * **Y cada uno con sus alergias.** Quien acompaña se atiende igual que
     * quien reservó, así que la alergia que declara es tan importante como la
     * de ella — y hasta la 7.113.0 no tenía dónde ir: la cita entera anotaba
     * una sola, la de la ficha de la titular. Va acá, al lado de su nombre,
     * porque esta persona no tiene ficha: es un dato de esta visita.
     *
     * @param  array<int|string, mixed>  $nombres    acomp_nombre[orden]
     * @param  array<int|string, mixed>  $apellidos  acomp_apellido[orden]
     * @param  array<int|string, mixed>  $alergias   acomp_alergias[orden]
     */
    public static function guardar(int $idCita, array $nombres, array $apellidos, int $personas, array $alergias = []): void
    {
        try {
            DB::delete('DELETE FROM cita_acompanante WHERE id_cita = ?', [$idCita]);

            foreach ($nombres as $orden => $nombre) {
                $orden = (int) $orden;
                $nombre = trim((string) $nombre);

                // **Fuera del grupo declarado no entra nadie.** El número de
                // personas es lo que manda: si dice 3, hay lugar para el 2 y el
                // 3 y nada más. Sin esto, bajar el número dejaría colgados a los
                // que ya estaban cargados y la agenda mostraría más gente de la
                // que la clienta anunció.
                if ($orden < 2 || $orden > $personas || $orden > 20) {
                    continue;
                }
                // Un nombre de una letra no identifica a nadie, y el `CHECK` de
                // la base lo rechaza: se descarta acá para no reventar por algo
                // que la clienta simplemente dejó a medias.
                if (mb_strlen($nombre) < 2) {
                    continue;
                }

                $apellido = trim((string) ($apellidos[$orden] ?? ''));

                DB::insert(
                    'INSERT INTO cita_acompanante (id_cita, orden, nombre, apellido, alergias) VALUES (?,?,?,?,?)',
                    [$idCita, $orden, mb_substr($nombre, 0, 60),
                        $apellido !== '' ? mb_substr($apellido, 0, 60) : null,
                        Alergias::limpiar($alergias[$orden] ?? null)]
                );
            }
        } catch (Throwable $e) {
            // **No se cae la reserva por esto.** La cita ya está agendada y el
            // horario tomado; los nombres son un dato del pedido, no de la
            // disponibilidad. Queda en el log, que es donde hace falta.
            report($e);
        }
    }

    /**
     * Los que vienen con la clienta, por cita.
     *
     * Devuelve `[id_cita => [ {nombre, apellido, completo, alergias, id_cliente} ]]`. Se
     * pide para TODAS las citas de la pantalla de una vez: una consulta por
     * fila sería una por cada renglón de la agenda.
     *
     * **`id_cliente` viene resuelto y es lo que hace falta para el botón.**
     * Quien viene acompañando es una persona que el salón va a atender, y sin
     * ficha propia no hay dónde anotarle sus preferencias ni le queda
     * historial — el día que quiera abrir su cuenta, arranca de cero. Se busca
     * por nombre y apellido, el mismo criterio con el que la agenda resuelve
     * la ficha de «para otra persona».
     *
     * **No se le crea la ficha sola**, que es la regla de siempre: sería
     * inventar una persona que el salón no registró, y con un nombre a medias.
     * Lo que se hace es ofrecerla, con el nombre ya puesto.
     *
     * @param  array<int>  $idsCita
     * @return array<int, array<int, object>>
     */
    public static function deCitas(array $idsCita): array
    {
        $ids = array_values(array_filter(array_map('intval', $idsCita)));
        if (! $ids) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $filas = DB::select(
            "SELECT a.id_cita, a.orden, a.nombre, a.apellido, a.alergias,
                    (SELECT cl.id_cliente
                       FROM cliente cl
                       JOIN persona pe ON pe.id_persona = cl.id_persona
                      WHERE cl.activo = 1
                        AND LOWER(TRIM(CONCAT(pe.nombre, ' ', COALESCE(pe.apellido, ''))))
                            = LOWER(TRIM(CONCAT(a.nombre, ' ', COALESCE(a.apellido, ''))))
                      ORDER BY cl.id_cliente LIMIT 1) AS id_cliente
               FROM cita_acompanante a
              WHERE a.id_cita IN ($in) ORDER BY a.id_cita, a.orden", $ids
        );

        $out = [];
        foreach ($filas as $f) {
            $out[(int) $f->id_cita][] = (object) [
                // Su lugar en el grupo: es con lo que `cita_servicio.persona`
                // dice de quién es cada servicio.
                'orden' => (int) $f->orden,
                'nombre' => (string) $f->nombre,
                'apellido' => (string) ($f->apellido ?? ''),
                'completo' => trim($f->nombre . ' ' . (string) $f->apellido),
                'alergias' => $f->alergias !== null ? (string) $f->alergias : null,
                'id_cliente' => $f->id_cliente ? (int) $f->id_cliente : null,
            ];
        }

        return $out;
    }

    /**
     * Quién es cada número del grupo: `[1 => 'Ana García', 2 => 'Josefina …']`.
     *
     * El 1 es quien se atiende como titular —la clienta, o `nombre_para` si
     * la cita es para otra— y del 2 en adelante cada acompañante por su
     * `orden`. Un lugar sin nombre cargado se nombra por su número, que es lo
     * único que se sabe de él.
     *
     * Es lo que la agenda, el cobro y la factura usan para decir «de quién»
     * sin repetir la regla en tres lugares.
     *
     * @param  array<int,object>  $acompanantes  los de `deCitas()` para esa cita
     * @return array<int,string>
     */
    public static function nombres(object $cita, array $acompanantes = []): array
    {
        $titular = ! empty($cita->para_otra_persona)
            ? (trim((string) ($cita->nombre_para ?? '')) ?: 'Para quien es la cita')
            : (trim((string) ($cita->cliente ?? '')) ?: 'La clienta');

        $n = max(1, min(20, (int) ($cita->personas ?? 1)));
        $out = [1 => $titular];
        foreach ($acompanantes as $ac) {
            $o = (int) ($ac->orden ?? 0);
            if ($o >= 2 && $o <= 20) {
                $out[$o] = trim((string) ($ac->completo ?? '')) ?: ('Persona ' . $o);
            }
        }
        for ($i = 2; $i <= $n; $i++) {
            $out[$i] ??= 'Persona ' . $i;
        }
        ksort($out);

        return $out;
    }

    /**
     * Para quién es cada servicio, leído del formulario y acotado al grupo.
     *
     * `para[id_servicio]` viene del selector de cada tarjeta. Lo que falte o
     * no entre en 1..personas es de la titular: es el caso de la cita de una
     * sola persona, que no pregunta nada, y de un POST armado a mano.
     *
     * @param  array<int|string,mixed>  $para
     * @param  array<int>  $servicios
     * @return array<int,int>  [id_servicio => persona]
     */
    public static function personaDe(array $para, array $servicios, int $personas): array
    {
        $out = [];
        foreach ($servicios as $sid) {
            $p = (int) ($para[$sid] ?? 1);
            $out[(int) $sid] = ($p >= 1 && $p <= max(1, $personas)) ? $p : 1;
        }

        return $out;
    }

    /**
     * Qué falta para que el grupo esté completo, dicho para la pantalla.
     *
     * **El asistente no deja avanzar sin los nombres, y el servidor lo vuelve
     * a pedir.** `guardar()` descarta en silencio al que no tiene nombre —para
     * que una reserva ya agendada no se caiga por un dato del pedido— y con
     * eso «van 3» podía llegar a la agenda con una sola persona nombrada: el
     * salón sabía cuántas esperar y no a quiénes. Esto se pregunta ANTES de
     * tomar el horario, que es cuando todavía se puede corregir.
     *
     * @param  array<int|string, mixed>  $nombres  acomp_nombre[orden]
     * @return string|null  el aviso, o null si no falta nadie
     */
    public static function avisoFaltantes(array $nombres, int $personas): ?string
    {
        $faltan = [];
        for ($orden = 2; $orden <= min($personas, 20); $orden++) {
            if (mb_strlen(trim((string) ($nombres[$orden] ?? ''))) < 2) {
                $faltan[] = $orden;
            }
        }
        if (! $faltan) {
            return null;
        }
        if ($personas === 2) {
            return 'Dijiste que van 2 personas: falta el nombre de la que viene además de la clienta.';
        }
        $lista = count($faltan) === 1
            ? 'la persona ' . $faltan[0]
            : 'las personas ' . implode(', ', array_slice($faltan, 0, -1)) . ' y ' . end($faltan);

        return 'Dijiste que van ' . $personas . ' personas: falta el nombre de ' . $lista
            . '. Cada una lleva el suyo, así el salón sabe a quién espera.';
    }
    /**
     * La cuenta de una cita de varias, persona por persona.
     *
     * Es lo que hace posible que un grupo pague **junto o por separado**, que
     * fue el pedido: «si es individual debe mostrar el monto del servicio para
     * hacer una factura para cada persona; si no, el monto total». Para cada
     * número del grupo devuelve:
     *
     *   nombre      quién es (ver `nombres()`)
     *   servicios   los nombres de lo suyo
     *   lista       la suma de sus precios de lista, sin lo canjeado
     *   total       su parte del total de la cita CON el descuento
     *   cobrado     lo que ya se cobró a su nombre (`cobro.persona`)
     *   falta       total − cobrado, nunca negativo
     *   id_factura  su comprobante, si ya se le emitió (`factura.persona`)
     *   nro, saldo  de ese comprobante
     *
     * **El descuento se reparte proporcional al peso de cada una**, y la
     * última absorbe el redondeo para que las partes sumen exactamente
     * `fn_cita_total`: es el mismo criterio con el que el TXT del SIFEN
     * reparte el descuento entre los renglones. El comprobante de cada una lo
     * calcula después la base sobre SU detalle, así que con un porcentaje
     * coincide al guaraní; con una promoción de monto fijo puede diferir en
     * poco, y ahí manda el comprobante.
     *
     * Además trae, bajo la clave 0, lo que se cobró **al grupo entero**
     * (`cobro.persona` NULL): existe cuando alguien cobró junto antes de
     * decidir separar, y se muestra para que no se pierda de vista.
     *
     * @param  array<int,object>  $acompanantes  los de `deCitas()` para esa cita
     * @return array<int,array<string,mixed>>
     */
    public static function cuenta(object $cita, array $acompanantes = []): array
    {
        $idCita = (int) $cita->id_cita;
        $nombres = self::nombres($cita, $acompanantes);

        $out = [];
        foreach ($nombres as $p => $n) {
            $out[$p] = ['nombre' => $n, 'servicios' => [], 'lista' => 0.0, 'total' => 0.0,
                        'cobrado' => 0.0, 'falta' => 0.0, 'id_factura' => null, 'nro' => null, 'saldo' => 0.0];
        }

        $filas = DB::select(
            'SELECT cs.persona, s.nombre, s.precio,
                    EXISTS (SELECT 1 FROM canje cj
                             WHERE cj.id_cita = cs.id_cita AND cj.id_servicio = cs.id_servicio) AS canjeado
               FROM cita_servicio cs
               JOIN servicio s ON s.id_servicio = cs.id_servicio
              WHERE cs.id_cita = ?
              ORDER BY cs.persona, s.nombre', [$idCita]
        );
        $neto = 0.0;
        foreach ($filas as $r) {
            $p = (int) $r->persona;
            if (! isset($out[$p])) {
                $out[$p] = ['nombre' => 'Persona ' . $p, 'servicios' => [], 'lista' => 0.0, 'total' => 0.0,
                            'cobrado' => 0.0, 'falta' => 0.0, 'id_factura' => null, 'nro' => null, 'saldo' => 0.0];
            }
            $out[$p]['servicios'][] = (string) $r->nombre;
            if (! (int) $r->canjeado) {
                $out[$p]['lista'] += (float) $r->precio;
                $neto += (float) $r->precio;
            }
        }

        // La parte de cada una, con la última absorbiendo el redondeo.
        $totalCita = (float) DB::scalar('SELECT fn_cita_total(?)', [$idCita]);
        $conServicios = array_keys(array_filter($out, static fn ($x) => $x['lista'] > 0));
        $acumulado = 0.0;
        foreach ($conServicios as $i => $p) {
            $parte = $neto > 0
                ? ($i === count($conServicios) - 1
                    ? $totalCita - $acumulado
                    : round($out[$p]['lista'] * $totalCita / $neto))
                : 0.0;
            $out[$p]['total'] = max(0.0, $parte);
            $acumulado += $out[$p]['total'];
        }

        foreach (DB::select(
            'SELECT persona, COALESCE(SUM(monto), 0) AS monto
               FROM cobro
              WHERE id_cita = ? AND id_estado_cobro = 1 AND id_factura IS NULL
              GROUP BY persona', [$idCita]) as $r) {
            $p = (int) ($r->persona ?? 0);
            if ($p === 0) {
                $out[0] = ['nombre' => 'Todo el grupo', 'servicios' => [], 'lista' => 0.0, 'total' => 0.0,
                           'cobrado' => (float) $r->monto, 'falta' => 0.0, 'id_factura' => null, 'nro' => null, 'saldo' => 0.0];
            } elseif (isset($out[$p])) {
                $out[$p]['cobrado'] = (float) $r->monto;
            }
        }

        foreach (DB::select(
            'SELECT persona, id_factura, fn_factura_nro(id_factura) AS nro, fn_factura_saldo(id_factura) AS saldo
               FROM factura
              WHERE id_cita = ? AND id_estado_factura = 1 AND persona IS NOT NULL', [$idCita]) as $r) {
            $p = (int) $r->persona;
            if (isset($out[$p])) {
                $out[$p]['id_factura'] = (int) $r->id_factura;
                $out[$p]['nro'] = (string) $r->nro;
                $out[$p]['saldo'] = (float) $r->saldo;
            }
        }

        foreach ($out as $p => &$x) {
            if ($p > 0) {
                $x['falta'] = max(0.0, $x['total'] - $x['cobrado']);
            }
        }
        unset($x);
        ksort($out);

        return $out;
    }
}
