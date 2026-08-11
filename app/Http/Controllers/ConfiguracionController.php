<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Servicios\Auditoria;
use App\Servicios\Contacto;
use App\Servicios\Listado;
use App\Servicios\Permisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * La mitad del módulo Seguridad que trata del sistema: sucursales, roles,
 * contacto y auditoría. La otra mitad —usuarios, turnos, comisiones y
 * asistencia— está en `PersonalController`, y la portada del módulo en
 * `SeguridadController`.
 */
class ConfiguracionController extends Controller
{
    // ---------- Sucursales ----------

    public function sucursales(): View
    {
        return view('seguridad.sucursales', [
            'rows' => DB::select(
                'SELECT s.*, (SELECT COUNT(*) FROM usuario u WHERE u.id_sucursal = s.id_sucursal) AS personal
                   FROM sucursal s ORDER BY s.nombre'
            ),
        ]);
    }

    public function sucursalForm(int $id = 0): View|RedirectResponse
    {
        $s = $id ? DB::selectOne('SELECT * FROM sucursal WHERE id_sucursal = ?', [$id]) : null;
        if ($id && ! $s) {
            flash('Esa sucursal no existe.', 'error');

            return redirect()->route('seguridad.sucursales');
        }

        return view('seguridad.sucursal_form', ['s' => $s]);
    }

    public function sucursalGuardar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_sucursal', 0);
        $d = [
            'nombre' => trim((string) $request->input('nombre', '')),
            'ruc' => trim((string) $request->input('ruc', '')) ?: null,
            'telefono' => trim((string) $request->input('telefono', '')) ?: null,
            'direccion' => trim((string) $request->input('direccion', '')) ?: null,
            'ciudad' => trim((string) $request->input('ciudad', '')) ?: null,
        ];
        $volver = $id ? redirect()->route('seguridad.sucursal_form', $id) : redirect()->route('seguridad.sucursal_form');

        if ($d['nombre'] === '') {
            flash('El nombre de la sucursal es obligatorio.', 'error');

            return $volver->withInput();
        }
        if (DB::scalar('SELECT COUNT(*) FROM sucursal WHERE nombre = ? AND id_sucursal <> ?', [$d['nombre'], $id])) {
            flash('Ya existe una sucursal con ese nombre.', 'error');

            return $volver->withInput();
        }

        try {
            if ($id) {
                DB::update(
                    'UPDATE sucursal SET nombre=:nombre, ruc=:ruc, telefono=:telefono,
                        direccion=:direccion, ciudad=:ciudad WHERE id_sucursal=:id', $d + ['id' => $id]
                );
                Auditoria::registrar('MODIFICACION', 'Configuracion', 'sucursal', $id, $d['nombre']);
                flash('Sucursal actualizada.');
            } else {
                DB::insert(
                    'INSERT INTO sucursal (nombre,ruc,telefono,direccion,ciudad,activo)
                     VALUES (:nombre,:ruc,:telefono,:direccion,:ciudad,1)', $d
                );
                Auditoria::registrar('ALTA', 'Configuracion', 'sucursal', (int) DB::getPdo()->lastInsertId(), $d['nombre']);
                flash('Sucursal creada.');
            }
        } catch (Throwable) {
            flash('No se pudo guardar la sucursal.', 'error');

            return $volver->withInput();
        }

        return redirect()->route('seguridad.sucursales');
    }

    public function sucursalBaja(Request $request): RedirectResponse
    {
        DB::update('UPDATE sucursal SET activo = 1 - activo WHERE id_sucursal = ?',
            [(int) $request->input('id_sucursal', 0)]);
        flash('Estado de la sucursal actualizado.');

        return redirect()->route('seguridad.sucursales');
    }

    // ---------- Contacto y soporte ----------

    public function contacto(): View
    {
        $contactos = [];
        try {
            $contactos = DB::select('SELECT canal, valor, etiqueta FROM contacto_soporte ORDER BY orden, id_contacto');
        } catch (Throwable) {
            // la tabla se crea en la migración del sistema anterior
        }

        return view('seguridad.contacto', [
            'contactos' => $contactos,
            'canales' => Contacto::canales(),
        ]);
    }

    public function contactoGuardar(Request $request): RedirectResponse
    {
        $canales = (array) $request->input('canal', []);
        $valores = (array) $request->input('valor', []);
        $etiquetas = (array) $request->input('etiqueta', []);
        $definidos = Contacto::canales();
        $volver = redirect()->route('seguridad.contacto');

        // Se valida ANTES de tocar la base: si el valor no forma un enlace
        // usable, se avisa en vez de guardar algo que no lleva a ninguna parte.
        $contactos = [];
        foreach ($canales as $i => $canal) {
            $canal = (string) $canal;
            $valor = trim((string) ($valores[$i] ?? ''));
            if ($valor === '') {
                continue;
            }
            if (! isset($definidos[$canal])) {
                flash('Hay una fila con un medio de contacto que no existe.', 'error');

                return $volver;
            }
            if (mb_strlen($valor) > 160) {
                flash('El contacto de ' . $definidos[$canal]['etiqueta'] . ' es demasiado largo.', 'error');

                return $volver;
            }
            if (! Contacto::url($canal, $valor)) {
                flash('No se entiende el contacto de ' . $definidos[$canal]['etiqueta'] . '. '
                    . $definidos[$canal]['ayuda'], 'error');

                return $volver;
            }
            $contactos[] = [
                'canal' => $canal, 'valor' => $valor,
                'etiqueta' => mb_substr(trim((string) ($etiquetas[$i] ?? '')), 0, 40) ?: null,
            ];
        }

        if (count($contactos) > 12) {
            flash('Son demasiados medios de contacto: dejá los que la gente use de verdad.', 'error');

            return $volver;
        }

        try {
            DB::transaction(function () use ($contactos) {
                // Se rehace: lo que se borró del formulario deja de mostrarse
                DB::delete('DELETE FROM contacto_soporte');
                foreach ($contactos as $n => $c) {
                    DB::insert('INSERT INTO contacto_soporte (canal, valor, etiqueta, orden) VALUES (?,?,?,?)',
                        [$c['canal'], $c['valor'], $c['etiqueta'], $n]);
                }
            });

            Auditoria::registrar('MODIFICACION', 'Configuracion', 'contacto_soporte', null,
                $contactos ? count($contactos) . ' medio(s): ' . implode(', ', array_column($contactos, 'canal'))
                           : 'Se quitaron todos los medios');

            flash($contactos
                ? count($contactos) . ' medio(s) de contacto guardado(s). Ya aparecen en el pie, bajo «Centro de Ayuda y Soporte».'
                : 'Se quitaron los contactos: el bloque de ayuda deja de mostrarse en el pie.');
        } catch (Throwable) {
            flash('No se pudo guardar el contacto.', 'error');
        }

        return $volver;
    }

    // ---------- Roles y permisos ----------

    public function roles(): View
    {
        $admin = (int) config('permisos.rol_admin', 1);

        // Se ordenan por ALCANCE, no por id: ordenados por id, el Profesional
        // salía arriba del Asistente administrativo y la matriz se leía al
        // revés de lo que es.
        $roles = DB::select(
            'SELECT r.*, (SELECT COUNT(*) FROM usuario u WHERE u.id_rol = r.id_rol) AS usuarios,
                    (SELECT COUNT(*) FROM rol_modulo rm WHERE rm.id_rol = r.id_rol) AS accesos
               FROM rol r
              ORDER BY (r.id_rol = :admin) DESC,
                       r.es_personal DESC,
                       accesos DESC,
                       r.nombre',
            ['admin' => $admin]
        );

        // Las claves se traducen igual que al comprobar un permiso: si un rol
        // quedó guardado con los nombres viejos, la matriz tiene que mostrar
        // las casillas que ese rol realmente tiene, no todas en blanco.
        $crudo = [];
        foreach (DB::select('SELECT id_rol, modulo FROM rol_modulo') as $p) {
            $crudo[(int) $p->id_rol][] = (string) $p->modulo;
        }

        $perm = [];
        foreach ($crudo as $idRol => $claves) {
            $perm[$idRol] = array_fill_keys(Permisos::equivaler($claves), true);
        }

        $claves = Permisos::claves();
        foreach ($roles as $r) {
            $r->alcance = (int) $r->id_rol === $admin
                ? count($claves)
                : count(array_filter($claves, fn ($c) => Permisos::marcado($perm[(int) $r->id_rol] ?? [], $c)));
        }

        return view('seguridad.roles', [
            'roles' => $roles,
            'matriz' => Permisos::matriz(),
            'perm' => $perm,
            'totalClaves' => count($claves),
            'admin' => $admin,
            'protegidos' => [$admin, (int) config('permisos.rol_cliente', 4)],
            // Para avisarle SOLO a quien puede quedarse afuera: el rol propio
            // se edita en esta misma matriz, salvo que sea el Administrador
            // (que queda fuera de `$editables` al guardar).
            'miRol' => (int) session('rol', 0),
        ]);
    }

    public function rolCrear(Request $request): RedirectResponse
    {
        $nombre = trim((string) $request->input('nombre', ''));
        $desc = trim((string) $request->input('descripcion', '')) ?: null;
        $esPersonal = $request->boolean('es_personal') ? 1 : 0;
        $volver = redirect()->route('seguridad.roles');

        if ($nombre === '') {
            flash('El nombre del rol es obligatorio.', 'error');

            return $volver;
        }
        if (mb_strlen($nombre) > 60) {
            flash('El nombre del rol no puede superar los 60 caracteres.', 'error');

            return $volver;
        }
        if (DB::scalar('SELECT COUNT(*) FROM rol WHERE nombre = ?', [$nombre])) {
            flash('Ya existe un rol con ese nombre.', 'error');

            return $volver;
        }

        try {
            DB::insert('INSERT INTO rol (nombre, descripcion, es_personal, activo) VALUES (?,?,?,1)',
                [$nombre, $desc, $esPersonal]);
            $id = (int) DB::getPdo()->lastInsertId();

            if ($esPersonal) {
                $sel = (array) $request->input('modulos', []);
                foreach (Permisos::claves() as $m) {
                    if (in_array($m, $sel, true)) {
                        DB::insert('INSERT IGNORE INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$id, $m]);
                    }
                }
            }

            Permisos::olvidar();
            Auditoria::registrar('ALTA', 'Configuracion', 'rol', $id, $nombre);
            flash('Rol «' . $nombre . '» creado.');
        } catch (Throwable) {
            flash('No se pudo crear el rol.', 'error');
        }

        return $volver;
    }

    public function rolEditar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_rol', 0);
        $nombre = trim((string) $request->input('nombre', ''));
        $desc = trim((string) $request->input('descripcion', '')) ?: null;
        $volver = redirect()->route('seguridad.roles');

        $rol = DB::selectOne('SELECT * FROM rol WHERE id_rol = ?', [$id]);
        if (! $rol) {
            flash('Ese rol no existe.', 'error');

            return $volver;
        }
        if ($nombre === '') {
            flash('El nombre del rol es obligatorio.', 'error');

            return $volver;
        }
        if (DB::scalar('SELECT COUNT(*) FROM rol WHERE nombre = ? AND id_rol <> ?', [$nombre, $id])) {
            flash('Ya existe otro rol con ese nombre.', 'error');

            return $volver;
        }

        // es_personal solo se puede tocar en roles que no usa el código
        $esPersonal = $this->protegido($id) ? (int) $rol->es_personal : ($request->boolean('es_personal') ? 1 : 0);
        $activo = $id === (int) config('permisos.rol_admin', 1) ? 1 : ($request->boolean('activo') ? 1 : 0);

        DB::update('UPDATE rol SET nombre = ?, descripcion = ?, es_personal = ?, activo = ? WHERE id_rol = ?',
            [$nombre, $desc, $esPersonal, $activo, $id]);

        // Un rol que dejó de ser personal no debe conservar módulos del panel
        if (! $esPersonal) {
            DB::delete('DELETE FROM rol_modulo WHERE id_rol = ?', [$id]);
        }

        Permisos::olvidar();
        Auditoria::registrar('MODIFICACION', 'Configuracion', 'rol', $id, $nombre);
        flash('Rol actualizado.');

        return $volver;
    }

    public function rolBorrar(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id_rol', 0);
        $volver = redirect()->route('seguridad.roles');

        $rol = DB::selectOne('SELECT * FROM rol WHERE id_rol = ?', [$id]);
        if (! $rol) {
            flash('Ese rol no existe.', 'error');

            return $volver;
        }
        if ($this->protegido($id)) {
            flash('El rol «' . $rol->nombre . '» es parte del funcionamiento del sistema y no se puede '
                . 'eliminar. Podés desactivarlo o renombrarlo.', 'warning');

            return $volver;
        }

        $usuarios = (int) DB::scalar('SELECT COUNT(*) FROM usuario WHERE id_rol = ?', [$id]);
        if ($usuarios) {
            flash("No se puede eliminar: hay $usuarios usuario(s) con ese rol. Cambiales el rol primero.", 'warning');

            return $volver;
        }

        try {
            DB::transaction(function () use ($id) {
                DB::delete('DELETE FROM rol_modulo WHERE id_rol = ?', [$id]);
                DB::delete('DELETE FROM rol WHERE id_rol = ?', [$id]);
            });
            Permisos::olvidar();
            Auditoria::registrar('BAJA', 'Configuracion', 'rol', $id, 'Rol eliminado: ' . $rol->nombre);
            flash('Rol «' . $rol->nombre . '» eliminado.');
        } catch (Throwable) {
            flash('No se pudo eliminar el rol.', 'error');
        }

        return $volver;
    }

    /**
     * Guarda la matriz de permisos.
     *
     * Solo se aceptan las claves de la matriz, y lo que se guarda es siempre la
     * parte más chica —`facturacion.cobros`, no `facturacion`—, así el permiso
     * queda dicho una sola vez y sin ambigüedad.
     */
    public function permisosGuardar(Request $request): RedirectResponse
    {
        $matriz = (array) $request->input('perm', []);   // perm[id_rol][modulo] = 1
        $modulos = Permisos::claves();
        $volver = redirect()->route('seguridad.roles');

        // Solo roles de personal, excepto el Administrador (acceso total siempre)
        $editables = DB::select('SELECT id_rol FROM rol WHERE es_personal = 1 AND id_rol <> ?',
            [(int) config('permisos.rol_admin', 1)]);

        try {
            DB::transaction(function () use ($editables, $modulos, $matriz) {
                foreach ($editables as $r) {
                    $idr = (int) $r->id_rol;
                    DB::delete('DELETE FROM rol_modulo WHERE id_rol = ?', [$idr]);
                    foreach ($modulos as $m) {
                        if (! empty($matriz[$idr][$m])) {
                            DB::insert('INSERT INTO rol_modulo (id_rol, modulo) VALUES (?,?)', [$idr, $m]);
                        }
                    }
                }
            });
        } catch (Throwable) {
            flash('No se pudieron guardar los permisos.', 'error');

            return $volver;
        }

        Permisos::olvidar();
        Auditoria::registrar('MODIFICACION', 'Configuracion', 'rol_modulo', null, 'Actualizó permisos de roles');
        flash('Permisos de los roles actualizados.');

        return $volver;
    }

    // ---------- Auditoría ----------

    // La pantalla también baja: sin `StreamedResponse` en la firma, exportar la
    // auditoría reventaba con un TypeError en vez de devolver el archivo.
    public function auditoria(): View|StreamedResponse
    {
        // Las opciones salen de lo que hay cargado, no de una lista fija: así
        // aparecen solas las acciones y módulos nuevos sin tocar el código.
        $opc = function (string $col): array {
            $vals = array_map(fn ($r) => (string) $r->v,
                DB::select("SELECT DISTINCT `$col` v FROM auditoria WHERE `$col` IS NOT NULL AND `$col` <> '' ORDER BY `$col`"));

            return ['' => 'Todos'] + array_combine($vals, $vals);
        };

        $f = Listado::filtros([
            'q' => ['tipo' => 'texto', 'etiqueta' => 'Buscar', 'ph' => 'Detalle, tabla o usuario', 'ancho' => '250px'],
            'accion' => ['tipo' => 'select', 'etiqueta' => 'Acción', 'opciones' => $opc('accion')],
            'modulo' => ['tipo' => 'select', 'etiqueta' => 'Módulo', 'opciones' => $opc('modulo')],
            'desde' => ['tipo' => 'fecha', 'etiqueta' => 'Desde'],
            'hasta' => ['tipo' => 'fecha', 'etiqueta' => 'Hasta'],
        ]);
        $f['csv'] = true;

        $w = ['1=1'];
        $par = [];
        if (Listado::hay($f, 'q')) {
            $w[] = Listado::likeVarias(['a.detalle', 'a.tabla_afectada', "CONCAT(pe.nombre,' ',pe.apellido)"],
                Listado::valor($f, 'q'), 'q', $par);
        }
        if (Listado::hay($f, 'accion')) {
            $w[] = 'a.accion = :ac';
            $par['ac'] = Listado::valor($f, 'accion');
        }
        if (Listado::hay($f, 'modulo')) {
            $w[] = 'a.modulo = :mo';
            $par['mo'] = Listado::valor($f, 'modulo');
        }
        // OJO: la columna de `auditoria` es `fecha_hora`, no `fecha`. Escrita
        // como `a.fecha` la pantalla contestaba 500 en todas sus consultas.
        if (Listado::hay($f, 'desde')) {
            $w[] = 'DATE(a.fecha_hora) >= :d';
            $par['d'] = Listado::valor($f, 'desde');
        }
        if (Listado::hay($f, 'hasta')) {
            $w[] = 'DATE(a.fecha_hora) <= :h';
            $par['h'] = Listado::valor($f, 'hasta');
        }

        $desde = 'FROM auditoria a
                  JOIN usuario u  ON u.id_usuario = a.id_usuario
                  JOIN persona pe ON pe.id_persona = u.id_persona
                  WHERE ' . implode(' AND ', $w);
        $cols = "a.fecha_hora AS fecha, a.accion, a.modulo, a.tabla_afectada, a.id_registro, a.detalle,
                 CONCAT(pe.nombre,' ',pe.apellido) AS usuario";

        if (Listado::pideExport()) {
            return Listado::exportar('auditoria',
                ['Fecha', 'Usuario', 'Acción', 'Módulo', 'Tabla', 'Registro', 'Detalle'],
                array_map(fn ($r) => [fecha($r->fecha, 'd/m/Y H:i'), $r->usuario, $r->accion,
                    $r->modulo, $r->tabla_afectada, $r->id_registro, $r->detalle],
                    DB::select("SELECT $cols $desde ORDER BY a.fecha_hora DESC", $par)),
                $f, 'Auditoría'
            );
        }

        $pag = Listado::paginacion((int) DB::scalar("SELECT COUNT(*) $desde", $par), 30);

        return view('seguridad.auditoria', [
            'rows' => DB::select("SELECT $cols $desde ORDER BY a.fecha_hora DESC LIMIT {$pag['porPagina']} OFFSET {$pag['offset']}", $par),
            'f' => $f,
            'pag' => $pag,
        ]);
    }

    /** El Administrador y el Cliente los usa el código: no se borran. */
    private function protegido(int $idRol): bool
    {
        return in_array($idRol, [
            (int) config('permisos.rol_admin', 1),
            (int) config('permisos.rol_cliente', 4),
        ], true);
    }
}
