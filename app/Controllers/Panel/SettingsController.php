<?php
declare(strict_types=1);

namespace App\Controllers\Panel;

use App\Core\Audit;
use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Url;
use App\Core\Validator;
use App\Services\PaymentService;

final class SettingsController extends PanelController
{
    public function index(Request $r): Response
    {
        $t = $this->tid();
        $tenant = Tenant::current();
        $gw = $this->db()->fetch('SELECT tenant_id, gateway, enabled, sandbox, public_key, access_token_enc IS NOT NULL AS has_token, webhook_secret_enc IS NOT NULL AS has_secret, updated_at FROM gateway_credentials WHERE tenant_id = ? AND gateway = ?', [$t, 'mercadopago']);
        return $this->view('panel/settings', [
            'title' => 'Configurações',
            'settings' => $this->db()->fetch('SELECT * FROM store_settings WHERE tenant_id = ?', [$t]),
            'shipping' => $this->db()->fetchAll('SELECT * FROM shipping_methods WHERE tenant_id = ? ORDER BY id', [$t]),
            'gateway' => $gw,
            'webhookUrl' => Url::absolute('webhooks/mercadopago/' . $tenant['uuid']),
            'storeUrl' => Url::storefrontFor($tenant['slug']),
            'activeGateway' => (new PaymentService($this->db(), $tenant))->activeGatewayCode(),
        ]);
    }

    public function saveStore(Request $r): Response
    {
        $d = Validator::check($r->post, [
            'trade_name' => 'required|max:120', 'contact_email' => 'required|email|max:160', 'contact_phone' => 'phone',
            'whatsapp' => 'phone', 'service_hours' => 'max:120', 'about' => 'max:3000',
            'address_line' => 'max:200', 'city' => 'required|max:80', 'state' => 'required|uf', 'zip' => 'cep',
        ], ['trade_name' => 'Nome da loja', 'contact_email' => 'E-mail de atendimento', 'contact_phone' => 'Telefone', 'service_hours' => 'Horário de atendimento', 'about' => 'Sobre a loja', 'address_line' => 'Endereço', 'city' => 'Cidade', 'state' => 'UF', 'zip' => 'CEP']);
        $t = $this->tid();
        $this->db()->execute('UPDATE tenants SET trade_name = ?, address_line = ?, city = ?, state = ?, zip = ?, updated_at = ? WHERE id = ?',
            [$d['trade_name'], $d['address_line'], $d['city'], $d['state'], $d['zip'], Clock::now(), $t]);
        $this->db()->execute('UPDATE store_settings SET contact_email = ?, contact_phone = ?, whatsapp = ?, service_hours = ?, about = ?, updated_at = ? WHERE tenant_id = ?',
            [$d['contact_email'], $d['contact_phone'], $d['whatsapp'], $d['service_hours'], $d['about'], Clock::now(), $t]);
        Audit::panel('settings.store');
        $this->flash('Dados da loja salvos.');
        return $this->redirect('/painel/configuracoes');
    }

    public function savePolicies(Request $r): Response
    {
        $d = Validator::check($r->post, ['policy_privacy' => 'required|max:60000', 'policy_returns' => 'required|max:60000', 'policy_shipping' => 'required|max:60000'],
            ['policy_privacy' => 'Política de privacidade', 'policy_returns' => 'Trocas e devoluções', 'policy_shipping' => 'Entrega']);
        // Nova versão: aceites anteriores ficam registrados com a versão que a pessoa viu.
        $this->db()->execute('UPDATE store_settings SET policy_privacy = ?, policy_returns = ?, policy_shipping = ?, policy_version = policy_version + 1, updated_at = ? WHERE tenant_id = ?',
            [$d['policy_privacy'], $d['policy_returns'], $d['policy_shipping'], Clock::now(), $this->tid()]);
        Audit::panel('settings.policies');
        $this->flash('Políticas salvas (nova versão publicada).');
        return $this->redirect('/painel/configuracoes#politicas');
    }

    public function saveShipping(Request $r): Response
    {
        $d = Validator::check($r->post, [
            'id' => 'int', 'name' => 'required|max:80', 'kind' => 'required|in:delivery,pickup', 'price' => 'money',
            'free_above' => 'money', 'days_min' => 'int|min:0', 'days_max' => 'int|min:0', 'regions' => 'max:255', 'active' => 'bool',
        ], ['name' => 'Nome', 'kind' => 'Tipo', 'price' => 'Valor', 'free_above' => 'Grátis acima de', 'days_min' => 'Prazo mínimo', 'days_max' => 'Prazo máximo', 'regions' => 'UFs atendidas']);
        $regions = $d['regions'] ? implode(',', array_filter(array_map(fn($u) => strtoupper(trim($u)), explode(',', $d['regions'])), fn($u) => in_array($u, Validator::UFS, true))) : null;
        $row = [
            'name' => $d['name'], 'kind' => $d['kind'], 'price_cents' => $d['price'] ?? 0, 'free_above_cents' => $d['free_above'],
            'days_min' => $d['days_min'] ?? 0, 'days_max' => max($d['days_max'] ?? 0, $d['days_min'] ?? 0), 'regions' => $regions, 'active' => $d['active'],
        ];
        $repo = new \App\Repositories\TenantRepository($this->db(), $this->tid(), 'shipping_methods', array_keys($row), false);
        if ($d['id']) {
            $repo->update($d['id'], $row);
        } else {
            $repo->create($row);
        }
        Audit::panel('settings.shipping', 'shipping_method', $d['id']);
        $this->flash('Forma de entrega salva.');
        return $this->redirect('/painel/configuracoes#entrega');
    }

    /** Credenciais do Mercado Pago, cifradas com a APP_KEY. Campos em branco mantêm o valor atual. */
    public function saveGateway(Request $r): Response
    {
        $t = $this->tid();
        $current = $this->db()->fetch('SELECT * FROM gateway_credentials WHERE tenant_id = ? AND gateway = ?', [$t, 'mercadopago']);
        $token = $r->str('access_token');
        $secret = $r->str('webhook_secret');
        $enabled = $r->str('enabled') === '1';
        $tokenEnc = $token !== '' ? Crypto::encrypt($token) : ($current['access_token_enc'] ?? null);
        $secretEnc = $secret !== '' ? Crypto::encrypt($secret) : ($current['webhook_secret_enc'] ?? null);
        if ($enabled && (!$tokenEnc || !$secretEnc)) {
            throw new \App\Core\ValidationException(['access_token' => 'Para ativar, informe o Access Token e a assinatura secreta do webhook.']);
        }
        $this->db()->execute(
            'INSERT INTO gateway_credentials (tenant_id, gateway, enabled, sandbox, public_key, access_token_enc, webhook_secret_enc, updated_at)
             VALUES (?, \'mercadopago\', ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), sandbox = VALUES(sandbox), public_key = VALUES(public_key),
                access_token_enc = VALUES(access_token_enc), webhook_secret_enc = VALUES(webhook_secret_enc), updated_at = VALUES(updated_at)',
            [$t, (int) $enabled, (int) ($r->str('sandbox') === '1'), mb_substr($r->str('public_key'), 0, 200) ?: null, $tokenEnc, $secretEnc, Clock::now()]
        );
        Audit::panel('settings.gateway', 'gateway', 'mercadopago', ['enabled' => $enabled, 'token_changed' => $token !== '', 'secret_changed' => $secret !== '']);
        $this->flash('Configuração de pagamento salva.');
        return $this->redirect('/painel/configuracoes#pagamentos');
    }
}
