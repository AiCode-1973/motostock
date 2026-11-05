<div class="card">
  <h1>Instalação do MotoStock</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>
  <h2>Verificação do servidor</h2>
  <ul class="list-plain">
    <?php foreach ($checks as $c): ?>
      <li><span><?= e($c['label']) ?><?php if (!$c['ok'] && $c['hint']): ?><br><span class="small muted"><?= e($c['hint']) ?></span><?php endif; ?></span>
        <span class="badge <?= $c['ok'] ? 'badge--order-paid' : ($c['required'] ? 'badge--order-cancelled' : 'badge--order-awaiting_payment') ?>"><?= $c['ok'] ? 'OK' : ($c['required'] ? 'Corrigir' : 'Atenção') ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>

<?php if ($blocking): ?>
  <div class="alert alert--error">Corrija os itens marcados e recarregue a página.</div>
<?php else: ?>
  <form class="card" method="post" action="<?= e(url('/instalacao')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <h2>Banco de dados</h2>
    <p><?= $pending ? 'Atualizações pendentes: <strong>' . e(implode(', ', $pending)) . '</strong>.' : 'Banco atualizado.' ?></p>
    <?php if (!$hasAdmin): ?>
      <h2>Primeiro administrador da plataforma</h2>
      <p class="small muted">Esta conta gerencia lojas, planos e auditoria. Depois de entrar, o sistema exige a verificação em duas etapas.</p>
      <div class="field"><label class="req" for="name">Nome</label><input id="name" name="name" value="<?= e(old('name')) ?>" required<?= field_attrs('name') ?>><?= field_error('name') ?></div><br>
      <div class="field"><label class="req" for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autocomplete="username"<?= field_attrs('email') ?>><?= field_error('email') ?></div><br>
      <div class="field"><label class="req" for="password">Senha</label><input id="password" name="password" type="password" minlength="12" required autocomplete="new-password"<?= field_attrs('password') ?>><span class="hint">Mínimo de 12 caracteres.</span><?= field_error('password') ?></div><br>
      <div class="field"><label class="req" for="password_confirmation">Confirme</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"<?= field_attrs('password_confirmation') ?>><?= field_error('password_confirmation') ?></div>
      <div class="actions"><button class="btn" type="submit" data-loading="Instalando…">Instalar e criar administrador</button></div>
    <?php else: ?>
      <div class="actions"><button class="btn" type="submit"<?= $pending ? '' : ' disabled' ?>>Aplicar atualizações</button><a href="<?= e(url('/entrar')) ?>">Ir para o login</a></div>
    <?php endif; ?>
  </form>

  <?php if ($hasAdmin): ?>
  <div class="card">
    <h2>Próximos passos</h2>
    <ol>
      <li>Entre com o administrador e ative a verificação em duas etapas.</li>
      <li><?= $plans ? "Planos públicos: {$plans}." : '<strong>Crie ao menos um plano público</strong> em Plataforma › Planos: sem plano, o cadastro de lojas fica fechado.' ?></li>
      <li>Em cPanel › Cron Jobs, agende a cada 15 minutos:<div class="code-box"><?= e($cron) ?></div></li>
      <li><strong>Apague a linha SETUP_TOKEN do .env</strong> (ou deixe vazia). Gere um token novo só quando for atualizar.</li>
    </ol>
  </div>
  <?php endif; ?>
<?php endif; ?>
