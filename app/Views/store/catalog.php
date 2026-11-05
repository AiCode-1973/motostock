<?php $f = $filters; $hasFilter = $f['q'] !== '' || $f['category_id'] || $f['brand_id'] || $f['make'] !== '' || $f['available']; ?>
<div class="catalog">
  <aside>
    <details class="card" open>
      <summary><strong>Filtrar peças</strong></summary>
      <form method="get" action="<?= e(store_url('/')) ?>" class="filter-body">
        <?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
        <fieldset>
          <legend>Minha moto</legend>
          <div class="field"><label for="moto">Marca</label><select id="moto" name="moto" data-autosubmit><option value="">Qualquer</option><?php foreach ($makes as $m): ?><option<?= selected($f['make'], $m) ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="modelo">Modelo</label><select id="modelo" name="modelo"<?= $f['make'] === '' ? ' disabled' : '' ?>><option value="">Qualquer</option><?php foreach ($models as $m): ?><option<?= selected($f['model'], $m['model']) ?>><?= e($m['model']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="ano">Ano</label><input id="ano" name="ano" inputmode="numeric" maxlength="4" value="<?= e($f['year'] ?: '') ?>" placeholder="2020"></div>
          <?php if (!$makes): ?><p class="small muted">A loja ainda não cadastrou compatibilidades.</p><?php endif; ?>
        </fieldset>
        <div class="field"><label for="categoria">Categoria</label><select id="categoria" name="categoria"><option value="">Todas</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($f['category_id'], $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><br>
        <div class="field"><label for="marca">Marca da peça</label><select id="marca" name="marca"><option value="">Todas</option><?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>"<?= selected($f['brand_id'], $b['id']) ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div><br>
        <div class="field"><label for="ordem">Ordenar</label><select id="ordem" name="ordem"><option value="name">Nome</option><option value="price_asc"<?= selected($sort, 'price_asc') ?>>Menor preço</option><option value="price_desc"<?= selected($sort, 'price_desc') ?>>Maior preço</option><option value="newest"<?= selected($sort, 'newest') ?>>Novidades</option></select></div><br>
        <label class="check"><input type="checkbox" name="disponivel" value="1"<?= checked($f['available']) ?>> Só com estoque</label>
        <div class="actions"><button class="btn btn--block" type="submit">Aplicar filtros</button><?php if ($hasFilter): ?><a href="<?= e(store_url('/')) ?>">Limpar</a><?php endif; ?></div>
      </form>
    </details>
  </aside>
  <section aria-labelledby="titulo-catalogo">
    <h1 id="titulo-catalogo"><?= $f['q'] !== '' ? 'Resultados para “' . e($f['q']) . '”' : ($f['make'] !== '' ? 'Peças para ' . e(trim($f['make'] . ' ' . $f['model'] . ' ' . ($f['year'] ?: ''))) : 'Peças e acessórios') ?></h1>
    <p class="muted"><?= (int) $result['total'] ?> produto(s)</p>
    <?php if (!$result['items']): ?>
      <div class="card"><?php $emptyTitle = $hasFilter ? 'Nada encontrado com esses filtros' : 'Catálogo em montagem'; $emptyText = $hasFilter ? 'Tente outro termo, remova filtros ou fale com a loja: podemos ter a peça sob encomenda.' : 'Em breve os produtos estarão aqui.'; $emptyAction = $hasFilter ? ['Ver todas as peças', store_url('/')] : null; include __DIR__ . '/../partials/empty.php'; ?></div>
    <?php else: ?>
    <div class="products">
      <?php foreach ($result['items'] as $p): ?>
        <a class="product-card" href="<?= e(store_url('/produto/' . $p['slug'])) ?>">
          <div class="thumb"><?php if ($p['image_id']): ?><img src="<?= e(store_url('/midia/' . $p['image_id'])) ?>" alt="" loading="lazy"><?php else: ?>Sem foto<?php endif; ?></div>
          <div class="body">
            <span class="small muted"><?= $p['brand_name'] ? e($p['brand_name']) . ' · ' : '' ?>SKU <?= e($p['sku']) ?></span>
            <span class="name"><?= e($p['name']) ?></span>
            <span class="<?= (int) $p['available'] > 0 ? 'stock-ok' : 'stock-out' ?>"><?= (int) $p['available'] > 0 ? 'Em estoque' : 'Indisponível' ?></span>
            <span class="price"><?= e(money((int) $p['price_cents'])) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
  </section>
</div>
