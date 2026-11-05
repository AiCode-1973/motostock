<div class="page-head">
  <div><p><a href="<?= e(url('/painel/estoque')) ?>">← Estoque</a></p><h1>Histórico de estoque</h1><?php if ($product): ?><p><?= e($product['name']) ?> · <span class="mono"><?= e($product['sku']) ?></span> · <a href="<?= e(url('/painel/estoque/historico')) ?>">ver todos os produtos</a></p><?php endif; ?></div>
  <form class="filters" method="get" action="<?= e(url('/painel/estoque/historico')) ?>">
    <?php if ($product): ?><input type="hidden" name="produto" value="<?= (int) $product['id'] ?>"><?php endif; ?>
    <div class="field"><label for="tipo">Tipo</label><select id="tipo" name="tipo" data-autosubmit><option value="">Todos</option><?php foreach (\App\Support\Labels::MAP['movement'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($query['tipo'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  </form>
</div>
<div class="card">
  <?php if (!$result['items']): ?>
    <?php $emptyTitle = 'Nenhuma movimentação'; $emptyText = 'Entradas, saídas, ajustes e reservas aparecem aqui.'; $emptyAction = null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th class="num">Qtd.</th><th class="num">Físico após</th><th class="num">Reserv. após</th><th>Motivo</th><th>Por</th></tr></thead>
    <tbody><?php foreach ($result['items'] as $m): ?><tr>
      <td data-label="Data" class="nowrap"><?= e(dt($m['created_at'])) ?></td>
      <td data-label="Produto"><a href="<?= e(url('/painel/estoque/historico', ['produto' => $m['product_id']])) ?>"><?= e($m['product_name']) ?></a></td>
      <td data-label="Tipo"><?= e(label('movement', $m['type'])) ?></td>
      <td data-label="Qtd." class="num"><?= (int) $m['quantity'] > 0 ? '+' : '' ?><?= (int) $m['quantity'] ?></td>
      <td data-label="Físico após" class="num"><?= (int) $m['stock_after'] ?></td>
      <td data-label="Reserv. após" class="num"><?= (int) $m['reserved_after'] ?></td>
      <td data-label="Motivo"><?= e($m['reason']) ?><?php if ($m['order_id'] && can('orders.view')): ?> · <a href="<?= e(url('/painel/pedidos/' . $m['order_id'])) ?>">pedido</a><?php endif; ?><?php if ($m['purchase_order_id'] && can('purchases.view')): ?> · <a href="<?= e(url('/painel/compras/' . $m['purchase_order_id'])) ?>">compra</a><?php endif; ?></td>
      <td data-label="Por"><?= e($m['user_name'] ?? 'Sistema/cliente') ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php include __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</div>
