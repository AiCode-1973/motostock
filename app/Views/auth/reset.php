<div class="auth">
  <div class="card">
    <h1>Definir nova senha</h1>
    <?php if (!$valid): ?>
      <div class="alert alert--error">Este link é inválido ou expirou.</div>
      <a class="btn" href="<?= e(url('/esqueci-senha')) ?>">Pedir um novo link</a>
    <?php else: ?>
      <form method="post" action="<?= e(url('/redefinir-senha/' . $token)) ?>">
        <?= csrf_field() ?>
        <div class="field"><label for="password">Nova senha</label><input id="password" name="password" type="password" minlength="10" required autocomplete="new-password"<?= field_attrs('password') ?>><span class="hint">Mínimo de 10 caracteres.</span><?= field_error('password') ?></div>
        <br>
        <div class="field"><label for="password_confirmation">Confirme</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"<?= field_attrs('password_confirmation') ?>><?= field_error('password_confirmation') ?></div>
        <div class="actions"><button class="btn btn--block" type="submit">Salvar senha</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>
