<?php

declare(strict_types=1);
/*
 * DOCUMENTACION DEL ARCHIVO
 * Que hace: Codificador QR en PHP puro (modo byte). Devuelve la matriz de módulos (true=negro) para dibujar el QR
 *           del KuDE. Implementa selección de versión, Reed-Solomon, enmascarado y datos de formato/versión.
 * Donde se usa: lo usa KudeService para dibujar el QR real (dCarQR) como vectores en el PDF. Sin dependencias externas.
 * Nota: estándar ISO/IEC 18004 (QR). Verificado contra la librería `qrcode` de Python (matrices idénticas).
 */

namespace App\Service;

use RuntimeException;

final class QrCode
{
    /** GF(256) con polinomio primitivo 0x11d. */
    private static array $exp = [];
    private static array $log = [];

    /**
     * Tabla de corrección de errores (ISO 18004, Tabla 9), versiones 1..20.
     * [version][nivel] = [ecPorBloque, bloquesG1, datosG1, bloquesG2, datosG2]
     * nivel: 'L','M','Q','H'. Cubre URLs de SIFEN (que caen ~v13 nivel L).
     */
    private const ECC = [
        1  => ['L'=>[7,1,19,0,0],   'M'=>[10,1,16,0,0],  'Q'=>[13,1,13,0,0],  'H'=>[17,1,9,0,0]],
        2  => ['L'=>[10,1,34,0,0],  'M'=>[16,1,28,0,0],  'Q'=>[22,1,22,0,0],  'H'=>[28,1,16,0,0]],
        3  => ['L'=>[15,1,55,0,0],  'M'=>[26,1,44,0,0],  'Q'=>[18,2,17,0,0],  'H'=>[22,2,13,0,0]],
        4  => ['L'=>[20,1,80,0,0],  'M'=>[18,2,32,0,0],  'Q'=>[26,2,24,0,0],  'H'=>[16,4,9,0,0]],
        5  => ['L'=>[26,1,108,0,0], 'M'=>[24,2,43,0,0],  'Q'=>[18,2,15,2,16], 'H'=>[22,2,11,2,12]],
        6  => ['L'=>[18,2,68,0,0],  'M'=>[16,4,27,0,0],  'Q'=>[24,4,19,0,0],  'H'=>[28,4,15,0,0]],
        7  => ['L'=>[20,2,78,0,0],  'M'=>[18,4,31,0,0],  'Q'=>[18,2,14,4,15], 'H'=>[26,4,13,1,14]],
        8  => ['L'=>[24,2,97,0,0],  'M'=>[22,2,38,2,39], 'Q'=>[22,4,18,2,19], 'H'=>[26,4,14,2,15]],
        9  => ['L'=>[30,2,116,0,0], 'M'=>[22,3,36,2,37], 'Q'=>[20,4,16,4,17], 'H'=>[24,4,12,4,13]],
        10 => ['L'=>[18,2,68,2,69], 'M'=>[26,4,43,1,44], 'Q'=>[24,6,19,2,20], 'H'=>[28,6,15,2,16]],
        11 => ['L'=>[20,4,81,0,0],  'M'=>[30,1,50,4,51], 'Q'=>[28,4,22,4,23], 'H'=>[24,3,12,8,13]],
        12 => ['L'=>[24,2,92,2,93], 'M'=>[22,6,36,2,37], 'Q'=>[26,4,20,6,21], 'H'=>[28,7,14,4,15]],
        13 => ['L'=>[26,4,107,0,0], 'M'=>[22,8,37,1,38], 'Q'=>[24,8,20,4,21], 'H'=>[22,12,11,4,12]],
        14 => ['L'=>[30,3,115,1,116],'M'=>[24,4,40,5,41],'Q'=>[20,11,16,5,17],'H'=>[24,11,12,5,13]],
        15 => ['L'=>[22,5,87,1,88], 'M'=>[24,5,41,5,42], 'Q'=>[30,5,24,7,25], 'H'=>[24,11,12,7,13]],
        16 => ['L'=>[24,5,98,1,99], 'M'=>[28,7,45,3,46], 'Q'=>[24,15,19,2,20], 'H'=>[30,3,15,13,16]],
        17 => ['L'=>[28,1,107,5,108],'M'=>[28,10,46,1,47],'Q'=>[28,1,22,15,23],'H'=>[28,2,14,17,15]],
        18 => ['L'=>[30,5,120,1,121],'M'=>[26,9,43,4,44],'Q'=>[28,17,22,1,23],'H'=>[28,2,14,19,15]],
        19 => ['L'=>[28,3,113,4,114],'M'=>[26,3,44,11,45],'Q'=>[26,17,21,4,22],'H'=>[26,9,13,16,14]],
        20 => ['L'=>[28,3,107,5,108],'M'=>[26,3,41,13,42],'Q'=>[30,15,24,5,25],'H'=>[28,15,15,10,16]],
    ];

    /** Posiciones (centros) de los patrones de alineación por versión. */
    private const ALIGN = [
        1=>[], 2=>[6,18], 3=>[6,22], 4=>[6,26], 5=>[6,30], 6=>[6,34],
        7=>[6,22,38], 8=>[6,24,42], 9=>[6,26,46], 10=>[6,28,50], 11=>[6,30,54],
        12=>[6,32,58], 13=>[6,34,62], 14=>[6,26,46,66], 15=>[6,26,48,70],
        16=>[6,26,50,74], 17=>[6,30,54,78], 18=>[6,30,56,82], 19=>[6,30,58,86], 20=>[6,34,62,90],
    ];

    /**
     * Genera la matriz de módulos del QR para el texto dado.
     *
     * @param string   $data      texto a codificar (la URL dCarQR).
     * @param string   $ecLevel   nivel de corrección: 'L','M','Q','H' (L = QR más chico).
     * @param int|null $version   forzar versión (para tests); null = automática.
     * @param int|null $forceMask forzar máscara 0..7 (para tests); null = mejor máscara.
     * @return array<int,array<int,bool>> matriz [fila][col], true = módulo negro.
     */
    public static function matrix(string $data, string $ecLevel = 'L', ?int $version = null, ?int $forceMask = null): array
    {
        self::initGF();
        $ecLevel = strtoupper($ecLevel);

        $version ??= self::chooseVersion($data, $ecLevel);
        [$ecPerBlock, $g1, $g1d, $g2, $g2d] = self::ECC[$version][$ecLevel];
        $totalDataCw = $g1 * $g1d + $g2 * $g2d;

        // ---- 1) Bitstream: modo byte (0100) + indicador de longitud + datos ----
        $bits = [];
        self::pushBits($bits, 0b0100, 4);
        $lenBitsCount = $version <= 9 ? 8 : 16;
        self::pushBits($bits, strlen($data), $lenBitsCount);
        foreach (str_split($data) as $ch) {
            self::pushBits($bits, ord($ch), 8);
        }
        // Terminador
        $capacityBits = $totalDataCw * 8;
        $remaining = $capacityBits - count($bits);
        if ($remaining < 0) {
            throw new RuntimeException('QR: los datos no entran en la versión elegida.');
        }
        self::pushBits($bits, 0, min(4, $remaining));
        // Relleno a byte
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        // Bytes de relleno alternados 0xEC / 0x11
        $dataCw = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($b = 0; $b < 8; $b++) {
                $byte = ($byte << 1) | $bits[$i + $b];
            }
            $dataCw[] = $byte;
        }
        $pad = [0xEC, 0x11];
        $pi = 0;
        while (count($dataCw) < $totalDataCw) {
            $dataCw[] = $pad[$pi % 2];
            $pi++;
        }

        // ---- 2) Dividir en bloques y calcular Reed-Solomon ----
        $blocks = [];
        $idx = 0;
        for ($i = 0; $i < $g1; $i++) {
            $blocks[] = array_slice($dataCw, $idx, $g1d);
            $idx += $g1d;
        }
        for ($i = 0; $i < $g2; $i++) {
            $blocks[] = array_slice($dataCw, $idx, $g2d);
            $idx += $g2d;
        }
        $ecBlocks = [];
        foreach ($blocks as $blk) {
            $ecBlocks[] = self::rsEncode($blk, $ecPerBlock);
        }

        // ---- 3) Intercalar datos y EC ----
        $final = [];
        $maxData = max($g1d, $g2d);
        for ($c = 0; $c < $maxData; $c++) {
            foreach ($blocks as $blk) {
                if ($c < count($blk)) {
                    $final[] = $blk[$c];
                }
            }
        }
        for ($c = 0; $c < $ecPerBlock; $c++) {
            foreach ($ecBlocks as $blk) {
                $final[] = $blk[$c];
            }
        }

        // Bits finales (con bits de remanente)
        $finalBits = [];
        foreach ($final as $cw) {
            self::pushBits($finalBits, $cw, 8);
        }
        $rem = self::remainderBits($version);
        for ($i = 0; $i < $rem; $i++) {
            $finalBits[] = 0;
        }

        // ---- 4) Construir matriz con patrones de función ----
        $size = $version * 4 + 17;
        $m = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::placeFinder($m, $reserved, 0, 0, $size);
        self::placeFinder($m, $reserved, 0, $size - 7, $size);
        self::placeFinder($m, $reserved, $size - 7, 0, $size);
        self::placeTiming($m, $reserved, $size);
        self::placeAlignment($m, $reserved, $version, $size);
        // Módulo oscuro fijo
        $m[4 * $version + 9][8] = 1;
        $reserved[4 * $version + 9][8] = true;
        self::reserveFormat($reserved, $size);
        if ($version >= 7) {
            self::reserveVersion($reserved, $size);
        }

        // ---- 5) Colocar datos en zigzag ----
        self::placeData($m, $reserved, $finalBits, $size);

        // ---- 6) Máscara ----
        $mask = $forceMask ?? self::chooseMask($m, $reserved, $size);
        self::applyMask($m, $reserved, $mask, $size);

        // ---- 7) Información de formato y versión ----
        self::placeFormat($m, $size, $ecLevel, $mask);
        if ($version >= 7) {
            self::placeVersion($m, $version, $size);
        }

        // Convertir a bool
        $out = [];
        foreach ($m as $row) {
            $out[] = array_map(static fn($v) => $v === 1, $row);
        }
        return $out;
    }

    // ===================== internos =====================

    private static function initGF(): void
    {
        if (self::$exp) return;
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11d;
        }
        for ($i = 0; $i < 255; $i++) {
            self::$log[self::$exp[$i]] = $i;
        }
    }

    private static function pushBits(array &$bits, int $value, int $len): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function chooseVersion(string $data, string $ecLevel): int
    {
        $len = strlen($data);
        for ($v = 1; $v <= 20; $v++) {
            [$ec, $g1, $g1d, $g2, $g2d] = self::ECC[$v][$ecLevel];
            $totalDataCw = $g1 * $g1d + $g2 * $g2d;
            $lenBits = $v <= 9 ? 8 : 16;
            $needBits = 4 + $lenBits + $len * 8;
            if ($needBits <= $totalDataCw * 8) {
                return $v;
            }
        }
        throw new RuntimeException('QR: texto demasiado largo para versiones soportadas (máx 20).');
    }

    private static function remainderBits(int $v): int
    {
        if ($v === 1) return 0;
        if ($v <= 6) return 7;
        if ($v <= 13) return 0;
        if ($v <= 20) return 3;
        return 0;
    }

    /** Multiplicación en GF(256). */
    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) return 0;
        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /** Polinomio generador para n codewords de EC. */
    private static function rsGenerator(int $n): array
    {
        $g = [1];
        for ($i = 0; $i < $n; $i++) {
            $next = array_fill(0, count($g) + 1, 0);
            foreach ($g as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= self::gfMul($coef, self::$exp[$i]);
            }
            $g = $next;
        }
        return $g;
    }

    /** Calcula los codewords de EC de un bloque de datos. */
    private static function rsEncode(array $data, int $ecLen): array
    {
        $gen = self::rsGenerator($ecLen);
        $res = array_fill(0, $ecLen, 0);
        foreach ($data as $d) {
            $factor = $d ^ $res[0];
            array_shift($res);
            $res[] = 0;
            foreach ($gen as $j => $g) {
                if ($j === 0) continue;
                $res[$j - 1] ^= self::gfMul($g, $factor);
            }
        }
        return $res;
    }

    private static function placeFinder(array &$m, array &$reserved, int $r0, int $c0, int $size): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $r0 + $r; $cc = $c0 + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
                $isBorder = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                    || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6));
                $isCenter = ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                $m[$rr][$cc] = ($isBorder || $isCenter) ? 1 : 0;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeTiming(array &$m, array &$reserved, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) { $m[6][$i] = $bit; $reserved[6][$i] = true; }
            if (!$reserved[$i][6]) { $m[$i][6] = $bit; $reserved[$i][6] = true; }
        }
    }

    private static function placeAlignment(array &$m, array &$reserved, int $version, int $size): void
    {
        $pos = self::ALIGN[$version];
        $n = count($pos);
        if ($n === 0) return;
        $first = $pos[0]; $last = $pos[$n - 1];
        foreach ($pos as $r) {
            foreach ($pos as $c) {
                // Saltar las 3 esquinas que pisan los patrones localizadores.
                if (($r === $first && $c === $first)
                    || ($r === $first && $c === $last)
                    || ($r === $last && $c === $first)) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $rr = $r + $dr; $cc = $c + $dc;
                        $isRing = (abs($dr) === 2 || abs($dc) === 2);
                        $isCenter = ($dr === 0 && $dc === 0);
                        $m[$rr][$cc] = ($isRing || $isCenter) ? 1 : 0;
                        $reserved[$rr][$cc] = true;
                    }
                }
            }
        }
    }

    private static function reserveFormat(array &$reserved, int $size): void
    {
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) { $reserved[8][$i] = true; $reserved[$i][8] = true; }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }
    }

    private static function reserveVersion(array &$reserved, int $size): void
    {
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $reserved[$i][$size - 11 + $j] = true;
                $reserved[$size - 11 + $j][$i] = true;
            }
        }
    }

    private static function placeData(array &$m, array &$reserved, array $bits, int $size): void
    {
        $n = count($bits);
        $bi = 0;
        $up = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col = 5; // saltar columna del timing vertical
            for ($i = 0; $i < $size; $i++) {
                $row = $up ? ($size - 1 - $i) : $i;
                for ($k = 0; $k < 2; $k++) {
                    $c = $col - $k;
                    if (!$reserved[$row][$c]) {
                        $m[$row][$c] = ($bi < $n) ? $bits[$bi] : 0;
                        $bi++;
                    }
                }
            }
            $up = !$up;
        }
    }

    private static function maskBit(int $mask, int $row, int $col): bool
    {
        return match ($mask) {
            0 => ($row + $col) % 2 === 0,
            1 => $row % 2 === 0,
            2 => $col % 3 === 0,
            3 => ($row + $col) % 3 === 0,
            4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
            5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
            6 => ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0,
            7 => ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0,
            default => false,
        };
    }

    private static function applyMask(array &$m, array $reserved, int $mask, int $size): void
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!$reserved[$r][$c] && self::maskBit($mask, $r, $c)) {
                    $m[$r][$c] ^= 1;
                }
            }
        }
    }

    private static function chooseMask(array $m, array $reserved, int $size): int
    {
        $best = 0; $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $test = $m;
            self::applyMask($test, $reserved, $mask, $size);
            $p = self::penalty($test, $size);
            if ($p < $bestPenalty) { $bestPenalty = $p; $best = $mask; }
        }
        return $best;
    }

    private static function penalty(array $m, int $size): int
    {
        $penalty = 0;
        // Regla 1: 5+ módulos consecutivos iguales (filas y columnas)
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) { $run++; }
                else { if ($run >= 5) $penalty += 3 + ($run - 5); $run = 1; }
            }
            if ($run >= 5) $penalty += 3 + ($run - 5);
        }
        for ($c = 0; $c < $size; $c++) {
            $run = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) { $run++; }
                else { if ($run >= 5) $penalty += 3 + ($run - 5); $run = 1; }
            }
            if ($run >= 5) $penalty += 3 + ($run - 5);
        }
        // Regla 2: bloques 2x2
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }
        // Regla 3: patrón 1011101 (0000) en filas y columnas
        $pat1 = [1,0,1,1,1,0,1,0,0,0,0];
        $pat2 = [0,0,0,0,1,0,1,1,1,0,1];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                $seg = array_slice($m[$r], $c, 11);
                if ($seg === $pat1 || $seg === $pat2) $penalty += 40;
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r <= $size - 11; $r++) {
                $seg = [];
                for ($k = 0; $k < 11; $k++) $seg[] = $m[$r + $k][$c];
                if ($seg === $pat1 || $seg === $pat2) $penalty += 40;
            }
        }
        // Regla 4: proporción de oscuros
        $dark = 0;
        foreach ($m as $row) $dark += array_sum($row);
        $total = $size * $size;
        $ratio = ($dark * 100) / $total;
        $prev = (int)(floor($ratio / 5) * 5);
        $next = $prev + 5;
        $penalty += min(abs($prev - 50), abs($next - 50)) / 5 * 10;
        return (int)$penalty;
    }

    private static function bchFormat(int $data5): int
    {
        $d = $data5 << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($d >> $i) & 1) $d ^= (0x537 << ($i - 10));
        }
        return (($data5 << 10) | $d) ^ 0x5412;
    }

    private static function bchVersion(int $version): int
    {
        $d = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if (($d >> $i) & 1) $d ^= (0x1f25 << ($i - 12));
        }
        return ($version << 12) | $d;
    }

    private static function placeFormat(array &$m, int $size, string $ecLevel, int $mask): void
    {
        $ecBits = ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10][$ecLevel];
        $data5 = ($ecBits << 3) | $mask;
        $fmt = self::bchFormat($data5); // 15 bits

        // bit 0 = MSB (posición 14)
        $get = static fn(int $i): int => ($fmt >> (14 - $i)) & 1;

        // Copia 1 (alrededor del localizador superior-izquierdo)
        for ($i = 0; $i <= 5; $i++) $m[8][$i] = $get($i);
        $m[8][7] = $get(6);
        $m[8][8] = $get(7);
        $m[7][8] = $get(8);
        for ($i = 9; $i <= 14; $i++) $m[14 - $i][8] = $get($i);

        // Copia 2: vertical (bits 0-6) en col 8 subiendo desde abajo;
        //          horizontal (bits 7-14) en fila 8 desde col size-8 hasta size-1.
        for ($i = 0; $i <= 6; $i++) $m[$size - 1 - $i][8] = $get($i);
        for ($i = 7; $i <= 14; $i++) $m[8][$size - 8 + ($i - 7)] = $get($i);
    }

    private static function placeVersion(array &$m, int $version, int $size): void
    {
        $v = self::bchVersion($version); // 18 bits
        for ($i = 0; $i < 18; $i++) {
            $bit = ($v >> $i) & 1;
            $r = intdiv($i, 3);
            $c = $i % 3;
            $m[$r][$size - 11 + $c] = $bit;
            $m[$size - 11 + $c][$r] = $bit;
        }
    }
}
