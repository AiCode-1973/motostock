<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PurchaseService;

final class PurchaseController extends PanelController
{
    private function service(): PurchaseService
    {
        return new PurchaseService($this->db(), $this->tid(), $this->uid());
    }

    public function index(Request $r): Response
    {
        $status = $r->str('situacao');
        $params = [$this->tid()];
        $where = 'po.tenant_id = ?';
        if (isset(\App\Support\Labels::MAP['purchase'][$status])) {
            $where .= ' AND po.status = ?';
            $params[] = $status;
        }
        $items = $this->db()->fetchAll(
            "SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.tenant_id = po.tenant_id AND s.id = po.supplier_id
             WHERE {$where} ORDER BY po.id DESC LIMIT 200",
            $params
        );
        return $this->view('panel/purchases/index', ['title' => 'Compras', 'items' => $items]);
    }

    public function create(Request $r): Response
    {
        return $this->view('panel/purchases/create', [
            'title' => 'Nova compra',
            'suppliers' => $this->db()->fetchAll('SELECT id, name FROM suppliers WHERE tenant_id = ? AND active = 1 ORDER BY name', [$this->tid()]),
            'products' => $this->db()->fetchAll('SELECT id, sku, name, cost_cents FROM products WHERE tenant_id = ? ORDER BY name LIMIT 2000', [$this->tid()]),
            'preselect' => $r->int('fornecedor'),
        ]);
    }

    public function store(Request $r): Response
    {
        $items = is_array($r->post['items'] ?? null) ? $r->post['items'] : [];
        $id = $this->service()->create($r->int('supplier_id'), $items, $r->str('expected_at') ?: null, $r->str('notes') ?: null);
        $this->flash('Compra criada como rascunho. Revise e envie ao fornecedor.');
        return $this->redirect('/painel/compras/' . $id);
    }

    public function show(Request $r, int $id): Response
    {
        $po = $this->db()->fetch('SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.tenant_id = po.tenant_id AND s.id = po.supplier_id WHERE po.tenant_id = ? AND po.id = ?', [$this->tid(), $id])
            ?? throw HttpException::notFound('Compra não encontrada.');
        return $this->view('panel/purchases/show', ['title' => 'Compra nº ' . $po['number'], 'po' => $po, 'items' => $this->service()->items($id)]);
    }

    public function markOrdered(Request $r, int $id): Response
    {
        $this->service()->markOrdered($id);
        $this->flash('Compra marcada como enviada ao fornecedor.');
        return $this->redirect('/painel/compras/' . $id);
    }

    public function receive(Request $r, int $id): Response
    {
        $received = array_map('intval', is_array($r->post['received'] ?? null) ? $r->post['received'] : []);
        $this->service()->receive($id, $received);
        $this->flash('Recebimento registrado e estoque atualizado.');
        return $this->redirect('/painel/compras/' . $id);
    }

    public function cancel(Request $r, int $id): Response
    {
        $this->service()->cancel($id);
        $this->flash('Compra cancelada.', 'info');
        return $this->redirect('/painel/compras/' . $id);
    }
}
