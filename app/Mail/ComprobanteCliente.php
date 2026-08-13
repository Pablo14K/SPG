<?php

declare(strict_types=1);

namespace App\Mail;

use App\Servicios\Sifen;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El comprobante, por correo, para la clienta.
 *
 * **El detalle va escrito en el cuerpo**, para que se lea de una en el
 * teléfono sin abrir ningún archivo. Y si el comprobante se declaró ante la
 * DNIT, **se le adjuntan el KuDE en PDF y el XML**, que es lo que pide el
 * manual del SIFEN: son los documentos con valor fiscal, y el cuerpo del
 * correo no los reemplaza.
 *
 * Los adjuntos salen de la copia que guarda el sistema
 * (`storage/app/sifen/<factura>/`), no del Automatizador: así el comprobante
 * se puede reenviar aunque el servicio esté apagado.
 *
 * El **Comprobante de pago** no lleva adjuntos y está bien que así sea: no se
 * declara, así que no existe ningún KuDE ni XML que mandarle.
 */
class ComprobanteCliente extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  object  $f  La fila de `vw_factura_resumen`
     * @param  array<int, object>  $items  El detalle
     * @param  array<int, object>  $cobros  Lo que se pagó y con qué
     */
    public function __construct(
        public object $f,
        public array $items,
        public array $cobros,
        public string $salon,
        public string $nota = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->f->tipo_comprobante . ' ' . $this->f->nro_comprobante . ' · ' . $this->salon
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.comprobante', with: ['adjuntos' => $this->queAdjunta()]);
    }

    /**
     * El KuDE y el XML, si este comprobante se declaró y tenemos la copia.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $out = [];
        foreach ($this->queAdjunta() as $ext => $ruta) {
            $out[] = Attachment::fromPath($ruta)
                ->as(str_replace('/', '-', $this->f->nro_comprobante) . '.' . $ext)
                ->withMime($ext === 'pdf' ? 'application/pdf' : 'application/xml');
        }

        return $out;
    }

    /** @return array<string, string> extensión → ruta */
    private function queAdjunta(): array
    {
        if (! Sifen::activo() || ! Sifen::esElectronico((int) ($this->f->id_tipo_comprobante ?? 0))) {
            return [];
        }

        $out = [];
        foreach (['pdf', 'xml'] as $ext) {
            if ($ruta = Sifen::copia((int) $this->f->id_factura, $ext)) {
                $out[$ext] = $ruta;
            }
        }

        return $out;
    }
}
