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
    public function index(): View
    {
        return view('seguridad.index', [
            'subs' => Permisos::tarjetasPermitidas([
                ['p' => 'seguridad.usuarios', 'ruta' => 'seguridad.usuarios', 'ic' => 'person-badge',
                 't' => 'Usuarios', 'd' => 'Cuentas del personal'],
                ['p' => 'seguridad.roles', 'ruta' => 'seguridad.roles', 'ic' => 'shield-check',
                 't' => 'Roles', 'd' => 'Quién puede entrar a qué'],
                ['p' => 'seguridad.turnos', 'ruta' => 'seguridad.turnos', 'ic' => 'clock',
                 't' => 'Turnos', 'd' => 'Horarios y días de trabajo'],
                ['p' => 'seguridad.asistencia', 'ruta' => 'seguridad.asistencia', 'ic' => 'calendar-check',
                 't' => 'Asistencia', 'd' => 'Fichaje de entrada y salida'],
                ['p' => 'seguridad.comisiones', 'ruta' => 'seguridad.comisiones', 'ic' => 'percent',
                 't' => 'Comisiones', 'd' => 'Cuánto gana cada uno por servicio'],
                ['p' => 'seguridad.sucursales', 'ruta' => 'seguridad.sucursales', 'ic' => 'shop',
                 't' => 'Sucursales', 'd' => 'Locales del salón'],
                ['p' => 'seguridad.contacto', 'ruta' => 'seguridad.contacto', 'ic' => 'headset',
                 't' => 'Contacto y soporte', 'd' => 'Los medios que salen en el pie'],
                ['p' => 'seguridad.auditoria', 'ruta' => 'seguridad.auditoria', 'ic' => 'journal-text',
                 't' => 'Auditoría', 'd' => 'Qué se hizo, quién y cuándo'],
            ]),
        ]);
    }
}
