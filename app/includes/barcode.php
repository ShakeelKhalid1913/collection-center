<?php

declare(strict_types=1);

/**
 * Standard Code 128 (Subset B) SVG Barcode Generator.
 * Zero external libraries or internet connectivity required.
 */

if (!function_exists('generate_barcode_svg')) {
    function generate_barcode_svg(string $text, int $barHeight = 48, int $scale = 2): string
    {
        $code128B = [
            ' ' => '212222', '!' => '222122', '"' => '222221', '#' => '121223', '$' => '121322',
            '%' => '131222', '&' => '122213', '\'' => '122312', '(' => '132212', ')' => '221213',
            '*' => '221312', '+' => '231212', ',' => '112232', '-' => '122132', '.' => '122231',
            '/' => '113222', '0' => '123122', '1' => '123221', '2' => '223211', '3' => '221132',
            '4' => '221231', '5' => '213212', '6' => '223112', '7' => '312131', '8' => '311222',
            '9' => '321122', ':' => '321221', ';' => '312212', '<' => '322112', '=' => '322211',
            '>' => '212123', '?' => '212321', '@' => '232121', 'A' => '111323', 'B' => '131123',
            'C' => '131321', 'D' => '112313', 'E' => '132113', 'F' => '132311', 'G' => '211313',
            'H' => '231113', 'I' => '231311', 'J' => '112133', 'K' => '112331', 'L' => '132131',
            'M' => '113123', 'N' => '113321', 'O' => '133121', 'P' => '313121', 'Q' => '211331',
            'R' => '231131', 'S' => '213113', 'T' => '213311', 'U' => '213131', 'V' => '311123',
            'W' => '311321', 'X' => '331121', 'Y' => '312113', 'Z' => '312311', '[' => '332111',
            '\\' => '314111', ']' => '221411', '^' => '431111', '_' => '111224', '`' => '111422',
            'a' => '121124', 'b' => '121421', 'c' => '141122', 'd' => '141221', 'e' => '112214',
            'f' => '112412', 'g' => '122114', 'h' => '122411', 'i' => '142112', 'j' => '142211',
            'k' => '241211', 'l' => '221114', 'm' => '413111', 'n' => '241112', 'o' => '134111',
            'p' => '111242', 'q' => '121142', 'r' => '121241', 's' => '114212', 't' => '124112',
            'u' => '124211', 'v' => '411212', 'w' => '421112', 'x' => '421211', 'y' => '212141',
            'z' => '214121', '{' => '412121', '|' => '111143', '}' => '111341', '~' => '131141',
            'START_B' => '211214',
            'STOP' => '2331112',
        ];

        $chars = str_split($text);
        $checksum = 104; // Start B is index 104
        $patterns = [$code128B['START_B']];

        $charIndexMap = [];
        $i = 0;
        foreach ($code128B as $k => $v) {
            if ($k !== 'START_B' && $k !== 'STOP') {
                $charIndexMap[$k] = $i++;
            }
        }

        $pos = 1;
        foreach ($chars as $ch) {
            if (isset($code128B[$ch])) {
                $patterns[] = $code128B[$ch];
                $codeVal = $charIndexMap[$ch] ?? 0;
                $checksum += $codeVal * $pos;
                $pos++;
            }
        }

        $checkIndex = $checksum % 103;
        // Find pattern by index
        $keys = array_keys($charIndexMap);
        $checkChar = $keys[$checkIndex] ?? '0';
        $patterns[] = $code128B[$checkChar] ?? '123122';
        $patterns[] = $code128B['STOP'];

        // Convert patterns into SVG rect bars
        $combined = implode('', $patterns);
        $totalUnits = 0;
        for ($j = 0; $j < strlen($combined); $j++) {
            $totalUnits += (int)$combined[$j];
        }

        $x = 10 * $scale; // 10 units quiet zone
        $rects = '';
        $isBar = true;

        for ($j = 0; $j < strlen($combined); $j++) {
            $width = (int)$combined[$j] * $scale;
            if ($isBar) {
                $rects .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $barHeight . '" fill="#000000" />';
            }
            $x += $width;
            $isBar = !$isBar;
        }

        $totalWidth = $x + (10 * $scale);
        $svgHeight = $barHeight + 16;
        $textX = $totalWidth / 2;
        $textY = $barHeight + 12;

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$totalWidth} {$svgHeight}" width="100%" height="auto" style="max-height:{$svgHeight}px;display:block;margin:0 auto;">
            {$rects}
            <text x="{$textX}" y="{$textY}" font-family="monospace, monospace" font-size="11" font-weight="bold" text-anchor="middle" fill="#0f172a">{$text}</text>
        </svg>
        SVG;
    }
}
