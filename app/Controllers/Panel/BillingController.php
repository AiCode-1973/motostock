<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Services\PlanLimitService;
use App\Services\TenantExportService;

/** Situação da assinatura e exportação de dados (disponíveis mesmo com a loja suspensa). */
final class BillingController extends PanelController
{
    public function index(Request $r): Response
    {
        $limits = new PlanLimitService($this->db(), $this->tid());
        return $this->view('panel/billing', [
            'title' => 'Assinatura',
            'plan' => $limits->plan(),
            'usage' => $limits->usage(),
            'subscription' => $this->db()->fetch('SELECT * FROM subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$this->tid()]),
            'plans' => $this->db()->fetchAll('SELECT * FROM plans WHERE active = 1 AND is_public = 1 ORDER BY sort_order, price_cents'),
        ]);
    }

    public function export(Request $r): Response
    {
        $json = (new TenantExportService($this->db()))->export($this->tid());
        Audit::panel('tenant.exported');
        return Response::download($json, 'motostock-' . Tenant::current()['slug'] . '-' . gmdate('Ymd-His') . '.json', 'application/json');
    }
}
