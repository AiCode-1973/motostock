<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\TenantResolver;
use App\Core\Validator;

/** Provisionamento e ciclo de vida dos tenants. Nada aqui apaga dados de loja. */
final class TenantService
{
    public const STATUS_TRANSITIONS = [
        'trial' => ['active', 'past_due', 'suspended', 'cancelled'],
        'active' => ['past_due', 'suspended', 'cancelled'],
        'past_due' => ['active', 'suspended', 'cancelled'],
        'suspended' => ['active', 'cancelled'],
        'cancelled' => ['active'], // reativação dentro do prazo de retenção
    ];

    public function __construct(private Database $db)
    {
    }

    /** Cadastro de empresa + dono. Devolve ['tenant_id', 'user_id']. */
    public function signup(array $input): array
    {
        $d = Validator::check($input, [
            'trade_name' => 'required|max:120',
            'legal_name' => 'required|max:160',
            'document' => 'required|cnpj',
            'phone' => 'required|phone',
            'city' => 'required|max:80',
            'state' => 'required|uf',
            'slug' => 'required|slug',
            'plan_id' => 'required|int',
            'owner_name' => 'required|max:120',
            'email' => 'required|email|max:160',
            'password' => 'required|password',
            'accept_terms' => 'bool',
        ], [
            'trade_name' => 'Nome da loja', 'legal_name' => 'Razão social', 'document' => 'CNPJ', 'phone' => 'Telefone',
            'city' => 'Cidade', 'state' => 'UF', 'slug' => 'Endereço da loja', 'plan_id' => 'Plano',
            'owner_name' => 'Seu nome', 'email' => 'E-mail', 'password' => 'Senha',
        ]);
        if (!$d['accept_terms']) {
            throw new \App\Core\ValidationException(['accept_terms' => 'É preciso aceitar os termos de uso e a política de privacidade.']);
        }
        if (($input['password_confirmation'] ?? '') !== $input['password']) {
            throw new \App\Core\ValidationException(['password_confirmation' => 'As senhas não conferem.']);
        }
        if (in_array($d['slug'], TenantResolver::RESERVED, true)) {
            throw new \App\Core\ValidationException(['slug' => 'Este endereço é reservado. Escolha outro.']);
        }
        $plan = $this->db->fetch('SELECT * FROM plans WHERE id = ? AND active = 1 AND is_public = 1', [$d['plan_id']]);
        if (!$plan) {
            throw new \App\Core\ValidationException(['plan_id' => 'Escolha um plano disponível.']);
        }
        if ($this->db->value('SELECT 1 FROM tenants WHERE slug = ?', [$d['slug']])) {
            throw new \App\Core\ValidationException(['slug' => 'Este endereço já está em uso.']);
        }
        if ($this->db->value('SELECT 1 FROM users WHERE email = ?', [$d['email']])) {
            throw new \App\Core\ValidationException(['email' => 'Já existe uma conta com este e-mail. Entre com ela ou use outro e-mail.']);
        }
        return $this->provision($d, $plan, false);
    }

    /** Usado pelo cadastro e pelos dados de demonstração. */
    public function provision(array $d, array $plan, bool $isDemo): array
    {
        return $this->db->transaction(function () use ($d, $plan, $isDemo) {
            $now = Clock::now();
            $trial = (int) $plan['trial_days'] > 0;
            $tenantId = $this->db->insert('tenants', [
                'uuid' => uuid4(),
                'slug' => $d['slug'],
                'trade_name' => $d['trade_name'],
                'legal_name' => $d['legal_name'],
                'document' => $d['document'],
                'email' => $d['email'],
                'phone' => $d['phone'],
                'city' => $d['city'],
                'state' => $d['state'],
                'status' => $trial ? 'trial' : 'past_due',
                'status_reason' => $trial ? null : 'Aguardando o primeiro pagamento da assinatura',
                'plan_id' => (int) $plan['id'],
                'trial_ends_at' => $trial ? Clock::now('+' . (int) $plan['trial_days'] . ' days') : null,
                'is_demo' => (int) $isDemo,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->insert('tenant_domains', [
                'tenant_id' => $tenantId,
                'host' => $d['slug'] . '.' . Env::get('APP_BASE_DOMAIN', 'localhost'),
                'kind' => 'subdomain',
                'verified_at' => $now,
                'created_at' => $now,
            ]);
            $this->db->insert('subscriptions', [
                'tenant_id' => $tenantId,
                'plan_id' => (int) $plan['id'],
                'status' => $trial ? 'trialing' : 'past_due',
                'current_period_start' => $now,
                'current_period_end' => $trial ? Clock::now('+' . (int) $plan['trial_days'] . ' days') : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userId = $this->db->insert('users', [
                'name' => $d['owner_name'],
                'email' => $d['email'],
                'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
                'password_changed_at' => $now,
                'is_demo' => (int) $isDemo,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->insert('tenant_users', [
                'tenant_id' => $tenantId, 'user_id' => $userId, 'role' => 'owner', 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->db->insert('store_settings', [
                'tenant_id' => $tenantId,
                'contact_email' => $d['email'],
                'contact_phone' => $d['phone'],
                'policy_privacy' => LegalTemplates::privacy(),
                'policy_returns' => LegalTemplates::returns(),
                'policy_shipping' => LegalTemplates::shipping(),
                'updated_at' => $now,
            ]);
            foreach ([
                ['Retirada na loja', 'pickup', 0, 0, 0, 1],
                ['Entrega própria (configure o valor)', 'delivery', 0, 1, 3, 0],
            ] as [$name, $kind, $price, $min, $max, $active]) {
                $this->db->insert('shipping_methods', [
                    'tenant_id' => $tenantId, 'name' => $name, 'kind' => $kind, 'price_cents' => $price,
                    'days_min' => $min, 'days_max' => $max, 'active' => $active, 'created_at' => $now,
                ]);
            }
            Audit::log('system', 'tenant.created', $tenantId, $userId, 'tenant', $tenantId, ['plan' => $plan['code'], 'demo' => $isDemo]);
            return ['tenant_id' => $tenantId, 'user_id' => $userId];
        });
    }

    /** Suspensão, reativação e cancelamento pela plataforma (auditados). */
    public function changeStatus(int $tenantId, string $to, string $reason, int $actorId): void
    {
        $t = $this->db->fetch('SELECT * FROM tenants WHERE id = ?', [$tenantId]) ?? throw HttpException::notFound();
        if (!in_array($to, self::STATUS_TRANSITIONS[$t['status']] ?? [], true)) {
            throw new BusinessException('Mudança de situação não permitida.');
        }
        if (trim($reason) === '') {
            throw new BusinessException('Informe o motivo.');
        }
        if ($t['status'] === 'cancelled' && $t['retention_until'] && $t['retention_until'] < Clock::now()) {
            throw new BusinessException('O prazo de retenção acabou; a reativação exige análise manual.');
        }
        $set = ['status' => $to, 'status_reason' => mb_substr($reason, 0, 255), 'updated_at' => Clock::now()];
        if ($to === 'suspended') {
            $set['suspended_at'] = Clock::now();
        }
        if ($to === 'cancelled') {
            $set['cancelled_at'] = Clock::now();
            $set['retention_until'] = Clock::now('+' . Env::int('TENANT_RETENTION_DAYS', 90) . ' days');
        }
        if ($to === 'active') {
            $set['suspended_at'] = null;
            $set['cancelled_at'] = null;
            $set['retention_until'] = null;
        }
        $cols = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($set)));
        $this->db->execute("UPDATE tenants SET {$cols} WHERE id = ?", [...array_values($set), $tenantId]);
        $subStatus = match ($to) {
            'active' => 'active',
            'past_due', 'suspended' => 'past_due',
            'cancelled' => 'cancelled',
            default => 'trialing',
        };
        $this->db->execute('UPDATE subscriptions SET status = ?, updated_at = ? WHERE tenant_id = ?', [$subStatus, Clock::now(), $tenantId]);
        Audit::log('platform', 'tenant.status', $tenantId, $actorId, 'tenant', $tenantId, ['from' => $t['status'], 'to' => $to, 'reason' => $reason]);
    }

    public function changePlan(int $tenantId, int $planId, int $actorId): void
    {
        $plan = $this->db->fetch('SELECT * FROM plans WHERE id = ? AND active = 1', [$planId]) ?? throw new BusinessException('Plano inválido.');
        $old = $this->db->value('SELECT plan_id FROM tenants WHERE id = ?', [$tenantId]) ?? throw HttpException::notFound();
        $this->db->execute('UPDATE tenants SET plan_id = ?, updated_at = ? WHERE id = ?', [$planId, Clock::now(), $tenantId]);
        $this->db->execute('UPDATE subscriptions SET plan_id = ?, updated_at = ? WHERE tenant_id = ?', [$planId, Clock::now(), $tenantId]);
        Audit::log('platform', 'tenant.plan', $tenantId, $actorId, 'tenant', $tenantId, ['from' => (int) $old, 'to' => $planId]);
    }

    /** Tarefa agendada: fim do teste → pagamento pendente (não suspende nem apaga). */
    public function expireTrials(): int
    {
        $rows = $this->db->fetchAll("SELECT id FROM tenants WHERE status = 'trial' AND trial_ends_at < ?", [Clock::now()]);
        foreach ($rows as $r) {
            $this->db->execute("UPDATE tenants SET status = 'past_due', status_reason = 'Período de teste encerrado', updated_at = ? WHERE id = ?", [Clock::now(), $r['id']]);
            $this->db->execute("UPDATE subscriptions SET status = 'past_due', updated_at = ? WHERE tenant_id = ?", [Clock::now(), $r['id']]);
            Audit::log('system', 'tenant.trial_expired', (int) $r['id']);
        }
        return count($rows);
    }
}
