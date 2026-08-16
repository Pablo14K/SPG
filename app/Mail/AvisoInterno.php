<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El aviso que le llega al equipo, no a la clienta.
 *
 * Son los de `tipo_notificacion.destinatario = 'INTERNO'`: hoy, que un producto
 * llegó al mínimo y que se cerró una caja. Hasta la 7.29.0 **no se mandaban a
 * ningún lado**: el despachador sólo tomaba los de destinatario CLIENTE y estos
 * quedaban en la cola hasta que el barrido de NO-02 los cerraba como FALLIDA.
 * En 60 días fueron 21 alertas de stock que no leyó nadie.
 */
class AvisoInterno extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $tipo,
        public string $mensaje,
        public string $paraQuien = '',
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titulo() . ' · ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'correo.aviso_interno', with: [
            'titulo' => $this->titulo(),
            'textoBoton' => $this->tipo === 5 ? 'Ver el stock' : 'Abrir el sistema',
        ]);
    }

    private function titulo(): string
    {
        return match ($this->tipo) {
            5 => 'Hay productos por reponer',
            6 => 'Se cerró una caja',
            default => 'Aviso del sistema',
        };
    }
}
