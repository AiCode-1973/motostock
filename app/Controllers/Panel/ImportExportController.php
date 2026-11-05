<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogImportService;

final class ImportExportController extends PanelController
{
    public function export(Request $r): Response
    {
        $csv = (new CatalogImportService($this->db(), $this->tid(), $this->uid()))->export();
        Audit::panel('catalog.exported');
        return Response::download($csv, 'catalogo-' . gmdate('Ymd') . '.csv', 'text/csv; charset=utf-8');
    }

    public function form(Request $r): Response
    {
        $logs = $this->db()->fetchAll('SELECT l.*, u.name AS user_name FROM import_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.tenant_id = ? ORDER BY l.id DESC LIMIT 10', [$this->tid()]);
        return $this->view('panel/import', ['title' => 'Importar catálogo', 'logs' => $logs, 'result' => null]);
    }

    public function import(Request $r): Response
    {
        $file = $r->files['file'] ?? null;
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw new BusinessException('Escolha um arquivo CSV.');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new BusinessException('O arquivo pode ter no máximo 2 MB (cerca de 5.000 linhas).');
        }
        $result = (new CatalogImportService($this->db(), $this->tid(), $this->uid()))
            ->import((string) file_get_contents($file['tmp_name']), (string) $file['name']);
        $logs = $this->db()->fetchAll('SELECT l.*, u.name AS user_name FROM import_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.tenant_id = ? ORDER BY l.id DESC LIMIT 10', [$this->tid()]);
        return $this->view('panel/import', ['title' => 'Importar catálogo', 'logs' => $logs, 'result' => $result]);
    }
}
