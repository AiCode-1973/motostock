<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Repositories\ProductRepository;

final class CatalogController extends StoreController
{
    public function index(Request $r): Response
    {
        $repo = new ProductRepository($this->db(), Tenant::id());
        $filters = [
            'q' => mb_substr($r->str('q'), 0, 80),
            'category_id' => $r->int('categoria') ?: null,
            'brand_id' => $r->int('marca') ?: null,
            'make' => mb_substr($r->str('moto'), 0, 60),
            'model' => mb_substr($r->str('modelo'), 0, 80),
            'year' => $r->int('ano') ?: null,
            'available' => $r->str('disponivel') === '1',
            'active' => 1,
        ];
        $sort = in_array($r->str('ordem'), ['price_asc', 'price_desc', 'newest'], true) ? $r->str('ordem') : 'name';
        $result = $repo->search($filters, $r->int('pagina', 1), 24, $sort);
        $fit = $repo->fitmentOptions();
        return $this->view('store/catalog', [
            'title' => Tenant::current()['trade_name'],
            'result' => $result,
            'filters' => $filters,
            'sort' => $sort,
            'categories' => $this->db()->fetchAll('SELECT id, name FROM categories WHERE tenant_id = ? AND active = 1 ORDER BY name', [Tenant::id()]),
            'brands' => $this->db()->fetchAll('SELECT DISTINCT b.id, b.name FROM brands b JOIN products p ON p.tenant_id = b.tenant_id AND p.brand_id = b.id AND p.active = 1 WHERE b.tenant_id = ? ORDER BY b.name', [Tenant::id()]),
            'makes' => array_values(array_unique(array_column($fit, 'make'))),
            'models' => array_values(array_filter($fit, fn($f) => $filters['make'] === '' || $f['make'] === $filters['make'])),
        ]);
    }

    public function product(Request $r, string $slug): Response
    {
        $repo = new ProductRepository($this->db(), Tenant::id());
        $p = $repo->findActiveBySlug($slug) ?? throw HttpException::notFound('Produto não encontrado.');
        $shipping = $this->db()->fetchAll('SELECT * FROM shipping_methods WHERE tenant_id = ? AND active = 1 ORDER BY price_cents', [Tenant::id()]);
        return $this->view('store/product', [
            'title' => $p['name'],
            'p' => $p,
            'available' => (int) $p['stock_qty'] - (int) $p['reserved_qty'],
            'images' => $repo->images((int) $p['id']),
            'fitments' => $repo->fitments((int) $p['id']),
            'shipping' => $shipping,
        ]);
    }
}
