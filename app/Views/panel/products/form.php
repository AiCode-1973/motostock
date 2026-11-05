<?php
$p = $product;
$readonly = $readonly ?? false;
$v = fn(string $k, $default = '') => old($k, $p[$k] ?? $default);
$ro = $readonly ? ' disabled' : '';
?>
<div class="page-head">
  <div>
    <p><a href="<?= e(url('/painel/produtos')) ?>">← Produtos</a></p>
    <h1><?= e($p ? $p['name'] : 'Novo produto') ?></h1>
    <?php if ($p): ?><p>SKU <span class="mono"><?= e($p['sku']) ?></span> · físico <?= (int) $p['stock_qty'] ?> · reservado <?= (int) $p['reserved_qty'] ?> · <strong>disponível <?= (int) $p['stock_qty'] - (int) $p['reserved_qty'] ?></strong></p><?php endif; ?>
  </div>
  <?php if ($p): ?><div class="actions">
    <?php if (can('stock.move')): ?><a class="btn btn--ghost" href="<?= e(url('/painel/estoque/movimentar', ['produto' => $p['id']])) ?>">Movimentar estoque</a><?php endif; ?>
    <a class="btn btn--ghost" href="<?= e(url('/painel/estoque/historico', ['produto' => $p['id']])) ?>">Histórico</a>
  </div><?php endif; ?>
</div>
<?php if ($readonly): ?><div class="alert alert--info">Seu perfil pode consultar, mas não editar produtos.</div><?php endif; ?>

<form method="post" action="<?= e(url($p ? '/painel/produtos/' . $p['id'] : '/painel/produtos')) ?>" class="card" novalidate>
  <?= csrf_field() ?>
  <h2>Dados do produto</h2>
  <div class="form-grid">
    <div class="field span-2"><label class="req" for="name">Nome</label><input id="name" name="name" value="<?= e($v('name')) ?>" required maxlength="160"<?= $ro ?><?= field_attrs('name') ?>><span class="hint">Seja específico: tipo de peça, marca e aplicação. Ex.: “Pastilha de freio dianteira Cobreq CG 160”.</span><?= field_error('name') ?></div>
    <div class="field"><label class="req" for="sku">SKU (código interno)</label><input id="sku" name="sku" value="<?= e($v('sku')) ?>" required maxlength="60"<?= $ro ?><?= field_attrs('sku') ?>><?= field_error('sku') ?></div>
    <div class="field"><label for="barcode">Código de barras (EAN)</label><input id="barcode" name="barcode" value="<?= e($v('barcode')) ?>" maxlength="60" inputmode="numeric"<?= $ro ?>></div>
    <div class="field"><label for="category_id">Categoria</label><select id="category_id" name="category_id"<?= $ro ?><?= field_attrs('category_id') ?>><option value="">Sem categoria</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($v('category_id'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><?= field_error('category_id') ?></div>
    <div class="field"><label for="brand_id">Marca do produto</label><select id="brand_id" name="brand_id"<?= $ro ?><?= field_attrs('brand_id') ?>><option value="">Sem marca</option><?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>"<?= selected($v('brand_id'), $b['id']) ?>><?= e($b['name']) ?></option><?php endforeach; ?></select><span class="hint"><a href="<?= e(url('/painel/catalogo')) ?>">Gerenciar categorias e marcas</a></span></div>
    <div class="field"><label class="req" for="price_cents">Preço de venda (R$)</label><input id="price_cents" name="price_cents" value="<?= e(old('price_cents', $p ? money_input((int) $p['price_cents']) : '')) ?>" inputmode="decimal" placeholder="129,90" required<?= $ro ?><?= field_attrs('price_cents') ?>><?= field_error('price_cents') ?></div>
    <?php if (can('products.cost')): ?>
    <div class="field"><label for="cost_cents">Custo (R$)</label><input id="cost_cents" name="cost_cents" value="<?= e(old('cost_cents', $p ? money_input((int) $p['cost_cents']) : '')) ?>" inputmode="decimal" placeholder="80,00"<?= $ro ?><?= field_attrs('cost_cents') ?>>
      <?php if ($p && $p['price_cents'] > 0): ?><span class="hint">Margem bruta: <?= round((($p['price_cents'] - $p['cost_cents']) / $p['price_cents']) * 100) ?>%. O recebimento de compras atualiza o custo.</span><?php endif; ?><?= field_error('cost_cents') ?></div>
    <?php endif; ?>
    <div class="field"><label for="min_stock">Estoque mínimo</label><input id="min_stock" name="min_stock" type="number" min="0" value="<?= e($v('min_stock', 0)) ?>"<?= $ro ?><?= field_attrs('min_stock') ?>><span class="hint">Alerta de estoque baixo quando o disponível chegar aqui.</span><?= field_error('min_stock') ?></div>
    <div class="field"><label for="weight_grams">Peso (g)</label><input id="weight_grams" name="weight_grams" type="number" min="0" value="<?= e($v('weight_grams')) ?>"<?= $ro ?>><span class="hint">Para cotação de frete futura.</span></div>
    <?php if (!$p): ?>
    <div class="field"><label for="initial_stock">Saldo inicial</label><input id="initial_stock" name="initial_stock" type="number" min="0" value="<?= e(old('initial_stock', 0)) ?>"><span class="hint">Gera uma entrada “Saldo inicial do cadastro”.</span></div>
    <?php endif; ?>
    <div class="field span-2"><label for="description">Descrição</label><textarea id="description" name="description" maxlength="5000"<?= $ro ?>><?= e($v('description')) ?></textarea><span class="hint">Material, medidas, o que vem na embalagem, garantia.</span></div>
    <label class="check span-2"><input type="checkbox" name="active" value="1"<?= checked($v('active', 1)) ?><?= $ro ?>> Ativo (aparece na loja virtual e nas vendas)</label>
  </div>
  <?php if (!$readonly): ?><div class="actions"><button class="btn" type="submit">Salvar produto</button></div><?php endif; ?>
</form>

<?php if ($p): ?>
<div class="grid grid-2">
  <div class="card">
    <h2>Fotos</h2>
    <?php if (!$images): ?><p class="muted">Sem fotos. Produtos com foto vendem mais.</p><?php endif; ?>
    <div class="products">
      <?php foreach ($images as $img): ?>
        <div class="product-card">
          <div class="thumb"><img src="<?= e(url('/painel/midia/' . $img['id'])) ?>" alt="Foto do produto" loading="lazy"></div>
          <?php if (!$readonly): ?><form method="post" action="<?= e(url('/painel/produtos/' . $p['id'] . '/imagens/' . $img['id'] . '/excluir')) ?>" class="body" data-confirm="Excluir esta foto?"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit">Excluir</button></form><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$readonly): ?>
    <form method="post" action="<?= e(url('/painel/produtos/' . $p['id'] . '/imagens')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field"><label for="image">Enviar foto</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required><span class="hint">JPG, PNG ou WebP até 2 MB. Até 8 fotos.</span></div>
      <div class="actions"><button class="btn btn--ghost" type="submit" data-loading="Enviando…">Enviar</button></div>
    </form>
    <?php endif; ?>
  </div>
  <div class="card" id="compatibilidade">
    <h2>Compatibilidade com motos</h2>
    <?php if (!$fitments): ?><p class="muted">Nenhuma moto cadastrada. Sem compatibilidade, o cliente não consegue filtrar por moto.</p><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Moto</th><th>Anos</th><th>Observação</th><th></th></tr></thead>
      <tbody><?php foreach ($fitments as $f): ?><tr>
        <td><?= e($f['make'] . ' ' . $f['model']) ?></td><td class="nowrap"><?= (int) $f['year_from'] ?>–<?= (int) $f['year_to'] ?></td><td><?= e($f['notes'] ?? '') ?></td>
        <td><?php if (!$readonly): ?><form method="post" action="<?= e(url('/painel/produtos/' . $p['id'] . '/compatibilidade/' . $f['id'] . '/excluir')) ?>" data-confirm="Remover esta compatibilidade?"><?= csrf_field() ?><button class="link-btn" type="submit">Remover</button></form><?php endif; ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
    <?php if (!$readonly): ?>
    <form method="post" action="<?= e(url('/painel/produtos/' . $p['id'] . '/compatibilidade')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field"><label class="req" for="make">Marca da moto</label><input id="make" name="make" value="<?= e(old('make')) ?>" placeholder="Honda" required<?= field_attrs('make') ?>><?= field_error('make') ?></div>
        <div class="field"><label class="req" for="model">Modelo</label><input id="model" name="model" value="<?= e(old('model')) ?>" placeholder="CG 160 Titan" required<?= field_attrs('model') ?>><?= field_error('model') ?></div>
        <div class="field"><label class="req" for="year_from">De (ano)</label><input id="year_from" name="year_from" inputmode="numeric" maxlength="4" value="<?= e(old('year_from')) ?>" required<?= field_attrs('year_from') ?>><?= field_error('year_from') ?></div>
        <div class="field"><label class="req" for="year_to">Até (ano)</label><input id="year_to" name="year_to" inputmode="numeric" maxlength="4" value="<?= e(old('year_to')) ?>" required<?= field_attrs('year_to') ?>><?= field_error('year_to') ?></div>
        <div class="field span-2"><label for="notes">Observação</label><input id="notes" name="notes" maxlength="255" value="<?= e(old('notes')) ?>" placeholder="Ex.: somente versão com freio a disco"></div>
      </div>
      <div class="actions"><button class="btn btn--ghost" type="submit">Adicionar moto</button></div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
