<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * Envía correos vía SMTP (sin dependencia de BD).
 * Soporta adjuntos (PDF KuDE + XML firmado).
 * transport=smtp → envío real | transport=file → solo guarda .eml en logs/emails/
 */
final class MailService
{
    public function __construct(private array $config)
    {
    }

    /**
     * @param string $toEmail     Destinatario
     * @param string $subject     Asunto
     * @param string $htmlBody    Cuerpo HTML
     * @param array  $attachments Rutas absolutas de archivos a adjuntar (PDF, XML, etc.)
     */
    public function send(string $toEmail, string $subject, string $htmlBody, array $attachments = []): void
    {
        $transport = (string)($this->config['transport'] ?? 'smtp');
        $fromEmail = (string)($this->config['from_email'] ?? '');
        if ($fromEmail === '') {
            throw new RuntimeException('MAIL_FROM_EMAIL no está configurado en automatizador.php.');
        }

        $message = $this->buildMime($fromEmail, (string)($this->config['from_name'] ?? ''), $toEmail, $subject, $htmlBody, $attachments);

        // Guardar .eml como evidencia
        $emailDir = rtrim((string)($this->config['email_dir'] ?? (APP_ROOT . '/logs/emails')), '/');
        if (!is_dir($emailDir)) {
            @mkdir($emailDir, 0775, true);
        }
        $eml = $emailDir . '/email_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.eml';
        @file_put_contents($eml, $message);

        if ($transport === 'smtp') {
            $this->smtp($toEmail, $fromEmail, $message);
        }
        // transport=file: solo guarda el .eml, no envía
    }

    private function buildMime(string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody, array $attachments): string
    {
        $boundary = 'b_' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'To: <' . $toEmail . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'Date: ' . date(DATE_RFC2822),
        ];

        $parts = [];
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = $htmlBody;

        foreach ($attachments as $path) {
            if ($path === '' || !is_file($path)) continue;
            $filename = basename($path);
            $mime = match(true) {
                str_ends_with(strtolower($filename), '.pdf') => 'application/pdf',
                str_ends_with(strtolower($filename), '.xml') => 'application/xml',
                default => 'application/octet-stream',
            };
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: ' . $mime . '; name="' . $filename . '"';
            $parts[] = 'Content-Transfer-Encoding: base64';
            $parts[] = 'Content-Disposition: attachment; filename="' . $filename . '"';
            $parts[] = '';
            $parts[] = chunk_split(base64_encode((string)file_get_contents($path)));
        }

        $parts[] = '--' . $boundary . '--';
        $parts[] = '';

        return implode("\r\n", array_merge($headers, [''], $parts));
    }

    private function smtp(string $toEmail, string $fromEmail, string $message): void
    {
        $host       = (string)($this->config['host'] ?? '');
        $port       = (int)($this->config['port'] ?? 587);
        $encryption = strtolower((string)($this->config['encryption'] ?? 'tls'));
        $username   = (string)($this->config['username'] ?? '');
        $password   = (string)($this->config['password'] ?? '');

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            throw new MailConnectionException('No se pudo conectar al SMTP: ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($socket, 20);

        $read = function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') break;
            }
            if (stream_get_meta_data($socket)['timed_out'] ?? false) {
                throw new MailConnectionException('Timeout en diálogo SMTP.');
            }
            return $data;
        };
        $write  = fn(string $cmd) => fwrite($socket, $cmd . "\r\n");
        $expect = function (array $codes, string $resp) {
            if (!in_array((int)substr($resp, 0, 3), $codes, true)) {
                throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($resp));
            }
        };

        $expect([220], $read());
        $write('EHLO localhost');
        $expect([250], $read());

        if ($encryption === 'tls') {
            $write('STARTTLS');
            $expect([220], $read());
            if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new MailConnectionException('Falló negociación TLS.');
            }
            $write('EHLO localhost');
            $expect([250], $read());
        }

        if ($username !== '') {
            $write('AUTH LOGIN');
            $expect([334], $read());
            $write(base64_encode($username));
            $expect([334], $read());
            $write(base64_encode($password));
            $expect([235], $read());
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        $expect([250], $read());
        $write('RCPT TO:<' . $toEmail . '>');
        $expect([250, 251], $read());
        $write('DATA');
        $expect([354], $read());
        fwrite($socket, $message . "\r\n.\r\n");
        $expect([250], $read());
        $write('QUIT');
        fclose($socket);
    }
}
