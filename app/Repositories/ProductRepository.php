<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class ProductRepository extends TenantRepository
{
    public const COLUMNS = ['sku', 'barcode', 'name', 'slug', 'description', 'category_id', 'brand_id', 'cost_cents',
        'price_cents', 'min_stock', 'weight_grams', 'active', 'is_demo'];

    public function __construct(Database $db, int $tenantId)
    {
        // stock_qty/reserved_qty ficam de fora: só o StockService altera saldo.
        parent::__construct($db, $tenantId, 'products', self::COLUMNS);
    }

    public function findBySku(string $sku): ?array
    {
        return $this->db->fetch('SELECT * FROM products WHERE tenant_id = ? AND sku = ?', [$this->tenantId, $sku]);
    }

    public function findActiveBySlug(string $slug): ?array
    {
        return $this->db->fetch(
            'SELECT p.*, c.name AS category_name, b.name AS brand_name FROM products p
             LEFT JOIN categories c ON c.tenant_id = p.tenant_id AND c.id = p.category_id
             LEFT JOIN brands b ON b.tenant_id = p.tenant_id AND b.id = p.brand_id
             WHERE p.tenant_id = ? AND p.slug = ? AND p.active = 1',
            [$this->tenantId, $slug]
        );
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return (bool) $this->db->value(
            'SELECT 1 FROM products WHERE tenant_id = ? AND slug = ? AND id <> ?',
            [$this->tenantId, $slug, $exceptId ?? 0]
        );
    }

    /**
     * Busca para painel e loja.
     * Filtros: q, category_id, brand_id, active (0/1), low (estoque baixo), make, model, year, available (só com saldo).
     */
    public function search(array $f, int $page = 1, int $perPage = 20, string $sort = 'name'): array
    {
        $where = ['p.tenant_id = ?'];
        $params = [$this->tenantId];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode = ? OR b.name LIKE ?)';
            $like = self::like($f['q']);
            array_push($params, $like, $like, $f['q'], $like);
        }
        if (!empty($f['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $f['category_id'];
        }
        if (!empty($f['brand_id'])) {
            $where[] = 'p.brand_id = ?';
            $params[] = (int) $f['brand_id'];
        }
        if (isset($f['active']) && $f['active'] !== '' && $f['active'] !== null) {
            $where[] = 'p.active = ?';
            $params[] = (int) $f['active'];
        }
        if (!empty($f['low'])) {
            $where[] = '(p.stock_qty - p.reserved_qty) <= p.min_stock';
        }
        if (!empty($f['available'])) {
            $where[] = '(p.stock_qty - p.reserved_qty) > 0';
        }
        if (($f['make'] ?? '') !== '') {
            $fit = 'EXISTS (SELECT 1 FROM product_fitments pf WHERE pf.tenant_id = p.tenant_id AND pf.product_id = p.id AND pf.make = ?';
            $params[] = $f['make'];
            if (($f['model'] ?? '') !== '') {
                $fit .= ' AND pf.model = ?';
                $params[] = $f['model'];
            }
            if (!empty($f['year'])) {
                $fit .= ' AND ? BETWEEN pf.year_from AND pf.year_to';
                $params[] = (int) $f['year'];
            }
            $where[] = $fit . ')';
        }
        $order = match ($sort) {
            'price_asc' => 'p.price_cents ASC, p.id',
            'price_desc' => 'p.price_cents DESC, p.id',
            'newest' => 'p.id DESC',
            'stock' => '(p.stock_qty - p.reserved_qty) ASC, p.name',
            default => 'p.name ASC, p.id',
        };
        $from = 'FROM products p
                 LEFT JOIN categories c ON c.tenant_id = p.tenant_id AND c.id = p.category_id
                 LEFT JOIN brands b ON b.tenant_id = p.tenant_id AND b.id = p.brand_id
                 WHERE ' . implode(' AND ', $where);
        $select = 'p.*, c.name AS category_name, b.name AS brand_name, (p.stock_qty - p.reserved_qty) AS available,
                   (SELECT i.id FROM product_images i WHERE i.tenant_id = p.tenant_id AND i.product_id = p.id ORDER BY i.position, i.id LIMIT 1) AS image_id';
        return $this->paginate($select, $from, $params, $order, $page, $perPage);
    }

    public function images(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM product_images WHERE tenant_id = ? AND product_id = ? ORDER BY position, id',
            [$this->tenantId, $productId]
        );
    }

    public function fitments(int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM product_fitments WHERE tenant_id = ? AND product_id = ? ORDER BY make, model, year_from',
            [$this->tenantId, $productId]
        );
    }

    /** Marcas e modelos de moto cadastrados (para os filtros da loja). */
    public function fitmentOptions(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT pf.make, pf.model FROM product_fitments pf
             JOIN products p ON p.tenant_id = pf.tenant_id AND p.id = pf.product_id AND p.active = 1
             WHERE pf.tenant_id = ? ORDER BY pf.make, pf.model',
            [$this->tenantId]
        );
    }

    public function lowStock(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT id, sku, name, stock_qty, reserved_qty, min_stock, (stock_qty - reserved_qty) AS available
             FROM products WHERE tenant_id = ? AND active = 1 AND (stock_qty - reserved_qty) <= min_stock
             ORDER BY (stock_qty - reserved_qty) - min_stock ASC, name LIMIT ?',
            [$this->tenantId, $limit]
        );
    }
}
