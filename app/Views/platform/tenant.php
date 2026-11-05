<?php $tz = 'America/Sao_Paulo'; $isAdmin = $user['platform_role'] === 'admin'; $statusAction = ['active' => 'Ativar / reativar', 'past_due' => 'Marcar pagamento pendente', 'suspended' => 'Suspender', 'cancelled' => 'Cancelar conta']; ?>
<div class="page-head">
  <div><p><a href="<?= e(url('/plataforma/lojas')) ?>">← Lojas</a></p><h1><?= e($t['trade_name']) ?> <?= badge('tenant', $t['status']) ?></h1>
    <p><?= e($t['legal_name']) ?> · CNPJ <?= e(format_doc($t['document'])) ?> · <?= e($t['city'] . '/' . $t['state']) ?></p></div>
  <?php if ($isAdmin): ?><a class="btn btn--ghost" href="<?= e(url('/plataforma/lojas/' . $t['id'] . '/exportar', ['motivo' => 'solicitação administrativa'])) ?>">Exportar dados (auditado)</a><?php endif; ?>
</div>
<?php if ($t['status_reason']): ?><div class="alert alert--info">Motivo registrado: <?= e($t['status_reason']) ?></div><?php endif; ?>
<div class="grid grid-2">
  <div class="card">
    <h2>Conta</h2>
    <dl class="dl">
      <dt>Endereço</dt><dd class="mono"><?= e($t['slug']) ?></dd>
      <dt>Plano</dt><dd><?= e($plan['name']) ?> · <?= e(money((int) $plan['price_cents'])) ?></dd>
      <dt>Assinatura</dt><dd><?= $subscription ? badge('subscription', $subscription['status']) : '—' ?></dd>
      <dt>Criada</dt><dd><?= e(dt($t['created_at'], 'd/m/Y H:i', $tz)) ?></dd>
      <?php if ($t['trial_ends_at']): ?><dt>Teste até</dt><dd><?= e(dt($t['trial_ends_at'], 'd/m/Y', $tz)) ?></dd><?php endif; ?>
      <?php if ($t['suspended_at']): ?><dt>Suspensa em</dt><dd><?= e(dt($t['suspended_at'], 'd/m/Y H:i', $tz)) ?></dd><?php endif; ?>
      <?php if ($t['cancelled_at']): ?><dt>Cancelada em</dt><dd><?= e(dt($t['cancelled_at'], 'd/m/Y H:i', $tz)) ?></dd><dt>Retenção até</dt><dd><?= e(dt($t['retention_until'], 'd/m/Y', $tz)) ?></dd><?php endif; ?>
      <dt>Domínios</dt><dd><?php foreach ($domains as $d): ?><span class="mono small"><?= e($d['host']) ?></span> (<?= $d['kind'] === 'custom' ? 'próprio' : 'subdomínio' ?><?= $d['verified_at'] ? '' : ', não verificado' ?>)<br><?php endforeach; ?></dd>
      <dt>Proprietários</dt><dd><?php foreach ($owners as $o): ?><?= e($o['name']) ?> · <?= e($o['email']) ?> <?= $o['mfa_enabled'] ? '<span class="badge">2 etapas</span>' : '' ?><br><?php endforeach; ?></dd>
    </dl>
  </div>
  <div class="card">
    <h2>Uso</h2>
    <ul class="list-plain">
      <li><span>Produtos</span><strong><?= (int) $usage['products'] ?><?= $plan['max_products'] !== null ? ' / ' . (int) $plan['max_products'] : '' ?></strong></li>
      <li><span>Usuários ativos</span><strong><?= (int) $usage['users'] ?><?= $plan['max_users'] !== null ? ' / ' . (int) $plan['max_users'] : '' ?></strong></li>
      <li><span>Pedidos no mês</span><strong><?= (int) $usage['orders'] ?><?= $plan['max_orders_month'] !== null ? ' / ' . (int) $plan['max_orders_month'] : '' ?></strong></li>
    </ul>
  </div>
  <?php if ($isAdmin): ?>
  <form class="card" method="post" action="<?= e(url('/plataforma/lojas/' . $t['id'] . '/status')) ?>" data-confirm="Confirmar mudança de situação desta loja?">
    <h2>Situação</h2>
    <?= csrf_field() ?>
    <?php if (!$next): ?><p class="muted">Sem mudanças possíveis.</p><?php else: ?>
    <div class="field"><label for="to">Nova situação</label><select id="to" name="to"><?php foreach ($next as $s): ?><option value="<?= e($s) ?>"><?= e($statusAction[$s] ?? $s) ?></option><?php endforeach; ?></select></div><br>
    <div class="field"><label class="req" for="reason">Motivo</label><input id="reason" name="reason" required maxlength="255" placeholder="Ex.: inadimplência há 30 dias; pedido do cliente"></div>
    <p class="small muted">Suspensão tira a loja do ar e bloqueia o painel (o proprietário ainda vê a assinatura e exporta dados). Cancelamento mantém os dados por <?= (int) \App\Core\Env::int('TENANT_RETENTION_DAYS', 90) ?> dias. Nada é apagado automaticamente.</p>
    <div class="actions"><button class="btn" type="submit">Aplicar</button></div>
    <?php endif; ?>
  </form>
  <form class="card" method="post" action="<?= e(url('/plataforma/lojas/' . $t['id'] . '/plano')) ?>">
    <h2>Plano</h2>
    <?= csrf_field() ?>
    <div class="field"><label for="plan_id">Mudar para</label><select id="plan_id" name="plan_id"><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], $t['plan_id']) ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
    <div class="actions"><button class="btn btn--ghost" type="submit">Mudar plano</button></div>
  </form>
  <?php endif; ?>
</div>
<div class="card">
  <h2>Eventos da conta</h2>
  <?php if (!$events): ?><p class="muted">Nenhum.</p><?php else: ?>
  <div class="table-wrap"><table class="stack"><thead><tr><th>Data</th><th>Ação</th><th>Por</th><th>Detalhes</th></tr></thead><tbody>
    <?php foreach ($events as $ev): ?><tr><td data-label="Data" class="nowrap"><?= e(dt($ev['created_at'], 'd/m/Y H:i', $tz)) ?></td><td data-label="Ação" class="mono"><?= e($ev['action']) ?></td><td data-label="Por"><?= e($ev['user_name'] ?? 'Sistema') ?></td><td data-label="Detalhes" class="small mono"><?= e($ev['meta'] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
