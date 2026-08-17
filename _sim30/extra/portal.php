<?php
/**
 * El portal de la clienta: alta de cuenta, reserva, seña, valoración,
 * recordatorios, atención en vivo y pedidos.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/** @var int $DIA */

$hoy = date('Y-m-d');

// ---------------------------------------------------------------------------
// 1. Una clienta NUEVA se crea la cuenta
// ---------------------------------------------------------------------------
$u = 'clienta' . $DIA;
$mail = $u . '@correo.com.py';

$n = new Nav();
$n->quien = $u;
$n->get('/registro');
$n->post('/registro', [
    'nombre' => 'Nadia', 'apellido' => 'Portal' . $DIA, 'email' => $mail,
    'telefono' => '0981' . str_pad((string) (400000 + $DIA), 6, '0', STR_PAD_LEFT),
    'username' => $u, 'password' => 'clienta123', 'password2' => 'clienta123',
])->seguir();

$idu = (int) (DB::scalar('SELECT id_usuario FROM usuario WHERE username = ?', [$u]) ?: 0);
if (! $idu) {
    sim_incidente('PORTAL_REGISTRO', 'No se creó la cuenta de ' . $u . ': ' . $n->flashTxt(), 'ALTO');
} else {
    if ((int) DB::scalar('SELECT activo FROM usuario WHERE id_usuario=?', [$idu]) !== 0) {
        sim_incidente('PORTAL_ACTIVA_SIN_VERIFICAR', 'La cuenta nació activa sin verificar el correo', 'ALTO');
    }
    // La clienta lee el código que le llegó por correo
    $cod = (string) (DB::scalar("SELECT codigo FROM token_seguridad WHERE id_usuario=? AND tipo='VERIFICACION' AND usado=0 ORDER BY id_token DESC LIMIT 1", [$idu]) ?: '');
    $n->post('/verificar', ['codigo' => '000000'])->seguir();      // primero uno mal
    if ($n->dice('Cuenta verificada')) {
        sim_incidente('PORTAL_CODIGO_CUALQUIERA', 'Se verificó la cuenta con un código incorrecto', 'CRITICO');
    }
    if ($cod !== '') {
        $n->post('/verificar', ['codigo' => $cod])->seguir();
    }
}

// ---------------------------------------------------------------------------
// 2. La clienta con ficha en el salón (sin cuenta) se registra: debe enlazarse
// ---------------------------------------------------------------------------
if ($DIA === 17 || $DIA === 49) {
    $ficha = DB::selectOne('SELECT pe.id_persona, pe.email, cl.id_cliente
                              FROM cliente cl JOIN persona pe ON pe.id_persona = cl.id_persona
                             WHERE cl.id_usuario IS NULL AND pe.email IS NOT NULL
                               AND EXISTS (SELECT 1 FROM cita c WHERE c.id_cliente = cl.id_cliente)
                             LIMIT 1');
    if ($ficha) {
        $antesClientes = (int) DB::scalar('SELECT COUNT(*) FROM cliente');
        $m = new Nav();
        $m->quien = 'enlace' . $DIA;
        $m->post('/registro', [
            'nombre' => 'Enlace', 'apellido' => 'Prueba', 'email' => $ficha->email,
            'username' => 'enlace' . $DIA, 'password' => 'clienta123', 'password2' => 'clienta123',
        ])->seguir();
        $despues = (int) DB::scalar('SELECT COUNT(*) FROM cliente');
        if ($despues > $antesClientes) {
            sim_incidente('PORTAL_CLIENTE_DUPLICADO',
                'Registrarse con el correo de una ficha existente creó un cliente NUEVO (' . $antesClientes . ' → ' . $despues . ')', 'ALTO');
        } else {
            sim_log(['tipo' => 'ANOM_OK', 'cod' => 'PORTAL_ENLACE', 'msg' => $m->flashTxt()]);
        }
    }
}

// ---------------------------------------------------------------------------
// 3. La clienta usa el portal
// ---------------------------------------------------------------------------
$c = new Nav();
$usuarioPortal = $idu && (int) DB::scalar('SELECT activo FROM usuario WHERE id_usuario=?', [$idu]) === 1 ? $u : 'cliente';
$pass = $usuarioPortal === 'cliente' ? 'cliente123' : 'clienta123';

if ($c->entrar($usuarioPortal, $pass)) {
    $c->get('/portal');
    $c->get('/portal/promociones');
    $c->get('/portal/citas');
    $c->get('/portal/valoraciones');

    // Recordatorios
    $c->post('/portal/recordatorios', ['dias_antes' => (string) (1 + $DIA % 3), 'activo' => '1'])->seguir();
    $c->post('/portal/recordatorios', ['dias_antes' => '40', 'activo' => '1'])->seguir();   // fuera de rango
    if ((int) DB::scalar('SELECT COALESCE(MAX(dias_antes),0) FROM preferencia_recordatorio') > 15) {
        sim_incidente('PORTAL_RECORDATORIO_RANGO', 'Se guardó una anticipación mayor a 15 días', 'BAJO');
    }

    // Reservar
    $c->get('/portal/disponibilidad', ['servicios' => [12]]);
    $j = json_decode($c->body, true);
    $dias = array_values(array_filter($j['dias'] ?? [], fn ($d) => $d > $hoy));
    if ($dias) {
        $f = $dias[array_rand(array_slice($dias, 0, 6))] ?? $dias[0];
        $c->get('/portal/disponibilidad', ['servicios' => [12], 'fecha' => $f]);
        $j2 = json_decode($c->body, true);
        if (! empty($j2['horas'])) {
            $h = $j2['horas'][array_rand($j2['horas'])]['hora'];
            $c->post('/portal/reservar', ['servicios' => [12], 'fecha_hora' => $f . ' ' . $h])->seguir();
            if (! $c->dice('reservada')) {
                sim_log(['tipo' => 'PORTAL_RESERVA_NO', 'msg' => $c->flashTxt()]);
            }
        }
    }

    // Anotar una seña sobre su propia cita
    $idcli = (int) DB::scalar('SELECT id_cliente FROM cliente WHERE id_usuario = ?',
        [(int) DB::scalar('SELECT id_usuario FROM usuario WHERE username=?', [$usuarioPortal])]);
    $mia = DB::selectOne('SELECT id_cita FROM cita WHERE id_cliente = ? AND id_estado_cita IN (1,2) AND fecha_hora > NOW() ORDER BY fecha_hora LIMIT 1', [$idcli]);
    if ($mia) {
        $c->post('/portal/sena', ['id_cita' => (int) $mia->id_cita, 'monto' => '60000'])->seguir();
        $c->post('/portal/sena', ['id_cita' => (int) $mia->id_cita, 'monto' => '-5000'])->seguir();
        $neg = (int) DB::scalar('SELECT COUNT(*) FROM sena_solicitud WHERE monto < 0');
        if ($neg) {
            sim_incidente('PORTAL_SENA_NEGATIVA', 'Se anotó una seña de monto negativo', 'MEDIO');
        }
    }

    // Calificar una cita ya atendida
    $atend = DB::selectOne('SELECT id_cita FROM cita WHERE id_cliente = ? AND id_estado_cita = 4
                              AND NOT EXISTS (SELECT 1 FROM calificacion k WHERE k.id_cita = cita.id_cita) LIMIT 1', [$idcli]);
    if ($atend) {
        $c->post('/portal/calificar', ['id_cita' => (int) $atend->id_cita, 'puntaje' => (string) (3 + $DIA % 3),
            'comentario' => 'Quedé conforme'])->seguir();
        $c->post('/portal/calificar', ['id_cita' => (int) $atend->id_cita, 'puntaje' => '9',
            'comentario' => 'segunda vez'])->seguir();
        $n2 = (int) DB::scalar('SELECT COUNT(*) FROM calificacion WHERE id_cita = ?', [(int) $atend->id_cita]);
        if ($n2 > 1) {
            sim_incidente('PORTAL_DOBLE_CALIFICACION', 'La misma cita quedó calificada ' . $n2 . ' veces', 'MEDIO');
        }
        if ((int) DB::scalar('SELECT COALESCE(MAX(puntaje),0) FROM calificacion') > 5) {
            sim_incidente('PORTAL_PUNTAJE_FUERA', 'Se guardó un puntaje mayor a 5', 'MEDIO');
        }
    }

    // Atención en vivo + pedido (si hay una cita en proceso)
    $enCurso = DB::selectOne('SELECT id_cita FROM cita WHERE id_cliente = ? AND id_estado_cita = 5 LIMIT 1', [$idcli]);
    if ($enCurso) {
        $c->get('/portal/atencion', ['id' => (int) $enCurso->id_cita]);
        $c->get('/portal/atencion/json', ['id' => (int) $enCurso->id_cita]);
        $c->post('/portal/pedir', ['id_cita' => (int) $enCurso->id_cita, 'pedido' => 'Sumame las cejas por favor'])->seguir();
    }

    // Cancelar su propia cita
    if ($DIA % 2 === 1) {
        $mia2 = DB::selectOne('SELECT id_cita FROM cita WHERE id_cliente = ? AND id_estado_cita IN (1,2) AND fecha_hora > NOW() ORDER BY fecha_hora DESC LIMIT 1', [$idcli]);
        if ($mia2) {
            $c->post('/portal/cancelar', ['id_cita' => (int) $mia2->id_cita])->seguir();
        }
    }

    $c->salir();
}

// ---------------------------------------------------------------------------
// 4. El salón confirma (o rechaza) las señas anotadas desde el portal
// ---------------------------------------------------------------------------
$sol = DB::select('SELECT s.id_solicitud, s.id_cita, s.monto FROM sena_solicitud s
                    WHERE s.id_cobro IS NULL AND s.rechazada_en IS NULL LIMIT 3');
if ($sol) {
    $r = new Nav();
    if ($r->entrar('recepcion', 'recepcion123')) {
        foreach ($sol as $s) {
            $est = (int) DB::scalar('SELECT id_estado_cita FROM cita WHERE id_cita=?', [(int) $s->id_cita]);
            if (in_array($est, [3, 6], true)) {
                continue;
            }
            $r->post('/facturacion/sena', ['id_cita' => (int) $s->id_cita, 'id_metodo_pago' => 1,
                'monto' => (string) (int) $s->monto, 'id_solicitud' => (int) $s->id_solicitud, 'dia' => $hoy])->seguir();
        }
        $r->salir();
    }
}

// ---------------------------------------------------------------------------
// 5. El enlace del correo (token de cita), sin sesión
// ---------------------------------------------------------------------------
$tok = DB::selectOne("SELECT t.token, t.id_cita FROM token_cita t
                        JOIN cita c ON c.id_cita = t.id_cita
                       WHERE c.id_estado_cita IN (1,2) AND c.fecha_hora > NOW()
                         AND (t.expira_en IS NULL OR t.expira_en > NOW()) LIMIT 1");
if ($tok) {
    $t = new Nav();
    $t->quien = 'token';
    $t->get('/mi-cita', ['t' => $tok->token]);
    if ($t->status !== 200) {
        sim_log(['tipo' => 'TOKEN_CITA', 'st' => $t->status]);
    }
    $t->get('/mi-cita/calendario', ['t' => $tok->token]);
    // Token inventado
    $t->get('/mi-cita', ['t' => str_repeat('a', 40)]);
    if ($t->status === 200 && ! $t->dice('no')) {
        sim_log(['tipo' => 'TOKEN_INVENTADO', 'st' => $t->status]);
    }
}

sim_log(['tipo' => 'PORTAL_FIN', 'dia' => $DIA]);
