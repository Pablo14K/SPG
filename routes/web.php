<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitasController;
use App\Http\Controllers\CitaTokenController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SeguridadController;
use App\Http\Controllers\ServiciosController;
use App\Http\Controllers\WebauthnController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del sistema
|--------------------------------------------------------------------------
|
| Reemplazan al front controller `index.php?r=modulo/accion`. Cada ruta lleva
| nombre —el mismo que usa el catálogo de pantallas de config/navegacion.php—
| y declara al lado el permiso que hace falta para entrar, así se ve de un
| vistazo quién puede abrir qué.
|
| Mientras dure la migración conviven módulos ya migrados y módulos que no.
| El menú y los accesos rápidos preguntan por Route::has() antes de dibujar un
| enlace, así que las pantallas van apareciendo a medida que se migran.
|
*/

Route::get('/', fn () => redirect()->route('login'));

// --- Entrar y salir -------------------------------------------------------
Route::get('entrar', [AuthController::class, 'formulario'])->name('login');
Route::post('entrar', [AuthController::class, 'entrar'])->middleware('throttle:10,1');

// Salir es POST: con GET lo dispararía cualquier enlace, una precarga del
// navegador o una imagen incrustada en otra página.
Route::post('salir', [AuthController::class, 'salir'])->name('salir');

// --- Ingreso con huella ---------------------------------------------------
// Las dos primeras son públicas: hacen falta ANTES de tener sesión.
Route::post('huella/opciones', [WebauthnController::class, 'opcionesLogin'])
    ->name('webauthn.auth_options')->middleware('throttle:20,1');
Route::post('huella/entrar', [WebauthnController::class, 'login'])
    ->name('webauthn.login')->middleware('throttle:20,1');

Route::middleware('sesion')->group(function () {
    Route::get('huella/activar', [WebauthnController::class, 'preguntar'])->name('webauthn.preguntar');
    Route::post('huella/preguntado', [WebauthnController::class, 'marcarPreguntado'])->name('webauthn.preguntado');
    Route::post('huella/registro/opciones', [WebauthnController::class, 'opcionesRegistro'])->name('webauthn.reg_options');
    Route::post('huella/registro', [WebauthnController::class, 'registrar'])->name('webauthn.registrar');
    Route::post('huella/desactivar', [WebauthnController::class, 'desactivar'])->name('webauthn.desactivar');
});

// --- Registro y recuperación (sin sesión) ---------------------------------
Route::get('registro', [AuthController::class, 'registro'])->name('registro');
Route::post('registro', [AuthController::class, 'registrar'])->middleware('throttle:6,1');

Route::get('verificar', [AuthController::class, 'verificar'])->name('verificar');
Route::post('verificar', [AuthController::class, 'verificarGuardar'])->middleware('throttle:10,1');

Route::get('recuperar', [AuthController::class, 'recuperar'])->name('recuperar');
Route::post('recuperar', [AuthController::class, 'recuperarEnviar'])->middleware('throttle:6,1');

Route::get('recuperar/codigo', [AuthController::class, 'recuperarCodigo'])->name('recuperar.codigo');
Route::post('recuperar/codigo', [AuthController::class, 'recuperarGuardar'])->middleware('throttle:10,1');

// --- Mi cuenta (personal y clientas por igual) ----------------------------
Route::middleware('sesion')->prefix('cuenta')->name('cuenta.')->group(function () {
    Route::get('/', [CuentaController::class, 'index'])->name('index');
    Route::post('password', [CuentaController::class, 'password'])->name('password');
    Route::match(['get', 'post'], 'password/confirmar', [CuentaController::class, 'passwordConfirmar'])
        ->name('password_confirmar');
    Route::post('password/cancelar', [CuentaController::class, 'passwordCancelar'])->name('password_cancelar');
    // El tema de la interfaz: preferencia de cada persona, no del salón.
    Route::post('tema', [CuentaController::class, 'tema'])->name('tema');
});

// --- La cita desde el enlace del correo (SIN sesión) ----------------------
// La credencial es el token: la mayoría de las clientas que agendan en el
// local no tienen cuenta. Por eso estas tres rutas quedan fuera del middleware
// de sesión.
Route::get('mi-cita', [CitaTokenController::class, 'ver'])->name('cita.token');
Route::post('mi-cita', [CitaTokenController::class, 'guardar'])
    ->name('cita.token.guardar')->middleware('throttle:20,1');
Route::get('mi-cita/calendario', [CitaTokenController::class, 'calendario'])->name('cita.calendario');

// --- Portal de la clienta -------------------------------------------------
// No lleva el middleware `personal`: acá entra quien NO es personal. Cada
// acción toma el id de cliente de la sesión, nunca del formulario.
Route::middleware('sesion')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('index');
    Route::get('reservar', [PortalController::class, 'reservar'])->name('reservar');
    Route::post('reservar', [PortalController::class, 'guardarReserva'])->name('guardar_reserva');
    Route::get('disponibilidad', [PortalController::class, 'disponibilidad'])->name('disponibilidad');
    Route::get('citas', [PortalController::class, 'citas'])->name('citas');
    Route::post('cancelar', [PortalController::class, 'cancelar'])->name('cancelar');
    Route::get('atencion', [PortalController::class, 'atencion'])->name('atencion');
    Route::get('atencion/json', [PortalController::class, 'atencionJson'])->name('atencion_json');
    Route::post('pedir', [PortalController::class, 'pedir'])->name('pedir');
    Route::get('promociones', [PortalController::class, 'promociones'])->name('promociones');
    Route::get('valoraciones', [PortalController::class, 'valoraciones'])->name('valoraciones');
    Route::post('calificar', [PortalController::class, 'calificar'])->name('calificar');
    Route::match(['get', 'post'], 'recordatorios', [PortalController::class, 'preferencias'])->name('preferencias');
});

// --- Panel de gestión -----------------------------------------------------
Route::middleware(['sesion', 'personal'])->group(function () {
    Route::get('panel', [PanelController::class, 'index'])->name('panel');

    // --- Citas y agenda ---------------------------------------------------
    Route::prefix('citas')->name('citas.')->group(function () {
        Route::get('/', [CitasController::class, 'index'])->name('index')->middleware('modulo:citas');

        Route::middleware('modulo:citas.agenda')->group(function () {
            Route::get('agenda', [CitasController::class, 'agenda'])->name('agenda');
            Route::get('nueva', [CitasController::class, 'form'])->name('form');
            Route::post('guardar', [CitasController::class, 'guardar'])->name('guardar');
            Route::post('estado', [CitasController::class, 'estado'])->name('estado');
            Route::post('cancelar', [CitasController::class, 'cancelar'])->name('cancelar');
            Route::post('reprogramar', [CitasController::class, 'reprogramar'])->name('reprogramar');
            // Lo consume el selector de disponibilidad de Nueva cita
            Route::get('disponibilidad', [CitasController::class, 'disponibilidad'])->name('disponibilidad');
        });

        Route::middleware('modulo:citas.atencion')->group(function () {
            Route::get('atender', [CitasController::class, 'atender'])->name('atender');
            Route::post('atender', [CitasController::class, 'atenderGuardar'])->name('atender.guardar');
            Route::post('pedido-visto', [CitasController::class, 'pedidoVisto'])->name('pedido_visto');
        });

        // El alta rápida de cliente pide el permiso de Clientes, no el de Citas
        Route::post('cliente-rapido', [CitasController::class, 'clienteRapido'])
            ->name('cliente_rapido')->middleware('modulo:clientes.registro');

        Route::middleware('modulo:citas.ausencias')->group(function () {
            Route::get('excepciones', [CitasController::class, 'ausencias'])->name('ausencias');
            Route::post('excepciones', [CitasController::class, 'ausenciaGuardar'])->name('ausencia.guardar');
        });
    });

    // --- Clientes ---------------------------------------------------------
    // El landing pide el módulo padre; cada pantalla, su clave fina.
    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [ClientesController::class, 'index'])->name('index')->middleware('modulo:clientes');

        Route::middleware('modulo:clientes.registro')->group(function () {
            Route::get('lista', [ClientesController::class, 'lista'])->name('lista');
            // Una sola ruta para alta y edición: route('clientes.form') abre el
            // formulario vacío y route('clientes.form', 5) trae al cliente 5.
            Route::get('form/{id?}', [ClientesController::class, 'form'])->whereNumber('id')->name('form');
            Route::post('guardar', [ClientesController::class, 'guardar'])->name('guardar');
            Route::post('baja', [ClientesController::class, 'baja'])->name('baja');
            Route::get('{id}/historial', [ClientesController::class, 'historial'])->whereNumber('id')->name('historial');
        });

        Route::get('fidelizacion', [ClientesController::class, 'fidelizacion'])
            ->name('fidelizacion')->middleware('modulo:clientes.fidelizacion');
        Route::get('valoraciones', [ClientesController::class, 'valoraciones'])
            ->name('valoraciones')->middleware('modulo:clientes.valoraciones');
    });

    // --- Reportes ---------------------------------------------------------
    Route::middleware('modulo:reportes')->group(function () {
        Route::get('reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('reportes/imprimir', [ReportesController::class, 'imprimir'])->name('reportes.imprimir');
    });

    // --- Seguridad --------------------------------------------------------
    // Las cuentas del personal y lo que cada rol puede hacer, en un solo
    // módulo. Las pantallas se reparten entre dos controladores: PersonalController
    // (usuarios, turnos, comisiones, asistencia) y ConfiguracionController
    // (sucursales, roles, contacto, auditoría).
    Route::prefix('seguridad')->name('seguridad.')->group(function () {
        Route::get('/', [SeguridadController::class, 'index'])->name('index')->middleware('modulo:seguridad');

        // Ver la lista pide el submódulo; crear y editar cuentas es exclusivo
        // del Administrador, sin importar lo que diga la matriz de roles.
        Route::get('usuarios', [PersonalController::class, 'usuarios'])
            ->name('usuarios')->middleware('modulo:seguridad.usuarios');

        Route::middleware('admin')->group(function () {
            Route::get('usuarios/form/{id?}', [PersonalController::class, 'usuarioForm'])
                ->whereNumber('id')->name('usuario_form');
            Route::post('usuarios/guardar', [PersonalController::class, 'usuarioGuardar'])->name('usuario.guardar');
            Route::post('usuarios/baja', [PersonalController::class, 'usuarioBaja'])->name('usuario.baja');
            Route::post('sucursal-rapida', [PersonalController::class, 'sucursalRapida'])->name('sucursal.rapida');
        });

        Route::middleware('modulo:seguridad.turnos')->group(function () {
            Route::get('turnos', [PersonalController::class, 'turnos'])->name('turnos');
            Route::post('turnos/guardar', [PersonalController::class, 'turnoGuardar'])->name('turno.guardar');
            Route::post('turnos/baja', [PersonalController::class, 'turnoBaja'])->name('turno.baja');
            Route::post('turnos/rapido', [PersonalController::class, 'turnoRapido'])->name('turno.rapido');
        });

        Route::middleware('modulo:seguridad.comisiones')->group(function () {
            Route::get('comisiones', [PersonalController::class, 'comisiones'])->name('comisiones');
            Route::get('comisiones/nueva', [PersonalController::class, 'comisionForm'])->name('comision_form');
            Route::post('comisiones/guardar', [PersonalController::class, 'comisionGuardar'])->name('comision.guardar');
        });

        Route::middleware('modulo:seguridad.asistencia')->group(function () {
            Route::get('asistencia', [PersonalController::class, 'asistencia'])->name('asistencia');
            Route::post('asistencia', [PersonalController::class, 'asistenciaMarcar'])->name('asistencia.marcar');
        });

        Route::middleware('modulo:seguridad.sucursales')->group(function () {
            Route::get('sucursales', [ConfiguracionController::class, 'sucursales'])->name('sucursales');
            Route::get('sucursales/form/{id?}', [ConfiguracionController::class, 'sucursalForm'])
                ->whereNumber('id')->name('sucursal_form');
            Route::post('sucursales/guardar', [ConfiguracionController::class, 'sucursalGuardar'])->name('sucursal.guardar');
            Route::post('sucursales/baja', [ConfiguracionController::class, 'sucursalBaja'])->name('sucursal.baja');
        });

        Route::middleware('modulo:seguridad.contacto')->group(function () {
            Route::get('contacto', [ConfiguracionController::class, 'contacto'])->name('contacto');
            Route::post('contacto', [ConfiguracionController::class, 'contactoGuardar'])->name('contacto.guardar');
        });

        Route::middleware('modulo:seguridad.roles')->group(function () {
            Route::get('roles', [ConfiguracionController::class, 'roles'])->name('roles');
            Route::post('roles/crear', [ConfiguracionController::class, 'rolCrear'])->name('rol.crear');
            Route::post('roles/editar', [ConfiguracionController::class, 'rolEditar'])->name('rol.editar');
            Route::post('roles/borrar', [ConfiguracionController::class, 'rolBorrar'])->name('rol.borrar');
            Route::post('roles/permisos', [ConfiguracionController::class, 'permisosGuardar'])->name('permisos.guardar');
        });

        Route::get('auditoria', [ConfiguracionController::class, 'auditoria'])
            ->name('auditoria')->middleware('modulo:seguridad.auditoria');
    });

    // --- Inventario -------------------------------------------------------
    Route::prefix('inventario')->name('inventario.')->group(function () {
        Route::get('/', [InventarioController::class, 'index'])->name('index')->middleware('modulo:inventario');

        Route::middleware('modulo:inventario.productos')->group(function () {
            Route::get('productos', [InventarioController::class, 'productos'])->name('productos');
            Route::get('productos/form/{id?}', [InventarioController::class, 'productoForm'])
                ->whereNumber('id')->name('producto_form');
            Route::post('productos/guardar', [InventarioController::class, 'productoGuardar'])->name('producto.guardar');
            Route::post('productos/baja', [InventarioController::class, 'productoBaja'])->name('producto.baja');
            Route::post('productos/rapido', [InventarioController::class, 'productoRapido'])->name('producto.rapido');

            Route::get('categorias', [InventarioController::class, 'categorias'])->name('categorias');
            Route::post('categorias/crear', [InventarioController::class, 'categoriaCrear'])->name('categoria.crear');
            Route::post('categorias/editar', [InventarioController::class, 'categoriaEditar'])->name('categoria.editar');
            Route::post('categorias/borrar', [InventarioController::class, 'categoriaBorrar'])->name('categoria.borrar');
        });

        Route::middleware('modulo:inventario.stock')->group(function () {
            Route::get('stock', [InventarioController::class, 'stock'])->name('stock');
            Route::get('movimientos', [InventarioController::class, 'movimientos'])->name('movimientos');
            Route::get('cargar-stock', [InventarioController::class, 'ajuste'])->name('ajuste');
            Route::post('cargar-stock', [InventarioController::class, 'ajusteGuardar'])->name('ajuste.guardar');
        });

        Route::middleware('modulo:inventario.proveedores')->group(function () {
            Route::get('proveedores', [InventarioController::class, 'proveedores'])->name('proveedores');
            Route::get('proveedores/form/{id?}', [InventarioController::class, 'proveedorForm'])
                ->whereNumber('id')->name('proveedor_form');
            Route::post('proveedores/guardar', [InventarioController::class, 'proveedorGuardar'])->name('proveedor.guardar');
            Route::post('proveedores/baja', [InventarioController::class, 'proveedorBaja'])->name('proveedor.baja');
            Route::post('proveedores/rapido', [InventarioController::class, 'proveedorRapido'])->name('proveedor.rapido');
        });

        Route::middleware('modulo:inventario.compras')->group(function () {
            Route::get('compras', [InventarioController::class, 'compras'])->name('compras');
            Route::get('compras/ver', [InventarioController::class, 'compraVer'])->name('compra_ver');
            Route::get('compras/nueva', [InventarioController::class, 'compraForm'])->name('compra_form');
            Route::post('compras/guardar', [InventarioController::class, 'compraGuardar'])->name('compra.guardar');
        });
    });

    // --- Facturación y caja -----------------------------------------------
    Route::prefix('facturacion')->name('facturacion.')->group(function () {
        Route::get('/', [FacturacionController::class, 'index'])->name('index')->middleware('modulo:facturacion');

        Route::middleware('modulo:facturacion.facturas')->group(function () {
            Route::get('facturas', [FacturacionController::class, 'facturas'])->name('facturas');
            Route::get('comprobante', [FacturacionController::class, 'facturaVer'])->name('factura_ver');
            Route::get('emitir', [FacturacionController::class, 'emitir'])->name('emitir');
            Route::post('emitir', [FacturacionController::class, 'emitirGuardar'])->name('emitir.guardar');
            // Los datos del receptor que exige el manual del SIFEN. Se piden
            // ANTES de emitir: un rechazo de la DNIT por un dato mal cargado
            // no se reintenta, hay que anular el comprobante y hacer otro.
            Route::get('receptor', [FacturacionController::class, 'receptor'])->name('receptor');
            Route::post('receptor', [FacturacionController::class, 'receptorGuardar'])->name('receptor.guardar');
            Route::post('anular-factura', [FacturacionController::class, 'anularFactura'])->name('factura.anular');
            Route::post('nota-credito', [FacturacionController::class, 'notaCredito'])->name('nota_credito');
            // Declarar el comprobante ante la DNIT. Va aparte de emitir: la
            // factura ya es válida, y un servicio caído no puede frenar el cobro.
            Route::post('sifen/enviar', [FacturacionController::class, 'sifenEnviar'])->name('sifen.enviar');
        });

        Route::middleware('modulo:facturacion.cobros')->group(function () {
            Route::get('cobros', [FacturacionController::class, 'cobros'])->name('cobros');
            Route::post('cobrar', [FacturacionController::class, 'cobrar'])->name('cobrar');
            Route::post('anular-cobro', [FacturacionController::class, 'anularCobro'])->name('cobro.anular');
            Route::post('sena', [FacturacionController::class, 'sena'])->name('sena');
        });

        Route::middleware('modulo:facturacion.caja')->group(function () {
            Route::get('caja', [FacturacionController::class, 'caja'])->name('caja');
            Route::post('caja/abrir', [FacturacionController::class, 'abrirCaja'])->name('caja.abrir');
            Route::post('caja/cerrar', [FacturacionController::class, 'cerrarCaja'])->name('caja.cerrar');
        });

        Route::middleware('modulo:facturacion.pagos')->group(function () {
            Route::get('pagos', [FacturacionController::class, 'pagos'])->name('pagos');
            Route::post('pagos/personal', [FacturacionController::class, 'pagarPersonal'])->name('pagar_personal');
            Route::post('pagos/revertir', [FacturacionController::class, 'revertirPagoPersonal'])->name('revertir_pago_personal');
        });

        Route::middleware('modulo:facturacion.proveedores')->group(function () {
            Route::get('proveedores', [FacturacionController::class, 'proveedores'])->name('proveedores');
            Route::post('proveedores/pagar', [FacturacionController::class, 'pagarProveedor'])->name('pagar_proveedor');
            Route::post('proveedores/anular', [FacturacionController::class, 'anularPagoProveedor'])->name('anular_pago_proveedor');
        });

        Route::middleware('modulo:facturacion.timbrados')->group(function () {
            Route::get('timbrados', [FacturacionController::class, 'timbrados'])->name('timbrados');
            Route::post('timbrados/guardar', [FacturacionController::class, 'timbradoGuardar'])->name('timbrado.guardar');
            Route::post('timbrados/baja', [FacturacionController::class, 'timbradoBaja'])->name('timbrado.baja');
        });
    });

    // --- Servicios --------------------------------------------------------
    Route::prefix('servicios')->name('servicios.')->group(function () {
        Route::get('/', [ServiciosController::class, 'index'])->name('index')->middleware('modulo:servicios');

        Route::middleware('modulo:servicios.catalogo')->group(function () {
            Route::get('lista', [ServiciosController::class, 'lista'])->name('lista');
            Route::get('form/{id?}', [ServiciosController::class, 'form'])->whereNumber('id')->name('form');
            Route::post('guardar', [ServiciosController::class, 'guardar'])->name('guardar');
            Route::post('baja', [ServiciosController::class, 'baja'])->name('baja');
        });

        Route::middleware('modulo:servicios.categorias')->group(function () {
            Route::get('categorias', [ServiciosController::class, 'categorias'])->name('categorias');
            Route::post('categorias/crear', [ServiciosController::class, 'categoriaCrear'])->name('categoria.crear');
            Route::post('categorias/editar', [ServiciosController::class, 'categoriaEditar'])->name('categoria.editar');
            Route::post('categorias/borrar', [ServiciosController::class, 'categoriaBorrar'])->name('categoria.borrar');
        });

        Route::middleware('modulo:servicios.descuentos')->group(function () {
            Route::get('descuentos', [ServiciosController::class, 'descuentos'])->name('descuentos');
            Route::get('descuentos/form/{id?}', [ServiciosController::class, 'descuentoForm'])
                ->whereNumber('id')->name('descuento_form');
            Route::post('descuentos/guardar', [ServiciosController::class, 'descuentoGuardar'])->name('descuento.guardar');
            Route::post('descuentos/baja', [ServiciosController::class, 'descuentoBaja'])->name('descuento.baja');
        });
    });
});
