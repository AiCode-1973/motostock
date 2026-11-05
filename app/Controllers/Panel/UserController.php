<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Request;
use App\Core\Response;
use App\Services\PlanLimitService;
use App\Services\UserService;

final class UserController extends PanelController
{
    public function index(Request $r): Response
    {
        $limits = new PlanLimitService($this->db(), $this->tid());
        return $this->view('panel/users', [
            'title' => 'Equipe',
            'members' => (new UserService($this->db()))->members($this->tid()),
            'plan' => $limits->plan(),
            'usage' => $limits->usage(),
        ]);
    }

    public function store(Request $r): Response
    {
        (new UserService($this->db()))->addMember($this->tid(), $r->post, (int) $this->uid());
        $this->flash('Pessoa adicionada. Ela recebeu um e-mail para definir a senha.');
        return $this->redirect('/painel/usuarios');
    }

    public function update(Request $r, int $id): Response
    {
        (new UserService($this->db()))->updateMember($this->tid(), $id, $r->str('role'), $r->str('status'), (int) $this->uid());
        $this->flash('Acesso atualizado.');
        return $this->redirect('/painel/usuarios');
    }

    public function audit(Request $r): Response
    {
        $rows = $this->db()->fetchAll(
            'SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             WHERE a.tenant_id = ? AND a.area IN (\'panel\', \'system\', \'store\') ORDER BY a.id DESC LIMIT 200',
            [$this->tid()]
        );
        return $this->view('panel/audit', ['title' => 'Auditoria da loja', 'rows' => $rows]);
    }
}
