<?php
declare(strict_types=1);

/*
 * AUTOMATIZADOR SIFEN — Endpoint HTTP
 *
 * Recibe un .txt con facturas de cualquier manera:
 *   POST multipart  → campo "archivo" (file upload)
 *   POST raw body   → Content-Type: text/plain
 *   POST form       → campo "contenido" (texto plano)
 *
 * Responde siempre JSON:
 *   { ok: true,  facturas: [{cdc, estado, xml_url, kude_url, mail_enviado}] }
 *   { ok: false, error: "mensaje" }
 *
 * Autenticación: token en header  X-API-Token: <SIFEN_API_TOKEN del .env>
 * Correo:         X-SPG-Correo: no  → no manda el comprobante por correo, lo
 *                 hace quien emite. Sin la cabecera, se comporta como siempre.
 * Si SIFEN_API_TOKEN está vacío en .env, el endpoint es público (solo usar en red privada).
 */

require __DIR__ . '/../src/bootstrap.php';
// $AUTO_CONFIG y $SIFEN_CONFIG quedan disponibles desde bootstrap.php

header('Content-Type: application/json; charset=utf-8');

// ── Autenticación por token ───────────────────────────────────────────────────
$tokenEsperado = env('SIFEN_API_TOKEN');
if ($tokenEsperado !== '') {
    $tokenRecibido = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($tokenRecibido !== $tokenEsperado) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Token inválido o ausente.']);
        exit;
    }
}

// ── Solo POST ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Solo se aceptan peticiones POST.']);
    exit;
}

// ── Obtener el contenido del .txt ─────────────────────────────────────────────
$contenido = '';

if (!empty($_FILES['archivo']['tmp_name'])) {
    // Opción A: file upload (multipart/form-data, campo "archivo")
    $contenido = file_get_contents($_FILES['archivo']['tmp_name']) ?: '';
} elseif (!empty($_POST['contenido'])) {
    // Opción B: campo de formulario "contenido"
    $contenido = (string)$_POST['contenido'];
} else {
    // Opción C: body raw (Content-Type: text/plain)
    $contenido = file_get_contents('php://input') ?: '';
}

$contenido = trim($contenido);
if ($contenido === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibió contenido. Enviá el .txt como archivo (campo "archivo"), campo "contenido" o raw body.']);
    exit;
}

// ── Procesar ──────────────────────────────────────────────────────────────────
try {
    $log  = new \Automatizador\Logger($AUTO_CONFIG['rutas']['logs']);
    $parser  = new \Automatizador\TxtParser();
    $factory = new \Automatizador\InvoiceFactory($AUTO_CONFIG['emisor']);
    $mapper  = new \App\Service\InvoiceMapper();
    $emitter = new \App\Service\SifenEmitter($SIFEN_CONFIG);
    $kude    = new \App\Service\KudeService($AUTO_CONFIG['rutas']['salida']);
    $mail    = construirMail($AUTO_CONFIG);

    $facturas = $parser->parse($contenido);
    $resultados = [];

    foreach ($facturas as $i => $cruda) {
        $built   = $factory->build($cruda);
        $payload = $mapper->buildPayload($built['emitter'], $built['invoice'], $SIFEN_CONFIG);

        $resp = $emitter->emit($payload);
        if (empty($resp['ok'])) {
            $msg = (string)($resp['respuesta']['message'] ?? 'SIFEN rechazó el documento.');
            throw new RuntimeException('Factura #' . ($i + 1) . ': ' . $msg);
        }

        $cdc      = (string)$resp['cdc'];
        $xmlPath  = (string)$resp['xml_path'];
        $kudePath = $kude->generate([
            'cdc'       => $cdc,
            'qr_text'   => (string)$resp['qr_text'],
            'emisor'    => $payload['emisor'],
            'cliente'   => $payload['cliente'],
            'documento' => $payload['documento'],
            'items'     => $payload['items'],
            'totales'   => $payload['totales'],
        ]);

        // Correo al cliente
        $mailEnviado = false;
        $emailCliente = (string)($payload['cliente']['email'] ?? '');
        if ($emailCliente !== '' && $mail !== null) {
            try {
                $tpl = (new \App\Service\EmailTemplateService())->electronicInvoice(
                    ['cliente_nombre' => $payload['cliente']['nombre'], 'numero' => $payload['documento']['numero']],
                    $cdc
                );
                $mail->send($emailCliente, $tpl['subject'], $tpl['html'],
                    array_filter([$kudePath, $xmlPath], fn($p) => $p !== '' && is_file($p))
                );
                $mailEnviado = true;
            } catch (\Throwable $e) {
                $log->error("Mail factura #" . ($i+1) . ": " . $e->getMessage());
            }
        }

        $log->info("HTTP → CDC $cdc | {$resp['estado']}");

        $resultados[] = [
            'factura'      => $i + 1,
            'cdc'          => $cdc,
            'estado'       => (string)$resp['estado'],
            'track_id'     => (string)$resp['track_id'],
            'xml_url'      => urlArchivo($xmlPath),
            'kude_url'     => urlArchivo($kudePath),
            'mail_enviado' => $mailEnviado,
        ];
    }

    echo json_encode(['ok' => true, 'facturas' => $resultados], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function construirMail(array $auto): ?\App\Service\MailService {
    // Quien pide la emisión puede decir que NO mande el correo, y manda eso.
    //
    // El comprobante lo manda UN SOLO sistema —el SPG, con la cuenta que el
    // salón carga en su pantalla—, porque con los dos prendidos la clienta lo
    // recibe dos veces desde direcciones distintas y cambiar la cuenta en un
    // lado arregla la mitad. Antes eso dependía de dejar MAIL_FROM_EMAIL vacío
    // acá; ahora lo decide quien emite, en cada petición.
    $pedido = strtolower(trim((string)($_SERVER['HTTP_X_SPG_CORREO'] ?? '')));
    if (in_array($pedido, ['no', '0', 'false'], true)) return null;

    if (empty($auto['mail']['from_email'])) return null;
    $emailDir = $auto['rutas']['logs'] . '/emails';
    return new \App\Service\MailService(array_merge($auto['mail'], ['email_dir' => $emailDir]));
}

function urlArchivo(string $path): string {
    if ($path === '' || !is_file($path)) return '';
    $base = rtrim(env('APP_URL'), '/');
    $salida = rtrim(AUTO_ROOT . '/salida', '/');
    if (str_starts_with($path, $salida)) {
        return $base . '/descargar.php?f=' . urlencode(basename($path));
    }
    return '';
}
