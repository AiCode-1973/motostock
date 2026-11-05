<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\TenantRepository;

final class SupplierController extends PanelController
{
    private const RULES = [
        'name' => 'required|max:160', 'document' => 'cpf_cnpj', 'contact_name' => 'max:120',
        'email' => 'email|max:160', 'phone' => 'phone', 'notes' => 'max:2000', 'active' => 'bool',
    ];
    private const LABELS = ['name' => 'Razão social / nome', 'document' => 'CNPJ/CPF', 'contact_name' => 'Contato', 'email' => 'E-mail', 'phone' => 'Telefone', 'notes' => 'Observações'];

    private function repo(): TenantRepository
    {
        return new TenantRepository($this->db(), $this->tid(), 'suppliers', array_keys(self::RULES));
    }

    public function index(Request $r): Response
    {
        $q = $r->str('q');
        $where = $q !== '' ? '(name LIKE ? OR contact_name LIKE ? OR document LIKE ?)' : '1=1';
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $items = $this->repo()->all('active DESC, name', $where, $q !== '' ? [$like, $like, $like] : []);
        return $this->view('panel/suppliers/index', ['title' => 'Fornecedores', 'items' => $items]);
    }

    public function create(Request $r): Response
    {
        return $this->view('panel/suppliers/form', ['title' => 'Novo fornecedor', 'supplier' => null, 'purchases' => [], 'products' => []]);
    }

    public function store(Request $r): Response
    {
        $d = Validator::check($r->post + ['active' => '1'], self::RULES, self::LABELS);
        $id = $this->repo()->create($d);
        Audit::panel('supplier.created', 'supplier', $id);
        $this->flash('Fornecedor cadastrado.');
        return $this->redirect('/painel/fornecedores/' . $id);
    }

    public function show(Request $r, int $id): Response
    {
        $supplier = $this->repo()->findOrFail($id);
        $purchases = $this->db()->fetchAll('SELECT * FROM purchase_orders WHERE tenant_id = ? AND supplier_id = ? ORDER BY id DESC LIMIT 50', [$this->tid(), $id]);
        $products = $this->db()->fetchAll(
            'SELECT p.sku, p.name, SUM(i.quantity_received) AS qty, MAX(i.unit_cost_cents) AS last_cost
             FROM purchase_order_items i JOIN purchase_orders po ON po.tenant_id = i.tenant_id AND po.id = i.purchase_order_id
             JOIN products p ON p.tenant_id = i.tenant_id AND p.id = i.product_id
             WHERE i.tenant_id = ? AND po.supplier_id = ? GROUP BY p.id, p.sku, p.name ORDER BY qty DESC LIMIT 50',
            [$this->tid(), $id]
        );
        return $this->view('panel/suppliers/form', ['title' => $supplier['name'], 'supplier' => $supplier, 'purchases' => $purchases, 'products' => $products]);
    }

    public function update(Request $r, int $id): Response
    {
        $d = Validator::check($r->post, self::RULES, self::LABELS);
        $this->repo()->update($id, $d);
        Audit::panel('supplier.updated', 'supplier', $id);
        $this->flash('Fornecedor atualizado.');
        return $this->redirect('/painel/fornecedores/' . $id);
    }
}
