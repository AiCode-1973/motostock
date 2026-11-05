<div class="page-head">
  <div><h1>Estoque</h1><p>Disponível = físico − reservado em pedidos aguardando pagamento.</p></div>
  <div class="actions">
    <a class="btn btn--ghost" href="<?= e(url('/painel/estoque/historico')) ?>">Histórico</a>
    <?php if (can('stock.move')): ?><a class="btn" href="<?= e(url('/painel/estoque/movimentar')) ?>">Movimentar</a><?php endif; ?>
  </div>
</div>
<form class="filters card" method="get" action="<?= e(url('/painel/estoque')) ?>">
  <div class="field field--wide"><label for="q">Buscar</label><input id="q" type="search" name="q" value="<?= e($query['q'] ?? '') ?>" placeholder="Nome, SKU ou código de barras"></div>
  <label class="check"><input type="checkbox" name="baixo" value="1"<?= checked(($query['baixo'] ?? '') === '1') ?> data-autosubmit> Só estoque baixo</label>
  <button class="btn btn--ghost" type="submit">Filtrar</button>
</form>
<div class="card">
  <?php if (!$result['items']): ?>
    <?php $emptyTitle = 'Nenhum produto ativo encontrado'; $emptyText = ($query['baixo'] ?? '') ? 'Nenhum produto está com estoque baixo.' : 'Cadastre produtos para controlar o estoque.'; $emptyAction = null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Produto</th><th>SKU</th><th class="num">Físico</th><th class="num">Reservado</th><th class="num">Disponível</th><th class="num">Mínimo</th><th></th></tr></thead>
    <tbody><?php foreach ($result['items'] as $p): $low = (int) $p['available'] <= (int) $p['min_stock']; ?>
      <tr>
        <td data-label="Produto"><a href="<?= e(url('/painel/produtos/' . $p['id'])) ?>"><?= e($p['name']) ?></a></td>
        <td data-label="SKU" class="mono"><?= e($p['sku']) ?></td>
        <td data-label="Físico" class="num"><?= (int) $p['stock_qty'] ?></td>
        <td data-label="Reservado" class="num"><?= (int) $p['reserved_qty'] ?></td>
        <td data-label="Disponível" class="num"><?= $low ? '<span class="badge badge--low">' . (int) $p['available'] . '</span>' : (int) $p['available'] ?></td>
        <td data-label="Mínimo" class="num"><?= (int) $p['min_stock'] ?></td>
        <td><?php if (can('stock.move')): ?><a class="small" href="<?= e(url('/painel/estoque/movimentar', ['produto' => $p['id']])) ?>">Movimentar</a> · <?php endif; ?><a class="small" href="<?= e(url('/painel/estoque/historico', ['produto' => $p['id']])) ?>">Histórico</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php include __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</div>
