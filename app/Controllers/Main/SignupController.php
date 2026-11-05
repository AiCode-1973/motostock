<?php
declare(strict_types=1);

namespace App\Controllers\Main;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TenantService;

final class SignupController extends Controller
{
    public function form(Request $r): Response
    {
        $plans = $this->db()->fetchAll('SELECT * FROM plans WHERE active = 1 AND is_public = 1 ORDER BY sort_order, price_cents');
        return $this->view('main/signup', ['plans' => $plans, 'title' => 'Criar conta da loja', 'selectedPlan' => $r->int('plano')]);
    }

    public function store(Request $r): Response
    {
        RateLimiter::guard($this->db(), 'signup', $r->ip, 5, 3600);
        $result = (new TenantService($this->db()))->signup($r->post);
        $user = $this->db()->fetch('SELECT * FROM users WHERE id = ?', [$result['user_id']]);
        Auth::login($user);
        Session::set('tenant_id', $result['tenant_id']);
        $this->flash('Loja criada! Comece cadastrando seus produtos.');
        return $this->redirect('/painel');
    }
}
