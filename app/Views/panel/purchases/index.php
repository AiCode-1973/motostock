<div class="page-head">
  <div><h1>Pedidos de compra</h1><p>Do rascunho ao recebimento, que dá entrada no estoque.</p></div>
  <?php if (can('purchases.manage')): ?><a class="btn" href="<?= e(url('/painel/compras/nova')) ?>">+ Nova compra</a><?php endif; ?>
</div>
<form class="filters" method="get" action="<?= e(url('/painel/compras')) ?>">
  <div class="field"><label for="situacao">Situação</label><select id="situacao" name="situacao" data-autosubmit><option value="">Todas</option><?php foreach (\App\Support\Labels::MAP['purchase'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($query['situacao'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
</form>
<div class="card">
  <?php if (!$items): ?>
    <?php $emptyTitle = 'Nenhuma compra'; $emptyText = 'Registre pedidos aos fornecedores para controlar prazos e dar entrada no estoque ao receber.'; $emptyAction = can('purchases.manage') ? ['Nova compra', url('/painel/compras/nova')] : null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Nº</th><th>Fornecedor</th><th>Criada em</th><th>Previsão</th><th>Situação</th><th class="num">Total</th></tr></thead>
    <tbody><?php foreach ($items as $po): ?><tr>
      <td data-label="Nº"><a href="<?= e(url('/painel/compras/' . $po['id'])) ?>"><?= (int) $po['number'] ?></a></td>
      <td data-label="Fornecedor"><?= e($po['supplier_name']) ?></td>
      <td data-label="Criada em"><?= e(dt($po['created_at'], 'd/m/Y')) ?></td>
      <td data-label="Previsão"><?= $po['expected_at'] ? e(date('d/m/Y', strtotime($po['expected_at']))) : '—' ?></td>
      <td data-label="Situação"><?= badge('purchase', $po['status']) ?></td>
      <td data-label="Total" class="num"><?= e(money((int) $po['total_cents'])) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
