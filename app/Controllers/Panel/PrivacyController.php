<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/** Pedidos de titulares (LGPD art. 18) recebidos pela loja. */
final class PrivacyController extends PanelController
{
    public function index(Request $r): Response
    {
        $rows = $this->db()->fetchAll(
            'SELECT d.*, c.name AS customer_name, u.name AS resolver FROM data_requests d
             LEFT JOIN customers c ON c.tenant_id = d.tenant_id AND c.id = d.customer_id
             LEFT JOIN users u ON u.id = d.resolved_by WHERE d.tenant_id = ? ORDER BY d.status = \'open\' DESC, d.id DESC LIMIT 200',
            [$this->tid()]
        );
        return $this->view('panel/privacy', ['title' => 'Privacidade (LGPD)', 'rows' => $rows]);
    }

    public function resolve(Request $r, int $id): Response
    {
        $status = $r->str('status');
        $resolution = $r->str('resolution');
        if (!in_array($status, ['done', 'rejected'], true) || $resolution === '') {
            throw new BusinessException('Escolha o resultado e descreva o que foi feito (fica registrado).');
        }
        $n = $this->db()->execute('UPDATE data_requests SET status = ?, resolution = ?, resolved_by = ?, resolved_at = ? WHERE tenant_id = ? AND id = ?',
            [$status, mb_substr($resolution, 0, 2000), $this->uid(), Clock::now(), $this->tid(), $id]);
        if ($n === 0) {
            throw HttpException::notFound();
        }
        Audit::panel('privacy.request_resolved', 'data_request', $id, ['status' => $status]);
        $this->flash('Solicitação registrada como ' . ($status === 'done' ? 'atendida' : 'negada') . '.');
        return $this->redirect('/painel/privacidade');
    }
}
