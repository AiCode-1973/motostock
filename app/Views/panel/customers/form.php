<?php $c = $customer; $v = fn(string $k, $d = '') => old($k, $c[$k] ?? $d); $ro = (!can('customers.manage') || ($c['anonymized_at'] ?? null)) ? ' disabled' : ''; ?>
<div class="page-head">
  <div><p><a href="<?= e(url('/painel/clientes')) ?>">← Clientes</a></p><h1><?= e($c ? $c['name'] : 'Novo cliente') ?></h1>
  <?php if ($c): ?><p>Cliente desde <?= e(dt($c['created_at'], 'd/m/Y')) ?><?= $c['privacy_accepted_at'] ? ' · aceitou a política v' . (int) $c['privacy_version'] . ' em ' . e(dt($c['privacy_accepted_at'], 'd/m/Y')) : '' ?></p><?php endif; ?></div>
  <?php if ($c && can('orders.create') && !$c['anonymized_at']): ?><a class="btn" href="<?= e(url('/painel/pedidos/novo', ['cliente' => $c['id']])) ?>">+ Venda para este cliente</a><?php endif; ?>
</div>
<?php if ($c && $c['anonymized_at']): ?><div class="alert alert--info">Dados pessoais removidos em <?= e(dt($c['anonymized_at'])) ?> a pedido do titular. Os pedidos permanecem sem identificação.</div><?php endif; ?>
<form method="post" action="<?= e(url($c ? '/painel/clientes/' . $c['id'] : '/painel/clientes')) ?>" class="card" novalidate>
  <?= csrf_field() ?>
  <?php if (!empty($query['next'])): ?><input type="hidden" name="next" value="pedido"><?php endif; ?>
  <p class="small muted">Colete só o necessário (LGPD): nome e um contato bastam para a venda. CPF apenas se for emitir nota fiscal.</p>
  <div class="form-grid">
    <div class="field"><label class="req" for="name">Nome</label><input id="name" name="name" value="<?= e($v('name')) ?>" required maxlength="120"<?= $ro ?><?= field_attrs('name') ?>><?= field_error('name') ?></div>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e($v('email')) ?>"<?= $ro ?><?= field_attrs('email') ?>><?= field_error('email') ?></div>
    <div class="field"><label for="phone">Telefone</label><input id="phone" name="phone" type="tel" value="<?= e($v('phone') ? format_phone($v('phone')) : '') ?>"<?= $ro ?><?= field_attrs('phone') ?>><?= field_error('phone') ?></div>
    <div class="field"><label for="document">CPF/CNPJ</label><input id="document" name="document" inputmode="numeric" value="<?= e($v('document') ? format_doc($v('document')) : '') ?>"<?= $ro ?><?= field_attrs('document') ?>><span class="hint">Opcional.</span><?= field_error('document') ?></div>
    <label class="check span-2"><input type="checkbox" name="marketing_opt_in" value="1"<?= checked($v('marketing_opt_in')) ?><?= $ro ?>> Aceita receber ofertas (somente com autorização expressa do cliente)</label>
  </div>
  <?php if (!$ro): ?><div class="actions"><button class="btn" type="submit">Salvar</button></div><?php endif; ?>
</form>
<?php if ($c): ?>
<div class="card">
  <h2>Pedidos</h2>
  <?php if (!$orders): ?><p class="muted">Nenhum pedido.</p><?php else: ?>
  <div class="table-wrap"><table class="stack"><thead><tr><th>Nº</th><th>Data</th><th>Canal</th><th>Situação</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td data-label="Nº"><a href="<?= e(url('/painel/pedidos/' . $o['id'])) ?>"><?= (int) $o['number'] ?></a></td><td data-label="Data"><?= e(dt($o['placed_at'])) ?></td><td data-label="Canal"><?= e(label('channel', $o['channel'])) ?></td><td data-label="Situação"><?= badge('order', $o['status']) ?></td><td data-label="Total" class="num"><?= e(money((int) $o['total_cents'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
<?php if (can('privacy.manage') && !$c['anonymized_at']): ?>
<form class="card" method="post" action="<?= e(url('/painel/clientes/' . $c['id'] . '/anonimizar')) ?>" data-confirm="Remover definitivamente os dados pessoais deste cliente?">
  <h2>Eliminar dados pessoais (LGPD)</h2>
  <?= csrf_field() ?>
  <p class="small muted">Use para atender um pedido de eliminação. Remove nome, e-mail, telefone, documento, endereços e o acesso à loja; mantém valores e itens dos pedidos por obrigação fiscal. <strong>Não pode ser desfeito.</strong></p>
  <div class="filters"><div class="field"><label for="confirm">Digite ANONIMIZAR para confirmar</label><input id="confirm" name="confirm" autocomplete="off" required></div><button class="btn btn--danger" type="submit">Anonimizar cliente</button></div>
</form>
<?php endif; ?>
<?php endif; ?>
