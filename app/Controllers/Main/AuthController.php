<?php
declare(strict_types=1);

namespace App\Controllers\Main;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Crypto;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Totp;
use App\Core\Url;
use App\Services\UserService;

final class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900; // 15 minutos

    public function loginForm(Request $r): Response
    {
        return $this->view('auth/login', ['title' => 'Entrar']);
    }

    public function login(Request $r): Response
    {
        $email = mb_strtolower($r->str('email'));
        $db = $this->db();
        // Dois limites: por IP+e-mail (alvo) e por IP (varredura de vários e-mails).
        if (RateLimiter::tooMany($db, 'login', $r->ip . '|' . $email, self::MAX_ATTEMPTS, self::WINDOW)
            || RateLimiter::tooMany($db, 'login-ip', $r->ip, 30, self::WINDOW)) {
            Audit::log('system', 'auth.login_blocked', null, null, null, null, ['ip' => $r->ip]);
            throw new HttpException(429, 'Muitas tentativas de login. Aguarde 15 minutos ou redefina sua senha.');
        }
        $user = Auth::verifyCredentials($email, (string) ($r->post['password'] ?? ''));
        if (!$user) {
            RateLimiter::hit($db, 'login', $r->ip . '|' . $email);
            RateLimiter::hit($db, 'login-ip', $r->ip);
            Audit::log('system', 'auth.login_failed', null, null, null, null, ['email_hash' => substr(hash('sha256', $email), 0, 16)]);
            Session::flashInput(['email' => $email], ['email' => 'E-mail ou senha incorretos.']);
            return $this->redirect('/entrar');
        }
        RateLimiter::clear($db, 'login', $r->ip . '|' . $email);
        if ($user['mfa_enabled']) {
            Session::regenerate();
            Session::set('mfa_pending', ['id' => (int) $user['id'], 'at' => Clock::ts()]);
            return $this->redirect('/entrar/verificacao');
        }
        return $this->finishLogin($user);
    }

    public function mfaForm(Request $r): Response
    {
        if (!Session::get('mfa_pending')) {
            return $this->redirect('/entrar');
        }
        return $this->view('auth/mfa', ['title' => 'Verificação em duas etapas']);
    }

    public function mfaVerify(Request $r): Response
    {
        $pending = Session::get('mfa_pending');
        if (!is_array($pending) || Clock::ts() - $pending['at'] > 300) {
            Session::remove('mfa_pending');
            $this->flash('A verificação expirou. Entre de novo.', 'error');
            return $this->redirect('/entrar');
        }
        RateLimiter::guard($this->db(), 'mfa', (string) $pending['id'], 6, self::WINDOW);
        $user = $this->db()->fetch("SELECT * FROM users WHERE id = ? AND status = 'active'", [$pending['id']]);
        $secret = $user ? Crypto::decrypt($user['mfa_secret']) : null;
        if (!$secret || !Totp::verify($secret, $r->str('code'))) {
            Session::flashInput([], ['code' => 'Código inválido. Confira o horário do celular e tente de novo.']);
            return $this->redirect('/entrar/verificacao');
        }
        RateLimiter::clear($this->db(), 'mfa', (string) $pending['id']);
        return $this->finishLogin($user);
    }

    private function finishLogin(array $user): Response
    {
        $intended = Session::get('intended');
        Auth::login($user);
        Audit::log('system', 'auth.login', null, (int) $user['id']);
        if (is_string($intended) && str_starts_with($intended, '/painel')) {
            return $this->redirect($intended);
        }
        $hasStore = $this->db()->value("SELECT 1 FROM tenant_users WHERE user_id = ? AND status = 'active'", [$user['id']]);
        return $this->redirect($user['platform_role'] && !$hasStore ? '/plataforma' : '/painel');
    }

    public function logout(Request $r): Response
    {
        $id = Auth::id();
        Auth::logout();
        if ($id) {
            Audit::log('system', 'auth.logout', null, $id);
        }
        return $this->redirect('/entrar');
    }

    public function forgotForm(Request $r): Response
    {
        return $this->view('auth/forgot', ['title' => 'Recuperar senha']);
    }

    public function forgot(Request $r): Response
    {
        RateLimiter::guard($this->db(), 'forgot', $r->ip, 5, 3600);
        (new UserService($this->db()))->requestReset($r->str('email'));
        $this->flash('Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha. Ele vale por 1 hora.', 'info');
        return $this->redirect('/entrar');
    }

    public function resetForm(Request $r, string $token): Response
    {
        $valid = (new UserService($this->db()))->findValidReset($token) !== null;
        return $this->view('auth/reset', ['title' => 'Definir nova senha', 'token' => $token, 'valid' => $valid]);
    }

    public function reset(Request $r, string $token): Response
    {
        RateLimiter::guard($this->db(), 'reset', $r->ip, 10, 3600);
        (new UserService($this->db()))->resetPassword($token, (string) ($r->post['password'] ?? ''), (string) ($r->post['password_confirmation'] ?? ''));
        $this->flash('Senha definida. Entre com a nova senha.');
        return Response::redirect(Url::to('/entrar'));
    }
}
