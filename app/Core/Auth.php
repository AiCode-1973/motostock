<?php
declare(strict_types=1);

namespace App\Core;

/** Autenticação da equipe (lojistas e plataforma). Clientes da loja usam CustomerAuth. */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = Session::get('user_id');
        if (!is_int($id)) {
            return null;
        }
        $now = Clock::ts();
        $idle = Env::int('SESSION_IDLE_MINUTES', 120) * 60;
        $absolute = Env::int('SESSION_ABSOLUTE_MINUTES', 720) * 60;
        if ($now - (int) Session::get('auth_last', 0) > $idle || $now - (int) Session::get('auth_at', 0) > $absolute) {
            self::logout();
            Session::flash('info', 'Sua sessão expirou. Entre novamente.');
            return null;
        }
        $user = Database::connect()->fetch('SELECT * FROM users WHERE id = ? AND status = \'active\'', [$id]);
        // Troca de senha encerra as outras sessões.
        if (!$user || (string) Session::get('pwd_stamp') !== (string) $user['password_changed_at']) {
            self::logout();
            return null;
        }
        Session::set('auth_last', $now);
        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::remove('mfa_pending');
        Session::set('user_id', (int) $user['id']);
        Session::set('auth_at', Clock::ts());
        Session::set('auth_last', Clock::ts());
        Session::set('pwd_stamp', (string) $user['password_changed_at']);
        Database::connect()->execute('UPDATE users SET last_login_at = ? WHERE id = ?', [Clock::now(), $user['id']]);
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        Session::clear();
        self::$user = null;
        self::$loaded = true;
    }

    /** Confere e-mail e senha em tempo constante, sem revelar se o e-mail existe. */
    public static function verifyCredentials(string $email, string $password): ?array
    {
        $user = Database::connect()->fetch('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        static $dummy = null;
        $dummy ??= password_hash(random_bytes(12), PASSWORD_DEFAULT);
        $hash = $user['password_hash'] ?? $dummy;
        $ok = password_verify($password, $hash);
        if (!$user || !$ok || $user['status'] !== 'active') {
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::connect()->execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        return $user;
    }
}
