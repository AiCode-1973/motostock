<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\ValidationException;

/** Categorias e marcas de produtos. */
final class CatalogController extends PanelController
{
    public function index(Request $r): Response
    {
        $t = $this->tid();
        return $this->view('panel/catalog', [
            'title' => 'Categorias e marcas',
            'categories' => $this->db()->fetchAll('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.tenant_id = c.tenant_id AND p.category_id = c.id) AS n FROM categories c WHERE c.tenant_id = ? ORDER BY c.name', [$t]),
            'brands' => $this->db()->fetchAll('SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.tenant_id = b.tenant_id AND p.brand_id = b.id) AS n FROM brands b WHERE b.tenant_id = ? ORDER BY b.name', [$t]),
        ]);
    }

    public function storeCategory(Request $r): Response
    {
        $d = Validator::check($r->post, ['category_name' => 'required|max:80'], ['category_name' => 'Nome da categoria']);
        $slug = slugify($d['category_name']);
        if ($this->db()->value('SELECT 1 FROM categories WHERE tenant_id = ? AND slug = ?', [$this->tid(), $slug])) {
            throw new ValidationException(['category_name' => 'Já existe uma categoria com este nome.']);
        }
        $id = $this->db()->insert('categories', ['tenant_id' => $this->tid(), 'name' => $d['category_name'], 'slug' => $slug, 'created_at' => Clock::now()]);
        Audit::panel('category.created', 'category', $id);
        $this->flash('Categoria criada.');
        return $this->redirect('/painel/catalogo');
    }

    public function deleteCategory(Request $r, int $id): Response
    {
        if ($this->db()->value('SELECT 1 FROM products WHERE tenant_id = ? AND category_id = ? LIMIT 1', [$this->tid(), $id])) {
            throw new BusinessException('Há produtos nesta categoria. Mova-os antes de excluir.');
        }
        if ($this->db()->execute('DELETE FROM categories WHERE tenant_id = ? AND id = ?', [$this->tid(), $id]) === 0) {
            throw \App\Core\HttpException::notFound();
        }
        Audit::panel('category.deleted', 'category', $id);
        $this->flash('Categoria excluída.');
        return $this->redirect('/painel/catalogo');
    }

    public function storeBrand(Request $r): Response
    {
        $d = Validator::check($r->post, ['brand_name' => 'required|max:80'], ['brand_name' => 'Nome da marca']);
        if ($this->db()->value('SELECT 1 FROM brands WHERE tenant_id = ? AND name = ?', [$this->tid(), $d['brand_name']])) {
            throw new ValidationException(['brand_name' => 'Esta marca já existe.']);
        }
        $id = $this->db()->insert('brands', ['tenant_id' => $this->tid(), 'name' => $d['brand_name'], 'created_at' => Clock::now()]);
        Audit::panel('brand.created', 'brand', $id);
        $this->flash('Marca criada.');
        return $this->redirect('/painel/catalogo');
    }

    public function deleteBrand(Request $r, int $id): Response
    {
        if ($this->db()->value('SELECT 1 FROM products WHERE tenant_id = ? AND brand_id = ? LIMIT 1', [$this->tid(), $id])) {
            throw new BusinessException('Há produtos desta marca. Altere-os antes de excluir.');
        }
        if ($this->db()->execute('DELETE FROM brands WHERE tenant_id = ? AND id = ?', [$this->tid(), $id]) === 0) {
            throw \App\Core\HttpException::notFound();
        }
        Audit::panel('brand.deleted', 'brand', $id);
        $this->flash('Marca excluída.');
        return $this->redirect('/painel/catalogo');
    }
}
