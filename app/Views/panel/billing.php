<?php
$meter = function (int $used, $limit): string {
    if ($limit === null) return '<span class="small muted">sem limite</span>';
    $pct = min(100, (int) floor(($used / max(1, (int) $limit)) * 10) * 10);
    return '<div class="meter"><span class="w-' . $pct . '"></span></div>';
};
?>
<div class="page-head"><div><h1>Assinatura</h1><p>Plano <?= e($plan['name']) ?> · <?= badge('tenant', $tenant['status']) ?></p></div></div>
<?php if (in_array($tenant['status'], ['suspended', 'cancelled'], true)): ?>
  <div class="alert alert--error">Sua loja está <?= e(mb_strtolower(label('tenant', $tenant['status']))) ?>. <?= e($tenant['status_reason'] ?? '') ?> Nenhum dado foi apagado. Fale com o suporte MotoStock para regularizar.</div>
<?php endif; ?>
<div class="grid grid-2">
  <div class="card">
    <h2>Uso do plano</h2>
    <ul class="list-plain">
      <li><span>Produtos: <?= (int) $usage['products'] ?><?= $plan['max_products'] !== null ? ' de ' . (int) $plan['max_products'] : '' ?></span></li>
      <li><?= $meter($usage['products'], $plan['max_products']) ?></li>
      <li><span>Usuários ativos: <?= (int) $usage['users'] ?><?= $plan['max_users'] !== null ? ' de ' . (int) $plan['max_users'] : '' ?></span></li>
      <li><?= $meter($usage['users'], $plan['max_users']) ?></li>
      <li><span>Pedidos neste mês: <?= (int) $usage['orders'] ?><?= $plan['max_orders_month'] !== null ? ' de ' . (int) $plan['max_orders_month'] : '' ?></span></li>
      <li><?= $meter($usage['orders'], $plan['max_orders_month']) ?></li>
    </ul>
    <p class="small muted">Ao atingir um limite, o sistema impede novos cadastros daquele tipo e explica o motivo. Nada existente é bloqueado ou apagado.</p>
  </div>
  <div class="card">
    <h2>Situação da assinatura</h2>
    <dl class="dl">
      <dt>Plano</dt><dd><?= e($plan['name']) ?> · <?= e(money((int) $plan['price_cents'])) ?>/<?= $plan['billing_interval'] === 'year' ? 'ano' : 'mês' ?></dd>
      <dt>Situação</dt><dd><?= $subscription ? badge('subscription', $subscription['status']) : '—' ?></dd>
      <?php if ($tenant['trial_ends_at']): ?><dt>Teste até</dt><dd><?= e(dt($tenant['trial_ends_at'], 'd/m/Y')) ?></dd><?php endif; ?>
    </dl>
    <div class="alert alert--info small">A cobrança automática da assinatura ainda não está integrada (depende de contrato com gateway de cobrança recorrente). Por enquanto, a equipe MotoStock confirma pagamentos e muda de plano manualmente.</div>
    <?php if (can('data.export')): ?>
      <h3>Exportar todos os dados da loja</h3>
      <p class="small muted">Arquivo JSON com catálogo, estoque, clientes, pedidos, compras e histórico (sem senhas e credenciais). Use para portabilidade ou ao encerrar o contrato.</p>
      <a class="btn btn--ghost" href="<?= e(url('/painel/exportar-dados')) ?>">Baixar exportação completa</a>
    <?php endif; ?>
  </div>
</div>
