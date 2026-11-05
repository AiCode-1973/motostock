<?php
declare(strict_types=1);

namespace App\Controllers\Main;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\ValidationException;
use App\Support\Migrator;

/**
 * Instalação e atualização pelo navegador, para hospedagem sem terminal.
 * Só funciona com SETUP_TOKEN (32+ caracteres) no .env e o mesmo token na URL.
 * Cria o PRIMEIRO administrador da plataforma; depois disso só mostra a situação e aplica migrations.
 */
final class InstallController extends Controller
{
    protected string $layout = 'layouts/minimal';

    private function authorize(Request $r): string
    {
        $expected = (string) Env::get('SETUP_TOKEN', '');
        $given = (string) ($r->post['token'] ?? $r->query['token'] ?? '');
        if (strlen($expected) < 32 || !hash_equals($expected, $given)) {
            // 404 para não revelar que o instalador existe.
            throw HttpException::notFound();
        }
        return $given;
    }

    public function show(Request $r): Response
    {
        $token = $this->authorize($r);
        return $this->view('install/index', ['title' => 'Instalação'] + $this->state($token));
    }

    public function run(Request $r): Response
    {
        $token = $this->authorize($r);
        $state = $this->state($token);
        if (!$state['dbOk']) {
            throw new \App\Core\BusinessException('Sem conexão com o banco. Confira DB_* no .env.');
        }
        $db = Database::connect();
        $applied = Migrator::run($db);
        if (!$db->value("SELECT 1 FROM users WHERE platform_role = 'admin' LIMIT 1")) {
            $d = Validator::check($r->post, ['name' => 'required|max:120', 'email' => 'required|email|max:160', 'password' => 'required|password'],
                ['name' => 'Nome', 'email' => 'E-mail', 'password' => 'Senha']);
            if (mb_strlen($d['password']) < 12) {
                throw new ValidationException(['password' => 'Para a conta de administrador, use pelo menos 12 caracteres.']);
            }
            if (($r->post['password_confirmation'] ?? '') !== $r->post['password']) {
                throw new ValidationException(['password_confirmation' => 'As senhas não conferem.']);
            }
            $existing = $db->fetch('SELECT id FROM users WHERE email = ?', [$d['email']]);
            if ($existing) {
                $db->execute("UPDATE users SET platform_role = 'admin' WHERE id = ?", [$existing['id']]);
                $uid = (int) $existing['id'];
            } else {
                $uid = $db->insert('users', [
                    'name' => $d['name'], 'email' => $d['email'], 'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
                    'platform_role' => 'admin', 'password_changed_at' => Clock::now(), 'created_at' => Clock::now(), 'updated_at' => Clock::now(),
                ]);
            }
            Audit::log('system', 'platform.admin_created', null, $uid, 'user', $uid, ['via' => 'instalador']);
            $this->flash('Administrador criado. Entre e ative a verificação em duas etapas. Depois apague SETUP_TOKEN do .env.');
        } else {
            $this->flash($applied ? 'Banco atualizado: ' . implode(', ', $applied) . '.' : 'Nada pendente: o banco já está atualizado.');
        }
        Audit::log('system', 'install.run', null, null, null, null, ['migrations' => $applied]);
        return $this->redirect('/instalacao', ['token' => $token]);
    }

    private function state(string $token): array
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $hint = '', bool $required = true) use (&$checks) {
            $checks[] = compact('label', 'ok', 'hint', 'required');
        };
        $add('PHP 8.1 ou mais novo (atual: ' . PHP_VERSION . ')', PHP_VERSION_ID >= 80100, 'Mude a versão em cPanel › Selecionar versão do PHP.');
        foreach (['pdo_mysql', 'openssl', 'mbstring', 'fileinfo'] as $ext) {
            $add("Extensão {$ext}", extension_loaded($ext), 'Ative em cPanel › Selecionar versão do PHP › Extensões.');
        }
        $add('Extensão curl (pagamento Mercado Pago)', extension_loaded('curl'), 'Necessária só para pagamento online.', false);
        try {
            Crypto::encrypt('teste');
            $keyOk = true;
        } catch (\Throwable) {
            $keyOk = false;
        }
        $add('APP_KEY configurada', $keyOk, 'Gere uma chave e cole no .env (APP_KEY=base64:...). Guarde uma cópia em local seguro.');
        foreach (['storage/logs', 'storage/uploads'] as $dir) {
            $add("Pasta {$dir} gravável", is_dir(BASE_PATH . '/' . $dir) && is_writable(BASE_PATH . '/' . $dir), 'Permissão 755 ou 775 na pasta.');
        }
        $add('APP_URL com https', str_starts_with((string) Env::get('APP_URL'), 'https://'), 'Use https:// e ative o SSL do domínio.', false);
        $add('APP_DEBUG desligado', !Env::bool('APP_DEBUG'), 'Em produção: APP_DEBUG=false.', false);
        $add('Gateway simulado desligado', !Env::bool('SIMULATED_GATEWAY_ENABLED'), 'Em produção: SIMULATED_GATEWAY_ENABLED=false (ligue só para demonstração).', false);

        $dbOk = false;
        $dbError = '';
        $pending = [];
        $hasAdmin = false;
        $plans = 0;
        try {
            $db = Database::connect();
            $dbOk = true;
            $pending = Migrator::pending($db);
            if (!in_array('001_schema', $pending, true)) {
                $hasAdmin = (bool) $db->value("SELECT 1 FROM users WHERE platform_role = 'admin' LIMIT 1");
                $plans = (int) $db->value('SELECT COUNT(*) FROM plans WHERE active = 1 AND is_public = 1');
            }
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }
        $add('Conexão com o banco', $dbOk, $dbError !== '' ? 'Erro: ' . mb_substr($dbError, 0, 200) . ' · Confira DB_HOST, DB_DATABASE, DB_USERNAME e DB_PASSWORD.' : '');
        return [
            'token' => $token,
            'checks' => $checks,
            'dbOk' => $dbOk,
            'pending' => $pending,
            'hasAdmin' => $hasAdmin,
            'plans' => $plans,
            'blocking' => (bool) array_filter($checks, fn($c) => $c['required'] && !$c['ok']),
            'cron' => '*/15 * * * * php ' . BASE_PATH . '/bin/cron.php >> ' . BASE_PATH . '/storage/logs/cron.log 2>&1',
        ];
    }
}
