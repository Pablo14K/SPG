<?php
declare(strict_types=1);

namespace Automatizador;

use App\Service\EmailTemplateService;
use App\Service\InvoiceMapper;
use App\Service\KudeService;
use App\Service\MailConnectionException;
use App\Service\MailService;
use App\Service\SifenEmitter;
use RuntimeException;
use Throwable;

/**
 * Orquestador del automatizador.
 * Recorre la carpeta de entrada, procesa cada .txt y por cada factura:
 *   1. Emite el XML SIFEN (CDC → XML → firma → QR → envío DNIT)
 *   2. Genera el KuDE PDF
 *   3. Envía el correo al cliente con PDF + XML adjuntos (si tiene email y mail configurado)
 *
 * Reglas de archivo:
 *   .txt OK      → se mueve a procesados/
 *   .txt error   → se mueve a errores/ + <archivo>.error.txt con el detalle
 *   mientras procesa → se renombra a .procesando (lock simple)
 */
final class Procesador
{
    private InvoiceFactory $factory;
    private InvoiceMapper $mapper;
    private SifenEmitter $emitter;
    private KudeService $kude;
    private ?MailService $mail;

    public function __construct(
        private array $autoConfig,
        private array $sifenConfig,
        private Logger $log,
    ) {
        $this->factory = new InvoiceFactory($autoConfig['emisor'] ?? []);
        $this->mapper  = new InvoiceMapper();
        $this->emitter = new SifenEmitter($sifenConfig);
        $this->kude    = new KudeService($autoConfig['rutas']['salida']);
        $this->mail    = $this->buildMailService();

        foreach ($autoConfig['rutas'] as $dir) {
            if (is_string($dir) && !is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }

    /** Procesa todos los .txt de la carpeta de entrada. Devuelve [ok, errores]. */
    public function procesarEntrada(): array
    {
        $entrada  = rtrim($this->autoConfig['rutas']['entrada'], '/');
        $archivos = glob($entrada . '/*.txt') ?: [];
        $ok = $err = 0;

        if ($archivos === []) {
            $this->log->info('No hay archivos .txt en la carpeta de entrada.');
            return [0, 0];
        }

        foreach ($archivos as $archivo) {
            $this->procesarArchivo($archivo) ? $ok++ : $err++;
        }
        $this->log->info("Resumen: $ok archivo(s) OK, $err con error.");
        return [$ok, $err];
    }

    /** Procesa un único archivo .txt. Devuelve true si todas sus facturas se emitieron. */
    public function procesarArchivo(string $archivo): bool
    {
        $nombre  = basename($archivo);
        $this->log->info("Procesando: $nombre");

        $trabajo = $archivo . '.procesando';
        if (!@rename($archivo, $trabajo)) {
            $this->log->error("No se pudo bloquear $nombre (¿permisos?).");
            return false;
        }

        try {
            $contenido = file_get_contents($trabajo);
            if ($contenido === false) {
                throw new RuntimeException('No se pudo leer el archivo.');
            }

            $facturas   = (new TxtParser())->parse($contenido);
            $resultados = [];

            foreach ($facturas as $i => $cruda) {
                $n       = $i + 1;
                $built   = $this->factory->build($cruda);
                $payload = $this->mapper->buildPayload($built['emitter'], $built['invoice'], $this->sifenConfig);

                // ── 1) Emitir XML SIFEN ──────────────────────────────────
                $resp = $this->emitter->emit($payload);
                if (empty($resp['ok'])) {
                    $msg = (string)($resp['respuesta']['message'] ?? 'SIFEN rechazó el documento.');
                    throw new RuntimeException("Factura #$n rechazada: $msg");
                }

                $cdc = (string)$resp['cdc'];

                // ── 2) Generar KuDE PDF ───────────────────────────────────
                $kudePath = $this->kude->generate([
                    'cdc'       => $cdc,
                    'qr_text'   => (string)$resp['qr_text'],
                    'emisor'    => $payload['emisor'],
                    'cliente'   => $payload['cliente'],
                    'documento' => $payload['documento'],
                    'items'     => $payload['items'],
                    'totales'   => $payload['totales'],
                ]);

                $this->log->info("  Factura #$n → CDC $cdc | {$resp['estado']}");

                // ── 3) Enviar correo al cliente ───────────────────────────
                $emailCliente = (string)($payload['cliente']['email'] ?? '');
                $mailEnviado  = false;
                if ($emailCliente !== '' && $this->mail !== null) {
                    $mailEnviado = $this->enviarCorreo(
                        $emailCliente,
                        $cdc,
                        $payload['cliente']['nombre'],
                        $payload['documento']['numero'],
                        (string)$resp['xml_path'],
                        $kudePath,
                        $n
                    );
                } elseif ($emailCliente === '') {
                    $this->log->info("  Factura #$n → sin email de cliente, no se envía correo.");
                } else {
                    $this->log->info("  Factura #$n → correo no configurado (MAIL_* vacío en config).");
                }

                $resultados[] = [
                    'factura'      => $n,
                    'cdc'          => $cdc,
                    'estado'       => (string)$resp['estado'],
                    'xml'          => (string)$resp['xml_path'],
                    'kude'         => $kudePath,
                    'track_id'     => (string)$resp['track_id'],
                    'mail_enviado' => $mailEnviado,
                ];
            }

            $this->mover($trabajo, $this->autoConfig['rutas']['procesados'], $nombre);
            $this->escribirResultado($nombre, $resultados);
            $this->log->info("OK: $nombre (" . count($resultados) . ' factura(s))');
            return true;

        } catch (Throwable $e) {
            $this->log->error("FALLÓ $nombre: " . $e->getMessage());
            $destino = $this->mover($trabajo, $this->autoConfig['rutas']['errores'], $nombre);
            @file_put_contents(
                $destino . '.error.txt',
                '[' . date('Y-m-d H:i:s') . "] $nombre\n" . $e->getMessage() . "\n"
            );
            return false;
        }
    }

    private function enviarCorreo(
        string $email, string $cdc, string $nombre, string $numero,
        string $xmlPath, string $kudePath, int $n
    ): bool {
        try {
            $tpl = (new EmailTemplateService())->electronicInvoice(
                ['cliente_nombre' => $nombre, 'numero' => $numero],
                $cdc
            );
            $this->mail->send(
                $email,
                $tpl['subject'],
                $tpl['html'],
                array_filter([$kudePath, $xmlPath], fn($p) => $p !== '' && is_file($p))
            );
            $this->log->info("  Factura #$n → correo enviado a $email");
            return true;
        } catch (MailConnectionException $e) {
            $this->log->error("  Factura #$n → error de conexión SMTP (reintentable): " . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            $this->log->error("  Factura #$n → no se pudo enviar el correo: " . $e->getMessage());
            return false;
        }
    }

    private function buildMailService(): ?MailService
    {
        $mail = $this->autoConfig['mail'] ?? [];
        if (empty($mail['from_email'])) {
            return null; // correo no configurado, se omite silenciosamente
        }
        $emailDir = $this->autoConfig['rutas']['logs'] . '/emails';
        return new MailService(array_merge($mail, ['email_dir' => $emailDir]));
    }

    private function mover(string $src, string $dirDestino, string $nombre): string
    {
        $dirDestino = rtrim($dirDestino, '/');
        if (!is_dir($dirDestino)) {
            @mkdir($dirDestino, 0775, true);
        }
        $base    = pathinfo($nombre, PATHINFO_FILENAME);
        $destino = $dirDestino . '/' . $base . '_' . date('YmdHis') . '.txt';
        @rename($src, $destino);
        return $destino;
    }

    private function escribirResultado(string $nombre, array $resultados): void
    {
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $file = rtrim($this->autoConfig['rutas']['salida'], '/') . '/' . $base . '_' . date('YmdHis') . '.json';
        @file_put_contents($file, json_encode([
            'archivo'   => $nombre,
            'procesado' => date('c'),
            'modo'      => $this->sifenConfig['mode'] ?? 'mock',
            'facturas'  => $resultados,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
