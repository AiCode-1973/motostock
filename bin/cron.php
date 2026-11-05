<?php
declare(strict_types=1);

// Tarefas agendadas. Rodar a cada 15 minutos:
//   */15 * * * * php /caminho/motostock/bin/cron.php >> /caminho/motostock/storage/logs/cron.log 2>&1
// Cada tarefa de loja roda com o tenant_id explícito, uma loja por vez.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\RateLimiter;
use App\Services\OrderService;
use App\Services\TenantService;

$db = Database::connect();
$lock = BASE_PATH . '/storage/logs/cron.lock';
$fh = fopen($lock, 'c');
if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
    echo gmdate('c') . " cron já em execução\n";
    exit(0);
}

$start = microtime(true);
$expiredOrders = 0;
foreach ($db->fetchAll("SELECT id FROM tenants WHERE status IN ('trial','active','past_due','suspended')") as $t) {
    try {
        $expiredOrders += (new OrderService($db, (int) $t['id'], null, 'system'))->expireOverdue();
    } catch (Throwable $e) {
        error_log('[MotoStock cron] tenant ' . $t['id'] . ': ' . $e->getMessage());
    }
}
$trials = (new TenantService($db))->expireTrials();
$purged = RateLimiter::purge($db);
printf("%s pedidos_expirados=%d testes_encerrados=%d limites_limpos=%d tempo=%.2fs\n", gmdate('c'), $expiredOrders, $trials, $purged, microtime(true) - $start);
flock($fh, LOCK_UN);
