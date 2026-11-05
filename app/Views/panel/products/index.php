<div class="page-head">
  <div><h1>Produtos</h1><p><?= (int) $result['total'] ?> produto(s)</p></div>
  <div class="actions">
    <?php if (can('catalog.import')): ?><a class="btn btn--ghost" href="<?= e(url('/painel/catalogo/importar')) ?>">Importar / exportar</a><?php endif; ?>
    <?php if (can('products.manage')): ?><a class="btn" href="<?= e(url('/painel/produtos/novo')) ?>">+ Novo produto</a><?php endif; ?>
  </div>
</div>
<form class="filters card" method="get" action="<?= e(url('/painel/produtos')) ?>">
  <div class="field field--wide"><label for="q">Buscar</label><input id="q" type="search" name="q" value="<?= e($query['q'] ?? '') ?>" placeholder="Nome, SKU, código de barras ou marca"></div>
  <div class="field"><label for="categoria">Categoria</label><select id="categoria" name="categoria" data-autosubmit><option value="">Todas</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($query['categoria'] ?? '', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="marca">Marca</label><select id="marca" name="marca" data-autosubmit><option value="">Todas</option><?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>"<?= selected($query['marca'] ?? '', $b['id']) ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="situacao">Situação</label><select id="situacao" name="situacao" data-autosubmit><option value="">Todos</option><option value="ativos"<?= selected($query['situacao'] ?? '', 'ativos') ?>>Ativos</option><option value="inativos"<?= selected($query['situacao'] ?? '', 'inativos') ?>>Inativos</option></select></div>
  <label class="check"><input type="checkbox" name="baixo" value="1"<?= checked(($query['baixo'] ?? '') === '1') ?> data-autosubmit> Estoque baixo</label>
  <button class="btn btn--ghost" type="submit">Filtrar</button>
</form>
<div class="card">
  <?php if (!$result['items']): ?>
    <?php $emptyTitle = ($query ?? []) ? 'Nenhum produto encontrado' : 'Nenhum produto cadastrado'; $emptyText = ($query ?? []) ? 'Tente outros filtros.' : 'Cadastre o primeiro produto ou importe uma planilha.'; $emptyAction = can('products.manage') ? ['Cadastrar produto', url('/painel/produtos/novo')] : null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Produto</th><th>SKU</th><th>Categoria / marca</th><th class="num">Preço</th><?php if (can('products.cost')): ?><th class="num">Margem</th><?php endif; ?><th class="num">Disponível</th><th>Situação</th></tr></thead>
    <tbody>
    <?php foreach ($result['items'] as $p): $margin = $p['price_cents'] > 0 ? round((($p['price_cents'] - $p['cost_cents']) / $p['price_cents']) * 100) : 0; ?>
      <tr>
        <td data-label="Produto"><a href="<?= e(url('/painel/produtos/' . $p['id'])) ?>"><?= e($p['name']) ?></a><?php if ($p['is_demo']): ?> <span class="badge badge--demo">demo</span><?php endif; ?></td>
        <td data-label="SKU" class="mono"><?= e($p['sku']) ?></td>
        <td data-label="Categoria"><?= e($p['category_name'] ?? '—') ?> <span class="muted">· <?= e($p['brand_name'] ?? '—') ?></span></td>
        <td data-label="Preço" class="num"><?= e(money((int) $p['price_cents'])) ?></td>
        <?php if (can('products.cost')): ?><td data-label="Margem" class="num"><?= (int) $margin ?>%</td><?php endif; ?>
        <td data-label="Disponível" class="num"><?php if ((int) $p['available'] <= (int) $p['min_stock']): ?><span class="badge badge--low"><?= (int) $p['available'] ?></span><?php else: ?><?= (int) $p['available'] ?><?php endif; ?><?php if ($p['reserved_qty'] > 0): ?> <span class="muted small">(+<?= (int) $p['reserved_qty'] ?> res.)</span><?php endif; ?></td>
        <td data-label="Situação"><?= $p['active'] ? 'Ativo' : '<span class="muted">Inativo</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php include __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</div>
