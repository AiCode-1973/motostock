<?php
declare(strict_types=1);

namespace App\Core;

/** Valores em centavos (inteiros). */
final class Money
{
    public static function format(?int $cents): string
    {
        $cents ??= 0;
        $neg = $cents < 0 ? '-' : '';
        return $neg . 'R$ ' . number_format(abs($cents) / 100, 2, ',', '.');
    }

    /** Aceita "1.234,56", "1234,56", "1234.56" e "1234". Devolve null se inválido. */
    public static function parse(string $v): ?int
    {
        $v = trim(str_replace(['R$', ' ', "\u{00a0}"], '', $v));
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d{1,2})?$/', $v) || preg_match('/^\d+(,\d{1,2})?$/', $v)) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
            return null;
        }
        $cents = (int) round(((float) $v) * 100);
        return $cents > 2_000_000_000 ? null : $cents;
    }

    /** Para preencher campos de formulário: 12990 → "129,90". */
    public static function input(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, ',', '');
    }
}
