<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sessão com armazenamento trocável: nativa (PHP) em produção, em memória nos testes.
 * Cookie HttpOnly, SameSite=Lax, Secure em HTTPS e regeneração do id no login.
 */
final class Session
{
    private static array $data = [];
    private static bool $native = false;
    private static bool $started = false;

    public static function startNative(bool $secure): void
    {
        self::$native = true;
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        session_name(Env::get('SESSION_NAME', 'motostock_sid'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) (Env::int('SESSION_ABSOLUTE_MINUTES', 720) * 60));
        session_start();
        self::$started = true;
    }

    /** Usado pelos testes: sessão em memória, isolada por "navegador". */
    public static function useArray(array &$store): void
    {
        self::$native = false;
        self::$data = &$store;
        self::$started = true;
    }

    private static function &bag(): array
    {
        if (self::$native) {
            return $_SESSION;
        }
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $bag = &self::bag();
        return $bag[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $bag = &self::bag();
        $bag[$key] = $value;
    }

    public static function remove(string $key): void
    {
        $bag = &self::bag();
        unset($bag[$key]);
    }

    public static function regenerate(): void
    {
        if (self::$native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::set('_csrf', bin2hex(random_bytes(32)));
    }

    public static function clear(): void
    {
        $bag = &self::bag();
        foreach (array_keys($bag) as $k) {
            unset($bag[$k]);
        }
        if (self::$native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flash(string $type, string $message): void
    {
        $bag = &self::bag();
        $bag['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        $bag = &self::bag();
        $f = $bag['_flash'] ?? [];
        unset($bag['_flash']);
        return $f;
    }

    public static function flashInput(array $input, array $errors): void
    {
        unset($input['_csrf'], $input['password'], $input['password_confirmation'], $input['current_password']);
        self::set('_old', $input);
        self::set('_errors', $errors);
    }

    /** Lê e descarta os dados do formulário anterior (uma única vez por requisição). */
    public static function takeFormState(): array
    {
        $state = ['old' => self::get('_old', []), 'errors' => self::get('_errors', [])];
        self::remove('_old');
        self::remove('_errors');
        return $state;
    }
}
