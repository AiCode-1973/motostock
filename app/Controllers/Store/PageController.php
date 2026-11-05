<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\Audit;
use App\Core\Clock;
use App\Core\CustomerAuth;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Validator;

/** Páginas institucionais exigidas no e-commerce (identificação, contato, políticas) e pedidos de titulares. */
final class PageController extends StoreController
{
    private const PAGES = [
        'contato' => 'Contato e atendimento',
        'privacidade' => 'Política de privacidade',
        'trocas-e-devolucoes' => 'Trocas, devoluções e arrependimento',
        'entrega' => 'Entrega',
        'sobre' => 'Sobre a loja',
    ];

    public function show(Request $r, string $page): Response
    {
        if (!isset(self::PAGES[$page])) {
            throw HttpException::notFound();
        }
        $s = $this->settings();
        $body = match ($page) {
            'privacidade' => $s['policy_privacy'] ?? '',
            'trocas-e-devolucoes' => $s['policy_returns'] ?? '',
            'entrega' => $s['policy_shipping'] ?? '',
            'sobre' => $s['about'] ?? '',
            default => '',
        };
        $shipping = $page === 'entrega' ? $this->db()->fetchAll('SELECT * FROM shipping_methods WHERE tenant_id = ? AND active = 1 ORDER BY price_cents', [Tenant::id()]) : [];
        return $this->view('store/page', ['title' => self::PAGES[$page], 'page' => $page, 'body' => $body, 'shipping' => $shipping]);
    }

    public function privacyForm(Request $r): Response
    {
        return $this->view('store/privacy_request', ['title' => 'Solicitar meus dados (LGPD)']);
    }

    public function privacyRequest(Request $r): Response
    {
        RateLimiter::guard($this->db(), 'privacy-request', Tenant::id() . '|' . $r->ip, 5, 3600);
        $d = Validator::check($r->post, ['email' => 'required|email|max:160', 'type' => 'required|in:access,correction,deletion,portability,other', 'message' => 'max:2000'],
            ['email' => 'E-mail', 'type' => 'Tipo de solicitação', 'message' => 'Mensagem']);
        $customerId = $this->db()->value('SELECT id FROM customers WHERE tenant_id = ? AND email = ?', [Tenant::id(), $d['email']]);
        $id = $this->db()->insert('data_requests', [
            'tenant_id' => Tenant::id(), 'customer_id' => $customerId ? (int) $customerId : null, 'email' => $d['email'],
            'type' => $d['type'], 'message' => $d['message'], 'created_at' => Clock::now(),
        ]);
        Audit::log('store', 'privacy.request', Tenant::id(), null, 'data_request', $id, ['type' => $d['type']]);
        $this->flash('Solicitação registrada. A loja vai conferir sua identidade e responder pelo e-mail informado.');
        return $this->toStore('/privacidade/solicitar');
    }
}
