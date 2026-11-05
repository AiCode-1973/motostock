<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProductRepository;
use App\Services\StockService;

final class StockController extends PanelController
{
    public function index(Request $r): Response
    {
        $result = (new ProductRepository($this->db(), $this->tid()))->search([
            'q' => $r->str('q'),
            'low' => $r->str('baixo') === '1',
            'active' => 1,
        ], $r->int('pagina', 1), 30, 'stock');
        return $this->view('panel/stock/index', ['title' => 'Estoque', 'result' => $result]);
    }

    public function form(Request $r): Response
    {
        $product = $r->int('produto') ? (new ProductRepository($this->db(), $this->tid()))->find($r->int('produto')) : null;
        $products = $this->db()->fetchAll('SELECT id, sku, name, stock_qty, reserved_qty FROM products WHERE tenant_id = ? AND active = 1 ORDER BY name LIMIT 2000', [$this->tid()]);
        return $this->view('panel/stock/move', ['title' => 'Movimentar estoque', 'product' => $product, 'products' => $products]);
    }

    public function move(Request $r): Response
    {
        $pid = $r->int('product_id');
        $qty = $r->int('quantity');
        $reason = $r->str('reason');
        $type = $r->str('type');
        $stock = new StockService($this->db(), $this->tid(), $this->uid());
        match ($type) {
            'entry' => $stock->entry($pid, $qty, $reason),
            'exit' => $stock->exit($pid, $qty, $reason),
            'adjust' => $stock->adjust($pid, $qty, $reason),
            'return' => $stock->returnToStock($pid, $qty, $reason),
            default => throw new BusinessException('Escolha o tipo de movimentação.'),
        };
        Audit::panel('stock.' . $type, 'product', $pid, ['qty' => $qty]);
        $this->flash('Movimentação registrada.');
        return $this->redirect('/painel/estoque/historico', ['produto' => $pid]);
    }

    public function history(Request $r): Response
    {
        $filters = ['product_id' => $r->int('produto') ?: null, 'type' => $r->str('tipo') ?: null];
        $product = $filters['product_id'] ? (new ProductRepository($this->db(), $this->tid()))->findOrFail($filters['product_id']) : null;
        $result = (new StockService($this->db(), $this->tid()))->history($filters, $r->int('pagina', 1));
        return $this->view('panel/stock/history', ['title' => 'Histórico de estoque', 'result' => $result, 'product' => $product]);
    }
}
