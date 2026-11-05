<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

/** Notificações de provedores de pagamento. O tenant vem da URL e é confirmado pela assinatura. */
final class WebhookController extends Controller
{
    public function mercadoPago(Request $r, string $uuid): Response
    {
        return $this->handle($r, $uuid, 'mercadopago');
    }

    public function simulated(Request $r, string $uuid): Response
    {
        if (!\App\Core\Env::bool('SIMULATED_GATEWAY_ENABLED', false)) {
            return Response::json(['error' => 'desativado'], 404);
        }
        return $this->handle($r, $uuid, 'simulated');
    }

    private function handle(Request $r, string $uuid, string $gateway): Response
    {
        $tenant = Database::connect()->fetch('SELECT * FROM tenants WHERE uuid = ?', [$uuid]);
        if (!$tenant) {
            return Response::json(['error' => 'desconhecido'], 404);
        }
        try {
            [$status, $message] = (new PaymentService(Database::connect(), $tenant))->handleWebhook($gateway, $r);
        } catch (\App\Core\BusinessException $e) {
            return Response::json(['error' => 'gateway não configurado'], 409);
        } catch (\Throwable $e) {
            error_log('[MotoStock webhook] ' . $e->getMessage());
            // 500 faz o provedor reenviar mais tarde.
            return Response::json(['error' => 'falha temporária'], 500);
        }
        return Response::json(['result' => $message], $status);
    }
}
