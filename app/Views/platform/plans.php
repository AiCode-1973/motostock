<div class="page-head"><div><h1>Planos</h1><p>Preços e limites são dados, editáveis aqui. Mudanças valem na hora para as lojas do plano.</p></div><?php if ($user['platform_role'] === 'admin'): ?><a class="btn" href="<?= e(url('/plataforma/planos/novo')) ?>">+ Novo plano</a><?php endif; ?></div>
<div class="card">
  <?php if (!$plans): ?><?php $emptyTitle = 'Nenhum plano'; $emptyText = 'Crie ao menos um plano público para liberar o cadastro de lojas.'; $emptyAction = null; include __DIR__ . '/../partials/empty.php'; ?><?php else: ?>
  <div class="table-wrap"><table class="stack"><thead><tr><th>Plano</th><th class="num">Preço</th><th class="num">Teste</th><th class="num">Usuários</th><th class="num">Produtos</th><th class="num">Pedidos/mês</th><th>Visível</th><th class="num">Lojas</th></tr></thead><tbody>
    <?php foreach ($plans as $p): $lim = fn($v) => $v === null ? '∞' : (int) $v; ?><tr>
      <td data-label="Plano"><?= $user['platform_role'] === 'admin' ? '<a href="' . e(url('/plataforma/planos/' . $p['id'])) . '">' . e($p['name']) . '</a>' : e($p['name']) ?> <span class="mono small muted"><?= e($p['code']) ?></span><?= $p['active'] ? '' : ' <span class="badge">inativo</span>' ?></td>
      <td data-label="Preço" class="num"><?= e(money((int) $p['price_cents'])) ?>/<?= $p['billing_interval'] === 'year' ? 'ano' : 'mês' ?></td>
      <td data-label="Teste" class="num"><?= (int) $p['trial_days'] ?> d</td>
      <td data-label="Usuários" class="num"><?= $lim($p['max_users']) ?></td>
      <td data-label="Produtos" class="num"><?= $lim($p['max_products']) ?></td>
      <td data-label="Pedidos/mês" class="num"><?= $lim($p['max_orders_month']) ?></td>
      <td data-label="Visível"><?= $p['is_public'] ? 'Sim' : 'Não' ?></td>
      <td data-label="Lojas" class="num"><?= (int) $p['tenants'] ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
