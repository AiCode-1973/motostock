<div class="page-head"><div><h1>Categorias e marcas</h1><p>Organizam o catálogo e os filtros da loja virtual.</p></div></div>
<div class="grid grid-2">
  <?php foreach ([['Categorias', $categories, 'category', '/painel/categorias', 'Ex.: Freios, Transmissão, Elétrica'], ['Marcas de produto', $brands, 'brand', '/painel/marcas', 'Ex.: Cobreq, Riffel, NGK']] as [$label, $rows, $kind, $action, $ph]): ?>
  <div class="card">
    <h2><?= e($label) ?></h2>
    <?php if (can('products.manage')): ?>
    <form method="post" action="<?= e(url($action)) ?>" class="filters">
      <?= csrf_field() ?>
      <div class="field field--wide"><label for="<?= $kind ?>_name">Nova</label><input id="<?= $kind ?>_name" name="<?= $kind ?>_name" placeholder="<?= e($ph) ?>" required maxlength="80"<?= field_attrs($kind . '_name') ?>><?= field_error($kind . '_name') ?></div>
      <button class="btn" type="submit">Adicionar</button>
    </form>
    <?php endif; ?>
    <?php if (!$rows): ?><p class="muted">Nenhuma cadastrada.</p><?php else: ?>
    <ul class="list-plain">
      <?php foreach ($rows as $row): ?><li><span><?= e($row['name']) ?> <span class="muted small"><?= (int) $row['n'] ?> produto(s)</span></span>
        <?php if (can('products.manage') && (int) $row['n'] === 0): ?><form method="post" action="<?= e(url($action . '/' . $row['id'] . '/excluir')) ?>" data-confirm="Excluir “<?= e($row['name']) ?>”?"><?= csrf_field() ?><button class="link-btn" type="submit">Excluir</button></form><?php endif; ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
