<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** El aviso que le llega a la clienta: recordatorio, confirmación o cambio. */
class AvisoCita extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $tipo,
        public string $mensaje,
        public string $cliente = '',
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titulo() . ' · ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'correo.aviso_cita', with: [
            'titulo' => $this->titulo(),
            'esRecordatorio' => $this->tipo === 1,
            'textoBoton' => $this->tipo === 1 ? 'Ver o reprogramar mi cita' : 'Reprogramar o cambiar de profesional',
        ]);
    }

    private function titulo(): string
    {
        return match ($this->tipo) {
            1 => 'Recordatorio de tu cita',
            2 => 'Tu cita quedó agendada',
            3 => 'Cambio en tu cita',
            4 => 'Promociones del salón',
            default => 'Aviso del salón',
        };
    }
}
