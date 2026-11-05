<?php
declare(strict_types=1);

// Cria (ou promove) um administrador da plataforma MotoStock.
// Uso: php bin/create-platform-admin.php email@empresa.com.br "Nome Completo" [admin|support]
// Uma senha aleatória é exibida UMA vez no terminal. Troque-a no primeiro acesso e ative a verificação em duas etapas.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Clock;
use App\Core\Database;

[$script, $email, $name, $role] = array_pad($argv, 4, null);
$role ??= 'admin';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$name || !in_array($role, ['admin', 'support'], true)) {
    fwrite(STDERR, "Uso: php bin/create-platform-admin.php email \"Nome\" [admin|support]\n");
    exit(1);
}
$db = Database::connect();
$email = mb_strtolower($email);
$existing = $db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
if ($existing) {
    $db->execute('UPDATE users SET platform_role = ?, updated_at = ? WHERE id = ?', [$role, Clock::now(), $existing['id']]);
    Audit::log('system', 'platform.admin_promoted', null, (int) $existing['id'], 'user', (int) $existing['id'], ['role' => $role]);
    echo "Usuário existente promovido a {$role}. A senha atual foi mantida.\n";
    exit(0);
}
$password = rtrim(strtr(base64_encode(random_bytes(15)), '+/', 'Ab'), '=');
$id = $db->insert('users', [
    'name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'platform_role' => $role,
    'password_changed_at' => Clock::now(), 'created_at' => Clock::now(), 'updated_at' => Clock::now(),
]);
Audit::log('system', 'platform.admin_created', null, $id, 'user', $id, ['role' => $role]);
echo "Administrador criado: {$email}\nSenha provisória (anote agora, não será exibida de novo): {$password}\n";
