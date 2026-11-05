<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Money;
use App\Core\Tenant;
use App\Core\Url;
use App\Core\View;

/** Escape obrigatório para toda saída em HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    return Url::to($path, $query);
}

function store_url(string $path = '/', array $query = []): string
{
    return Url::store($path, $query);
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return Url::to('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    $old = View::$shared['old'] ?? [];
    return array_key_exists($key, $old) ? $old[$key] : $default;
}

function field_error(string $key): string
{
    $errors = View::$shared['errors'] ?? [];
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>' : '';
}

function has_error(string $key): bool
{
    return isset((View::$shared['errors'] ?? [])[$key]);
}

/** Atributos de acessibilidade para um campo com possível erro. */
function field_attrs(string $key): string
{
    return has_error($key) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
}

function money(?int $cents): string
{
    return Money::format($cents);
}

function money_input(?int $cents): string
{
    return Money::input($cents);
}

/** Converte data UTC do banco para o fuso da loja. */
function dt(?string $utc, string $format = 'd/m/Y H:i', ?string $tz = null): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $d->setTimezone(new DateTimeZone($tz ?? Tenant::timezone()))->format($format);
}

function can(string $permission): bool
{
    return Tenant::can($permission);
}

function uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function slugify(string $text): string
{
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e','í'=>'i','î'=>'i','ì'=>'i','ï'=>'i',
        'ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o','ú'=>'u','û'=>'u','ù'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n'];
    $t = strtr(mb_strtolower($text), $map);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    return trim(substr($t, 0, 150), '-') ?: 'item';
}

function format_doc(?string $digits): string
{
    $d = preg_replace('/\D/', '', (string) $digits) ?? '';
    return match (strlen($d)) {
        14 => vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($d)),
        11 => vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($d)),
        default => $d,
    };
}

function format_phone(?string $digits): string
{
    $d = preg_replace('/\D/', '', (string) $digits) ?? '';
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return $d;
}

function format_cep(?string $d): string
{
    $d = preg_replace('/\D/', '', (string) $d) ?? '';
    return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : $d;
}

function label(string $group, ?string $code): string
{
    return App\Support\Labels::get($group, $code);
}

function badge(string $group, ?string $code): string
{
    return '<span class="badge badge--' . e($group . '-' . $code) . '">' . e(label($group, $code)) . '</span>';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $v): string
{
    return $v ? ' checked' : '';
}

/** Monta query string mantendo filtros atuais (para paginação). */
function query_with(array $changes): string
{
    $q = array_merge(View::$shared['query'] ?? [], $changes);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return $q ? '?' . http_build_query($q) : '';
}
