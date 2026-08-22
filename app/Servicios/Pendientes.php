<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Qué le falta CARGAR al salón para que el sistema haga todo lo que sabe.
 *
 * **No busca errores: busca decisiones sin tomar.** `spg:diagnostico` contesta
 * «¿el sistema está sano?»; esto contesta «¿está configurado?», que es otra
 * pregunta y hasta la 7.60.0 no la contestaba nadie.
 *
 * La diferencia importa porque **el sistema no se rompe cuando falta un dato:
 * cae en el criterio permisivo**. Un profesional sin servicios cargados los
 * hace todos; un servicio sin zona no comparte con nadie; una sucursal sin
 * timbrado numera con el de otra sede. Ninguna de las tres tira un error — el
 * sistema decide distinto de lo que el salón espera, y eso se descubre el día
 * de la cita.
 *
 * Cada renglón lleva **dónde se arregla** y **con qué permiso**, que es lo que
 * permite mostrarlo en el panel de quien puede resolverlo y no en el de todos.
 */
class Pendientes
{
    /** No se puede trabajar hasta cargarlo. */
    public const IMPIDE = 'IMPIDE';

    /** Se puede trabajar, pero el sistema decide distinto de lo que se espera. */
    public const CONFUNDE = 'CONFUNDE';

    /** Ni impide ni confunde: falta para que algo sirva del todo. */
    public const CONVIENE = 'CONVIENE';

    /** @var list<array{nivel:string,que:string,donde:string,ruta:?string,permiso:string}> */
    private static array $puntos = [];

    /**
     * Todo lo que falta, sin filtrar por quién mira.
     *
     * @return list<array{nivel:string,que:string,donde:string,ruta:?string,permiso:string}>
     */
    public static function todo(): array
    {
        self::$puntos = [];

        try {
            self::timbrados();
            self::servicios();
            self::profesionales();
            self::comisiones();
            self::fiscales();
        } catch (Throwable) {
            // Una consulta que falle no puede dejar el panel sin dibujarse:
            // esto es un aviso, no la pantalla.
            return self::$puntos;
        }

        return self::ordenar(self::$puntos);
    }

    /**
     * Sólo lo que ESTA persona puede resolver.
     *
     * **Se filtra por permiso y no por rol**, la misma regla que decide a quién
     * le llegan los avisos internos: mostrarle a la recepcionista que faltan
     * timbrados no sirve de nada —no puede cargarlos— y le tapa lo que sí es
     * suyo.
     *
     * @return list<array{nivel:string,que:string,donde:string,ruta:?string,permiso:string}>
     */
    public static function mios(): array
    {
        return array_values(array_filter(
            self::todo(), fn (array $p) => Permisos::puede($p['permiso'])
        ));
    }

    /** IMPIDE primero, CONVIENE al final. */
    private static function ordenar(array $puntos): array
    {
        $peso = [self::IMPIDE => 0, self::CONFUNDE => 1, self::CONVIENE => 2];
        usort($puntos, fn ($a, $b) => $peso[$a['nivel']] <=> $peso[$b['nivel']]);

        return $puntos;
    }

    private static function anotar(string $nivel, string $que, string $donde, ?string $ruta, string $permiso): void
    {
        self::$puntos[] = compact('nivel', 'que', 'donde', 'ruta', 'permiso');
    }

    /** Sin timbrado propio, el establecimiento impreso dice otra sede. */
    private static function timbrados(): void
    {
        $sinTimbrado = DB::select(
            'SELECT s.nombre FROM sucursal s
              WHERE s.activo = 1
                AND NOT EXISTS (SELECT 1 FROM timbrado t
                                 WHERE t.id_sucursal = s.id_sucursal AND t.activo = 1)
              ORDER BY s.nombre'
        );

        if ($sinTimbrado) {
            self::anotar(self::CONFUNDE,
                count($sinTimbrado) . ' sucursal(es) sin timbrado propio: ' . self::nombres($sinTimbrado, 'nombre')
                . '. Numeran con el de otra sede, así que el establecimiento impreso '
                . '—los tres primeros dígitos— dice de qué local salió el comprobante, y va a decir el equivocado.',
                'Tesorería → Timbrados, uno por sucursal',
                'facturacion.timbrados', 'facturacion.timbrados');
        }

        $vencidos = (int) DB::scalar(
            'SELECT COUNT(*) FROM timbrado WHERE activo = 1 AND fecha_fin < CURDATE()');
        if ($vencidos) {
            self::anotar(self::IMPIDE,
                $vencidos . ' timbrado(s) vencido(s): con esos no se emite.',
                'Tesorería → Timbrados', 'facturacion.timbrados', 'facturacion.timbrados');
        }

        $porVencer = (int) DB::scalar(
            'SELECT COUNT(*) FROM timbrado WHERE activo = 1
              AND fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
        if ($porVencer) {
            self::anotar(self::CONVIENE,
                $porVencer . ' timbrado(s) vencen dentro de 30 días.',
                'Tesorería → Timbrados', 'facturacion.timbrados', 'facturacion.timbrados');
        }
    }

    /** Servicios sin publicar, sin zona o sin decidir la seña. */
    private static function servicios(): void
    {
        // La convención es «sin filas vale en todas», así que sólo tiene
        // sentido preguntar cuando ALGUIEN publica algo.
        $vacias = DB::select(
            'SELECT s.nombre FROM sucursal s
              WHERE s.activo = 1
                AND NOT EXISTS (SELECT 1 FROM servicio_sucursal ss WHERE ss.id_sucursal = s.id_sucursal)
                AND EXISTS (SELECT 1 FROM servicio_sucursal)
              ORDER BY s.nombre'
        );
        if ($vacias) {
            self::anotar(self::IMPIDE,
                count($vacias) . ' sucursal(es) no publican ningún servicio: ' . self::nombres($vacias, 'nombre')
                . '. La clienta que elija ese local en el portal no ve nada que reservar.',
                'Servicios → el listado, con esa sucursal activa',
                'servicios.index', 'servicios.catalogo');
        }

        $sinZona = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE activo = 1 AND id_zona IS NULL');
        if ($sinZona) {
            self::anotar(self::CONFUNDE,
                $sinZona . ' servicio(s) sin zona del cuerpo. Sin zona no comparte con nadie, '
                . 'así que el sistema los deja hacerse en paralelo con cualquier cosa — '
                . 'incluida otra cosa sobre la misma cabeza.',
                'Servicios → Zonas del cuerpo nombra cuáles son',
                'servicios.zonas', 'servicios.categorias');
        }

        $conSena = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE activo = 1 AND sena_porcentaje IS NOT NULL');
        $total = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE activo = 1');
        if ($conSena === 0 && $total > 0) {
            self::anotar(self::CONVIENE,
                'Ningún servicio pide seña. Si el salón cobra adelanto para reservar, hay que decirlo '
                . 'acá: si no, la reserva no la garantiza nada.',
                'Servicios → el formulario, campo «Seña que se pide»',
                'servicios.index', 'servicios.catalogo');
        }
    }

    /** Quién atiende y qué hace. */
    private static function profesionales(): void
    {
        // El criterio permisivo es DEL SALÓN: si nadie tiene turnos, el salón
        // todavía no los usa y no falta nada.
        if ((int) DB::scalar('SELECT COUNT(*) FROM usuario_turno') > 0) {
            $sinTurno = DB::select(
                "SELECT CONCAT(pe.nombre, ' ', pe.apellido) AS quien
                   FROM usuario u
                   JOIN rol r ON r.id_rol = u.id_rol
                   JOIN persona pe ON pe.id_persona = u.id_persona
                  WHERE u.activo = 1 AND r.es_personal = 1
                    AND NOT EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)
                  ORDER BY pe.nombre"
            );
            if ($sinTurno) {
                self::anotar(self::CONFUNDE,
                    count($sinTurno) . ' persona(s) sin turno asignado: ' . self::nombres($sinTurno)
                    . '. No aparecen en la agenda, porque el salón usa turnos. Si alguna atiende, '
                    . 'hay que darle uno.',
                    'Personal → Turnos, y después la ficha de cada persona',
                    'seguridad.turnos', 'personal.turnos');
            }
        }

        if ((int) DB::scalar('SELECT COUNT(*) FROM usuario_servicio') > 0) {
            $sinServicios = DB::select(
                "SELECT CONCAT(pe.nombre, ' ', pe.apellido) AS quien
                   FROM usuario u
                   JOIN rol r ON r.id_rol = u.id_rol
                   JOIN persona pe ON pe.id_persona = u.id_persona
                  WHERE u.activo = 1 AND r.es_personal = 1
                    AND EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)
                    AND NOT EXISTS (SELECT 1 FROM usuario_servicio us WHERE us.id_usuario = u.id_usuario)
                  ORDER BY pe.nombre"
            );
            if ($sinServicios) {
                self::anotar(self::CONFUNDE,
                    count($sinServicios) . ' profesional(es) sin servicios cargados: ' . self::nombres($sinServicios)
                    . '. Se les ofrece para todo, así que la clienta puede reservar una coloración '
                    . 'con quien sólo hace uñas, y el «no» llega el día de la cita.',
                    'Seguridad → Usuarios → la ficha, «Servicios que hace»',
                    'seguridad.usuarios', 'seguridad.usuarios');
            }
        }
    }

    /** Sin comisión cargada no se puede liquidar. */
    private static function comisiones(): void
    {
        $sinComision = DB::select(
            "SELECT CONCAT(pe.nombre, ' ', pe.apellido) AS quien
               FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.activo = 1 AND r.es_personal = 1
                AND EXISTS (SELECT 1 FROM usuario_turno ut WHERE ut.id_usuario = u.id_usuario)
                AND NOT EXISTS (SELECT 1 FROM comision c
                                 WHERE c.id_usuario = u.id_usuario AND c.activo = 1)
              ORDER BY pe.nombre"
        );
        if ($sinComision) {
            self::anotar(self::CONVIENE,
                count($sinComision) . ' profesional(es) sin comisión cargada: ' . self::nombres($sinComision)
                . '. El informe del equipo dice «sin cargar» en vez de un cero —que sería mentir— '
                . 'pero tampoco se puede liquidar.',
                'Personal → Comisiones', 'seguridad.comisiones', 'personal.comisiones');
        }
    }

    /** Lo que sale impreso en el comprobante electrónico. */
    private static function fiscales(): void
    {
        $c = DB::selectOne(
            'SELECT actividad_cod, actividad_desc FROM configuracion WHERE id_configuracion = 1');

        if ($c && (trim((string) $c->actividad_cod) === '' || trim((string) $c->actividad_desc) === '')) {
            self::anotar(self::CONFUNDE,
                'Sin actividad económica cargada. El KuDE la imprime, y si no viaja con la factura '
                . 'el Automatizador pone la de su archivo de ejemplo: «VENTA AL POR MENOR».',
                'Configuración → Sucursales, bloque de la factura electrónica',
                'seguridad.sucursales', 'configuracion.sucursales');
        }

        $sinRuc = DB::select(
            "SELECT nombre FROM sucursal WHERE activo = 1 AND (ruc IS NULL OR TRIM(ruc) = '') ORDER BY nombre");
        if ($sinRuc) {
            self::anotar(self::CONFUNDE,
                count($sinRuc) . ' sucursal(es) sin RUC cargado: ' . self::nombres($sinRuc, 'nombre')
                . '. El comprobante que emitan sale sin él.',
                'Configuración → Sucursales', 'seguridad.sucursales', 'configuracion.sucursales');
        }

        $sinDireccion = (int) DB::scalar(
            "SELECT COUNT(*) FROM sucursal WHERE activo = 1 AND (direccion IS NULL OR TRIM(direccion) = '')");
        if ($sinDireccion) {
            self::anotar(self::CONVIENE,
                $sinDireccion . ' sucursal(es) sin dirección. Va impresa en el comprobante.',
                'Configuración → Sucursales', 'seguridad.sucursales', 'configuracion.sucursales');
        }
    }

    /** Los primeros nombres, y «y N más» si son muchos. */
    private static function nombres(array $filas, string $campo = 'quien'): string
    {
        $lista = implode(', ', array_map(fn ($f) => $f->$campo, array_slice($filas, 0, 3)));

        return count($filas) > 3 ? $lista . ' y ' . (count($filas) - 3) . ' más' : $lista;
    }
}
