<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Clock;

/**
 * Período de relatório em datas LOCAIS da loja, convertido para intervalo UTC [início, fim).
 * Ex.: 01/11 a 30/11 em America/Sao_Paulo → 2026-11-01 03:00:00 UTC até 2026-12-01 03:00:00 UTC.
 */
final class Period
{
    public function __construct(
        public readonly string $from,   // Y-m-d local
        public readonly string $to,     // Y-m-d local (inclusive)
        public readonly string $tz,
    ) {
    }

    public static function fromInput(?string $from, ?string $to, string $tz, string $defaultPreset = '30d'): self
    {
        $zone = new \DateTimeZone($tz);
        $today = (new \DateTimeImmutable('@' . Clock::ts()))->setTimezone($zone);
        $valid = static fn(?string $d) => $d && \DateTime::createFromFormat('Y-m-d', $d)?->format('Y-m-d') === $d;
        if (!$valid($from) || !$valid($to)) {
            $to = $today->format('Y-m-d');
            $from = match ($defaultPreset) {
                'today' => $to,
                'month' => $today->format('Y-m-01'),
                default => $today->modify('-29 days')->format('Y-m-d'),
            };
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        // Limite de 366 dias por consulta para proteger o banco.
        $max = (new \DateTimeImmutable($from))->modify('+366 days')->format('Y-m-d');
        if ($to > $max) {
            $to = $max;
        }
        return new self($from, $to, $tz);
    }

    public function startUtc(): string
    {
        return (new \DateTimeImmutable($this->from . ' 00:00:00', new \DateTimeZone($this->tz)))
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function endUtc(): string
    {
        return (new \DateTimeImmutable($this->to . ' 00:00:00', new \DateTimeZone($this->tz)))
            ->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Expressão SQL que converte uma coluna UTC para a data local (offset fixo do início do período). */
    public function offset(): string
    {
        $d = new \DateTimeImmutable($this->from . ' 12:00:00', new \DateTimeZone($this->tz));
        return $d->format('P');
    }

    public function label(): string
    {
        $f = \DateTime::createFromFormat('Y-m-d', $this->from)->format('d/m/Y');
        $t = \DateTime::createFromFormat('Y-m-d', $this->to)->format('d/m/Y');
        return $f === $t ? $f : "{$f} a {$t}";
    }
}
