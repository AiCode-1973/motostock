<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PlanLimitService;
use App\Services\TenantExportService;
use App\Services\TenantService;

/**
 * Painel da equipe MotoStock. Mostra dados cadastrais, situação e USO dos tenants —
 * nunca clientes ou pedidos das lojas. Acessos e ações ficam na auditoria.
 */
final class TenantController extends Controller
{
    protected string $layout = 'layouts/platform';

    public function dashboard(Request $r): Response
    {
        $db = $this->db();
        return $this->view('platform/dashboard', [
            'title' => 'Plataforma',
            'byStatus' => $db->fetchAll('SELECT status, COUNT(*) AS n FROM tenants WHERE is_demo = 0 GROUP BY status'),
            'demoCount' => (int) $db->value('SELECT COUNT(*) FROM tenants WHERE is_demo = 1'),
            'byPlan' => $db->fetchAll('SELECT p.name, COUNT(t.id) AS n FROM plans p LEFT JOIN tenants t ON t.plan_id = p.id AND t.is_demo = 0 GROUP BY p.id, p.name ORDER BY p.sort_order'),
            'trialsEnding' => $db->fetchAll("SELECT id, trade_name, trial_ends_at FROM tenants WHERE status = 'trial' AND trial_ends_at < DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY) ORDER BY trial_ends_at LIMIT 10"),
            'events' => $db->fetchAll("SELECT a.*, t.trade_name, u.name AS user_name FROM audit_logs a LEFT JOIN tenants t ON t.id = a.tenant_id LEFT JOIN users u ON u.id = a.user_id WHERE a.action LIKE 'tenant.%' ORDER BY a.id DESC LIMIT 10"),
            'webhookFailures' => (int) $db->value("SELECT COUNT(*) FROM webhook_events WHERE status = 'failed' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)"),
        ]);
    }

    public function index(Request $r): Response
    {
        $where = '1=1';
        $params = [];
        if (isset(TenantService::STATUS_TRANSITIONS[$r->str('situacao')])) {
            $where .= ' AND t.status = ?';
            $params[] = $r->str('situacao');
        }
        if (($q = $r->str('q')) !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where .= ' AND (t.trade_name LIKE ? OR t.slug LIKE ? OR t.document LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $rows = $this->db()->fetchAll(
            "SELECT t.*, p.name AS plan_name FROM tenants t JOIN plans p ON p.id = t.plan_id WHERE {$where} ORDER BY t.id DESC LIMIT 300",
            $params
        );
        return $this->view('platform/tenants', ['title' => 'Lojas', 'rows' => $rows]);
    }

    public function show(Request $r, int $id): Response
    {
        $tenant = $this->db()->fetch('SELECT t.*, p.name AS plan_name FROM tenants t JOIN plans p ON p.id = t.plan_id WHERE t.id = ?', [$id]) ?? throw HttpException::notFound();
        Audit::log('platform', 'tenant.viewed', $id, Auth::id(), 'tenant', $id);
        $limits = new PlanLimitService($this->db(), $id);
        return $this->view('platform/tenant', [
            'title' => $tenant['trade_name'],
            't' => $tenant,
            'usage' => $limits->usage(),
            'plan' => $limits->plan(),
            'plans' => $this->db()->fetchAll('SELECT id, name FROM plans WHERE active = 1 ORDER BY sort_order'),
            'subscription' => $this->db()->fetch('SELECT * FROM subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$id]),
            'domains' => $this->db()->fetchAll('SELECT * FROM tenant_domains WHERE tenant_id = ?', [$id]),
            'owners' => $this->db()->fetchAll("SELECT u.name, u.email, u.mfa_enabled, u.last_login_at FROM tenant_users tu JOIN users u ON u.id = tu.user_id WHERE tu.tenant_id = ? AND tu.role = 'owner'", [$id]),
            'next' => TenantService::STATUS_TRANSITIONS[$tenant['status']] ?? [],
            'events' => $this->db()->fetchAll("SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.tenant_id = ? AND a.area IN ('platform','system') ORDER BY a.id DESC LIMIT 30", [$id]),
        ]);
    }

    public function changeStatus(Request $r, int $id): Response
    {
        (new TenantService($this->db()))->changeStatus($id, $r->str('to'), $r->str('reason'), (int) Auth::id());
        $this->flash('Situação da loja alterada. Nenhum dado foi apagado.');
        return $this->redirect('/plataforma/lojas/' . $id);
    }

    public function changePlan(Request $r, int $id): Response
    {
        (new TenantService($this->db()))->changePlan($id, $r->int('plan_id'), (int) Auth::id());
        $this->flash('Plano alterado.');
        return $this->redirect('/plataforma/lojas/' . $id);
    }

    public function export(Request $r, int $id): Response
    {
        $slug = $this->db()->value('SELECT slug FROM tenants WHERE id = ?', [$id]) ?? throw HttpException::notFound();
        Audit::log('platform', 'tenant.exported', $id, Auth::id(), 'tenant', $id, ['reason' => $r->str('motivo')]);
        return Response::download((new TenantExportService($this->db()))->export($id), "motostock-{$slug}-" . gmdate('Ymd-His') . '.json', 'application/json');
    }

    public function audit(Request $r): Response
    {
        $rows = $this->db()->fetchAll(
            "SELECT a.*, t.trade_name, u.name AS user_name FROM audit_logs a LEFT JOIN tenants t ON t.id = a.tenant_id
             LEFT JOIN users u ON u.id = a.user_id WHERE a.area IN ('platform','system') ORDER BY a.id DESC LIMIT 300"
        );
        return $this->view('platform/audit', ['title' => 'Auditoria da plataforma', 'rows' => $rows]);
    }
}
