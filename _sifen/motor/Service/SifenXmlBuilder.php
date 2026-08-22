<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Constructor XML PHP. Arma rDE/DE con los grupos SIFEN v150 en el ORDEN y
 *           con la OBLIGATORIEDAD exactos del XSD oficial (DE_v150.xsd / siRecepDE_v150.xsd).
 *           Validado contra el XSD oficial con lxml (ver storage/tmp/sifen_info).
 * Donde se usa: lo invoca SifenEmitter para generar el XML sin firmar (la firma y el QR se
 *           insertan despues). Estructura: rDE > DE > gOpeDE, gTimb, gDatGralOpe(gOpeCom,
 *           gEmis, gDatRec), gDtipDE(gCamFE, gCamCond, gCamItem+), gTotSub.
 * Nota: Manual SIFEN v150 cap. 7.2.4 — sin whitespace entre etiquetas, sin prefijos de
 *       namespace, campos opcionales con valor cero NO se emiten (salvo obligatorios).
 */


namespace App\Service;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Comentario de codigo: clase SifenXmlBuilder. Agrupa la responsabilidad principal indicada en la cabecera del archivo.
 */
final class SifenXmlBuilder
{
    public function build(array $config, array $payload, string $cdc, string $dCodSeg, ?string $qrText = null): string
    {
        $ns  = (string) ($config['namespace'] ?? 'http://ekuatia.set.gov.py/sifen/xsd');
        $doc = $payload['documento'];
        $em  = $payload['emisor'];
        $cli = $payload['cliente'];
        $tot = $payload['totales'];

        $dom = new DOMDocument('1.0', 'UTF-8');
        // Manual SIFEN v150 cap. 7.2.4 — Prohibido whitespace entre etiquetas (rechazo 0105).
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        $rDE = $dom->createElementNS($ns, 'rDE');
        $dom->appendChild($rDE);
        $this->t($dom, $rDE, 'dVerFor', '150', $ns);

        $de = $dom->createElementNS($ns, 'DE');
        $de->setAttribute('Id', $cdc);
        $rDE->appendChild($de);

        $this->t($dom, $de, 'dDVId', substr($cdc, -1), $ns);
        $this->t($dom, $de, 'dFecFirma', $this->fmtDateTime($doc['fecha_firma'] ?? $doc['fecha_emision']), $ns);
        $this->t($dom, $de, 'dSisFact', '1', $ns);

        // ---- gOpeDE ----
        $gOpeDE = $this->g($dom, $de, 'gOpeDE', $ns);
        $this->t($dom, $gOpeDE, 'iTipEmi', (string) $doc['tipo_emision'], $ns);
        $this->t($dom, $gOpeDE, 'dDesTipEmi', (string) $doc['descripcion_tipo_emision'], $ns);
        $this->t($dom, $gOpeDE, 'dCodSeg', $dCodSeg, $ns);

        // ---- gTimb ----  (iTiDE: 1 ó 4..7, SIN cero a la izquierda)
        $gTimb = $this->g($dom, $de, 'gTimb', $ns);
        $this->t($dom, $gTimb, 'iTiDE', (string) (int) $doc['tipo_documento'], $ns);
        $this->t($dom, $gTimb, 'dDesTiDE', (string) $doc['descripcion_tipo_documento'], $ns);
        $this->t($dom, $gTimb, 'dNumTim', (string) $em['timbrado_numero'], $ns);
        $this->t($dom, $gTimb, 'dEst', str_pad((string) $doc['establecimiento'], 3, '0', STR_PAD_LEFT), $ns);
        $this->t($dom, $gTimb, 'dPunExp', str_pad((string) $doc['punto'], 3, '0', STR_PAD_LEFT), $ns);
        $this->t($dom, $gTimb, 'dNumDoc', str_pad((string) $doc['numero'], 7, '0', STR_PAD_LEFT), $ns);
        if (($doc['serie'] ?? '') !== '') {
            $this->t($dom, $gTimb, 'dSerieNum', (string) $doc['serie'], $ns);
        }
        $this->t($dom, $gTimb, 'dFeIniT', (string) $em['timbrado_inicio'], $ns);

        // ---- gDatGralOpe ----  (orden XSD: dFeEmiDE, gOpeCom, gEmis, gDatRec)
        $gDatGralOpe = $this->g($dom, $de, 'gDatGralOpe', $ns);
        $this->t($dom, $gDatGralOpe, 'dFeEmiDE', $this->fmtDateTime($doc['fecha_emision']), $ns);

        // gOpeCom (datos comerciales) — obligatorio para FE
        $gOpeCom = $this->g($dom, $gDatGralOpe, 'gOpeCom', $ns);
        $this->t($dom, $gOpeCom, 'iTipTra', (string) $doc['tipo_transaccion'], $ns);
        $this->t($dom, $gOpeCom, 'dDesTipTra', (string) $doc['descripcion_tipo_transaccion'], $ns);
        $this->t($dom, $gOpeCom, 'iTImp', (string) $doc['tipo_impuesto'], $ns);
        $this->t($dom, $gOpeCom, 'dDesTImp', (string) $doc['descripcion_tipo_impuesto'], $ns);
        $this->t($dom, $gOpeCom, 'cMoneOpe', (string) $doc['moneda'], $ns);
        $this->t($dom, $gOpeCom, 'dDesMoneOpe', (string) $doc['descripcion_moneda'], $ns);
        if (($doc['moneda'] ?? 'PYG') !== 'PYG') {
            $this->t($dom, $gOpeCom, 'dCondTiCam', '1', $ns);
            $this->t($dom, $gOpeCom, 'dTiCam', $this->num((float) ($doc['tipo_cambio'] ?? 1)), $ns);
        }

        // gEmis
        $gEmis = $this->g($dom, $gDatGralOpe, 'gEmis', $ns);
        $this->t($dom, $gEmis, 'dRucEm', (string) $em['ruc'], $ns);
        $this->t($dom, $gEmis, 'dDVEmi', (string) $em['dv'], $ns);
        $this->t($dom, $gEmis, 'iTipCont', (string) $em['tipo_contribuyente'], $ns);
        $this->t($dom, $gEmis, 'dNomEmi', (string) $em['razon_social'], $ns);
        if (($em['nombre_fantasia'] ?? '') !== '') {
            $this->t($dom, $gEmis, 'dNomFanEmi', (string) $em['nombre_fantasia'], $ns);
        }
        $this->t($dom, $gEmis, 'dDirEmi', (string) $em['direccion'], $ns);
        $this->t($dom, $gEmis, 'dNumCas', (string) $em['numero_casa'], $ns);
        $this->t($dom, $gEmis, 'cDepEmi', (string) $em['codigo_departamento'], $ns);
        $this->t($dom, $gEmis, 'dDesDepEmi', (string) $em['descripcion_departamento'], $ns);
        if (($em['codigo_distrito'] ?? '') !== '') {
            $this->t($dom, $gEmis, 'cDisEmi', (string) $em['codigo_distrito'], $ns);
            $this->t($dom, $gEmis, 'dDesDisEmi', (string) $em['descripcion_distrito'], $ns);
        }
        $this->t($dom, $gEmis, 'cCiuEmi', (string) $em['codigo_ciudad'], $ns);
        $this->t($dom, $gEmis, 'dDesCiuEmi', (string) $em['descripcion_ciudad'], $ns);
        $this->t($dom, $gEmis, 'dTelEmi', (string) $em['telefono'], $ns);
        $this->t($dom, $gEmis, 'dEmailE', (string) $em['email'], $ns);
        $gActEco = $this->g($dom, $gEmis, 'gActEco', $ns);
        $this->t($dom, $gActEco, 'cActEco', (string) $em['codigo_actividad_economica'], $ns);
        $this->t($dom, $gActEco, 'dDesActEco', (string) $em['descripcion_actividad_economica'], $ns);

        // gDatRec  (iNatRec, iTiOpe, cPaisRec, dDesPaisRe van AQUI, no en gDatGralOpe)
        $gDatRec = $this->g($dom, $gDatGralOpe, 'gDatRec', $ns);
        $tieneRuc = (($cli['ruc'] ?? '') !== '');
        $iNatRec  = (string) ($cli['naturaleza_receptor'] ?? ($tieneRuc ? '1' : '2'));
        $this->t($dom, $gDatRec, 'iNatRec', $iNatRec, $ns);
        $this->t($dom, $gDatRec, 'iTiOpe', (string) ($cli['tipo_operacion'] ?? '1'), $ns);
        $this->t($dom, $gDatRec, 'cPaisRec', (string) ($cli['codigo_pais'] ?? 'PRY'), $ns);
        $this->t($dom, $gDatRec, 'dDesPaisRe', (string) ($cli['descripcion_pais'] ?? 'Paraguay'), $ns);
        if ($tieneRuc) {
            if (($cli['tipo_contribuyente'] ?? null) !== null) {
                $this->t($dom, $gDatRec, 'iTiContRec', (string) $cli['tipo_contribuyente'], $ns);
            }
            $this->t($dom, $gDatRec, 'dRucRec', (string) $cli['ruc'], $ns);
            $this->t($dom, $gDatRec, 'dDVRec', (string) $cli['dv'], $ns);
        } else {
            $this->t($dom, $gDatRec, 'iTipIDRec', (string) ($cli['tipo_documento'] ?? 1), $ns);
            $this->t($dom, $gDatRec, 'dDTipIDRec', (string) ($cli['descripcion_tipo_documento'] ?? 'Cédula paraguaya'), $ns);
            $this->t($dom, $gDatRec, 'dNumIDRec', (string) ($cli['numero_documento'] ?? '0'), $ns);
        }
        $this->t($dom, $gDatRec, 'dNomRec', (string) $cli['nombre'], $ns);
        if (($cli['direccion'] ?? '') !== '') {
            $this->t($dom, $gDatRec, 'dDirRec', (string) $cli['direccion'], $ns);
            if (($cli['numero_casa'] ?? '') !== '') {
                $this->t($dom, $gDatRec, 'dNumCasRec', (string) $cli['numero_casa'], $ns);
            }
            if (($cli['codigo_departamento'] ?? '') !== '') {
                $this->t($dom, $gDatRec, 'cDepRec', (string) $cli['codigo_departamento'], $ns);
                $this->t($dom, $gDatRec, 'dDesDepRec', (string) $cli['descripcion_departamento'], $ns);
            }
            if (($cli['codigo_ciudad'] ?? '') !== '') {
                $this->t($dom, $gDatRec, 'cCiuRec', (string) $cli['codigo_ciudad'], $ns);
                $this->t($dom, $gDatRec, 'dDesCiuRec', (string) $cli['descripcion_ciudad'], $ns);
            }
        }
        if (($cli['telefono'] ?? '') !== '') {
            $this->t($dom, $gDatRec, 'dTelRec', (string) $cli['telefono'], $ns);
        }
        if (($cli['email'] ?? '') !== '') {
            $this->t($dom, $gDatRec, 'dEmailRec', (string) $cli['email'], $ns);
        }
        if (($cli['codigo_cliente'] ?? '') !== '') {
            $this->t($dom, $gDatRec, 'dCodCliente', (string) $cli['codigo_cliente'], $ns);
        }

        // ---- gDtipDE  (wrapper obligatorio: gCamFE, gCamCond, gCamItem+) ----
        $gDtipDE = $this->g($dom, $de, 'gDtipDE', $ns);

        // gCamFE: indicador de presencia (obligatorio para Factura Electrónica)
        $gCamFE = $this->g($dom, $gDtipDE, 'gCamFE', $ns);
        $this->t($dom, $gCamFE, 'iIndPres', (string) ($doc['indicador_presencia'] ?? '1'), $ns);
        $this->t($dom, $gCamFE, 'dDesIndPres', (string) ($doc['descripcion_indicador_presencia'] ?? 'Operación presencial'), $ns);

        // gCamCond: condición de la operación (contado = gPaConEIni / crédito = gPagCred)
        $gCamCond = $this->g($dom, $gDtipDE, 'gCamCond', $ns);
        $condOpe  = (int) $doc['condicion_operacion'];
        $this->t($dom, $gCamCond, 'iCondOpe', (string) $condOpe, $ns);
        $this->t($dom, $gCamCond, 'dDCondOpe', (string) $doc['descripcion_condicion_operacion'], $ns);
        if ($condOpe === 1) {
            foreach ($payload['pagos'] as $pago) {
                $gPa = $this->g($dom, $gCamCond, 'gPaConEIni', $ns);
                $this->t($dom, $gPa, 'iTiPago', (string) $pago['tipo_pago'], $ns);
                $this->t($dom, $gPa, 'dDesTiPag', (string) $pago['descripcion_tipo_pago'], $ns);
                $this->t($dom, $gPa, 'dMonTiPag', $this->num((float) $pago['monto']), $ns);
                $this->t($dom, $gPa, 'cMoneTiPag', (string) ($pago['moneda'] ?? $doc['moneda'] ?? 'PYG'), $ns);
                $this->t($dom, $gPa, 'dDMoneTiPag', (string) ($pago['descripcion_moneda'] ?? $doc['descripcion_moneda'] ?? 'Guaraní'), $ns);
            }
        } else {
            $gCred = $this->g($dom, $gCamCond, 'gPagCred', $ns);
            $this->t($dom, $gCred, 'iCondCred', (string) ($doc['condicion_credito'] ?? '1'), $ns);
            $this->t($dom, $gCred, 'dDCondCred', (string) ($doc['descripcion_condicion_credito'] ?? 'Plazo'), $ns);
            if ((int) ($doc['condicion_credito'] ?? 1) === 1) {
                $this->t($dom, $gCred, 'dPlazoCre', (string) ($doc['plazo_credito'] ?? '30 días'), $ns);
            } else {
                $this->t($dom, $gCred, 'dCuotas', (string) ($doc['cuotas'] ?? '1'), $ns);
            }
        }

        // gCamItem (1..999)
        foreach ($payload['items'] as $item) {
            $gItem = $this->g($dom, $gDtipDE, 'gCamItem', $ns);
            $this->t($dom, $gItem, 'dCodInt', (string) $item['codigo'], $ns);
            $this->t($dom, $gItem, 'dDesProSer', (string) $item['descripcion'], $ns);
            $this->t($dom, $gItem, 'cUniMed', (string) $item['unidad_codigo'], $ns);
            $this->t($dom, $gItem, 'dDesUniMed', (string) $item['unidad_descripcion'], $ns);
            $this->t($dom, $gItem, 'dCantProSer', $this->num((float) $item['cantidad']), $ns);

            // gValorItem: dPUniProSer, dTotBruOpeItem, gValorRestaItem(... dTotOpeItem)
            $bruto    = (float) $item['ea008'];
            $descItem = (float) ($item['descuento_item'] ?? 0);
            $descGlo  = (float) ($item['descuento_global_item'] ?? 0);
            $totOpeIt = max(0.0, $bruto - $descItem - $descGlo);

            $gVal = $this->g($dom, $gItem, 'gValorItem', $ns);
            $this->t($dom, $gVal, 'dPUniProSer', $this->num((float) $item['precio_unitario']), $ns);
            $this->t($dom, $gVal, 'dTotBruOpeItem', $this->num($bruto), $ns);
            $gRes = $this->g($dom, $gVal, 'gValorRestaItem', $ns);
            if ($descItem > 0) {
                $this->t($dom, $gRes, 'dDescItem', $this->num($descItem), $ns);
            }
            if ($descGlo > 0) {
                $this->t($dom, $gRes, 'dDescGloItem', $this->num($descGlo), $ns);
            }
            $this->t($dom, $gRes, 'dTotOpeItem', $this->num($totOpeIt), $ns);

            // gCamIVA
            $gIVA = $this->g($dom, $gItem, 'gCamIVA', $ns);
            $this->t($dom, $gIVA, 'iAfecIVA', (string) $item['afectacion_iva'], $ns);
            $this->t($dom, $gIVA, 'dDesAfecIVA', (string) $item['descripcion_afectacion_iva'], $ns);
            $this->t($dom, $gIVA, 'dPropIVA', $this->num((float) $item['proporcion_iva']), $ns);
            $this->t($dom, $gIVA, 'dTasaIVA', $this->num((float) $item['tasa_iva']), $ns);
            $this->t($dom, $gIVA, 'dBasGravIVA', $this->num((float) $item['base_gravada_iva']), $ns);
            $this->t($dom, $gIVA, 'dLiqIVAItem', $this->num((float) $item['liquidacion_iva']), $ns);
        }

        // ---- gTotSub  (orden XSD; obligatorios siempre, opcionales solo si > 0) ----
        $gTot = $this->g($dom, $de, 'gTotSub', $ns);
        $this->optNum($dom, $gTot, 'dSubExe', (float) ($tot['subtotal_exenta'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dSubExo', (float) ($tot['subtotal_exonerada'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dSub5',  (float) ($tot['subtotal_5'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dSub10', (float) ($tot['subtotal_10'] ?? 0), $ns);
        $this->t($dom, $gTot, 'dTotOpe', $this->num((float) ($tot['total_bruto'] ?? $tot['total_neto'])), $ns);
        $this->t($dom, $gTot, 'dTotDesc', $this->num((float) ($tot['total_descuento'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dTotDescGlotem', $this->num((float) ($tot['total_descuento_global'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dTotAntItem', $this->num((float) ($tot['total_anticipo_item'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dTotAnt', $this->num((float) ($tot['total_anticipo'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dPorcDescTotal', $this->num((float) ($tot['porcentaje_descuento_total'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dDescTotal', $this->num((float) ($tot['descuento_total'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dAnticipo', $this->num((float) ($tot['total_anticipo'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dRedon', $this->num((float) ($tot['redondeo'] ?? 0)), $ns);
        $this->t($dom, $gTot, 'dTotGralOpe', $this->num((float) $tot['total_neto']), $ns);
        $this->optNum($dom, $gTot, 'dIVA5',  (float) ($tot['iva_5'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dIVA10', (float) ($tot['iva_10'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dTotIVA', (float) ($tot['total_iva'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dBaseGrav5',  (float) ($tot['base_gravada_5'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dBaseGrav10', (float) ($tot['base_gravada_10'] ?? 0), $ns);
        $this->optNum($dom, $gTot, 'dTBasGraIVA', (float) ($tot['total_base_gravada'] ?? 0), $ns);

        // gCamFuFD (QR) va como hermano de DE, DESPUÉS de la firma (Manual v150 cap. 7.6).
        // El flujo normal lo inserta como string después de </Signature> (SifenEmitter::insertarQr);
        // aquí solo se agrega si ya viene el texto (p. ej. para pruebas de validación).
        if ($qrText !== null) {
            $gCamFuFD = $this->g($dom, $rDE, 'gCamFuFD', $ns);
            $this->t($dom, $gCamFuFD, 'dCarQR', $qrText, $ns);
        }

        // Manual SIFEN v150 cap. 7.2.4 — compactar (sin whitespace entre etiquetas, rechazo 0105).
        $xml = (string) $dom->saveXML();
        $xml = (string) preg_replace('/>\s+</', '><', $xml);

        return trim($xml);
    }

    /**
     * Comentario de codigo: crea un grupo (elemento contenedor) y lo cuelga del padre.
     */
    private function g(DOMDocument $dom, DOMElement $parent, string $name, string $ns): DOMElement
    {
        $node = $dom->createElementNS($ns, $name);
        $parent->appendChild($node);
        return $node;
    }

    /**
     * Comentario de codigo: crea un elemento de texto con valor y lo cuelga del padre.
     */
    private function t(DOMDocument $dom, DOMElement $parent, string $name, string $value, string $ns): void
    {
        $node = $dom->createElementNS($ns, $name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
    }

    /**
     * Comentario de codigo: emite un campo numerico OPCIONAL solo si es > 0
     * (Manual v150 cap. 7.2.4: no emitir opcionales con valor cero).
     */
    private function optNum(DOMDocument $dom, DOMElement $parent, string $name, float $value, string $ns): void
    {
        if ($value > 0) {
            $this->t($dom, $parent, $name, $this->num($value), $ns);
        }
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function fmtDateTime(string $value): string
    {
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d\TH:i:s');
        } catch (\Throwable) {
            throw new RuntimeException('Fecha inválida para XML SIFEN: ' . $value);
        }
    }

    /**
     * Comentario de codigo: Metodo del flujo de negocio; leer parametros y retornos para ver que datos transforma.
     */
    private function num(float $value): string
    {
        $formatted = number_format($value, 8, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
