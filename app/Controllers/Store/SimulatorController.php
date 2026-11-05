<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Url;
use App\Payments\SimulatedGateway;
use App\Services\PaymentService;

/**
 * Tela do "provedor" simulado (somente desenvolvimento/homologação).
 * Aprovar aqui gera uma notificação ASSINADA que percorre o mesmo caminho de um webhook real.
 */
final class SimulatorController extends StoreController
{
    private function payment(string $uuid): array
    {
        if (!Env::bool('SIMULATED_GATEWAY_ENABLED', false)) {
            throw HttpException::notFound();
        }
        return $this->db()->fetch(
            "SELECT p.*, o.number FROM payments p JOIN orders o ON o.tenant_id = p.tenant_id AND o.id = p.order_id
             WHERE p.tenant_id = ? AND p.uuid = ? AND p.gateway = 'simulated'",
            [Tenant::id(), $uuid]
        ) ?? throw HttpException::notFound();
    }

    public function show(Request $r, string $uuid): Response
    {
        return $this->view('store/simulator', ['title' => 'Pagamento simulado', 'payment' => $this->payment($uuid), 'return' => $this->safeReturn($r->str('retorno'))]);
    }

    public function decide(Request $r, string $uuid): Response
    {
        $payment = $this->payment($uuid);
        $status = $r->str('decision') === 'approve' ? 'approved' : 'rejected';
        $gateway = new SimulatedGateway($this->db(), Tenant::current()['uuid']);
        $signed = $gateway->decide((string) $payment['external_id'], $status);
        // Notificação entregue ao mesmo processamento usado pelo endpoint de webhook.
        $webhook = new Request('POST', '/webhooks/simulado/' . Tenant::current()['uuid'], [], [], [], $signed['headers'], body: $signed['body']);
        (new PaymentService($this->db(), Tenant::current()))->handleWebhook('simulated', $webhook);
        $this->flash($status === 'approved' ? 'Pagamento simulado aprovado. O pedido foi atualizado pela notificação do provedor.' : 'Pagamento simulado recusado.', $status === 'approved' ? 'success' : 'error');
        $return = $this->safeReturn($r->str('retorno'));
        return $return ? Response::redirect($return) : $this->toStore('/');
    }

    /** Só aceita voltar para a página de pedido desta loja (evita redirecionamento aberto). */
    private function safeReturn(string $url): ?string
    {
        $base = rtrim(Url::storefrontFor(Tenant::current()['slug']), '/') . '/pedido/';
        return str_starts_with($url, $base) && !str_contains($url, '..') ? $url : null;
    }
}
