<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El correo con el código de un solo uso.
 *
 * El asunto y el texto cambian según para qué es el código: recibir «Verificá
 * tu cuenta» cuando lo que pediste fue cambiar la contraseña haría dudar de si
 * el correo es legítimo.
 */
class CodigoSeguridad extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tipo,
        public string $codigo,
        public string $nombre = '',
        public int $minutos = 30,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto() . ' · ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'correo.codigo', with: [
            'asunto' => $this->asunto(),
            'intro' => $this->intro(),
        ]);
    }

    private function asunto(): string
    {
        return match ($this->tipo) {
            'VERIFICACION' => 'Verificá tu cuenta',
            'RECUPERACION' => 'Recuperación de contraseña',
            'CAMBIO_PASSWORD' => 'Confirmá el cambio de tu contraseña',
            default => 'Código de seguridad',
        };
    }

    private function intro(): string
    {
        return match ($this->tipo) {
            'VERIFICACION' => 'Usá este código para terminar de crear tu cuenta:',
            'RECUPERACION' => 'Usá este código para restablecer tu contraseña:',
            'CAMBIO_PASSWORD' => 'Alguien pidió cambiar la contraseña de tu cuenta. Si fuiste vos, '
                . 'usá este código para confirmarlo:',
            default => 'Tu código de seguridad:',
        };
    }
}
