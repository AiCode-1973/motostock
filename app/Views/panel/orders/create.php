<?php
$options = '';
foreach ($products as $p) {
    $disabled = (int) $p['available'] <= 0 ? ' disabled' : '';
    $options .= '<option value="' . (int) $p['id'] . '" data-price="' . (int) $p['price_cents'] . '"' . $disabled . '>' . e($p['name'] . ' · ' . $p['sku'] . ' · ' . money((int) $p['price_cents']) . ' · disp. ' . $p['available']) . '</option>';
}
$row = '<tr><td data-label="Produto"><select name="items[__i__][product_id]" required><option value="">Escolha o produto</option>' . $options . '</select></td>'
    . '<td data-label="Qtd."><input type="number" min="1" max="999" name="items[__i__][quantity]" data-qty value="1" required></td>'
    . '<td data-label="Total" class="num" data-line-total>R$ 0,00</td>'
    . '<td><button type="button" class="link-btn" data-remove-row>Remover</button></td></tr>';
?>
<div class="page-head"><div><p><a href="<?= e(url('/painel/pedidos')) ?>">← Pedidos</a></p><h1>Venda no balcão</h1><p>O preço vem do cadastro do produto. O estoque é reservado ao salvar e baixado quando o pagamento é registrado.</p></div></div>
<?php if (!$products): ?>
  <div class="card"><?php $emptyTitle = 'Nenhum produto ativo'; $emptyText = 'Cadastre produtos para vender.'; $emptyAction = can('products.manage') ? ['Cadastrar produto', url('/painel/produtos/novo')] : null; include __DIR__ . '/../../partials/empty.php'; ?></div>
<?php else: ?>
<form method="post" action="<?= e(url('/painel/pedidos')) ?>" class="card" novalidate>
  <?= csrf_field() ?>
  <h2>Cliente</h2>
  <div class="form-grid">
    <div class="field"><label for="customer_id">Cliente cadastrado</label><select id="customer_id" name="customer_id"><option value="">Consumidor sem cadastro</option><?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('customer_id', $preselect), $c['id']) ?>><?= e($c['name'] . ($c['email'] ? ' · ' . $c['email'] : '')) ?></option><?php endforeach; ?></select>
      <span class="hint"><a href="<?= e(url('/painel/clientes/novo', ['next' => 'pedido'])) ?>">Cadastrar cliente novo</a></span></div>
    <div class="field"><label for="customer_name">Nome (se sem cadastro)</label><input id="customer_name" name="customer_name" value="<?= e(old('customer_name')) ?>" maxlength="120" placeholder="Consumidor (balcão)"></div>
  </div>
  <h2>Itens</h2>
  <div class="table-wrap"><table id="order-items" class="stack" data-calc>
    <thead><tr><th>Produto</th><th>Qtd.</th><th class="num">Total</th><th></th></tr></thead>
    <tbody><?= str_replace('__i__', '0', $row) ?></tbody>
    <template><?= $row ?></template>
  </table></div>
  <div class="actions"><button type="button" class="btn btn--ghost btn--sm" data-add-row="order-items">+ Adicionar item</button><span class="muted">Subtotal estimado: <strong data-grand-total="order-items">R$ 0,00</strong></span></div>

  <h2>Entrega</h2>
  <div class="form-grid">
    <div class="field"><label for="shipping_method_id">Forma</label><select id="shipping_method_id" name="shipping_method_id"><option value="" data-kind="pickup">Retirada imediata no balcão</option><?php foreach ($shipping as $m): ?><option value="<?= (int) $m['id'] ?>" data-kind="<?= e($m['kind']) ?>"<?= selected(old('shipping_method_id'), $m['id']) ?>><?= e($m['name'] . ' · ' . money((int) $m['price_cents'])) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="form-grid is-hidden" data-address>
    <div class="field"><label for="zip">CEP</label><input id="zip" name="zip" inputmode="numeric" value="<?= e(old('zip')) ?>"></div>
    <div class="field"><label for="street">Rua</label><input id="street" name="street" value="<?= e(old('street')) ?>"></div>
    <div class="field"><label for="number">Número</label><input id="number" name="number" value="<?= e(old('number')) ?>"></div>
    <div class="field"><label for="complement">Complemento</label><input id="complement" name="complement" value="<?= e(old('complement')) ?>"></div>
    <div class="field"><label for="district">Bairro</label><input id="district" name="district" value="<?= e(old('district')) ?>"></div>
    <div class="field"><label for="city">Cidade</label><input id="city" name="city" value="<?= e(old('city')) ?>"></div>
    <div class="field"><label for="state">UF</label><select id="state" name="state"><option value="">—</option><?php foreach (\App\Core\Validator::UFS as $uf): ?><option<?= selected(old('state'), $uf) ?>><?= $uf ?></option><?php endforeach; ?></select></div>
  </div>

  <h2>Pagamento</h2>
  <div class="form-grid">
    <div class="field"><label for="discount">Desconto (R$)</label><input id="discount" name="discount" inputmode="decimal" value="<?= e(old('discount')) ?>" placeholder="0,00"<?= field_attrs('discount') ?>><?php if ($discountLimit): ?><span class="hint">Seu perfil permite até <?= (int) $discountLimit ?>% do subtotal.</span><?php endif; ?><?= field_error('discount') ?></div>
    <div class="field"><label for="pay_now">Recebido agora?</label><select id="pay_now" name="pay_now">
      <option value="">Não: deixar aguardando pagamento</option>
      <?php foreach (['cash', 'pix_manual', 'card_pos', 'transfer'] as $m): ?><option value="<?= $m ?>"<?= selected(old('pay_now'), $m) ?>>Sim: <?= e(label('pay_method', $m)) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field span-2"><label for="notes">Observações</label><input id="notes" name="notes" maxlength="500" value="<?= e(old('notes')) ?>"></div>
  </div>
  <div class="actions"><button class="btn" type="submit" data-loading="Registrando…">Registrar venda</button></div>
</form>
<?php endif; ?>
