<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\HttpException;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Services\OrderService;
use App\Services\PaymentService;

final class OrderController extends PanelController
{
    private const SELLER_DISCOUNT_LIMIT = 10; // %

    private function service(): OrderService
    {
        return new OrderService($this->db(), $this->tid(), $this->uid());
    }

    public function index(Request $r): Response
    {
        $where = ['o.tenant_id = ?'];
        $params = [$this->tid()];
        $status = $r->str('situacao');
        if (isset(OrderService::TRANSITIONS[$status])) {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }
        if (in_array($r->str('canal'), ['store', 'manual'], true)) {
            $where[] = 'o.channel = ?';
            $params[] = $r->str('canal');
        }
        if (($q = $r->str('q')) !== '') {
            $where[] = '(o.number = ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, ctype_digit($q) ? (int) $q : 0, $like, $like);
        }
        $from = 'FROM orders o WHERE ' . implode(' AND ', $where);
        $total = (int) $this->db()->value("SELECT COUNT(*) {$from}", $params);
        $pages = max(1, (int) ceil($total / 25));
        $page = min(max(1, $r->int('pagina', 1)), $pages);
        $items = $this->db()->fetchAll("SELECT o.* {$from} ORDER BY o.id DESC LIMIT 25 OFFSET " . (($page - 1) * 25), $params);
        return $this->view('panel/orders/index', ['title' => 'Pedidos', 'result' => ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages]]);
    }

    public function create(Request $r): Response
    {
        $t = $this->tid();
        return $this->view('panel/orders/create', [
            'title' => 'Nova venda no balcão',
            'customers' => $this->db()->fetchAll('SELECT id, name, email FROM customers WHERE tenant_id = ? AND anonymized_at IS NULL ORDER BY name LIMIT 2000', [$t]),
            'products' => $this->db()->fetchAll('SELECT id, sku, name, price_cents, (stock_qty - reserved_qty) AS available FROM products WHERE tenant_id = ? AND active = 1 ORDER BY name LIMIT 3000', [$t]),
            'shipping' => $this->db()->fetchAll('SELECT * FROM shipping_methods WHERE tenant_id = ? AND active = 1 ORDER BY kind DESC, name', [$t]),
            'preselect' => $r->int('cliente'),
            'discountLimit' => Tenant::role() === 'seller' ? self::SELLER_DISCOUNT_LIMIT : null,
        ]);
    }

    public function store(Request $r): Response
    {
        $items = is_array($r->post['items'] ?? null) ? $r->post['items'] : [];
        $discount = $r->str('discount') === '' ? 0 : Money::parse($r->str('discount'));
        if ($discount === null) {
            throw new \App\Core\ValidationException(['discount' => 'Desconto inválido.']);
        }
        $result = $this->service()->create([
            'channel' => 'manual',
            'items' => $items,
            'customer_id' => $r->int('customer_id') ?: null,
            'customer' => ['name' => $r->str('customer_name')],
            'shipping_method_id' => $r->int('shipping_method_id') ?: null,
            'address' => [
                'zip' => preg_replace('/\D/', '', $r->str('zip')), 'street' => $r->str('street'), 'number' => $r->str('number'),
                'complement' => $r->str('complement'), 'district' => $r->str('district'), 'city' => $r->str('city'), 'state' => strtoupper($r->str('state')),
            ],
            'discount_cents' => $discount,
            'discount_limit_percent' => Tenant::role() === 'seller' ? self::SELLER_DISCOUNT_LIMIT : null,
            'notes' => $r->str('notes') ?: null,
        ]);
        Audit::panel('order.created', 'order', $result['id'], ['number' => $result['number'], 'channel' => 'manual']);
        $method = $r->str('pay_now');
        if ($method !== '') {
            (new PaymentService($this->db(), Tenant::current()))->recordManual($result['id'], $method, (int) $this->uid(), null);
            $this->flash("Venda nº {$result['number']} registrada e paga.");
        } else {
            $this->flash("Pedido nº {$result['number']} criado. O estoque está reservado até o pagamento.");
        }
        return $this->redirect('/painel/pedidos/' . $result['id']);
    }

    public function show(Request $r, int $id): Response
    {
        $order = $this->db()->fetch('SELECT o.*, u.name AS operator_name FROM orders o LEFT JOIN users u ON u.id = o.operator_user_id WHERE o.tenant_id = ? AND o.id = ?', [$this->tid(), $id])
            ?? throw HttpException::notFound('Pedido não encontrado.');
        $s = $this->service();
        return $this->view('panel/orders/show', [
            'title' => 'Pedido nº ' . $order['number'],
            'order' => $order,
            'items' => $s->items($id),
            'history' => $s->historyOf($id),
            'payments' => $this->db()->fetchAll('SELECT p.*, u.name AS recorded_by_name FROM payments p LEFT JOIN users u ON u.id = p.recorded_by WHERE p.tenant_id = ? AND p.order_id = ? ORDER BY p.id', [$this->tid(), $id]),
            'next' => OrderService::TRANSITIONS[$order['status']] ?? [],
        ]);
    }

    public function transition(Request $r, int $id): Response
    {
        $to = $r->str('to');
        if (!array_key_exists($to, OrderService::TRANSITIONS)) {
            throw new BusinessException('Situação inválida.');
        }
        if ($to === 'refunded') {
            $this->requirePermission('orders.refund');
        }
        if ($to === 'paid') {
            throw new BusinessException('Para marcar como pago, registre o pagamento (forma e valor) no quadro Pagamentos.');
        }
        $this->service()->transition($id, $to, ['note' => $r->str('note') ?: null, 'restock' => $r->str('restock') === '1']);
        Audit::panel('order.status', 'order', $id, ['to' => $to]);
        $this->flash('Situação do pedido atualizada.');
        return $this->redirect('/painel/pedidos/' . $id);
    }

    public function registerPayment(Request $r, int $id): Response
    {
        (new PaymentService($this->db(), Tenant::current()))->recordManual($id, $r->str('method'), (int) $this->uid(), $r->str('note') ?: null);
        Audit::panel('order.payment_manual', 'order', $id, ['method' => $r->str('method')]);
        $this->flash('Pagamento registrado. O estoque reservado foi baixado.');
        return $this->redirect('/painel/pedidos/' . $id);
    }

    public function tracking(Request $r, int $id): Response
    {
        $code = $r->str('tracking_code');
        if ($code === '' || mb_strlen($code) > 60) {
            throw new \App\Core\ValidationException(['tracking_code' => 'Informe o código de rastreio (até 60 caracteres).']);
        }
        $this->service()->setTracking($id, $code);
        $this->flash('Código de rastreio salvo.');
        return $this->redirect('/painel/pedidos/' . $id);
    }
}
