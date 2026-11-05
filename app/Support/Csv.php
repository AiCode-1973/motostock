<?php
declare(strict_types=1);

namespace App\Support;

/** CSV para Excel brasileiro (separador ;, UTF-8 com BOM) com proteção contra injeção de fórmulas. */
final class Csv
{
    public static function build(array $header, iterable $rows): string
    {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_map([self::class, 'cell'], $header), ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($h, array_map([self::class, 'cell'], array_values($row)), ';', '"', '');
        }
        rewind($h);
        $out = (string) stream_get_contents($h);
        fclose($h);
        return $out;
    }

    public static function cell(mixed $v): string
    {
        $s = (string) ($v ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s)) {
            $s = "'" . $s;
        }
        return $s;
    }

    /** Lê CSV com ; ou , e devolve linhas associativas pelo cabeçalho (minúsculo). */
    public static function parse(string $content, int $maxRows = 5000): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        if (!$lines) {
            return [];
        }
        $sep = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn($c) => mb_strtolower(trim($c)), str_getcsv($lines[0], $sep, '"', ''));
        $rows = [];
        foreach (array_slice($lines, 1, $maxRows) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $sep, '"', '');
            $rows[$i + 2] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }
        return $rows;
    }
}
