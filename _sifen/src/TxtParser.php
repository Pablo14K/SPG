<?php
declare(strict_types=1);

namespace Automatizador;

use RuntimeException;

/**
 * Lee un archivo .txt y devuelve un arreglo de facturas "crudas" (datos mínimos).
 *
 * FORMATO (ver docs/FORMATO_TXT.md):
 *   - Líneas que empiezan con #  → comentario (se ignoran)
 *   - Línea  ===  → separa una factura de la siguiente
 *   - Campos separados por |  (pipe)
 *
 *   FAC|estab|punto|numero|fecha|condicion|moneda
 *   CLI|tipo|documento|nombre|email|direccion|telefono
 *   ITM|codigo|descripcion|cantidad|precio_unitario|iva       (repetible)
 *   PAG|tipo                                                  (repetible; el monto lo calcula el sistema)
 *
 *   condicion: 1=contado, 2=crédito
 *   iva: 10, 5 ó 0 (0 = exenta)
 *   cliente tipo: RUC (documento = "80012345-6")  ó  CI (documento = nº de cédula / "CF")
 *   pago tipo: 1=efectivo 2=cheque 3=tarj. crédito 4=tarj. débito 5=transferencia
 */
final class TxtParser
{
    /** @return array<int,array> lista de facturas crudas */
    public function parse(string $contenido): array
    {
        $facturas = [];
        $actual = $this->nuevaFactura();
        $tieneDatos = false;
        $nLinea = 0;

        foreach (preg_split('/\r\n|\r|\n/', $contenido) as $linea) {
            $nLinea++;
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            if ($linea === '===') {
                if ($tieneDatos) {
                    $facturas[] = $actual;
                }
                $actual = $this->nuevaFactura();
                $tieneDatos = false;
                continue;
            }

            $campos = array_map('trim', explode('|', $linea));
            $tipo = strtoupper((string)($campos[0] ?? ''));

            switch ($tipo) {
                // EMI: quien emite. Lo manda el SPG desde la 7.52.0.
                //
                // **Es opcional y esa es la gracia**: sin esta linea se usan
                // los datos de config/.env, asi que un .txt viejo o de otro
                // sistema sigue funcionando igual. Cuando viene, gana, porque
                // el emisor cambia con la sucursal —la direccion y el timbrado
                // son los del local que atendio— y eso el .env no lo puede
                // expresar con un solo juego de valores.
                case 'EMI':
                    $actual['emi'] = [
                        'razon_social'   => $campos[1] ?? '',
                        'ruc'            => $campos[2] ?? '',
                        'dv'             => $campos[3] ?? '',
                        'direccion'      => $campos[4] ?? '',
                        'ciudad'         => $campos[5] ?? '',
                        'telefono'       => $campos[6] ?? '',
                        'email'          => $campos[7] ?? '',
                        'actividad_cod'  => $campos[8] ?? '',
                        'actividad_desc' => $campos[9] ?? '',
                        'timbrado'       => $campos[10] ?? '',
                        'timbrado_ini'   => $campos[11] ?? '',
                        'timbrado_fin'   => $campos[12] ?? '',
                        'sucursal'       => $campos[13] ?? '',
                    ];
                    $tieneDatos = true;
                    break;

                case 'FAC':
                    $actual['fac'] = [
                        'establecimiento' => $campos[1] ?? '',
                        'punto'           => $campos[2] ?? '',
                        'numero'          => $campos[3] ?? '',
                        'fecha'           => $campos[4] ?? '',
                        'condicion'       => $campos[5] ?? '1',
                        'moneda'          => strtoupper($campos[6] ?? 'PYG'),
                        // D011 iTipTra. Ausente = 1 (venta de mercaderia),
                        // que es como se comportaba antes de que el SPG lo
                        // mandara: un .txt viejo no cambia de significado.
                        'tipo_transaccion' => $campos[7] ?? '',
                    ];
                    $tieneDatos = true;
                    break;

                case 'CLI':
                    $actual['cli'] = [
                        'tipo'      => strtoupper($campos[1] ?? 'CI'),
                        'documento' => $campos[2] ?? '',
                        'nombre'    => $campos[3] ?? '',
                        'email'     => $campos[4] ?? '',
                        'direccion' => $campos[5] ?? '',
                        'telefono'  => $campos[6] ?? '',
                    ];
                    $tieneDatos = true;
                    break;

                case 'ITM':
                    $actual['items'][] = [
                        'codigo'          => $campos[1] ?? '',
                        'descripcion'     => $campos[2] ?? '',
                        'cantidad'        => $campos[3] ?? '0',
                        'precio_unitario' => $campos[4] ?? '0',
                        'iva'             => $campos[5] ?? '10',
                    ];
                    $tieneDatos = true;
                    break;

                case 'PAG':
                    // El MONTO ya NO se lee del .txt: lo calcula el sistema desde los
                    // ítems (evita errores humanos). Solo se toma el medio de pago.
                    $actual['pagos'][] = [
                        'tipo' => $campos[1] ?? '1',
                    ];
                    $tieneDatos = true;
                    break;

                default:
                    throw new RuntimeException("Línea $nLinea: tipo de registro desconocido '$tipo' (esperado EMI/FAC/CLI/ITM/PAG/===).");
            }
        }

        if ($tieneDatos) {
            $facturas[] = $actual;
        }

        if ($facturas === []) {
            throw new RuntimeException('El archivo no contiene ninguna factura.');
        }

        foreach ($facturas as $i => $f) {
            $this->validar($f, $i + 1);
        }

        return $facturas;
    }

    private function nuevaFactura(): array
    {
        return ['fac' => null, 'cli' => null, 'items' => [], 'pagos' => []];
    }

    private function validar(array $f, int $n): void
    {
        if ($f['fac'] === null) {
            throw new RuntimeException("Factura #$n: falta la línea FAC.");
        }
        if ($f['cli'] === null) {
            throw new RuntimeException("Factura #$n: falta la línea CLI.");
        }
        if ($f['items'] === []) {
            throw new RuntimeException("Factura #$n: no tiene ítems (ITM).");
        }
        foreach (['establecimiento', 'punto', 'numero', 'fecha'] as $req) {
            if (($f['fac'][$req] ?? '') === '') {
                throw new RuntimeException("Factura #$n: falta '$req' en la línea FAC.");
            }
        }
        if (($f['cli']['nombre'] ?? '') === '') {
            throw new RuntimeException("Factura #$n: el cliente no tiene nombre (CLI).");
        }
    }
}
