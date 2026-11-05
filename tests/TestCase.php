<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Clock;
use App\Core\Database;
use App\Core\Session;
use App\Services\ProductService;
use App\Services\TenantService;

/** Base dos testes: asserções simples e fábricas de dados. Cada teste roda em banco recriado. */
abstract class TestCase
{
    public int $assertions = 0;
    protected Database $db;
    private static int $seq = 0;

    public function setUp(): void
    {
        $this->db = Database::connect();
        Fresh::reset($this->db);
        Clock::$fixed = null;
        \App\Services\PaymentService::$override = null;
        \App\Core\Mailer::$sent = [];
        $empty = [];
        Session::useArray($empty);
    }

    // ===== Asserções =====
    protected function assertTrue(mixed $v, string $msg = 'esperado verdadeiro'): void
    {
        $this->assertions++;
        if ($v !== true) {
            throw new AssertionFailed($msg . ' (obtido: ' . var_export($v, true) . ')');
        }
    }

    protected function assertFalse(mixed $v, string $msg = 'esperado falso'): void
    {
        $this->assertTrue($v === false, $msg);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(($msg ? $msg . ': ' : '') . 'esperado ' . var_export($expected, true) . ', obtido ' . var_export($actual, true));
        }
    }

    protected function assertContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(($msg ? $msg . ': ' : '') . "texto não contém \"{$needle}\"");
        }
    }

    protected function assertNotContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            throw new AssertionFailed(($msg ? $msg . ': ' : '') . "texto não deveria conter \"{$needle}\"");
        }
    }

    protected function assertStatus(int $status, \App\Core\Response $r, string $msg = ''): void
    {
        $this->assertSame($status, $r->status, ($msg ?: 'status HTTP') . ' [Location: ' . ($r->headers['Location'] ?? '-') . ']');
    }

    protected function assertRedirectTo(string $path, \App\Core\Response $r): void
    {
        $this->assertSame(303, $r->status, 'esperado redirecionamento');
        $this->assertSame($path, parse_url((string) ($r->headers['Location'] ?? ''), PHP_URL_PATH), 'destino do redirecionamento');
    }

    /** @param class-string<\Throwable> $class */
    protected function assertThrows(string $class, callable $fn, string $contains = ''): \Throwable
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                throw new AssertionFailed("esperado {$class}, obtido " . get_class($e) . ': ' . $e->getMessage());
            }
            if ($contains !== '' && !str_contains($e->getMessage(), $contains)) {
                throw new AssertionFailed("mensagem \"{$e->getMessage()}\" não contém \"{$contains}\"");
            }
            return $e;
        }
        throw new AssertionFailed("esperado {$class}, nada foi lançado");
    }

    // ===== Fábricas =====
    protected function plan(array $over = []): array
    {
        $code = 'plano' . (++self::$seq);
        $id = $this->db->insert('plans', $over + [
            'code' => $code, 'name' => 'Plano ' . self::$seq, 'price_cents' => 9900, 'trial_days' => 14,
            'max_users' => null, 'max_products' => null, 'max_orders_month' => null,
            'created_at' => Clock::now(), 'updated_at' => Clock::now(),
        ]);
        return $this->db->fetch('SELECT * FROM plans WHERE id = ?', [$id]);
    }

    /** @return array{tenant: array, owner: array} */
    protected function tenant(string $slug, ?array $plan = null): array
    {
        $plan ??= $this->plan();
        $r = (new TenantService($this->db))->provision([
            'slug' => $slug, 'trade_name' => 'Loja ' . $slug, 'legal_name' => 'Loja ' . $slug . ' Ltda', 'document' => '11222333000181',
            'email' => "dono@{$slug}.test", 'phone' => '11987654321', 'city' => 'São Paulo', 'state' => 'SP',
            'owner_name' => 'Dono ' . $slug, 'password' => 'senha-forte-123',
        ], $plan, false);
        return [
            'tenant' => $this->db->fetch('SELECT * FROM tenants WHERE id = ?', [$r['tenant_id']]),
            'owner' => $this->db->fetch('SELECT * FROM users WHERE id = ?', [$r['user_id']]),
        ];
    }

    protected function member(int $tenantId, string $role, string $email): array
    {
        $uid = $this->db->insert('users', ['name' => ucfirst($role), 'email' => $email, 'password_hash' => password_hash('senha-forte-123', PASSWORD_DEFAULT),
            'password_changed_at' => Clock::now(), 'created_at' => Clock::now(), 'updated_at' => Clock::now()]);
        $this->db->insert('tenant_users', ['tenant_id' => $tenantId, 'user_id' => $uid, 'role' => $role, 'status' => 'active', 'created_at' => Clock::now(), 'updated_at' => Clock::now()]);
        return $this->db->fetch('SELECT * FROM users WHERE id = ?', [$uid]);
    }

    protected function product(int $tenantId, string $sku, int $priceCents = 10000, int $stock = 10, int $costCents = 6000): int
    {
        return (new ProductService($this->db, $tenantId))->create([
            'sku' => $sku, 'name' => 'Produto ' . $sku, 'price_cents' => \App\Core\Money::input($priceCents),
            'cost_cents' => \App\Core\Money::input($costCents), 'min_stock' => '2', 'active' => '1',
        ], $stock);
    }

    protected function stock(int $tenantId, int $productId): array
    {
        return $this->db->fetch('SELECT stock_qty, reserved_qty FROM products WHERE tenant_id = ? AND id = ?', [$tenantId, $productId]);
    }

    protected function shippingPickup(int $tenantId): int
    {
        return (int) $this->db->value("SELECT id FROM shipping_methods WHERE tenant_id = ? AND kind = 'pickup'", [$tenantId]);
    }

    protected function browser(string $host = 'localhost'): Browser
    {
        return new Browser($host);
    }
}

final class AssertionFailed extends \RuntimeException
{
}

final class Fresh
{
    private static ?array $tables = null;

    /** Limpa todas as tabelas (rápido) mantendo o esquema. */
    public static function reset(Database $db): void
    {
        $pdo = $db->pdo();
        self::$tables ??= array_values(array_filter($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN), fn($t) => $t !== 'schema_migrations'));
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::$tables as $t) {
            $pdo->exec("TRUNCATE TABLE `{$t}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
