<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Calcula digito verificador modulo 11. Se usa en CDC y validaciones numericas.
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

/**
 * Comentario de codigo: clase Mod11. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class Mod11
{
    public static function calculate(string $numeric): int
    {
        $factor = 2;
        $sum = 0;
        for ($i = strlen($numeric) - 1; $i >= 0; $i--) {
            $sum += ((int) $numeric[$i]) * $factor;
            $factor = $factor === 11 ? 2 : $factor + 1;
        }

        $remainder = $sum % 11;
        $dv = 11 - $remainder;
        if ($dv === 10) {
            return 1;
        }
        if ($dv === 11) {
            return 0;
        }
        return $dv;
    }
}
