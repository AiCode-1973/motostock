<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Clock;
use App\Core\Database;
use App\Core\ValidationException;
use App\Support\Csv;

/**
 * Importação/exportação do catálogo em CSV.
 * Colunas: sku;nome;preco;custo;estoque_minimo;codigo_barras;categoria;marca;ativo;descricao
 * - SKU existente → atualiza; novo → cria. Saldo de estoque NÃO é importado (use Estoque › Movimentar),
 *   para que toda mudança de saldo tenha motivo e responsável.
 * - Cada linha é validada isoladamente; erros ficam em import_logs.
 */
final class CatalogImportService
{
    public const HEADER = ['sku', 'nome', 'preco', 'custo', 'estoque_minimo', 'codigo_barras', 'categoria', 'marca', 'ativo', 'descricao'];

    public function __construct(private Database $db, private int $tenantId, private ?int $userId = null)
    {
    }

    public function export(): string
    {
        $rows = $this->db->fetchAll(
            'SELECT p.sku, p.name, p.price_cents, p.cost_cents, p.min_stock, p.barcode, c.name AS cat, b.name AS brand, p.active, p.description, (p.stock_qty - p.reserved_qty) AS available
             FROM products p LEFT JOIN categories c ON c.tenant_id = p.tenant_id AND c.id = p.category_id
             LEFT JOIN brands b ON b.tenant_id = p.tenant_id AND b.id = p.brand_id
             WHERE p.tenant_id = ? ORDER BY p.name',
            [$this->tenantId]
        );
        $out = array_map(fn($r) => [
            $r['sku'], $r['name'], \App\Core\Money::input((int) $r['price_cents']), \App\Core\Money::input((int) $r['cost_cents']),
            $r['min_stock'], $r['barcode'], $r['cat'], $r['brand'], $r['active'] ? 'sim' : 'nao', $r['description'], $r['available'],
        ], $rows);
        return Csv::build([...self::HEADER, 'disponivel (somente leitura)'], $out);
    }

    /** @return array{total: int, ok: int, failed: int, errors: array<int, string>} */
    public function import(string $content, string $fileName): array
    {
        $rows = Csv::parse($content);
        if (!$rows || !isset(reset($rows)['sku'], reset($rows)['nome'], reset($rows)['preco'])) {
            throw new ValidationException(['file' => 'Arquivo sem as colunas obrigatórias sku, nome e preco. Baixe o modelo pela exportação.']);
        }
        $service = new ProductService($this->db, $this->tenantId, $this->userId);
        $repo = new \App\Repositories\ProductRepository($this->db, $this->tenantId);
        $ok = 0;
        $errors = [];
        foreach ($rows as $line => $r) {
            try {
                $this->db->transaction(function () use ($r, $service, $repo) {
                    $input = [
                        'sku' => $r['sku'] ?? '', 'name' => $r['nome'] ?? '', 'price_cents' => $r['preco'] ?? '',
                        'cost_cents' => $r['custo'] ?? '', 'min_stock' => $r['estoque_minimo'] ?? '', 'barcode' => $r['codigo_barras'] ?? '',
                        'description' => $r['descricao'] ?? '', 'active' => in_array(mb_strtolower(trim($r['ativo'] ?? 'sim')), ['sim', '1', 's', 'true', 'ativo'], true) ? '1' : '',
                        'category_id' => $this->lookup('categories', $r['categoria'] ?? ''),
                        'brand_id' => $this->lookup('brands', $r['marca'] ?? ''),
                    ];
                    $existing = $repo->findBySku(trim($input['sku']));
                    $existing ? $service->update((int) $existing['id'], $input) : $service->create($input);
                });
                $ok++;
            } catch (ValidationException $e) {
                $errors[$line] = implode(' ', $e->errors);
            } catch (\App\Core\BusinessException $e) {
                $errors[$line] = $e->getMessage();
                if (str_contains($e->getMessage(), 'Limite do plano')) {
                    break;
                }
            }
        }
        $this->db->insert('import_logs', [
            'tenant_id' => $this->tenantId, 'kind' => 'catalog', 'file_name' => mb_substr($fileName, 0, 160),
            'rows_total' => count($rows), 'rows_ok' => $ok, 'rows_failed' => count($errors),
            'errors' => $errors ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null, 'user_id' => $this->userId, 'created_at' => Clock::now(),
        ]);
        Audit::log('panel', 'catalog.imported', $this->tenantId, $this->userId, null, null, ['ok' => $ok, 'failed' => count($errors)]);
        return ['total' => count($rows), 'ok' => $ok, 'failed' => count($errors), 'errors' => $errors];
    }

    /** Categoria/marca por nome; cria se não existir. */
    private function lookup(string $table, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $id = $this->db->value("SELECT id FROM {$table} WHERE tenant_id = ? AND name = ?", [$this->tenantId, $name]);
        if ($id) {
            return (string) $id;
        }
        $data = ['tenant_id' => $this->tenantId, 'name' => mb_substr($name, 0, 80), 'created_at' => Clock::now()];
        if ($table === 'categories') {
            $data['slug'] = slugify($name) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        return (string) $this->db->insert($table, $data);
    }
}
