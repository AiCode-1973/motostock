<div class="page-head">
  <div><h1>Clientes</h1><p><?= (int) $result['total'] ?> cliente(s)</p></div>
  <?php if (can('customers.manage')): ?><a class="btn" href="<?= e(url('/painel/clientes/novo')) ?>">+ Novo cliente</a><?php endif; ?>
</div>
<form class="filters card" method="get" action="<?= e(url('/painel/clientes')) ?>">
  <div class="field field--wide"><label for="q">Buscar</label><input id="q" type="search" name="q" value="<?= e($query['q'] ?? '') ?>" placeholder="Nome, e-mail ou telefone"></div>
  <button class="btn btn--ghost" type="submit">Buscar</button>
</form>
<div class="card">
  <?php if (!$result['items']): ?>
    <?php $emptyTitle = 'Nenhum cliente'; $emptyText = 'Clientes são cadastrados nas compras da loja virtual ou aqui, para vendas no balcão.'; $emptyAction = can('customers.manage') ? ['Cadastrar cliente', url('/painel/clientes/novo')] : null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th class="num">Pedidos</th><th>Conta na loja</th></tr></thead>
    <tbody><?php foreach ($result['items'] as $c): ?><tr>
      <td data-label="Nome"><a href="<?= e(url('/painel/clientes/' . $c['id'])) ?>"><?= e($c['name']) ?></a><?= $c['is_demo'] ? ' <span class="badge badge--demo">demo</span>' : '' ?></td>
      <td data-label="E-mail"><?= e($c['email'] ?? '—') ?></td>
      <td data-label="Telefone"><?= e(format_phone($c['phone'])) ?: '—' ?></td>
      <td data-label="Pedidos" class="num"><?= (int) $c['orders_count'] ?></td>
      <td data-label="Conta"><?= $c['anonymized_at'] ? '<span class="muted">Anonimizado</span>' : ($c['password_hash'] ? 'Sim' : 'Não') ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php include __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</div>
