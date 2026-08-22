<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Generador PDF KuDE de respaldo PHP. Dibuja representacion grafica, tabla, totales, CDC y zona QR.
 *           Pagina automaticamente: si los items no entran en una hoja, continuan en paginas siguientes
 *           numeradas "X/Y"; los totales van solo en la ultima pagina (Manual Tecnico SIFEN v150, seccion 13.3).
 * Donde se usa: forma parte del flujo de facturacion electronica SIFEN documentado en docs/DOCUMENTACION_TECNICA_COMPLETA.md.
 * Nota: los detalles de variables, palabras reservadas y cambios posibles estan centralizados en docs/GUIA_COMENTARIOS_CODIGO.md para no duplicar ruido en cada linea.
 */


namespace App\Service;

use App\Support\FileStore;

/**
 * Genera el KuDE (representación gráfica del DE) en PDF.
 * Formato fiel al capítulo 13 del Manual Técnico SIFEN v150.
 * Usa construcción nativa de PDF sin dependencias externas.
 * Multipágina: los ítems que no entran en una hoja continúan en la siguiente (numeración "X/Y",
 * totales solo en la última página, QR/CDC repetido en todas — sección 13.3 del manual).
 */
/**
 * Comentario de codigo: clase KudeService. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class KudeService
{
    /** Geometría de página A4 (en puntos PDF), compartida entre el paginado y el dibujo. */
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;
    private const MARGIN = 28.0;
    private const ROW_H  = 18.0;   // alto de cada fila de ítem

    /*
     * Paleta del sistema que emite, en el espacio de color de PDF (0..1).
     *
     * **La DNIT no impone diseno al KuDE.** El capitulo 13 del Manual
     * Tecnico v150 fija QUE datos tienen que estar y que se lea que es la
     * representacion grafica de un documento electronico, no como se ve.
     * Lo obligatorio —CDC, QR, la leyenda del XML, la consulta en
     * ekuatia, el timbrado, el numero— queda intacto; lo que cambia es el
     * color y la jerarquia.
     *
     * El oro va SOLO donde hay jerarquia: la banda del titulo y el total.
     * Puesto en todos lados pierde el efecto, que es la regla que el
     * sistema sigue en pantalla.
     */
    private const ORO   = '0.788 0.659 0.298';   // #C9A84C
    private const NEGRO = '0.051 0.051 0.051';   // #0D0D0D
    private const HUESO = '0.969 0.961 0.949';   // #F7F5F2

    public function __construct(private string $outputDir)
    {
    }

    /**
     * Comentario de codigo: Genera el artefacto o valor final requerido por el flujo.
     */
    public function generate(array $data): string
    {
        $cdc  = (string) $data['cdc'];
        $path = rtrim($this->outputDir, '/') . '/' . $cdc . '.pdf';
        FileStore::ensureDir(dirname($path));

        $pdf = $this->buildPdf($data, $cdc);
        file_put_contents($path, $pdf);
        return $path;
    }

    // -------------------------------------------------------
    // PDF builder nativo (sin librerías externas)
    // -------------------------------------------------------

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     * Paginador del KuDE (Manual Tecnico SIFEN v150, seccion 13.3): "La representación gráfica de cada
     * documento electrónico puede contar con una o varias páginas enumeradas. Debiendo indicar para el
     * caso de varias páginas el número de la página en relación con el total. Ejemplo: 2/5. Para el caso
     * de los subtotales o totales debe indicarlos en la última página y el código QR debe ser impreso,
     * al menos, en la primera página."
     * Aqui: se calcula cuantas filas entran por hoja, se parten los items en paginas, los totales se
     * reservan para la ultima y el QR/CDC se imprime al pie de TODAS las paginas (cumple de sobra el
     * "al menos, en la primera pagina" del manual).
     */
    private function buildPdf(array $data, string $cdc): string
    {
        // Fuentes embebidas (Helvetica es estándar PDF, no necesita embedding)
        $resources = '/Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> '
                   . '/F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >> >>';

        // ---- Capacidad de filas por página (geometría vertical, espejo de renderPage) ----
        // Tope de tabla = margen + encabezado(80) + separación(3) + banda título(14) + receptor(55) = 152.
        $rowsTop     = self::PAGE_H - self::MARGIN - 152.0 - 24.0;               // bajo la cabecera de la tabla
        $qrTop       = self::MARGIN + 14.0 + 90.0;                               // techo de la zona QR/CDC (anclada al pie)
        $totalsH     = 5 * self::ROW_H;                                          // SUBTOTAL+DESCUENTO+TOTAL+letras+IVA
        $perPage     = (int) floor(($rowsTop - $qrTop) / self::ROW_H);           // filas en páginas de continuación
        $perPageLast = (int) floor(($rowsTop - $qrTop - $totalsH) / self::ROW_H); // la última reserva lugar a totales

        // ---- Particionar los ítems en páginas ----
        $items  = array_values($data['items']);
        $chunks = [];
        $offset = 0;
        $n      = count($items);
        while (true) {
            $left = $n - $offset;
            if ($left <= $perPageLast) {              // lo que queda entra junto con los totales
                $chunks[] = array_slice($items, $offset, $left);
                break;
            }
            $take     = min($perPage, $left);
            $chunks[] = array_slice($items, $offset, $take);
            $offset  += $take;
            if ($offset >= $n) {                      // los totales ya no entran: página propia para ellos
                $chunks[] = [];
                break;
            }
        }

        // ---- Renderizar cada página y ensamblar ----
        $totalPages = count($chunks);
        $streams    = [];
        foreach ($chunks as $i => $pageItems) {
            $streams[] = $this->renderPage($data, $cdc, $pageItems, $i + 1, $totalPages);
        }

        return $this->assemblePdf($streams, $resources, self::PAGE_W, self::PAGE_H);
    }

    /**
     * Comentario de codigo: Dibuja UNA página del KuDE y devuelve su content stream PDF.
     * El encabezado, la banda de título, el receptor, la cabecera de tabla y la zona QR/CDC se repiten
     * en todas las páginas; $items son solo las filas de esta página; los subtotales/totales se dibujan
     * únicamente en la última página ($pageNo === $totalPages), como exige el manual SIFEN.
     */
    private function renderPage(array $data, string $cdc, array $items, int $pageNo, int $totalPages): string
    {
        $em  = $data['emisor'];
        $doc = $data['documento'];
        $cli = $data['cliente'];
        $tot = $data['totales'];

        $W      = self::PAGE_W;
        $H      = self::PAGE_H;
        $mg     = self::MARGIN;
        $isLast = ($pageNo === $totalPages);

        $stream = '';

        // ---- Utilidades locales ----
        $esc = fn(string $s): string => $this->pdfStr($s);
        $fmt = fn(float $v, int $d = 0): string => number_format($v, $d, ',', '.');

        // ---- Helpers de dibujo ----
        $rect = function (float $x, float $ry, float $w, float $h, bool $fill = false, string $color = '0 0 0') use (&$stream): void {
            $stream .= sprintf("q %s %s\n%.2f %.2f %.2f %.2f %s\nQ\n",
                ($fill ? "$color rg" : ''), ($fill ? '' : "$color RG"),
                $x, $ry, $w, $h,
                ($fill ? 're f' : 're S')
            );
        };

        $estimateTextWidth = static function (string $txt, float $sz, bool $bold = false): float {
            $units = 0.0;
            foreach (str_split($txt) as $ch) {
                if ($ch >= '0' && $ch <= '9') {
                    $units += 0.58;
                } elseif ($ch === '.' || $ch === ',') {
                    $units += 0.32;
                } elseif ($ch === '-' || $ch === '/') {
                    $units += 0.36;
                } elseif ($ch === ' ') {
                    $units += 0.28;
                } else {
                    $units += 0.64;
                }
            }
            return max(0.1, $units * $sz * ($bold ? 1.05 : 1.0));
        };

        $text = function (string $txt, float $x, float $ty, float $sz = 9, bool $bold = false) use (&$stream, $esc): void {
            $font = $bold ? '/F2' : '/F1';
            $stream .= sprintf("BT %s %.1f Tf 100 Tz %.2f %.2f Td (%s) Tj ET\n",
                $font, $sz, $x, $ty, $esc($txt)
            );
        };

        $numberCell = function (string $txt, float $cellX, float $ty, float $cellW, float $sz = 7.5, bool $bold = false) use (&$stream, $esc, $estimateTextWidth): void {
            if ($txt === '') {
                return;
            }

            $pad = 2.0;
            $safety = 1.08;
            $available = max(1.0, $cellW - ($pad * 2));
            $estimatedWidth = $estimateTextWidth($txt, $sz, $bold);
            $scale = min(100.0, ($available / ($estimatedWidth * $safety)) * 100.0);
            $safeDrawnWidth = $estimatedWidth * $safety * ($scale / 100.0);
            $tx = $cellX + $cellW - $pad - $safeDrawnWidth;
            $font = $bold ? '/F2' : '/F1';

            $stream .= sprintf(
                "q %.2f %.2f %.2f %.2f re W n BT 0 0 0 rg %s %.1f Tf %.2f Tz %.2f %.2f Td (%s) Tj ET Q 0 0 0 rg\n",
                $cellX,
                $ty - $sz - 4,
                $cellW,
                ($sz * 2) + 8,
                $font,
                $sz,
                $scale,
                $tx,
                $ty,
                $esc($txt)
            );
        };

        $fitTextCell = function (string $txt, float $cellX, float $ty, float $cellW, float $sz = 8.0, bool $bold = false) use (&$stream, $esc, $estimateTextWidth): void {
            if ($txt === '') {
                return;
            }

            $pad = 2.0;
            $safety = 1.08;
            $available = max(1.0, $cellW - ($pad * 2));
            $estimatedWidth = $estimateTextWidth($txt, $sz, $bold);
            $scale = min(100.0, ($available / ($estimatedWidth * $safety)) * 100.0);
            $font = $bold ? '/F2' : '/F1';

            $stream .= sprintf(
                "q %.2f %.2f %.2f %.2f re W n BT 0 0 0 rg %s %.1f Tf %.2f Tz %.2f %.2f Td (%s) Tj ET Q 0 0 0 rg\n",
                $cellX,
                $ty - $sz - 4,
                $cellW,
                ($sz * 2) + 8,
                $font,
                $sz,
                $scale,
                $cellX + $pad,
                $ty,
                $esc($txt)
            );
        };

        $line = function (float $x1, float $y1, float $x2, float $y2, float $lw = 0.5) use (&$stream): void {
            $stream .= sprintf("q %.1f w %.2f %.2f m %.2f %.2f l S Q\n", $lw, $x1, $y1, $x2, $y2);
        };

        // ---- ENCABEZADO ----
        // Borde exterior encabezado
        $rect($mg, $H - $mg - 80, $W - 2 * $mg, 80, false);

        // Bloque izquierdo: datos emisor
        $exl = $mg + 4;
        $ey  = $H - $mg - 12;

        $razonSocial = (string) ($em['razon_social_original'] ?? $em['razon_social']);
        $text($razonSocial, $exl, $ey, 11, true);
        // **El numero de casa del .env no se pega a una direccion ajena.**
        // Cuando el emisor viene del sistema de origen, la direccion ya
        // trae la altura adentro: agregarle el numero configurado daba
        // «Avda. Gral. Aquino 1250 N° 123», o sea dos alturas distintas
        // en la misma linea. El XML sigue usando dNumCas, que es donde la
        // DNIT lo pide por separado.
        $dir = (string) $em['direccion'];
        if (trim((string) ($em['sucursal_nombre'] ?? '')) === '' && trim((string) $em['numero_casa']) !== '') {
            $dir .= ' N° ' . $em['numero_casa'];
        }
        $text('Dirección: ' . $dir, $exl, $ey - 13, 8);
        $ciudad = trim((string) ($em['sucursal_ciudad'] ?? '')) ?: $em['descripcion_ciudad'];
        $text('Ciudad: ' . $ciudad, $exl, $ey - 23, 8);
        $text('Tel: ' . $em['telefono'], $exl, $ey - 33, 8);
        $text('Correo: ' . $em['email'], $exl, $ey - 43, 8);
        // Con varias sucursales, de cual salio el papel no se deduce del
        // numero para quien lo recibe: el establecimiento son tres
        // digitos. Se nombra, y solo cuando el sistema de origen lo manda.
        $sucursal = trim((string) ($em['sucursal_nombre'] ?? ''));
        if ($sucursal !== '') {
            $text('Sucursal: ' . mb_strimwidth($sucursal, 0, 45, '...'), $exl, $ey - 63, 8);
        }

        $text('Actividad: ' . mb_strimwidth($em['descripcion_actividad_economica'], 0, 55, '...'), $exl, $ey - 53, 8);

        // Línea vertical separadora
        $sepX = $W - $mg - 170;
        $line($sepX, $H - $mg - 80, $sepX, $H - $mg);

        // Bloque derecho: datos del documento
        $erx = $sepX + 6;
        $text('RUC: ' . $em['ruc'] . '-' . $em['dv'], $erx, $ey, 8);
        $text('Timbrado N°: ' . $em['timbrado_numero'], $erx, $ey - 12, 8);
        $text('Inicio vigencia: ' . $em['timbrado_inicio'], $erx, $ey - 22, 8);

        // Tipo de documento
        $text('Factura Electrónica', $erx, $ey - 40, 11, true);
        $numDoc = 'N°: ' . str_pad($doc['establecimiento'], 3, '0', STR_PAD_LEFT)
                . '-' . str_pad($doc['punto'], 3, '0', STR_PAD_LEFT)
                . '-' . str_pad($doc['numero'], 7, '0', STR_PAD_LEFT);
        $text($numDoc, $erx, $ey - 54, 10, true);

        // ---- TÍTULO KUDE ----
        $kyTop = $H - $mg - 83;
        $rect($mg, $kyTop - 14, $W - 2 * $mg, 14, true, self::ORO);
        // Texto NEGRO sobre el oro: el blanco sobre #C9A84C da 2,1:1 y no
        // se lee. Negro sobre oro da 8,5:1, que es la combinacion que el
        // sistema usa en sus botones principales.
        $stream .= sprintf("BT /F2 10 Tf %s rg %.2f %.2f Td (KuDE de FACTURA ELECTR%sNICA) Tj 0 0 0 rg ET\n",
            self::NEGRO, $mg + 6, $kyTop - 10, "\xD3"
        );

        // Número de página relativo al total (obligatorio si hay varias páginas — manual 13.3, ej. "2/5")
        if ($totalPages > 1) {
            $stream .= sprintf("BT /F2 8 Tf %s rg %.2f %.2f Td (%s) Tj 0 0 0 rg ET\n",
                self::NEGRO, $W - $mg - 70, $kyTop - 10, $esc(sprintf('Página: %d/%d', $pageNo, $totalPages))
            );
        }

        // ---- DATOS DEL RECEPTOR ----
        $ry = $kyTop - 14;
        $rect($mg, $ry - 55, $W - 2 * $mg, 55, false);

        $col1 = $mg + 4;
        $col2 = $mg + 290;
        $rl   = $ry - 12;

        $text('Fecha y hora de emisión: ' . $doc['fecha_emision'], $col1, $rl, 8);
        $text('Cond. de venta: ' . $doc['descripcion_condicion_operacion'], $col2, $rl, 8);
        $rl -= 11;

        $tieneRuc = $cli['ruc'] !== '';
        $docId = $tieneRuc ? ($cli['ruc'] . '-' . $cli['dv']) : ($cli['numero_documento'] ?? 'S/D');
        $rotulo = $tieneRuc
            ? 'RUC: '
            : (trim((string) ($cli['descripcion_tipo_documento'] ?? '')) ?: 'Documento') . ': ';
        $text($rotulo . $docId, $col1, $rl, 8);
        $text('Moneda: ' . ($doc['moneda'] ?? 'PYG'), $col2, $rl, 8);
        $rl -= 11;

        $text('Nombre o Razón Social: ' . mb_strimwidth($cli['nombre'], 0, 50, '...'), $col1, $rl, 8);
        $text('Tipo de transacción: ' . $doc['descripcion_tipo_transaccion'], $col2, $rl, 8);
        $rl -= 11;

        if ($cli['email'] !== '') {
            $text('Correo: ' . $cli['email'], $col1, $rl, 8);
        }
        if ($cli['direccion'] !== '') {
            $text('Dirección: ' . $cli['direccion'], $col2, $rl, 8);
        }

        // ---- TABLA DE ÍTEMS ----
        $tTop = $ry - 55;
        $tW = $W - 2 * $mg;
        $colW = [48, 206, 35, 70, 60, 60]; // REF,DESC,%DESC,PRECIO,EXENTA,5%
        $colW[] = $tW - array_sum($colW); // 10%
        $colX = [$mg];
        foreach ($colW as $i => $w) {
            if ($i > 0) {
                $colX[] = $colX[$i - 1] + $colW[$i - 1];
            }
        }

        // Cabecera tabla
        $rect($mg, $tTop - 24, $tW, 24, true, self::HUESO);
        // Una regla de oro de 1 pt debajo: separa sin gritar, que es lo
        // que una banda maciza hace de mas en una tabla larga.
        $stream .= sprintf("q %s RG 1 w %.2f %.2f m %.2f %.2f l S Q\n",
            self::ORO, $mg, $tTop - 24, $mg + $tW, $tTop - 24);
        $headers = ['REF', 'DESCRIPCIÓN', '%DESC', 'PRECIO\nUNITARIO', 'EXENTA', '5%', '10%'];
        foreach ($headers as $hi => $hdr) {
            $cx = $colX[$hi] + 2;
            $hLines = explode('\n', $hdr);
            foreach ($hLines as $k => $hl) {
                $stream .= sprintf("BT /F2 7 Tf %s rg %.2f %.2f Td (%s) Tj 0 0 0 rg ET\n",
                    self::NEGRO,
                    $cx, $tTop - 10 - ($k * 9), $this->pdfStr($hl)
                );
            }
        }

        // Líneas verticales de cabecera
        foreach ($colX as $i => $cx) {
            if ($i > 0) {
                $line($cx, $tTop, $cx, $tTop - 24, 0.3);
            }
        }

        // Filas de ítems (solo las que corresponden a esta página; el resto sigue en la próxima)
        $rowH  = self::ROW_H;
        $itemY = $tTop - 24;
        foreach ($items as $item) {
            $rect($mg, $itemY - $rowH, $tW, $rowH, false);
            foreach ($colX as $ci => $cx) {
                if ($ci > 0) {
                    $line($cx, $itemY, $cx, $itemY - $rowH, 0.3);
                }
            }

            $exenta = ($item['afectacion_iva'] == 3) ? (float) $item['ea008'] : 0;
            $v5     = ($item['tasa_iva'] == 5 && $item['afectacion_iva'] != 3) ? (float) $item['ea008'] : 0;
            $v10    = ($item['tasa_iva'] == 10 && $item['afectacion_iva'] != 3) ? (float) $item['ea008'] : 0;

            $iy = $itemY - 11;
            $text((string) $item['codigo'],                  $colX[0] + 2, $iy, 7.5);
            $text(mb_strimwidth((string) $item['descripcion'], 0, 38, '...'), $colX[1] + 2, $iy, 7.5);
            $numberCell('0',                                  $colX[2], $iy, $colW[2], 7.5);
            $numberCell($fmt((float) $item['precio_unitario']), $colX[3], $iy, $colW[3], 7.5);
            $numberCell($exenta > 0 ? $fmt($exenta) : '',      $colX[4], $iy, $colW[4], 7.5);
            $numberCell($v5 > 0 ? $fmt($v5) : '',              $colX[5], $iy, $colW[5], 7.5);
            $numberCell($v10 > 0 ? $fmt($v10) : '',            $colX[6], $iy, $colW[6], 7.5);

            $itemY -= $rowH;
        }

        // Y donde termina el contenido dibujado en esta página (bajo el último ítem). En la
        // última página se actualiza tras los totales, para anclar el QR justo debajo de ellos.
        $contentBottom = $itemY;

        // ---- SUBTOTALES Y TOTALES (SOLO en la última página — manual SIFEN 13.3) ----
        if ($isLast) {
            $stY = $itemY;
            $rect($mg, $stY - 18, $tW, 18, false);
            $text('SUBTOTAL', $colX[0] + 2, $stY - 11, 8, true);
            $numberCell($fmt((float) $tot['subtotal_exenta']), $colX[4], $stY - 11, $colW[4], 8, true);
            $numberCell($fmt((float) $tot['subtotal_5']),      $colX[5], $stY - 11, $colW[5], 8, true);
            $numberCell($fmt((float) $tot['subtotal_10']),     $colX[6], $stY - 11, $colW[6], 8, true);
            $stY -= 18;

            $rect($mg, $stY - 18, $tW, 18, false);
            $text('DESCUENTO: 0 %', $colX[0] + 2, $stY - 11, 8);
            $numberCell('0', $colX[6], $stY - 11, $colW[6], 8);
            $stY -= 18;

            // Total operación
            $rect($mg, $stY - 18, $tW, 18, false);
            $text('TOTAL DE LA OPERACION:', $colX[0] + 2, $stY - 11, 8, true);
            $numberCell($fmt((float) $tot['total_neto']), $colX[6], $stY - 11, $colW[6], 9, true);
            $stY -= 18;

            // Total en letras
            $rect($mg, $stY - 18, $tW, 18, true, self::HUESO);
            $letras = 'TOTAL A PAGAR EN LETRAS: ' . $this->numberToWords((int) $tot['total_neto']) . ' GUARANÍES';
            $text(mb_strimwidth($letras, 0, 95, '...'), $colX[0] + 2, $stY - 11, 7.5, true);
            $stY -= 18;

            // Liquidación IVA
            $rect($mg, $stY - 18, $tW, 18, false);
            $ivaStr = 'LIQUIDACIÓN DEL IVA:   (5%) ' . $fmt((float) $tot['iva_5'])
                    . '   (10%) ' . $fmt((float) $tot['iva_10'])
                    . '         TOTAL IVA: ' . $fmt((float) $tot['total_iva']);
            $fitTextCell($ivaStr, $colX[0] + 2, $stY - 11, $tW - 4, 8, true);
            $contentBottom = $stY - 18;   // pie de la fila de liquidación de IVA
        }

        // ---- QR + CDC: la zona se ancla JUSTO DEBAJO del último contenido de la página
        //      (totales en la última, último ítem en las de continuación), en vez de quedar
        //      pegada al pie dejando un hueco vacío. Se repite en TODAS las páginas (el manual
        //      exige el QR "al menos en la primera"). El max() impide que en páginas llenas la
        //      zona baje más allá de la posición segura al pie. ----
        $qrZoneH  = 90;                            // alto de la zona QR/CDC
        $qrGap    = 10;                            // separación bajo el último contenido
        $qrAnchor = $mg + 14 + $qrZoneH;           // piso seguro (igual geometría que la capacidad de filas)
        $qrTop    = max($contentBottom - $qrGap, $qrAnchor);
        $cdcY  = $qrTop - $qrZoneH;
        $rect($mg, $cdcY, $W - 2 * $mg, 90, false);

        // Texto de consulta
        $qrX = $mg + 90;
        $text('Consulte la validez de esta Factura Electrónica con el número de CDC impreso abajo en:', $qrX, $qrTop - 14, 7.5);
        $text('https://ekuatia.set.gov.py/consultas', $qrX, $qrTop - 26, 7.5);

        // CDC formateado en grupos de 4
        $cdcGrouped = implode(' ', str_split($cdc, 4));
        $rect($qrX, $qrTop - 68, $W - $mg - $qrX - 4, 26, true, self::HUESO);
        $stream .= sprintf("BT /F2 9 Tf %.2f %.2f Td (%s) Tj ET\n",
            $qrX + 4, $qrTop - 58, $this->pdfStr($cdcGrouped)
        );

        $text('ESTE DOCUMENTO ES UNA REPRESENTACIÓN GRÁFICA DE UN DOCUMENTO ELECTRÓNICO (XML)', $qrX, $qrTop - 76, 7);
        $text('Si su documento electrónico presenta algún error puede solicitar la modificación dentro de las 72 horas.', $qrX, $qrTop - 86, 7);

        // ---- QR real (dCarQR) dibujado como vectores ----
        $qrText = (string) ($data['qr_text'] ?? '');
        if ($qrText !== '') {
            $matrix = QrCode::matrix($qrText, 'L');   // nivel L = QR más chico/escaneable
            $n = count($matrix);
            $qrSize = 82.0;                            // tamaño del QR en puntos
            $mod = $qrSize / $n;                       // tamaño de cada módulo
            $qrOx = $mg + 5;                           // origen X
            $qrOy = $cdcY + 4;                         // origen Y (borde inferior)

            // Un solo bloque de relleno negro con todos los módulos oscuros.
            $stream .= "q 0 0 0 rg\n";
            for ($r = 0; $r < $n; $r++) {
                for ($c = 0; $c < $n; $c++) {
                    if ($matrix[$r][$c]) {
                        $x = $qrOx + $c * $mod;
                        // fila 0 = arriba; en PDF la Y crece hacia arriba.
                        $yy = $qrOy + $qrSize - ($r + 1) * $mod;
                        // +0.25 de solape para evitar líneas finas entre módulos al renderizar.
                        $stream .= sprintf("%.3f %.3f %.3f %.3f re\n", $x, $yy, $mod + 0.25, $mod + 0.25);
                    }
                }
            }
            $stream .= "f\nQ\n";
        } else {
            // Sin QR (no debería pasar): recuadro vacío de respaldo.
            $rect($mg + 4, $cdcY + 4, 82, 82, false);
        }

        // ---- FOOTER ----
        $fyY = $cdcY - 12;

        // (Marca de agua "PRUEBA" removida a pedido — el KuDE sale limpio.)

        return $stream;
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     * Ensambla el PDF final con N páginas: un objeto Page + un content stream por cada página recibida.
     */
    private function assemblePdf(array $streams, string $resources, float $W, float $H): string
    {
        $objs = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $kids = [];
        foreach ($streams as $k => $stream) {
            $pageObj = 3 + ($k * 2);   // objetos de página: 3, 5, 7, ...
            $contObj = $pageObj + 1;   // su contenido:      4, 6, 8, ...
            $kids[]  = $pageObj . ' 0 R';
            $objs[$pageObj] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << %s >> /Contents %d 0 R >>",
                $W, $H, $resources, $contObj);
            $objs[$contObj] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }
        $objs[2] = '<< /Type /Pages /Count ' . count($streams) . ' /Kids [' . implode(' ', $kids) . '] >>';
        ksort($objs);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "$num 0 obj\n$body\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $count   = count($objs) + 1;
        $pdf .= "xref\n0 $count\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\n";
        $pdf .= "startxref\n$xrefPos\n%%EOF";

        return $pdf;
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function pdfStr(string $s): string
    {
        // Transcodificar UTF-8 → Latin-1 para Helvetica estándar PDF
        $s = mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function numberToWords(int $n): string
    {
        $n = abs($n);
        if ($n === 0) {
            return 'CERO';
        }

        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
                     'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS',
                     'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $decenas  = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
                     'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $group = function (int $g) use ($unidades, $decenas, $centenas): string {
            $out = '';
            $c   = intdiv($g, 100);
            $r   = $g % 100;
            if ($c > 0) {
                $out .= ($c === 1 && $r === 0) ? 'CIEN ' : $centenas[$c] . ' ';
            }
            if ($r > 0) {
                if ($r < 20) {
                    $out .= $unidades[$r] . ' ';
                } else {
                    $d = intdiv($r, 10);
                    $u = $r % 10;
                    $out .= $decenas[$d];
                    if ($u > 0) {
                        $out .= ' Y ' . $unidades[$u];
                    }
                    $out .= ' ';
                }
            }
            return $out;
        };

        $scaleName = function (int $idx, int $value) use ($group): string {
            return match ($idx) {
                0 => $group($value),
                1 => $value === 1 ? 'MIL' : $group($value) . 'MIL',
                2 => $value === 1 ? 'UN MILLÓN' : $group($value) . 'MILLONES',
                3 => $value === 1 ? 'MIL MILLONES' : $group($value) . 'MIL MILLONES',
                4 => $value === 1 ? 'UN BILLÓN' : $group($value) . 'BILLONES',
                5 => $value === 1 ? 'MIL BILLONES' : $group($value) . 'MIL BILLONES',
                default => $group($value) . 'TRILLONES',
            };
        };

        $groups = [];
        while ($n > 0) {
            $groups[] = $n % 1000;
            $n = intdiv($n, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if ($groups[$i] > 0) {
                $parts[] = $scaleName($i, $groups[$i]);
            }
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
    }
}
