<?php $p = $plan; $v = fn(string $k, $d = '') => old($k, $p[$k] ?? $d); ?>
<div class="page-head"><div><p><a href="<?= e(url('/plataforma/planos')) ?>">← Planos</a></p><h1><?= e($p ? $p['name'] : 'Novo plano') ?></h1></div></div>
<form class="card" method="post" action="<?= e(url($p ? '/plataforma/planos/' . $p['id'] : '/plataforma/planos')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label class="req" for="name">Nome</label><input id="name" name="name" value="<?= e($v('name')) ?>" required<?= field_attrs('name') ?>><?= field_error('name') ?></div>
    <div class="field"><label class="req" for="code">Código</label><input id="code" name="code" value="<?= e($v('code')) ?>" required<?= field_attrs('code') ?>><span class="hint">Identificador interno, ex.: essencial</span><?= field_error('code') ?></div>
    <div class="field"><label class="req" for="price">Preço (R$)</label><input id="price" name="price" inputmode="decimal" value="<?= e(old('price', $p ? money_input((int) $p['price_cents']) : '')) ?>" required<?= field_attrs('price') ?>><?= field_error('price') ?></div>
    <div class="field"><label for="billing_interval">Cobrança</label><select id="billing_interval" name="billing_interval"><option value="month"<?= selected($v('billing_interval', 'month'), 'month') ?>>Mensal</option><option value="year"<?= selected($v('billing_interval'), 'year') ?>>Anual</option></select></div>
    <div class="field"><label for="trial_days">Dias de teste</label><input id="trial_days" name="trial_days" type="number" min="0" value="<?= e($v('trial_days', 0)) ?>"></div>
    <div class="field"><label for="sort_order">Ordem de exibição</label><input id="sort_order" name="sort_order" type="number" value="<?= e($v('sort_order', 0)) ?>"></div>
    <div class="field"><label for="max_users">Máx. usuários</label><input id="max_users" name="max_users" type="number" min="1" value="<?= e($v('max_users')) ?>"><span class="hint">Vazio = sem limite</span></div>
    <div class="field"><label for="max_products">Máx. produtos</label><input id="max_products" name="max_products" type="number" min="1" value="<?= e($v('max_products')) ?>"></div>
    <div class="field"><label for="max_orders_month">Máx. pedidos/mês</label><input id="max_orders_month" name="max_orders_month" type="number" min="1" value="<?= e($v('max_orders_month')) ?>"></div>
    <div class="field span-2"><label for="description">Descrição</label><input id="description" name="description" maxlength="255" value="<?= e($v('description')) ?>"></div>
    <label class="check"><input type="checkbox" name="allow_custom_domain" value="1"<?= checked($v('allow_custom_domain')) ?>> Permite domínio próprio</label>
    <label class="check"><input type="checkbox" name="is_public" value="1"<?= checked($v('is_public', 1)) ?>> Visível no site e no cadastro</label>
    <label class="check"><input type="checkbox" name="active" value="1"<?= checked($v('active', 1)) ?>> Ativo</label>
  </div>
  <div class="actions"><button class="btn" type="submit">Salvar plano</button></div>
</form>
