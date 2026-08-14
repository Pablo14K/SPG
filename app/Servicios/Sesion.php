<?php

declare(strict_types=1);

namespace App\Servicios;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Quién está usando el sistema.
 *
 * No se usa el modelo `User` de Laravel: las cuentas viven en `usuario`, los
 * datos personales en `persona` y el alcance en `rol`, tal como los define el
 * esquema del TCC. Meter un modelo Eloquent con las convenciones del framework
 * obligaría a agregarle columnas a esas tablas y a romper la 3FN.
 *
 * Esta clase es el único lugar que arma la sesión: por acá pasan el login con
 * contraseña, el biométrico y la verificación de cuenta, así ninguno se olvida
 * de regenerar el identificador de sesión.
 */
class Sesion
{
    /**
     * Valida usuario y contraseña. Se puede entrar con el nombre de usuario o
     * con el email, que vive en `persona` desde que se unificaron los datos
     * personales.
     */
    public static function intentarLogin(string $usuario, string $password): bool
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, u.password_hash
               FROM usuario u JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE (u.username = :u1 OR pe.email = :u2) AND u.activo = 1
              LIMIT 1',
            ['u1' => $usuario, 'u2' => $usuario]
        );

        if (! $u) {
            // Se gasta el mismo tiempo que costaría comprobar una contraseña
            // real, para que el tiempo de respuesta no delate qué cuentas
            // están registradas.
            //
            // OJO: acá NO sirve comparar contra un hash inventado, como hacía
            // el sistema anterior. `password_verify()` devolvía false y
            // seguía de largo, pero `Hash::check()` de Laravel valida el
            // formato y levanta una excepción si no es un bcrypt legítimo:
            // intentar entrar con un usuario inexistente terminaba en un
            // error 500 en lugar de «usuario o contraseña incorrectos».
            Hash::make($password);

            return false;
        }

        try {
            if (! Hash::check($password, (string) $u->password_hash)) {
                return false;
            }
        } catch (RuntimeException) {
            // Un hash guardado con otro algoritmo no deja entrar, pero tampoco
            // rompe la pantalla: la persona tiene que poder recuperar su clave.
            return false;
        }

        if (! self::iniciarPorId((int) $u->id_usuario)) {
            return false;
        }

        Auditoria::registrar('LOGIN', 'Seguridad', 'usuario', (int) $u->id_usuario, 'Inicio de sesión');

        return true;
    }

    /** Arma la sesión a partir de un id de usuario. */
    public static function iniciarPorId(int $idUsuario): bool
    {
        $u = DB::selectOne(
            'SELECT u.id_usuario, u.id_rol, u.id_sucursal, pe.nombre, pe.apellido,
                    r.nombre AS rol_nombre, r.es_personal
               FROM usuario u
               JOIN rol r      ON r.id_rol = u.id_rol
               JOIN persona pe ON pe.id_persona = u.id_persona
              WHERE u.id_usuario = ? AND u.activo = 1 LIMIT 1',
            [$idUsuario]
        );
        if (! $u) {
            return false;
        }

        session()->regenerate();

        session([
            'uid' => (int) $u->id_usuario,
            'nombre' => $u->nombre . ' ' . $u->apellido,
            'rol' => (int) $u->id_rol,
            'rol_nom' => $u->rol_nombre,
            'es_personal' => (bool) $u->es_personal,
            // Cliente es cualquier rol que NO sea personal: así un rol nuevo
            // marcado como «no personal» también va al portal, sin tocar código.
            'es_cliente' => ! (bool) $u->es_personal,
            'id_sucursal' => (int) ($u->id_sucursal ?? 0),
            'id_cliente' => null,
            // El tema se lee una vez al entrar y viaja en la sesión: lo dibuja
            // el layout en cada pantalla, y consultarlo por petición sería una
            // consulta de más para un dato que no cambia solo.
            'tema' => self::temaDe((int) $u->id_usuario),
        ]);

        if (! $u->es_personal) {
            $idc = DB::scalar('SELECT id_cliente FROM cliente WHERE id_usuario = ? LIMIT 1', [$idUsuario]);
            session(['id_cliente' => $idc ? (int) $idc : null]);
        }

        // Esta pasa a ser LA sesión de la cuenta: si había otra abierta, queda
        // desplazada y se cierra sola en su próxima petición.
        self::marcarUnica($idUsuario);

        Permisos::olvidar();

        return true;
    }

    // -----------------------------------------------------------------
    //  Una sola sesión por cuenta
    // -----------------------------------------------------------------

    /**
     * Deja esta sesión como la única válida de la cuenta.
     *
     * Se guarda una marca en `usuario` y la misma en la sesión. No hace falta
     * ir a buscar la sesión anterior para destruirla —con el driver de archivo
     * habría que revolver `storage/framework/sessions`—: alcanza con que la
     * marca que lleva la otra ya no sea la que figura en la cuenta.
     */
    public static function marcarUnica(int $idUsuario): void
    {
        // La marca es un valor propio, NO el id de la sesión. El id se
        // regenera —al entrar, y otra vez al cambiar la contraseña—, y con el
        // driver `array` de las pruebas cambia en cada petición: comparar
        // contra él sacaba a la persona en el paso siguiente al ingreso.
        $marca = bin2hex(random_bytes(16));

        DB::update('UPDATE usuario SET sesion_activa = ?, sesion_desde = NOW() WHERE id_usuario = ?',
            [$marca, $idUsuario]);
        session(['sesion_marca' => $marca]);
    }

    /**
     * ¿Esta sesión sigue siendo la de la cuenta, o la desplazó otra?
     *
     * Devuelve `null` si todo bien, o el aviso para mostrarle a quien quedó
     * afuera. Se consulta en cada petición junto con el rol, así que va en la
     * misma ida a la base y no cuesta una consulta más.
     */
    public static function desplazada(): ?string
    {
        $uid = (int) session('uid', 0);
        if (! $uid) {
            return null;
        }

        $u = DB::selectOne('SELECT sesion_activa, sesion_desde FROM usuario WHERE id_usuario = ?', [$uid]);
        if (! $u || $u->sesion_activa === null || $u->sesion_activa === session('sesion_marca')) {
            return null;
        }

        // Sin negritas ni markdown: el aviso se dibuja escapado, así que los
        // asteriscos saldrían tal cual.
        return 'Alguien entró a tu cuenta desde otro equipo'
            . ($u->sesion_desde ? ' el ' . fecha($u->sesion_desde) : '')
            . ', así que esta sesión se cerró. Si fuiste vos, volvé a entrar sin problema. '
            . 'Si NO fuiste vos, alguien más sabe tu contraseña: cambiala apenas entres, '
            . 'desde Mi cuenta.';
    }

    /**
     * Relee el rol desde la base, una vez por petición.
     *
     * El Administrador puede renombrarlo, cambiarlo de tipo o cambiárselo a la
     * persona, y la sesión seguiría aplicando el dato viejo hasta el próximo
     * login. A quien le sacaban el rol de Administrador le seguían funcionando
     * los permisos de Administrador hasta que cerrara sesión.
     */
    public static function refrescarRol(): void
    {
        if (! session('uid')) {
            return;
        }
        $rol = DB::selectOne(
            'SELECT u.id_rol, r.nombre, r.es_personal FROM usuario u
               JOIN rol r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?',
            [(int) session('uid')]
        );
        if (! $rol) {
            return;
        }

        if ((int) $rol->id_rol !== (int) session('rol')) {
            Permisos::olvidar();
        }

        session([
            'rol' => (int) $rol->id_rol,
            'rol_nom' => $rol->nombre,
            'es_personal' => (bool) $rol->es_personal,
            'es_cliente' => ! (bool) $rol->es_personal,
        ]);
    }

    public static function activa(): bool
    {
        return (bool) session('uid');
    }

    public static function esCliente(): bool
    {
        return (bool) session('es_cliente', false);
    }

    /** A dónde va cada quien después de entrar. */
    public static function inicio(): string
    {
        return self::esCliente() ? 'portal.index' : 'panel';
    }

    public static function cerrar(): void
    {
        // Se limpia la marca SÓLO si esta sesión era la activa. Si ya la
        // desplazó otra, borrarla dejaría a la nueva sin marca y la próxima
        // que entrara no la desplazaría a ella.
        $uid = (int) session('uid', 0);
        if ($uid) {
            DB::update('UPDATE usuario SET sesion_activa = NULL, sesion_desde = NULL
                         WHERE id_usuario = ? AND sesion_activa = ?', [$uid, session('sesion_marca')]);
        }

        session()->flush();
        session()->regenerate();
        Permisos::olvidar();
    }

    /** Los temas que existen. De acá salen el selector y la validación. */
    public const TEMAS = ['claro' => 'Claro', 'oscuro' => 'Oscuro'];

    /**
     * El tema que eligió esta persona.
     *
     * Si no tiene fila en `preferencia_usuario` —o el valor quedó en algo que
     * ya no existe— se devuelve «claro», que es el de siempre. La pantalla
     * nunca se queda sin tema.
     */
    public static function temaDe(int $idUsuario): string
    {
        $t = (string) (DB::scalar('SELECT tema FROM preferencia_usuario WHERE id_usuario = ?', [$idUsuario]) ?: '');

        return isset(self::TEMAS[$t]) ? $t : 'claro';
    }

    /** El tema de quien está mirando la pantalla ahora. */
    public static function tema(): string
    {
        $t = (string) session('tema', 'claro');

        return isset(self::TEMAS[$t]) ? $t : 'claro';
    }

    /** Guarda el tema y lo deja aplicado en el acto, sin volver a entrar. */
    public static function guardarTema(int $idUsuario, string $tema): bool
    {
        if (! isset(self::TEMAS[$tema])) {
            return false;
        }

        DB::statement(
            'INSERT INTO preferencia_usuario (id_usuario, tema) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE tema = VALUES(tema)', [$idUsuario, $tema]
        );
        session(['tema' => $tema]);

        return true;
    }
}
