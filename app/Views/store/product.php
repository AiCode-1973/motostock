<div class="product-page">
  <div class="gallery">
    <div class="main-img"><?php if ($images): ?><img data-main-img src="<?= e(store_url('/midia/' . $images[0]['id'])) ?>" alt="<?= e($p['name']) ?>"><?php else: ?>Sem foto<?php endif; ?></div>
    <?php if (count($images) > 1): ?><div class="thumbs"><?php foreach ($images as $img): ?><a data-thumb href="<?= e(store_url('/midia/' . $img['id'])) ?>"><img src="<?= e(store_url('/midia/' . $img['id'])) ?>" alt="Outra foto de <?= e($p['name']) ?>" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="buy-box">
    <p class="small muted"><a href="<?= e(store_url('/')) ?>">Peças</a><?= $p['category_name'] ? ' › <a href="' . e(store_url('/', ['categoria' => $p['category_id']])) . '">' . e($p['category_name']) . '</a>' : '' ?></p>
    <h1><?= e($p['name']) ?></h1>
    <p class="muted small">Marca: <?= e($p['brand_name'] ?? '—') ?> · SKU <?= e($p['sku']) ?><?= $p['barcode'] ? ' · EAN ' . e($p['barcode']) : '' ?></p>
    <div class="price"><?= e(money((int) $p['price_cents'])) ?></div>
    <p class="<?= $available > 0 ? 'stock-ok' : 'stock-out' ?>"><?= $available > 0 ? ($available <= 3 ? "Últimas {$available} unidade(s)" : 'Em estoque') : 'Indisponível no momento' ?></p>

    <div class="fit-box">
      <h2>Compatibilidade</h2>
      <?php if ($fitments): ?>
        <ul><?php foreach ($fitments as $f): ?><li><?= e($f['make'] . ' ' . $f['model']) ?> · <?= (int) $f['year_from'] ?>–<?= (int) $f['year_to'] ?><?= $f['notes'] ? ' <span class="small">(' . e($f['notes']) . ')</span>' : '' ?></li><?php endforeach; ?></ul>
      <?php else: ?>
        <p class="small">A loja não informou motos compatíveis para esta peça. Antes de comprar, confirme com o atendimento.</p>
      <?php endif; ?>
    </div>

    <?php if ($available > 0): ?>
    <form method="post" action="<?= e(store_url('/carrinho/adicionar')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
      <div class="form-grid">
        <div class="field"><label for="quantity">Quantidade</label><input id="quantity" name="quantity" type="number" min="1" max="<?= min(999, $available) ?>" value="1"></div>
        <div class="field"><label for="fitment_note">Sua moto (marca, modelo e ano)</label><input id="fitment_note" name="fitment_note" maxlength="160" placeholder="Ex.: Honda CG 160 Fan 2021"><span class="hint">Opcional. Ajuda a loja a conferir a compatibilidade antes de enviar.</span></div>
      </div>
      <div class="actions"><button class="btn btn--block" type="submit">Adicionar ao carrinho</button></div>
    </form>
    <?php else: ?>
      <a class="btn btn--ghost btn--block" href="<?= e(store_url('/contato')) ?>">Avise-me / consultar prazo</a>
    <?php endif; ?>

    <h2>Entrega</h2>
    <?php if (!$shipping): ?><p class="small muted">Consulte a loja.</p><?php else: ?>
      <ul class="small"><?php foreach ($shipping as $m): ?><li><?= e($m['name']) ?>: <?= (int) $m['price_cents'] === 0 ? 'grátis' : e(money((int) $m['price_cents'])) ?><?= $m['free_above_cents'] !== null ? ' (grátis acima de ' . e(money((int) $m['free_above_cents'])) . ')' : '' ?> · <?= e(\App\Services\OrderService::daysLabel($m)) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <p class="small"><a href="<?= e(store_url('/trocas-e-devolucoes')) ?>">Trocas e direito de arrependimento (7 dias)</a></p>
  </div>
</div>
<?php if ($p['description']): ?><div class="card"><h2>Descrição</h2><p class="pre"><?= e($p['description']) ?></p></div><?php endif; ?>
