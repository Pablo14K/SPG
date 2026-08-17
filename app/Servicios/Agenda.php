<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * Motor de disponibilidad de la agenda.
 *
 * Arma los huecos reales de un profesional cruzando su turno laboral con sus
 * ausencias y sus citas ya tomadas.
 *
 * IMPORTANTE — quién decide qué:
 *
 *  · Para **pintar** la pantalla, los huecos se calculan acá, en memoria.
 *    Antes se le preguntaba a `fn_verificar_disponibilidad` hueco por hueco:
 *    el calendario de 60 días daba unas 12.000 consultas y tardaba 38 segundos
 *    (medido), y bajo concurrencia cortaba peticiones por timeout. Trayendo
 *    turnos, citas y ausencias en tres consultas, tarda 0,11 s.
 *
 *  · Para **guardar**, la autoridad sigue siendo `fn_verificar_disponibilidad`,
 *    que se consulta de nuevo dentro del candado del procedimiento.
 *
 * Es la única parte del sistema donde PHP replica una regla de la base, y se
 * hace a propósito por costo. **Si cambian las reglas de disponibilidad en la
 * base, hay que reflejarlas en slotsProfesional()**, o la pantalla va a
 * ofrecer horarios que el servidor después rechaza.
 */
class Agenda
{
    /** ¿Alguien en el salón tiene turnos cargados? (una vez por petición) */
    private static ?bool $salonConTurnos = null;

    /**
     * ¿El salón usa la agenda de turnos?
     *
     * Es la pregunta que decide el criterio permisivo, y se hace **del salón,
     * no de cada persona**. Ver la corrección de AG-01 en `datosProfesional()`.
     * Se consulta una vez por petición porque el calendario de 60 días la
     * necesita por profesional y por día.
     */
    public static function elSalonUsaTurnos(): bool
    {
        return self::$salonConTurnos ??= (bool) DB::scalar(
            'SELECT EXISTS (SELECT 1
                              FROM usuario_turno ut
                              JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1)'
        );
    }

    /**
     * Quién atiende clientes.
     *
     * **No es «todo el personal», y ésa era la mitad visible de AG-01.** Con
     * `es_personal = 1` a secas entraban la propietaria y la recepcionista, que
     * no atienden a nadie: la agenda las ofrecía, la clienta reservaba con
     * ellas y esa cita no la podía dar el salón. Fueron 302 de 557.
     *
     * Atiende quien tiene un turno cargado. Si el salón todavía no usa turnos
     * —nadie tiene ninguno— vale el criterio permisivo de siempre y se
     * devuelve a todo el personal, que si no la agenda quedaría vacía el
     * primer día y no se podría agendar nada.
     */
    public static function profesionales(?int $idSucursal = null): array
    {
        $soloConTurno = self::elSalonUsaTurnos()
            ? 'AND EXISTS (SELECT 1
                             FROM usuario_turno ut
                             JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
                            WHERE ut.id_usuario = u.id_usuario)'
            : '';

        // Quién atiende en ESTE local. Sin el filtro, la clienta del portal
        // podía pedir a alguien que trabaja en la otra punta de la ciudad, y
        // la agenda del panel ofrecía gente que ese día no está en el local.
        // Se mira `usuario_sucursal` —la asignación real— y no la ficha, que
        // dice sólo dónde trabaja habitualmente.
        $idSucursal ??= Sucursales::activa();
        $par = [];
        $deEsteLocal = '';
        if ($idSucursal) {
            $deEsteLocal = 'AND EXISTS (SELECT 1 FROM usuario_sucursal us
                                         WHERE us.id_usuario = u.id_usuario AND us.id_sucursal = ?)';
            $par[] = $idSucursal;
        }

        return DB::select(
            "SELECT u.id_usuario, CONCAT(pe_u.nombre,' ',pe_u.apellido) AS nombre
               FROM usuario u
               JOIN persona pe_u ON pe_u.id_persona = u.id_persona
               JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.activo = 1 AND r.es_personal = 1 $soloConTurno $deEsteLocal
              ORDER BY pe_u.nombre, pe_u.apellido", $par
        );
    }

    /** Duración total, en minutos, de una lista de servicios. */
    public static function duracion(array $idsServicio): int
    {
        $ids = array_values(array_filter(array_map('intval', $idsServicio)));
        if (! $ids) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));

        return (int) DB::scalar(
            "SELECT COALESCE(SUM(duracion_min),0) FROM servicio WHERE activo=1 AND id_servicio IN ($in)",
            $ids
        );
    }

    /**
     * Toda la agenda de un profesional en un rango, en tres consultas.
     *
     * `dia` va de 1 (lunes) a 7 (domingo), que es lo que dan date('N') en PHP y
     * WEEKDAY()+1 en la base. NO es el DAYOFWEEK() de MySQL, que arranca en
     * domingo: si se mezclan, la agenda se corre un día.
     */
    public static function datosProfesional(int $idUsuario, string $desde, string $hasta): array
    {
        $turnos = [];
        foreach (DB::select(
            'SELECT td.dia_semana AS dia, t.hora_inicio, t.hora_fin
               FROM usuario_turno ut
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
               JOIN turno_dia td    ON td.id_turno = t.id_turno
              WHERE ut.id_usuario = ?
              ORDER BY td.dia_semana, t.hora_inicio',
            [$idUsuario]
        ) as $t) {
            $turnos[(int) $t->dia][] = [$t->hora_inicio, $t->hora_fin];
        }

        // **El criterio permisivo es del SALÓN, no de cada persona**, y es la
        // corrección de AG-01: mientras se resolvía persona por persona, quien
        // no tenía turno cargado quedaba libre las 24 horas de los 7 días.
        // Así la propietaria y la recepcionista se llevaron 302 de 557 citas
        // —76 en domingo, con el salón cerrado— y ninguna se pudo atender.
        //
        // La intención de la regla sigue valiendo: si el salón todavía no usa
        // la agenda de turnos, no se le bloquea nada a nadie. Lo que cambia es
        // quién decide eso: si ALGUIEN tiene turnos, el salón usa turnos, y
        // quien no los tenga no atiende.
        //
        // Tiene que decir lo mismo que fn_verificar_disponibilidad: la base es
        // la autoridad al guardar, y esto sólo dibuja la pantalla.
        $usaTurnos = $turnos !== [] || self::elSalonUsaTurnos();

        // Citas que le ocupan la agenda: las suyas y aquellas en las que solo
        // hace algunos servicios. Se mide con SU bloque (fn_cita_duracion_de),
        // no con la cita entera: si la clienta está 90 minutos pero él solo
        // hace el lavado de 20, a los 20 queda libre.
        $ocupado = [];
        foreach (DB::select(
            // **Y desde cuándo**, que no siempre es la hora de la cita: cuando
            // hay servicios que ocupan a la clienta entera, los profesionales
            // se turnan y el segundo arranca cuando el primero termina
            // (`fn_cita_inicio_de`). Sin eso quedaría bloqueado desde el
            // principio —cuando en realidad está libre— y libre al final,
            // que es cuando de verdad está atendiendo acá.
            'SELECT c.fecha_hora, fn_cita_inicio_de(c.id_cita, :u0) AS ini,
                    fn_cita_duracion_de(c.id_cita, :u) AS dur
               FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1
                AND (c.id_usuario = :u2
                     OR EXISTS (SELECT 1 FROM cita_servicio cs
                                 WHERE cs.id_cita = c.id_cita AND cs.id_usuario = :u3))
                AND c.fecha_hora >= :d AND c.fecha_hora < DATE_ADD(:h, INTERVAL 1 DAY)',
            ['u0' => $idUsuario, 'u' => $idUsuario, 'u2' => $idUsuario, 'u3' => $idUsuario,
             'd' => $desde . ' 00:00:00', 'h' => $hasta]
        ) as $c) {
            $dur = (int) $c->dur;
            if ($dur <= 0) {
                continue;   // no hace nada en esa cita
            }
            $ini = strtotime((string) $c->fecha_hora) + (int) $c->ini * 60;
            $ocupado[] = [$ini, $ini + $dur * 60];
        }

        foreach (DB::select(
            'SELECT fecha_inicio, fecha_fin FROM ausencia_agenda
              WHERE activo=1 AND (id_usuario=? OR id_usuario IS NULL)
                AND fecha_fin >= ? AND fecha_inicio < DATE_ADD(?, INTERVAL 1 DAY)',
            [$idUsuario, $desde . ' 00:00:00', $hasta]
        ) as $a) {
            $ocupado[] = [strtotime((string) $a->fecha_inicio), strtotime((string) $a->fecha_fin)];
        }

        return ['turnos' => $turnos, 'ocupado' => $ocupado, 'usaTurnos' => $usaTurnos];
    }

    /**
     * Huecos libres de un profesional en un día, para una duración dada.
     * Devuelve ['09:00', '09:15', …]: las horas en que la cita ENTERA entra.
     */
    public static function slotsProfesional(int $idUsuario, string $fecha, int $duracion, ?array $datos = null): array
    {
        if ($duracion <= 0) {
            return [];
        }
        $datos ??= self::datosProfesional($idUsuario, $fecha, $fecha);

        $turnos = $datos['turnos'][(int) date('N', strtotime($fecha))] ?? [];
        if (! $turnos) {
            // Sin turno ese día no atiende. Salvo que no use turnos en
            // absoluto: ahí se ofrece la jornada por defecto, como la base.
            if ($datos['usaTurnos']) {
                return [];
            }
            $turnos = [['08:00:00', '20:00:00']];
        }

        $paso = (int) config('spg.agenda.paso_min', 15);
        $libres = [];
        $ahora = time();

        foreach ($turnos as [$hIni, $hFin]) {
            $ini = strtotime($fecha . ' ' . $hIni);
            $fin = strtotime($fecha . ' ' . $hFin);
            for ($m = $ini; $m + $duracion * 60 <= $fin; $m += $paso * 60) {
                if ($m <= $ahora) {
                    continue;   // no se ofrece un horario que ya pasó
                }
                $hasta = $m + $duracion * 60;
                $choca = false;
                foreach ($datos['ocupado'] as [$oIni, $oFin]) {
                    if ($oIni < $hasta && $m < $oFin) {
                        $choca = true;
                        break;
                    }
                }
                if (! $choca) {
                    $libres[] = date('H:i', $m);
                }
            }
        }

        return $libres;
    }

    /**
     * Huecos del día. Con $idUsuario en null junta los de todo el equipo: el
     * cliente que no tiene profesional de preferencia ve todos los horarios y
     * el sistema le asigna a quien esté libre.
     */
    public static function slots(?int $idUsuario, string $fecha, int $duracion, ?array $cache = null): array
    {
        $profs = $idUsuario ? [(object) ['id_usuario' => $idUsuario]] : self::profesionales();
        $porHora = [];
        foreach ($profs as $p) {
            $idp = (int) $p->id_usuario;
            foreach (self::slotsProfesional($idp, $fecha, $duracion, $cache[$idp] ?? null) as $h) {
                $porHora[$h][] = $idp;
            }
        }
        ksort($porHora);

        $out = [];
        foreach ($porHora as $hora => $ids) {
            $out[] = ['hora' => $hora, 'profesionales' => $ids];
        }

        return $out;
    }

    /**
     * Días con al menos un hueco. Es lo que pinta el calendario: los días sin
     * cupo ni se ofrecen.
     */
    public static function diasConCupo(?int $idUsuario, string $desde, int $dias, int $duracion): array
    {
        if ($duracion <= 0) {
            return [];
        }
        $dias = max(1, min($dias, (int) config('spg.agenda.dias_vista', 60)));
        $d = strtotime($desde);
        $hasta = date('Y-m-d', strtotime('+' . ($dias - 1) . ' day', $d));

        // Toda la agenda del rango de una sola vez: tres consultas por
        // profesional en lugar de una por cada hueco candidato.
        $profs = $idUsuario ? [(object) ['id_usuario' => $idUsuario]] : self::profesionales();
        $cache = [];
        foreach ($profs as $p) {
            $cache[(int) $p->id_usuario] = self::datosProfesional((int) $p->id_usuario, $desde, $hasta);
        }

        $out = [];
        for ($i = 0; $i < $dias; $i++) {
            $fecha = date('Y-m-d', strtotime("+$i day", $d));
            if (self::slots($idUsuario, $fecha, $duracion, $cache)) {
                $out[] = $fecha;
            }
        }

        return $out;
    }

    /**
     * ¿Ese horario exacto sigue libre? Se vuelve a preguntar al guardar:
     * entre que se dibujó la pantalla y se apretó el botón pudo tomarlo otro.
     * Acá SÍ decide la base.
     */
    public static function huecoLibre(int $idUsuario, string $fechaHora, int $duracion, ?int $excluirCita = null): bool
    {
        return (bool) (int) Bd::funcion(
            'fn_verificar_disponibilidad(?,?,?,?)',
            [$idUsuario, $fechaHora, $duracion, $excluirCita]
        );
    }

    /**
     * ¿Por qué se perdió el hueco?
     *
     * Cuando alguien elige un horario que la pantalla mostraba libre y al
     * guardar ya no lo está, no alcanza con decir «no disponible»: la persona
     * necesita saber si se lo ganó otro —y entonces cambia de hora— o si el
     * profesional directamente no atiende —y entonces cambia de profesional—.
     */
    public static function motivoHuecoPerdido(int $idUsuario, string $fechaHora, int $duracion, ?int $excluirCita = null): string
    {
        $nombre = (string) DB::scalar(
            "SELECT CONCAT(pe.nombre,' ',pe.apellido) FROM usuario u
               JOIN persona pe ON pe.id_persona = u.id_persona WHERE u.id_usuario=?",
            [$idUsuario]
        );

        // 1) ¿Otra cita ocupó el lugar? Es la carrera entre dos personas.
        $choque = DB::selectOne(
            'SELECT c.fecha_hora
               FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE c.id_usuario = :u AND ec.bloquea_agenda = 1
                AND (:x IS NULL OR c.id_cita <> :x2)
                AND c.fecha_hora < DATE_ADD(:f, INTERVAL :d MINUTE)
                AND :f2 < DATE_ADD(c.fecha_hora, INTERVAL fn_cita_duracion(c.id_cita) MINUTE)
              LIMIT 1',
            ['u' => $idUsuario, 'x' => $excluirCita, 'x2' => $excluirCita,
             'f' => $fechaHora, 'd' => $duracion, 'f2' => $fechaHora]
        );
        if ($choque) {
            return 'Ese horario lo tomó otra persona mientras completabas la reserva. '
                . $nombre . ' ya tiene una cita a las ' . fecha($choque->fecha_hora, 'H:i')
                . '. Elegí otro de los horarios que quedan libres.';
        }

        // 2) ¿Se cargó una ausencia? (licencia, feriado, llegada tardía)
        $aus = DB::selectOne(
            'SELECT a.motivo, ta.nombre AS tipo
               FROM ausencia_agenda a JOIN tipo_ausencia ta ON ta.id_tipo_ausencia = a.id_tipo_ausencia
              WHERE a.activo = 1 AND (a.id_usuario = :u OR a.id_usuario IS NULL)
                AND a.fecha_inicio < DATE_ADD(:f, INTERVAL :d MINUTE)
                AND :f2 < a.fecha_fin LIMIT 1',
            ['u' => $idUsuario, 'f' => $fechaHora, 'd' => $duracion, 'f2' => $fechaHora]
        );
        if ($aus) {
            return $nombre . ' no va a estar en ese horario (' . mb_strtolower((string) ($aus->motivo ?: $aus->tipo)) . '). '
                . 'Elegí otra fecha o pedí que te atienda otro profesional.';
        }

        // 3) Queda el turno laboral
        return $nombre . ' no atiende en ese horario. Elegí uno de los horarios que se muestran disponibles.';
    }

    // -----------------------------------------------------------------
    //  Varios profesionales en una misma cita
    //
    //  Una clienta puede pedir lavado y pedicura a la vez: dos personas
    //  trabajando en partes distintas, las dos empezando a la hora de la
    //  cita. Pero coloración y keratina no se pueden repartir así — las dos
    //  necesitan la cabeza, así que una espera a la otra.
    // -----------------------------------------------------------------

    /** Duración de cada bloque: [id_usuario => minutos]. */
    public static function bloques(array $asignacion, int $idPrincipal): array
    {
        $ids = array_values(array_filter(array_map('intval', array_keys($asignacion))));
        if (! $ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $dur = [];
        foreach (DB::select("SELECT id_servicio, duracion_min FROM servicio WHERE activo=1 AND id_servicio IN ($in)", $ids) as $s) {
            $dur[(int) $s->id_servicio] = (int) $s->duracion_min;
        }

        $bloques = [];
        foreach ($asignacion as $idServicio => $idProf) {
            $idProf = (int) $idProf ?: $idPrincipal;
            $bloques[$idProf] = ($bloques[$idProf] ?? 0) + ($dur[(int) $idServicio] ?? 0);
        }

        return $bloques;
    }

    /**
     * ¿Se puede armar esta cita? Devuelve el mensaje del problema, o null.
     * $asignacion es [id_servicio => id_usuario] (0 = el principal).
     */
    public static function validarReparto(array $asignacion, int $idPrincipal, string $fechaHora, ?int $excluirCita = null): ?string
    {
        $ids = array_values(array_filter(array_map('intval', array_keys($asignacion))));
        if (! $ids) {
            return 'Elegí al menos un servicio.';
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $servicios = DB::select(
            "SELECT id_servicio, nombre, duracion_min, requiere_exclusividad
               FROM servicio WHERE activo=1 AND id_servicio IN ($in)",
            $ids
        );
        if (count($servicios) !== count($ids)) {
            return 'Alguno de los servicios elegidos ya no está disponible.';
        }

        // --- Cada profesional tiene que estar libre por su bloque completo ---
        //
        // **Y desde su propio turno.** Lo que ocupa a la clienta entera se hace
        // por turnos, así que el segundo profesional no empieza a la hora de la
        // cita: empieza cuando el primero terminó. Comprobarlo desde la hora de
        // la cita lo daría por ocupado cuando está libre, y —peor— lo dejaría
        // libre para otra clienta justo en la franja en la que va a estar acá.
        foreach (self::turnos($asignacion, $idPrincipal) as $idProf => $t) {
            if ($t['minutos'] <= 0) {
                continue;
            }
            $arranca = $t['inicio']
                ? date('Y-m-d H:i:s', strtotime($fechaHora) + $t['inicio'] * 60)
                : $fechaHora;

            if (! self::huecoLibre($idProf, $arranca, $t['minutos'], $excluirCita)) {
                return self::motivoHuecoPerdido($idProf, $arranca, $t['minutos'], $excluirCita);
            }
        }

        return null;
    }

    /**
     * Quién trabaja cuándo dentro de la cita: `[id_usuario => [inicio, minutos]]`.
     *
     * **Los servicios que ocupan a la clienta entera se hacen por turnos, no a
     * la vez, y eso ahora se puede representar.** Antes el modelo daba por hecho
     * que todos los profesionales de una cita trabajan en paralelo, así que dos
     * servicios exclusivos en manos distintas se pisaban sobre la clienta y
     * `validarReparto()` los rechazaba: la única salida que ofrecía era ponerlos
     * con la misma persona. Eso no dejaba reservar una coloración con una y un
     * corte con otra, que en el salón se hace todos los días — primero una,
     * después la otra.
     *
     * El reparto en turnos es **por profesional**, no por servicio: si alguien
     * hace algo exclusivo, ocupa a la clienta hasta que termina todo lo suyo.
     * Quien no hace nada exclusivo va en el turno 0, en paralelo con el resto —
     * el lavado y la pedicura conviven sin problema.
     *
     * El orden entre los que sí lo hacen es **de mayor a menor bloque**, y no es
     * capricho: el primer turno es el único que puede solaparse con los no
     * exclusivos, así que poniendo adelante al más largo se aprovecha esa
     * franja y la cita entera termina antes.
     */
    public static function turnos(array $asignacion, int $idPrincipal): array
    {
        $bloques = self::bloques($asignacion, $idPrincipal);
        if (! $bloques) {
            return [];
        }

        // Qué profesionales ocupan a la clienta entera.
        $ids = array_values(array_filter(array_map('intval', array_keys($asignacion))));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $exclusivos = [];
        foreach (DB::select("SELECT id_servicio FROM servicio
                              WHERE requiere_exclusividad = 1 AND id_servicio IN ($in)", $ids) as $s) {
            $prof = (int) ($asignacion[(int) $s->id_servicio] ?? 0) ?: $idPrincipal;
            $exclusivos[$prof] = true;
        }

        $out = [];
        foreach ($bloques as $prof => $minutos) {
            $out[$prof] = ['inicio' => 0, 'minutos' => $minutos, 'orden' => 0];
        }

        // Con uno solo —o ninguno— no hay nada que secuenciar: es el caso de
        // siempre y todo arranca a la hora de la cita.
        if (count($exclusivos) < 2) {
            return $out;
        }

        $enTurnos = array_intersect_key($bloques, $exclusivos);
        arsort($enTurnos);   // el bloque más largo primero

        $acumulado = 0;
        $orden = 0;
        foreach ($enTurnos as $prof => $minutos) {
            $out[$prof] = ['inicio' => $acumulado, 'minutos' => $minutos, 'orden' => $orden];
            $acumulado += $minutos;
            $orden++;
        }

        return $out;
    }

    /**
     * Cuánto dura la cita entera.
     *
     * En paralelo es el bloque más largo —color de 45 min + uñas de 30 a la vez
     * son 45 minutos de cita, no 75—; por turnos, hasta que termina el último.
     * Es la misma cuenta que hace `fn_cita_duracion` en la base.
     */
    public static function duracionReparto(array $asignacion, int $idPrincipal): int
    {
        $fin = 0;
        foreach (self::turnos($asignacion, $idPrincipal) as $t) {
            $fin = max($fin, $t['inicio'] + $t['minutos']);
        }

        return $fin;
    }

    /**
     * A quién se le da la cita cuando la clienta no eligió profesional.
     *
     * **No es «el primero de la lista», y ése era el problema.** Antes se
     * recorría `profesionales()` —que viene `ORDER BY nombre`— y se devolvía el
     * primero libre, así que la cita caía SIEMPRE en la misma persona: la
     * propietaria, porque «Ana» es el primer nombre del alfabeto.
     *
     * Y se agravaba con la otra mitad: la propietaria **no tiene turno
     * asignado**, y `fn_verificar_disponibilidad` es permisiva con quien no lo
     * tiene —entiende que el salón todavía no usa la agenda de turnos—, así que
     * la daba por libre las 24 horas, incluido un domingo a las 3 de la mañana.
     * Entre las dos cosas, la dueña se llevaba todas las citas sin preferencia.
     *
     * Ahora se elige en dos pasos:
     *
     *  1. **Tener turno gana.** Entre los que están libres, se prefiere a quien
     *     tiene un turno cargado, porque de ese sí se sabe que atiende a esa
     *     hora. Si ninguno de los libres tiene turno, se toma igual al primero
     *     —el salón no está usando turnos y no hay con qué distinguir.
     *  2. **Entre esos, la que menos tiene ese día**, así el trabajo se reparte
     *     en vez de amontonarse en la primera del alfabeto. A igualdad, decide
     *     el nombre, para que el resultado sea siempre el mismo.
     */
    public static function profesionalLibre(string $fechaHora, int $duracion): ?int
    {
        $conTurno = self::losQueTienenTurno();
        $dia = substr($fechaHora, 0, 10);
        $carga = self::citasDelDia($dia);

        $libres = [];
        foreach (self::profesionales() as $orden => $p) {
            $id = (int) $p->id_usuario;
            if (self::huecoLibre($id, $fechaHora, $duracion)) {
                $libres[] = [
                    'id' => $id,
                    'turno' => isset($conTurno[$id]) ? 0 : 1,   // 0 ordena primero
                    'carga' => $carga[$id] ?? 0,
                    'orden' => $orden,
                ];
            }
        }
        if (! $libres) {
            return null;
        }

        // Si NINGUNO de los libres tiene turno, el criterio del turno no
        // distingue nada y se cae solo: todos empatan en 1.
        usort($libres, fn ($a, $b) => [$a['turno'], $a['carga'], $a['orden']]
                                  <=> [$b['turno'], $b['carga'], $b['orden']]);

        return $libres[0]['id'];
    }

    /** Ids del personal que tiene al menos un turno activo asignado. */
    private static function losQueTienenTurno(): array
    {
        $out = [];
        foreach (DB::select(
            'SELECT DISTINCT ut.id_usuario
               FROM usuario_turno ut
               JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1'
        ) as $r) {
            $out[(int) $r->id_usuario] = true;
        }

        return $out;
    }

    /** Cuántas citas tiene ya cada profesional ese día, para repartir. */
    private static function citasDelDia(string $dia): array
    {
        $out = [];
        foreach (DB::select(
            'SELECT c.id_usuario, COUNT(*) AS n
               FROM cita c
               JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
              WHERE ec.bloquea_agenda = 1 AND DATE(c.fecha_hora) = ?
              GROUP BY c.id_usuario', [$dia]
        ) as $r) {
            $out[(int) $r->id_usuario] = (int) $r->n;
        }

        return $out;
    }

    /**
     * A nombre de quién queda la cita cuando TODOS los servicios se repartieron.
     *
     * Es el caso que quedaba mal: la clienta elige quién le hace cada cosa y no
     * pide principal, así que al principal no le queda ningún servicio. Buscar
     * entonces a alguien «libre» metía en la cita a una persona que no atiende
     * nada ahí —la propietaria, casi siempre—, y esa cita le aparecía en su
     * agenda y le contaba como carga del día.
     *
     * La cita queda a nombre de quien más minutos pone, que es quien de verdad
     * la sostiene. A igualdad de minutos gana el id más chico, para que el
     * resultado sea siempre el mismo y no dependa del orden del formulario.
     *
     * @param  array  $asignacion  [id_servicio => id_usuario] (0 = el principal)
     */
    public static function principalDelReparto(array $asignacion): int
    {
        // Con 0 como principal, `bloques()` agrupa bajo la clave 0 lo que nadie
        // tomó; acá no hay nada de eso, porque este método se llama justamente
        // cuando todos los servicios tienen dueño.
        $bloques = self::bloques($asignacion, 0);
        unset($bloques[0]);

        if (! $bloques) {
            return 0;
        }

        $mejor = 0;
        $minutos = -1;
        foreach ($bloques as $idProf => $mins) {
            if ($mins > $minutos || ($mins === $minutos && $idProf < $mejor)) {
                $mejor = (int) $idProf;
                $minutos = $mins;
            }
        }

        return $mejor;
    }

    // -----------------------------------------------------------------
    //  Escritura: agendar, reprogramar, cancelar
    // -----------------------------------------------------------------

    /**
     * Agenda la cita y guarda el reparto de servicios.
     *
     * Todo va dentro de una transacción porque `sp_agendar_cita` toma un
     * candado sobre la fila del profesional antes de consultar disponibilidad,
     * y ese candado se suelta al confirmar. Sin la transacción, dos peticiones
     * simultáneas reciben las dos «está libre» y se quedan con el mismo hueco.
     *
     * @param  array  $asignacion  [id_servicio => id_usuario] (0 = el principal)
     */
    public static function agendar(int $idCliente, int $idUsuario, string $fechaHora, int $duracion, ?string $observaciones, array $asignacion, ?int $idSucursal = null): int
    {
        // Sin sucursal explícita se usa la activa de la sesión, que es el caso
        // del panel. El portal SÍ la pasa: la clienta elige el local al
        // agendar, y no está atada a ninguno.
        //
        // Y si tampoco hay sesión —un comando, una prueba, el cron— se cae a
        // la sucursal del propio profesional. Es la única respuesta razonable:
        // sin eso quedaría en 0, que no es ninguna sucursal y la clave foránea
        // rechaza la cita con un error que no dice nada.
        $idSucursal ??= Sucursales::activa();
        if (! $idSucursal) {
            $idSucursal = (int) DB::scalar('SELECT id_sucursal FROM usuario WHERE id_usuario = ?', [$idUsuario]);
        }

        return (int) Bd::enTransaccion(function () use ($idCliente, $idUsuario, $fechaHora, $duracion, $observaciones, $asignacion, $idSucursal) {
            $idCita = Bd::idDe('sp_agendar_cita',
                [$idCliente, $idUsuario, $fechaHora, $duracion, $observaciones, $idSucursal]);

            // El turno de cada uno se guarda con el servicio: es lo que después
            // le dice a la agenda desde cuándo está ocupado ese profesional,
            // vía `fn_cita_inicio_de`. Sin esto, el segundo quedaría libre en la
            // franja en la que va a estar atendiendo acá.
            $turnos = self::turnos($asignacion, $idUsuario);

            foreach ($asignacion as $idServicio => $idProf) {
                $otro = (int) $idProf;
                $de = ($otro && $otro !== $idUsuario) ? $otro : $idUsuario;
                DB::insert(
                    'INSERT INTO cita_servicio (id_cita, id_servicio, id_usuario, orden) VALUES (?,?,?,?)',
                    [$idCita, (int) $idServicio, $de === $idUsuario ? null : $de,
                     (int) ($turnos[$de]['orden'] ?? 0)]
                );
            }

            return $idCita;
        });
    }

    /** Reprograma. Mismo motivo que arriba para la transacción. */
    public static function reprogramar(int $idCita, string $nuevaFechaHora, ?int $nuevoProfesional = null): void
    {
        Bd::enTransaccion(function () use ($idCita, $nuevaFechaHora, $nuevoProfesional) {
            if ($nuevoProfesional) {
                DB::update('UPDATE cita SET id_usuario=? WHERE id_cita=?', [$nuevoProfesional, $idCita]);
            }
            Bd::procedimiento('sp_reprogramar_cita', [$idCita, $nuevaFechaHora]);

            // **El recordatorio viejo se tira, si todavía no salió** (NO-01).
            // `generarRecordatorios()` saltea toda cita que ya tenga un aviso
            // de tipo 1, así que sin esto la clienta se queda con el de la
            // fecha anterior y **nunca recibe uno de la fecha real**: la cita
            // #545 se movió al 19/11 y su único recordatorio siguió diciendo
            // «tu cita del 14/11/2026 a las 09:30». Borrada la pendiente, el
            // cron la vuelve a crear con la fecha nueva.
            //
            // El que ya se envió no se toca: es historia de lo que se mandó, y
            // borrarlo no lo saca del buzón de nadie.
            Notificaciones::descartarRecordatorioPendiente($idCita);
        });
    }

    /**
     * Cancela la cita.
     *
     * **Va en transacción, y no es un adorno** (AG-04). `sp_cancelar_cita` toma
     * un candado sobre la fila de la cita antes de mirar su estado, y un
     * candado sólo dura hasta el commit: sin transacción propia se suelta al
     * instante y no serializa nada. Cancelar y reprogramar a la vez se pisaban
     * —ganaba la última en confirmar— y la cita quedaba Reprogramada aunque la
     * cancelación se hubiera registrado: la clienta cree que canceló, el
     * horario sigue ocupado y alguien la va a esperar.
     *
     * Es la misma razón por la que `agendar()` y `reprogramar()` la abren. Si
     * agregás otro camino que cancele, hacelo pasar por acá.
     */
    public static function cancelar(int $idCita): void
    {
        Bd::enTransaccion(function () use ($idCita) {
            Bd::procedimiento('sp_cancelar_cita', [$idCita]);

            // **El canje vuelve a quedar disponible, y los puntos NO se
            // devuelven.** No los perdió: los cambió por un servicio que sigue
            // teniendo. Devolverle los puntos y dejarle el canje sería
            // regalarle las dos cosas.
            //
            // Si el plazo se venció mientras la cita estaba agendada, el canje
            // vuelve vencido: el vencimiento corre desde que se canjeó, y la
            // pantalla lo muestra como tal.
            Canje::soltarDeCita($idCita);
        });
    }

    /**
     * Le pasa la cita a otro profesional **sin moverla de horario** (AG-03).
     *
     * Es lo que hace falta cuando alguien se da de baja o se toma una licencia
     * larga: la clienta ya tiene su hora reservada y no hay por qué hacerla
     * cambiar de día — lo único que cambia es quién la atiende.
     *
     * No es reprogramar, así que no pasa por `sp_reprogramar_cita`: ése cambia
     * la fecha y deja la cita en «Reprogramada», que acá sería mentir. Pero sí
     * comparte lo importante — **candado sobre el profesional que la recibe y
     * disponibilidad comprobada adentro**, porque entre que la pantalla mostró
     * la lista y se apretó el botón, ese horario se le pudo ocupar.
     *
     * Devuelve `true` si la movió y `false` si el destino no estaba libre.
     */
    public static function reasignar(int $idCita, int $nuevoProfesional): bool
    {
        return (bool) Bd::enTransaccion(function () use ($idCita, $nuevoProfesional) {
            $cita = DB::selectOne(
                'SELECT c.id_cita, c.id_usuario, c.fecha_hora
                   FROM cita c JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
                  WHERE c.id_cita = ? AND ec.bloquea_agenda = 1 FOR UPDATE', [$idCita]
            );
            if (! $cita || (int) $cita->id_usuario === $nuevoProfesional) {
                return false;
            }

            DB::selectOne('SELECT id_usuario FROM usuario WHERE id_usuario = ? FOR UPDATE', [$nuevoProfesional]);

            // La duración que le va a tocar A ÉL: la cita entera si se la lleva
            // toda, o sólo su bloque si el resto queda repartido.
            $dur = (int) Bd::funcion('fn_cita_duracion(?)', [$idCita]);
            if ($dur <= 0 || ! self::huecoLibre($nuevoProfesional, (string) $cita->fecha_hora, $dur, $idCita)) {
                return false;
            }

            DB::update('UPDATE cita SET id_usuario = ? WHERE id_cita = ?', [$nuevoProfesional, $idCita]);

            // **El reparto también se muda.** `cita_servicio.id_usuario` apunta
            // a quien hace cada servicio: si queda apuntando al que se fue, la
            // cita cambia de dueño pero los servicios siguen a nombre de una
            // persona inactiva, y con eso la comisión y el informe del equipo
            // se lo siguen atribuyendo a ella.
            DB::update('UPDATE cita_servicio SET id_usuario = ? WHERE id_cita = ? AND id_usuario = ?',
                [$nuevoProfesional, $idCita, (int) $cita->id_usuario]);

            // El recordatorio pendiente nombra al profesional viejo, así que se
            // tira y el cron lo rehace — mismo criterio que al reprogramar.
            Notificaciones::descartarRecordatorioPendiente($idCita);

            return true;
        });
    }
}
