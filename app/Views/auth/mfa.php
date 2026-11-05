<div class="auth">
  <form method="post" action="<?= e(url('/entrar/verificacao')) ?>" class="card">
    <h1>Verificação em duas etapas</h1>
    <p class="muted">Digite o código de 6 números do seu aplicativo autenticador.</p>
    <?= csrf_field() ?>
    <div class="field"><label for="code">Código</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus<?= field_attrs('code') ?>><?= field_error('code') ?></div>
    <div class="actions"><button class="btn btn--block" type="submit">Confirmar</button></div>
    <p class="small"><a href="<?= e(url('/entrar')) ?>">Voltar</a></p>
  </form>
</div>
