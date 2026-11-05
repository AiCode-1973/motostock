<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Repositories\ProductRepository;
use App\Services\ProductService;

final class ProductController extends PanelController
{
    private function repo(): ProductRepository
    {
        return new ProductRepository($this->db(), $this->tid());
    }

    private function options(): array
    {
        return [
            'categories' => $this->db()->fetchAll('SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name', [$this->tid()]),
            'brands' => $this->db()->fetchAll('SELECT id, name FROM brands WHERE tenant_id = ? ORDER BY name', [$this->tid()]),
        ];
    }

    public function index(Request $r): Response
    {
        $filters = [
            'q' => $r->str('q'),
            'category_id' => $r->int('categoria') ?: null,
            'brand_id' => $r->int('marca') ?: null,
            'active' => $r->str('situacao') === '' ? null : ($r->str('situacao') === 'ativos' ? 1 : 0),
            'low' => $r->str('baixo') === '1',
        ];
        $result = $this->repo()->search($filters, $r->int('pagina', 1), 25, $r->str('ordem', 'name'));
        return $this->view('panel/products/index', ['title' => 'Produtos', 'result' => $result] + $this->options());
    }

    public function create(Request $r): Response
    {
        return $this->view('panel/products/form', ['title' => 'Novo produto', 'product' => null] + $this->options());
    }

    public function store(Request $r): Response
    {
        $initial = max(0, $r->int('initial_stock'));
        $id = (new ProductService($this->db(), $this->tid(), $this->uid()))->create($r->post, $initial);
        $this->flash('Produto cadastrado. Adicione fotos e compatibilidade abaixo.');
        return $this->redirect('/painel/produtos/' . $id);
    }

    public function edit(Request $r, int $id): Response
    {
        $repo = $this->repo();
        $product = $repo->findOrFail($id);
        return $this->view('panel/products/form', [
            'title' => $product['name'],
            'product' => $product,
            'images' => $repo->images($id),
            'fitments' => $repo->fitments($id),
            'readonly' => !Tenant::can('products.manage'),
        ] + $this->options());
    }

    public function update(Request $r, int $id): Response
    {
        (new ProductService($this->db(), $this->tid(), $this->uid()))->update($id, $r->post);
        $this->flash('Produto atualizado.');
        return $this->redirect('/painel/produtos/' . $id);
    }

    public function uploadImage(Request $r, int $id): Response
    {
        (new ProductService($this->db(), $this->tid(), $this->uid()))->addImage($id, $r->files['image'] ?? [], Tenant::current()['uuid']);
        $this->flash('Imagem enviada.');
        return $this->redirect('/painel/produtos/' . $id);
    }

    public function deleteImage(Request $r, int $id, int $imageId): Response
    {
        (new ProductService($this->db(), $this->tid(), $this->uid()))->deleteImage($id, $imageId, Tenant::current()['uuid']);
        Audit::panel('product.image_deleted', 'product', $id);
        $this->flash('Imagem removida.');
        return $this->redirect('/painel/produtos/' . $id);
    }

    public function addFitment(Request $r, int $id): Response
    {
        (new ProductService($this->db(), $this->tid(), $this->uid()))->addFitment($id, $r->post);
        $this->flash('Compatibilidade adicionada.');
        return $this->redirect('/painel/produtos/' . $id . '#compatibilidade');
    }

    public function removeFitment(Request $r, int $id, int $fitmentId): Response
    {
        (new ProductService($this->db(), $this->tid(), $this->uid()))->removeFitment($id, $fitmentId);
        $this->flash('Compatibilidade removida.');
        return $this->redirect('/painel/produtos/' . $id . '#compatibilidade');
    }
}
