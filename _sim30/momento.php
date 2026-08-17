<?php
/**
 * Un «momento» del día simulado: se corre como proceso aparte, con el reloj de
 * PHP falseado (libfaketime) y el de MariaDB sincronizado. argv: dia fase
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

use Illuminate\Support\Facades\DB;

$DIA = (int) ($argv[1] ?? 1);          // 1..90
$FASE = (string) ($argv[2] ?? 'apertura');
$HOY = date('Y-m-d');
$DOW = (int) date('N');                 // 1=lunes .. 7=domingo

mt_srand($DIA * 977 + crc32($FASE));

const PASS_PROF = 'profesional123';
const PROFS = [10 => 'marta', 11 => 'rocio', 12 => 'lucia', 13 => 'sofia'];
const TURNO_MANANA = 3;
const TURNO_TARDE = 4;
const MANANA = [10 => 'marta', 12 => 'lucia'];
const TARDE = [11 => 'rocio', 13 => 'sofia'];

function elegir(array $a) { return $a[array_rand($a)]; }
function chance(int $pct): bool { return mt_rand(1, 100) <= $pct; }

// Demanda: sábado alto, lunes bajo, domingo cerrado
function demanda(int $dow, int $dia): int
{
    // **Alta densidad**: la base triplica la de una operación normal. No es un
    // número inventado — es lo que hace falta para que 30 días sometan a la
    // agenda, la caja y el stock a la presión de varios meses de uso real.
    if ($dow === 7) return 0;
    // Bajada a pedido del usuario para que la corrida entre en 30 minutos.
    // **Se sacrifica volumen, no cobertura**: siguen todos los módulos, todos
    // los escenarios y todas las comprobaciones; lo que baja es cuántas
    // operaciones de cada tipo. La forma de la curva —lunes flojo, sábado
    // fuerte, picos y valles— se conserva, que es lo que prueba el sistema
    // bajo cambios bruscos de carga.
    $base = [1 => 6, 2 => 8, 3 => 8, 4 => 9, 5 => 12, 6 => 14][$dow] ?? 7;

    // Picos de máxima presión: fin de mes, quincena y un fin de semana largo.
    if (in_array($dia, [13, 14, 15], true))     $base = (int) ($base * 1.7);
    if (in_array($dia, [27, 28, 29, 30], true)) $base = (int) ($base * 1.9);
    // Días flojos, para ver el sistema bajo cambios bruscos de carga.
    if (in_array($dia, [8, 20], true))          $base = (int) ($base * 0.35);

    return max(0, $base + mt_rand(-3, 4));
}

$NOMBRES = ['Andrea','Lorena','Patricia','Verónica','Cynthia','Liliana','Gabriela','Romina','Fátima','Nathalia',
            'Mirta','Silvia','Carolina','Estela','Rossana','Blanca','Elvira','Maribel','Zunilda','Perla',
            'Diego','Gustavo','Hugo','Óscar','Rubén','Javier','Aldo','Nelson'];
$APELLIDOS = ['Villalba','Benítez','Ramírez','Ovelar','Fernández','Ayala','Rojas','Cabral','Insfrán','Gaona',
              'Riveros','Núñez','Bogado','Aquino','Vera','Escobar','Franco','Mendoza','Chávez','Barreto'];

// ---------------------------------------------------------------------------
//  Utilidades de estado
// ---------------------------------------------------------------------------

function clienteAlAzar(): ?int
{
    $r = DB::selectOne('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY RAND() LIMIT 1');
    return $r ? (int) $r->id_cliente : null;
}

/** Clientes recurrentes: los que ya vinieron alguna vez. */
function clienteRecurrente(): ?int
{
    $r = DB::selectOne('SELECT c.id_cliente FROM cliente c
                         WHERE c.activo=1 AND EXISTS (SELECT 1 FROM cita ci WHERE ci.id_cliente=c.id_cliente)
                         ORDER BY RAND() LIMIT 1');
    return $r ? (int) $r->id_cliente : null;
}

function cajaAbierta(): ?object
{
    return DB::selectOne('SELECT id_caja, id_usuario, monto_inicial FROM caja WHERE id_estado_caja = 1 ORDER BY id_caja DESC LIMIT 1');
}

// ---------------------------------------------------------------------------
//  FASES
// ---------------------------------------------------------------------------

/** Día 1: el salón se pone en marcha. */
function faseInit(): void
{
    $n = new Nav();
    if (! $n->entrar('admin', 'admin123')) return;

    // 1) Una recepcionista (Asistente administrativo)
    $n->post('/seguridad/usuarios/guardar', [
        'id_rol' => 3, 'id_sucursal' => 1, 'username' => 'recepcion',
        'nombre' => 'Claudia', 'apellido' => 'Ortiz', 'cedula' => '3900555',
        'telefono' => '0981200500', 'email' => 'claudia.ortiz@peluqueria.local',
        'password' => 'recepcion123', 'sucursales' => [1], 'turnos' => [],
    ])->seguir();
    sim_esperado($n, 'Usuario creado', 'INIT_USUARIO', 'Alta de la recepcionista');

    // 2) Un profesional más, con turno mañana
    $n->post('/seguridad/usuarios/guardar', [
        'id_rol' => 2, 'id_sucursal' => 1, 'username' => 'karen',
        'nombre' => 'Karen', 'apellido' => 'Giménez', 'cedula' => '3900666',
        'telefono' => '0981200600', 'email' => 'karen.gimenez@peluqueria.local',
        'password' => PASS_PROF, 'sucursales' => [1], 'turnos' => [TURNO_MANANA],
    ])->seguir();

    // 3) Comisión para karen (las otras ya tienen)
    $idk = (int) (DB::scalar("SELECT id_usuario FROM usuario WHERE username='karen'") ?: 0);
    if ($idk) {
        $n->post('/seguridad/comisiones/guardar', [
            'id_usuario' => $idk, 'id_servicio' => 0, 'tipo' => 'PORCENTAJE',
            'valor' => '15', 'vigente_desde' => date('Y-m-d'),
        ])->seguir();
    }

    // 4) Clientes cargados desde el mostrador
    global $NOMBRES, $APELLIDOS;
    for ($i = 0; $i < 14; $i++) {
        $nom = $NOMBRES[$i % count($NOMBRES)];
        $ape = $APELLIDOS[$i % count($APELLIDOS)];
        $n->post('/clientes/guardar', [
            'nombre' => $nom, 'apellido' => $ape,
            'cedula' => (string) (4100000 + $i * 7),
            'telefono' => '098' . str_pad((string) (1300000 + $i * 137), 7, '0', STR_PAD_LEFT),
            'email' => strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nom . '.' . $ape)) . $i . '@correo.com.py',
            'fecha_nacimiento' => '19' . (70 + ($i % 25)) . '-0' . (1 + $i % 9) . '-1' . ($i % 9),
            'direccion' => 'Barrio ' . ($i % 6 + 1) . ', Luque',
        ])->seguir();
    }

    // 5) Compra inicial de insumos (contado) → deja stock
    $prov = (int) DB::scalar('SELECT MIN(id_proveedor) FROM proveedor WHERE activo=1');
    $prods = DB::select('SELECT id_producto, nombre, precio_costo FROM producto ORDER BY id_producto');
    $d = ['id_proveedor' => $prov, 'id_condicion_venta' => 1, 'nro_factura_proveedor' => '001-001-0009001',
          'observaciones' => 'Carga inicial de insumos',
          'nombre' => [], 'id_producto' => [], 'cantidad' => [], 'precio' => [], 'categoria' => []];
    foreach ($prods as $k => $p) {
        $d['nombre'][$k] = $p->nombre;
        $d['id_producto'][$k] = $p->id_producto;
        $d['cantidad'][$k] = (string) (12 + $k);
        $d['precio'][$k] = (string) (int) $p->precio_costo;
        $d['categoria'][$k] = '';
    }
    $n->post('/inventario/compras/guardar', $d)->seguir();
    sim_esperado($n, 'Compra registrada', 'INIT_COMPRA', 'Compra inicial de insumos');

    // 6) Una promoción vigente sobre coloración y mechas
    $n->post('/servicios/descuentos/guardar', [
        'nombre' => 'Promo color agosto', 'tipo' => 'PORCENTAJE', 'valor' => '10',
        'fecha_inicio' => date('Y-m-d'), 'fecha_fin' => date('Y-m-d', strtotime('+45 day')),
        'servicios' => [6, 8],
    ])->seguir();

    $n->salir();
    sim_log(['tipo' => 'FASE', 'f' => 'init', 'ok' => true]);
}

function faseApertura(int $dia, int $dow): void
{
    if ($dow === 7) return;
    $n = new Nav();
    if (! $n->entrar('recepcion', 'recepcion123')) return;

    // Caja del día
    if (! cajaAbierta()) {
        $n->post('/facturacion/caja/abrir', ['monto_inicial' => '300.000'])->seguir();
        sim_esperado($n, 'Caja abierta', 'CAJA_ABRIR', 'Apertura de caja del día ' . $dia);
    }

    // Fichaje de entrada del turno mañana
    foreach (MANANA as $id => $u) {
        // Algún día alguien falta
        if ($dia % 23 === 0 && $id === 12) {
            $n->post('/seguridad/asistencia', ['accion' => 'falta_con', 'id_turno' => TURNO_MANANA,
                'id_usuario' => $id, 'fecha' => date('Y-m-d'), 'motivo_ausencia' => 'Consulta médica'])->seguir();
            continue;
        }
        $n->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => TURNO_MANANA,
            'id_usuario' => $id, 'fecha' => date('Y-m-d')])->seguir();
    }
    // karen, si existe
    $idk = (int) (DB::scalar("SELECT id_usuario FROM usuario WHERE username='karen' AND activo=1") ?: 0);
    if ($idk) {
        $n->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => TURNO_MANANA,
            'id_usuario' => $idk, 'fecha' => date('Y-m-d')])->seguir();
    }

    $n->salir();
}

/** Reserva citas para los próximos días, usando los huecos que ofrece el sistema. */
function reservar(Nav $n, int $cuantas, int $dia): int
{
    $hechas = 0;
    $catalogo = array_map(fn ($r) => (int) $r->id_servicio,
        DB::select('SELECT id_servicio FROM servicio WHERE activo=1'));
    if (! $catalogo) return 0;

    for ($i = 0; $i < $cuantas; $i++) {
        $cli = chance(65) ? (clienteRecurrente() ?? clienteAlAzar()) : clienteAlAzar();
        if (! $cli) break;

        // 1 a 2 servicios; a veces con profesional elegido
        $servs = [elegir($catalogo)];
        if (chance(30)) {
            $otro = elegir($catalogo);
            if ($otro !== $servs[0]) $servs[] = $otro;
        }

        // Casi siempre la clienta pide un día concreto y se le miran las horas
        // de ESE día; cada tanto se abre el calendario entero.
        if (chance(70)) {
            $fecha = date('Y-m-d', strtotime('+' . mt_rand(1, 12) . ' day'));
        } else {
            $n->get('/citas/disponibilidad', ['servicios' => $servs]);
            $j = json_decode($n->body, true);
            if (! is_array($j) || empty($j['ok']) || empty($j['dias'])) continue;
            $dias = array_values(array_filter($j['dias'], fn ($d) => $d > date('Y-m-d') && $d <= date('Y-m-d', strtotime('+12 day'))));
            if (! $dias) continue;
            $fecha = elegir($dias);
        }

        $n->get('/citas/disponibilidad', ['servicios' => $servs, 'fecha' => $fecha]);
        $j2 = json_decode($n->body, true);
        if (empty($j2['horas'])) continue;
        $slot = elegir($j2['horas']);
        $hora = $slot['hora'];
        $profes = $slot['profesionales'] ?? [];

        $d = ['id_cliente' => $cli, 'fecha_hora' => $fecha . ' ' . $hora,
              'servicios' => $servs, 'observaciones' => chance(20) ? 'Pidió el mismo color de la vez pasada' : ''];
        // 3 de cada 4 clientas piden a alguien en particular
        if ($profes && chance(75)) $d['id_usuario'] = (string) elegir($profes);

        $n->post('/citas/guardar', $d)->seguir();
        if ($n->dice('Cita agendada')) $hechas++;
    }

    return $hechas;
}

/** Atiende las citas de hoy que ya pasaron de hora. */
function atenderCitas(int $dia, array $turno): void
{
    $ids = array_keys($turno);
    $in = implode(',', $ids);
    $citas = DB::select(
        "SELECT c.id_cita, c.id_usuario, c.fecha_hora, c.id_estado_cita
           FROM cita c
          WHERE DATE(c.fecha_hora) = CURDATE() AND c.id_usuario IN ($in)
            AND c.id_estado_cita IN (1,2,5,7)
            AND c.fecha_hora <= NOW()
          ORDER BY c.fecha_hora"
    );
    if (! $citas) return;

    $porProf = [];
    foreach ($citas as $c) $porProf[(int) $c->id_usuario][] = $c;

    foreach ($porProf as $idProf => $lista) {
        $user = $turno[$idProf] ?? null;
        if (! $user) continue;
        $n = new Nav();
        if (! $n->entrar($user, PASS_PROF)) continue;

        foreach ($lista as $c) {
            $idc = (int) $c->id_cita;

            // 8% no vino
            if (chance(8)) {
                $n->post('/citas/estado', ['id_cita' => $idc, 'id_estado_cita' => 6, 'dia' => date('Y-m-d')])->seguir();
                continue;
            }
            // En proceso primero (como en el mostrador)
            $n->post('/citas/estado', ['id_cita' => $idc, 'id_estado_cita' => 5, 'dia' => date('Y-m-d')])->seguir();

            $servs = array_map(fn ($r) => (int) $r->id_servicio,
                DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [$idc]));
            if (! $servs) continue;

            // A veces se agrega un servicio en el momento, a veces uno no se hace
            $realizados = $servs;
            if (count($servs) > 1 && chance(15)) array_pop($realizados);
            if (chance(12)) {
                $extra = (int) (DB::scalar('SELECT id_servicio FROM servicio WHERE activo=1 ORDER BY RAND() LIMIT 1') ?: 0);
                if ($extra && ! in_array($extra, $realizados, true)) $realizados[] = $extra;
            }

            $d = ['id_cita' => $idc, 'servicios' => $realizados, 'dia' => date('Y-m-d'),
                  'observaciones' => chance(25) ? 'Sin novedad' : ''];

            // Consumo de productos
            $prod = [];
            $cant = [];
            $de = [];
            $k = 0;
            foreach ($realizados as $sid) {
                if (in_array($sid, [1, 2, 3, 4, 9], true)) {      // cortes y lavados
                    $prod[$k] = 1; $cant[$k] = (string) (15 + mt_rand(0, 45)); $de[$k] = $sid; $k++;
                    if (chance(60)) { $prod[$k] = 2; $cant[$k] = (string) (10 + mt_rand(0, 30)); $de[$k] = $sid; $k++; }
                } elseif (in_array($sid, [6, 7, 8], true)) {      // color
                    $prod[$k] = 4; $cant[$k] = '1'; $de[$k] = $sid; $k++;
                    $prod[$k] = 3; $cant[$k] = (string) (60 + mt_rand(0, 60)); $de[$k] = $sid; $k++;
                    $prod[$k] = 7; $cant[$k] = '1'; $de[$k] = $sid; $k++;
                } elseif ($sid === 11) {
                    $prod[$k] = 5; $cant[$k] = '2'; $de[$k] = $sid; $k++;
                } elseif (in_array($sid, [12, 13, 14], true)) {
                    if (chance(70)) { $prod[$k] = 9; $cant[$k] = '1'; $de[$k] = $sid; $k++; }
                } elseif ($sid === 10) {
                    $prod[$k] = 6; $cant[$k] = '1'; $de[$k] = $sid; $k++;
                }
            }
            if ($prod) { $d['producto'] = $prod; $d['cantidad'] = $cant; $d['servicio_de'] = $de; }

            $n->post('/citas/atender', $d)->seguir();
            if (! $n->dice('Atención registrada')) {
                sim_log(['tipo' => 'ATENCION_NO', 'cita' => $idc, 'msg' => $n->flashTxt()]);
            }
        }
        $n->salir();
    }
}

/** Cobra y emite el comprobante de las citas atendidas sin comprobante. */
function facturarYCobrar(int $dia, int $limite = 99): void
{
    $pend = DB::select(
        'SELECT c.id_cita, c.id_cliente
           FROM cita c
          WHERE c.id_estado_cita = 4
            AND NOT EXISTS (SELECT 1 FROM factura f WHERE f.id_cita = c.id_cita AND f.id_estado_factura = 1)
            AND EXISTS (SELECT 1 FROM cita_servicio cs WHERE cs.id_cita = c.id_cita)
          ORDER BY c.fecha_hora LIMIT ' . (int) $limite
    );
    if (! $pend) return;

    $n = new Nav();
    if (! $n->entrar('recepcion', 'recepcion123')) return;

    foreach ($pend as $p) {
        $idc = (int) $p->id_cita;
        // La clienta pide factura 1 de cada 4; si no, comprobante de pago
        $tipo = chance(25) ? 1 : 8;

        // A veces se cobra primero contra la cita (orden del mostrador, 7.19.0)
        if (chance(25)) {
            $total = (float) DB::scalar('SELECT COALESCE(SUM(s.precio),0) FROM cita_servicio cs
                                          JOIN servicio s ON s.id_servicio=cs.id_servicio WHERE cs.id_cita=?', [$idc]);
            if ($total > 0) {
                $n->post('/facturacion/sena', ['id_cita' => $idc, 'id_metodo_pago' => 1,
                    'monto' => (string) (int) round($total * 0.5), 'dia' => date('Y-m-d')])->seguir();
            }
        }

        if ($tipo === 1) {
            $per = DB::selectOne('SELECT pe.nombre,pe.apellido,pe.cedula,pe.email,pe.telefono,pe.direccion
                                    FROM cliente c JOIN persona pe ON pe.id_persona=c.id_persona
                                   WHERE c.id_cliente=?', [(int) $p->id_cliente]);
            $n->post('/facturacion/receptor', [
                'id_cita' => $idc, 'id_tipo_comprobante' => 1, 'id_condicion_venta' => 1,
                'tipo_doc' => $per && $per->cedula ? 'CI' : 'CF',
                'documento' => $per->cedula ?? '',
                'nombre' => trim(($per->nombre ?? '') . ' ' . ($per->apellido ?? '')),
                'email' => $per->email ?? '', 'direccion' => $per->direccion ?? '', 'telefono' => $per->telefono ?? '',
            ])->seguir();
        } else {
            $n->post('/facturacion/emitir', ['id_cita' => $idc, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1])->seguir();
        }

        $f = DB::selectOne('SELECT id_factura, fn_factura_saldo(id_factura) AS saldo
                              FROM factura WHERE id_cita = ? AND id_estado_factura = 1 ORDER BY id_factura DESC LIMIT 1', [$idc]);
        if (! $f) { sim_log(['tipo' => 'SIN_FACTURA', 'cita' => $idc, 'msg' => $n->flashTxt()]); continue; }

        $saldo = (float) $f->saldo;
        if ($saldo <= 0.01) continue;

        // Medio de pago: efectivo mayormente, a veces mixto
        $lineas = [];
        if (chance(22)) {
            $m1 = (int) round($saldo * 0.5);
            $lineas[] = ['metodo' => '1', 'monto' => (string) $m1, 'ref' => ''];
            $lineas[] = ['metodo' => '3', 'monto' => (string) ((int) $saldo - $m1), 'ref' => 'AUT' . mt_rand(10000, 99999)];
        } elseif (chance(20)) {
            $lineas[] = ['metodo' => '4', 'monto' => (string) (int) $saldo, 'ref' => 'OP' . mt_rand(100000, 999999)];
        } else {
            $lineas[] = ['metodo' => '1', 'monto' => (string) (int) $saldo, 'ref' => ''];
        }

        $d = ['id_factura' => (int) $f->id_factura, 'metodo' => [], 'monto' => [], 'referencia' => [],
              'marca' => [], 'tipo_tarjeta' => [], 'cuotas' => [], 'ultimos_4' => [], 'nro_boleta' => [],
              'cod_autorizacion' => [], 'banco' => [], 'nro_cheque' => [], 'nro_operacion' => [], 'fecha_emision' => []];
        foreach ($lineas as $i => $l) {
            $d['metodo'][$i] = $l['metodo'];
            $d['monto'][$i] = $l['monto'];
            $d['referencia'][$i] = $l['ref'];
            $d['marca'][$i] = $l['metodo'] === '3' ? 'Visa' : '';
            $d['tipo_tarjeta'][$i] = $l['metodo'] === '3' ? 'CREDITO' : '';
            $d['cuotas'][$i] = '1';
            $d['ultimos_4'][$i] = $l['metodo'] === '3' ? (string) mt_rand(1000, 9999) : '';
            $d['nro_boleta'][$i] = '';
            $d['cod_autorizacion'][$i] = $l['metodo'] === '3' ? (string) mt_rand(100000, 999999) : '';
            $d['banco'][$i] = $l['metodo'] === '4' ? 'Banco Continental' : '';
            $d['nro_cheque'][$i] = '';
            $d['nro_operacion'][$i] = $l['metodo'] === '4' ? $l['ref'] : '';
            $d['fecha_emision'][$i] = '';
        }
        $n->post('/facturacion/cobrar', $d)->seguir();
        if (! $n->dice('Cobro registrado')) {
            sim_log(['tipo' => 'COBRO_NO', 'factura' => (int) $f->id_factura, 'msg' => $n->flashTxt()]);
        }
    }
    $n->salir();
}

function faseManana(int $dia, int $dow): void
{
    if ($dow === 7) return;

    $turno = MANANA;
    $idk = (int) (DB::scalar("SELECT id_usuario FROM usuario WHERE username='karen' AND activo=1") ?: 0);
    if ($idk) $turno[$idk] = 'karen';
    atenderCitas($dia, $turno);
    facturarYCobrar($dia, 6);

    // Reservas del día
    $n = new Nav();
    if ($n->entrar('recepcion', 'recepcion123')) {
        $hechas = reservar($n, demanda($dow, $dia), $dia);
        sim_log(['tipo' => 'RESERVAS', 'dia' => $dia, 'n' => $hechas]);
        $n->salir();
    }
}

function faseMediodia(int $dia, int $dow): void
{
    if ($dow === 7) return;
    $n = new Nav();
    if (! $n->entrar('recepcion', 'recepcion123')) return;

    foreach (MANANA as $id => $u) {
        $n->post('/seguridad/asistencia', ['accion' => 'salida', 'id_turno' => TURNO_MANANA,
            'id_usuario' => $id, 'fecha' => date('Y-m-d')])->seguir();
    }
    $idk = (int) (DB::scalar("SELECT id_usuario FROM usuario WHERE username='karen' AND activo=1") ?: 0);
    if ($idk) {
        $n->post('/seguridad/asistencia', ['accion' => 'salida', 'id_turno' => TURNO_MANANA,
            'id_usuario' => $idk, 'fecha' => date('Y-m-d')])->seguir();
    }
    foreach (TARDE as $id => $u) {
        $n->post('/seguridad/asistencia', ['accion' => 'entrada', 'id_turno' => TURNO_TARDE,
            'id_usuario' => $id, 'fecha' => date('Y-m-d')])->seguir();
    }
    $n->salir();
}

function faseTarde(int $dia, int $dow): void
{
    if ($dow === 7) return;

    atenderCitas($dia, TARDE);
    facturarYCobrar($dia, 12);

    $n = new Nav();
    if (! $n->entrar('recepcion', 'recepcion123')) return;

    // Citas de días pasados que quedaron sin atender: la recepción las cierra
    // como ausentes (es lo que muestra el bloque de «atrasados» del panel).
    $viejas = DB::select("SELECT c.id_cita, c.id_usuario, c.fecha_hora,
                                 (SELECT COUNT(*) FROM usuario_turno ut WHERE ut.id_usuario = c.id_usuario) AS turnos
                            FROM cita c
                           WHERE c.id_estado_cita IN (1,2,5,7) AND DATE(c.fecha_hora) < CURDATE()");
    $sinTurno = 0;
    foreach ($viejas as $v) {
        if ((int) $v->turnos === 0) $sinTurno++;
        $n->post('/citas/estado', ['id_cita' => (int) $v->id_cita, 'id_estado_cita' => 6, 'dia' => date('Y-m-d')])->seguir();
    }
    if ($viejas) {
        sim_log(['tipo' => 'ATRASADAS_CERRADAS', 'dia' => $dia, 'n' => count($viejas), 'sin_turno' => $sinTurno]);
    }

    // Cancelaciones y reprogramaciones de citas futuras
    $futuras = DB::select('SELECT id_cita, id_usuario, fecha_hora FROM cita
                            WHERE id_estado_cita IN (1,2) AND fecha_hora > NOW() ORDER BY RAND() LIMIT 3');
    foreach ($futuras as $c) {
        if (chance(18)) {
            $n->post('/citas/cancelar', ['id_cita' => (int) $c->id_cita, 'dia' => date('Y-m-d')])->seguir();
        } elseif (chance(40)) {
            // Reprogramar a un hueco real del mismo profesional
            $srv = array_map(fn ($r) => (int) $r->id_servicio,
                DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita=?', [(int) $c->id_cita]));
            $f2 = date('Y-m-d', strtotime('+' . mt_rand(2, 10) . ' day'));
            $n->get('/citas/disponibilidad', ['servicios' => $srv,
                'id_usuario' => (int) $c->id_usuario, 'fecha' => $f2]);
            $j2 = json_decode($n->body, true);
            if (! empty($j2['horas'])) {
                $h = elegir($j2['horas'])['hora'];
                $n->post('/citas/reprogramar', ['id_cita' => (int) $c->id_cita,
                    'nueva_fecha' => $f2 . ' ' . $h, 'dia' => date('Y-m-d')])->seguir();
            }
        }
    }

    // Reposición de insumos cada tanto (a crédito, con cuotas)
    if ($dia % 12 === 3) {
        $prov = (int) DB::scalar('SELECT id_proveedor FROM proveedor WHERE activo=1 ORDER BY RAND() LIMIT 1');
        $prods = DB::select('SELECT p.id_producto, p.nombre, p.precio_costo FROM producto p
                              WHERE p.activo=1 ORDER BY fn_producto_stock(p.id_producto, 1) ASC LIMIT 5');
        $d = ['id_proveedor' => $prov, 'id_condicion_venta' => 2,
              'nro_factura_proveedor' => '001-001-' . str_pad((string) (9000 + $dia), 7, '0', STR_PAD_LEFT),
              'nombre' => [], 'id_producto' => [], 'cantidad' => [], 'precio' => [], 'categoria' => [],
              'cuota_fecha' => [], 'cuota_monto' => []];
        $total = 0;
        foreach ($prods as $k => $p) {
            $c = 6 + mt_rand(0, 8);
            $d['nombre'][$k] = $p->nombre;
            $d['id_producto'][$k] = $p->id_producto;
            $d['cantidad'][$k] = (string) $c;
            $d['precio'][$k] = (string) (int) $p->precio_costo;
            $d['categoria'][$k] = '';
            $total += $c * (int) $p->precio_costo;
        }
        $c1 = (int) round($total / 2);
        $d['cuota_fecha'] = [date('Y-m-d', strtotime('+30 day')), date('Y-m-d', strtotime('+60 day'))];
        $d['cuota_monto'] = [(string) $c1, (string) ($total - $c1)];
        $n->post('/inventario/compras/guardar', $d)->seguir();
    }

    // Mermas y ajustes ocasionales
    if ($dia % 17 === 5) {
        $p = DB::selectOne('SELECT id_producto FROM producto WHERE activo=1 AND fn_producto_stock(id_producto, 1) > 3 ORDER BY RAND() LIMIT 1');
        if ($p) {
            $n->post('/inventario/cargar-stock', ['id_producto' => (int) $p->id_producto, 'modo' => 'movimiento',
                'id_tipo_movimiento' => 5, 'cantidad' => '1', 'precio_unitario' => '0',
                'referencia' => 'MERMA', 'observaciones' => 'Producto vencido'])->seguir();
        }
    }

    // Pago a proveedores cuando hay deuda
    if ($dia % 9 === 4) {
        $cta = DB::selectOne('SELECT * FROM vw_cuenta_proveedor WHERE saldo > 0 ORDER BY vencimiento LIMIT 1');
        if ($cta) {
            $caja = cajaAbierta();
            $enCaja = $caja ? (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $caja->id_caja]) : 0;
            $monto = min((float) $cta->saldo, max(50000.0, $enCaja * 0.4));
            $metodo = $monto > $enCaja ? 4 : 1;
            $n->post('/facturacion/proveedores/pagar', [
                'id_compra' => (int) $cta->id_compra, 'id_metodo_pago' => $metodo,
                'monto' => (string) (int) $monto, 'referencia' => 'PAGO' . $dia])->seguir();
        }
    }

    $n->salir();
}

function faseCierre(int $dia, int $dow): void
{
    $n = new Nav();
    if ($dow !== 7) {
        if ($n->entrar('recepcion', 'recepcion123')) {
            foreach (TARDE as $id => $u) {
                $n->post('/seguridad/asistencia', ['accion' => 'salida', 'id_turno' => TURNO_TARDE,
                    'id_usuario' => $id, 'fecha' => date('Y-m-d')])->seguir();
            }
            $n->salir();
        }
    }

    // Fin de mes: liquidación al personal y reportes (lo hace el Administrador)
    $manana = date('Y-m-d', strtotime('+1 day'));
    $finDeMes = date('m', strtotime($manana)) !== date('m');

    $a = new Nav();
    if ($a->entrar('admin', 'admin123')) {
        if ($finDeMes) {
            // 7.22.0: la liquidación pide con qué se paga y descuenta del arqueo
            // cuando es en efectivo. Se alterna efectivo/transferencia para que
            // el cierre pueda comprobar las dos mitades de fn_caja_saldo.
            foreach (array_merge(array_values(PROFS), ['karen']) as $k => $u) {
                $idu = (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE username=?', [$u]) ?: 0);
                if ($idu) {
                    $a->post('/facturacion/pagos/personal', [
                        'id_usuario' => $idu, 'periodo' => date('m/Y'),
                        'id_metodo_pago' => $k % 2 === 0 ? 1 : 4,
                    ])->seguir();
                    if ($a->dice('Elegí con qué')) {
                        sim_incidente('LIQUIDACION_SIN_MEDIO', 'El pago al personal rechazó el medio de pago', 'ALTO');
                    }
                }
            }
            $a->get('/reportes', ['desde' => date('Y-m-01'), 'hasta' => date('Y-m-d')]);
            $a->get('/reportes/imprimir', ['desde' => date('Y-m-01'), 'hasta' => date('Y-m-d')]);
        }

        // Cierre de caja
        $caja = cajaAbierta();
        if ($caja) {
            $a->post('/facturacion/caja/cerrar', ['id_caja' => (int) $caja->id_caja])->seguir();
            sim_esperado($a, 'Caja cerrada', 'CAJA_CERRAR', 'Cierre de caja del día ' . $dia);
        }
        $a->salir();
    }

    // El cron de avisos
    try {
        Illuminate\Support\Facades\Artisan::call('spg:notificaciones');
        sim_log(['tipo' => 'CRON', 'salida' => trim(Illuminate\Support\Facades\Artisan::output())]);
    } catch (Throwable $e) {
        sim_incidente('CRON_FALLO', $e->getMessage(), 'ALTO');
    }
}

// ---------------------------------------------------------------------------

sim_log(['tipo' => 'FASE_INI', 'dia' => $DIA, 'fase' => $FASE, 'fecha' => $HOY, 'dow' => $DOW]);

switch ($FASE) {
    case 'init':      faseInit(); break;
    case 'apertura':  faseApertura($DIA, $DOW); break;
    case 'manana':    faseManana($DIA, $DOW); break;
    case 'mediodia':  faseMediodia($DIA, $DOW); break;
    case 'tarde':     faseTarde($DIA, $DOW); break;
    case 'cierre':    faseCierre($DIA, $DOW); break;
    default:
        $f = __DIR__ . '/extra/' . preg_replace('/[^a-z0-9_]/', '', $FASE) . '.php';
        if (is_file($f)) { require $f; } else { fwrite(STDERR, "fase desconocida: $FASE\n"); }
}

echo "d$DIA $FASE $HOY ok\n";
