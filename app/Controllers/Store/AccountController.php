<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\CustomerAuth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Services\CustomerService;

final class AccountController extends StoreController
{
    public function loginForm(Request $r): Response
    {
        return $this->view('store/login', ['title' => 'Entrar na loja']);
    }

    public function login(Request $r): Response
    {
        $key = Tenant::id() . '|' . $r->ip . '|' . mb_strtolower($r->str('email'));
        RateLimiter::guard($this->db(), 'store-login', $key, 8, 900);
        $c = (new CustomerService($this->db(), Tenant::id()))->verifyLogin($r->str('email'), (string) ($r->post['password'] ?? ''));
        if (!$c) {
            Session::flashInput(['email' => $r->str('email')], ['email' => 'E-mail ou senha incorretos.']);
            return $this->toStore('/conta/entrar');
        }
        RateLimiter::clear($this->db(), 'store-login', $key);
        CustomerAuth::login((int) $c['id']);
        return $this->toStore('/conta/pedidos');
    }

    public function registerForm(Request $r): Response
    {
        return $this->view('store/register', ['title' => 'Criar conta']);
    }

    public function register(Request $r): Response
    {
        RateLimiter::guard($this->db(), 'store-register', Tenant::id() . '|' . $r->ip, 5, 3600);
        $id = (new CustomerService($this->db(), Tenant::id()))->register($r->post, (int) ($this->settings()['policy_version'] ?? 1));
        CustomerAuth::login($id);
        $this->flash('Conta criada.');
        return $this->toStore('/conta/pedidos');
    }

    public function logout(Request $r): Response
    {
        CustomerAuth::logout();
        return $this->toStore('/');
    }

    public function orders(Request $r): Response
    {
        $c = CustomerAuth::customer();
        if (!$c) {
            return $this->toStore('/conta/entrar');
        }
        $orders = $this->db()->fetchAll('SELECT * FROM orders WHERE tenant_id = ? AND customer_id = ? ORDER BY id DESC LIMIT 50', [Tenant::id(), $c['id']]);
        return $this->view('store/account', ['title' => 'Meus pedidos', 'orders' => $orders]);
    }
}
