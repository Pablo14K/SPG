<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Permisos;
use Illuminate\View\View;

/**
 * Portada del módulo Seguridad.
 *
 * Seguridad junta lo que hasta la 6.1.5 eran dos módulos, Personal y
 * Configuración: las cuentas y sus permisos por un lado, el registro de lo que
 * cada uno hizo por el otro. Las pantallas siguen repartidas en
 * `PersonalController` (usuarios, turnos, comisiones, asistencia) y
 * `ConfiguracionController` (sucursales, roles, contacto, auditoría), que son
 * dos archivos grandes y no gana nada juntarlos; lo único que vive acá es el
 * tercer nivel de navegación, que sí es uno solo.
 */
class SeguridadController extends Controller
{
    /** Quién entra y qué puede hacer. */
    public function index(): View
    {
        return view('seguridad.index', [
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'seguridad.usuarios', 'ruta' => 'seguridad.usuarios', 'ic' => 'person-badge',
                 't' => 'Usuarios', 'd' => 'Cuentas del personal'],
                ['p' => 'seguridad.roles', 'ruta' => 'seguridad.roles', 'ic' => 'shield-check',
                 't' => 'Roles', 'd' => 'Quién puede entrar a qué'],
                ['p' => 'seguridad.auditoria', 'ruta' => 'seguridad.auditoria', 'ic' => 'journal-text',
                 't' => 'Auditoría', 'd' => 'Qué se hizo, quién y cuándo'],
            ]),
        ]);
    }

    /**
     * Quién trabaja y cuándo.
     *
     * **Estaba dentro de Seguridad y no es lo mismo.** Los turnos y el fichaje
     * son la operación de todos los días; los roles y la auditoría se tocan
     * una vez y se miran cuando algo pasó. Juntas obligaban a entrar al mismo
     * lugar para dos trabajos distintos.
     */
    public function personal(): View
    {
        return view('seguridad.personal', [
            'subs' => Permisos::tarjetasPermitidas([
                // **Va con `?desde=personal`**: es la misma ficha que Usuarios,
                // pero abierta en los datos de la persona y no en la cuenta.
                // Son dos trabajos distintos sobre el mismo registro, y una
                // sola ficha porque dos se desfasan.
                ['p' => 'seguridad.usuarios', 'ruta' => 'seguridad.usuarios',
                 'ancla' => '?desde=personal', 'ic' => 'people',
                 't' => 'Profesionales', 'd' => 'El equipo del salón'],
                ['p' => 'personal.turnos', 'ruta' => 'seguridad.turnos', 'ic' => 'clock',
                 't' => 'Turnos', 'd' => 'Horarios y días de trabajo'],
                ['p' => 'personal.asistencia', 'ruta' => 'seguridad.asistencia', 'ic' => 'calendar-check',
                 't' => 'Asistencia', 'd' => 'Fichaje de entrada y salida'],
                ['p' => 'personal.comisiones', 'ruta' => 'seguridad.comisiones', 'ic' => 'percent',
                 't' => 'Comisiones', 'd' => 'Cuánto gana cada uno por servicio'],
            ]),
        ]);
    }

    /**
     * Cómo está armado el salón.
     *
     * Junta lo que es de cada persona —su cuenta— con lo que es del negocio:
     * los locales y por dónde lo contactan. Es lo que se toca una vez y no se
     * vuelve a mirar, así que no tiene por qué competir con la operación.
     */
    public function configuracion(): View
    {
        return view('seguridad.configuracion', [
            // **Mi cuenta NO va acá.** Ya vive en el desplegable del nombre,
            // arriba a la derecha, que es donde la busca cualquiera: repetirla
            // acá agrega un renglón que no lleva a ningún lado nuevo.
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'configuracion.sucursales', 'ruta' => 'seguridad.sucursales', 'ic' => 'shop',
                 't' => 'Sucursales', 'd' => 'Locales del salón, nombre y logo'],
                ['p' => 'configuracion.contacto', 'ruta' => 'seguridad.contacto', 'ic' => 'headset',
                 't' => 'Contacto', 'd' => 'Los medios que salen en el pie'],
            ]),
        ]);
    }

}
