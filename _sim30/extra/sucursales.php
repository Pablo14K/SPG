<?php
/**
 * MULTISUCURSAL — lo que entró entre la 7.30.0 y la 7.33.0.
 *
 * La simulación de 60 días corrió contra la 7.27.1, cuando el sistema todavía
 * operaba sobre un solo local: nada de esto tuvo cobertura nunca. Y no alcanza
 * con crear la sucursal y mirar que la pantalla abra — lo que hay que probar es
 * el **aislamiento**, o sea que lo de un local no se vea ni se toque desde el
 * otro, que es lo único que hace que dos sucursales puedan compartir sistema.
 *
 * Se ejecuta con `$FASE = 'sucursales'`.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/** Día 2: se inaugura el segundo local. */
function faseSucursalesAlta(): void
{
    $n = new Nav();
    if (! $n->entrar('admin', 'admin123')) {
        return;
    }

    if ((int) DB::scalar('SELECT COUNT(*) FROM sucursal WHERE activo = 1') > 1) {
        return;   // ya están abiertas
    }

    // **Tres locales y no dos.** Con dos, una regla escrita «el otro» funciona
    // por casualidad; con tres se ve si de verdad es por sucursal. La idea es
    // que el sistema aguante N, así que la simulación no prueba el mínimo.
    foreach ([
        ['Peluqueria San Lorenzo', '80099887-5', '021555777', 'Avda. Mcal. López 1200', 'San Lorenzo', '002'],
        ['Peluqueria Capiata',     '80077665-3', '021444888', 'Ruta 1 km 20',          'Capiatá',     '003'],
    ] as [$nom, $ruc, $tel, $dir, $ciu, $est]) {
        $n->post('/seguridad/sucursales/guardar', [
            'id_sucursal' => 0, 'nombre' => $nom, 'ruc' => $ruc,
            'telefono' => $tel, 'direccion' => $dir, 'ciudad' => $ciu,
        ])->seguir();
        sim_esperado($n, 'Sucursal creada', 'SUC_ALTA', 'Alta de la sucursal ' . $nom);

        $id = (int) (DB::scalar('SELECT id_sucursal FROM sucursal WHERE nombre = ?', [$nom]) ?: 0);
        if ($id) {
            $n->post('/facturacion/timbrados/guardar', [
                'id_timbrado' => 0, 'id_sucursal' => $id, 'id_tipo_comprobante' => 8,
                'nro_timbrado' => '80' . $est . '00000', 'establecimiento' => $est, 'punto_expedicion' => '001',
                'numero_desde' => '1', 'numero_hasta' => '9999999',
                'fecha_inicio' => date('Y-m-d'), 'fecha_fin' => date('Y-m-d', strtotime('+2 years')),
            ])->seguir();
        }
    }

    $suc2 = (int) (DB::scalar("SELECT id_sucursal FROM sucursal WHERE nombre = 'Peluqueria San Lorenzo'") ?: 0);
    if (! $suc2) {
        sim_incidente('SUC_SIN_ID', 'La segunda sucursal no quedó creada', 'CRITICO');

        return;
    }

    // **El local nuevo tiene que abrir con el catálogo de servicios.** Si nace
    // sin nada, la clienta que lo elige en el portal no ve qué reservar. Es la
    // corrección de la 7.35.0 y se comprueba acá, no se da por hecha.
    $publicados = (int) DB::scalar('SELECT COUNT(*) FROM servicio_sucursal WHERE id_sucursal = ?', [$suc2]);
    $activos = (int) DB::scalar('SELECT COUNT(*) FROM servicio WHERE activo = 1');
    $sinFilas = (int) DB::scalar('SELECT COUNT(*) FROM servicio s WHERE s.activo = 1
                                    AND NOT EXISTS (SELECT 1 FROM servicio_sucursal ss WHERE ss.id_servicio = s.id_servicio)');
    sim_check($publicados + $sinFilas >= $activos, 'SUC_CATALOGO_NUEVO',
        "El local nuevo abrió con $publicados de $activos servicios publicados ($sinFilas valen en todas)", 'ALTO');

    // Personal: una profesional sólo del local nuevo y otra que trabaja en los dos.
    $n->post('/seguridad/usuarios/guardar', [
        'id_rol' => 2, 'id_sucursal' => $suc2, 'username' => 'noelia',
        'nombre' => 'Noelia', 'apellido' => 'Cardozo', 'cedula' => '3900777',
        'telefono' => '0981200700', 'email' => 'noelia.cardozo@peluqueria.local',
        'password' => 'profesional123', 'sucursales' => [$suc2], 'turnos' => [3],
    ])->seguir();
    sim_esperado($n, 'Usuario creado', 'SUC_USUARIO_LOCAL', 'Alta de una profesional del local nuevo');

    // Marta pasa a estar asignada a los DOS locales: es el caso que obliga a
    // elegir al entrar, y el que la 7.31.2 valida en cada petición.
    // Marta y la recepcionista llegan a TODOS los locales: son las cuentas que
    // obligan a elegir al entrar y las que la 7.31.2 revalida en cada petición.
    foreach (['marta', 'recepcion'] as $u) {
        $idu = (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE username = ?', [$u]) ?: 0);
        if (! $idu) {
            continue;
        }
        foreach (DB::select('SELECT id_sucursal FROM sucursal WHERE activo = 1') as $sx) {
            DB::statement('INSERT IGNORE INTO usuario_sucursal (id_usuario, id_sucursal) VALUES (?,?)',
                [$idu, (int) $sx->id_sucursal]);
        }
    }

    // Mercadería: se TRAE del otro local en vez de volver a cargarla. Es la
    // función de la 7.33.0, y lo que hay que comprobar es que no duplique.
    $antes = (int) DB::scalar('SELECT COUNT(*) FROM producto');
    $n2 = new Nav();
    if ($n2->entrar('admin', 'admin123', true, $suc2)) {
        foreach (DB::select('SELECT id_producto FROM producto ORDER BY id_producto LIMIT 6') as $p) {
            $n2->post('/inventario/productos/traer', ['id_producto' => (int) $p->id_producto])->seguir();
        }
    }
    $despues = (int) DB::scalar('SELECT COUNT(*) FROM producto');
    sim_check($antes === $despues, 'SUC_TRAER_DUPLICA',
        "Traer productos de otro local cambió el catálogo de $antes a $despues filas: tendría que ser el mismo", 'ALTO');

    $traidos = (int) DB::scalar('SELECT COUNT(*) FROM producto_sucursal WHERE id_sucursal = ?', [$suc2]);
    sim_check($traidos >= 6, 'SUC_TRAER_HABILITA',
        "El local nuevo maneja $traidos producto(s); se trajeron 6", 'ALTO');

    // El stock arranca en cero acá: lo que hay en el otro local no es de éste.
    foreach (DB::select('SELECT id_producto FROM producto_sucursal WHERE id_sucursal = ?', [$suc2]) as $p) {
        $st = (float) DB::scalar('SELECT fn_producto_stock(?,?)', [$p->id_producto, $suc2]);
        if (abs($st) > 0.0001) {
            sim_incidente('SUC_STOCK_HEREDADO',
                "El producto {$p->id_producto} arrancó con $st en el local nuevo: tendría que ser 0", 'CRITICO');
        }
    }

    sim_log(['tipo' => 'SUC_ALTA_OK', 'sucursal' => $suc2, 'publicados' => $publicados, 'traidos' => $traidos]);
}

/** El segundo local opera: compra, agenda, atiende, cobra y cierra. */
function faseSucursalesOpera(int $dia): void
{
    $suc2 = (int) (DB::scalar("SELECT id_sucursal FROM sucursal WHERE nombre = 'Peluqueria San Lorenzo' AND activo = 1") ?: 0);
    if (! $suc2) {
        return;
    }

    // --- Caja del local nuevo -------------------------------------------
    $rec = new Nav();
    if (! $rec->entrar('recepcion', 'recepcion123', true, $suc2)) {
        return;
    }

    $abierta = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc2]);
    $habiaEnOtro = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_sucursal <> ?', [$suc2]);

    if (! $abierta) {
        $rec->post('/facturacion/caja/abrir', ['monto_inicial' => '150000'])->seguir();
    }

    // **La caja es del local**: cada sede cuenta su propio cajón, así que el
    // local nuevo tiene que poder abrir la suya aunque la casa central tenga
    // la de ella abierta. Si no puede, ese local no cobra en todo el día.
    $enOtro = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_sucursal <> ?', [$suc2]);
    $enEste = (int) DB::scalar('SELECT COUNT(*) FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc2]);

    sim_check($enEste <= 1, 'SUC_CAJA_DOBLE', "El local nuevo tiene $enEste cajas abiertas a la vez", 'CRITICO');

    if (! $abierta && $enEste === 0) {
        sim_incidente('SUC_CAJA_NO_ABRE',
            'El segundo local no pudo abrir su caja' . ($habiaEnOtro ? ' teniendo la otra sucursal una abierta' : '')
            . ': ' . ($rec->flashTxt() ?: 'sin aviso') . '. Sin caja no se cobra, así que ese local queda '
            . 'sin poder facturar en todo el día.', 'ALTO');
    }

    sim_log(['tipo' => 'SUC_CAJAS', 'este' => $enEste, 'otros' => $enOtro, 'habia_en_otro' => $habiaEnOtro]);

    // --- Reposición: mercadería que entra a ESTE local -------------------
    $prods = DB::select('SELECT ps.id_producto FROM producto_sucursal ps WHERE ps.id_sucursal = ? LIMIT 4', [$suc2]);
    foreach ($prods as $p) {
        $rec->post('/inventario/cargar-stock', [
            'modo' => 'movimiento', 'id_producto' => (int) $p->id_producto,
            'id_tipo_movimiento' => 3, 'cantidad' => (string) mt_rand(4, 14),
            'precio_unitario' => '25000', 'referencia' => 'REP-S2-' . $dia,
            'observaciones' => 'Reposición del local de San Lorenzo',
        ])->seguir();
    }

    // --- Agenda del local nuevo -----------------------------------------
    //
    // **Se le piden los huecos al sistema, no se inventa la hora.** Es el mismo
    // camino que recorre una recepcionista: elige servicios, mira qué días y
    // horas hay libres, y reserva uno de ésos. Inventar la hora hacía que el
    // sistema rechazara casi todo por caer fuera del turno, y la simulación
    // terminaba midiendo el rechazo en vez de la agenda.
    $servicios = DB::select(
        'SELECT s.id_servicio FROM servicio s
          WHERE s.activo = 1
            AND (EXISTS (SELECT 1 FROM servicio_sucursal ss WHERE ss.id_servicio = s.id_servicio AND ss.id_sucursal = ?)
                 OR NOT EXISTS (SELECT 1 FROM servicio_sucursal ss2 WHERE ss2.id_servicio = s.id_servicio))
          ORDER BY RAND() LIMIT 6', [$suc2]);

    $cuantas = mt_rand(3, 6);
    $hechas = 0;

    for ($i = 0; $i < $cuantas && $servicios; $i++) {
        $cli = DB::selectOne('SELECT id_cliente FROM cliente WHERE activo = 1 ORDER BY RAND() LIMIT 1');
        if (! $cli) {
            break;
        }

        $sv = $servicios[array_rand($servicios)];
        $servs = [(int) $sv->id_servicio];
        $fecha = date('Y-m-d', strtotime('+' . mt_rand(1, 8) . ' day'));

        $rec->get('/citas/disponibilidad', ['servicios' => $servs, 'fecha' => $fecha]);
        $j = json_decode($rec->body, true);
        if (empty($j['horas'])) {
            continue;   // ese día no hay huecos: la clienta elige otro
        }

        $slot = $j['horas'][array_rand($j['horas'])];
        $d = [
            'id_cliente' => (int) $cli->id_cliente,
            'fecha_hora' => $fecha . ' ' . $slot['hora'],
            'servicios' => $servs,
            'observaciones' => 'Cita del local de San Lorenzo',
        ];
        // La mayoría pide a alguien en particular, de entre los que atienden acá.
        if (! empty($slot['profesionales']) && mt_rand(1, 100) <= 75) {
            $d['id_usuario'] = (string) $slot['profesionales'][array_rand($slot['profesionales'])];
        }

        $rec->post('/citas/guardar', $d)->seguir();
        if ($rec->dice('Cita agendada')) {
            $hechas++;
        }
    }

    // **La cita tiene que quedar en el local en que se la agendó.** Si cayera
    // en otro, el aislamiento no serviría de nada: la agenda del día mostraría
    // clientas que nunca van a llegar a ese salón.
    $ultimas = DB::select(
        'SELECT id_cita, id_sucursal FROM cita ORDER BY id_cita DESC LIMIT ?', [max(1, $hechas)]);
    foreach ($ultimas as $u) {
        if ($hechas > 0 && (int) $u->id_sucursal !== $suc2) {
            sim_incidente('SUC_CITA_EN_OTRO_LOCAL',
                "La cita {$u->id_cita} se agendó desde San Lorenzo y quedó en la sucursal {$u->id_sucursal}", 'ALTO');
            break;
        }
    }

    sim_log(['tipo' => 'SUC_AGENDA', 'dia' => $dia, 'intentos' => $cuantas, 'agendadas' => $hechas]);

    // --- AISLAMIENTO: lo del otro local no se ve desde acá ---------------
    // La agenda del día, mirada desde el local nuevo, no puede traer citas
    // de la casa central. Es lo que hace que dos sucursales convivan.
    $rec->get('/citas/agenda', ['fecha' => date('Y-m-d')]);
    $ajenas = DB::select(
        "SELECT c.id_cita, CONCAT(pe.nombre,' ',pe.apellido) AS cliente
           FROM cita c
           JOIN cliente cl ON cl.id_cliente = c.id_cliente
           JOIN persona pe ON pe.id_persona = cl.id_persona
          WHERE c.id_sucursal <> ? AND DATE(c.fecha_hora) = CURDATE() LIMIT 6", [$suc2]);
    foreach ($ajenas as $a) {
        if ($rec->dice('data-cita="' . $a->id_cita . '"')) {
            sim_incidente('SUC_FUGA_AGENDA',
                "La agenda del local nuevo muestra la cita {$a->id_cita}, que es de otra sucursal", 'CRITICO');
            break;
        }
    }

    // El stock que se ve acá es el de acá.
    $rec->get('/inventario/stock');
    foreach (DB::select('SELECT p.id_producto, p.nombre FROM producto p
                          WHERE NOT EXISTS (SELECT 1 FROM producto_sucursal ps
                                             WHERE ps.id_producto = p.id_producto AND ps.id_sucursal = ?)
                          LIMIT 3', [$suc2]) as $p) {
        // Un producto que este local NO maneja no puede aparecer en su stock.
        if ($rec->dice('>' . $p->nombre . '<')) {
            sim_incidente('SUC_FUGA_STOCK',
                "«{$p->nombre}» aparece en el stock del local nuevo y ahí no se maneja", 'ALTO');
            break;
        }
    }

    // --- Cambio de local en caliente (7.31.1 / 7.31.2) -------------------
    // Marta está en los dos: entra a uno, y desde Mi cuenta se pasa al otro.
    $m = new Nav();
    if ($m->entrar('marta', 'profesional123', true, $suc2)) {
        $m->get('/cuenta');
        sim_esperado($m, 'San Lorenzo', 'SUC_FICHA_VISIBLE',
            'Mi cuenta tiene que decir en qué local está trabajando', 'MEDIO');

        $suc1 = (int) (DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1') ?: 1);
        $m->post('/sucursal', ['id_sucursal' => $suc1])->seguir();
        $m->get('/panel');
        sim_log(['tipo' => 'SUC_CAMBIO', 'de' => $suc2, 'a' => $suc1, 'st' => $m->status]);
    }

    // --- Una cuenta NO puede entrar a un local que no es suyo ------------
    // Noelia está sólo en San Lorenzo: pedir la casa central tiene que fallar.
    $x = new Nav();
    $x->cookies = [];
    $x->quien = 'noelia';
    $x->get('/entrar');
    $x->post('/entrar', ['usuario' => 'noelia', 'password' => 'profesional123', 'forzar' => '1']);
    if ($x->location && str_contains($x->location, 'huella/activar')) {
        $x->post('/huella/preguntado');
    }
    $suc1 = (int) (DB::scalar('SELECT MIN(id_sucursal) FROM sucursal WHERE activo = 1') ?: 1);
    $x->post('/sucursal', ['id_sucursal' => $suc1])->seguir();
    $quedo = (int) (DB::scalar('SELECT COUNT(*) FROM usuario_sucursal us
                                 JOIN usuario u ON u.id_usuario = us.id_usuario
                                WHERE u.username = ? AND us.id_sucursal = ?', ['noelia', $suc1]) ?: 0);
    if ($quedo === 0 && ! $x->dice('sucursal')) {
        // Si no protestó y tampoco tiene ese local asignado, entró donde no debía.
        sim_incidente('SUC_ACCESO_AJENO',
            'Una cuenta asignada sólo a San Lorenzo pudo entrar a la casa central sin aviso', 'CRITICO');
    }

    // --- Cierre de la caja del local nuevo -------------------------------
    if ($dia % 2 === 0) {
        $c = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$suc2]);
        if ($c) {
            $saldo = (float) DB::scalar('SELECT fn_caja_saldo(?)', [$c->id_caja]);
            $rec2 = new Nav();
            if ($rec2->entrar('recepcion', 'recepcion123', true, $suc2)) {
                $rec2->post('/facturacion/caja/cerrar', [
                    'id_caja' => (int) $c->id_caja,
                    'monto_final' => (string) round($saldo),
                    'observaciones' => 'Cierre del local de San Lorenzo',
                ])->seguir();
            }
        }
    }
}


/**
 * N LOCALES, no dos.
 *
 * **Con dos sucursales una regla escrita «el otro» funciona por casualidad.**
 * Recién con tres se ve si de verdad está acotada por sucursal o si alguien
 * comparó contra «la que no es ésta». Esto corre todos los días sobre TODOS los
 * locales secundarios, no sólo sobre el segundo.
 */
function faseSucursalesN(int $dia): void
{
    $locales = DB::select('SELECT id_sucursal, nombre FROM sucursal WHERE activo = 1 ORDER BY id_sucursal');
    if (count($locales) < 3) {
        return;
    }
    $principal = (int) $locales[0]->id_sucursal;

    // --- Cada local abre su propio cajón ---------------------------------
    // Es el invariante que la simulación destapó roto: el disparador miraba el
    // salón entero, así que el primero que abría dejaba a los demás sin caja
    // —y sin caja no se cobra—. Se comprueba sobre los N, no sobre uno.
    $abiertas = [];
    foreach ($locales as $l) {
        $s = (int) $l->id_sucursal;
        if (DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$s])) {
            $abiertas[$s] = true;

            continue;
        }
        $u = new Nav();
        if ($u->entrar('recepcion', 'recepcion123', true, $s)) {
            $u->post('/facturacion/caja/abrir', ['monto_inicial' => '150000'])->seguir();
        }
        $abiertas[$s] = (bool) DB::selectOne(
            'SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$s]);

        if (! $abiertas[$s]) {
            sim_incidente('SUCN_CAJA_NO_ABRE',
                "El local «{$l->nombre}» no pudo abrir su caja teniendo otros locales la suya abierta: "
                . ($u->flashTxt() ?: 'sin aviso') . '. Sin caja ese local no cobra en todo el día.', 'ALTO');
        }
    }
    $conCaja = count(array_filter($abiertas));
    sim_check($conCaja === count($locales), 'SUCN_CAJA_POR_LOCAL',
        "$conCaja de " . count($locales) . ' locales pudieron tener su caja abierta a la vez', 'ALTO');

    // Y que ninguno tenga dos.
    $dobles = (int) DB::scalar(
        'SELECT COALESCE(SUM(n - 1), 0) FROM (SELECT id_sucursal, COUNT(*) n FROM caja
           WHERE id_estado_caja = 1 GROUP BY id_sucursal HAVING COUNT(*) > 1) x');
    sim_check($dobles === 0, 'SUCN_CAJA_DOBLE',
        "Hay $dobles caja(s) de más abiertas en algún local", 'CRITICO');

    // --- El stock de cada local es suyo ----------------------------------
    // El tercer local recibe mercadería propia; los otros no se pueden mover.
    $tercero = (int) $locales[2]->id_sucursal;
    $prod = DB::selectOne('SELECT id_producto FROM producto ORDER BY id_producto LIMIT 1');
    if ($prod) {
        $idp = (int) $prod->id_producto;

        // Se habilita acá si todavía no lo estaba.
        $t = new Nav();
        if ($t->entrar('admin', 'admin123', true, $tercero)) {
            if (! DB::selectOne('SELECT 1 FROM producto_sucursal WHERE id_producto=? AND id_sucursal=?', [$idp, $tercero])) {
                $t->post('/inventario/productos/traer', ['id_producto' => $idp])->seguir();
            }

            $antes = [];
            foreach ($locales as $l) {
                $antes[(int) $l->id_sucursal] = (float) DB::scalar(
                    'SELECT fn_producto_stock(?,?)', [$idp, (int) $l->id_sucursal]);
            }

            $t->post('/inventario/cargar-stock', [
                'modo' => 'movimiento', 'id_producto' => $idp, 'id_tipo_movimiento' => 3,
                'cantidad' => '7', 'precio_unitario' => '25000',
                'referencia' => 'REP-N-' . $dia, 'observaciones' => 'Reposicion del tercer local',
            ])->seguir();

            foreach ($locales as $l) {
                $s = (int) $l->id_sucursal;
                $ahora = (float) DB::scalar('SELECT fn_producto_stock(?,?)', [$idp, $s]);
                $movio = abs($ahora - $antes[$s]) > 0.0001;

                if ($s !== $tercero && $movio) {
                    sim_incidente('SUCN_FUGA_STOCK',
                        "Una entrada en «{$locales[2]->nombre}» movió el stock de «{$l->nombre}» "
                        . "de {$antes[$s]} a $ahora: el inventario de un local no es del otro", 'CRITICO');
                }
                if ($s === $tercero && ! $movio) {
                    sim_incidente('SUCN_STOCK_NO_ENTRA',
                        "La entrada no se registró en «{$l->nombre}»: quedó en $ahora", 'ALTO');
                }
            }
        }
    }

    // --- La agenda de cada local es suya ---------------------------------
    // Se mira la del tercero: no puede traer citas de los otros dos.
    $v = new Nav();
    if ($v->entrar('recepcion', 'recepcion123', true, $tercero)) {
        $v->get('/citas/agenda', ['dia' => date('Y-m-d')]);
        $ajena = DB::selectOne(
            'SELECT c.id_cita FROM cita c WHERE c.id_sucursal <> ? AND DATE(c.fecha_hora) = CURDATE() LIMIT 1',
            [$tercero]);
        if ($ajena && $v->dice('data-cita="' . $ajena->id_cita . '"')) {
            sim_incidente('SUCN_FUGA_AGENDA',
                "La agenda del tercer local muestra la cita {$ajena->id_cita}, que es de otra sucursal", 'CRITICO');
        }
    }

    // --- El arqueo de cada local cuenta lo suyo --------------------------
    foreach ($locales as $l) {
        $s = (int) $l->id_sucursal;
        $c = DB::selectOne('SELECT id_caja FROM caja WHERE id_estado_caja = 1 AND id_sucursal = ?', [$s]);
        if (! $c) {
            continue;
        }
        // Recalculado a mano: inicial + cobros en efectivo de ESA caja.
        $teo = (float) DB::scalar(
            "SELECT k.monto_inicial
                  + COALESCE((SELECT SUM(co.monto) FROM cobro co
                                JOIN metodo_pago mp USING(id_metodo_pago)
                               WHERE co.id_caja = k.id_caja AND co.id_estado_cobro = 1
                                 AND mp.tipo = 'EFECTIVO'), 0)
                  + COALESCE((SELECT SUM(CASE WHEN mc.tipo='INGRESO' THEN mc.monto ELSE -mc.monto END)
                                FROM movimiento_caja mc WHERE mc.id_caja = k.id_caja), 0)
                  -- **`pago_proveedor` y `pago_personal` NO guardan el monto**:
                  -- sale de su detalle, y la base ya tiene la función que lo
                  -- suma. Escribir `pp.monto` hacía fallar la consulta ENTERA,
                  -- y como `DB::scalar` levanta la excepción y el escenario la
                  -- deja pasar, **este arqueo por local no llegó a comprobarse
                  -- nunca**: 70 errores en el log que nadie miró. Una prueba
                  -- que falla en silencio es peor que no tenerla — da por
                  -- verificado lo que no se verificó.
                  - COALESCE((SELECT SUM(fn_pago_proveedor_monto(pp.id_pago_proveedor))
                                FROM pago_proveedor pp
                                JOIN metodo_pago mp2 ON mp2.id_metodo_pago = pp.id_metodo_pago
                               WHERE pp.id_caja = k.id_caja AND pp.id_estado_pago_proveedor = 1
                                 AND mp2.tipo = 'EFECTIVO'), 0)
                  - COALESCE((SELECT SUM(fn_pago_personal_monto(ps.id_pago_personal))
                                FROM pago_personal ps
                                JOIN metodo_pago mp3 ON mp3.id_metodo_pago = ps.id_metodo_pago
                               WHERE ps.id_caja = k.id_caja AND ps.id_estado_pago = 1
                                 AND mp3.tipo = 'EFECTIVO'), 0)
               FROM caja k WHERE k.id_caja = ?", [(int) $c->id_caja]);
        $sis = (float) DB::scalar('SELECT fn_caja_saldo(?)', [(int) $c->id_caja]);

        // Que la comprobación CORRIÓ es en sí un dato: si vuelve a romperse, el
        // informe tiene que poder decir que no se midió, en vez de callarse.
        sim_log(['tipo' => 'SUCN_ARQUEO_OK', 'caja' => (int) $c->id_caja,
                 'recalculado' => $teo, 'sistema' => $sis]);

        if (abs($teo - $sis) > 0.51) {
            sim_incidente('SUCN_ARQUEO',
                "El arqueo de «{$l->nombre}» no cuadra: recalculado $teo, el sistema dice $sis", 'ALTO');
        }
    }

    sim_log(['tipo' => 'SUCN_FIN', 'dia' => $dia, 'locales' => count($locales), 'con_caja' => $conCaja]);
}


/**
 * El local nuevo cierra su circuito: atiende, factura y cobra lo suyo.
 *
 * **Agendar no alcanza.** La corrida anterior le dio 92 citas propias y 0
 * facturas: las 170 salieron del timbrado de la casa central, porque las fases
 * de atención y cobro operan sobre el personal de la sede principal. O sea que
 * el circuito de facturación de una sucursal secundaria —el que emite con SU
 * timbrado y cobra en SU cajón— nunca se ejercitó de punta a punta. Es
 * justamente donde vivía CJ-03, así que dejarlo sin cobertura es dejar sin
 * mirar el lugar donde ya apareció un defecto.
 */
function faseSucursalFactura(int $suc, int $dia): void
{
    $prof = (int) (DB::scalar("SELECT id_usuario FROM usuario WHERE username = 'noelia'") ?: 0);
    if (! $prof) {
        return;
    }

    // --- Fichar: atender lo exige -----------------------------------------
    $turno = (int) (DB::scalar(
        'SELECT ut.id_turno FROM usuario_turno ut
           JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
          WHERE ut.id_usuario = ? LIMIT 1', [$prof]) ?: 0);
    if ($turno) {
        DB::statement(
            'INSERT IGNORE INTO asistencia (id_turno, id_usuario, fecha, hora_entrada, id_usuario_registro)
             VALUES (?,?,?,?,?)',
            [$turno, $prof, ahora_bd('Y-m-d'), ahora_bd('H:i:s'), $prof]);
    }

    // --- Las citas de HOY de este local que ya pasaron de hora -------------
    $citas = DB::select(
        "SELECT c.id_cita FROM cita c
          WHERE c.id_sucursal = ? AND DATE(c.fecha_hora) = CURDATE()
            AND c.fecha_hora <= NOW() AND c.id_estado_cita IN (1,2)
          ORDER BY c.fecha_hora LIMIT 4", [$suc]);

    if (! $citas) {
        return;
    }

    $n = new Nav();
    if (! $n->entrar('recepcion', 'recepcion123', true, $suc)) {
        return;
    }

    $atendidas = 0;
    $facturadas = 0;
    $cobradas = 0;

    foreach ($citas as $c) {
        $idc = (int) $c->id_cita;

        // 1) Atender: los servicios que la cita traía.
        $servs = array_map(fn ($x) => (int) $x->id_servicio,
            DB::select('SELECT id_servicio FROM cita_servicio WHERE id_cita = ?', [$idc]));
        if (! $servs) {
            continue;
        }
        $n->post('/citas/atender', ['id_cita' => $idc, 'servicios' => $servs])->seguir();
        if ((int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita = ?', [$idc]) !== 4) {
            continue;
        }
        $atendidas++;

        // 2) Emitir con el timbrado de ESTE local.
        $n->post('/facturacion/emitir', [
            'id_cita' => $idc, 'id_tipo_comprobante' => 8, 'id_condicion_venta' => 1,
        ])->seguir();

        $f = DB::selectOne(
            'SELECT f.id_factura, t.id_sucursal FROM factura f
               LEFT JOIN timbrado t ON t.id_timbrado = f.id_timbrado
              WHERE f.id_cita = ? AND f.id_estado_factura = 1 ORDER BY f.id_factura DESC LIMIT 1', [$idc]);
        if (! $f) {
            continue;
        }
        $facturadas++;

        // **El comprobante tiene que salir con el timbrado del local**, que es
        // de donde la SET saca el establecimiento. Si sale con el de la casa
        // central, la numeración de las dos sedes se pisa.
        if ((int) $f->id_sucursal !== $suc) {
            sim_incidente('SUC_TIMBRADO_AJENO',
                "La factura {$f->id_factura} del local $suc se emitió con el timbrado de la sucursal "
                . $f->id_sucursal . ': la numeración de los dos locales se pisa', 'ALTO');
        }

        // 3) Cobrar, y comprobar que la plata entra al cajón de ESTE local.
        $saldo = (float) DB::scalar('SELECT fn_factura_saldo(?)', [(int) $f->id_factura]);
        if ($saldo <= 0) {
            continue;
        }
        $metodo = (int) DB::scalar("SELECT id_metodo_pago FROM metodo_pago WHERE tipo='EFECTIVO' AND activo=1 LIMIT 1");
        $n->post('/facturacion/cobrar', [
            'id_factura' => (int) $f->id_factura,
            'metodo' => [$metodo], 'monto' => [(string) round($saldo)],
            'referencia' => ['S' . $suc . '-' . $dia],
        ])->seguir();

        $donde = (int) (DB::scalar(
            'SELECT k.id_sucursal FROM cobro co JOIN caja k ON k.id_caja = co.id_caja
              WHERE co.id_factura = ? ORDER BY co.id_cobro DESC LIMIT 1', [(int) $f->id_factura]) ?: 0);

        if ($donde && $donde !== $suc) {
            sim_incidente('SUC_COBRO_AJENO',
                "El cobro de la factura {$f->id_factura} del local $suc entró al cajón de la sucursal "
                . "$donde: el arqueo de un local se come la plata del otro", 'CRITICO');
        } elseif ($donde === $suc) {
            $cobradas++;
        }
    }

    sim_log(['tipo' => 'SUC_FACTURA', 'sucursal' => $suc, 'dia' => $dia,
             'atendidas' => $atendidas, 'facturadas' => $facturadas, 'cobradas' => $cobradas]);
}

// ---------------------------------------------------------------------------
//  Despacho: el día 2 se inaugura, de ahí en adelante opera.
// ---------------------------------------------------------------------------

if ($DIA <= 2) {
    faseSucursalesAlta();
} else {
    faseSucursalesOpera($DIA);
    // El local nuevo cierra su circuito: atiende, factura con SU timbrado y
    // cobra en SU cajón. Sin esto sólo se probaba que agenda.
    $sucOpera = (int) (DB::scalar("SELECT id_sucursal FROM sucursal WHERE nombre = 'Peluqueria San Lorenzo' AND activo = 1") ?: 0);
    if ($sucOpera) {
        faseSucursalFactura($sucOpera, $DIA);
    }
    // El barrido sobre los N locales cada tres días: comprueba invariantes que
    // no cambian de un día para el otro, así que correrlo a diario sólo
    // alargaba la corrida.
    if ($DIA % 3 === 0) {
        faseSucursalesN($DIA);
    }
}
