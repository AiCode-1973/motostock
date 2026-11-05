<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\ValidationException;

/** Planos configuráveis: preços e limites ficam no banco, nunca no código. */
final class PlanController extends Controller
{
    protected string $layout = 'layouts/platform';

    private const RULES = [
        'code' => 'required|slug', 'name' => 'required|max:80', 'description' => 'max:255', 'price' => 'required|money',
        'billing_interval' => 'required|in:month,year', 'trial_days' => 'int|min:0', 'max_users' => 'int|min:1',
        'max_products' => 'int|min:1', 'max_orders_month' => 'int|min:1', 'allow_custom_domain' => 'bool',
        'is_public' => 'bool', 'active' => 'bool', 'sort_order' => 'int',
    ];
    private const LABELS = ['code' => 'Código', 'name' => 'Nome', 'price' => 'Preço', 'trial_days' => 'Dias de teste', 'max_users' => 'Máx. usuários', 'max_products' => 'Máx. produtos', 'max_orders_month' => 'Máx. pedidos/mês'];

    public function index(Request $r): Response
    {
        $plans = $this->db()->fetchAll('SELECT p.*, (SELECT COUNT(*) FROM tenants t WHERE t.plan_id = p.id) AS tenants FROM plans p ORDER BY sort_order, id');
        return $this->view('platform/plans', ['title' => 'Planos', 'plans' => $plans]);
    }

    public function create(Request $r): Response
    {
        return $this->view('platform/plan_form', ['title' => 'Novo plano', 'plan' => null]);
    }

    public function store(Request $r): Response
    {
        $d = $this->data($r);
        if ($this->db()->value('SELECT 1 FROM plans WHERE code = ?', [$d['code']])) {
            throw new ValidationException(['code' => 'Código já usado.']);
        }
        $id = $this->db()->insert('plans', $d + ['created_at' => Clock::now(), 'updated_at' => Clock::now()]);
        Audit::log('platform', 'plan.created', null, Auth::id(), 'plan', $id, ['code' => $d['code'], 'price_cents' => $d['price_cents']]);
        $this->flash('Plano criado.');
        return $this->redirect('/plataforma/planos');
    }

    public function edit(Request $r, int $id): Response
    {
        $plan = $this->db()->fetch('SELECT * FROM plans WHERE id = ?', [$id]) ?? throw HttpException::notFound();
        return $this->view('platform/plan_form', ['title' => $plan['name'], 'plan' => $plan]);
    }

    public function update(Request $r, int $id): Response
    {
        $old = $this->db()->fetch('SELECT * FROM plans WHERE id = ?', [$id]) ?? throw HttpException::notFound();
        $d = $this->data($r);
        if ($this->db()->value('SELECT 1 FROM plans WHERE code = ? AND id <> ?', [$d['code'], $id])) {
            throw new ValidationException(['code' => 'Código já usado.']);
        }
        $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($d)));
        $this->db()->execute("UPDATE plans SET {$sets}, updated_at = ? WHERE id = ?", [...array_values($d), Clock::now(), $id]);
        Audit::log('platform', 'plan.updated', null, Auth::id(), 'plan', $id, ['price_from' => $old['price_cents'], 'price_to' => $d['price_cents']]);
        $this->flash('Plano atualizado. Os limites novos valem imediatamente para as lojas deste plano.');
        return $this->redirect('/plataforma/planos');
    }

    private function data(Request $r): array
    {
        $d = Validator::check($r->post, self::RULES, self::LABELS);
        $d['price_cents'] = $d['price'];
        unset($d['price']);
        $d['trial_days'] ??= 0;
        $d['sort_order'] ??= 0;
        return $d;
    }
}
