<div class="page-head">
  <div><h1>Fornecedores</h1><p><?= count($items) ?> cadastrado(s)</p></div>
  <?php if (can('suppliers.manage')): ?><a class="btn" href="<?= e(url('/painel/fornecedores/novo')) ?>">+ Novo fornecedor</a><?php endif; ?>
</div>
<form class="filters card" method="get" action="<?= e(url('/painel/fornecedores')) ?>">
  <div class="field field--wide"><label for="q">Buscar</label><input id="q" type="search" name="q" value="<?= e($query['q'] ?? '') ?>" placeholder="Nome, contato ou CNPJ"></div>
  <button class="btn btn--ghost" type="submit">Buscar</button>
</form>
<div class="card">
  <?php if (!$items): ?>
    <?php $emptyTitle = 'Nenhum fornecedor'; $emptyText = 'Cadastre fornecedores para registrar pedidos de compra e entradas de mercadoria.'; $emptyAction = can('suppliers.manage') ? ['Cadastrar fornecedor', url('/painel/fornecedores/novo')] : null; include __DIR__ . '/../../partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="stack">
    <thead><tr><th>Fornecedor</th><th>CNPJ/CPF</th><th>Contato</th><th>Telefone</th><th>Situação</th></tr></thead>
    <tbody><?php foreach ($items as $s): ?><tr>
      <td data-label="Fornecedor"><a href="<?= e(url('/painel/fornecedores/' . $s['id'])) ?>"><?= e($s['name']) ?></a></td>
      <td data-label="CNPJ/CPF"><?= e(format_doc($s['document'])) ?: '—' ?></td>
      <td data-label="Contato"><?= e($s['contact_name'] ?? '—') ?><?= $s['email'] ? '<br><span class="small muted">' . e($s['email']) . '</span>' : '' ?></td>
      <td data-label="Telefone"><?= e(format_phone($s['phone'])) ?: '—' ?></td>
      <td data-label="Situação"><?= $s['active'] ? 'Ativo' : '<span class="muted">Inativo</span>' ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
