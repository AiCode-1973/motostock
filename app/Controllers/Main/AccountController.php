<?php
declare(strict_types=1);

namespace App\Controllers\Main;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\BusinessException;
use App\Core\Crypto;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Totp;
use App\Services\UserService;

/** Segurança da conta da equipe: senha e verificação em duas etapas (TOTP). */
final class AccountController extends Controller
{
    public function security(Request $r): Response
    {
        $user = Auth::user();
        $pending = Session::get('mfa_setup');
        return $this->view('auth/security', [
            'title' => 'Segurança da conta',
            'user' => $user,
            'setupSecret' => is_string($pending) ? $pending : null,
            'setupUri' => is_string($pending) ? Totp::uri($pending, $user['email']) : null,
        ]);
    }

    public function password(Request $r): Response
    {
        $user = Auth::user();
        if (!password_verify((string) ($r->post['current_password'] ?? ''), $user['password_hash'])) {
            throw new \App\Core\ValidationException(['current_password' => 'Senha atual incorreta.']);
        }
        $new = (string) ($r->post['password'] ?? '');
        \App\Core\Validator::check(['password' => $new], ['password' => 'required|password']);
        if ($new !== ($r->post['password_confirmation'] ?? '')) {
            throw new \App\Core\ValidationException(['password_confirmation' => 'As senhas não conferem.']);
        }
        (new UserService($this->db()))->setPassword((int) $user['id'], $new);
        Auth::login($this->db()->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]));
        Audit::log('system', 'auth.password_changed', null, (int) $user['id']);
        $this->flash('Senha alterada. Outras sessões abertas foram encerradas.');
        return $this->redirect('/conta/seguranca');
    }

    public function mfaStart(Request $r): Response
    {
        Session::set('mfa_setup', Totp::generateSecret());
        return $this->redirect('/conta/seguranca');
    }

    public function mfaEnable(Request $r): Response
    {
        $secret = Session::get('mfa_setup');
        if (!is_string($secret)) {
            throw new BusinessException('Comece a configuração de novo.');
        }
        if (!Totp::verify($secret, $r->str('code'))) {
            throw new \App\Core\ValidationException(['code' => 'Código inválido. Digite os 6 números que aparecem no aplicativo.']);
        }
        $this->db()->execute('UPDATE users SET mfa_secret = ?, mfa_enabled = 1, updated_at = NOW() WHERE id = ?', [Crypto::encrypt($secret), Auth::id()]);
        Session::remove('mfa_setup');
        Audit::log('system', 'auth.mfa_enabled', null, Auth::id());
        $this->flash('Verificação em duas etapas ativada.');
        return $this->redirect('/conta/seguranca');
    }

    public function mfaDisable(Request $r): Response
    {
        $user = Auth::user();
        if (!password_verify((string) ($r->post['current_password'] ?? ''), $user['password_hash'])) {
            throw new \App\Core\ValidationException(['current_password_mfa' => 'Senha atual incorreta.']);
        }
        if ($user['platform_role'] && \App\Core\Env::bool('PLATFORM_REQUIRE_MFA', true)) {
            throw new BusinessException('Contas da plataforma precisam manter a verificação em duas etapas.');
        }
        $this->db()->execute('UPDATE users SET mfa_secret = NULL, mfa_enabled = 0 WHERE id = ?', [$user['id']]);
        Audit::log('system', 'auth.mfa_disabled', null, (int) $user['id']);
        $this->flash('Verificação em duas etapas desativada.', 'info');
        return $this->redirect('/conta/seguranca');
    }

    /** Troca de loja ativa: só para lojas em que o usuário tem vínculo ativo. */
    public function switchTenant(Request $r): Response
    {
        $tid = $r->int('tenant_id');
        $ok = $this->db()->value("SELECT 1 FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND status = 'active'", [Auth::id(), $tid]);
        if (!$ok) {
            throw HttpException::forbidden();
        }
        Session::set('tenant_id', $tid);
        return $this->redirect('/painel');
    }
}
