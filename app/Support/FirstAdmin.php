<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Clock;
use App\Core\Database;
use App\Core\Env;

/**
 * Cria o primeiro administrador da plataforma a partir de ADMIN_NAME, ADMIN_EMAIL e ADMIN_PASSWORD do .env.
 * Só age se ainda não existir nenhum administrador. Depois de usar, apaga ADMIN_PASSWORD do arquivo .env.
 */
final class FirstAdmin
{
    /** @return string mensagem para o log da instalação ('' se não havia nada a fazer) */
    public static function fromEnv(Database $db, string $envFile): string
    {
        $email = mb_strtolower(trim((string) Env::get('ADMIN_EMAIL', '')));
        $password = (string) Env::get('ADMIN_PASSWORD', '');
        if ($email === '' && $password === '') {
            return '';
        }
        if ($db->value("SELECT 1 FROM users WHERE platform_role = 'admin' LIMIT 1")) {
            self::forgetPassword($envFile);
            return 'Administrador já existia; ADMIN_PASSWORD removida do .env.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'ADMIN_EMAIL inválido: administrador não criado.';
        }
        if (mb_strlen($password) < 12) {
            return 'ADMIN_PASSWORD precisa de pelo menos 12 caracteres: administrador não criado.';
        }
        $name = trim((string) Env::get('ADMIN_NAME', '')) ?: 'Administrador';
        $existing = $db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            $db->execute("UPDATE users SET platform_role = 'admin', password_hash = ?, password_changed_at = ?, updated_at = ? WHERE id = ?",
                [password_hash($password, PASSWORD_DEFAULT), Clock::now(), Clock::now(), $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = $db->insert('users', [
                'name' => mb_substr($name, 0, 120), 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'platform_role' => 'admin', 'password_changed_at' => Clock::now(), 'created_at' => Clock::now(), 'updated_at' => Clock::now(),
            ]);
        }
        Audit::log('system', 'platform.admin_created', null, $id, 'user', $id, ['via' => '.env']);
        self::forgetPassword($envFile);
        return "Administrador criado: {$email}";
    }

    /** Esvazia ADMIN_PASSWORD no arquivo .env (a senha já está guardada só como hash no banco). */
    public static function forgetPassword(string $envFile): void
    {
        if (!is_file($envFile) || !is_writable($envFile)) {
            return;
        }
        $content = (string) file_get_contents($envFile);
        $clean = preg_replace('/^ADMIN_PASSWORD=.*$/m', 'ADMIN_PASSWORD=', $content);
        if ($clean !== null && $clean !== $content) {
            file_put_contents($envFile, $clean);
        }
        Env::set('ADMIN_PASSWORD', '');
    }
}
