<div class="page-head">
  <div><h1>Pedidos</h1><p><?= (int) $result['total'] ?> pedido(s)</p></div>
  <?php if (can('orders.create')): ?><a class="btn" href="<?= e(url('/painel/pedidos/novo')) ?>">+ Venda no balcão</a><?php endif; ?>
</div>
<form class="filters card" method="get" action="<?= e(url('/painel/pedidos')) ?>">
  <div class="field field--wide"><label for="q">Buscar</label><input id="q" type="search" name="q" value="<?= e($query['q'] ?? '') ?>" placeholder="Número, nome ou e-mail do cliente"></div>
  <div class="field"><label for="situacao">Situação</label><select id="situacao" name="situacao" data-autosubmit><option value="">Todas</option><?php foreach (\App\Support\Labels::MAP['order'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($query['situacao'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="canal">Canal</label><select id="canal" name="canal" data-autosubmit><option value="">Todos</option><option value="store"<?= selected($query['canal'] ?? '', 'store') ?>>Loja virtual</option><option value="manual"<?= selected($query['canal'] ?? '', 'manual') ?>>Balcão</option></select></div>
  <button class="btn btn--ghost" type="submit">Filtrar</button>
</form>
<div class="card">
  <?php if (!$result['items']): ?>
    <?php $emptyTitle = 'Nenhum pedido encontrado'; $emptyText = 'Pedidos da loja virtual e vendas no balcão aparecem aqui.'; $emptyAction = can('orders.create') ? ['Registrar venda no balcão', url('/painel/pedidos/novo')] : null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Nº</th><th>Data</th><th>Cliente</th><th>Canal</th><th>Situação</th><th class="num">Total</th></tr></thead>
    <tbody><?php foreach ($result['items'] as $o): ?><tr>
      <td data-label="Nº"><a href="<?= e(url('/painel/pedidos/' . $o['id'])) ?>"><strong><?= (int) $o['number'] ?></strong></a></td>
      <td data-label="Data" class="nowrap"><?= e(dt($o['placed_at'])) ?></td>
      <td data-label="Cliente"><?= e($o['customer_name']) ?></td>
      <td data-label="Canal"><?= e(label('channel', $o['channel'])) ?></td>
      <td data-label="Situação"><?= badge('order', $o['status']) ?></td>
      <td data-label="Total" class="num"><?= e(money((int) $o['total_cents'])) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php include __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</div>
