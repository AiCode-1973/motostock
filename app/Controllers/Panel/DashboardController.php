<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Services\PlanLimitService;
use App\Services\ReportService;
use App\Support\Period;

final class DashboardController extends PanelController
{
    public function index(Request $r): Response
    {
        $period = Period::fromInput($r->str('de') ?: null, $r->str('ate') ?: null, Tenant::timezone(), '30d');
        $data = (new ReportService($this->db(), $this->tid()))->dashboard($period);
        $limits = new PlanLimitService($this->db(), $this->tid());
        return $this->view('panel/dashboard', [
            'title' => 'Visão geral',
            'period' => $period,
            'd' => $data,
            'usage' => $limits->usage(),
            'plan' => $limits->plan(),
            'productsCount' => (int) $this->db()->value('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$this->tid()]),
        ]);
    }
}
