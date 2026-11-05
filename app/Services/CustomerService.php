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

/** Clientes: cadastro mínimo (LGPD: necessidade), conta na loja e anonimização. */
final class CustomerService
{
    public const RULES = [
        'name' => 'required|max:120',
        'email' => 'email|max:160',
        'phone' => 'phone',
        'document' => 'cpf_cnpj',
        'marketing_opt_in' => 'bool',
    ];
    public const LABELS = ['name' => 'Nome', 'email' => 'E-mail', 'phone' => 'Telefone', 'document' => 'CPF/CNPJ'];

    public function __construct(private Database $db, private int $tenantId, private ?int $userId = null)
    {
    }

    public function find(int $id): array
    {
        return $this->db->fetch('SELECT * FROM customers WHERE tenant_id = ? AND id = ?', [$this->tenantId, $id])
            ?? throw HttpException::notFound('Cliente não encontrado.');
    }

    public function search(string $q, int $page, int $perPage = 20): array
    {
        $where = 'tenant_id = ?';
        $params = [$this->tenantId];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $total = (int) $this->db->value("SELECT COUNT(*) FROM customers WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = $this->db->fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.tenant_id = c.tenant_id AND o.customer_id = c.id) AS orders_count
             FROM customers c WHERE {$where} ORDER BY c.name LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function create(array $input, bool $isDemo = false): int
    {
        $d = Validator::check($input, self::RULES, self::LABELS);
        $this->assertEmailFree($d['email']);
        $id = $this->db->insert('customers', $d + [
            'tenant_id' => $this->tenantId, 'is_demo' => (int) $isDemo, 'created_at' => Clock::now(), 'updated_at' => Clock::now(),
        ]);
        Audit::log('panel', 'customer.created', $this->tenantId, $this->userId, 'customer', $id);
        return $id;
    }

    public function update(int $id, array $input): void
    {
        $c = $this->find($id);
        if ($c['anonymized_at']) {
            throw new BusinessException('Cliente anonimizado não pode ser editado.');
        }
        $d = Validator::check($input, self::RULES, self::LABELS);
        $this->assertEmailFree($d['email'], $id);
        $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($d)));
        $this->db->execute("UPDATE customers SET {$sets}, updated_at = ? WHERE tenant_id = ? AND id = ?", [...array_values($d), Clock::now(), $this->tenantId, $id]);
        Audit::log('panel', 'customer.updated', $this->tenantId, $this->userId, 'customer', $id, ['fields' => array_keys($d)]);
    }

    /** Conta criada pelo próprio cliente na loja. */
    public function register(array $input, int $privacyVersion): int
    {
        $d = Validator::check($input, ['name' => 'required|max:120', 'email' => 'required|email|max:160', 'phone' => 'phone', 'password' => 'required|password', 'marketing_opt_in' => 'bool', 'accept_privacy' => 'bool'],
            self::LABELS + ['password' => 'Senha']);
        if (!$d['accept_privacy']) {
            throw new ValidationException(['accept_privacy' => 'É preciso ler e aceitar a política de privacidade.']);
        }
        $existing = $this->db->fetch('SELECT * FROM customers WHERE tenant_id = ? AND email = ?', [$this->tenantId, $d['email']]);
        if ($existing && $existing['password_hash']) {
            throw new ValidationException(['email' => 'Já existe uma conta com este e-mail nesta loja. Entre ou recupere o acesso com a loja.']);
        }
        $data = [
            'name' => $d['name'], 'phone' => $d['phone'], 'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
            'marketing_opt_in' => $d['marketing_opt_in'], 'privacy_accepted_at' => Clock::now(), 'privacy_version' => $privacyVersion,
            'updated_at' => Clock::now(),
        ];
        if ($existing) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            $this->db->execute("UPDATE customers SET {$sets} WHERE tenant_id = ? AND id = ?", [...array_values($data), $this->tenantId, $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->db->insert('customers', $data + ['tenant_id' => $this->tenantId, 'email' => $d['email'], 'created_at' => Clock::now()]);
    }

    public function verifyLogin(string $email, string $password): ?array
    {
        $c = $this->db->fetch('SELECT * FROM customers WHERE tenant_id = ? AND email = ? AND anonymized_at IS NULL', [$this->tenantId, mb_strtolower(trim($email))]);
        static $dummy = null;
        $dummy ??= password_hash('x', PASSWORD_DEFAULT);
        $ok = password_verify($password, $c['password_hash'] ?? $dummy);
        return ($c && $c['password_hash'] && $ok) ? $c : null;
    }

    /** Cliente comprando sem conta: reaproveita pelo e-mail ou cria cadastro mínimo. */
    public function upsertGuest(array $d, int $privacyVersion): int
    {
        $existing = $this->db->fetch('SELECT id FROM customers WHERE tenant_id = ? AND email = ?', [$this->tenantId, $d['email']]);
        if ($existing) {
            $this->db->execute('UPDATE customers SET name = ?, phone = ?, privacy_accepted_at = ?, privacy_version = ?, updated_at = ? WHERE tenant_id = ? AND id = ?',
                [$d['name'], $d['phone'], Clock::now(), $privacyVersion, Clock::now(), $this->tenantId, $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->db->insert('customers', [
            'tenant_id' => $this->tenantId, 'name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'],
            'privacy_accepted_at' => Clock::now(), 'privacy_version' => $privacyVersion,
            'created_at' => Clock::now(), 'updated_at' => Clock::now(),
        ]);
    }

    /**
     * Atende pedido de eliminação: remove identificação do cliente e dos pedidos,
     * mas mantém valores e itens (obrigações fiscais/contábeis). Irreversível.
     */
    public function anonymize(int $id): void
    {
        $c = $this->find($id);
        if ($c['anonymized_at']) {
            throw new BusinessException('Este cliente já foi anonimizado.');
        }
        $this->db->transaction(function () use ($id) {
            $label = 'Cliente anonimizado #' . $id;
            $this->db->execute('UPDATE customers SET name = ?, email = NULL, phone = NULL, document = NULL, password_hash = NULL, marketing_opt_in = 0, anonymized_at = ?, updated_at = ? WHERE tenant_id = ? AND id = ?',
                [$label, Clock::now(), Clock::now(), $this->tenantId, $id]);
            $this->db->execute('DELETE FROM customer_addresses WHERE tenant_id = ? AND customer_id = ?', [$this->tenantId, $id]);
            $this->db->execute('UPDATE orders SET customer_name = ?, customer_email = NULL, customer_phone = NULL, ship_street = NULL, ship_number = NULL, ship_complement = NULL, ship_district = NULL, ship_zip = NULL
                                WHERE tenant_id = ? AND customer_id = ?', [$label, $this->tenantId, $id]);
            Audit::log('panel', 'customer.anonymized', $this->tenantId, $this->userId, 'customer', $id);
        });
    }

    private function assertEmailFree(?string $email, ?int $exceptId = null): void
    {
        if ($email && $this->db->value('SELECT 1 FROM customers WHERE tenant_id = ? AND email = ? AND id <> ?', [$this->tenantId, $email, $exceptId ?? 0])) {
            throw new ValidationException(['email' => 'Já existe um cliente com este e-mail.']);
        }
    }
}
