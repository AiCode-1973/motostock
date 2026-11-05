<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CustomerService;

final class CustomerController extends PanelController
{
    private function service(): CustomerService
    {
        return new CustomerService($this->db(), $this->tid(), $this->uid());
    }

    public function index(Request $r): Response
    {
        return $this->view('panel/customers/index', ['title' => 'Clientes', 'result' => $this->service()->search($r->str('q'), $r->int('pagina', 1))]);
    }

    public function create(Request $r): Response
    {
        return $this->view('panel/customers/form', ['title' => 'Novo cliente', 'customer' => null, 'orders' => []]);
    }

    public function store(Request $r): Response
    {
        $id = $this->service()->create($r->post);
        $this->flash('Cliente cadastrado.');
        if ($r->str('next') === 'pedido') {
            return $this->redirect('/painel/pedidos/novo', ['cliente' => $id]);
        }
        return $this->redirect('/painel/clientes/' . $id);
    }

    public function show(Request $r, int $id): Response
    {
        $customer = $this->service()->find($id);
        $orders = $this->db()->fetchAll('SELECT id, number, status, total_cents, placed_at, channel FROM orders WHERE tenant_id = ? AND customer_id = ? ORDER BY id DESC LIMIT 100', [$this->tid(), $id]);
        return $this->view('panel/customers/form', ['title' => $customer['name'], 'customer' => $customer, 'orders' => $orders]);
    }

    public function update(Request $r, int $id): Response
    {
        $this->service()->update($id, $r->post);
        $this->flash('Cliente atualizado.');
        return $this->redirect('/painel/clientes/' . $id);
    }

    public function anonymize(Request $r, int $id): Response
    {
        if ($r->str('confirm') !== 'ANONIMIZAR') {
            throw new BusinessException('Digite ANONIMIZAR para confirmar. Esta ação não pode ser desfeita.');
        }
        $this->service()->anonymize($id);
        $this->flash('Dados pessoais do cliente removidos. Os pedidos foram mantidos sem identificação.', 'info');
        return $this->redirect('/painel/clientes/' . $id);
    }
}
