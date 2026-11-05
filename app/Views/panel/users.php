<div class="page-head"><div><h1>Equipe</h1><p><?= (int) $usage['users'] ?> de <?= $plan['max_users'] === null ? 'ilimitados' : (int) $plan['max_users'] ?> usuário(s) ativos no plano <?= e($plan['name']) ?></p></div></div>
<div class="grid grid-2">
  <div class="card">
    <h2>Pessoas com acesso</h2>
    <div class="table-wrap"><table class="stack">
      <thead><tr><th>Pessoa</th><th>Função e acesso</th></tr></thead>
      <tbody><?php foreach ($members as $m): ?><tr>
        <td data-label="Pessoa"><strong><?= e($m['name']) ?></strong><br><span class="small muted"><?= e($m['email']) ?> · último acesso <?= e(dt($m['last_login_at'])) ?> · <?= $m['mfa_enabled'] ? 'duas etapas ativa' : 'sem duas etapas' ?></span></td>
        <td data-label="Função">
          <?php if ((int) $m['id'] === (int) $user['id']): ?><?= e(label('role', $m['role'])) ?> <span class="muted small">(você)</span><?php else: ?>
          <form method="post" action="<?= e(url('/painel/usuarios/' . $m['member_id'])) ?>" class="filters">
            <?= csrf_field() ?>
            <label class="sr-only" for="role<?= (int) $m['member_id'] ?>">Função</label>
            <select id="role<?= (int) $m['member_id'] ?>" name="role"><?php foreach (\App\Core\Permissions::ROLES as $k => $l): ?><option value="<?= $k ?>"<?= selected($m['role'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <label class="sr-only" for="st<?= (int) $m['member_id'] ?>">Acesso</label>
            <select id="st<?= (int) $m['member_id'] ?>" name="status"><option value="active"<?= selected($m['status'], 'active') ?>>Ativo</option><option value="disabled"<?= selected($m['status'], 'disabled') ?>>Bloqueado</option></select>
            <button class="btn btn--ghost btn--sm" type="submit">Salvar</button>
          </form><?php endif; ?>
        </td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </div>
  <form class="card" method="post" action="<?= e(url('/painel/usuarios')) ?>" novalidate>
    <h2>Adicionar pessoa</h2>
    <?= csrf_field() ?>
    <div class="field"><label class="req" for="name">Nome</label><input id="name" name="name" value="<?= e(old('name')) ?>" required<?= field_attrs('name') ?>><?= field_error('name') ?></div><br>
    <div class="field"><label class="req" for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required<?= field_attrs('email') ?>><?= field_error('email') ?></div><br>
    <div class="field"><label class="req" for="role">Função</label><select id="role" name="role"><option value="seller"<?= selected(old('role'), 'seller') ?>>Atendente/vendedor</option><option value="manager"<?= selected(old('role'), 'manager') ?>>Gerente</option></select></div>
    <ul class="small muted">
      <li><strong>Atendente/vendedor</strong>: clientes, pedidos e balcão (desconto até 10%); vê produtos e estoque sem custo.</li>
      <li><strong>Gerente</strong>: tudo de vendas, catálogo, estoque, compras, reembolsos e relatórios.</li>
      <li><strong>Proprietário</strong>: também equipe, configurações, pagamentos, privacidade e exportação de dados.</li>
    </ul>
    <div class="actions"><button class="btn" type="submit">Adicionar e enviar convite</button></div>
    <p class="small muted">A pessoa recebe um link por e-mail para criar a própria senha (válido por 72 horas).</p>
  </form>
</div>
