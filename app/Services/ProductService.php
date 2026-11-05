<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Validator;
use App\Core\ValidationException;
use App\Repositories\ProductRepository;

final class ProductService
{
    public const RULES = [
        'sku' => 'required|max:60',
        'barcode' => 'max:60',
        'name' => 'required|max:160',
        'description' => 'max:5000',
        'category_id' => 'int',
        'brand_id' => 'int',
        'cost_cents' => 'money',
        'price_cents' => 'required|money|min:1',
        'min_stock' => 'int|min:0',
        'weight_grams' => 'int|min:0',
        'active' => 'bool',
    ];
    public const LABELS = [
        'sku' => 'SKU', 'barcode' => 'Código de barras', 'name' => 'Nome', 'description' => 'Descrição',
        'category_id' => 'Categoria', 'brand_id' => 'Marca', 'cost_cents' => 'Custo', 'price_cents' => 'Preço de venda',
        'min_stock' => 'Estoque mínimo', 'weight_grams' => 'Peso (g)',
    ];

    private ProductRepository $repo;

    public function __construct(private Database $db, private int $tenantId, private ?int $userId = null)
    {
        $this->repo = new ProductRepository($db, $tenantId);
    }

    public function create(array $input, int $initialStock = 0, bool $isDemo = false): int
    {
        $d = $this->validate($input);
        return $this->db->transaction(function () use ($d, $initialStock, $isDemo) {
            (new PlanLimitService($this->db, $this->tenantId))->assertCanAdd('products');
            $d['slug'] = $this->uniqueSlug($d['name'] . '-' . $d['sku']);
            $d['is_demo'] = (int) $isDemo;
            $id = $this->repo->create($d);
            if ($initialStock > 0) {
                (new StockService($this->db, $this->tenantId, $this->userId))->entry($id, $initialStock, 'Saldo inicial do cadastro');
            }
            Audit::log('panel', 'product.created', $this->tenantId, $this->userId, 'product', $id, ['sku' => $d['sku']]);
            return $id;
        });
    }

    public function update(int $id, array $input): void
    {
        $current = $this->repo->findOrFail($id);
        $d = $this->validate($input, $id);
        if ($d['name'] !== $current['name']) {
            $d['slug'] = $this->uniqueSlug($d['name'] . '-' . $d['sku'], $id);
        }
        $this->repo->update($id, $d);
        $changed = array_keys(array_filter($d, fn($v, $k) => array_key_exists($k, $current) && (string) $current[$k] !== (string) $v, ARRAY_FILTER_USE_BOTH));
        Audit::log('panel', 'product.updated', $this->tenantId, $this->userId, 'product', $id, ['fields' => $changed]);
    }

    private function validate(array $input, ?int $id = null): array
    {
        $d = Validator::check($input, self::RULES, self::LABELS);
        $d['cost_cents'] ??= 0;
        $d['min_stock'] ??= 0;
        $existing = $this->repo->findBySku($d['sku']);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new ValidationException(['sku' => 'Já existe um produto com este SKU.']);
        }
        foreach (['category_id' => 'categories', 'brand_id' => 'brands'] as $field => $table) {
            if ($d[$field] !== null && !$this->db->value("SELECT 1 FROM {$table} WHERE tenant_id = ? AND id = ?", [$this->tenantId, $d[$field]])) {
                throw new ValidationException([$field => 'Opção inválida.']);
            }
        }
        return $d;
    }

    public function uniqueSlug(string $text, ?int $exceptId = null): string
    {
        $base = substr(slugify($text), 0, 170);
        $slug = $base;
        for ($i = 2; $this->repo->slugExists($slug, $exceptId); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public function addFitment(int $productId, array $input): void
    {
        $this->repo->findOrFail($productId);
        $d = Validator::check($input, ['make' => 'required|max:60', 'model' => 'required|max:80', 'year_from' => 'required|year', 'year_to' => 'required|year', 'notes' => 'max:255'],
            ['make' => 'Marca da moto', 'model' => 'Modelo', 'year_from' => 'Ano inicial', 'year_to' => 'Ano final', 'notes' => 'Observações']);
        if ($d['year_to'] < $d['year_from']) {
            throw new ValidationException(['year_to' => 'O ano final deve ser igual ou maior que o inicial.']);
        }
        $this->db->insert('product_fitments', [
            'tenant_id' => $this->tenantId, 'product_id' => $productId,
            'make' => mb_convert_case($d['make'], MB_CASE_TITLE), 'model' => $d['model'],
            'year_from' => $d['year_from'], 'year_to' => $d['year_to'], 'notes' => $d['notes'], 'created_at' => Clock::now(),
        ]);
    }

    public function removeFitment(int $productId, int $fitmentId): void
    {
        $n = $this->db->execute('DELETE FROM product_fitments WHERE tenant_id = ? AND product_id = ? AND id = ?', [$this->tenantId, $productId, $fitmentId]);
        if ($n === 0) {
            throw HttpException::notFound();
        }
    }

    /** Upload validado pelo conteúdo real do arquivo (não pela extensão), guardado fora de public/. */
    public function addImage(int $productId, array $file, string $tenantUuid): void
    {
        $this->repo->findOrFail($productId);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file_safe($file['tmp_name'])) {
            throw new BusinessException('Envie uma imagem.');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new BusinessException('A imagem pode ter no máximo 2 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext || @getimagesize($file['tmp_name']) === false) {
            throw new BusinessException('Formato não aceito. Use JPG, PNG ou WebP.');
        }
        if (count($this->repo->images($productId)) >= 8) {
            throw new BusinessException('Cada produto pode ter até 8 imagens.');
        }
        $dir = self::uploadDir($tenantUuid);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file_safe($file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Falha ao gravar a imagem.');
        }
        $this->db->insert('product_images', [
            'tenant_id' => $this->tenantId, 'product_id' => $productId, 'file_name' => $name, 'mime' => $mime,
            'size_bytes' => (int) $file['size'], 'alt' => null,
            'position' => count($this->repo->images($productId)), 'created_at' => Clock::now(),
        ]);
    }

    public function deleteImage(int $productId, int $imageId, string $tenantUuid): void
    {
        $img = $this->db->fetch('SELECT * FROM product_images WHERE tenant_id = ? AND product_id = ? AND id = ?', [$this->tenantId, $productId, $imageId])
            ?? throw HttpException::notFound();
        $this->db->execute('DELETE FROM product_images WHERE tenant_id = ? AND id = ?', [$this->tenantId, $imageId]);
        $path = self::uploadDir($tenantUuid) . '/' . basename($img['file_name']);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function uploadDir(string $tenantUuid): string
    {
        if (!preg_match('/^[0-9a-f-]{36}$/', $tenantUuid)) {
            throw new \InvalidArgumentException('uuid inválido');
        }
        $dir = BASE_PATH . '/storage/uploads/' . $tenantUuid;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }
}

/** Permite testes com arquivos locais; em produção exige upload HTTP real. */
function is_uploaded_file_safe(string $tmp): bool
{
    return is_uploaded_file($tmp) || (getenv('MOTOSTOCK_TESTING') && is_file($tmp));
}

function move_uploaded_file_safe(string $tmp, string $dest): bool
{
    if (is_uploaded_file($tmp)) {
        return move_uploaded_file($tmp, $dest);
    }
    return getenv('MOTOSTOCK_TESTING') ? copy($tmp, $dest) : false;
}
