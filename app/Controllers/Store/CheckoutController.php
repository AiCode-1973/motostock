<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\CustomerAuth;
use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Url;
use App\Core\Validator;
use App\Core\ValidationException;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\Cart;

final class CheckoutController extends StoreController
{
    public function form(Request $r): Response
    {
        $cart = Cart::details();
        if (!$cart['lines']) {
            $this->flash('Seu carrinho está vazio.', 'info');
            return $this->toStore('/carrinho');
        }
        $methods = $this->db()->fetchAll('SELECT * FROM shipping_methods WHERE tenant_id = ? AND active = 1 ORDER BY kind DESC, price_cents', [Tenant::id()]);
        foreach ($methods as &$m) {
            $m['cost'] = OrderService::shippingCost($m, $cart['subtotal']);
            $m['days'] = OrderService::daysLabel($m);
        }
        return $this->view('store/checkout', [
            'title' => 'Finalizar compra',
            'cart' => $cart,
            'methods' => $methods,
            'online' => (new PaymentService($this->db(), Tenant::current()))->onlineAvailable(),
            'needsFitment' => (bool) array_filter($cart['lines'], fn($l) => (int) $l['fitments'] > 0),
        ]);
    }

    public function place(Request $r): Response
    {
        RateLimiter::guard($this->db(), 'checkout', Tenant::id() . '|' . $r->ip, 10, 600);
        $cart = Cart::details();
        if (!$cart['lines']) {
            throw new BusinessException('Seu carrinho está vazio.');
        }
        if ($cart['issues']) {
            throw new BusinessException('O carrinho mudou: ' . implode(' ', $cart['issues']) . ' Revise antes de concluir.');
        }
        $d = Validator::check($r->post, [
            'name' => 'required|max:120', 'email' => 'required|email|max:160', 'phone' => 'required|phone',
            'shipping_method_id' => 'required|int', 'zip' => 'cep', 'street' => 'max:160', 'number' => 'max:20',
            'complement' => 'max:80', 'district' => 'max:80', 'city' => 'max:80', 'state' => 'uf',
            'notes' => 'max:500', 'accept_privacy' => 'bool', 'accept_fitment' => 'bool', 'marketing_opt_in' => 'bool',
            'payment' => 'required|in:online,offline',
        ], ['name' => 'Nome', 'email' => 'E-mail', 'phone' => 'Telefone', 'shipping_method_id' => 'Entrega', 'zip' => 'CEP', 'street' => 'Rua', 'number' => 'Número', 'city' => 'Cidade', 'state' => 'UF', 'payment' => 'Pagamento']);
        if (!$d['accept_privacy']) {
            throw new ValidationException(['accept_privacy' => 'Leia e aceite a política de privacidade e as condições de troca.']);
        }
        if (array_filter($cart['lines'], fn($l) => (int) $l['fitments'] > 0) && !$d['accept_fitment']) {
            throw new ValidationException(['accept_fitment' => 'Confirme que conferiu a compatibilidade das peças com a sua moto.']);
        }
        $payments = new PaymentService($this->db(), Tenant::current());
        if ($d['payment'] === 'online' && !$payments->onlineAvailable()) {
            throw new BusinessException('Pagamento online indisponível. Escolha combinar o pagamento com a loja.');
        }
        $version = (int) ($this->settings()['policy_version'] ?? 1);
        $customer = CustomerAuth::customer();
        $customerService = new CustomerService($this->db(), Tenant::id());
        $customerId = $customer ? (int) $customer['id'] : $customerService->upsertGuest(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone']], $version);

        $order = (new OrderService($this->db(), Tenant::id(), null, 'customer'))->create([
            'channel' => 'store',
            'items' => array_map(fn($l) => ['product_id' => $l['id'], 'quantity' => $l['qty'], 'fitment_note' => $l['fitment']], $cart['lines']),
            'customer_id' => $customerId,
            'shipping_method_id' => $d['shipping_method_id'],
            'address' => ['zip' => $d['zip'], 'street' => $d['street'], 'number' => $d['number'], 'complement' => $d['complement'], 'district' => $d['district'], 'city' => $d['city'], 'state' => $d['state']],
            'payment_method' => $d['payment'],
            'notes' => $d['notes'],
            'privacy_version' => $version,
        ]);
        // Contato do pedido = o informado agora (cliente com conta pode ter outro telefone).
        $this->db()->execute('UPDATE orders SET customer_name = ?, customer_email = ?, customer_phone = ? WHERE tenant_id = ? AND id = ?', [$d['name'], $d['email'], $d['phone'], Tenant::id(), $order['id']]);
        Cart::clear();
        Audit::log('store', 'order.placed', Tenant::id(), null, 'order', $order['id'], ['number' => $order['number']]);

        $trackPath = '/pedido/' . $order['number'] . '/' . $order['token'];
        $trackUrl = $this->absoluteStoreUrl($trackPath);
        $store = Tenant::current()['trade_name'];
        Mailer::send($d['email'], "{$store}: pedido nº {$order['number']} recebido",
            "Olá, {$d['name']}.\n\nRecebemos seu pedido nº {$order['number']} no valor de " . money($order['total_cents']) . ".\n"
            . "Acompanhe por aqui: {$trackUrl}\n\nGuarde este link: ele dá acesso ao seu pedido.");

        if ($d['payment'] === 'online') {
            try {
                return Response::redirect($payments->startOnline($order['id'], $trackUrl));
            } catch (\Throwable $e) {
                error_log('[MotoStock checkout] ' . $e->getMessage());
                $this->flash('Pedido criado, mas o pagamento online não abriu agora. Tente de novo abaixo ou fale com a loja.', 'error');
            }
        } else {
            $this->flash('Pedido recebido! A loja vai entrar em contato para combinar o pagamento.');
        }
        return $this->toStore($trackPath);
    }

    public function track(Request $r, string $number, string $token): Response
    {
        $orders = new OrderService($this->db(), Tenant::id());
        $order = ctype_digit($number) ? $orders->findByNumberAndToken((int) $number, $token) : null;
        if (!$order) {
            throw HttpException::notFound('Pedido não encontrado. Confira o link recebido por e-mail.');
        }
        $history = array_values(array_filter($orders->historyOf((int) $order['id']), fn($h) => $h['from_status'] !== $h['to_status'] || $h['actor'] !== 'gateway'));
        return $this->view('store/order', [
            'title' => 'Pedido nº ' . $order['number'],
            'order' => $order,
            'items' => $orders->items((int) $order['id']),
            'history' => $history,
            'token' => $token,
            'online' => (new PaymentService($this->db(), Tenant::current()))->onlineAvailable(),
        ]);
    }

    public function pay(Request $r, string $number, string $token): Response
    {
        $orders = new OrderService($this->db(), Tenant::id());
        $order = ctype_digit($number) ? $orders->findByNumberAndToken((int) $number, $token) : null;
        if (!$order) {
            throw HttpException::notFound();
        }
        $trackUrl = $this->absoluteStoreUrl('/pedido/' . $number . '/' . $token);
        return Response::redirect((new PaymentService($this->db(), Tenant::current()))->startOnline((int) $order['id'], $trackUrl));
    }

    private function absoluteStoreUrl(string $path): string
    {
        $tenant = Tenant::current();
        return rtrim(Url::storefrontFor($tenant['slug']), '/') . $path;
    }
}
