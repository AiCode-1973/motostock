<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\CustomerAuth;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Url;
use App\Core\View;
use App\Support\Cart;

abstract class StoreController extends Controller
{
    protected string $layout = 'layouts/store';

    protected function settings(): array
    {
        static $cache = [];
        return $cache[Tenant::id()] ??= $this->db()->fetch('SELECT * FROM store_settings WHERE tenant_id = ?', [Tenant::id()]) ?? [];
    }

    protected function view(string $template, array $data = [], ?string $layout = null): Response
    {
        View::$shared['store'] = Tenant::current();
        View::$shared['settings'] = $this->settings();
        View::$shared['cartCount'] = Cart::count();
        View::$shared['customer'] = CustomerAuth::customer();
        View::$shared['navCategories'] = $this->db()->fetchAll(
            'SELECT c.id, c.name FROM categories c WHERE c.tenant_id = ? AND c.active = 1
             AND EXISTS (SELECT 1 FROM products p WHERE p.tenant_id = c.tenant_id AND p.category_id = c.id AND p.active = 1) ORDER BY c.name LIMIT 12',
            [Tenant::id()]
        );
        return parent::view($template, $data, $layout);
    }

    protected function toStore(string $path, array $query = []): Response
    {
        return Response::redirect(Url::store($path, $query));
    }
}
