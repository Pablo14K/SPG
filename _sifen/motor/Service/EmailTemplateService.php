<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Construye asunto y cuerpo HTML del correo que recibe el cliente con PDF/XML adjuntos.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

/**
 * Comentario de codigo: clase EmailTemplateService. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class EmailTemplateService
{
    public function electronicInvoice(array $invoice, string $cdc): array
    {
        $customer = htmlspecialchars((string) $invoice['cliente_nombre'], ENT_QUOTES, 'UTF-8');
        $number = htmlspecialchars((string) $invoice['numero'], ENT_QUOTES, 'UTF-8');
        $cdcEsc = htmlspecialchars($cdc, ENT_QUOTES, 'UTF-8');

        $subject = 'Factura electrónica #' . $number;
        $html = <<<HTML
<h2>Factura electrónica</h2>
<p>Hola {$customer},</p>
<p>Adjuntamos tu KuDE en PDF y el archivo XML de la factura electrónica.</p>
<p><strong>Número:</strong> {$number}<br><strong>CDC:</strong> {$cdcEsc}</p>
<p>Gracias.</p>
HTML;

        return ['subject' => $subject, 'html' => $html];
    }
}
