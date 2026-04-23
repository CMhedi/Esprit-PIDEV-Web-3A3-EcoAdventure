<?php

namespace App\Service\Inscription;

final class Code39BarcodeGenerator
{
    private const PATTERNS = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw',
        'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn',
        'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw',
        'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn',
        'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn',
        'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn',
        'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw',
        'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw',
        '.' => 'wwnnnnwnn',
        ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn',
        '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn',
    ];

    public function generateSvg(string $value): string
    {
        $encodedValue = '*' . $this->sanitize($value) . '*';
        $narrow = 2;
        $wide = 5;
        $gap = $narrow;
        $height = 84;
        $quietZone = 16;
        $textHeight = 22;
        $x = $quietZone;
        $bars = [];

        foreach (str_split($encodedValue) as $character) {
            $pattern = self::PATTERNS[$character] ?? null;
            if ($pattern === null) {
                continue;
            }

            foreach (str_split($pattern) as $index => $weight) {
                $width = $weight === 'w' ? $wide : $narrow;

                if ($index % 2 === 0) {
                    $bars[] = sprintf(
                        '<rect x="%d" y="0" width="%d" height="%d" rx="0.5" ry="0.5" fill="#111827"/>',
                        $x,
                        $width,
                        $height
                    );
                }

                $x += $width;
            }

            $x += $gap;
        }

        $totalWidth = $x + $quietZone;
        $totalHeight = $height + $textHeight;
        $label = htmlspecialchars($this->sanitize($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="Barcode %3$s"><rect width="100%%" height="100%%" fill="#ffffff"/>%4$s<text x="%5$d" y="%6$d" text-anchor="middle" font-family="DejaVu Sans, Arial, sans-serif" font-size="14" letter-spacing="2" fill="#111827">%3$s</text></svg>',
            $totalWidth,
            $totalHeight,
            $label,
            implode('', $bars),
            (int) round($totalWidth / 2),
            $height + 16
        );
    }

    private function sanitize(string $value): string
    {
        $value = strtoupper(trim($value));

        return preg_replace('/[^0-9A-Z\\.\\-\\ \\$\\/\\+\\%]+/', '-', $value) ?? '';
    }
}
