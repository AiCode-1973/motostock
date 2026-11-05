<div class="page-head"><div><p><a href="<?= e(url('/painel/estoque')) ?>">← Estoque</a></p><h1>Movimentar estoque</h1><p>Toda movimentação fica no histórico com seu nome, data e motivo.</p></div></div>
<?php if (!$products): ?>
  <div class="card"><?php $emptyTitle = 'Nenhum produto ativo'; $emptyText = 'Cadastre um produto antes de movimentar o estoque.'; $emptyAction = ['Cadastrar produto', url('/painel/produtos/novo')]; include __DIR__ . '/../../partials/empty.php'; ?></div>
<?php else: ?>
<form method="post" action="<?= e(url('/painel/estoque/movimentar')) ?>" class="card" novalidate>
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field span-2"><label class="req" for="product_id">Produto</label>
      <select id="product_id" name="product_id" required>
        <option value="">Escolha o produto</option>
        <?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected(old('product_id', $product['id'] ?? ''), $p['id']) ?>><?= e($p['name'] . ' · ' . $p['sku'] . ' · físico ' . $p['stock_qty'] . ' / reservado ' . $p['reserved_qty']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label class="req" for="type">Tipo</label>
      <select id="type" name="type" required>
        <option value="entry"<?= selected(old('type'), 'entry') ?>>Entrada (soma ao saldo)</option>
        <option value="exit"<?= selected(old('type'), 'exit') ?>>Saída (perda, avaria, uso interno)</option>
        <option value="adjust"<?= selected(old('type'), 'adjust') ?>>Ajuste por contagem (define o saldo físico)</option>
        <option value="return"<?= selected(old('type'), 'return') ?>>Devolução de cliente (soma ao saldo)</option>
      </select>
      <span class="hint">Entradas de fornecedor: prefira registrar pelo recebimento da compra.</span></div>
    <div class="field"><label class="req" for="quantity">Quantidade</label><input id="quantity" name="quantity" type="number" min="0" required value="<?= e(old('quantity')) ?>"><span class="hint">No ajuste, informe a quantidade contada.</span></div>
    <div class="field span-2"><label class="req" for="reason">Motivo</label><input id="reason" name="reason" maxlength="255" required value="<?= e(old('reason')) ?>" placeholder="Ex.: inventário mensal, peça avariada no transporte"></div>
  </div>
  <div class="actions"><button class="btn" type="submit">Registrar movimentação</button></div>
</form>
<?php endif; ?>
