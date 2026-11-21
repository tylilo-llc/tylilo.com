<?php

declare(strict_types=1);

/**
 * Byte-mode QR codes, ECC level L, versions 1–5. Drawn locally as SVG.
 */
function tylilo_admin_qr_svg(string $text): string
{
    $matrix = tylilo_admin_qr_matrix($text);
    $n = count($matrix);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . ($n + 8) . ' ' . ($n + 8) . '" role="img" aria-label="QR code for the authenticator setup">';
    $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($matrix[$y][$x] === 1) {
                $svg .= '<rect x="' . ($x + 4) . '" y="' . ($y + 4) . '" width="1" height="1" fill="#000"/>';
            }
        }
    }
    $svg .= '</svg>';

    return $svg;
}

function tylilo_admin_qr_matrix(string $text): array
{
    $bytes = array_values(unpack('C*', $text));
    $specs = [
        1 => [19, 7, []],
        2 => [34, 10, [18]],
        3 => [55, 15, [22]],
        4 => [80, 20, [26]],
        5 => [108, 26, [30]],
    ];
    $version = 0;
    $data = [];
    $eccCount = 0;
    $align = [];
    foreach ($specs as $ver => [$capacity, $ecc, $positions]) {
        $data = tylilo_admin_qr_data($bytes, $capacity);
        if ($data !== []) {
            $version = $ver;
            $eccCount = $ecc;
            $align = $positions;
            break;
        }
    }
    if ($version === 0) {
        throw new RuntimeException('That setup code is too long for the QR code.');
    }
    $ecc = tylilo_admin_qr_ecc($data, $eccCount);
    $codewords = array_merge($data, $ecc);
    $size = 17 + 4 * $version;
    $matrix = array_fill(0, $size, array_fill(0, $size, 0));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));
    $set = static function (int $x, int $y, int $bit) use (&$matrix, &$reserved, $size): void {
        if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
            $matrix[$y][$x] = $bit;
            $reserved[$y][$x] = true;
        }
    };
    $finder = static function (int $x, int $y) use ($set): void {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $on = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6 && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $set($x + $dx, $y + $dy, $on ? 1 : 0);
            }
        }
    };
    $finder(0, 0);
    $finder($size - 7, 0);
    $finder(0, $size - 7);
    for ($i = 0; $i < $size; $i++) {
        if (!$reserved[6][$i]) {
            $set($i, 6, $i % 2 === 0 ? 1 : 0);
        }
        if (!$reserved[$i][6]) {
            $set(6, $i, $i % 2 === 0 ? 1 : 0);
        }
    }
    foreach ($align as $ay) {
        foreach ($align as $ax) {
            if (($ax < 10 && $ay < 10) || ($ax > $size - 10 && $ay < 10) || ($ax < 10 && $ay > $size - 10)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $on = max(abs($dx), abs($dy)) !== 1;
                    $set($ax + $dx, $ay + $dy, $on ? 1 : 0);
                }
            }
        }
    }
    $set(8, 4 * $version + 9, 1);
    for ($i = 0; $i < 9; $i++) {
        if ($i !== 6) {
            $reserved[8][$i] = $reserved[$i][8] = true;
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $reserved[8][$size - 1 - $i] = $reserved[$size - 1 - $i][8] = true;
    }
    $bits = '';
    foreach ($codewords as $word) {
        $bits .= str_pad(decbin($word), 8, '0', STR_PAD_LEFT);
    }
    $bitIndex = 0;
    $bitLen = strlen($bits);
    for ($step = $size - 1; $step > 0; $step -= 2) {
        $right = $step <= 6 ? $step - 1 : $step;
        for ($vertical = 0; $vertical < $size; $vertical++) {
            for ($z = 0; $z < 2; $z++) {
                $x = $right - $z;
                $upwards = (($right & 2) === 0) !== ($x < 6);
                $y = $upwards ? $size - 1 - $vertical : $vertical;
                if ($x < 0 || $reserved[$y][$x]) {
                    continue;
                }
                $matrix[$y][$x] = $bitIndex < $bitLen && $bits[$bitIndex] === '1' ? 1 : 0;
                $bitIndex++;
            }
        }
    }
    $best = null;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $candidate = $matrix;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($reserved[$y][$x]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => (($x * $y) % 2) + (($x * $y) % 3) === 0,
                    6 => ((($x * $y) % 2) + (($x * $y) % 3)) % 2 === 0,
                    default => ((($x + $y) % 2) + (($x * $y) % 3)) % 2 === 0,
                };
                if ($flip) {
                    $candidate[$y][$x] ^= 1;
                }
            }
        }
        $format = tylilo_admin_qr_format($mask);
        tylilo_admin_qr_apply_format($candidate, $format);
        $score = tylilo_admin_qr_penalty($candidate);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $candidate;
        }
    }

    return $best ?? $matrix;
}

function tylilo_admin_qr_data(array $bytes, int $capacity): array
{
    $length = count($bytes);
    if ($length > 106) {
        return [];
    }
    $bits = '0100' . str_pad(decbin($length), 8, '0', STR_PAD_LEFT);
    foreach ($bytes as $byte) {
        $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
    }
    $capacityBits = $capacity * 8;
    $bits .= str_repeat('0', min(4, max(0, $capacityBits - strlen($bits))));
    while (strlen($bits) % 8 !== 0) {
        $bits .= '0';
    }
    $pads = ['11101100', '00010001'];
    $pad = 0;
    while (strlen($bits) < $capacityBits) {
        $bits .= $pads[$pad % 2];
        $pad++;
    }
    if (strlen($bits) > $capacityBits) {
        return [];
    }
    $out = [];
    foreach (str_split($bits, 8) as $chunk) {
        $out[] = bindec($chunk);
    }

    return $out;
}

function tylilo_admin_qr_ecc(array $data, int $eccCount): array
{
    $generators = [
        7 => [87, 229, 146, 149, 238, 102, 21],
        10 => [251, 67, 46, 61, 118, 70, 64, 94, 32, 45],
        15 => [8, 183, 61, 91, 202, 37, 51, 58, 58, 237, 140, 124, 5, 99, 105],
        20 => [17, 60, 79, 50, 61, 163, 26, 187, 202, 180, 221, 225, 83, 239, 156, 164, 212, 212, 188, 190],
        26 => [173, 125, 158, 2, 103, 182, 118, 17, 145, 201, 111, 28, 165, 53, 161, 21, 245, 142, 13, 102, 48, 227, 153, 145, 218, 70],
    ];
    $generator = $generators[$eccCount];
    $block = array_merge($data, array_fill(0, $eccCount, 0));
    $length = count($data);
    for ($k = 0; $k < $length; $k++) {
        $coef = $block[$k];
        if ($coef === 0) {
            continue;
        }
        for ($n = 0; $n < $eccCount; $n++) {
            $block[$k + $n + 1] ^= tylilo_admin_qr_exp_sum($coef, $generator[$n]);
        }
    }

    return array_slice($block, $length);
}

function tylilo_admin_qr_exp_sum(int $coef, int $exponent): int
{
    if ($coef === 0) {
        return 0;
    }
    static $log = null;
    if ($log === null) {
        $log = array_fill(0, 256, 0);
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $log[$value] = $i;
            $value <<= 1;
            if ($value >= 256) {
                $value ^= 0x11d;
            }
        }
    }

    return tylilo_admin_qr_exp($log[$coef] + $exponent);
}

function tylilo_admin_qr_exp(int $n): int
{
    static $exp = null;
    static $log = null;
    if ($exp === null) {
        $exp = [];
        $log = array_fill(0, 256, 0);
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;
            if ($value >= 256) {
                $value ^= 0x11d;
            }
        }
        $exp = array_merge($exp, $exp);
    }

    return $exp[$n % 255];
}

function tylilo_admin_qr_mul(int $a, int $b): int
{
    static $log = null;
    static $exp = null;
    if ($log === null) {
        $exp = [];
        $log = array_fill(0, 256, 0);
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;
            if ($value >= 256) {
                $value ^= 0x11d;
            }
        }
        $exp = array_merge($exp, $exp);
    }
    if ($a === 0 || $b === 0) {
        return 0;
    }

    return $exp[$log[$a] + $log[$b]];
}

function tylilo_admin_qr_format(int $mask): int
{
    $table = [
        0x5412, 0x5125, 0x5e7c, 0x5b4b, 0x45f9, 0x40ce, 0x4f97, 0x4aa0,
        0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976,
    ];

    return $table[8 + $mask];
}

function tylilo_admin_qr_apply_format(array &$matrix, int $format): void
{
    $size = count($matrix);
    $vOffset = 0;
    $hOffset = 0;
    for ($i = 0; $i < 8; $i++) {
        $vBit = ($format >> $i) & 1;
        $hBit = ($format >> (14 - $i)) & 1;
        if ($i === 6) {
            $vOffset++;
            $hOffset = 1;
        }
        $matrix[$i + $vOffset][8] = $vBit;
        $matrix[8][$i + $hOffset] = $hBit;
        $matrix[8][$size - 1 - $i] = $vBit;
        $matrix[$size - 1 - $i][8] = $hBit;
    }
    $matrix[$size - 8][8] = 1;
}

function tylilo_admin_qr_penalty(array $matrix): int
{
    $size = count($matrix);
    $score = 0;
    for ($y = 0; $y < $size; $y++) {
        $run = 1;
        for ($x = 1; $x < $size; $x++) {
            if ($matrix[$y][$x] === $matrix[$y][$x - 1]) {
                $run++;
                if ($run === 5) {
                    $score += 3;
                } elseif ($run > 5) {
                    $score++;
                }
            } else {
                $run = 1;
            }
        }
    }

    return $score;
}
