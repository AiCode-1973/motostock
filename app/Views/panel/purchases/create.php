<?php
$options = '';
foreach ($products as $p) {
    $options .= '<option value="' . (int) $p['id'] . '" data-cost="' . (int) $p['cost_cents'] . '">' . e($p['name'] . ' · ' . $p['sku']) . '</option>';
}
$row = '<tr><td data-label="Produto"><select name="items[__i__][product_id]" required><option value="">Escolha</option>' . $options . '</select></td>'
    . '<td data-label="Qtd."><input type="number" min="1" name="items[__i__][quantity]" data-qty value="1" required></td>'
    . '<td data-label="Custo unit."><input name="items[__i__][unit_cost]" data-cost inputmode="decimal" placeholder="0,00" required></td>'
    . '<td data-label="Total" class="num" data-line-total>R$ 0,00</td>'
    . '<td><button type="button" class="link-btn" data-remove-row>Remover</button></td></tr>';
?>
<div class="page-head"><div><p><a href="<?= e(url('/painel/compras')) ?>">← Compras</a></p><h1>Nova compra</h1></div></div>
<?php if (!$suppliers || !$products): ?>
  <div class="card"><?php $emptyTitle = 'Faltam cadastros'; $emptyText = !$suppliers ? 'Cadastre pelo menos um fornecedor ativo.' : 'Cadastre pelo menos um produto.'; $emptyAction = !$suppliers ? ['Cadastrar fornecedor', url('/painel/fornecedores/novo')] : ['Cadastrar produto', url('/painel/produtos/novo')]; include __DIR__ . '/../../partials/empty.php'; ?></div>
<?php else: ?>
<form method="post" action="<?= e(url('/painel/compras')) ?>" class="card">
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label class="req" for="supplier_id">Fornecedor</label><select id="supplier_id" name="supplier_id" required><option value="">Escolha</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old('supplier_id', $preselect), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="expected_at">Previsão de entrega</label><input id="expected_at" type="date" name="expected_at" value="<?= e(old('expected_at')) ?>"></div>
    <div class="field span-2"><label for="notes">Observações</label><input id="notes" name="notes" maxlength="1000" value="<?= e(old('notes')) ?>" placeholder="Condição de pagamento, frete, contato…"></div>
  </div>
  <h2>Itens</h2>
  <div class="table-wrap"><table id="po-items" class="stack" data-calc>
    <thead><tr><th>Produto</th><th>Qtd.</th><th>Custo unit. (R$)</th><th class="num">Total</th><th></th></tr></thead>
    <tbody><?= str_replace('__i__', '0', $row) ?></tbody>
    <template><?= $row ?></template>
  </table></div>
  <div class="actions"><button type="button" class="btn btn--ghost btn--sm" data-add-row="po-items">+ Adicionar item</button><span class="muted">Total estimado: <strong data-grand-total="po-items">R$ 0,00</strong></span></div>
  <div class="actions"><button class="btn" type="submit">Salvar como rascunho</button></div>
</form>
<?php endif; ?>
