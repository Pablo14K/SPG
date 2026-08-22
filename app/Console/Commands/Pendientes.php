<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Qué le falta cargar al salón para que el sistema haga todo lo que sabe.
 *
 * **No busca errores: busca decisiones sin tomar.** `spg:diagnostico` contesta
 * «¿el sistema está sano?»; esto contesta «¿está configurado?», que es una
 * pregunta distinta y hoy no la contestaba nadie.
 *
 * La diferencia importa porque **el sistema no se rompe cuando falta un dato:
 * cae en el criterio permisivo**. Un profesional sin servicios cargados los
 * hace todos; un servicio sin zona no comparte con nadie; una sucursal sin
 * timbrado numera con el de otra sede. Ninguna de esas tres tira un error —
 * simplemente el sistema decide distinto de lo que el salón espera, y eso se
 * descubre el día de la cita.
 *
 * Cada renglón dice **dónde se arregla**, que es lo que convierte un aviso en
 * algo accionable.
 */
class Pendientes extends Command
{
    protected $signature = 'spg:pendientes';

    protected $description = 'Qué datos le faltan al salón para que el sistema funcione completo';

    /** Lo que se va encontrando: [gravedad, qué, dónde se arregla]. */
    private array $puntos = [];

    public function handle(): int
    {
        $this->line('');
        $this->info('  QUÉ FALTA CARGAR');
        $this->line('  ' . str_repeat('─', 66));

        try {
            $this->timbrados();
            $this->servicios();
            $this->profesionales();
            $this->comisiones();
            $this->fiscales();
        } catch (Throwable $e) {
            $this->error('  No se pudo revisar: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('');

        if ($this->puntos === []) {
            $this->info('  Nada pendiente: el salón está configurado.');

            return self::SUCCESS;
        }

        foreach (['IMPIDE', 'CONFUNDE', 'CONVIENE'] as $nivel) {
            $delNivel = array_filter($this->puntos, fn ($p) => $p[0] === $nivel);
            if (! $delNivel) {
                continue;
            }

            $this->line('');
            $this->line('  <options=bold>' . match ($nivel) {
                'IMPIDE' => 'IMPIDE TRABAJAR',
                'CONFUNDE' => 'HACE QUE EL SISTEMA DECIDA DISTINTO DE LO QUE ESPERÁS',
                default => 'CONVIENE',
            } . '</>');

            foreach ($delNivel as [, $que, $donde]) {
                $this->line('   · ' . $que);
                $this->line('     <fg=gray>→ ' . $donde . '</>');
            }
        }

        $this->line('');

        return self::SUCCESS;
    }

    private function anotar(string $nivel, string $que, string $donde): void
    {
        $this->puntos[] = [$nivel, $que, $donde];
    }

    /** Sin timbrado propio, el establecimiento impreso dice otra sede. */
    private function timbrados(): void
    {
        $sinTimbrado = DB::select(
            'SELECT s.id_sucursal, s.nombre FROM sucursal s
              WHERE s.activo = 1
                AND NOT EXISTS (SELECT 1 FROM timbrado t
                                 WHERE t.id_sucursal = s.id_sucursal AND t.activo = 1)
              ORDER BY s.nombre'
        );

        if ($sinTimbrado) {
            $nombres = implode(', ', array_map(fn ($s) => $s->nombre, array_slice($sinTimbrado, 0, 4)));
            $mas = count($sinTimbrado) > 4 ? ' y ' . (count($sinTimbrado) - 4) . ' más' : '';
            $this->anotar('CONFUNDE',
                count($sinTimbrado) . ' sucursal(es) sin timbrado propio: ' . $nombres . $mas
                . '. Numeran con el de otra sede, así que el establecimiento impreso '
                . '—los tres primeros dígitos— dice de qué local salió, y va a decir el equivocado.',
                'Tesorería → Timbrados, uno por sucursal');
        }

        $vencidos = (int) DB::scalar(
            'SELECT COUNT(*) FROM timbrado WHERE activo = 1 AND fecha_fin < CURDATE()');
        if ($vencidos) {
            $this->anotar('IMPIDE', $vencidos . ' timbrado(s) vencido(s): con esos no se emite.',
                'Tesorería → Timbrados');
        }

        $porVencer = (int) DB::scalar(
            'SELECT COUNT(*) FROM timbrado WHERE activo = 1
              AND fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
        if ($porVencer) {
            $this->anotar('CONVIENE', $porVencer . ' timbrado(s) vencen dentro de 30 días.',
                'Tesorería → Timbrados');
        }
    }

    /** Servicios sin publicar, sin zona o sin decidir la seña. */
    private function servicios(): void
    {
        $vacias = DB::select(
            'SELECT s.nombre FROM sucursal s
              WHERE s.activo = 1
                AND NOT EXISTS (SELECT 1 FROM servicio_sucursal ss WHERE ss.id_sucursal = s.id_sucursal)
                AND EXISTS (SELECT 1 FROM servicio_sucursal)'
        );
        foreach ($vacias as $v) {
            $this->anotar('IMPIDE',
                '«' . $v->nombre . '» no publica ningún servicio: la clienta que elija ese local '
                . 'en el portal no ve nada que reservar.',
                'Servicios → el listado, con esa sucursal activa');
        }

        $sinZona = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE activo = 1 AND id_zona IS NULL');
        if ($sinZona) {
            $this->anotar('CONFUNDE',
                $sinZona . ' servicio(s) sin zona del cuerpo. Sin zona no comparte con nadie, '
                . 'así que el sistema los deja hacerse en paralelo con cualquier cosa — '
                . 'incluida otra cosa sobre la misma cabeza.',
                'Servicios → el formulario de cada uno');
        }

        $conSena = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE activo = 1 AND sena_porcentaje IS NOT NULL');
        $total = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE activo = 1');
        if ($conSena === 0 && $total > 0) {
            $this->anotar('CONVIENE',
                'Ningún servicio pide seña. Si el salón cobra adelanto para reservar, hay que decirlo '
                . 'acá: si no, la reserva no la garantiza nada.',
                'Servicios → el formulario, campo «Seña que se pide»');
        }
    }

    /** Quién atiende, qué hace y dónde. */
    private function profesionales(): void
    {
        $sinTurno = DB::select(
            "SELECT CONCAT(pe.nombre, ' ', pe.apellido) AS quien
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.activo = 1 AND r.es_personal = 1
                AND NOT EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)"
        );
        if ($sinTurno && (int) DB::scalar('SELECT COUNT(*) FROM usuario_turno') > 0) {
            $this->anotar('CONFUNDE',
                count($sinTurno) . ' persona(s) sin turno asignado: ' . implode(', ',
                    array_map(fn ($s) => $s->quien, array_slice($sinTurno, 0, 4)))
                . '. **No aparecen en la agenda**, porque el salón usa turnos. Si alguna atiende, '
                . 'hay que darle uno.',
                'Personal → Turnos, y después la ficha de cada persona');
        }

        $sinServicios = DB::select(
            "SELECT CONCAT(pe.nombre, ' ', pe.apellido) AS quien
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.activo = 1 AND r.es_personal = 1
                AND EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)
                AND NOT EXISTS (SELECT 1 FROM usuario_servicio us WHERE us.id_usuario = u.id_usuario)"
        );
        if ($sinServicios && (int) DB::scalar('SELECT COUNT(*) FROM usuario_servicio') > 0) {
            $this->anotar('CONFUNDE',
                count($sinServicios) . ' profesional(es) sin servicios cargados: ' . implode(', ',
                    array_map(fn ($s) => $s->quien, array_slice($sinServicios, 0, 4)))
                . '. **Se les ofrece para todo**, así que la clienta puede reservar una coloración '
                . 'con quien sólo hace uñas, y el «no» llega el día de la cita.',
                'Personal → Profesionales → la ficha, «Servicios que hace»');
        }
    }

    /** Sin comisión cargada, el informe del equipo no sirve para liquidar. */
    private function comisiones(): void
    {
        $sinComision = DB::select(
            "SELECT CONCAT(pe.nombre, ' ', pe.apellido) AS quien
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.activo = 1 AND r.es_personal = 1
                AND EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)
                AND NOT EXISTS (SELECT 1 FROM comision c
                                 WHERE c.id_usuario = u.id_usuario AND c.activo = 1)"
        );
        if ($sinComision) {
            $this->anotar('CONVIENE',
                count($sinComision) . ' profesional(es) sin comisión cargada. El informe del equipo '
                . 'dice «sin cargar» en vez de un cero —que sería mentir— pero tampoco se puede liquidar.',
                'Personal → Comisiones');
        }
    }

    /** Lo que sale impreso en el comprobante electrónico. */
    private function fiscales(): void
    {
        $c = DB::selectOne('SELECT nombre_salon, actividad_cod, actividad_desc, email FROM configuracion WHERE id_configuracion = 1');

        if ($c && (trim((string) $c->actividad_cod) === '' || trim((string) $c->actividad_desc) === '')) {
            $this->anotar('CONFUNDE',
                'Sin actividad económica cargada. El KuDE la imprime, y si no viaja con la factura '
                . 'el Automatizador pone la de su archivo de ejemplo: «VENTA AL POR MENOR».',
                'Configuración → Sucursales, bloque de la factura electrónica');
        }

        $sinRuc = DB::select(
            "SELECT nombre FROM sucursal WHERE activo = 1 AND (ruc IS NULL OR TRIM(ruc) = '')");
        foreach ($sinRuc as $s) {
            $this->anotar('CONFUNDE',
                '«' . $s->nombre . '» no tiene RUC cargado: el comprobante que emita sale sin él.',
                'Configuración → Sucursales');
        }

        $sinDireccion = (int) DB::scalar(
            "SELECT COUNT(*) FROM sucursal WHERE activo = 1 AND (direccion IS NULL OR TRIM(direccion) = '')");
        if ($sinDireccion) {
            $this->anotar('CONVIENE',
                $sinDireccion . ' sucursal(es) sin dirección. Va impresa en el comprobante.',
                'Configuración → Sucursales');
        }
    }
}
