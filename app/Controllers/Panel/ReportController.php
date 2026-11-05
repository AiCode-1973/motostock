<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Services\ReportService;
use App\Support\Csv;
use App\Support\Period;

final class ReportController extends PanelController
{
    public function index(Request $r): Response
    {
        return $this->view('panel/reports/index', ['title' => 'Relatórios', 'reports' => ReportService::REPORTS]);
    }

    public function show(Request $r, string $key): Response
    {
        [$period, $data] = $this->run($r, $key);
        return $this->view('panel/reports/show', [
            'title' => ReportService::REPORTS[$key],
            'key' => $key,
            'period' => $period,
            'data' => $data,
            'canSeeCost' => Tenant::can('products.cost'),
        ]);
    }

    public function csv(Request $r, string $key): Response
    {
        [$period, $data] = $this->run($r, $key);
        $columns = $data['columns'];
        if (!Tenant::can('products.cost')) {
            unset($columns['cost_cents'], $columns['margin_cents'], $columns['value_cents']);
        }
        $rows = [];
        foreach ($data['rows'] as $row) {
            $line = [];
            foreach (array_keys($columns) as $c) {
                $v = $row[$c] ?? '';
                if (str_ends_with($c, '_cents')) {
                    $v = number_format(((int) $v) / 100, 2, ',', '');
                } elseif (in_array($c, ['placed_at', 'paid_at', 'created_at'], true)) {
                    $v = dt($v);
                } elseif ($c === 'status') {
                    $v = label($key === 'pagamentos' ? 'payment' : 'order', $v);
                } elseif ($c === 'channel') {
                    $v = label('channel', $v);
                }
                $line[] = $v;
            }
            $rows[] = $line;
        }
        Audit::panel('report.exported', 'report', $key, ['from' => $period->from, 'to' => $period->to, 'rows' => count($rows)]);
        return Response::download(Csv::build(array_values($columns), $rows), "relatorio-{$key}-{$period->from}-a-{$period->to}.csv", 'text/csv; charset=utf-8');
    }

    private function run(Request $r, string $key): array
    {
        if (!isset(ReportService::REPORTS[$key])) {
            throw HttpException::notFound('Relatório inexistente.');
        }
        $period = Period::fromInput($r->str('de') ?: null, $r->str('ate') ?: null, Tenant::timezone(), 'month');
        $data = (new ReportService($this->db(), $this->tid()))->run($key, $period, ['status' => $r->str('situacao')]);
        return [$period, $data];
    }
}
