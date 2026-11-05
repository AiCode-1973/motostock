<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Base de acesso a dados de uma loja. O tenant_id vem do construtor (definido pelo servidor)
 * e entra em TODA consulta. Registro de outra loja = "não encontrado".
 */
class TenantRepository
{
    public function __construct(
        protected Database $db,
        protected int $tenantId,
        protected string $table = '',
        protected array $columns = [],
        protected bool $hasUpdatedAt = true,
    ) {
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function find(int $id, bool $forUpdate = false): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE tenant_id = ? AND id = ?" . ($forUpdate ? ' FOR UPDATE' : ''),
            [$this->tenantId, $id]
        );
    }

    public function findOrFail(int $id, bool $forUpdate = false): array
    {
        return $this->find($id, $forUpdate) ?? throw HttpException::notFound('Registro não encontrado.');
    }

    public function create(array $data): int
    {
        $row = array_intersect_key($data, array_flip($this->columns));
        $row['tenant_id'] = $this->tenantId;
        $row['created_at'] = Clock::now();
        if ($this->hasUpdatedAt) {
            $row['updated_at'] = Clock::now();
        }
        return $this->db->insert($this->table, $row);
    }

    public function update(int $id, array $data): void
    {
        $row = array_intersect_key($data, array_flip($this->columns));
        if ($this->hasUpdatedAt) {
            $row['updated_at'] = Clock::now();
        }
        if (!$row) {
            return;
        }
        $this->findOrFail($id);
        $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($row)));
        $this->db->execute(
            "UPDATE {$this->table} SET {$sets} WHERE tenant_id = ? AND id = ?",
            [...array_values($row), $this->tenantId, $id]
        );
    }

    public function delete(int $id): void
    {
        $n = $this->db->execute("DELETE FROM {$this->table} WHERE tenant_id = ? AND id = ?", [$this->tenantId, $id]);
        if ($n === 0) {
            throw HttpException::notFound('Registro não encontrado.');
        }
    }

    /** $orderBy vem sempre do código, nunca do usuário. */
    public function all(string $orderBy = 'id DESC', string $where = '1=1', array $params = []): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE tenant_id = ? AND ({$where}) ORDER BY {$orderBy}",
            [$this->tenantId, ...$params]
        );
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = ? AND ({$where})", [$this->tenantId, ...$params]);
    }

    /**
     * Paginação. $from deve conter "WHERE x.tenant_id = ?" como primeiro parâmetro.
     * @return array{items: array, total: int, page: int, pages: int, per_page: int}
     */
    protected function paginate(string $select, string $from, array $params, string $order, int $page, int $perPage = 20): array
    {
        $total = (int) $this->db->value("SELECT COUNT(*) {$from}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = $this->db->fetchAll(
            "SELECT {$select} {$from} ORDER BY {$order} LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    /** Escapa curingas de LIKE vindos do usuário. */
    protected static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
