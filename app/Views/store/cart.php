<h1>Carrinho</h1>
<?php foreach ($cart['issues'] as $issue): ?><div class="alert alert--warn"><?= e($issue) ?></div><?php endforeach; ?>
<?php if (!$cart['lines']): ?>
  <div class="card"><?php $emptyTitle = 'Seu carrinho está vazio'; $emptyText = 'Encontre a peça certa filtrando pela sua moto.'; $emptyAction = ['Ver peças', store_url('/')]; include __DIR__ . '/../partials/empty.php'; ?></div>
<?php else: ?>
<div class="grid grid-2">
  <form class="card" method="post" action="<?= e(store_url('/carrinho/atualizar')) ?>">
    <?= csrf_field() ?>
    <div class="table-wrap"><table class="stack">
      <thead><tr><th>Produto</th><th>Qtd.</th><th class="num">Total</th><th></th></tr></thead>
      <tbody><?php foreach ($cart['lines'] as $l): ?><tr>
        <td data-label="Produto"><a href="<?= e(store_url('/produto/' . $l['slug'])) ?>"><?= e($l['name']) ?></a><br><span class="small muted"><?= e(money((int) $l['price_cents'])) ?> cada<?= $l['fitment'] ? ' · para ' . e($l['fitment']) : '' ?></span></td>
        <td data-label="Qtd."><label class="sr-only" for="q<?= (int) $l['id'] ?>">Quantidade</label><input id="q<?= (int) $l['id'] ?>" type="number" name="qty[<?= (int) $l['id'] ?>]" min="0" max="<?= (int) $l['available'] ?>" value="<?= (int) $l['qty'] ?>"></td>
        <td data-label="Total" class="num"><?= e(money((int) $l['total'])) ?></td>
        <td><button class="link-btn" type="submit" name="remove" value="<?= (int) $l['id'] ?>" data-no-loading>Remover</button></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <div class="actions"><button class="btn btn--ghost" type="submit">Atualizar quantidades</button><a href="<?= e(store_url('/')) ?>">Continuar comprando</a></div>
  </form>
  <div class="card">
    <h2>Resumo</h2>
    <div class="summary-line"><span>Subtotal</span><strong><?= e(money($cart['subtotal'])) ?></strong></div>
    <h3>Frete</h3>
    <?php if (!$methods): ?><p class="small muted">A loja ainda não configurou formas de entrega.</p><?php else: ?>
      <ul class="small"><?php foreach ($methods as $m): ?><li><?= e($m['name']) ?>: <strong><?= $m['cost'] === 0 ? 'grátis' : e(money($m['cost'])) ?></strong> · <?= e($m['days']) ?><?= $m['regions'] ? ' · atende ' . e($m['regions']) : '' ?></li><?php endforeach; ?></ul>
      <p class="small muted">Você escolhe a entrega na próxima etapa. Prazo conta a partir da confirmação do pagamento.</p>
    <?php endif; ?>
    <a class="btn btn--block" href="<?= e(store_url('/checkout')) ?>">Finalizar compra</a>
  </div>
</div>
<?php endif; ?>
