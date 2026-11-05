<div class="page-head"><div><h1>Meus pedidos</h1><p><?= e($customer['name']) ?> · <?= e($customer['email']) ?></p></div>
  <form method="post" action="<?= e(store_url('/conta/sair')) ?>"><?= csrf_field() ?><button class="btn btn--ghost" type="submit" data-no-loading>Sair</button></form></div>
<div class="card">
  <?php if (!$orders): ?>
    <?php $emptyTitle = 'Você ainda não fez pedidos'; $emptyText = null; $emptyAction = ['Ver peças', store_url('/')]; include __DIR__ . '/../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack"><thead><tr><th>Pedido</th><th>Data</th><th>Situação</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td data-label="Pedido">nº <?= (int) $o['number'] ?></td><td data-label="Data"><?= e(dt($o['placed_at'])) ?></td><td data-label="Situação"><?= badge('order', $o['status']) ?></td><td data-label="Total" class="num"><?= e(money((int) $o['total_cents'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="small muted">O link completo de acompanhamento de cada pedido foi enviado para o seu e-mail.</p>
  <?php endif; ?>
</div>
<p class="small"><a href="<?= e(store_url('/privacidade/solicitar')) ?>">Solicitar acesso, correção ou eliminação dos meus dados</a></p>
