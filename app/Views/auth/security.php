<div class="auth auth--wide">
  <p><a href="<?= e(url($user['platform_role'] ? '/plataforma' : '/painel')) ?>">← Voltar ao painel</a></p>
  <h1>Segurança da conta</h1>
  <div class="grid grid-2">
    <form method="post" action="<?= e(url('/conta/senha')) ?>" class="card">
      <h2>Trocar senha</h2>
      <?= csrf_field() ?>
      <div class="field"><label for="current_password">Senha atual</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"<?= field_attrs('current_password') ?>><?= field_error('current_password') ?></div><br>
      <div class="field"><label for="password">Nova senha</label><input id="password" name="password" type="password" minlength="10" required autocomplete="new-password"<?= field_attrs('password') ?>><?= field_error('password') ?></div><br>
      <div class="field"><label for="password_confirmation">Confirme</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"<?= field_attrs('password_confirmation') ?>><?= field_error('password_confirmation') ?></div>
      <div class="actions"><button class="btn" type="submit">Salvar</button></div>
      <p class="small muted">Ao trocar a senha, outras sessões abertas são encerradas.</p>
    </form>
    <div class="card">
      <h2>Verificação em duas etapas</h2>
      <?php if ($user['mfa_enabled']): ?>
        <div class="alert alert--success">Ativada. Ao entrar, pediremos o código do aplicativo.</div>
        <form method="post" action="<?= e(url('/conta/mfa/desativar')) ?>" data-confirm="Desativar a verificação em duas etapas deixa sua conta menos protegida. Continuar?">
          <?= csrf_field() ?>
          <div class="field"><label for="cp2">Senha atual para desativar</label><input id="cp2" name="current_password" type="password" required<?= field_attrs('current_password_mfa') ?>><?= field_error('current_password_mfa') ?></div>
          <div class="actions"><button class="btn btn--ghost" type="submit">Desativar</button></div>
        </form>
      <?php elseif ($setupSecret): ?>
        <ol>
          <li>Abra seu app autenticador (Google Authenticator, Microsoft Authenticator, Authy…).</li>
          <li>Adicione uma conta manualmente com esta chave:</li>
        </ol>
        <div class="code-box"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></div>
        <p class="small muted">Tipo: baseado em tempo, 6 dígitos, 30 s. URI: <span class="mono"><?= e($setupUri) ?></span></p>
        <form method="post" action="<?= e(url('/conta/mfa/ativar')) ?>">
          <?= csrf_field() ?>
          <div class="field"><label for="code">Código gerado pelo app</label><input id="code" name="code" inputmode="numeric" maxlength="7" required<?= field_attrs('code') ?>><?= field_error('code') ?></div>
          <div class="actions"><button class="btn" type="submit">Ativar</button></div>
        </form>
      <?php else: ?>
        <p class="muted">Protege sua conta mesmo que alguém descubra sua senha. <?= $user['platform_role'] ? '<strong>Obrigatória para a equipe da plataforma.</strong>' : 'Recomendada para proprietários e gerentes.' ?></p>
        <form method="post" action="<?= e(url('/conta/mfa/iniciar')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Configurar agora</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
