<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\Url;
use App\Core\Validator;

/** Equipe da loja e recuperação de senha. */
final class UserService
{
    public function __construct(private Database $db)
    {
    }

    public function members(int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT tu.id AS member_id, tu.role, tu.status, u.id, u.name, u.email, u.last_login_at, u.mfa_enabled
             FROM tenant_users tu JOIN users u ON u.id = tu.user_id WHERE tu.tenant_id = ? ORDER BY tu.role, u.name',
            [$tenantId]
        );
    }

    /** Adiciona alguém à equipe. Conta nova recebe link para definir a senha (sem senha provisória). */
    public function addMember(int $tenantId, array $input, int $actorId): void
    {
        $d = Validator::check($input, ['name' => 'required|max:120', 'email' => 'required|email|max:160', 'role' => 'required|in:manager,seller'],
            ['name' => 'Nome', 'email' => 'E-mail', 'role' => 'Função']);
        $this->db->transaction(function () use ($tenantId, $d, $actorId) {
            (new PlanLimitService($this->db, $tenantId))->assertCanAdd('users');
            $user = $this->db->fetch('SELECT * FROM users WHERE email = ?', [$d['email']]);
            $isNew = false;
            if (!$user) {
                $isNew = true;
                $id = $this->db->insert('users', [
                    'name' => $d['name'], 'email' => $d['email'],
                    'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                    'password_changed_at' => Clock::now(), 'created_at' => Clock::now(), 'updated_at' => Clock::now(),
                ]);
                $user = ['id' => $id, 'email' => $d['email'], 'name' => $d['name']];
            }
            if ($this->db->value('SELECT 1 FROM tenant_users WHERE tenant_id = ? AND user_id = ?', [$tenantId, $user['id']])) {
                throw new BusinessException('Esta pessoa já faz parte da equipe.');
            }
            $this->db->insert('tenant_users', [
                'tenant_id' => $tenantId, 'user_id' => (int) $user['id'], 'role' => $d['role'], 'status' => 'active',
                'created_at' => Clock::now(), 'updated_at' => Clock::now(),
            ]);
            $store = (string) $this->db->value('SELECT trade_name FROM tenants WHERE id = ?', [$tenantId]);
            if ($isNew) {
                $link = $this->createResetLink((int) $user['id'], 72);
                Mailer::send($user['email'], "Convite para a equipe de {$store} no MotoStock",
                    "Olá, {$user['name']}.\n\nVocê foi convidado(a) para a equipe de {$store}.\nDefina sua senha em até 72 horas: {$link}\n");
            } else {
                Mailer::send($user['email'], "Você agora faz parte de {$store}", "Entre no MotoStock com sua conta atual: " . Url::absolute('entrar'));
            }
            Audit::log('panel', 'member.added', $tenantId, $actorId, 'user', (int) $user['id'], ['role' => $d['role']]);
        });
    }

    public function updateMember(int $tenantId, int $memberId, string $role, string $status, int $actorId): void
    {
        if (!in_array($role, ['owner', 'manager', 'seller'], true) || !in_array($status, ['active', 'disabled'], true)) {
            throw new BusinessException('Dados inválidos.');
        }
        $m = $this->db->fetch('SELECT * FROM tenant_users WHERE tenant_id = ? AND id = ?', [$tenantId, $memberId]) ?? throw HttpException::notFound();
        if ((int) $m['user_id'] === $actorId) {
            throw new BusinessException('Você não pode alterar a sua própria função ou acesso.');
        }
        if ($m['role'] === 'owner' && ($role !== 'owner' || $status !== 'active')) {
            $owners = (int) $this->db->value("SELECT COUNT(*) FROM tenant_users WHERE tenant_id = ? AND role = 'owner' AND status = 'active'", [$tenantId]);
            if ($owners <= 1) {
                throw new BusinessException('A loja precisa de pelo menos um proprietário ativo.');
            }
        }
        if ($status === 'active' && $m['status'] !== 'active') {
            (new PlanLimitService($this->db, $tenantId))->assertCanAdd('users');
        }
        $this->db->execute('UPDATE tenant_users SET role = ?, status = ?, updated_at = ? WHERE tenant_id = ? AND id = ?', [$role, $status, Clock::now(), $tenantId, $memberId]);
        Audit::log('panel', 'member.updated', $tenantId, $actorId, 'user', (int) $m['user_id'], ['role' => $role, 'status' => $status]);
    }

    /** Sempre responde igual, exista ou não o e-mail (não revela contas). */
    public function requestReset(string $email): void
    {
        $user = $this->db->fetch("SELECT * FROM users WHERE email = ? AND status = 'active'", [mb_strtolower(trim($email))]);
        if (!$user) {
            return;
        }
        $link = $this->createResetLink((int) $user['id'], 1);
        Mailer::send($user['email'], 'Redefinição de senha do MotoStock',
            "Recebemos um pedido para redefinir sua senha.\nUse o link em até 1 hora: {$link}\n\nSe não foi você, ignore este e-mail.");
        Audit::log('system', 'auth.reset_requested', null, (int) $user['id']);
    }

    public function createResetLink(int $userId, int $hours): string
    {
        $token = \App\Core\Crypto::token(32);
        $this->db->execute('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [Clock::now(), $userId]);
        $this->db->insert('password_resets', [
            'user_id' => $userId, 'token_hash' => hash('sha256', $token),
            'expires_at' => Clock::now("+{$hours} hours"), 'created_at' => Clock::now(),
        ]);
        return Url::absolute('redefinir-senha/' . $token);
    }

    public function findValidReset(string $token): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
            [hash('sha256', $token), Clock::now()]
        );
    }

    public function resetPassword(string $token, string $password, string $confirmation): int
    {
        Validator::check(['password' => $password], ['password' => 'required|password']);
        if ($password !== $confirmation) {
            throw new \App\Core\ValidationException(['password_confirmation' => 'As senhas não conferem.']);
        }
        $reset = $this->findValidReset($token) ?? throw new BusinessException('Link inválido ou expirado. Peça um novo.');
        $this->setPassword((int) $reset['user_id'], $password);
        $this->db->execute('UPDATE password_resets SET used_at = ? WHERE id = ?', [Clock::now(), $reset['id']]);
        Audit::log('system', 'auth.password_reset', null, (int) $reset['user_id']);
        return (int) $reset['user_id'];
    }

    public function setPassword(int $userId, string $password): void
    {
        $this->db->execute('UPDATE users SET password_hash = ?, password_changed_at = ?, updated_at = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), Clock::now(), Clock::now(), $userId]);
    }
}
