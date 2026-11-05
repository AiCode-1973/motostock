<?php $s = $supplier; $v = fn(string $k, $d = '') => old($k, $s[$k] ?? $d); $ro = can('suppliers.manage') ? '' : ' disabled'; ?>
<div class="page-head">
  <div><p><a href="<?= e(url('/painel/fornecedores')) ?>">← Fornecedores</a></p><h1><?= e($s ? $s['name'] : 'Novo fornecedor') ?></h1></div>
  <?php if ($s && can('purchases.manage')): ?><a class="btn" href="<?= e(url('/painel/compras/nova', ['fornecedor' => $s['id']])) ?>">+ Nova compra</a><?php endif; ?>
</div>
<form method="post" action="<?= e(url($s ? '/painel/fornecedores/' . $s['id'] : '/painel/fornecedores')) ?>" class="card" novalidate>
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label class="req" for="name">Razão social / nome</label><input id="name" name="name" value="<?= e($v('name')) ?>" required maxlength="160"<?= $ro ?><?= field_attrs('name') ?>><?= field_error('name') ?></div>
    <div class="field"><label for="document">CNPJ/CPF</label><input id="document" name="document" value="<?= e($v('document') ? format_doc($v('document')) : '') ?>" inputmode="numeric"<?= $ro ?><?= field_attrs('document') ?>><?= field_error('document') ?></div>
    <div class="field"><label for="contact_name">Contato</label><input id="contact_name" name="contact_name" value="<?= e($v('contact_name')) ?>" maxlength="120"<?= $ro ?>></div>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e($v('email')) ?>"<?= $ro ?><?= field_attrs('email') ?>><?= field_error('email') ?></div>
    <div class="field"><label for="phone">Telefone</label><input id="phone" name="phone" type="tel" value="<?= e($v('phone') ? format_phone($v('phone')) : '') ?>"<?= $ro ?><?= field_attrs('phone') ?>><?= field_error('phone') ?></div>
    <div class="field span-2"><label for="notes">Observações</label><textarea id="notes" name="notes" maxlength="2000"<?= $ro ?> placeholder="Prazo médio, condições de pagamento, pedido mínimo…"><?= e($v('notes')) ?></textarea></div>
    <?php if ($s): ?><label class="check"><input type="checkbox" name="active" value="1"<?= checked($v('active', 1)) ?><?= $ro ?>> Ativo</label><?php endif; ?>
  </div>
  <?php if (!$ro): ?><div class="actions"><button class="btn" type="submit">Salvar</button></div><?php endif; ?>
</form>
<?php if ($s): ?>
<div class="grid grid-2">
  <div class="card">
    <h2>Compras</h2>
    <?php if (!$purchases): ?><p class="muted">Nenhuma compra com este fornecedor.</p><?php else: ?>
    <ul class="list-plain"><?php foreach ($purchases as $po): ?><li><a href="<?= e(url('/painel/compras/' . $po['id'])) ?>">nº <?= (int) $po['number'] ?> · <?= e(dt($po['created_at'], 'd/m/Y')) ?></a><span><?= badge('purchase', $po['status']) ?> <?= e(money((int) $po['total_cents'])) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Produtos recebidos deste fornecedor</h2>
    <?php if (!$products): ?><p class="muted">Nenhum recebimento ainda.</p><?php else: ?>
    <ul class="list-plain"><?php foreach ($products as $p): ?><li><span><?= e($p['name']) ?> <span class="mono small muted"><?= e($p['sku']) ?></span></span><span class="nowrap"><?= (int) $p['qty'] ?> un.<?= can('products.cost') ? ' · até ' . e(money((int) $p['last_cost'])) : '' ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
