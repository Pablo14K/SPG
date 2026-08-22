<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Excepcion de conexion SMTP. Marca fallos de RED/transporte (sin internet, timeout, TLS) que SI son reintentables.
 * Donde se usa: la lanza MailService cuando no logra conectar/negociar con el servidor SMTP; el endpoint de envio la usa
 *               para dejar el correo en estado SUSPENDIDO (reanudable) en vez de ERROR permanente.
 * Nota: ver docs/DOCUMENTACION_TECNICA_COMPLETA.md (flujo de correos) y GUIA_COMENTARIOS_CODIGO.md.
 */

namespace App\Service;

use RuntimeException;

/**
 * Error de conexión/transporte SMTP (reintentable).
 *
 * A diferencia de un RuntimeException común (que representa un rechazo
 * permanente: credenciales inválidas, destinatario rechazado, etc.), esta
 * excepción indica que el problema es de RED — típicamente "se fue el
 * internet" — y por lo tanto el correo NO debe marcarse como ERROR sino como
 * SUSPENDIDO, para reanudar el envío cuando vuelva la conexión.
 */
final class MailConnectionException extends RuntimeException
{
}
