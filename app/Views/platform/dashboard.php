<?php $by = array_column($byStatus, 'n', 'status'); ?>
<div class="page-head"><div><h1>Visão geral da plataforma</h1><p>Sem dados de clientes ou pedidos das lojas: só situação e uso. <?= (int) $demoCount ?> loja(s) de demonstração fora da contagem.</p></div></div>
<div class="kpis">
  <?php foreach (['active' => 'Ativas', 'trial' => 'Em teste', 'past_due' => 'Pagamento pendente', 'suspended' => 'Suspensas', 'cancelled' => 'Canceladas'] as $k => $l): ?>
    <a class="kpi" href="<?= e(url('/plataforma/lojas', ['situacao' => $k])) ?>"><div class="kpi-label"><?= e($l) ?></div><div class="kpi-value"><?= (int) ($by[$k] ?? 0) ?></div></a>
  <?php endforeach; ?>
</div>
<?php if ($webhookFailures > 0): ?><div class="alert alert--warn"><?= (int) $webhookFailures ?> notificação(ões) de pagamento com falha nos últimos 7 dias (ex.: valor divergente). Verifique com as lojas.</div><?php endif; ?>
<div class="grid grid-2">
  <div class="card"><h2>Lojas por plano</h2><ul class="list-plain"><?php foreach ($byPlan as $p): ?><li><span><?= e($p['name']) ?></span><strong><?= (int) $p['n'] ?></strong></li><?php endforeach; ?></ul></div>
  <div class="card"><h2>Testes terminando em 7 dias</h2>
    <?php if (!$trialsEnding): ?><p class="muted">Nenhum.</p><?php else: ?><ul class="list-plain"><?php foreach ($trialsEnding as $t): ?><li><a href="<?= e(url('/plataforma/lojas/' . $t['id'])) ?>"><?= e($t['trade_name']) ?></a><span><?= e(dt($t['trial_ends_at'], 'd/m/Y', 'America/Sao_Paulo')) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
  <div class="card"><h2>Eventos recentes</h2>
    <?php if (!$events): ?><p class="muted">Nenhum.</p><?php else: ?><ul class="list-plain"><?php foreach ($events as $e): ?><li><span class="mono small"><?= e($e['action']) ?></span><span class="small"><?= e($e['trade_name'] ?? '') ?> · <?= e(dt($e['created_at'], 'd/m H:i', 'America/Sao_Paulo')) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
</div>
